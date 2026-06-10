<?php

use App\Enums\ProgramType;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;

function appInStatus(string $status): Application
{
    $program = Program::factory()->create(['type' => ProgramType::A->value]);
    $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);

    return User::factory()->create()->applications()->create([
        'public_id' => (string) Str::ulid(),
        'call_id'   => $call->id,
        'status'    => $status,
        'title'     => 'Projekt',
        'submitted_at' => now(),
    ]);
}

it('notifies the applicant when their application is approved', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $app = appInStatus('submitted');

    $this->actingAs($admin)->post("/evaluations/{$app->id}/decide", ['decision' => 'approved']);

    expect($app->user->fresh()->notifications()->count())->toBe(1)
        ->and($app->user->unreadNotifications()->count())->toBe(1);
});

it('notifies the applicant when a supplement is requested', function () {
    $evaluator = User::factory()->create();
    $evaluator->assignRole('evaluator');
    $app = appInStatus('submitted');

    $this->actingAs($evaluator)->post("/evaluations/{$app->id}/request-supplement", ['note' => 'Doplňte.']);

    expect($app->user->notifications()->count())->toBe(1);
});

it('notifies both the applicant and mentor on assignment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $mentor = User::factory()->create();
    $mentor->assignRole('mentor');
    $app = appInStatus('approved');

    $this->actingAs($admin)->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $mentor->id]);

    expect($app->user->notifications()->count())->toBe(1)
        ->and($mentor->notifications()->count())->toBe(1);
});

it('lets a user view and mark notifications as read', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $app = appInStatus('submitted');
    $this->actingAs($admin)->post("/evaluations/{$app->id}/decide", ['decision' => 'approved']);

    $applicant = $app->user;
    expect($applicant->unreadNotifications()->count())->toBe(1);

    $this->actingAs($applicant)->get('/notifications')->assertOk();

    $this->actingAs($applicant)->post('/notifications/read-all');
    expect($applicant->unreadNotifications()->count())->toBe(0);
});
