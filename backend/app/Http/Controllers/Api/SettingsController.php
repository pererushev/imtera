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

        try {
            $organization = $this->syncService->sync($organization);
        } catch (YandexMapsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'organization' => $this->formatOrganization($organization->fresh()),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Не удалось получить данные организации. Попробуйте позже.',
                'organization' => $this->formatOrganization($organization->fresh()),
            ], 500);
        }

        return response()->json([
            'message' => 'Настройки сохранены, данные загружены',
            'organization' => $this->formatOrganization($organization),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $organization = Organization::where('user_id', $request->user()->id)->first();

        if (! $organization) {
            return response()->json(['message' => 'Сначала укажите ссылку на организацию'], 404);
        }

        try {
            $organization = $this->syncService->sync($organization);
        } catch (YandexMapsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'organization' => $this->formatOrganization($organization->fresh()),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Не удалось обновить данные. Попробуйте позже.',
                'organization' => $this->formatOrganization($organization->fresh()),
            ], 500);
        }

        return response()->json([
            'message' => 'Данные обновлены',
            'organization' => $this->formatOrganization($organization),
        ]);
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
