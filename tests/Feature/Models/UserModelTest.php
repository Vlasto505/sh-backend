<?php

use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;

test('user has a student profile relationship', function () {
    $user = User::factory()->create(['account_type' => 'student']);
    StudentProfile::create(['user_id' => $user->id, 'study_program' => 'Informatika']);

    expect($user->fresh()->studentProfile)->not->toBeNull();
    expect($user->studentProfile->study_program)->toBe('Informatika');
});

test('user can belong to many organizations', function () {
    $user = User::factory()->create(['account_type' => 'company']);
    $org  = Organization::create(['name' => 'Acme s.r.o.']);

    $user->organizations()->attach($org->id, ['role_in_org' => 'contact', 'is_primary' => true]);

    expect($user->organizations)->toHaveCount(1);
    expect($user->organizations->first()->name)->toBe('Acme s.r.o.');
    expect($user->organizations->first()->pivot->is_primary)->toBe(1);
});

test('user can lead a team', function () {
    $user = User::factory()->create(['account_type' => 'student']);
    $team = Team::create(['name' => 'Alpha', 'leader_id' => $user->id, 'program_type' => 'A']);

    expect($team->leader->id)->toBe($user->id);
    expect($user->teamsAsLeader)->toHaveCount(1);
});

test('user can be a member of teams', function () {
    $leader = User::factory()->create();
    $member = User::factory()->create();
    $team   = Team::create(['name' => 'Beta', 'leader_id' => $leader->id, 'program_type' => 'B']);

    $team->members()->attach($member->id, ['role_in_team' => 'member']);

    expect($member->teams)->toHaveCount(1);
    expect($member->teams->first()->name)->toBe('Beta');
});
