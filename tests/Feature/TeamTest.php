<?php

use App\Enums\ProgramType;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;

function student(): User
{
    $u = User::factory()->create();
    $u->assignRole('student');

    return $u;
}

it('lets a student create a team and become its leader and member', function () {
    $leader = student();

    $this->actingAs($leader)->post('/teams', [
        'name' => 'Alfa', 'program_type' => 'A', 'description' => 'x',
    ])->assertRedirect();

    $team = Team::first();
    expect($team->leader_id)->toBe($leader->id)
        ->and($team->members()->where('users.id', $leader->id)->exists())->toBeTrue();
});

it('lets the leader add an existing user by email', function () {
    $leader = student();
    $team = Team::create(['name' => 'Alfa', 'leader_id' => $leader->id, 'program_type' => 'A']);
    $team->members()->attach($leader->id, ['role_in_team' => 'leader']);

    $mate = student();

    $this->actingAs($leader)->post("/teams/{$team->id}/members", ['email' => $mate->email])
        ->assertSessionHasNoErrors();

    expect($team->members()->where('users.id', $mate->id)->exists())->toBeTrue();
});

it('rejects adding an unknown email', function () {
    $leader = student();
    $team = Team::create(['name' => 'Alfa', 'leader_id' => $leader->id, 'program_type' => 'A']);
    $team->members()->attach($leader->id, ['role_in_team' => 'leader']);

    $this->actingAs($leader)->post("/teams/{$team->id}/members", ['email' => 'nobody@nowhere.io'])
        ->assertSessionHasErrors('email');
});

it('forbids a non-leader from adding members', function () {
    $leader = student();
    $team = Team::create(['name' => 'Alfa', 'leader_id' => $leader->id, 'program_type' => 'A']);
    $team->members()->attach($leader->id, ['role_in_team' => 'leader']);
    $intruder = student();

    $this->actingAs($intruder)->post("/teams/{$team->id}/members", ['email' => 'x@y.z'])
        ->assertForbidden();
});

it('prevents removing the leader', function () {
    $leader = student();
    $team = Team::create(['name' => 'Alfa', 'leader_id' => $leader->id, 'program_type' => 'A']);
    $team->members()->attach($leader->id, ['role_in_team' => 'leader']);

    $this->actingAs($leader)->delete("/teams/{$team->id}/members/{$leader->id}");

    expect($team->members()->where('users.id', $leader->id)->exists())->toBeTrue();
});

it('lets a member leave but not the leader', function () {
    $leader = student();
    $team = Team::create(['name' => 'Alfa', 'leader_id' => $leader->id, 'program_type' => 'A']);
    $team->members()->attach($leader->id, ['role_in_team' => 'leader']);
    $mate = student();
    $team->members()->attach($mate->id, ['role_in_team' => 'member']);

    $this->actingAs($mate)->post("/teams/{$team->id}/leave");
    expect($team->members()->where('users.id', $mate->id)->exists())->toBeFalse();

    $this->actingAs($leader)->post("/teams/{$team->id}/leave");
    expect($team->members()->where('users.id', $leader->id)->exists())->toBeTrue();
});

it('can attach a led team to an application but not someone else\'s team', function () {
    $leader = student();
    $program = Program::factory()->create(['type' => ProgramType::A->value]);
    $call = Call::factory()->create(['program_id' => $program->id, 'status' => 'open']);
    $app = $leader->applications()->create([
        'public_id' => (string) Str::ulid(), 'call_id' => $call->id, 'status' => 'draft', 'title' => 'T',
    ]);

    $myTeam = Team::create(['name' => 'Mine', 'leader_id' => $leader->id, 'program_type' => 'A']);
    $otherTeam = Team::create(['name' => 'Other', 'leader_id' => student()->id, 'program_type' => 'A']);

    // valid: own team
    $this->actingAs($leader)->put("/applications/{$app->id}", [
        'title' => 'T', 'team_id' => $myTeam->id,
    ])->assertSessionHasNoErrors();
    expect($app->fresh()->team_id)->toBe($myTeam->id);

    // invalid: someone else's team
    $this->actingAs($leader)->put("/applications/{$app->id}", [
        'title' => 'T', 'team_id' => $otherTeam->id,
    ])->assertSessionHasErrors('team_id');
});
