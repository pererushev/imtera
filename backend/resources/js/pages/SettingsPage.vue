<template>
    <div class="space-y-8">
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-xl font-semibold mb-4">Настройки организации</h2>

            <form @submit.prevent="saveSettings" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Ссылка на карточку в Яндекс.Картах
                    </label>
                    <input
                        v-model="yandexUrl"
                        type="url"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="https://yandex.ru/maps/org/..."
                    />
                    <p class="text-xs text-gray-400 mt-1">
                        Пример: https://yandex.ru/maps/org/yandeks/1124715036/
                    </p>
                </div>

                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="saving || isParsing"
                        class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                    <button
                        v-if="organization"
                        type="button"
                        @click="syncData"
                        :disabled="syncing || isParsing"
                        class="border border-gray-300 py-2 px-4 rounded-md hover:bg-gray-50 disabled:opacity-50"
                    >
                        {{ syncing ? 'Запуск...' : 'Обновить данные' }}
                    </button>
                </div>
            </form>

            <div v-if="settingsError" class="mt-4 p-3 bg-red-50 text-red-700 rounded-md text-sm">
                {{ settingsError }}
            </div>
            <div v-if="settingsSuccess" class="mt-4 p-3 bg-green-50 text-green-700 rounded-md text-sm">
                {{ settingsSuccess }}
            </div>
        </section>

        <section
            v-if="organization && isParsing"
            class="bg-blue-50 border border-blue-200 rounded-lg p-6"
        >
            <h3 class="font-semibold text-blue-800 mb-2">Загрузка отзывов</h3>
            <p class="text-blue-700 text-sm">
                Парсер собирает данные с Яндекс.Карт. Для крупных организаций это может занять несколько минут.
            </p>
        </section>

        <section v-if="organization && organization.parse_status === 'success'">
            <OrganizationStats :organization="organization" />
            <ReviewsList
                :reviews="reviews"
                :pagination="pagination"
                :loading="reviewsLoading"
                :error="reviewsError"
                @page-change="loadReviews"
            />
        </section>

        <section
            v-else-if="organization && organization.parse_status === 'error'"
            class="bg-red-50 border border-red-200 rounded-lg p-6"
        >
            <h3 class="font-semibold text-red-800 mb-2">Ошибка загрузки данных</h3>
            <p class="text-red-700 text-sm">{{ organization.parse_error }}</p>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import api from '../api';
import OrganizationStats from '../components/OrganizationStats.vue';
import ReviewsList from '../components/ReviewsList.vue';

const yandexUrl = ref('');
const organization = ref(null);
const reviews = ref([]);
const pagination = ref(null);
const saving = ref(false);
const syncing = ref(false);
const reviewsLoading = ref(false);
const settingsError = ref('');
const settingsSuccess = ref('');
const reviewsError = ref('');
let pollTimer = null;

const isParsing = computed(() => {
    return ['pending', 'parsing'].includes(organization.value?.parse_status);
});

onMounted(async () => {
    await loadSettings();
    if (organization.value?.parse_status === 'success') {
        await loadReviews(1);
    } else if (isParsing.value) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function startPolling() {
    stopPolling();
    pollTimer = setInterval(pollParseStatus, 3000);
}

async function pollParseStatus() {
    try {
        const { data } = await api.get('/settings');
        organization.value = data.organization;

        if (data.organization?.parse_status === 'success') {
            stopPolling();
            settingsSuccess.value = 'Данные загружены';
            settingsError.value = '';
            await loadReviews(1);
        } else if (data.organization?.parse_status === 'error') {
            stopPolling();
            settingsError.value = data.organization.parse_error || 'Ошибка загрузки данных';
            settingsSuccess.value = '';
        }
    } catch {
        // keep polling on transient network errors
    }
}

async function loadSettings() {
    try {
        const { data } = await api.get('/settings');
        organization.value = data.organization;
        if (data.organization) {
            yandexUrl.value = data.organization.yandex_url;
        }
    } catch {
        settingsError.value = 'Не удалось загрузить настройки';
    }
}

async function saveSettings() {
    saving.value = true;
    settingsError.value = '';
    settingsSuccess.value = '';

    try {
        const { data } = await api.put('/settings', { yandex_url: yandexUrl.value });
        organization.value = data.organization;
        settingsSuccess.value = data.message;

        if (isParsing.value) {
            startPolling();
        } else if (data.organization?.parse_status === 'success') {
            await loadReviews(1);
        }
    } catch (error) {
        settingsError.value = error.response?.data?.message || 'Ошибка сохранения';
        if (error.response?.data?.organization) {
            organization.value = error.response.data.organization;
        }
    } finally {
        saving.value = false;
    }
}

async function syncData() {
    syncing.value = true;
    settingsError.value = '';
    settingsSuccess.value = '';

    try {
        const { data } = await api.post('/settings/sync');
        organization.value = data.organization;
        settingsSuccess.value = data.message;

        if (isParsing.value) {
            startPolling();
        } else if (data.organization?.parse_status === 'success') {
            await loadReviews(1);
        }
    } catch (error) {
        settingsError.value = error.response?.data?.message || 'Ошибка обновления';
        if (error.response?.data?.organization) {
            organization.value = error.response.data.organization;
        }
    } finally {
        syncing.value = false;
    }
}

async function loadReviews(page) {
    reviewsLoading.value = true;
    reviewsError.value = '';

    try {
        const { data } = await api.get('/organization/reviews', { params: { page } });
        organization.value = { ...organization.value, ...data.organization };
        reviews.value = data.reviews;
        pagination.value = data.pagination;
    } catch (error) {
        reviewsError.value = error.response?.data?.message || 'Не удалось загрузить отзывы';
    } finally {
        reviewsLoading.value = false;
    }
}
</script>
