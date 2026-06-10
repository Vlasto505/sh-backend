<?php

use App\Enums\AccountType;
use App\Models\User;

test('registration screen can be rendered', function () {
    $this->get('/register')->assertStatus(200);
});

test('student can register and gets student role', function () {
    $response = $this->post('/register', [
        'name'                  => 'Ján Novák',
        'email'                 => 'jan@example.sk',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type'          => 'student',
        'gdpr_consent'          => true,
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'jan@example.sk')->first();
    expect($user)->not->toBeNull();
    expect($user->account_type)->toBe(AccountType::Student);
    expect($user->hasRole('student'))->toBeTrue();
});

test('company user can register and gets company_contact role', function () {
    $this->post('/register', [
        'name'                  => 'Firma Kontakt',
        'email'                 => 'firma@example.sk',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type'          => 'company',
        'gdpr_consent'          => true,
    ]);

    $user = User::where('email', 'firma@example.sk')->first();
    expect($user->account_type)->toBe(AccountType::Company);
    expect($user->hasRole('company_contact'))->toBeTrue();
});

test('registration fails without account_type', function () {
    $response = $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('account_type');
});

test('registration fails with invalid account_type', function () {
    $response = $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type'          => 'hacker',
    ]);

    $response->assertSessionHasErrors('account_type');
});
