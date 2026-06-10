<?php

use App\Enums\ProgramType;
use App\Models\Application;
use App\Models\Call;
use App\Models\Mentorship;
use App\Models\Program;
use App\Models\User;

function approvedApplication(): Application
{
    $program = Program::factory()->create(['type' => ProgramType::A->value]);
    $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);

    return User::factory()->create()->applications()->create([
        'public_id' => (string) Str::ulid(),
        'call_id'   => $call->id,
        'status'    => 'approved',
        'title'     => 'Schválený projekt',
        'decided_at'=> now(),
    ]);
}

it('lets an admin assign a mentor to an approved project', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $mentor = User::factory()->create();
    $mentor->assignRole('mentor');
    $app = approvedApplication();

    $this->actingAs($admin)
        ->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $mentor->id])
        ->assertSessionHasNoErrors();

    expect($app->activeMentorship?->mentor_id)->toBe($mentor->id);
});

it('reassigns a mentor by closing the previous active mentorship', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $m1 = User::factory()->create(); $m1->assignRole('mentor');
    $m2 = User::factory()->create(); $m2->assignRole('mentor');
    $app = approvedApplication();

    $this->actingAs($admin)->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $m1->id]);
    $this->actingAs($admin)->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $m2->id]);

    expect($app->activeMentorship?->mentor_id)->toBe($m2->id)
        ->and(Mentorship::where('application_id', $app->id)->where('status', 'active')->count())->toBe(1);
});

it('lets the assigned mentor manage milestones and record consultations', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin');
    $mentor = User::factory()->create(); $mentor->assignRole('mentor');
    $app = approvedApplication();
    $this->actingAs($admin)->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $mentor->id]);

    // add a milestone
    $this->actingAs($mentor)->post("/projects/{$app->id}/milestones", [
        'title' => 'Prototyp', 'due_at' => now()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();

    $milestone = $app->milestones()->first();
    expect($milestone)->not->toBeNull();

    // complete it
    $this->actingAs($mentor)->put("/projects/{$app->id}/milestones/{$milestone->id}", [
        'title' => 'Prototyp', 'is_completed' => true,
    ])->assertSessionHasNoErrors();
    expect($milestone->fresh()->is_completed)->toBeTrue()
        ->and($milestone->fresh()->completed_at)->not->toBeNull();

    // record a consultation
    $this->actingAs($mentor)->post("/projects/{$app->id}/consultations", [
        'summary' => 'Prebrali sme architektúru.',
    ])->assertSessionHasNoErrors();
    expect($app->consultations()->count())->toBe(1);
});

it('forbids a non-assigned mentor from managing the project', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin');
    $assigned = User::factory()->create(); $assigned->assignRole('mentor');
    $stranger = User::factory()->create(); $stranger->assignRole('mentor');
    $app = approvedApplication();
    $this->actingAs($admin)->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $assigned->id]);

    $this->actingAs($stranger)
        ->post("/projects/{$app->id}/milestones", ['title' => 'Hack'])
        ->assertForbidden();
});

it('forbids a mentor from assigning mentors', function () {
    $mentor = User::factory()->create(); $mentor->assignRole('mentor');
    $other = User::factory()->create(); $other->assignRole('mentor');
    $app = approvedApplication();

    $this->actingAs($mentor)
        ->post("/projects/{$app->id}/assign-mentor", ['mentor_id' => $other->id])
        ->assertForbidden();
});

it('lets the owner student view their project', function () {
    $app = approvedApplication();

    $this->actingAs($app->user)->get("/projects/{$app->id}")->assertOk();
});
