<?php

use App\Models\User;

it('requires GDPR consent to register', function () {
    $this->post('/register', [
        'name'                  => 'Test',
        'email'                 => 'test@example.com',
        'account_type'          => 'student',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        // no gdpr_consent
    ])->assertSessionHasErrors('gdpr_consent');

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

it('records consent timestamp on registration', function () {
    $this->post('/register', [
        'name'                  => 'Test',
        'email'                 => 'consent@example.com',
        'account_type'          => 'student',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'gdpr_consent'          => true,
    ]);

    $user = User::where('email', 'consent@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->gdpr_consented_at)->not->toBeNull();
});

it('lets a user export their personal data as JSON', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $response = $this->actingAs($user)->get('/profile/data-export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/json');
    expect($response->streamedContent())->toContain('me@example.com');
});

it('purges soft-deleted accounts past the retention window but keeps recent ones', function () {
    $old = User::factory()->create();
    $old->delete();
    $old->forceFill(['deleted_at' => now()->subDays(400)])->saveQuietly();

    $recent = User::factory()->create();
    $recent->delete(); // deleted just now

    $this->artisan('gdpr:purge', ['--days' => 365])->assertSuccessful();

    expect(User::withTrashed()->find($old->id))->toBeNull()           // purged
        ->and(User::withTrashed()->find($recent->id))->not->toBeNull(); // kept
});
