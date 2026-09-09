<?php

namespace App\Services\Rbl;

use App\Models\RblCheck;
use App\Models\RblEvent;
use App\Models\RblTarget;
use Illuminate\Support\Facades\DB;

class RblEventService
{
    public function record(RblCheck $check): void
    {
        if (! in_array($check->status, ['listed', 'clean'], true)) {
            return;
        }
        DB::transaction(function () use ($check) {
            RblTarget::whereKey($check->rbl_target_id)->lockForUpdate()->firstOrFail();
            $events = RblEvent::where('rbl_target_id', $check->rbl_target_id)
                ->where('rbl_list_id', $check->rbl_list_id)->where('status', 'open');
            if ($check->target->type === 'cidr') {
                $events->where('last_checked_value', $check->checked_value);
            }
            if ($check->status === 'clean') {
                $events->update(['status' => 'resolved', 'resolved_at' => $check->checked_at]);

                return;
            }
            $event = $events->first();
            if ($event) {
                $event->update(['last_seen_at' => $check->checked_at, 'last_checked_value' => $check->checked_value, 'last_response' => $check->response]);
            } else {
                RblEvent::create([
                    'rbl_target_id' => $check->rbl_target_id, 'rbl_list_id' => $check->rbl_list_id,
                    'status' => 'open', 'first_seen_at' => $check->checked_at,
                    'last_seen_at' => $check->checked_at, 'last_checked_value' => $check->checked_value, 'last_response' => $check->response,
                ]);
            }
        });
    }
}
