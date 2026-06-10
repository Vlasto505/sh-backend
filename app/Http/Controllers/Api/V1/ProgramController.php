<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Program;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Program::class);

        $programs = Program::with('creator:id,name')
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->paginate(15);

        return $this->success($programs);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Program::class);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', 'string', 'in:program_a,program_b'],
            'is_active'   => ['boolean'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $program = Program::create([
            ...$validated,
            'slug'       => Str::slug($validated['title']) . '-' . Str::random(6),
            'created_by' => $request->user()->id,
        ]);

        AuditService::log('program.created', $program);

        return $this->success($program->load('creator:id,name'), 201);
    }

    public function show(Program $program): JsonResponse
    {
        $this->authorize('view', $program);

        return $this->success($program->load(['creator:id,name', 'calls' => fn ($q) => $q->where('status', '!=', 'draft')]));
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $this->authorize('update', $program);

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['sometimes', 'string', 'in:program_a,program_b'],
            'is_active'   => ['boolean'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $program->update($validated);

        AuditService::log('program.updated', $program);

        return $this->success($program);
    }

    public function destroy(Program $program): JsonResponse
    {
        $this->authorize('delete', $program);

        $program->delete();

        AuditService::log('program.deleted', $program);

        return $this->success(['message' => 'Program bol odstránený.']);
    }
}
