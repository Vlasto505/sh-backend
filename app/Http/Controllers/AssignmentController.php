<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Concerns\LogsAudit;
use App\Models\Assignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    use LogsAudit;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $this->isAdmin($user);

        $query = Assignment::with(['organization:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s));

        if ($isAdmin) {
            // NTI sees the whole backlog
        } else {
            $orgIds = $user->organizations()->pluck('organizations.id');
            $query->whereIn('organization_id', $orgIds);
        }

        $assignments = $query->latest()->get()->map(fn (Assignment $a) => [
            'id'           => $a->id,
            'title'        => $a->title,
            'organization' => $a->organization?->name,
            'status'       => $a->status->value,
            'status_label' => $a->status->label(),
            'budget'       => $a->budget,
        ]);

        return Inertia::render('Assignments/Index', [
            'assignments' => $assignments,
            'isAdmin'     => $isAdmin,
            'myOrgs'      => $isAdmin ? [] : $user->organizations()->get(['organizations.id', 'name']),
            'statuses'    => AssignmentStatus::options(),
            'filterStatus'=> $request->input('status', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', Rule::exists('organizations', 'id')],
            ...$this->fieldRules(),
        ]);

        $org = Organization::findOrFail($data['organization_id']);
        $this->authorizeOrg($request->user(), $org);

        $assignment = $org->assignments()->create([
            'public_id'      => (string) Str::ulid(),
            'title'          => $data['title'],
            'summary'        => $data['summary'] ?? null,
            'technical_spec' => $data['technical_spec'] ?? null,
            'product_owner'  => $data['product_owner'] ?? null,
            'budget'         => $data['budget'] ?? null,
            'expectations'   => $data['expectations'] ?? null,
            'status'         => AssignmentStatus::Draft,
            'created_by'     => $request->user()->id,
        ]);

        return redirect()->route('assignments.show', $assignment)->with('success', 'Zadanie bolo vytvorené.');
    }

    public function show(Request $request, Assignment $assignment): Response
    {
        $assignment->load('organization:id,name');
        $this->authorizeView($request, $assignment);

        return Inertia::render('Assignments/Show', [
            'assignment' => [
                'id'             => $assignment->id,
                'public_id'      => $assignment->public_id,
                'title'          => $assignment->title,
                'summary'        => $assignment->summary,
                'technical_spec' => $assignment->technical_spec,
                'product_owner'  => $assignment->product_owner,
                'budget'         => $assignment->budget,
                'expectations'   => $assignment->expectations,
                'status'         => $assignment->status->value,
                'status_label'   => $assignment->status->label(),
                'organization'   => ['id' => $assignment->organization->id, 'name' => $assignment->organization->name],
            ],
            'statuses'      => AssignmentStatus::options(),
            'canManage'     => $this->canManage($request->user(), $assignment),
            'isAdmin'       => $this->isAdmin($request->user()),
            'companyStatuses' => AssignmentStatus::companySettable(),
        ]);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeManage($request, $assignment);

        $assignment->update($request->validate($this->fieldRules()));

        return back()->with('success', 'Zadanie bolo upravené.');
    }

    public function updateStatus(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeManage($request, $assignment);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AssignmentStatus::class)],
        ]);

        // Company contacts may only publish/unpublish (draft <-> backlog);
        // the rest of the pairing pipeline is driven by NTI (admin).
        if (! $this->isAdmin($request->user())
            && ! in_array($data['status'], AssignmentStatus::companySettable(), true)) {
            return back()->with('error', 'Tento stav môže nastaviť iba administrátor NTI.');
        }

        $from = $assignment->status->value;
        $assignment->update(['status' => $data['status']]);

        $this->audit($request, 'assignment.status_changed', Assignment::class, $assignment->id, [
            'from' => $from, 'to' => $data['status'],
        ]);

        return back()->with('success', 'Stav zadania bol zmenený.');
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeManage($request, $assignment);

        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', 'Zadanie bolo zmazané.');
    }

    // ---------------------------------------------------------------------

    private function fieldRules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'summary'        => ['nullable', 'string', 'max:2000'],
            'technical_spec' => ['nullable', 'string', 'max:10000'],
            'product_owner'  => ['nullable', 'string', 'max:255'],
            'budget'         => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'expectations'   => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin']);
    }

    private function authorizeOrg(User $user, Organization $org): void
    {
        $canManage = $this->isAdmin($user)
            || ($user->can('organizations.edit_own') && $org->users()->where('users.id', $user->id)->exists());
        abort_unless($canManage, 403, 'Nemáte oprávnenie spravovať zadania tejto firmy.');
    }

    private function canManage(User $user, Assignment $assignment): bool
    {
        return $this->isAdmin($user)
            || ($user->can('organizations.edit_own')
                && $assignment->organization->users()->where('users.id', $user->id)->exists());
    }

    private function authorizeView(Request $request, Assignment $assignment): void
    {
        abort_unless($this->canManage($request->user(), $assignment), 403, 'Nemáte prístup k tomuto zadaniu.');
    }

    private function authorizeManage(Request $request, Assignment $assignment): void
    {
        abort_unless($this->canManage($request->user(), $assignment), 403, 'Nemáte oprávnenie spravovať toto zadanie.');
    }
}
