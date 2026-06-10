<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\EvaluationStatus;
use App\Http\Controllers\Concerns\LogsAudit;
use App\Http\Controllers\Concerns\NotifiesSafely;
use App\Notifications\ApplicationStatusNotification;
use App\Models\Application;
use App\Models\Evaluation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    use LogsAudit, NotifiesSafely;

    /** Statuses that are still in the committee pipeline. */
    private const PIPELINE = [
        ApplicationStatus::Submitted->value,
        ApplicationStatus::UnderReview->value,
        ApplicationStatus::SupplementRequested->value,
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('applications.view_all'), 403);

        $applications = Application::with([
            'user:id,name,email',
            'call:id,title,program_id',
            'call.program:id,title,type',
        ])
            ->withCount('evaluations')
            ->withAvg('evaluations as avg_score', 'score')
            ->whereIn('status', [...self::PIPELINE, ApplicationStatus::Approved->value, ApplicationStatus::Rejected->value])
            ->orderByRaw("FIELD(status,'submitted','supplement_requested','under_review','approved','rejected')")
            ->orderByDesc('submitted_at')
            ->get();

        $myEvaluations = $request->user()->evaluations()
            ->pluck('status', 'application_id');

        return Inertia::render('Evaluations/Index', [
            'applications'  => $applications->map(fn (Application $a) => [
                'id'            => $a->id,
                'public_id'     => $a->public_id,
                'title'         => $a->title,
                'status'        => $a->status->value,
                'submitted_at'  => $a->submitted_at,
                'applicant'     => $a->user?->name,
                'call'          => $a->call?->title,
                'program_type'  => $a->call?->program?->type->value,
                'evaluations_count' => $a->evaluations_count,
                'avg_score'     => $a->avg_score !== null ? round((float) $a->avg_score, 1) : null,
                'my_status'     => $myEvaluations[$a->id] ?? null,
            ]),
            'statusLabels' => $this->statusLabels(),
            'can'          => [
                'evaluate' => $request->user()->can('applications.evaluate'),
                'decide'   => $request->user()->can('applications.decide'),
            ],
        ]);
    }

    public function show(Request $request, Application $application): Response
    {
        abort_unless($request->user()->can('applications.view_all'), 403);

        $application->load([
            'user:id,name,email',
            'call.program',
            'attachments',
            'evaluations.evaluator:id,name',
        ]);

        $myEval = $application->evaluations->firstWhere('evaluator_id', $request->user()->id);
        $criteria = $application->call->evaluation_criteria ?? [];

        return Inertia::render('Evaluations/Show', [
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
                'submitted_at'        => $application->submitted_at,
                'decided_at'          => $application->decided_at,
                'review_note'         => $application->review_note,
                'applicant'           => ['name' => $application->user?->name, 'email' => $application->user?->email],
                'call'                => [
                    'title'   => $application->call->title,
                    'program' => ['title' => $application->call->program->title, 'type' => $application->call->program->type->value],
                ],
                'documents' => $application->attachments->map(fn ($a) => [
                    'id'            => $a->id,
                    'document_type' => $a->document_type,
                    'original_name' => $a->original_name,
                    'size'          => $a->size,
                ]),
            ],
            'criteria'     => $criteria,
            'myEvaluation' => $myEval ? [
                'score'           => $myEval->score,
                'criteria_scores' => $myEval->criteria_scores ?? [],
                'notes'           => $myEval->notes,
                'status'          => $myEval->status->value,
            ] : null,
            'evaluations'  => $application->evaluations->map(fn (Evaluation $e) => [
                'evaluator' => $e->evaluator?->name,
                'score'     => $e->score,
                'notes'     => $e->notes,
                'status'    => $e->status->value,
            ]),
            'isEditable'   => in_array($application->status->value, self::PIPELINE, true),
            'statusLabels' => $this->statusLabels(),
            'can'          => [
                'evaluate'          => $request->user()->can('applications.evaluate'),
                'requestSupplement' => $request->user()->can('applications.request_supplement'),
                'decide'            => $request->user()->can('applications.decide'),
            ],
        ]);
    }

    public function score(Request $request, Application $application): RedirectResponse
    {
        abort_unless($request->user()->can('applications.evaluate'), 403);
        $this->assertPipeline($application);

        $criteria = $application->call->evaluation_criteria ?? [];

        $data = $request->validate([
            'criteria_scores'   => ['array'],
            'criteria_scores.*' => ['numeric', 'min:0', 'max:100'],
            'overall_score'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($criteria)) {
            $scores = $data['criteria_scores'] ?? [];
            $weighted = 0;
            $totalWeight = 0;
            foreach ($criteria as $c) {
                $w = (int) ($c['weight'] ?? 0);
                $weighted += (float) ($scores[$c['name']] ?? 0) * $w;
                $totalWeight += $w;
            }
            $score = $totalWeight > 0 ? round($weighted / $totalWeight, 2) : null;
            $criteriaScores = $scores;
        } else {
            $score = $data['overall_score'] ?? null;
            $criteriaScores = null;
        }

        $application->evaluations()->updateOrCreate(
            ['evaluator_id' => $request->user()->id],
            [
                'score'           => $score,
                'criteria_scores' => $criteriaScores,
                'notes'           => $data['notes'] ?? null,
                'status'          => EvaluationStatus::Completed,
                'evaluated_at'    => now(),
            ],
        );

        // Move from "submitted" into "under review" once evaluation starts.
        if ($application->status === ApplicationStatus::Submitted) {
            $application->update(['status' => ApplicationStatus::UnderReview]);
        }

        $this->audit($request, 'application.evaluated', Application::class, $application->id, ['score' => $score]);

        return back()->with('success', 'Hodnotenie bolo uložené.');
    }

    public function requestSupplement(Request $request, Application $application): RedirectResponse
    {
        abort_unless($request->user()->can('applications.request_supplement'), 403);
        $this->assertPipeline($application);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $application->update([
            'status'      => ApplicationStatus::SupplementRequested,
            'review_note' => $data['note'],
        ]);

        $this->audit($request, 'application.supplement_requested', Application::class, $application->id);

        $this->notifySafely($application->user, new ApplicationStatusNotification($application, 'supplement_requested'));

        return back()->with('success', 'Žiadosť o doplnenie bola odoslaná uchádzačovi.');
    }

    public function decide(Request $request, Application $application): RedirectResponse
    {
        abort_unless($request->user()->can('applications.decide'), 403);
        $this->assertPipeline($application);

        $data = $request->validate([
            'decision' => ['required', Rule::in([ApplicationStatus::Approved->value, ApplicationStatus::Rejected->value])],
            'note'     => ['nullable', 'string', 'max:5000'],
        ]);

        $application->update([
            'status'      => $data['decision'],
            'decided_at'  => now(),
            'review_note' => $data['note'] ?? $application->review_note,
        ]);

        $this->audit($request, 'application.decided', Application::class, $application->id, [
            'decision' => $data['decision'],
        ]);

        $this->notifySafely($application->user, new ApplicationStatusNotification($application, $data['decision']));

        $label = $data['decision'] === ApplicationStatus::Approved->value ? 'schválená' : 'zamietnutá';

        return back()->with('success', "Prihláška bola {$label}.");
    }

    private function assertPipeline(Application $application): void
    {
        abort_unless(
            in_array($application->status->value, self::PIPELINE, true),
            403,
            'Túto prihlášku už nie je možné hodnotiť.'
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
