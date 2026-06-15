<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexMaps\OrganizationSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public int $organizationId,
    ) {}

    public function handle(OrganizationSyncService $syncService): void
    {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            return;
        }

        $syncService->sync($organization);
    }

    public function failed(?\Throwable $exception): void
    {
        $organization = Organization::find($this->organizationId);

        if (! $organization || ! in_array($organization->parse_status, ['pending', 'parsing'], true)) {
            return;
        }

        $organization->update([
            'parse_status' => 'error',
            'parse_error' => $exception?->getMessage() ?? 'Загрузка прервана',
        ]);
    }
}
