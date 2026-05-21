"""
TikTok LIVE Tracking Service
=============================
FastAPI server quản lý các phiên theo dõi TikTok LIVE.

Chạy:
    uvicorn service:app --host 0.0.0.0 --port 50000

API:
    POST /sessions/start        → Bắt đầu theo dõi
    GET  /sessions/{id}         → Lấy thông tin + events
    GET  /sessions/{id}/stats   → Chỉ lấy thống kê
    GET  /sessions/{id}/snapshot→ Capture ảnh + audio từ stream
    POST /sessions/{id}/stop    → Dừng theo dõi
    GET  /sessions              → Danh sách sessions
    GET  /health                → Health check
"""

# Fix SSL cho Windows Server cũ (thiếu root CA certificates)
import ssl
import certifi
ssl._create_default_https_context = lambda: ssl.create_default_context(cafile=certifi.where())
import os
os.environ["SSL_CERT_FILE"] = certifi.where()

import asyncio
import base64
import os
import subprocess
import sys
import tempfile
import time
import uuid
import logging
from collections import deque
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Header, Query
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

from TikTokLive import TikTokLiveClient
from TikTokLive.events import (
    ConnectEvent,
    DisconnectEvent,
    CommentEvent,
    GiftEvent,
    LikeEvent,
    FollowEvent,
    JoinEvent,
    ShareEvent,
    LiveEndEvent,
    LivePauseEvent,
    LiveUnpauseEvent,
    RoomUserSeqEvent,
)

# --- Resolve base dir (PyInstaller exe hoặc script) ---
if getattr(sys, 'frozen', False):
    BASE_DIR = Path(sys.executable).parent
else:
    BASE_DIR = Path(__file__).parent

load_dotenv(BASE_DIR / ".env")

# --- Config ---
SERVICE_API_KEY = os.getenv("SERVICE_API_KEY", "change-me")
SERVICE_PORT = int(os.getenv("SERVICE_PORT", "50000"))
MAX_EVENTS_PER_SESSION = 50000  # giữ tối đa N events gần nhất trong memory (~50MB/session)

# --- Logging (cả console + file) ---
log_file = BASE_DIR / "service.log"
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler(log_file, encoding="utf-8"),
    ],
)
logger = logging.getLogger("tiktok-live-service")

# --- App ---
app = FastAPI(title="TikTok LIVE Service", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


# ============================================================
# Auth
# ============================================================
def verify_api_key(x_service_key: str = Header(None)):
    if x_service_key != SERVICE_API_KEY:
        raise HTTPException(status_code=403, detail="Invalid API key")


# ============================================================
# Data Structures
# ============================================================
class SessionInfo:
    """Quản lý 1 phiên theo dõi TikTok LIVE."""

    def __init__(self, session_id: str, tiktok_username: str):
        self.session_id = session_id
        self.tiktok_username = tiktok_username
        self.status = "connecting"  # connecting | live | ended | error
        self.created_at = datetime.now(timezone.utc).isoformat()
        self.connected_at: Optional[str] = None
        self.ended_at: Optional[str] = None
        self.error: Optional[str] = None

        # Room info (populated on connect)
        self.room_id: Optional[str] = None
        self.title: Optional[str] = None
        self.streamer_nickname: Optional[str] = None
        self.streamer_username: Optional[str] = None
        self.streamer_followers: Optional[int] = None

        # Stream URL (HLS m3u8) — dùng cho FFmpeg capture
        self.stream_url: Optional[str] = None

        # Snapshot cache — tránh gọi FFmpeg quá nhiều
        self._snapshot_cache: Optional[dict] = None
        self._snapshot_at: float = 0

        # Live stats (updated realtime)
        self.stats = {
            "viewer_count": 0,
            "total_views": 0,
            "total_likes": 0,
            "total_comments": 0,
            "total_gifts": 0,
            "total_follows": 0,
            "total_shares": 0,
            "total_joins": 0,
        }

        # Events buffer (recent events, capped)
        self.events: deque = deque(maxlen=MAX_EVENTS_PER_SESSION)

        # Internal
        self._client: Optional[TikTokLiveClient] = None
        self._task: Optional[asyncio.Task] = None

    def add_event(self, event_type: str, nickname: str = "", unique_id: str = "", user_id: str = "", data: dict = None):
        self.events.append({
            "type": event_type,
            "nickname": nickname,
            "unique_id": unique_id,
            "user_id": user_id,
            "data": data or {},
            "timestamp": datetime.now(timezone.utc).isoformat(),
        })

    def to_dict(self, include_events: bool = False, since: float = 0, limit: int = 100):
        result = {
            "session_id": self.session_id,
            "tiktok_username": self.tiktok_username,
            "status": self.status,
            "created_at": self.created_at,
            "connected_at": self.connected_at,
            "ended_at": self.ended_at,
            "error": self.error,
            "room_id": self.room_id,
            "title": self.title,
            "streamer": {
                "nickname": self.streamer_nickname,
                "username": self.streamer_username,
                "followers": self.streamer_followers,
            },
            "stats": self.stats.copy(),
        }
        if include_events:
            events = list(self.events)
            if since > 0:
                cutoff = datetime.fromtimestamp(since, tz=timezone.utc).isoformat()
                events = [e for e in events if e["timestamp"] > cutoff]
            result["events"] = events[-limit:]
            result["total_events_buffered"] = len(self.events)
        return result


# Session storage (in-memory)
sessions: dict[str, SessionInfo] = {}


# ============================================================
# Helpers
# ============================================================
def extract_user(event) -> dict:
    """Trích xuất user info từ event TikTok."""
    user = getattr(event, "user", None) or getattr(event, "user_info", None) or getattr(event, "from_user", None)

    # Lấy avatar URL từ avatar_thumb (ImageModel có m_urls: list[str])
    avatar_url = ""
    avatar_thumb = getattr(user, "avatar_thumb", None)
    if avatar_thumb:
        urls = getattr(avatar_thumb, "m_urls", [])
        if urls:
            avatar_url = urls[0]

    return {
        "nickname": getattr(user, "nickname", "Unknown"),
        "unique_id": getattr(user, "unique_id", ""),
        "user_id": str(getattr(user, "user_id", "") or getattr(user, "id", "")),
        "avatar_url": avatar_url,
    }


# ============================================================
# FFmpeg Snapshot Capture
# ============================================================
SNAPSHOT_CACHE_TTL = 25  # seconds — cache snapshot 25s (AI polls mỗi 30s)


def capture_image_from_stream(stream_url: str) -> Optional[str]:
    """Capture 1 frame JPEG từ HLS/FLV stream, return base64 string."""
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
            tmp_path = tmp.name

        cmd = [
            "ffmpeg", "-y",
            "-i", stream_url,
            "-frames:v", "1",
            "-q:v", "5",  # quality 1-31, lower = better
            "-vf", "scale=640:-1",  # resize to 640px width
            tmp_path,
        ]

        result = subprocess.run(
            cmd, capture_output=True, timeout=15,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0,
        )

        if result.returncode != 0:
            logger.warning(f"FFmpeg image capture failed: {result.stderr.decode('utf-8', errors='replace')[:200]}")
            return None

        if not os.path.exists(tmp_path) or os.path.getsize(tmp_path) == 0:
            return None

        with open(tmp_path, "rb") as f:
            return base64.b64encode(f.read()).decode("utf-8")

    except subprocess.TimeoutExpired:
        logger.warning("FFmpeg image capture timeout")
        return None
    except Exception as e:
        logger.warning(f"FFmpeg image capture error: {e}")
        return None
    finally:
        if tmp_path:
            try:
                os.unlink(tmp_path)
            except Exception:
                pass


def capture_audio_from_stream(stream_url: str, duration: int = 3) -> Optional[str]:
    """Capture N giây audio MP3 từ HLS/FLV stream, return base64 string."""
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(suffix=".mp3", delete=False) as tmp:
            tmp_path = tmp.name

        cmd = [
            "ffmpeg", "-y",
            "-i", stream_url,
            "-t", str(duration),
            "-vn",  # no video
            "-acodec", "libmp3lame",
            "-ar", "16000",  # 16kHz — đủ cho speech recognition
            "-ac", "1",  # mono
            "-b:a", "32k",  # low bitrate — giảm size
            tmp_path,
        ]

        result = subprocess.run(
            cmd, capture_output=True, timeout=duration + 15,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0,
        )

        if result.returncode != 0:
            logger.warning(f"FFmpeg audio capture failed: {result.stderr.decode('utf-8', errors='replace')[:200]}")
            return None

        if not os.path.exists(tmp_path) or os.path.getsize(tmp_path) == 0:
            return None

        with open(tmp_path, "rb") as f:
            return base64.b64encode(f.read()).decode("utf-8")

    except subprocess.TimeoutExpired:
        logger.warning("FFmpeg audio capture timeout")
        return None
    except Exception as e:
        logger.warning(f"FFmpeg audio capture error: {e}")
        return None
    finally:
        if tmp_path:
            try:
                os.unlink(tmp_path)
            except Exception:
                pass


def get_snapshot(session: SessionInfo) -> dict:
    """Lấy snapshot (image + audio) từ stream, với cache TTL."""
    now = time.time()

    # Dùng cache nếu còn hạn
    if session._snapshot_cache and (now - session._snapshot_at) < SNAPSHOT_CACHE_TTL:
        return session._snapshot_cache

    if not session.stream_url:
        return {"image_b64": None, "audio_b64": None, "title": session.title, "error": "No stream URL"}

    logger.info(f"[{session.session_id}] Capturing snapshot from stream...")

    image_b64 = capture_image_from_stream(session.stream_url)
    audio_b64 = capture_audio_from_stream(session.stream_url, duration=3)

    snapshot = {
        "image_b64": image_b64,
        "audio_b64": audio_b64,
        "title": session.title,
        "streamer": session.streamer_nickname,
        "viewer_count": session.stats.get("viewer_count", 0),
        "captured_at": datetime.now(timezone.utc).isoformat(),
    }

    # Cache
    session._snapshot_cache = snapshot
    session._snapshot_at = now

    logger.info(f"[{session.session_id}] Snapshot: image={'yes' if image_b64 else 'no'}, audio={'yes' if audio_b64 else 'no'}")

    return snapshot


# ============================================================
# TikTok Client Setup
# ============================================================
def setup_tiktok_client(session: SessionInfo) -> TikTokLiveClient:
    """Tạo TikTokLiveClient và gắn event handlers cho một session."""

    client = TikTokLiveClient(unique_id=session.tiktok_username)

    @client.on(ConnectEvent)
    async def on_connect(event):
        session.status = "live"
        session.connected_at = datetime.now(timezone.utc).isoformat()
        session.room_id = str(client.room_id)

        if client.room_info:
            owner = client.room_info.get("owner", {})
            stats = client.room_info.get("stats", {})
            stream_url_info = client.room_info.get("stream_url", {})

            session.title = client.room_info.get("title", "")
            session.stats["viewer_count"] = client.room_info.get("user_count", 0)
            session.stats["total_views"] = int(stats.get("total_user_str", "0") or "0")
            session.stats["total_likes"] = int(stats.get("like_count", 0) or 0)

            session.streamer_nickname = owner.get("nickname", "")
            session.streamer_username = owner.get("display_id", "")
            session.streamer_followers = owner.get("follower_count", 0)

            # Lấy HLS stream URL cho snapshot capture
            hls_url = stream_url_info.get("hls_pull_url", "")
            if hls_url:
                session.stream_url = hls_url
                logger.info(f"[{session.session_id}] Stream URL captured: {hls_url[:80]}...")
            else:
                # Fallback: FLV HD
                flv_urls = stream_url_info.get("flv_pull_url", {})
                if isinstance(flv_urls, dict):
                    session.stream_url = flv_urls.get("HD1", "") or flv_urls.get("SD1", "")
                elif isinstance(flv_urls, str):
                    session.stream_url = flv_urls

        logger.info(f"[{session.session_id}] Connected: {session.title} (Room {session.room_id})")
        session.add_event("connected", data={"room_id": session.room_id, "title": session.title})

    @client.on(CommentEvent)
    async def on_comment(event):
        u = extract_user(event)
        session.stats["total_comments"] += 1
        session.add_event("comment", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"], data={"comment": event.comment, "avatar_url": u["avatar_url"]})

    @client.on(GiftEvent)
    async def on_gift(event):
        u = extract_user(event)
        gift_name = getattr(event.gift, "name", "Unknown")
        gift_id = getattr(event.gift, "id", None)
        diamond_count = getattr(event.gift, "diamond_count", 0)
        repeat_count = getattr(event, "repeat_count", 1)
        streaking = getattr(event, "streaking", False)
        session.stats["total_gifts"] += 1
        session.add_event("gift", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"], data={
            "gift_name": gift_name,
            "gift_id": gift_id,
            "diamond_count": diamond_count,
            "repeat_count": repeat_count,
            "streaking": streaking,
        })

    @client.on(LikeEvent)
    async def on_like(event):
        u = extract_user(event)
        count = getattr(event, "count", 1)
        session.stats["total_likes"] += count
        session.add_event("like", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"], data={"count": count})

    @client.on(FollowEvent)
    async def on_follow(event):
        u = extract_user(event)
        session.stats["total_follows"] += 1
        session.add_event("follow", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"])

    @client.on(JoinEvent)
    async def on_join(event):
        session.stats["total_joins"] += 1
        # Không lưu event join vì quá nhiều, chỉ đếm

    @client.on(ShareEvent)
    async def on_share(event):
        u = extract_user(event)
        session.stats["total_shares"] += 1
        session.add_event("share", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"])

    @client.on(LiveEndEvent)
    async def on_live_end(event):
        session.status = "ended"
        session.ended_at = datetime.now(timezone.utc).isoformat()
        session.add_event("live_ended")
        logger.info(f"[{session.session_id}] Live ended")

    @client.on(LivePauseEvent)
    async def on_pause(event):
        session.add_event("live_paused")

    @client.on(LiveUnpauseEvent)
    async def on_unpause(event):
        session.add_event("live_unpaused")

    @client.on(DisconnectEvent)
    async def on_disconnect(event):
        if session.status == "live":
            session.status = "ended"
            session.ended_at = datetime.now(timezone.utc).isoformat()
            session.add_event("disconnected")
            logger.info(f"[{session.session_id}] Disconnected")

    @client.on(RoomUserSeqEvent)
    async def on_viewer_update(event):
        total = getattr(event, 'total_user', None)
        if total is not None:
            session.stats["viewer_count"] = total
            session.stats["total_views"] = max(session.stats["total_views"], total)

    return client


async def run_client(session: SessionInfo):
    """Chạy TikTokLiveClient trong background task."""
    try:
        client = setup_tiktok_client(session)
        session._client = client
        await client.start(fetch_room_info=True, fetch_gift_info=True)
    except asyncio.TimeoutError:
        session.status = "error"
        session.error = "Connection timeout — user có thể không đang live"
        session.ended_at = datetime.now(timezone.utc).isoformat()
        logger.error(f"[{session.session_id}] Timeout connecting")
    except Exception as e:
        error_msg = str(e).lower()
        if "offline" in error_msg or "not found" in error_msg or "not live" in error_msg:
            session.status = "error"
            session.error = f"User không đang live: {e}"
        else:
            session.status = "error"
            session.error = str(e)
        session.ended_at = datetime.now(timezone.utc).isoformat()
        logger.error(f"[{session.session_id}] Error: {e}")


# ============================================================
# API Endpoints
# ============================================================

class StartRequest(BaseModel):
    tiktok_username: str
    session_id: Optional[str] = None  # Laravel có thể truyền ID riêng


class StopResponse(BaseModel):
    session_id: str
    status: str
    message: str


@app.get("/health")
async def health():
    return {
        "status": "ok",
        "active_sessions": len([s for s in sessions.values() if s.status == "live"]),
        "total_sessions": len(sessions),
    }


@app.post("/sessions/start")
async def start_session(body: StartRequest, x_service_key: str = Header(None)):
    verify_api_key(x_service_key)

    username = body.tiktok_username.strip()
    if not username:
        raise HTTPException(status_code=400, detail="tiktok_username is required")

    # Thêm @ nếu chưa có
    if not username.startswith("@"):
        username = f"@{username}"

    session_id = body.session_id or str(uuid.uuid4())

    # Kiểm tra đã tracking username này chưa
    for s in sessions.values():
        if s.tiktok_username == username and s.status in ("connecting", "live"):
            return {
                "session_id": s.session_id,
                "status": s.status,
                "message": f"Already tracking {username}",
                "already_exists": True,
            }

    session = SessionInfo(session_id=session_id, tiktok_username=username)
    sessions[session_id] = session

    # Chạy TikTok client trong background
    session._task = asyncio.create_task(run_client(session))

    logger.info(f"[{session_id}] Starting session for {username}")

    # Đợi tối đa 5s để connect
    for _ in range(10):
        await asyncio.sleep(0.5)
        if session.status != "connecting":
            break

    return {
        "session_id": session_id,
        "status": session.status,
        "message": f"Session started for {username}",
        "already_exists": False,
    }


@app.get("/sessions")
async def list_sessions(x_service_key: str = Header(None)):
    verify_api_key(x_service_key)

    return {
        "sessions": [
            s.to_dict(include_events=False) for s in sessions.values()
        ]
    }


@app.get("/sessions/{session_id}")
async def get_session(
    session_id: str,
    since: float = Query(0, description="Unix timestamp — chỉ lấy events sau thời điểm này"),
    limit: int = Query(200, description="Số events tối đa trả về"),
    x_service_key: str = Header(None),
):
    verify_api_key(x_service_key)

    session = sessions.get(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")

    return session.to_dict(include_events=True, since=since, limit=limit)


@app.get("/sessions/{session_id}/stats")
async def get_session_stats(session_id: str, x_service_key: str = Header(None)):
    verify_api_key(x_service_key)

    session = sessions.get(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")

    return session.to_dict(include_events=False)


@app.get("/sessions/{session_id}/snapshot")
async def get_session_snapshot(session_id: str, x_service_key: str = Header(None)):
    """Capture ảnh + audio từ live stream cho AI phân tích."""
    verify_api_key(x_service_key)

    session = sessions.get(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")

    if session.status != "live":
        return {
            "image_b64": None,
            "audio_b64": None,
            "title": session.title,
            "error": f"Session not live (status: {session.status})",
        }

    # Chạy FFmpeg trong thread pool để không block event loop
    loop = asyncio.get_event_loop()
    snapshot = await loop.run_in_executor(None, get_snapshot, session)

    return snapshot


@app.post("/sessions/{session_id}/stop")
async def stop_session(session_id: str, x_service_key: str = Header(None)):
    verify_api_key(x_service_key)

    session = sessions.get(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")

    if session.status in ("ended", "error"):
        return StopResponse(
            session_id=session_id,
            status=session.status,
            message="Session already ended",
        )

    # Disconnect TikTok client
    if session._client:
        try:
            await session._client.disconnect()
        except Exception:
            pass

    # Cancel background task
    if session._task and not session._task.done():
        session._task.cancel()

    session.status = "ended"
    session.ended_at = datetime.now(timezone.utc).isoformat()
    session.add_event("stopped_by_user")

    logger.info(f"[{session_id}] Stopped by user")

    return StopResponse(
        session_id=session_id,
        status="ended",
        message="Session stopped",
    )


# ============================================================
# Entry point
# ============================================================
if __name__ == "__main__":
    import uvicorn

    try:
        logger.info(f"Base dir: {BASE_DIR}")
        logger.info(f"Starting TikTok LIVE Service on 0.0.0.0:{SERVICE_PORT}")
        uvicorn.run(app, host="0.0.0.0", port=SERVICE_PORT)
    except Exception as e:
        logger.exception(f"Fatal error: {e}")
        print(f"\n[FATAL] {e}")
        print(f"Xem log tại: {log_file}")
        input("Nhấn Enter để thoát...")
        sys.exit(1)
