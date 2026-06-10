<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CallStatus;
use App\Http\Controllers\Concerns\LogsAudit;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCallsController extends Controller
{
    use LogsAudit;

    public function index(Request $request): Response
    {
        $calls = Call::with(['program:id,title,type', 'creator:id,name'])
            ->withCount('applications')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $programs = Program::where('is_active', true)->get(['id', 'title', 'type']);

        return Inertia::render('Admin/Calls', [
            'calls'        => $calls,
            'programs'     => $programs,
            'callStatuses' => collect(CallStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $this->statusLabel($s),
            ]),
            'can' => [
                'manage' => $request->user()->can('calls.edit'),
                'close'  => $request->user()->can('calls.close'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('calls.create'), 403, 'Nemáte oprávnenie vytvárať výzvy.');

        $data = $this->validateCall($request);

        $call = Call::create([
            'program_id'    => $data['program_id'],
            'slug'          => $this->uniqueSlug($data['title']),
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'status'        => $data['status'],
            'opens_at'      => $data['opens_at'] ?? null,
            'closes_at'     => $data['closes_at'] ?? null,
            'min_team_size' => $data['min_team_size'],
            'max_team_size' => $data['max_team_size'],
            'evaluation_criteria' => $data['evaluation_criteria'] ?? [],
            'created_by'    => $request->user()->id,
        ]);

        $this->audit($request, 'call.created', Call::class, $call->id, [
            'title'      => $call->title,
            'program_id' => $call->program_id,
            'status'     => $call->status->value,
        ]);

        return back()->with('success', "Výzva „{$call->title}“ bola vytvorená.");
    }

    public function update(Request $request, Call $call): RedirectResponse
    {
        abort_unless($request->user()->can('calls.edit'), 403, 'Nemáte oprávnenie upravovať výzvy.');

        $data = $this->validateCall($request);

        $call->update([
            'program_id'    => $data['program_id'],
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'status'        => $data['status'],
            'opens_at'      => $data['opens_at'] ?? null,
            'closes_at'     => $data['closes_at'] ?? null,
            'min_team_size' => $data['min_team_size'],
            'max_team_size' => $data['max_team_size'],
            'evaluation_criteria' => $data['evaluation_criteria'] ?? [],
        ]);

        $this->audit($request, 'call.updated', Call::class, $call->id, [
            'title'  => $call->title,
            'status' => $call->status->value,
        ]);

        return back()->with('success', "Výzva „{$call->title}“ bola upravená.");
    }

    /**
     * Quick status change – "otvárať a uzatvárať kolá hodnotenia" (spec 7.2).
     */
    public function updateStatus(Request $request, Call $call): RedirectResponse
    {
        abort_unless($request->user()->can('calls.close'), 403, 'Nemáte oprávnenie meniť stav výzvy.');

        $data = $request->validate([
            'status' => ['required', Rule::enum(CallStatus::class)],
        ]);

        $from = $call->status->value;
        $call->update(['status' => $data['status']]);

        $this->audit($request, 'call.status_changed', Call::class, $call->id, [
            'from' => $from,
            'to'   => $call->status->value,
        ]);

        return back()->with('success', "Stav výzvy „{$call->title}“ bol zmenený.");
    }

    public function destroy(Request $request, Call $call): RedirectResponse
    {
        abort_unless($request->user()->can('calls.edit'), 403, 'Nemáte oprávnenie mazať výzvy.');

        if ($call->applications()->exists()) {
            return back()->with('error', 'Výzva má podané prihlášky a nedá sa zmazať. Namiesto toho ju archivujte.');
        }

        $title = $call->title;
        $call->delete();

        $this->audit($request, 'call.deleted', Call::class, $call->id, ['title' => $title]);

        return back()->with('success', "Výzva „{$title}“ bola zmazaná.");
    }

    private function validateCall(Request $request): array
    {
        return $request->validate([
            'program_id'    => ['required', Rule::exists('programs', 'id')],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'status'        => ['required', Rule::enum(CallStatus::class)],
            'opens_at'      => ['nullable', 'date'],
            'closes_at'     => ['nullable', 'date', 'after_or_equal:opens_at'],
            'min_team_size' => ['required', 'integer', 'min:1', 'max:255'],
            'max_team_size' => ['required', 'integer', 'min:1', 'max:255', 'gte:min_team_size'],
            'evaluation_criteria'          => ['array'],
            'evaluation_criteria.*.name'   => ['required', 'string', 'max:120'],
            'evaluation_criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'vyzva';
        $slug = $base;
        $i = 2;
        while (DB::table('calls')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function statusLabel(CallStatus $status): string
    {
        return match ($status) {
            CallStatus::Draft    => 'Koncept',
            CallStatus::Open     => 'Otvorená',
            CallStatus::Closed   => 'Uzavretá',
            CallStatus::Archived => 'Archivovaná',
        };
    }
}
