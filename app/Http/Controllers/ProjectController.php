<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\MentorshipStatus;
use App\Http\Controllers\Concerns\LogsAudit;
use App\Http\Controllers\Concerns\NotifiesSafely;
use App\Notifications\MentorAssignedNotification;
use App\Models\Application;
use App\Models\Consultation;
use App\Models\Mentorship;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use LogsAudit, NotifiesSafely;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Application::where('status', ApplicationStatus::Approved->value)
            ->with([
                'user:id,name',
                'call:id,title,program_id',
                'call.program:id,title,type',
                'activeMentorship.mentor:id,name',
            ])
            ->withCount([
                'milestones',
                'milestones as completed_milestones_count' => fn ($q) => $q->where('is_completed', true),
            ]);

        if ($this->isAdmin($user)) {
            // all approved projects
        } elseif ($user->hasRole('mentor')) {
            $query->whereHas('mentorships', fn ($q) => $q->where('mentor_id', $user->id)->where('status', 'active'));
        } else {
            $query->where('user_id', $user->id);
        }

        $projects = $query->orderByDesc('decided_at')->get()->map(fn (Application $a) => [
            'id'                 => $a->id,
            'title'              => $a->title,
            'applicant'          => $a->user?->name,
            'call'               => $a->call?->title,
            'program_type'       => $a->call?->program?->type->value,
            'mentor'             => $a->activeMentorship?->mentor?->name,
            'milestones_count'   => $a->milestones_count,
            'completed_count'    => $a->completed_milestones_count,
        ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'role'     => $this->isAdmin($user) ? 'admin' : ($user->hasRole('mentor') ? 'mentor' : 'student'),
        ]);
    }

    public function show(Request $request, Application $application): Response
    {
        $this->authorizeView($request, $application);

        $application->load([
            'user:id,name,email',
            'call.program',
            'activeMentorship.mentor:id,name',
            'milestones',
            'consultations.author:id,name',
        ]);

        $isAdmin  = $this->isAdmin($request->user());
        $canManage = $this->canManage($request->user(), $application);

        return Inertia::render('Projects/Show', [
            'project' => [
                'id'          => $application->id,
                'public_id'   => $application->public_id,
                'title'       => $application->title,
                'description' => $application->description,
                'status'      => $application->status->value,
                'applicant'   => ['name' => $application->user?->name, 'email' => $application->user?->email],
                'call'        => ['title' => $application->call->title, 'program' => ['title' => $application->call->program->title, 'type' => $application->call->program->type->value]],
            ],
            'mentor'       => $application->activeMentorship?->mentor
                ? ['id' => $application->activeMentorship->mentor->id, 'name' => $application->activeMentorship->mentor->name]
                : null,
            'milestones'   => $application->milestones->map(fn (Milestone $m) => [
                'id'           => $m->id,
                'title'        => $m->title,
                'description'  => $m->description,
                'due_at'       => $m->due_at,
                'is_completed' => $m->is_completed,
                'completed_at' => $m->completed_at,
            ]),
            'consultations' => $application->consultations->map(fn (Consultation $c) => [
                'id'      => $c->id,
                'summary' => $c->summary,
                'met_at'  => $c->met_at,
                'author'  => $c->author?->name,
            ]),
            'mentors' => $isAdmin ? User::role('mentor')->orderBy('name')->get(['id', 'name']) : [],
            'can'     => [
                'assignMentor' => $isAdmin,
                'manage'       => $canManage,
            ],
        ]);
    }

    public function assignMentor(Request $request, Application $application): RedirectResponse
    {
        abort_unless($this->isAdmin($request->user()), 403, 'Mentora môže prideliť iba administrátor.');
        abort_unless($application->status === ApplicationStatus::Approved, 403, 'Mentora možno prideliť len schválenému projektu.');

        $data = $request->validate([
            'mentor_id' => ['required', Rule::exists('users', 'id')],
        ]);

        $mentor = User::role('mentor')->findOrFail($data['mentor_id']);

        // Close any current active mentorship, then assign the new one.
        $application->mentorships()
            ->where('status', MentorshipStatus::Active->value)
            ->update(['status' => MentorshipStatus::Cancelled, 'ended_at' => now()]);

        Mentorship::create([
            'application_id' => $application->id,
            'mentor_id'      => $mentor->id,
            'status'         => MentorshipStatus::Active,
            'started_at'     => now(),
        ]);

        $this->audit($request, 'mentor.assigned', Application::class, $application->id, ['mentor_id' => $mentor->id]);

        $this->notifySafely($application->user, new MentorAssignedNotification($application, $mentor));
        $this->notifySafely($mentor, new MentorAssignedNotification($application, $mentor));

        return back()->with('success', "Mentor {$mentor->name} bol pridelený projektu.");
    }

    public function storeMilestone(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeManage($request, $application);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at'      => ['nullable', 'date'],
        ]);

        $application->milestones()->create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'due_at'      => $data['due_at'] ?? null,
            'order'       => (int) $application->milestones()->max('order') + 1,
        ]);

        return back()->with('success', 'Míľnik bol pridaný.');
    }

    public function updateMilestone(Request $request, Application $application, Milestone $milestone): RedirectResponse
    {
        $this->authorizeManage($request, $application);
        abort_unless($milestone->application_id === $application->id, 404);

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'due_at'       => ['nullable', 'date'],
            'is_completed' => ['required', 'boolean'],
        ]);

        $milestone->update([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'due_at'       => $data['due_at'] ?? null,
            'is_completed' => $data['is_completed'],
            'completed_at' => $data['is_completed'] ? ($milestone->completed_at ?? now()) : null,
        ]);

        return back()->with('success', 'Míľnik bol upravený.');
    }

    public function destroyMilestone(Request $request, Application $application, Milestone $milestone): RedirectResponse
    {
        $this->authorizeManage($request, $application);
        abort_unless($milestone->application_id === $application->id, 404);

        $milestone->delete();

        return back()->with('success', 'Míľnik bol odstránený.');
    }

    public function storeConsultation(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeManage($request, $application);

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
            'met_at'  => ['nullable', 'date'],
        ]);

        $application->consultations()->create([
            'author_id' => $request->user()->id,
            'summary'   => $data['summary'],
            'met_at'    => $data['met_at'] ?? now(),
        ]);

        $this->audit($request, 'consultation.recorded', Application::class, $application->id);

        return back()->with('success', 'Záznam z konzultácie bol pridaný.');
    }

    // ---------------------------------------------------------------------

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin']);
    }

    private function canManage(User $user, Application $application): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->hasRole('mentor')
            && $application->mentorships()->where('mentor_id', $user->id)->where('status', 'active')->exists();
    }

    private function authorizeManage(Request $request, Application $application): void
    {
        abort_unless($this->canManage($request->user(), $application), 403, 'Nemáte oprávnenie spravovať tento projekt.');
    }

    private function authorizeView(Request $request, Application $application): void
    {
        $user = $request->user();
        $canView = $this->canManage($user, $application) || $application->user_id === $user->id;
        abort_unless($canView, 403, 'Nemáte prístup k tomuto projektu.');
    }
}
