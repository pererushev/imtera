<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">
            {{ organization.name || 'Организация' }}
        </h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-yellow-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-yellow-700">
                    {{ formatRating(organization.rating) }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Средний рейтинг</div>
            </div>

            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-700">
                    {{ organization.reviews_count?.toLocaleString('ru-RU') ?? '—' }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Отзывов</div>
            </div>

            <div class="bg-purple-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-purple-700">
                    {{ organization.ratings_count?.toLocaleString('ru-RU') ?? '—' }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Оценок</div>
            </div>

            <div class="bg-green-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-700">
                    {{ organization.fetched_reviews_count?.toLocaleString('ru-RU') ?? '—' }}
                </div>
                <div class="text-sm text-gray-500 mt-1">Загружено</div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    organization: {
        type: Object,
        required: true,
    },
});

function formatRating(rating) {
    if (!rating) return '—';
    return Number(rating).toFixed(1);
}
</script>
