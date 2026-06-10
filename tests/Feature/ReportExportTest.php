<?php

use App\Enums\ProgramType;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;

function adminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function applicationWithStatus(string $status, string $type = 'program_a'): Application
{
    $program = Program::factory()->create(['type' => $type]);
    $call = Call::factory()->create(['program_id' => $program->id]);

    return User::factory()->create()->applications()->create([
        'public_id' => (string) Str::ulid(),
        'call_id'   => $call->id,
        'status'    => $status,
        'title'     => "App {$status}",
    ]);
}

it('shows report statistics to an admin', function () {
    applicationWithStatus('approved');
    applicationWithStatus('submitted');

    $this->actingAs(adminUser())
        ->get('/admin/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reports')
            ->where('stats.applications_total', 2)
            ->where('stats.approved', 1)
        );
});

it('forbids a student from viewing reports', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $this->actingAs($student)->get('/admin/reports')->assertForbidden();
});

it('exports applications as CSV', function () {
    applicationWithStatus('approved');

    $response = $this->actingAs(adminUser())->get('/admin/reports/export/applications?format=csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('App approved')
        ->and($response->streamedContent())->toContain('Názov projektu'); // header row
});

it('exports applications as XLSX', function () {
    applicationWithStatus('approved');

    $response = $this->actingAs(adminUser())->get('/admin/reports/export/applications?format=xlsx');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
});

it('filters the application export by status', function () {
    applicationWithStatus('approved');
    applicationWithStatus('rejected');

    $content = $this->actingAs(adminUser())
        ->get('/admin/reports/export/applications?format=csv&status=approved')
        ->streamedContent();

    expect($content)->toContain('App approved')
        ->and($content)->not->toContain('App rejected');
});

it('exports users as CSV', function () {
    $admin = adminUser();

    $content = $this->actingAs($admin)
        ->get('/admin/reports/export/users?format=csv')
        ->streamedContent();

    expect($content)->toContain($admin->email)
        ->and($content)->toContain('Meno'); // header
});

it('forbids a student from exporting', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $this->actingAs($student)->get('/admin/reports/export/applications?format=csv')->assertForbidden();
});
