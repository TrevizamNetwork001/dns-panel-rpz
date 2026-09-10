<?php

namespace App\Http\Controllers;

use App\Models\RblDelistRequest;
use App\Models\RblEvent;
use App\Services\Rbl\RblDelistService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RblDelistController extends Controller
{
    public function store(Request $request, RblEvent $event, RblDelistService $service)
    {
        $service->create($event, $request->user(), $this->validated($request));

        return back()->with('success', 'Status do delist atualizado. O status técnico do evento não foi alterado.');
    }

    public function update(Request $request, RblEvent $event, RblDelistRequest $delistRequest, RblDelistService $service)
    {
        abort_unless($delistRequest->rbl_event_id === $event->id, 404);
        $service->update($event, $delistRequest, $request->user(), $this->validated($request, true));

        return back()->with('success', 'Dados do delist atualizados. O status técnico do evento não foi alterado.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'status' => [$presence, Rule::in(RblDelistRequest::STATUSES)],
            'requested_at' => ['nullable', 'date'],
            'request_url' => ['nullable', 'url:http,https', 'max:2048'],
            'protocol' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
