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

import sys

# Monkey patch asyncio to fix benign ConnectionResetError [WinError 10054] on Windows
if sys.platform == "win32":
    import asyncio
    try:
        from asyncio.proactor_events import _ProactorBasePipeTransport
        _original_call_connection_lost = _ProactorBasePipeTransport._call_connection_lost
        def _patched_call_connection_lost(self, exc):
            try:
                _original_call_connection_lost(self, exc)
            except (ConnectionResetError, OSError):
                pass  # Bỏ qua lỗi ngắt kết nối cưỡng bức vô hại trên Windows
        _ProactorBasePipeTransport._call_connection_lost = _patched_call_connection_lost
    except Exception as e:
        import logging
        logging.getLogger("tiktok-live-service").warning(f"Failed to patch asyncio connection lost handler: {e}")

# Fix SSL cho Windows Server cũ (thiếu root CA certificates)
import ssl
import certifi
ssl._create_default_https_context = lambda: ssl.create_default_context(cafile=certifi.where())
import os
os.environ["SSL_CERT_FILE"] = certifi.where()


# Monkey patch websockets to disable default ping/pong which causes constant disconnects on TikTok Webcast WSS.
# TikTok webcast server does not respond to standard WebSocket Ping frames, causing keepalive timeout.
# We set ping_interval=None to disable it, since piratetok_live has its own custom heartbeat loop.
try:
    import websockets
    import websockets.asyncio.client
    
    _original_asyncio_connect = websockets.asyncio.client.connect
    def patched_asyncio_connect(*args, **kwargs):
        kwargs["ping_interval"] = None
        return _original_asyncio_connect(*args, **kwargs)
    websockets.asyncio.client.connect = patched_asyncio_connect

    _original_legacy_connect = websockets.connect
    def patched_legacy_connect(*args, **kwargs):
        kwargs["ping_interval"] = None
        return _original_legacy_connect(*args, **kwargs)
    websockets.connect = patched_legacy_connect
except Exception as e:
    import logging
    logging.getLogger("tiktok-live-service").warning(f"Failed to monkey patch websockets: {e}")

import asyncio
import base64
import subprocess
import sys
import tempfile
import time
import uuid
import logging
from collections import deque
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Header, Query
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

import urllib.parse
import urllib.request
import json

from piratetok_live import TikTokLiveClient, EventType, ProfileCache
from piratetok_live.errors import (
    HostNotOnlineError,
    UserNotFoundError,
    TikTokBlockedError,
    TikTokApiError,
    AgeRestrictedError,
)

# Khởi tạo profile cache toàn cục (TTL mặc định 5 phút)
profile_cache = ProfileCache()

# ThreadPoolExecutor toàn cục cho việc capture snapshot
snapshot_executor = ThreadPoolExecutor(max_workers=4)



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

# --- Dependency check (FFmpeg) ---
FFMPEG_AVAILABLE = None

def check_ffmpeg() -> bool:
    """Kiểm tra xem lệnh 'ffmpeg' có khả dụng trong hệ thống hay không (được cache)."""
    global FFMPEG_AVAILABLE
    if FFMPEG_AVAILABLE is not None:
        return FFMPEG_AVAILABLE

    try:
        subprocess.run(
            ["ffmpeg", "-version"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0,
            timeout=5
        )
        FFMPEG_AVAILABLE = True
    except (FileNotFoundError, subprocess.SubprocessError):
        FFMPEG_AVAILABLE = False

    return FFMPEG_AVAILABLE

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
        self.retries = 0  # Số lần tự động kết nối lại (reconnect)

        # Room info (populated on connect)
        self.room_id: Optional[str] = None
        self.title: Optional[str] = None
        self.cover_url: Optional[str] = None
        self.streamer_nickname: Optional[str] = None
        self.streamer_username: Optional[str] = None
        self.streamer_avatar: Optional[str] = None
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
        now = datetime.now(timezone.utc)
        self.events.append({
            "id": uuid.uuid4().hex,
            "type": event_type,
            "nickname": nickname,
            "unique_id": unique_id,
            "user_id": user_id,
            "data": data or {},
            "timestamp": now.isoformat(),
            "timestamp_raw": now.timestamp(),
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
            "cover_url": self.cover_url,
            "streamer": {
                "nickname": self.streamer_nickname,
                "username": self.streamer_username,
                "avatar": self.streamer_avatar,
                "followers": self.streamer_followers,
            },
            "stats": self.stats.copy(),
        }
        if include_events:
            events = list(self.events)
            if since > 0:
                events = [e for e in events if e.get("timestamp_raw", 0) > since]
                result["events"] = events[:limit]
            else:
                result["events"] = events[-limit:]
            result["total_events_buffered"] = len(self.events)
        return result


# Session storage (in-memory)
sessions: dict[str, SessionInfo] = {}


# ============================================================
# Helpers
# ============================================================
def extract_user(data: dict) -> dict:
    """Trích xuất user info từ event data dictionary trong piratetok_live."""
    user = data.get("user", {})
    if not user:
        return {"nickname": "Unknown", "unique_id": "", "user_id": "", "avatar_url": ""}
    
    # Fallback cho unique_id (camelCase hoặc snake_case)
    unique_id = user.get("uniqueId") or user.get("unique_id") or ""
    nickname = user.get("nickname") or "Unknown"
    user_id = str(user.get("id") or user.get("id_str") or "")
    
    avatar_url = ""
    avatar_thumb = user.get("avatarThumb") or user.get("avatar_thumb") or {}
    if avatar_thumb:
        urls = avatar_thumb.get("urlList") or avatar_thumb.get("url_list") or []
        if urls:
            avatar_url = urls[0]
            
    return {
        "nickname": nickname,
        "unique_id": unique_id,
        "user_id": user_id,
        "avatar_url": avatar_url,
    }


def parse_gift_data(data: dict) -> dict:
    """Trích xuất dữ liệu quà tặng chi tiết với cơ chế fallback."""
    gift = data.get("gift", {})
    gift_name = gift.get("name") or gift.get("describe") or "Unknown"
    gift_id = data.get("giftId") or data.get("gift_id")
    
    # Kim cương
    diamond_count = gift.get("diamondCount") or gift.get("diamond_count") or 0
    
    # Số lượng repeat
    repeat_count = data.get("repeatCount") or data.get("repeat_count") or 1
    streaking = not (data.get("repeatEnd") or data.get("repeat_end") or False)
    
    return {
        "gift_name": gift_name,
        "gift_id": gift_id,
        "diamond_count": diamond_count,
        "repeat_count": repeat_count,
        "streaking": streaking,
    }


# ============================================================
# FFmpeg Snapshot Capture
# ============================================================
SNAPSHOT_CACHE_TTL = 25  # seconds — cache snapshot 25s (AI polls mỗi 30s)


def capture_image_from_stream(stream_url: str) -> Optional[str]:
    """Capture 1 frame JPEG từ HLS/FLV stream, return base64 string."""
    if not check_ffmpeg():
        logger.warning("FFmpeg is not installed or not in PATH. Skipping image capture.")
        return None

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
    if not check_ffmpeg():
        logger.warning("FFmpeg is not installed or not in PATH. Skipping audio capture.")
        return None

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

    # Capture song song qua executor toàn cục để tránh tạo thread liên tục
    image_future = snapshot_executor.submit(capture_image_from_stream, session.stream_url)
    audio_future = snapshot_executor.submit(capture_audio_from_stream, session.stream_url, 3)
    image_b64 = image_future.result()
    audio_b64 = audio_future.result()

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


def fetch_room_details(client: TikTokLiveClient, room_id: str) -> dict:
    """Gọi trực tiếp API webcast của TikTok để lấy chi tiết phòng live bao gồm cover_url."""
    proxy = getattr(client, "_proxy", "")
    ua = getattr(client, "_user_agent", "") or "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    cookies = getattr(client, "_cookies", "") or ""
    lang = getattr(client, "_language", "") or "en"
    
    params = urllib.parse.urlencode({
        "aid": "1988",
        "app_name": "tiktok_web",
        "device_platform": "web_pc",
        "app_language": lang,
        "browser_language": f"{lang}-US",
        "browser_name": "Mozilla",
        "browser_online": "true",
        "browser_platform": "Linux x86_64",
        "cookie_enabled": "true",
        "screen_height": "1080",
        "screen_width": "1920",
        "room_id": room_id,
    })
    url = f"https://webcast.tiktok.com/webcast/room/info/?{params}"
    headers = {
        "User-Agent": ua,
        "Referer": "https://www.tiktok.com/",
    }
    if cookies:
        headers["Cookie"] = cookies

    req = urllib.request.Request(url, headers=headers)
    
    # Setup proxy
    handlers = []
    if proxy:
        handlers.append(urllib.request.ProxyHandler({"https": proxy, "http": proxy}))
    opener = urllib.request.build_opener(*handlers)
    
    with opener.open(req, timeout=10.0) as resp:
        body_raw = resp.read().decode("utf-8")
        
    body = json.loads(body_raw)
    data = body.get("data", {})
    stats = data.get("stats", {})
    
    # Lấy cover URL đầu tiên
    cover_url = ""
    cover = data.get("cover", {})
    if cover:
        urls = cover.get("url_list", [])
        if urls:
            cover_url = urls[0]
            
    # Lấy stream URL
    stream_url = ""
    stream_url_raw = data.get("stream_url")
    if isinstance(stream_url_raw, dict):
        flv = stream_url_raw.get("flv_pull_url")
        if isinstance(flv, dict) and flv:
            # HD1 -> SD1 -> FULL_HD1 -> SD2
            stream_url = flv.get("HD1") or flv.get("SD1") or flv.get("FULL_HD1") or flv.get("SD2")
            
    return {
        "title": data.get("title", ""),
        "viewers": data.get("user_count", 0),
        "cover_url": cover_url,
        "likes": stats.get("like_count", 0),
        "total_user": stats.get("total_user", 0),
        "stream_url": stream_url,
    }


async def fetch_session_metadata_task(session: SessionInfo, client: TikTokLiveClient, room_id: str):
    """Bất đồng bộ gọi API phòng live và profile streamer để cập nhật SessionInfo mà không block event loop."""
    loop = asyncio.get_running_loop()
    username = session.tiktok_username.replace("@", "").strip()
    
    # 1. Lấy chi tiết phòng live (chứa cover_url và stream_url)
    try:
        details = await loop.run_in_executor(None, fetch_room_details, client, room_id)
        session.title = details["title"]
        session.stats["viewer_count"] = details["viewers"]
        session.stats["total_likes"] = details["likes"]
        session.stats["total_views"] = details["total_user"]
        session.cover_url = details["cover_url"]
        
        if details["stream_url"]:
            session.stream_url = details["stream_url"]
            logger.info(f"[{session.session_id}] Stream URL captured: {session.stream_url[:80]}...")
            
        session.add_event("connected", data={"room_id": room_id, "title": session.title})
        logger.info(f"[{session.session_id}] Connected to Room {room_id} (Title: {session.title})")
    except Exception as e:
        logger.warning(f"[{session.session_id}] Failed to fetch room details: {e}")
        # Fallback dùng info từ client.fetch_room_info
        try:
            info = await loop.run_in_executor(None, client.fetch_room_info, room_id)
            session.title = info.title
            session.stats["viewer_count"] = info.viewers
            session.stats["total_likes"] = info.likes
            session.stats["total_views"] = info.total_user
            if info.stream_url:
                session.stream_url = info.stream_url.flv_hd or info.stream_url.flv_sd or info.stream_url.flv_origin or info.stream_url.flv_ld
            session.add_event("connected", data={"room_id": room_id, "title": session.title})
        except Exception as e2:
            logger.warning(f"[{session.session_id}] Failed to fetch room info fallback: {e2}")

    # 2. Lấy profile của streamer (chứa avatar và followers)
    try:
        profile = await loop.run_in_executor(None, profile_cache.fetch, username)
        session.streamer_nickname = profile.nickname or username
        session.streamer_username = profile.unique_id or username
        session.streamer_avatar = profile.avatar_large or profile.avatar_medium or profile.avatar_thumb
        session.streamer_followers = profile.follower_count
        logger.info(f"[{session.session_id}] Streamer profile loaded: followers={profile.follower_count}")
    except Exception as e:
        logger.warning(f"[{session.session_id}] Failed to fetch streamer profile: {e}")
        # Fallback gán username
        session.streamer_nickname = username
        session.streamer_username = username


# ============================================================
# TikTok Client Setup
# ============================================================
def ensure_live(session: SessionInfo):
    """Tự động khôi phục trạng thái LIVE và reset retries khi có sự kiện thời gian thực."""
    if session.status in ("disconnected", "connecting"):
        logger.info(f"[{session.session_id}] Connection restored, status set back to live")
        session.status = "live"
        session.retries = 0


def setup_tiktok_client(session: SessionInfo) -> TikTokLiveClient:
    """Tạo TikTokLiveClient và gắn event handlers cho một session."""
    username = session.tiktok_username.replace("@", "").strip()
    client = TikTokLiveClient(username)
    client.max_retries(20)

    @client.on(EventType.connected)
    def on_connect(event):
        room_id = event.room_id
        session.status = "live"
        session.retries = 0  # Reset số lần reconnect khi kết nối thành công
        session.connected_at = datetime.now(timezone.utc).isoformat()
        session.room_id = room_id

        # Khởi chạy background task lấy metadata đồng thời và non-blocking
        asyncio.create_task(fetch_session_metadata_task(session, client, room_id))

    @client.on(EventType.chat)
    def on_chat(event):
        ensure_live(session)
        data = event.data
        u = extract_user(data)
        content = data.get("content", "")
        session.stats["total_comments"] += 1
        session.add_event(
            "comment", 
            nickname=u["nickname"], 
            unique_id=u["unique_id"], 
            user_id=u["user_id"], 
            data={"comment": content, "avatar_url": u["avatar_url"]}
        )

    @client.on(EventType.gift)
    def on_gift(event):
        ensure_live(session)
        data = event.data
        u = extract_user(data)
        gift_info = parse_gift_data(data)
        session.stats["total_gifts"] += 1
        session.add_event(
            "gift", 
            nickname=u["nickname"], 
            unique_id=u["unique_id"], 
            user_id=u["user_id"], 
            data=gift_info
        )

    @client.on(EventType.like)
    def on_like(event):
        ensure_live(session)
        data = event.data
        u = extract_user(data)
        count = data.get("count", 1)
        session.stats["total_likes"] += count
        session.add_event(
            "like", 
            nickname=u["nickname"], 
            unique_id=u["unique_id"], 
            user_id=u["user_id"], 
            data={"count": count}
        )

    @client.on(EventType.follow)
    def on_follow(event):
        ensure_live(session)
        data = event.data
        u = extract_user(data)
        session.stats["total_follows"] += 1
        session.add_event("follow", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"])

    @client.on(EventType.share)
    def on_share(event):
        ensure_live(session)
        data = event.data
        u = extract_user(data)
        session.stats["total_shares"] += 1
        session.add_event("share", nickname=u["nickname"], unique_id=u["unique_id"], user_id=u["user_id"])

    @client.on(EventType.join)
    def on_join(event):
        ensure_live(session)
        session.stats["total_joins"] += 1

    @client.on(EventType.live_ended)
    def on_live_end(event):
        session.status = "ended"
        session.ended_at = datetime.now(timezone.utc).isoformat()
        session.add_event("live_ended")
        logger.info(f"[{session.session_id}] Live ended")

    @client.on(EventType.reconnecting)
    def on_reconnecting(event):
        data = event.data or {}
        attempt = data.get("attempt", 1)
        delay = data.get("delay", 2)
        session.status = "disconnected"
        session.retries = attempt
        session.add_event("reconnecting", data={"attempt": attempt, "delay": delay})
        logger.info(f"[{session.session_id}] Reconnecting: attempt {attempt}, delay {delay}s")

    @client.on(EventType.disconnected)
    def on_disconnect(event):
        if session.status == "live":
            session.status = "disconnected"
            session.add_event("disconnected")
            logger.info(f"[{session.session_id}] Disconnected")

    @client.on(EventType.room_user_seq)
    def on_viewer_update(event):
        ensure_live(session)
        data = event.data or {}
        viewer_count = data.get("viewerCount") or data.get("viewer_count")
        if viewer_count is not None:
            session.stats["viewer_count"] = viewer_count

        total_user = data.get("totalUser") or data.get("total_user")
        if total_user is not None:
            session.stats["total_views"] = max(session.stats["total_views"], total_user)

    return client


async def run_client(session: SessionInfo):
    """Chạy TikTokLiveClient trong background task."""
    try:
        client = setup_tiktok_client(session)
        session._client = client

        logger.info(f"[{session.session_id}] Starting TikTok client connection for {session.tiktok_username}...")
        await client.connect()

        if session.status in ("live", "connecting", "disconnected"):
            session.status = "ended"
            session.ended_at = datetime.now(timezone.utc).isoformat()

    except asyncio.CancelledError:
        logger.info(f"[{session.session_id}] Task cancelled by user stop.")
        session.status = "ended"
        session.ended_at = datetime.now(timezone.utc).isoformat()
    except HostNotOnlineError:
        logger.info(f"[{session.session_id}] Streamer {session.tiktok_username} is offline.")
        session.status = "error"
        session.error = "User hiện không phát livestream."
        session.ended_at = datetime.now(timezone.utc).isoformat()
    except UserNotFoundError:
        logger.info(f"[{session.session_id}] Streamer {session.tiktok_username} not found.")
        session.status = "error"
        session.error = "Không tìm thấy người dùng này trên TikTok."
        session.ended_at = datetime.now(timezone.utc).isoformat()
    except TikTokBlockedError as e:
        logger.error(f"[{session.session_id}] Request blocked by TikTok (anti-bot / rate limit): {e}")
        session.status = "error"
        session.error = "Yêu cầu bị chặn bởi TikTok (lỗi chống bot/rate limit)."
        session.ended_at = datetime.now(timezone.utc).isoformat()
    except Exception as e:
        logger.error(f"[{session.session_id}] Connection error: {e}")
        session.status = "error"
        session.error = str(e)
        session.ended_at = datetime.now(timezone.utc).isoformat()


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
        "ffmpeg_installed": check_ffmpeg(),
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
            session._client.disconnect()
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
        
        # Kiểm tra dependency FFmpeg khi khởi động
        if not check_ffmpeg():
            logger.warning(
                "⚠️ [WARNING] FFmpeg is not installed or not added to system PATH. "
                "Stream screen capture and audio extraction will NOT work."
            )
        else:
            logger.info("FFmpeg dependency check: OK")

        logger.info(f"Starting TikTok LIVE Service on 0.0.0.0:{SERVICE_PORT}")
        uvicorn.run(app, host="0.0.0.0", port=SERVICE_PORT)
    except Exception as e:
        logger.exception(f"Fatal error: {e}")
        print(f"\n[FATAL] {e}")
        print(f"Xem log tại: {log_file}")
        input("Nhấn Enter để thoát...")
        sys.exit(1)
