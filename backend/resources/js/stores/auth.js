import { defineStore } from 'pinia';
import api from '../api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        checked: false,
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
    },

    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/user');
                this.user = data.user;
            } catch {
                this.user = null;
            } finally {
                this.checked = true;
            }
        },

        async login(email, password) {
            this.loading = true;
            try {
                await api.get('/sanctum/csrf-cookie');
                const { data } = await api.post('/login', { email, password });
                this.user = data.user;
                return { success: true };
            } catch (error) {
                const message = error.response?.data?.message
                    || error.response?.data?.errors?.email?.[0]
                    || 'Ошибка входа';
                return { success: false, message };
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await api.post('/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
