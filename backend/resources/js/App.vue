<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <header v-if="auth.isAuthenticated" class="bg-white border-b border-gray-200">
            <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
                <h1 class="text-lg font-semibold">Imtera — Отзывы Яндекс.Карт</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">{{ auth.user?.email }}</span>
                    <button
                        @click="handleLogout"
                        class="text-sm text-red-600 hover:text-red-700"
                    >
                        Выйти
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-5xl mx-auto px-4 py-8">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>
