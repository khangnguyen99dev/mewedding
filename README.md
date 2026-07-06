# Kane Wedding — Wedding Invitation CMS

A production-ready, **config-driven** wedding-invitation platform. Admins pick a template,
configure every piece of content (text / images / music / colours / repeaters), and the public
page renders the chosen template dynamically at a slug — e.g. `https://example.com/anh-cuoi-thinh-hang`.

The backend is **template-agnostic**: a new design is added by dropping a folder into
`resources/templates/` — **no backend code changes**. The repo ships with two complete templates,
**Nobel** (luxury gold) and **Flowers** (soft floral), both rebuilt from the original LadiPage designs.

---

## ✨ Features

- **Dynamic Template Engine** — templates are filesystem folders with a JSON schema; the schema drives
  the admin form, server-side validation, and rendering.
- **Auto-generated dynamic form** — the editor builds itself from the selected template's schema
  (text, textarea, image, gallery, audio, color, font, select, date/time, boolean, repeater…).
- **Live preview** — an iframe re-renders the *same* Blade used in production as you type (debounced).
- **Media manager** — uploads via Spatie Media Library with on-the-fly `webp` + thumbnail conversions;
  drag-and-drop gallery sorting; **unlimited** gallery / timeline / events / accounts.
- **RSVP + realtime guestbook** — public submission, live updates across devices via Laravel Reverb
  (WebSockets), floating-hearts animation, admin moderation.
- **Roles & permissions** (Spatie) — `admin` / `editor`, ownership-scoped policies.
- **SEO-friendly public pages** — Blade SSR, Open Graph tags, responsive, lazy-loaded images,
  response caching keyed by `updated_at` (auto-invalidating).

---

## 🧱 Tech stack

| Layer | Tech |
|---|---|
| Backend | Laravel 12, PHP 8.2+, MySQL 8/9 |
| Admin UI | Vue 3 + TypeScript + Pinia + Vue Router + Tailwind v4 (SPA at `/admin`) |
| Public page | Blade SSR + light vanilla TS (countdown, music, lightbox, RSVP, Echo) |
| Auth | Laravel Sanctum (SPA cookie) + Spatie Permission |
| Media | Spatie Media Library + GD (`webp`) |
| Realtime | Laravel Reverb + Laravel Echo + pusher-js |
| Sanitization | mews/purifier + `strip_tags` on public input |
| Build | Vite 7 |

> **Environment notes:** Runs on **PHP 8.2** (8.4 not required). **Redis is optional** — dev uses the
> `database` driver for cache/queue; switch to Redis in production via `.env` only. Realtime uses Reverb
> (standalone WebSocket server, no Redis needed for a single node).

---

## 🚀 Getting started

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# edit .env -> DB_DATABASE=me_wedding, DB_USERNAME, DB_PASSWORD

# 3. Database
mysql -u root -e "CREATE DATABASE me_wedding CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed          # tables + roles + admin user + demo invitation
php artisan storage:link            # expose uploaded media

# 4. Register templates (scans resources/templates/, publishes their assets)
php artisan templates:sync

# 5. Run everything (server + queue + reverb + vite)
composer dev
# ...or individually:
#   php artisan serve
#   php artisan reverb:start
#   npm run dev
```

Then open:

- **Admin:** http://localhost:8000/admin  → `admin@kanewedding.test` / `password`
- **Public demo:** http://localhost:8000/nobel-demo and http://localhost:8000/flowers-demo

Production build: `npm run build`, then `php artisan optimize` (config + route + view cache).

---

## 🗂️ Project structure

```
app/
  Models/            User, Template, Invitation, Rsvp, GuestbookMessage
  Http/Controllers/  Api/* (admin), Public/* (slug page + interactions)
  Http/Requests/     schema-aware Form Requests
  Http/Resources/    API transformers
  Policies/          InvitationPolicy (ownership + roles)
  Services/          TemplateRegistry, InvitationRenderer, SettingsResolver,
                     InvitationService, RsvpService, GuestbookService
  Events/            RsvpReceived, GuestbookMessagePosted (broadcast)
  Console/Commands/  SyncTemplates  (templates:sync)
resources/
  templates/{key}/   template.json, preview.json, index.blade.php, sections/*, assets/{css,js,fonts,images,media}
  js/admin/          Vue SPA (views, stores, components/fields, lib)
  js/public/         public-page interactivity (countdown, music, lightbox, realtime)
  views/public/      show.blade.php (SEO + theme vars + asset host)
config/templates.php
routes/{web.php, api.php, channels.php}
```

---

## 🧬 Data model (ERD)

```
User 1───* Invitation *───1 Template
Invitation 1───* Rsvp
Invitation 1───* GuestbookMessage
Invitation 1───* Media (Spatie; collections: library, audio)
User *───* Role *───* Permission   (Spatie)
```

- **`invitations.settings` / `theme` / `seo` are JSON** — intentional: the editable shape varies per
  template, so it cannot be modelled as fixed columns. JSON is validated against the template schema
  on save. Repeater items live as JSON arrays (order preserved); media is referenced by **media id**.
- **`rsvps`, `guestbook_messages`** are real tables (queryable, realtime, moderation).
- **Media** uses Spatie's normalized `media` table (collections + ordering) instead of many small tables.

---

## 🔌 API (Sanctum SPA cookie auth)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/login`, `/api/logout`, GET `/api/me` | Auth |
| GET | `/api/templates`, `/api/templates/{key}` | Template registry + schema |
| GET/POST | `/api/invitations` | List / create (from template) |
| GET/PUT/DELETE | `/api/invitations/{id}` | Read / update / delete |
| POST | `/api/invitations/{id}/duplicate` `/publish` `/unpublish` `/preview` | Actions + draft preview |
| POST/DELETE | `/api/invitations/{id}/media[/{media}]` | Upload / remove media |
| GET/PATCH/DELETE | `/api/invitations/{id}/rsvps`, `/guestbook[/{msg}]` | Guests + moderation |
| **Public** | `GET /{slug}` | Rendered invitation (Blade SSR) |
| **Public** | `GET /api/public/{slug}/guestbook`, `/stats` | Read wishes / RSVP counts |
| **Public** | `POST /api/public/{slug}/guestbook`, `/rsvp` | Submit (rate-limited 20/min, sanitized) |

Realtime channels (public): `invitation.{id}.guestbook`, `invitation.{id}.rsvp`.

---

## 🎨 Two render engines

A template's `template.json` declares an `engine`. The backend stays generic for both.

- **`ladipage` (pixel-perfect, used by Nobel & Flowers)** — ships the **original LadiPage `index.html`
  byte-for-byte** plus its assets, so the design/animations are **100% identical** to the source.
  Editable content is injected in the browser by `resources/js/public/ladipage.ts` (hydration by
  element id; images overridden by filename in the stylesheet), driven by a `ladipage` map in the
  manifest. The flexible gallery + backend-wired RSVP/guestbook are injected into declared sections.
  → `LadiPageRenderer`.
- **`blade` (clean, config-driven)** — a hand-written `index.blade.php` + `sections/*.blade.php` that
  render the resolved `$settings`. Truly unlimited everything, Lighthouse-friendly. Reference copies of
  the clean Nobel/Flowers builds live in `resources/_clean_templates_backup/`. → `InvitationRenderer`.

Both share the same schema → dynamic admin form, media pipeline, live preview, RSVP/guestbook/realtime.

## ➕ Adding a new template (no backend code)

**Option A — clean Blade template**
1. Create `resources/templates/<key>/` with `template.json` (no `engine`, or `"engine":"blade"`) +
   `index.blade.php` + `sections/*.blade.php` + `preview.json` + `assets/`.
2. `template.json` holds the **`sections`** schema (field types: `text, textarea, richtext, number,
   image, gallery, audio, color, font, select, date, time, datetime, boolean, url`; a section may have
   `fields` and/or a `repeater: { item_fields }`), plus `theme` + `theme_schema`.
3. Blades read the resolved `$settings` (images resolved to `{thumb, web, full}`); theme via `--inv-*`
   CSS variables. `preview.json` references bundled assets with `@image:file.jpg` / `@audio:song.mp3`.

**Option B — pixel-perfect from an existing HTML export (LadiPage etc.)**
1. Drop the raw export into `_design_source/<name>/` (must contain `index.html` + `images/fonts/js/media`),
   then **scaffold it**:
   ```bash
   php artisan templates:make <key> --from=<name>   # omit --from if folder == key; add --sync to register now
   ```
   This copies the assets into `assets/`, copies `index.html` while rewriting relative asset paths to
   `/templates/<key>/...`, and writes skeleton `template.json` + `preview.json` (reusable
   gallery/guestbook/rsvp/music sections pre-filled). It never overwrites a hand-authored manifest.
   *(Doing it by hand instead? Just replicate those three steps under `resources/templates/<key>/`.)*
2. Fill in the mapping in `template.json` — `"engine":"ladipage"` is already set; complete the `ladipage`
   block: `text` (`{ELEMENT_ID: "settings.path"}`), `images` (`{"original-file.jpg": "settings.path"}`),
   `nav`, `hide`, `fit`, and `sections` (`{gallery, rsvp, guestbook}` → the section ids to inject into).
   Adjust the `sections` schema (hero/invite/couple stubs) to match the design, and add an
   `assets/images/thumbnail.jpg`.

Then run `php artisan templates:sync` → the template appears in **Create → Choose template** with a
fully auto-generated editor. See `resources/templates/nobel` (ladipage) and the
`resources/_clean_templates_backup/` copies (blade) as references.

---

## 🔒 Security & performance

- Sanctum SPA cookie auth + CSRF; ownership/role policies on every admin action.
- Form Request validation everywhere; reserved-slug protection; mass-assignment guarded.
- Public input sanitized (`strip_tags`) and escaped on output; rate limiting on public + login routes.
- Public HTML cached (key includes `updated_at` → self-invalidating); image `webp` conversions + lazy load.
- Routes are cache-safe (`php artisan route:cache`).

---

## ✅ Tests

```bash
php artisan test
```

Feature coverage: auth guards, invitation CRUD + publish + duplicate, slug routing (published vs draft),
template validation + reserved slugs, ownership policy, RSVP/guestbook submission + moderation + listing,
and template-registry discovery/schema.
