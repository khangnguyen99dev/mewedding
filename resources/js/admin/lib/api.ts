import axios from 'axios';

/**
 * Single axios instance for the admin SPA, configured for Laravel Sanctum
 * cookie (SPA) authentication.
 */
const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

/** Prime the CSRF cookie before the first stateful (POST/PUT/DELETE) request. */
export async function ensureCsrf(): Promise<void> {
    await api.get('/sanctum/csrf-cookie');
}

export default api;
