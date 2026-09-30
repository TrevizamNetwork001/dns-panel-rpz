<?php

namespace App\Jobs;

use App\Models\RpzZone;
use App\Services\RpzPrimaryNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Network timeouts must not hold the zone's publication lock. */
class NotifyRpzZone implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 90;

    public function __construct(public string $zoneKey, public int $versionId) {}

    public function handle(RpzPrimaryNotifier $notifier): void
    {
        $zone = RpzZone::where('zone_key', $this->zoneKey)->with('currentVersion')->first();
        // A newer publication already has its own notification.
        if ($zone !== null && (int) $zone->current_version_id === $this->versionId) {
            $notifier->notify($zone);
        }
    }
}
