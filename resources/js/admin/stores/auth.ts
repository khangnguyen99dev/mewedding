import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api, { ensureCsrf } from '@/admin/lib/api';
import type { User } from '@/admin/lib/types';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const ready = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const isAdmin = computed(() => user.value?.roles.includes('admin') ?? false);

    async function fetchUser(): Promise<void> {
        try {
            const { data } = await api.get('/api/me');
            user.value = data.data;
        } catch {
            user.value = null;
        } finally {
            ready.value = true;
        }
    }

    async function login(email: string, password: string, remember = false): Promise<void> {
        await ensureCsrf();
        const { data } = await api.post('/api/login', { email, password, remember });
        user.value = data.data;
    }

    async function logout(): Promise<void> {
        try {
            await api.post('/api/logout');
        } finally {
            user.value = null;
        }
    }

    return { user, ready, isAuthenticated, isAdmin, fetchUser, login, logout };
});
