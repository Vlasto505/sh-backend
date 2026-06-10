<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends ApiController
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'confirmed', Password::min(8)],
            'phone'        => ['nullable', 'string', 'max:20'],
            'account_type' => ['required', 'string', 'in:student,company,mentor'],
            'gdpr_consent' => ['required', 'accepted'],
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => $validated['password'],
            'phone'             => $validated['phone'] ?? null,
            'account_type'      => $validated['account_type'],
            'is_active'         => true,
            'gdpr_consented_at' => now(),
        ]);

        $user->assignRole($user->account_type->defaultRole());

        event(new Registered($user));

        AuditService::log('user.registered', $user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->error('Nesprávne prihlasovacie údaje.', 401);
        }

        if (!$user->is_active) {
            return $this->error('Účet je deaktivovaný.', 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        AuditService::log('user.login', $user);

        return $this->success([
            'message' => 'Prihlásenie bolo úspešné.',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profilePhoto');

        return $this->success([
            'user' => array_merge($this->formatUser($user), [
                'profile_photo_url' => $user->profilePhoto?->publicUrl(),
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditService::log('user.logout', $request->user());
        $request->user()->currentAccessToken()->delete();

        return $this->success(['message' => 'Odhlásenie bolo úspešné.']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'account_type' => $user->account_type->value,
            'is_active'    => $user->is_active,
            'roles'        => $user->getRoleNames(),
        ];
    }
}
