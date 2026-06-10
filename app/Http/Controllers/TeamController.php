<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $teams = Team::query()
            ->where('leader_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with(['leader:id,name'])
            ->withCount('members')
            ->latest()
            ->get()
            ->map(fn (Team $t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'program_type'  => $t->program_type,
                'description'   => $t->description,
                'leader'        => $t->leader?->name,
                'members_count' => $t->members_count,
                'is_leader'     => $t->leader_id === $user->id,
            ]);

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('teams.create'), 403, 'Nemáte oprávnenie zakladať tímy.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'program_type' => ['required', Rule::in(['A', 'B'])],
            'description'  => ['nullable', 'string', 'max:2000'],
        ]);

        $team = Team::create([
            'name'         => $data['name'],
            'leader_id'    => $request->user()->id,
            'program_type' => $data['program_type'],
            'description'  => $data['description'] ?? null,
        ]);

        // The leader is also a member of the team.
        $team->members()->attach($request->user()->id, ['role_in_team' => 'leader']);

        return redirect()->route('teams.show', $team)->with('success', 'Tím bol vytvorený.');
    }

    public function show(Request $request, Team $team): Response
    {
        $this->authorizeMember($request, $team);

        $team->load(['leader:id,name,email', 'members:id,name,email']);
        $isLeader = $team->leader_id === $request->user()->id;

        return Inertia::render('Teams/Show', [
            'team' => [
                'id'           => $team->id,
                'name'         => $team->name,
                'program_type' => $team->program_type,
                'description'  => $team->description,
                'leader'       => ['id' => $team->leader->id, 'name' => $team->leader->name],
                'members'      => $team->members->map(fn (User $m) => [
                    'id'        => $m->id,
                    'name'      => $m->name,
                    'email'     => $m->email,
                    'role'      => $m->pivot->role_in_team,
                    'is_leader' => $m->id === $team->leader_id,
                ]),
            ],
            'isLeader'     => $isLeader,
            'minTeamHint'  => $team->program_type === 'A' ? 3 : 1,
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeLeader($request, $team);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $team->update($data);

        return back()->with('success', 'Tím bol upravený.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeLeader($request, $team);

        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Tím bol zrušený.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeLeader($request, $team);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $member = User::where('email', $data['email'])->first();

        if (! $member) {
            throw ValidationException::withMessages(['email' => 'Používateľ s týmto e-mailom neexistuje.']);
        }
        if ($team->members()->where('users.id', $member->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Tento používateľ už je členom tímu.']);
        }

        $team->members()->attach($member->id, ['role_in_team' => 'member']);

        return back()->with('success', "{$member->name} bol pridaný do tímu.");
    }

    public function removeMember(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorizeLeader($request, $team);

        if ($user->id === $team->leader_id) {
            return back()->with('error', 'Vedúceho tímu nie je možné odstrániť.');
        }

        $team->members()->detach($user->id);

        return back()->with('success', 'Člen bol odstránený z tímu.');
    }

    public function leave(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeMember($request, $team);

        if ($team->leader_id === $request->user()->id) {
            return back()->with('error', 'Vedúci nemôže opustiť tím. Tím môžete zrušiť.');
        }

        $team->members()->detach($request->user()->id);

        return redirect()->route('teams.index')->with('success', 'Opustili ste tím.');
    }

    // ---------------------------------------------------------------------

    private function authorizeMember(Request $request, Team $team): void
    {
        $user = $request->user();
        $isMember = $team->leader_id === $user->id
            || $team->members()->where('users.id', $user->id)->exists();
        abort_unless($isMember, 403, 'Nie ste členom tohto tímu.');
    }

    private function authorizeLeader(Request $request, Team $team): void
    {
        abort_unless($team->leader_id === $request->user()->id, 403, 'Iba vedúci tímu môže vykonať túto akciu.');
    }
}
