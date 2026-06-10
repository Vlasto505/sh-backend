<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVisibility;
use App\Enums\ProgramACategory;
use App\Enums\ProgramADocument;
use App\Enums\ProgramAStack;
use App\Enums\ProgramType;
use App\Http\Controllers\Concerns\LogsAudit;
use App\Http\Controllers\Concerns\NotifiesSafely;
use App\Notifications\ApplicationStatusNotification;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\Call;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    use LogsAudit, NotifiesSafely;

    private const EDITABLE = [
        ApplicationStatus::Draft->value,
        ApplicationStatus::SupplementRequested->value,
    ];

    public function index(Request $request): Response
    {
        $applications = $request->user()->applications()
            ->with(['call:id,title,program_id', 'call.program:id,title,type'])
            ->orderByDesc('created_at')
            ->get();

        $openCalls = Call::where('status', 'open')
            ->with('program:id,title,type')
            ->orderBy('closes_at')
            ->get(['id', 'title', 'program_id', 'closes_at', 'min_team_size', 'max_team_size']);

        return Inertia::render('Applications/Index', [
            'applications' => $applications,
            'openCalls'    => $openCalls,
        ]);
    }

    /**
     * Start a new draft application for an open call.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'call_id' => ['required', Rule::exists('calls', 'id')],
        ]);

        $call = Call::with('program')->findOrFail($data['call_id']);

        if (! $call->isOpen()) {
            return back()->with('error', 'Táto výzva nie je otvorená na podávanie prihlášok.');
        }

        // One active application per user per call.
        $existing = $request->user()->applications()
            ->where('call_id', $call->id)
            ->where('status', '!=', ApplicationStatus::Withdrawn->value)
            ->first();

        if ($existing) {
            return redirect()->route('applications.edit', $existing)
                ->with('error', 'Pre túto výzvu už máte rozpracovanú prihlášku.');
        }

        $application = $request->user()->applications()->create([
            'public_id' => (string) Str::ulid(),
            'call_id'   => $call->id,
            'status'    => ApplicationStatus::Draft,
            'title'     => $call->title,
        ]);

        $this->audit($request, 'application.created', Application::class, $application->id, [
            'call_id' => $call->id,
        ]);

        return redirect()->route('applications.edit', $application)
            ->with('success', 'Prihláška bola vytvorená ako koncept. Doplňte údaje a prílohy.');
    }

    public function edit(Request $request, Application $application): Response
    {
        $this->authorizeOwner($request, $application);

        $application->load(['call.program', 'attachments']);
        $isProgramA = $application->call->program->type === ProgramType::A;

        // Teams the applicant leads that match this program (A/B) – may be attached to the application.
        $teamType = $isProgramA ? 'A' : 'B';
        $myTeams = $request->user()->teamsAsLeader()
            ->where('program_type', $teamType)
            ->withCount('members')
            ->get(['id', 'name'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'members_count' => $t->members_count]);

        return Inertia::render('Applications/Edit', [
            'application' => [
                'id'                  => $application->id,
                'public_id'           => $application->public_id,
                'status'              => $application->status->value,
                'title'               => $application->title,
                'description'         => $application->description,
                'problem_statement'   => $application->problem_statement,
                'proposed_solution'   => $application->proposed_solution,
                'category'            => $application->category,
                'qualification_stack' => $application->qualification_stack,
                'team_id'             => $application->team_id,
                'submitted_at'        => $application->submitted_at,
                'review_note'         => $application->review_note,
                'is_editable'         => in_array($application->status->value, self::EDITABLE, true),
                'call'                => [
                    'id'            => $application->call->id,
                    'title'         => $application->call->title,
                    'min_team_size' => $application->call->min_team_size,
                    'max_team_size' => $application->call->max_team_size,
                    'closes_at'     => $application->call->closes_at,
                    'program'       => [
                        'title' => $application->call->program->title,
                        'type'  => $application->call->program->type->value,
                    ],
                ],
                'documents' => $application->attachments->map(fn (Attachment $a) => [
                    'id'            => $a->id,
                    'document_type' => $a->document_type,
                    'original_name' => $a->original_name,
                    'size'          => $a->size,
                ]),
            ],
            'isProgramA'        => $isProgramA,
            'myTeams'           => $myTeams,
            'categories'        => ProgramACategory::options(),
            'stacks'            => ProgramAStack::options(),
            'requiredDocuments' => ProgramADocument::options(),
            'statusLabels'      => $this->statusLabels(),
        ]);
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        $this->authorizeEditable($application);

        $isProgramA = $application->call->program->type === ProgramType::A;

        $data = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'problem_statement'   => ['nullable', 'string', 'max:5000'],
            'proposed_solution'   => ['nullable', 'string', 'max:5000'],
            'category'            => [$isProgramA ? 'nullable' : 'prohibited', Rule::enum(ProgramACategory::class)],
            'qualification_stack' => [$isProgramA ? 'nullable' : 'prohibited', Rule::enum(ProgramAStack::class)],
            'team_id'             => [
                'nullable',
                Rule::exists('teams', 'id')->where('leader_id', $request->user()->id),
            ],
        ]);

        $application->update($data);

        return back()->with('success', 'Zmeny boli uložené.');
    }

    public function uploadDocument(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        $this->authorizeEditable($application);

        $isProgramA = $application->call->program->type === ProgramType::A;

        $request->validate([
            'document_type' => [
                'required', 'string',
                $isProgramA ? Rule::in(ProgramADocument::values()) : 'string',
            ],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx'],
        ]);

        $type = $request->input('document_type');
        $file = $request->file('file');

        // Replace any existing file for this document slot.
        $application->attachments()
            ->where('document_type', $type)
            ->get()
            ->each(fn (Attachment $a) => $this->deleteAttachment($a));

        $path = $file->store("applications/{$application->id}", 'local');

        $application->attachments()->create([
            'public_id'     => (string) Str::ulid(),
            'collection'    => 'document',
            'document_type' => $type,
            'visibility'    => DocumentVisibility::Private,
            'disk'          => 'local',
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => basename($path),
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return back()->with('success', 'Dokument bol nahraný.');
    }

    public function deleteDocument(Request $request, Application $application, Attachment $attachment): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        $this->authorizeEditable($application);
        abort_unless($attachment->attachable_id === $application->id && $attachment->attachable_type === Application::class, 404);

        $this->deleteAttachment($attachment);

        return back()->with('success', 'Dokument bol odstránený.');
    }

    public function submit(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        $this->authorizeEditable($application);

        $application->load(['call.program', 'attachments']);
        $isProgramA = $application->call->program->type === ProgramType::A;

        $errors = $this->validateCompleteness($application, $isProgramA);
        if ($errors) {
            return back()->withErrors(['submit' => $errors])->with('error', 'Prihláška nie je kompletná.');
        }

        $application->update([
            'status'       => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->audit($request, 'application.submitted', Application::class, $application->id, [
            'call_id' => $application->call_id,
        ]);

        $this->notifySafely($application->user, new ApplicationStatusNotification($application, 'submitted'));

        return redirect()->route('applications.index')
            ->with('success', 'Prihláška bola úspešne odoslaná.');
    }

    public function withdraw(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        abort_if(in_array($application->status, [ApplicationStatus::Approved, ApplicationStatus::Rejected], true), 403);

        $application->update(['status' => ApplicationStatus::Withdrawn]);
        $this->audit($request, 'application.withdrawn', Application::class, $application->id);

        return redirect()->route('applications.index')->with('success', 'Prihláška bola stiahnutá.');
    }

    public function download(Request $request, Application $application, Attachment $attachment): StreamedResponse
    {
        // Owner or staff with view-all permission may download.
        $user = $request->user();
        $canView = $application->user_id === $user->id || $user->can('applications.view_all');
        abort_unless($canView, 403);
        abort_unless($attachment->attachable_id === $application->id && $attachment->attachable_type === Application::class, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    // ---------------------------------------------------------------------

    /** @return list<string> */
    private function validateCompleteness(Application $application, bool $isProgramA): array
    {
        $errors = [];

        foreach (['title' => 'Názov projektu', 'description' => 'Popis', 'problem_statement' => 'Definícia problému', 'proposed_solution' => 'Navrhované riešenie'] as $field => $label) {
            if (blank($application->{$field})) {
                $errors[] = "Chýba povinné pole: {$label}.";
            }
        }

        if ($isProgramA) {
            if (blank($application->category)) {
                $errors[] = 'Vyberte tematickú kategóriu (Program A).';
            }
            if (blank($application->qualification_stack)) {
                $errors[] = 'Vyberte kvalifikačný stack (Program A).';
            }

            $uploaded = $application->attachments->pluck('document_type')->filter()->all();
            foreach (ProgramADocument::cases() as $doc) {
                if (! in_array($doc->value, $uploaded, true)) {
                    $errors[] = "Chýba povinný dokument: {$doc->label()}.";
                }
            }
        }

        return $errors;
    }

    private function deleteAttachment(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    private function authorizeOwner(Request $request, Application $application): void
    {
        abort_unless($application->user_id === $request->user()->id, 403, 'Toto nie je vaša prihláška.');
    }

    private function authorizeEditable(Application $application): void
    {
        abort_unless(
            in_array($application->status->value, self::EDITABLE, true),
            403,
            'Túto prihlášku už nie je možné upravovať.'
        );
    }

    private function statusLabels(): array
    {
        return [
            'draft'                => 'Koncept',
            'submitted'            => 'Podané',
            'under_review'         => 'V hodnotení',
            'supplement_requested' => 'Vyžiadané doplnenie',
            'approved'             => 'Schválené',
            'rejected'             => 'Zamietnuté',
            'withdrawn'            => 'Stiahnuté',
        ];
    }
}
