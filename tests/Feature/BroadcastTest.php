<?php

use App\Enums\ProgramType;
use App\Models\Broadcast;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;

function admin(): User
{
    $u = User::factory()->create();
    $u->assignRole('admin');

    return $u;
}

it('sends a broadcast to a role and records it', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $mentor = User::factory()->create();
    $mentor->assignRole('mentor');

    $this->actingAs(admin())->post('/admin/broadcasts', [
        'audience' => 'role:student', 'subject' => 'Pozor', 'body' => 'Dôležitý oznam.',
    ])->assertSessionHasNoErrors();

    expect($student->notifications()->count())->toBe(1)
        ->and($mentor->notifications()->count())->toBe(0)
        ->and(Broadcast::count())->toBe(1)
        ->and(Broadcast::first()->recipients_count)->toBe(1);
});

it('sends a broadcast to applicants of a specific call', function () {
    $program = Program::factory()->create(['type' => ProgramType::A->value]);
    $call = Call::factory()->create(['program_id' => $program->id]);
    $applicant = User::factory()->create();
    $applicant->applications()->create([
        'public_id' => (string) Str::ulid(), 'call_id' => $call->id, 'status' => 'submitted', 'title' => 'X',
    ]);
    $outsider = User::factory()->create();

    $this->actingAs(admin())->post('/admin/broadcasts', [
        'audience' => 'call', 'call_id' => $call->id, 'subject' => 'Výzva', 'body' => 'Info k výzve.',
    ])->assertSessionHasNoErrors();

    expect($applicant->notifications()->count())->toBe(1)
        ->and($outsider->notifications()->count())->toBe(0);
});

it('requires a call when audience is a call', function () {
    $this->actingAs(admin())->post('/admin/broadcasts', [
        'audience' => 'call', 'subject' => 'X', 'body' => 'Y',
    ])->assertSessionHasErrors('call_id');
});

it('forbids non-admins from broadcasting', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $this->actingAs($student)->post('/admin/broadcasts', [
        'audience' => 'all', 'subject' => 'X', 'body' => 'Y',
    ])->assertForbidden();
});
