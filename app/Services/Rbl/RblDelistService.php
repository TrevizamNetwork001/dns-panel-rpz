<?php

namespace App\Services\Rbl;

use App\Models\AuditLog;
use App\Models\RblDelistRequest;
use App\Models\RblEvent;
use App\Models\User;

class RblDelistService
{
    public function create(RblEvent $event, User $user, array $data): RblDelistRequest
    {
        $previous = $event->delistRequests()->first();
        if ($previous) {
            $data = array_merge($previous->only(['requested_at', 'request_url', 'protocol', 'contact_email', 'notes']), $data);
        }
        $data['user_id'] = $user->id;
        if ($data['status'] === 'requested' && empty($data['requested_at'])) {
            $data['requested_at'] = now();
        }

        $delistRequest = $event->delistRequests()->create($data);
        $this->audit($event, $delistRequest->status);

        return $delistRequest;
    }

    public function update(RblEvent $event, RblDelistRequest $delistRequest, User $user, array $data): RblDelistRequest
    {
        $data['user_id'] = $user->id;
        if (($data['status'] ?? $delistRequest->status) === 'requested' && ! $delistRequest->requested_at && empty($data['requested_at'])) {
            $data['requested_at'] = now();
        }
        $delistRequest->update($data);
        $this->audit($event, $delistRequest->status, true);

        return $delistRequest;
    }

    public function suggestedText(RblEvent $event): string
    {
        $lines = [
            'Solicitação de revisão/remoção de blacklist', '',
            'IP afetado: '.($event->last_checked_value ?: '—'),
            'Lista/RBL: '.($event->list?->name ?: '—'),
            'Resposta DNSBL: '.($event->last_response ?: '—'),
            'Primeira detecção: '.($event->first_seen_at?->format('d/m/Y H:i') ?: '—'),
            'Última detecção: '.($event->last_seen_at?->format('d/m/Y H:i') ?: '—'),
            'Status no painel: '.$event->status,
        ];
        if ($event->target?->group) {
            $lines[] = 'Grupo: '.$event->target->group->name;
        }
        $lines[] = '';
        $lines[] = 'Informamos que o caso foi analisado pela operação de rede. Solicitamos a revisão da listagem deste IP conforme a política da RBL.';

        return implode("\n", $lines);
    }

    private function audit(RblEvent $event, string $status, bool $updated = false): void
    {
        $action = $updated ? 'rbl.delist.updated' : match ($status) {
            'instructions_viewed' => 'rbl.delist.instructions_viewed',
            'requested' => 'rbl.delist.requested',
            'accepted' => 'rbl.delist.accepted',
            'rejected' => 'rbl.delist.rejected',
            'not_applicable' => 'rbl.delist.not_applicable',
            default => 'rbl.delist.updated',
        };
        AuditLog::record($action, "Delist RBL atualizado para evento #{$event->id} [status: {$status}]", null, 'rbl_event', $event->id);
    }
}
