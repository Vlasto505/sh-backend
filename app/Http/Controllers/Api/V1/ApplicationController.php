<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Application;
use App\Models\Call;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApplicationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Application::class);

        $query = Application::with(['call:id,title', 'user:id,name,email', 'team:id,name']);

        if (!$request->user()->hasPermissionTo('applications.view_all')) {
            $query->where('user_id', $request->user()->id);
        }

        $applications = $query->orderByDesc('created_at')->paginate(15);

        return $this->success($applications);
    }

    public function store(Request $request, Call $call): JsonResponse
    {
        $this->authorize('create', Application::class);

        if (!$call->isOpen()) {
            return $this->error('Táto výzva nie je otvorená.', 422);
        }

        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'problem_statement'  => ['nullable', 'string'],
            'proposed_solution'  => ['nullable', 'string'],
            'team_id'            => ['nullable', 'exists:teams,id'],
        ]);

        $application = Application::create([
            ...$validated,
            'public_id' => (string) Str::ulid(),
            'call_id'   => $call->id,
            'user_id'   => $request->user()->id,
            'status'    => ApplicationStatus::Draft,
        ]);

        AuditService::log('application.created', $application);

        return $this->success($application->load(['call:id,title', 'user:id,name']), 201);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorize('view', $application);

        return $this->success($application->load([
            'call:id,title,status',
            'call.program:id,title,type',
            'user:id,name,email',
            'team:id,name',
            'evaluations.evaluator:id,name',
            'milestones',
            'attachments',
        ]));
    }

    public function update(Request $request, Application $application): JsonResponse
    {
        $this->authorize('update', $application);

        $validated = $request->validate([
            'title'             => ['sometimes', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'problem_statement' => ['nullable', 'string'],
            'proposed_solution' => ['nullable', 'string'],
            'team_id'           => ['nullable', 'exists:teams,id'],
        ]);

        $application->update($validated);

        AuditService::log('application.updated', $application);

        return $this->success($application);
    }

    public function submit(Request $request, Application $application): JsonResponse
    {
        $this->authorize('submit', $application);

        $application->update([
            'status'       => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);

        AuditService::log('application.submitted', $application);

        return $this->success(['message' => 'Žiadosť bola odoslaná.', 'application' => $application]);
    }

    public function decide(Request $request, Application $application): JsonResponse
    {
        $this->authorize('decide', $application);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'note'     => ['nullable', 'string'],
        ]);

        $application->update([
            'status'     => $validated['decision'] === 'approved'
                ? ApplicationStatus::Approved
                : ApplicationStatus::Rejected,
            'decided_at' => now(),
        ]);

        AuditService::log("application.{$validated['decision']}", $application, ['note' => $validated['note'] ?? null]);

        return $this->success(['message' => 'Rozhodnutie bolo zaznamenané.', 'application' => $application]);
    }

    public function destroy(Application $application): JsonResponse
    {
        $this->authorize('delete', $application);

        $application->delete();

        AuditService::log('application.deleted', $application);

        return $this->success(['message' => 'Žiadosť bola odstránená.']);
    }
}
