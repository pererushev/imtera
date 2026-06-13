<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold mb-4">Отзывы</h3>

        <div v-if="loading" class="text-center py-8 text-gray-500">
            Загрузка отзывов...
        </div>

        <div v-else-if="error" class="text-center py-8 text-red-600">
            {{ error }}
        </div>

        <div v-else-if="!reviews.length" class="text-center py-8 text-gray-500">
            Отзывы не найдены
        </div>

        <template v-else>
            <div class="space-y-4">
                <div
                    v-for="review in reviews"
                    :key="review.id"
                    class="border border-gray-100 rounded-lg p-4"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium">{{ review.author || 'Аноним' }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-yellow-500">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</span>
                            <span class="text-sm text-gray-400">{{ formatDate(review.date) }}</span>
                        </div>
                    </div>
                    <p v-if="review.text" class="text-gray-700 text-sm leading-relaxed">
                        {{ review.text }}
                    </p>
                    <p v-else class="text-gray-400 text-sm italic">Без текста</p>
                </div>
            </div>

            <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
                <button
                    @click="$emit('page-change', pagination.current_page - 1)"
                    :disabled="pagination.current_page <= 1"
                    class="px-3 py-1 border rounded-md text-sm disabled:opacity-40 hover:bg-gray-50"
                >
                    ← Назад
                </button>

                <span class="text-sm text-gray-500">
                    Страница {{ pagination.current_page }} из {{ pagination.last_page }}
                </span>

                <button
                    @click="$emit('page-change', pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page"
                    class="px-3 py-1 border rounded-md text-sm disabled:opacity-40 hover:bg-gray-50"
                >
                    Вперёд →
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
defineProps({
    reviews: { type: Array, default: () => [] },
    pagination: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

defineEmits(['page-change']);

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}
</script>
