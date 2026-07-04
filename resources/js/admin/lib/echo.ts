import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/** Create a Laravel Echo instance wired to Reverb (admin realtime). */
export function createEcho(): Echo<'reverb'> {
    (window as any).Pusher = Pusher;
    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
