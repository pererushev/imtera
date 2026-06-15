<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use App\Services\YandexMaps\OrganizationSyncService;
use App\Services\YandexMaps\YandexUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly YandexUrlValidator $urlValidator,
        private readonly OrganizationSyncService $syncService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $organization = Organization::where('user_id', $request->user()->id)->first();

        if (! $organization) {
            return response()->json(['organization' => null]);
        }

        return response()->json([
            'organization' => $this->formatOrganization($organization),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'yandex_url' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $normalizedUrl = $this->urlValidator->validate($data['yandex_url']);
        } catch (YandexMapsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $organization = Organization::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'yandex_url' => $normalizedUrl,
                'parse_status' => 'pending',
                'parse_error' => null,
            ],
        );

        $organization = $this->syncService->queueSync($organization);

        return response()->json([
            'message' => 'Ссылка сохранена, загрузка отзывов запущена',
            'organization' => $this->formatOrganization($organization),
        ], 202);
    }

    public function sync(Request $request): JsonResponse
    {
        $organization = Organization::where('user_id', $request->user()->id)->first();

        if (! $organization) {
            return response()->json(['message' => 'Сначала укажите ссылку на организацию'], 404);
        }

        if (in_array($organization->parse_status, ['pending', 'parsing'], true)) {
            return response()->json([
                'message' => 'Загрузка уже выполняется',
                'organization' => $this->formatOrganization($organization),
            ], 202);
        }

        $organization = $this->syncService->queueSync($organization);

        return response()->json([
            'message' => 'Обновление данных запущено',
            'organization' => $this->formatOrganization($organization),
        ], 202);
    }

    private function formatOrganization(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'yandex_url' => $organization->yandex_url,
            'org_id' => $organization->org_id,
            'name' => $organization->name,
            'rating' => $organization->rating ? (float) $organization->rating : null,
            'reviews_count' => $organization->reviews_count,
            'ratings_count' => $organization->ratings_count,
            'parsed_at' => $organization->parsed_at?->toIso8601String(),
            'parse_status' => $organization->parse_status,
            'parse_error' => $organization->parse_error,
            'fetched_reviews_count' => $organization->reviews()->count(),
        ];
    }
}
