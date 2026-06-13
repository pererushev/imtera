<template>
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <h2 class="text-2xl font-bold mb-2">Вход</h2>
            <p class="text-gray-500 text-sm mb-6">Войдите для управления отзывами организации</p>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="admin@imtera.test"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Пароль</label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="password"
                    />
                </div>

                <div v-if="error" class="text-red-600 text-sm">{{ error }}</div>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ auth.loading ? 'Вход...' : 'Войти' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const email = ref('admin@imtera.test');
const password = ref('password');
const error = ref('');

async function handleLogin() {
    error.value = '';
    const result = await auth.login(email.value, password.value);
    if (result.success) {
        router.push({ name: 'settings' });
    } else {
        error.value = result.message;
    }
}
</script>
