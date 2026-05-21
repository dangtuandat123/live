from TikTokLive import TikTokLiveClient
from TikTokLive.events import ConnectEvent
from datetime import datetime
import json

client = TikTokLiveClient(unique_id="@swatchesbybaobao")


def to_plain(obj):
    """
    Chuyển object TikTokLive thành dict/list/string để dễ lưu file.
    """
    if obj is None or isinstance(obj, (str, int, float, bool)):
        return obj

    if isinstance(obj, dict):
        return {str(k): to_plain(v) for k, v in obj.items()}

    if isinstance(obj, (list, tuple, set)):
        return [to_plain(v) for v in obj]

    if hasattr(obj, "model_dump"):
        return to_plain(obj.model_dump())

    if hasattr(obj, "__dict__"):
        return to_plain(vars(obj))

    return str(obj)


def find_stream_urls(obj, path=""):
    """
    Tìm tất cả field có khả năng là stream URL/video/audio.
    """
    results = []

    keywords = [
        "stream",
        "hls",
        "flv",
        "rtmp",
        "pull",
        "play",
        "m3u8",
        "live_url",
        "main_url",
        "backup_url",
    ]

    if isinstance(obj, dict):
        for key, value in obj.items():
            current_path = f"{path}.{key}" if path else str(key)
            key_lower = str(key).lower()

            if isinstance(value, str):
                value_lower = value.lower()

                key_match = any(k in key_lower for k in keywords)
                url_match = (
                    value_lower.startswith("http://")
                    or value_lower.startswith("https://")
                    or value_lower.startswith("rtmp://")
                    or ".m3u8" in value_lower
                    or ".flv" in value_lower
                )

                if key_match or url_match:
                    results.append((current_path, value))
            else:
                results.extend(find_stream_urls(value, current_path))

    elif isinstance(obj, list):
        for index, item in enumerate(obj):
            current_path = f"{path}[{index}]"
            results.extend(find_stream_urls(item, current_path))

    return results


def safe_get(data, *keys, default=""):
    """
    Lấy dữ liệu nested dict an toàn.
    """
    current = data

    for key in keys:
        if not isinstance(current, dict):
            return default

        current = current.get(key)

        if current is None:
            return default

    return current


def pretty_json(value):
    """
    Format JSON string/dict/list cho dễ đọc trong TXT.
    """
    try:
        if isinstance(value, str):
            parsed = json.loads(value)
            return json.dumps(parsed, ensure_ascii=False, indent=2)

        if isinstance(value, (dict, list)):
            return json.dumps(value, ensure_ascii=False, indent=2)

        return str(value)

    except Exception:
        return str(value)


def export_stream_info_to_txt(room_info, urls, filename="tiktok_live_stream_info.txt"):
    owner = room_info.get("owner", {})
    stats = room_info.get("stats", {})
    stream_url = room_info.get("stream_url", {})

    hls_url = stream_url.get("hls_pull_url", "")
    flv_urls = stream_url.get("flv_pull_url", {})
    flv_hd = flv_urls.get("HD1", "")
    flv_sd = flv_urls.get("SD1", "")
    rtmp_pull_url = stream_url.get("rtmp_pull_url", "")

    hls_params = stream_url.get("hls_pull_url_params", "")
    rtmp_params = stream_url.get("rtmp_pull_url_params", "")

    with open(filename, "w", encoding="utf-8") as f:
        f.write("THÔNG TIN TIKTOK LIVE STREAM\n")
        f.write("=" * 80 + "\n")
        f.write(f"Thời gian xuất file: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n\n")

        f.write("1. THÔNG TIN PHIÊN LIVE\n")
        f.write("-" * 80 + "\n")
        f.write(f"Room ID: {room_info.get('id', '') or room_info.get('room_id', '')}\n")
        f.write(f"Stream ID: {room_info.get('stream_id_str', '')}\n")
        f.write(f"Multi Stream ID: {room_info.get('multi_stream_id_str', '')}\n")
        f.write(f"Tiêu đề live: {room_info.get('title', '')}\n")
        f.write(f"Trạng thái live: {room_info.get('status', '')}\n")
        f.write(f"Share URL: {room_info.get('share_url', '')}\n\n")

        f.write("2. THÔNG TIN CHỦ LIVE\n")
        f.write("-" * 80 + "\n")
        f.write(f"Nickname: {owner.get('nickname', '')}\n")
        f.write(f"Display ID: {owner.get('display_id', '')}\n")
        f.write(f"User ID: {owner.get('id', '')}\n")
        f.write(f"Follower count: {owner.get('follower_count', '')}\n")
        f.write(f"Following count: {owner.get('following_count', '')}\n\n")

        f.write("3. THỐNG KÊ LIVE\n")
        f.write("-" * 80 + "\n")
        f.write(f"Viewer hiện tại: {room_info.get('user_count', '')}\n")
        f.write(f"Total user: {stats.get('total_user', '')}\n")
        f.write(f"Total user string: {stats.get('total_user_str', '')}\n")
        f.write(f"Like count: {stats.get('like_count', '')}\n\n")

        f.write("4. STREAM URL CHÍNH\n")
        f.write("-" * 80 + "\n")
        f.write(f"HLS .m3u8:\n{hls_url}\n\n")
        f.write(f"FLV HD:\n{flv_hd}\n\n")
        f.write(f"FLV SD:\n{flv_sd}\n\n")
        f.write(f"RTMP/PULL URL:\n{rtmp_pull_url}\n\n")

        f.write("5. THÔNG SỐ STREAM HLS\n")
        f.write("-" * 80 + "\n")
        f.write(pretty_json(hls_params))
        f.write("\n\n")

        f.write("6. THÔNG SỐ RTMP/PULL\n")
        f.write("-" * 80 + "\n")
        f.write(pretty_json(rtmp_params))
        f.write("\n\n")

        f.write("7. TẤT CẢ FIELD NGHI LÀ STREAM/VIDEO/AUDIO\n")
        f.write("-" * 80 + "\n")

        if not urls:
            f.write("Không tìm thấy URL stream/video/audio.\n")
        else:
            for index, (path, url) in enumerate(urls, start=1):
                f.write(f"\n[{index}] FIELD: {path}\n")
                f.write(f"VALUE:\n{url}\n")
                f.write("-" * 80 + "\n")

        f.write("\n8. RAW ROOM INFO ĐẦY ĐỦ\n")
        f.write("-" * 80 + "\n")
        f.write(json.dumps(room_info, ensure_ascii=False, indent=2))

    print(f"Đã xuất file TXT: {filename}")


@client.on(ConnectEvent)
async def on_connect(event):
    print("Đã kết nối room:", client.room_id)

    room_info = to_plain(client.room_info)

    if not room_info:
        print("Không có room_info.")
        return

    urls = find_stream_urls(room_info)

    export_stream_info_to_txt(
        room_info=room_info,
        urls=urls,
        filename="tiktok_live_stream_info.txt"
    )


if __name__ == "__main__":
    client.run(fetch_room_info=True)