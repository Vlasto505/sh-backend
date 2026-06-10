<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'gdpr_consent' => ['accepted'],
        ], [
            'gdpr_consent.accepted' => 'Pre registráciu je potrebný súhlas so spracovaním osobných údajov.',
        ]);

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'account_type'      => $request->account_type,
            'gdpr_consented_at' => now(),
        ]);

        $user->assignRole($user->account_type->defaultRole());

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
