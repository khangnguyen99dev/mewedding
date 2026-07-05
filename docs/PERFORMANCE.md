# Tối ưu tốc độ trang thiệp (production)

Ghi chú các bước tăng tốc trang thiệp công khai (`/{slug}`). Phần **code** đã làm trong repo;
phần **server/deploy** dưới đây cần chạy trên production.

## Bối cảnh (số liệu đo được)

- HTML mỗi trang: **~500–590KB** (chưa nén) — gửi cho mọi khách.
- Google Fonts: đã có `preconnect` + `display=swap`, 17 họ font đều được dùng (không trim được).
- LadiPage runtime `ladipagev3.min.js`: **~540–640KB** (đã lên S3, cache 1 năm).
- Trang bị `visibility:hidden` tới khi JS hydrate xong → tốc độ *hiện trang* phụ thuộc thời gian tải HTML + CSS + JS.

## Đã làm trong code

| Việc | File |
|---|---|
| Asset template phục vụ từ S3/CDN (rewrite `/templates/`) | `config/templates.php`, `LadiPageRenderer` |
| `Cache-Control: max-age=1 năm, immutable` cho asset S3 | `templates:sync --s3` |
| Gỡ IE polyfill (html5shiv/respond) + preconnect chết trong `<head>` | `LadiPageRenderer::trimHead` |
| Defer Pusher/Echo (~74KB) ra chunk lazy, chạy khi idle | `resources/js/public/core.ts` |
| Command pre-warm cache HTML sau deploy | `invitations:warm-cache` |

---

## 1. Bật nén gzip/brotli ở nginx  ⭐ (win lớn nhất)

500KB HTML → ~70KB. Thêm vào `http {}` hoặc `server {}` block:

```nginx
# gzip
gzip on;
gzip_comp_level 5;
gzip_min_length 1024;
gzip_vary on;
gzip_proxied any;
gzip_types
    text/plain text/css text/xml
    application/javascript application/json
    image/svg+xml application/xml+rss;

# brotli (nếu có module ngx_brotli — tốt hơn gzip ~15-20%)
brotli on;
brotli_comp_level 5;
brotli_types
    text/plain text/css
    application/javascript application/json
    image/svg+xml;
```

Reload: `sudo nginx -t && sudo systemctl reload nginx`

Kiểm tra: `curl -sI -H "Accept-Encoding: gzip,br" https://your-domain/{slug} | grep -i content-encoding`

> S3 **không** tự nén object. Nếu dùng CloudFront (mục 4), bật "Compress objects automatically" để nén CSS/JS/SVG ở edge.

## 2. Deploy script + OPcache  ⭐ (fix gốc "chậm sau deploy")

"Chậm ngay sau deploy" = cache config/route/view rỗng + OPcache nguội → request đầu biên dịch lại toàn bộ.

**Deploy script** (chạy theo thứ tự này):
```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative

php artisan migrate --force
php artisan templates:sync --s3        # đẩy asset template lên S3

php artisan optimize:clear             # xả cache cũ
php artisan optimize                   # cache config + route + view + event
php artisan cache:clear                # xả HTML thiệp đã render (đổi asset URL/render)
php artisan invitations:warm-cache     # render sẵn thiệp published vào cache

sudo systemctl reload php8.5-fpm       # nạp code mới + reset OPcache
```

**OPcache** (`/etc/php/8.5/fpm/conf.d/10-opcache.ini`):
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0          ; prod: không stat file mỗi request (nhớ reload fpm khi deploy)
opcache.jit=1255
opcache.jit_buffer_size=64M
```
> `validate_timestamps=0` = nhanh nhất nhưng **bắt buộc** `reload php-fpm` mỗi lần deploy (đã có ở script trên), nếu không code mới không được nạp.

## 3. Cache store: database → Redis  (hoặc file)

Hiện `CACHE_STORE=database` → HTML render 500KB đọc từ bảng `cache` mỗi lượt xem. Redis nhanh hơn.

**Điều kiện:** Redis server đang chạy + client PHP (phpredis hoặc predis).
```bash
# Ubuntu
sudo apt install -y redis-server php8.5-redis
sudo systemctl enable --now redis-server
redis-cli ping        # -> PONG
```

`.env` production:
```
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CLIENT=phpredis
```
Rồi `php artisan config:cache`.

> Nếu **không** muốn chạy Redis: đặt `CACHE_STORE=file` — vẫn nhanh hơn `database` cho các blob HTML lớn và không cần service nào.

## 4. CloudFront trước S3  (nhanh nhất cho khách VN)

S3 trực tiếp (Singapore) đã ổn, nhưng CloudFront thêm edge cache + brotli + HTTP/2.

1. AWS Console → **CloudFront** → Create distribution.
2. **Origin domain**: chọn bucket `kanewedding` (dạng `kanewedding.s3.ap-southeast-1.amazonaws.com`).
3. **Viewer protocol policy**: Redirect HTTP→HTTPS.
4. **Compress objects automatically**: Yes.
5. **Cache policy**: CachingOptimized.
6. (Khuyến nghị) dùng **Origin Access Control (OAC)** để chỉ CloudFront đọc bucket, rồi có thể bỏ bucket policy public.
7. Tạo xong lấy domain `xxxxx.cloudfront.net` → đổi env:
```
TEMPLATE_ASSET_URL=https://xxxxx.cloudfront.net
```
`php artisan config:cache && php artisan cache:clear` để thiệp render lại với URL CDN. **Không cần sửa code.**

---

## Thứ tự ưu tiên (impact × dễ)

1. **gzip/brotli nginx** (mục 1) — lớn, dễ, làm ngay.
2. **Deploy `optimize` + reload php-fpm/OPcache** (mục 2) — fix "chậm sau deploy".
3. **`invitations:warm-cache`** trong deploy — khách đầu không chờ render.
4. **Redis/file cache** (mục 3).
5. **CloudFront** (mục 4) — tối ưu edge cho VN.

## Checklist verify sau khi làm

```bash
# HTML có được nén?
curl -sI -H "Accept-Encoding: br,gzip" https://DOMAIN/{slug} | grep -i content-encoding
# Asset template phục vụ từ S3/CDN?
curl -sI https://DOMAIN/{slug} | ...   # xem Network tab: ảnh/font từ kanewedding.s3... hoặc cloudfront.net
# Asset có cache 1 năm?
curl -sI https://kanewedding.s3.ap-southeast-1.amazonaws.com/templates/nobel/images/notify.svg | grep -i cache-control
```
