<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $this->isAdmin($user);

        $query = Organization::withCount(['users', 'assignments']);
        if (! $isAdmin) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        $organizations = $query->latest()->get()->map(fn (Organization $o) => [
            'id'                => $o->id,
            'name'              => $o->name,
            'ico'               => $o->ico,
            'sector'            => $o->sector,
            'is_verified'       => $o->is_verified,
            'users_count'       => $o->users_count,
            'assignments_count' => $o->assignments_count,
        ]);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            'isAdmin'       => $isAdmin,
            'canCreate'     => $user->can('organizations.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('organizations.create'), 403, 'Nemáte oprávnenie registrovať firmu.');

        $data = $this->validateOrg($request);

        $org = Organization::create($data);
        $org->users()->attach($request->user()->id, ['role_in_org' => 'owner', 'is_primary' => true]);

        return redirect()->route('organizations.show', $org)->with('success', 'Firma bola zaregistrovaná.');
    }

    public function show(Request $request, Organization $organization): Response
    {
        $this->authorizeView($request, $organization);

        $organization->load(['users:id,name,email', 'assignments']);
        $canManage = $this->canManage($request->user(), $organization);

        return Inertia::render('Organizations/Show', [
            'organization' => [
                'id'          => $organization->id,
                'name'        => $organization->name,
                'ico'         => $organization->ico,
                'website'     => $organization->website,
                'sector'      => $organization->sector,
                'description' => $organization->description,
                'address'     => $organization->address,
                'city'        => $organization->city,
                'is_verified' => $organization->is_verified,
                'members'     => $organization->users->map(fn (User $u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'email'      => $u->email,
                    'role'       => $u->pivot->role_in_org,
                    'is_primary' => (bool) $u->pivot->is_primary,
                ]),
                'assignments' => $organization->assignments->map(fn ($a) => [
                    'id'     => $a->id,
                    'title'  => $a->title,
                    'status' => $a->status->value,
                    'status_label' => $a->status->label(),
                ]),
            ],
            'canManage' => $canManage,
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        $organization->update($this->validateOrg($request, $organization));

        return back()->with('success', 'Profil firmy bol upravený.');
    }

    public function addMember(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        $data = $request->validate(['email' => ['required', 'email']]);

        $member = User::where('email', $data['email'])->first();
        if (! $member) {
            throw ValidationException::withMessages(['email' => 'Používateľ s týmto e-mailom neexistuje.']);
        }
        if ($organization->users()->where('users.id', $member->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Tento používateľ už je kontaktom firmy.']);
        }

        $organization->users()->attach($member->id, ['role_in_org' => 'contact', 'is_primary' => false]);

        return back()->with('success', "{$member->name} bol pridaný ako kontakt.");
    }

    public function removeMember(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        $pivot = $organization->users()->where('users.id', $user->id)->first();
        if ($pivot && $pivot->pivot->is_primary) {
            return back()->with('error', 'Primárny kontakt firmy nie je možné odstrániť.');
        }

        $organization->users()->detach($user->id);

        return back()->with('success', 'Kontakt bol odstránený.');
    }

    // ---------------------------------------------------------------------

    private function validateOrg(Request $request, ?Organization $org = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'ico'         => ['nullable', 'string', 'max:20', Rule::unique('organizations', 'ico')->ignore($org?->id)],
            'website'     => ['nullable', 'url', 'max:255'],
            'sector'      => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(['admin', 'super_admin']);
    }

    private function isMember(User $user, Organization $organization): bool
    {
        return $organization->users()->where('users.id', $user->id)->exists();
    }

    private function canManage(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user)
            || ($user->can('organizations.edit_own') && $this->isMember($user, $organization));
    }

    private function authorizeView(Request $request, Organization $organization): void
    {
        $user = $request->user();
        abort_unless($this->isAdmin($user) || $this->isMember($user, $organization), 403, 'Nemáte prístup k tejto firme.');
    }

    private function authorizeManage(Request $request, Organization $organization): void
    {
        abort_unless($this->canManage($request->user(), $organization), 403, 'Nemáte oprávnenie spravovať túto firmu.');
    }
}
