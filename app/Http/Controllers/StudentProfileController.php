<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorizeStudent($request);

        $profile = $request->user()->studentProfile;

        return Inertia::render('StudentProfile/Edit', [
            'profile' => [
                'study_program'     => $profile?->study_program,
                'study_year'        => $profile?->study_year,
                'university'        => $profile?->university ?? 'UKF Nitra',
                'bio'               => $profile?->bio,
                'skills'            => $profile?->skills ?? [],
                'academic_eligible' => (bool) ($profile?->academic_eligible ?? false),
                'has_cv'            => (bool) $profile?->cv_path,
                'cv_name'           => $profile?->cv_path ? basename($profile->cv_path) : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeStudent($request);

        $data = $request->validate([
            'study_program'     => ['nullable', 'string', 'max:255'],
            'study_year'        => ['nullable', 'integer', 'min:1', 'max:10'],
            'university'        => ['nullable', 'string', 'max:255'],
            'bio'               => ['nullable', 'string', 'max:2000'],
            'skills'            => ['array', 'max:30'],
            'skills.*'          => ['string', 'max:60'],
            'academic_eligible' => ['required', 'boolean'],
        ]);

        $request->user()->studentProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return back()->with('success', 'Profil bol uložený.');
    }

    public function uploadCv(Request $request): RedirectResponse
    {
        $this->authorizeStudent($request);

        $request->validate([
            'cv' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
        ]);

        $profile = $request->user()->studentProfile()->firstOrCreate(['user_id' => $request->user()->id]);

        if ($profile->cv_path) {
            Storage::disk('local')->delete($profile->cv_path);
        }

        $path = $request->file('cv')->store("student-cv/{$request->user()->id}", 'local');
        $profile->update(['cv_path' => $path]);

        return back()->with('success', 'Životopis bol nahraný.');
    }

    public function downloadCv(Request $request): StreamedResponse
    {
        $this->authorizeStudent($request);

        $profile = $request->user()->studentProfile;
        abort_unless($profile && $profile->cv_path, 404);

        return Storage::disk('local')->download($profile->cv_path, 'zivotopis-'.$request->user()->id.'.'.pathinfo($profile->cv_path, PATHINFO_EXTENSION));
    }

    public function deleteCv(Request $request): RedirectResponse
    {
        $this->authorizeStudent($request);

        $profile = $request->user()->studentProfile;
        if ($profile?->cv_path) {
            Storage::disk('local')->delete($profile->cv_path);
            $profile->update(['cv_path' => null]);
        }

        return back()->with('success', 'Životopis bol odstránený.');
    }

    private function authorizeStudent(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->account_type === AccountType::Student || $user->hasRole('student'),
            403,
            'Profil študenta je dostupný len pre študentské účty.'
        );
    }
}
