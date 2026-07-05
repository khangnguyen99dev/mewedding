# Cấu hình Reverb (WebSocket realtime) — me.kanewedding.com

Realtime (tim bay + lời chúc/RSVP cập nhật trực tiếp) chạy bằng **Laravel Reverb** —
tiến trình WebSocket chạy nền. Setup: **EC2 Ubuntu + nginx trực tiếp**.

**Giá trị cụ thể dùng trong hướng dẫn này:**
| | |
|---|---|
| App domain | `me.kanewedding.com` |
| WebSocket domain | `ws.kanewedding.com` (subdomain riêng) |
| Thư mục code | `/home/kane_service/code` |
| User chạy app | `kane_service` |
| Reverb nội bộ | `127.0.0.1:8080` |

> Event của app dùng `ShouldBroadcastNow` → bắn đồng bộ, **không cần queue worker** cho realtime.
> Dùng subdomain riêng `ws.kanewedding.com` để không đụng route `/{slug}` của trang thiệp.

---

## BƯỚC 1 — Cài supervisor (giữ Reverb chạy nền)

```bash
sudo apt update
sudo apt install -y supervisor
sudo systemctl enable --now supervisor
supervisorctl version
```

## BƯỚC 2 — Sửa `.env` production

Trong `/home/kane_service/code/.env`:
```bash
BROADCAST_CONNECTION=reverb

# Reverb bind nội bộ (chỉ nginx gọi tới, KHÔNG mở ra internet)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Client (trình duyệt) + backend kết nối qua đây — public, có TLS
REVERB_HOST=ws.kanewedding.com
REVERB_PORT=443
REVERB_SCHEME=https

REVERB_APP_ID=mewedding
REVERB_APP_KEY=mewedding-key
REVERB_APP_SECRET=<đặt secret mạnh, KHÁC dev>

# Frontend (nhúng vào JS lúc build)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```
Nạp lại:
```bash
cd /home/kane_service/code
php artisan config:clear && php artisan config:cache
```

## BƯỚC 3 — Chạy Reverb qua supervisor

Tạo `/etc/supervisor/conf.d/reverb.conf`:
```ini
[program:reverb]
process_name=%(program_name)s
command=php /home/kane_service/code/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=kane_service
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/home/kane_service/code/storage/logs/reverb.log
stdout_logfile_maxbytes=50MB
```
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
sudo supervisorctl status reverb                                  # RUNNING = OK
curl -s http://127.0.0.1:8080 -o /dev/null -w "%{http_code}\n"    # 404/426 = sống
```

## BƯỚC 4 — DNS subdomain

Trong quản lý DNS của `kanewedding.com` (Route 53 / nhà cung cấp domain), thêm:
```
Type: A     Name: ws     Value: <IP public EC2>     TTL: 300
```
Kiểm tra (chờ vài phút cho DNS lan):
```bash
dig +short ws.kanewedding.com     # phải ra đúng IP EC2
```

## BƯỚC 5 — Nginx proxy + SSL

**5a.** Thêm vào khối `http { }` của `/etc/nginx/nginx.conf` (nếu chưa có):
```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}
```

**5b.** Tạo `/etc/nginx/sites-available/reverb`:
```nginx
server {
    listen 80;
    server_name ws.kanewedding.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host              $host;
        proxy_set_header Upgrade           $http_upgrade;
        proxy_set_header Connection        $connection_upgrade;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout  3600s;
        proxy_send_timeout  3600s;
    }
}
```

**5c.** Bật site + reload:
```bash
sudo ln -s /etc/nginx/sites-available/reverb /etc/nginx/sites-enabled/reverb
sudo nginx -t && sudo systemctl reload nginx
```

**5d.** Cấp SSL (certbot tự sửa block thêm 443 + redirect 80→443):
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ws.kanewedding.com
sudo nginx -t && sudo systemctl reload nginx
```

## BƯỚC 6 — AWS Security Group

- Inbound đã mở **443** + **80** (dùng chung với web me.kanewedding.com).
- **KHÔNG** mở 8080 — Reverb chỉ chạy `127.0.0.1`, nginx proxy tới.

## BƯỚC 7 — Build lại frontend ⚠️

`VITE_*` nhúng vào bundle **lúc build** → phải build với env production:
```bash
cd /home/kane_service/code
npm ci
npm run build
```

## BƯỚC 8 — Verify

```bash
# 1. Reverb sống
sudo supervisorctl status reverb

# 2. Bắt tay WebSocket qua public domain
npm i -g wscat
wscat -c "wss://ws.kanewedding.com/app/mewedding-key?protocol=7&client=js&version=8"
#    -> {"event":"pusher:connection_established",...} = THÀNH CÔNG ✅

# 3. Trang thiệp thật: DevTools > Network > WS
#    -> wss://ws.kanewedding.com... = 101 Switching Protocols
#    -> gửi lời chúc/RSVP ở tab khác -> tab này nhận realtime + tim bay
```

---

## Vận hành & deploy

Sau mỗi lần deploy code, khởi động lại Reverb (thêm vào deploy script):
```bash
sudo supervisorctl restart reverb
```
Xem log realtime:
```bash
sudo supervisorctl tail -f reverb
```

## Sự cố thường gặp

| Triệu chứng | Nguyên nhân | Xử lý |
|---|---|---|
| `wss://localhost:8080 failed` | Bundle build với env dev | Build lại (Bước 7) |
| 502 Bad Gateway | Reverb không chạy | `supervisorctl status reverb` + xem log |
| Rớt sau ~60s | `proxy_read_timeout` thấp | đặt `3600s` (Bước 5b) |
| Bắt tay OK, không nhận event | `BROADCAST_CONNECTION` ≠ reverb | set `=reverb` + `config:cache` |
| certbot fail | DNS `ws` chưa trỏ / 80 chưa mở | kiểm tra `dig`, Security Group |

## Nhiều app server (scale-out) — hiện KHÔNG cần (1 EC2)

Nếu sau này chạy >1 instance, bật Redis đồng bộ giữa node:
```bash
REVERB_SCALING_ENABLED=true   # dùng REDIS_HOST/PORT sẵn trong config/reverb.php
```
