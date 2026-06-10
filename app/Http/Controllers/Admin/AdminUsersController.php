<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminUsersController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $users = User::with('roles:id,name')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users'        => $users,
            'roles'        => Role::orderBy('name')->pluck('name'),
            'accountTypes' => collect(AccountType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $this->accountTypeLabel($t),
            ]),
            'can' => [
                'create'      => $actor->can('users.edit'),
                'edit'        => $actor->can('users.edit'),
                'delete'      => $actor->can('users.delete'),
                'manageRoles' => $actor->can('roles.manage'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('users.edit'), 403, 'Nemáte oprávnenie vytvárať používateľov.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'password'     => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'is_active'    => ['required', 'boolean'],
            'roles'        => ['array'],
            'roles.*'      => ['string', Rule::exists('roles', 'name')],
        ]);

        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'] ?? null,
            'password'     => Hash::make($data['password']),
            'account_type' => $data['account_type'],
            'is_active'    => $data['is_active'],
        ]);

        // Accounts created by an admin are trusted – no e-mail verification needed.
        $user->markEmailAsVerified();

        // Roles: super_admin may pick explicitly; otherwise assign the default role.
        $accountType = AccountType::from($data['account_type']);
        if ($actor->can('roles.manage') && ! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        } else {
            $user->assignRole($accountType->defaultRole());
        }

        $this->audit($request, 'user.created', $user, [
            'name'         => $user->name,
            'email'        => $user->email,
            'account_type' => $user->account_type->value,
            'roles'        => $user->getRoleNames(),
        ]);

        return back()->with('success', "Používateľ „{$user->name}“ bol vytvorený.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('users.edit'), 403, 'Nemáte oprávnenie upravovať používateľov.');

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'        => ['nullable', 'string', 'max:30'],
            'password'     => ['nullable', 'confirmed', Password::defaults()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'is_active'    => ['required', 'boolean'],
            'roles'        => ['array'],
            'roles.*'      => ['string', Rule::exists('roles', 'name')],
        ]);

        // Guard: an admin must not lock themselves out by deactivating their own account.
        if ($user->id === $actor->id && ! $data['is_active']) {
            return back()->with('error', 'Nemôžete deaktivovať vlastný účet.');
        }

        $accountTypeChanged = $user->account_type->value !== $data['account_type'];

        $user->fill([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'] ?? null,
            'account_type' => $data['account_type'],
            'is_active'    => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Role changes are reserved for users who can manage roles (super_admin).
        if ($actor->can('roles.manage') && array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        } elseif ($accountTypeChanged) {
            // Keep roles consistent with the (new) account type for plain admins.
            $user->syncRoles([AccountType::from($data['account_type'])->defaultRole()]);
        }

        $this->audit($request, 'user.updated', $user, [
            'name'             => $user->name,
            'email'            => $user->email,
            'account_type'     => $user->account_type->value,
            'is_active'        => $user->is_active,
            'roles'            => $user->getRoleNames(),
            'password_changed' => ! empty($data['password']),
        ]);

        return back()->with('success', "Používateľ „{$user->name}“ bol upravený.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('users.delete'), 403, 'Nemáte oprávnenie mazať používateľov.');

        if ($user->id === $actor->id) {
            return back()->with('error', 'Nemôžete zmazať vlastný účet.');
        }

        $snapshot = ['name' => $user->name, 'email' => $user->email];

        // Free up the email so it can be reused for a new registration, while
        // keeping the (soft-deleted) record for data integrity and audit.
        $user->forceFill([
            'email' => $user->email.'.deleted.'.$user->id.'.'.now()->timestamp,
        ])->save();

        $user->delete();

        $this->audit($request, 'user.deleted', $user, $snapshot);

        return back()->with('success', "Používateľ „{$snapshot['name']}“ bol zmazaný.");
    }

    private function audit(Request $request, string $action, User $subject, array $metadata = []): void
    {
        AuditEvent::create([
            'actor_id'     => $request->user()?->id,
            'action'       => $action,
            'subject_type' => User::class,
            'subject_id'   => $subject->id,
            'metadata'     => $metadata,
            'ip_address'   => $request->ip(),
        ]);
    }

    private function accountTypeLabel(AccountType $type): string
    {
        return match ($type) {
            AccountType::Student    => 'Študent',
            AccountType::Company    => 'Firma',
            AccountType::Mentor     => 'Mentor',
            AccountType::Editor     => 'Editor',
            AccountType::Admin      => 'Admin',
            AccountType::SuperAdmin => 'Super Admin',
        };
    }
}
