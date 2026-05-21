# Fix SSL cho Windows Server cũ (thiếu root CA certificates)
import ssl
import certifi
ssl._create_default_https_context = ssl.create_default_context
ssl._create_default_https_context = lambda: ssl.create_default_context(cafile=certifi.where())
import os
os.environ["SSL_CERT_FILE"] = certifi.where()

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
    UnknownEvent,
    WebsocketResponseEvent,
)

client = TikTokLiveClient(unique_id="@swatchesbybaobao")


@client.on(ConnectEvent)
async def on_connect(event):
    print("Đã kết nối")
    print("Room ID:", client.room_id)

    # Room info chỉ có nếu client.run(fetch_room_info=True)
    if client.room_info:
        owner = client.room_info.get("owner", {})
        stats = client.room_info.get("stats", {})

        print("Tiêu đề live:", client.room_info.get("title"))
        print("Trạng thái:", client.room_info.get("status"))
        print("Viewer hiện tại:", client.room_info.get("user_count"))
        print("Tổng viewer:", stats.get("total_user_str"))
        print("Tổng like:", stats.get("like_count"))

        print("Streamer:", owner.get("nickname"))
        print("Username:", owner.get("display_id"))
        print("Follower:", owner.get("follower_count"))

    # Gift catalog chỉ có nếu client.run(fetch_gift_info=True)
    if client.gift_info:
        gifts = client.gift_info.get("gifts", [])
        print("Số loại gift:", len(gifts))

        for gift in gifts[:10]:
            print(
                "Gift:",
                gift.get("name"),
                "| ID:",
                gift.get("id"),
                "| Diamond:",
                gift.get("diamond_count")
            )


@client.on(CommentEvent)
async def on_comment(event):
    user = getattr(event, "user", None) or getattr(event, "user_info", None)
    nickname = getattr(user, "nickname", "Unknown")

    print(f"[COMMENT] {nickname}: {event.comment}")


@client.on(GiftEvent)
async def on_gift(event):
    user = getattr(event, "user", None) or getattr(event, "from_user", None)
    nickname = getattr(user, "nickname", "Unknown")

    gift_name = getattr(event.gift, "name", "Unknown gift")
    gift_id = getattr(event.gift, "id", None)
    repeat_count = getattr(event, "repeat_count", 1)
    streaking = getattr(event, "streaking", False)

    print(f"[GIFT] {nickname} gửi {repeat_count}x {gift_name} | id={gift_id} | streaking={streaking}")


@client.on(LikeEvent)
async def on_like(event):
    user = getattr(event, "user", None)
    nickname = getattr(user, "nickname", "Unknown")
    count = getattr(event, "count", 1)

    print(f"[LIKE] {nickname} like +{count}")


@client.on(FollowEvent)
async def on_follow(event):
    user = getattr(event, "user", None)
    nickname = getattr(user, "nickname", "Unknown")

    print(f"[FOLLOW] {nickname} vừa follow")


@client.on(JoinEvent)
async def on_join(event):
    user = getattr(event, "user", None)
    nickname = getattr(user, "nickname", "Unknown")

    print(f"[JOIN] {nickname} vừa vào live")


@client.on(ShareEvent)
async def on_share(event):
    user = getattr(event, "user", None)
    nickname = getattr(user, "nickname", "Unknown")
    users_joined = getattr(event, "users_joined", None)

    print(f"[SHARE] {nickname} vừa share live | users_joined={users_joined}")


@client.on(LivePauseEvent)
async def on_pause(event):
    print("[LIVE] Live bị pause")


@client.on(LiveUnpauseEvent)
async def on_unpause(event):
    print("[LIVE] Live tiếp tục")


@client.on(LiveEndEvent)
async def on_live_end(event):
    print("[LIVE] Live đã kết thúc")


@client.on(DisconnectEvent)
async def on_disconnect(event):
    print("[DISCONNECT] Mất kết nối")


@client.on(UnknownEvent)
async def on_unknown(event):
    print("[UNKNOWN EVENT]", getattr(event, "method", None))


# Debug: bắt tất cả websocket message
# Bật cái này sẽ spam rất nhiều log
@client.on(WebsocketResponseEvent)
async def on_ws(event):
    print("[WS]", getattr(event, "method", None))


if __name__ == "__main__":
    client.run(
        fetch_room_info=True,
        fetch_gift_info=True
    )