import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/admin/lib/api';
import type { Invitation, InvitationListItem } from '@/admin/lib/types';

export const useInvitationsStore = defineStore('invitations', () => {
    const items = ref<InvitationListItem[]>([]);
    const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 12 });
    const loading = ref(false);

    async function fetchAll(params: Record<string, string | number> = {}): Promise<void> {
        loading.value = true;
        try {
            const { data } = await api.get('/api/invitations', { params });
            items.value = data.data;
            meta.value = data.meta;
        } finally {
            loading.value = false;
        }
    }

    async function fetchOne(id: number | string): Promise<Invitation> {
        const { data } = await api.get(`/api/invitations/${id}`);
        return data.data;
    }

    async function create(payload: { template_key: string; title?: string }): Promise<Invitation> {
        const { data } = await api.post('/api/invitations', payload);
        return data.data;
    }

    async function update(id: number | string, payload: Partial<Invitation>): Promise<Invitation> {
        const { data } = await api.put(`/api/invitations/${id}`, payload);
        return data.data;
    }

    async function remove(id: number): Promise<void> {
        await api.delete(`/api/invitations/${id}`);
        items.value = items.value.filter((i) => i.id !== id);
    }

    async function duplicate(id: number): Promise<Invitation> {
        const { data } = await api.post(`/api/invitations/${id}/duplicate`);
        return data.data;
    }

    async function setPublished(id: number, publish: boolean): Promise<Invitation> {
        const { data } = await api.post(`/api/invitations/${id}/${publish ? 'publish' : 'unpublish'}`);
        const updated: Invitation = data.data;
        const row = items.value.find((i) => i.id === id);
        if (row) row.status = updated.status;
        return updated;
    }

    return { items, meta, loading, fetchAll, fetchOne, create, update, remove, duplicate, setPublished };
});
