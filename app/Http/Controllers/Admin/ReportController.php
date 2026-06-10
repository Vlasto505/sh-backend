<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\ProgramType;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const STATUS_LABELS = [
        'draft'                => 'Koncept',
        'submitted'            => 'Podané',
        'under_review'         => 'V hodnotení',
        'supplement_requested' => 'Vyžiadané doplnenie',
        'approved'             => 'Schválené',
        'rejected'             => 'Zamietnuté',
        'withdrawn'            => 'Stiahnuté',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $byStatus = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byProgram = Application::query()
            ->join('calls', 'applications.call_id', '=', 'calls.id')
            ->join('programs', 'calls.program_id', '=', 'programs.id')
            ->selectRaw('programs.type as type, COUNT(*) as total')
            ->groupBy('programs.type')
            ->pluck('total', 'type');

        return Inertia::render('Admin/Reports', [
            'stats' => [
                'applications_total' => Application::count(),
                'approved'           => (int) ($byStatus['approved'] ?? 0),
                'pending'            => (int) ($byStatus['submitted'] ?? 0) + (int) ($byStatus['under_review'] ?? 0),
                'open_calls'         => Call::where('status', 'open')->count(),
                'active_programs'    => Program::where('is_active', true)->count(),
                'users_total'        => User::count(),
                'mentors'            => User::role('mentor')->count(),
                'program_a'          => (int) ($byProgram['program_a'] ?? 0),
                'program_b'          => (int) ($byProgram['program_b'] ?? 0),
                'by_status'          => collect(self::STATUS_LABELS)->map(fn ($label, $key) => [
                    'label' => $label,
                    'count' => (int) ($byStatus[$key] ?? 0),
                ])->values(),
            ],
            'filters' => [
                'statuses'     => collect(self::STATUS_LABELS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'programTypes' => [
                    ['value' => 'program_a', 'label' => 'Program A'],
                    ['value' => 'program_b', 'label' => 'Program B'],
                ],
            ],
        ]);
    }

    public function applications(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $data = $request->validate([
            'format'       => ['nullable', Rule::in(['csv', 'xlsx'])],
            'status'       => ['nullable', Rule::enum(ApplicationStatus::class)],
            'program_type' => ['nullable', Rule::enum(ProgramType::class)],
        ]);

        $applications = Application::query()
            ->with(['user:id,name,email', 'call.program'])
            ->withAvg('evaluations as avg_score', 'score')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['program_type'] ?? null, fn ($q, $t) => $q->whereHas('call.program', fn ($q) => $q->where('type', $t)))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['ID', 'Názov projektu', 'Uchádzač', 'Email', 'Program', 'Výzva', 'Stav', 'Kategória', 'Stack', 'Priemerné skóre', 'Podané', 'Rozhodnuté'];

        $rows = $applications->map(fn (Application $a) => [
            $a->public_id,
            $a->title,
            $a->user?->name,
            $a->user?->email,
            $a->call?->program?->type === ProgramType::A ? 'Program A' : 'Program B',
            $a->call?->title,
            self::STATUS_LABELS[$a->status->value] ?? $a->status->value,
            $a->category,
            $a->qualification_stack,
            $a->avg_score !== null ? round((float) $a->avg_score, 1) : '',
            $a->submitted_at?->format('Y-m-d H:i'),
            $a->decided_at?->format('Y-m-d H:i'),
        ])->all();

        return $this->export($data['format'] ?? 'csv', 'prihlasky_'.now()->format('Y-m-d'), $headers, $rows);
    }

    public function users(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $data = $request->validate([
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);

        $users = User::with('roles:id,name')->orderByDesc('created_at')->get();

        $headers = ['ID', 'Meno', 'Email', 'Typ účtu', 'Roly', 'Aktívny', 'Registrácia'];

        $rows = $users->map(fn (User $u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->account_type->value,
            $u->roles->pluck('name')->join(', '),
            $u->is_active ? 'áno' : 'nie',
            $u->created_at?->format('Y-m-d'),
        ])->all();

        return $this->export($data['format'] ?? 'csv', 'pouzivatelia_'.now()->format('Y-m-d'), $headers, $rows);
    }

    // ---------------------------------------------------------------------

    private function export(string $format, string $filename, array $headers, array $rows): StreamedResponse
    {
        return $format === 'xlsx'
            ? $this->xlsx($filename, $headers, $rows)
            : $this->csv($filename, $headers, $rows);
    }

    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(string $filename, array $headers, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
