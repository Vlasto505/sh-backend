<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function studentUser(): User
{
    $u = User::factory()->create(['account_type' => 'student']);
    $u->assignRole('student');

    return $u;
}

it('lets a student view and save their profile', function () {
    $student = studentUser();

    $this->actingAs($student)->get('/student-profile')->assertOk();

    $this->actingAs($student)->put('/student-profile', [
        'study_program'     => 'Aplikovaná informatika',
        'study_year'        => 2,
        'university'        => 'UKF Nitra',
        'bio'               => 'Študent so záujmom o AI.',
        'skills'            => ['PHP', 'Vue', 'AI'],
        'academic_eligible' => true,
    ])->assertSessionHasNoErrors();

    $profile = $student->studentProfile()->first();
    expect($profile->study_program)->toBe('Aplikovaná informatika')
        ->and($profile->study_year)->toBe(2)
        ->and($profile->skills)->toBe(['PHP', 'Vue', 'AI'])
        ->and($profile->academic_eligible)->toBeTrue();
});

it('lets a student upload and remove a CV', function () {
    Storage::fake('local');
    $student = studentUser();

    $this->actingAs($student)->post('/student-profile/cv', [
        'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $profile = $student->studentProfile()->first();
    expect($profile->cv_path)->not->toBeNull();
    Storage::disk('local')->assertExists($profile->cv_path);

    $this->actingAs($student)->delete('/student-profile/cv')->assertSessionHasNoErrors();
    expect($profile->fresh()->cv_path)->toBeNull();
});

it('forbids non-students from accessing the student profile', function () {
    $mentor = User::factory()->create(['account_type' => 'mentor']);
    $mentor->assignRole('mentor');

    $this->actingAs($mentor)->get('/student-profile')->assertForbidden();
});

it('requires the academic eligibility field', function () {
    $student = studentUser();

    $this->actingAs($student)->put('/student-profile', [
        'study_program' => 'X',
        // no academic_eligible
    ])->assertSessionHasErrors('academic_eligible');
});
