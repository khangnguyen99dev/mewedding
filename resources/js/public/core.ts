/**
 * Shared public-page interactivity, used by BOTH render engines:
 *  - clean Blade templates (public.ts)
 *  - LadiPage pixel-perfect templates (ladipage.ts), after hydration
 *
 * Everything is driven by data-attributes so it is template-agnostic.
 *
 * laravel-echo + pusher-js (~72KB) are NOT imported statically: they are
 * dynamically imported inside initRealtime() so the realtime code splits into
 * its own chunk and never blocks the invitation's first paint.
 */

/* ----------------------------- Countdown ----------------------------- */
function initCountdowns(): void {
    const els = Array.from(document.querySelectorAll<HTMLElement>('[data-countdown]'));
    if (!els.length) return;
    const pad = (n: number) => String(Math.max(0, n)).padStart(2, '0');
    const tick = () => {
        const now = Date.now();
        for (const el of els) {
            const target = new Date(el.dataset.countdown ?? '').getTime();
            if (Number.isNaN(target)) continue;
            const diff = Math.max(0, target - now);
            const set = (u: string, v: string) => {
                const n = el.querySelector<HTMLElement>(`[data-cd="${u}"]`);
                if (n) n.textContent = v;
            };
            set('days', String(Math.floor(diff / 86_400_000)));
            set('hours', pad(Math.floor((diff % 86_400_000) / 3_600_000)));
            set('minutes', pad(Math.floor((diff % 3_600_000) / 60_000)));
            set('seconds', pad(Math.floor((diff % 60_000) / 1000)));
        }
    };
    tick();
    window.setInterval(tick, 1000);
}

/* ------------------------------- Music ------------------------------- */
function initMusic(): void {
    const audio = document.querySelector<HTMLAudioElement>('audio[data-audio], audio');
    const btn = document.querySelector<HTMLElement>('[data-music-toggle]');
    if (!audio) return;
    const play = () => audio.play().then(() => btn?.classList.add('playing')).catch(() => {});
    const pause = () => { audio.pause(); btn?.classList.remove('playing'); };
    btn?.addEventListener('click', () => (audio.paused ? play() : pause()));
    const tryAutoplay = () => { play(); window.removeEventListener('pointerdown', tryAutoplay); };
    window.addEventListener('pointerdown', tryAutoplay, { once: true });
}

/* ------------------------------ Reveal ------------------------------- */
function initReveal(): void {
    const els = Array.from(document.querySelectorAll<HTMLElement>('[data-reveal]'));
    if (!els.length || !('IntersectionObserver' in window)) {
        els.forEach((el) => el.classList.add('is-visible'));
        return;
    }
    const io = new IntersectionObserver((entries) => {
        for (const e of entries) if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
    }, { threshold: 0.12 });
    els.forEach((el) => io.observe(el));
}

/* ----------------------------- Lightbox ------------------------------ */
function initLightbox(): void {
    const imgs = Array.from(document.querySelectorAll<HTMLImageElement>('[data-lightbox]'));
    if (!imgs.length) return;
    const overlay = document.createElement('div');
    overlay.className = 'inv-lightbox';
    overlay.innerHTML = '<img alt="" />';
    const big = overlay.querySelector('img') as HTMLImageElement;
    document.body.appendChild(overlay);
    const close = () => overlay.classList.remove('open');
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
    imgs.forEach((img) => {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', () => { big.src = img.dataset.full || img.currentSrc || img.src; overlay.classList.add('open'); });
    });
}

/* ------------------- RSVP + Guestbook + realtime --------------------- */
const renderedWishIds = new Set<number>();

function csrf(): string {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}
async function postJson(url: string, body: Record<string, unknown>): Promise<any> {
    const res = await fetch(url, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw data;
    return data;
}
function setMsg(form: HTMLFormElement, text: string, ok: boolean) {
    const el = form.querySelector<HTMLElement>('[data-form-msg]');
    if (el) { el.textContent = text; el.style.color = ok ? '#2e7d32' : '#c62828'; }
}
export function spawnHearts(count = 6): void {
    const wrap = document.getElementById('inv-hearts');
    if (!wrap) return;
    const emojis = ['❤️', '🥰', '💕', '🌸', '🎉'];
    for (let i = 0; i < count; i++) {
        const h = document.createElement('div');
        h.className = 'inv-heart';
        h.textContent = emojis[i % emojis.length];
        h.style.left = `${10 + Math.floor((i / count) * 80)}%`;
        h.style.animationDelay = `${(i % 4) * 0.15}s`;
        wrap.appendChild(h);
        setTimeout(() => h.remove(), 4500);
    }
}
export function addWish(w: { id: number; name: string; message: string; emoji?: string | null }, isNew = false): void {
    const list = document.querySelector<HTMLElement>('[data-wishes]');
    if (!list || renderedWishIds.has(w.id)) return;
    renderedWishIds.add(w.id);
    const card = document.createElement('div');
    card.className = 'inv-wish' + (isNew ? ' inv-wish--new' : '');
    const name = document.createElement('p');
    name.className = 'inv-wish__name';
    name.textContent = `${w.name} ${w.emoji ?? ''}`.trim();
    const msg = document.createElement('p');
    msg.className = 'inv-wish__msg';
    msg.textContent = w.message;
    card.append(name, msg);
    list.prepend(card);
}
function setGuestCount(n: number) {
    document.querySelectorAll<HTMLElement>('[data-rsvp-guests]').forEach((el) => (el.textContent = String(n)));
}

function slug(): string { return document.body.dataset.slug ?? ''; }

async function loadInitial() {
    if (!slug()) return;
    try {
        const r = await (await fetch(`/api/public/${slug()}/guestbook`, { headers: { Accept: 'application/json' } })).json();
        (r.data ?? []).reverse().forEach((w: any) => addWish(w));
    } catch { /* ignore */ }
    try {
        const s = await (await fetch(`/api/public/${slug()}/stats`, { headers: { Accept: 'application/json' } })).json();
        setGuestCount(s.stats?.attending_guests ?? 0);
    } catch { /* ignore */ }
}
function wireForms() {
    const rsvp = document.querySelector<HTMLFormElement>('[data-rsvp-form]');
    rsvp?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await postJson(`/api/public/${slug()}/rsvp`, Object.fromEntries(new FormData(rsvp)));
            setMsg(rsvp, res.message ?? 'Cảm ơn bạn!', true);
            setGuestCount(res.stats?.attending_guests ?? 0);
            spawnHearts();
            rsvp.reset();
        } catch (err: any) { setMsg(rsvp, err?.message ?? 'Có lỗi, thử lại.', false); }
    });
    const gb = document.querySelector<HTMLFormElement>('[data-guestbook-form]');
    gb?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await postJson(`/api/public/${slug()}/guestbook`, Object.fromEntries(new FormData(gb)));
            setMsg(gb, res.message ?? 'Cảm ơn!', true);
            if (res.approved && res.data) addWish(res.data, true);
            spawnHearts();
            gb.reset();
        } catch (err: any) { setMsg(gb, err?.message ?? 'Có lỗi, thử lại.', false); }
    });
}
async function initRealtime(): Promise<void> {
    const id = document.body.dataset.invitation;
    if (!id || !import.meta.env.VITE_REVERB_APP_KEY) return;
    try {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);
        (window as any).Pusher = Pusher;
        const echo = new Echo({
            broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        echo.channel(`invitation.${id}.guestbook`).listen('.guestbook.posted', (e: any) => { addWish(e, true); spawnHearts(4); });
        echo.channel(`invitation.${id}.rsvp`).listen('.rsvp.received', (e: any) => setGuestCount(e.attending_guests));
    } catch { /* best-effort */ }
}

/** Run a non-critical task when the browser is idle (falls back to a timeout). */
function whenIdle(fn: () => void): void {
    const ric = (window as any).requestIdleCallback as
        | ((cb: () => void, opts?: { timeout: number }) => void)
        | undefined;
    if (ric) ric(fn, { timeout: 3000 });
    else window.setTimeout(fn, 1200);
}

/** Boot every interactive feature. Safe to call once after the DOM is ready. */
export function bootPublic(): void {
    initCountdowns();
    initMusic();
    initReveal();
    initLightbox();
    loadInitial();
    wireForms();
    // Realtime (websocket + 72KB pusher chunk) is non-critical — defer to idle
    // so it never competes with the initial render/hydration.
    whenIdle(() => { void initRealtime(); });
}
