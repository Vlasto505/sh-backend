<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Call;
use App\Models\Program;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CallController extends ApiController
{
    public function index(Program $program): JsonResponse
    {
        $this->authorize('viewAny', Call::class);

        $calls = $program->calls()
            ->with('creator:id,name')
            ->where('status', '!=', CallStatus::Draft->value)
            ->orderByDesc('opens_at')
            ->paginate(15);

        return $this->success($calls);
    }

    public function store(Request $request, Program $program): JsonResponse
    {
        $this->authorize('create', Call::class);

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'opens_at'      => ['nullable', 'date'],
            'closes_at'     => ['nullable', 'date', 'after_or_equal:opens_at'],
            'min_team_size' => ['integer', 'min:1', 'max:10'],
            'max_team_size' => ['integer', 'min:1', 'max:20', 'gte:min_team_size'],
        ]);

        $call = $program->calls()->create([
            ...$validated,
            'slug'       => Str::slug($validated['title']) . '-' . Str::random(6),
            'status'     => CallStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        AuditService::log('call.created', $call);

        return $this->success($call, 201);
    }

    public function show(Program $program, Call $call): JsonResponse
    {
        $this->authorize('view', $call);

        return $this->success($call->load('creator:id,name'));
    }

    public function update(Request $request, Program $program, Call $call): JsonResponse
    {
        $this->authorize('update', $call);

        $validated = $request->validate([
            'title'         => ['sometimes', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'status'        => ['sometimes', 'string', 'in:draft,open,closed,archived'],
            'opens_at'      => ['nullable', 'date'],
            'closes_at'     => ['nullable', 'date'],
            'min_team_size' => ['sometimes', 'integer', 'min:1'],
            'max_team_size' => ['sometimes', 'integer', 'min:1'],
        ]);

        $call->update($validated);

        AuditService::log('call.updated', $call);

        return $this->success($call);
    }

    public function destroy(Program $program, Call $call): JsonResponse
    {
        $this->authorize('delete', $call);

        $call->delete();

        AuditService::log('call.deleted', $call);

        return $this->success(['message' => 'Výzva bola odstránená.']);
    }
}
