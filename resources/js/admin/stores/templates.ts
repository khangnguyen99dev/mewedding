import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/admin/lib/api';
import type { Template } from '@/admin/lib/types';

export const useTemplatesStore = defineStore('templates', () => {
    const list = ref<Template[]>([]);
    const loading = ref(false);

    async function fetchAll(): Promise<void> {
        loading.value = true;
        try {
            const { data } = await api.get('/api/templates');
            list.value = data.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchOne(key: string): Promise<Template> {
        const { data } = await api.get(`/api/templates/${key}`);
        return data.data;
    }

    return { list, loading, fetchAll, fetchOne };
});
