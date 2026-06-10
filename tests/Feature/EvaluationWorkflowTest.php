<?php

use App\Enums\ProgramType;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;

function submittedApplication(array $criteria = []): Application
{
    $program = Program::factory()->create(['type' => ProgramType::A->value]);
    $call = Call::factory()->create([
        'program_id'          => $program->id,
        'status'              => 'open',
        'evaluation_criteria' => $criteria,
    ]);

    return User::factory()->create()->applications()->create([
        'public_id'         => (string) Str::ulid(),
        'call_id'           => $call->id,
        'status'            => 'submitted',
        'title'             => 'Test projekt',
        'description'       => 'x',
        'problem_statement' => 'x',
        'proposed_solution' => 'x',
        'submitted_at'      => now(),
    ]);
}

it('forbids a student from accessing the evaluation queue', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $this->actingAs($student)->get('/evaluations')->assertForbidden();
});

it('lets an evaluator score an application with weighted criteria', function () {
    $evaluator = User::factory()->create();
    $evaluator->assignRole('evaluator');

    $app = submittedApplication([
        ['name' => 'Inovatívnosť', 'weight' => 40],
        ['name' => 'Tím', 'weight' => 60],
    ]);

    $this->actingAs($evaluator)->post("/evaluations/{$app->id}/score", [
        'criteria_scores' => ['Inovatívnosť' => 80, 'Tím' => 50],
        'notes'           => 'Dobrý projekt.',
    ])->assertSessionHasNoErrors();

    $eval = $app->evaluations()->first();
    expect((float) $eval->score)->toBe(62.0)          // (80*40 + 50*60) / 100
        ->and($eval->evaluator_id)->toBe($evaluator->id)
        ->and($eval->status->value)->toBe('completed')
        ->and($app->fresh()->status->value)->toBe('under_review'); // moved out of "submitted"
});

it('lets a committee member request a supplement, reopening the application for the student', function () {
    $evaluator = User::factory()->create();
    $evaluator->assignRole('evaluator');
    $app = submittedApplication();

    $this->actingAs($evaluator)->post("/evaluations/{$app->id}/request-supplement", [
        'note' => 'Doplňte rozpočet.',
    ])->assertSessionHasNoErrors();

    $fresh = $app->fresh();
    expect($fresh->status->value)->toBe('supplement_requested')
        ->and($fresh->review_note)->toBe('Doplňte rozpočet.');

    // The applicant can edit again.
    $this->actingAs($app->user)
        ->put("/applications/{$app->id}", [
            'title'             => 'Aktualizovaný',
            'description'       => 'x',
            'problem_statement' => 'x',
            'proposed_solution' => 'x',
        ])->assertSessionHasNoErrors();
});

it('forbids an evaluator without decide permission from deciding', function () {
    $evaluator = User::factory()->create();
    $evaluator->assignRole('evaluator');
    $app = submittedApplication();

    $this->actingAs($evaluator)
        ->post("/evaluations/{$app->id}/decide", ['decision' => 'approved'])
        ->assertForbidden();

    expect($app->fresh()->status->value)->toBe('submitted');
});

it('lets an admin approve an application', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $app = submittedApplication();

    $this->actingAs($admin)->post("/evaluations/{$app->id}/decide", [
        'decision' => 'approved',
        'note'     => 'Gratulujeme.',
    ])->assertSessionHasNoErrors();

    $fresh = $app->fresh();
    expect($fresh->status->value)->toBe('approved')
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->review_note)->toBe('Gratulujeme.');
});
