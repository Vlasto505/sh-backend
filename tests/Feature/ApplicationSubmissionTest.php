<?php

use App\Enums\ProgramADocument;
use App\Enums\ProgramType;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function openProgramACall(): Call
{
    $program = Program::factory()->create(['type' => ProgramType::A->value]);

    return Call::factory()->create([
        'program_id' => $program->id,
        'status'     => 'open',
        'opens_at'   => now()->subWeek(),
        'closes_at'  => now()->addMonth(),
    ]);
}

it('lets a student create a draft application for an open call', function () {
    $student = User::factory()->create();
    $call = openProgramACall();

    $this->actingAs($student)
        ->post('/applications', ['call_id' => $call->id])
        ->assertRedirect();

    $app = Application::first();
    expect($app)->not->toBeNull()
        ->and($app->status->value)->toBe('draft')
        ->and($app->user_id)->toBe($student->id)
        ->and($app->call_id)->toBe($call->id);
});

it('prevents a duplicate draft for the same call', function () {
    $student = User::factory()->create();
    $call = openProgramACall();

    $this->actingAs($student)->post('/applications', ['call_id' => $call->id]);
    $this->actingAs($student)->post('/applications', ['call_id' => $call->id]);

    expect(Application::where('user_id', $student->id)->where('call_id', $call->id)->count())->toBe(1);
});

it('blocks submitting an incomplete Program A application', function () {
    $student = User::factory()->create();
    $call = openProgramACall();

    $app = $student->applications()->create([
        'public_id' => (string) Str::ulid(),
        'call_id'   => $call->id,
        'status'    => 'draft',
        'title'     => 'Test',
    ]);

    $this->actingAs($student)
        ->post("/applications/{$app->id}/submit")
        ->assertRedirect();

    expect($app->fresh()->status->value)->toBe('draft');
});

it('lets a student complete and submit a Program A application', function () {
    Storage::fake('local');

    $student = User::factory()->create();
    $call = openProgramACall();

    // create draft
    $this->actingAs($student)->post('/applications', ['call_id' => $call->id]);
    $app = Application::first();

    // fill in the fields
    $this->actingAs($student)->put("/applications/{$app->id}", [
        'title'               => 'AI asistent pre školy',
        'description'         => 'Popis projektu.',
        'problem_statement'   => 'Definícia problému.',
        'proposed_solution'   => 'Navrhované riešenie.',
        'category'            => 'ai_data',
        'qualification_stack' => 'stack_02',
    ])->assertSessionHasNoErrors();

    // upload all required documents
    foreach (ProgramADocument::values() as $type) {
        $this->actingAs($student)->post("/applications/{$app->id}/documents", [
            'document_type' => $type,
            'file'          => UploadedFile::fake()->create("{$type}.pdf", 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    expect($app->fresh()->attachments()->count())->toBe(6);

    // submit
    $this->actingAs($student)
        ->post("/applications/{$app->id}/submit")
        ->assertRedirect(route('applications.index'));

    $fresh = $app->fresh();
    expect($fresh->status->value)->toBe('submitted')
        ->and($fresh->submitted_at)->not->toBeNull();
});

it('forbids editing another user\'s application', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $call = openProgramACall();

    $app = $owner->applications()->create([
        'public_id' => (string) Str::ulid(),
        'call_id'   => $call->id,
        'status'    => 'draft',
        'title'     => 'Mine',
    ]);

    $this->actingAs($other)
        ->put("/applications/{$app->id}", ['title' => 'Hacked'])
        ->assertForbidden();
});
