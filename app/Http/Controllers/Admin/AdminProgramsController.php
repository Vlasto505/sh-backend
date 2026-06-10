<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramType;
use App\Http\Controllers\Concerns\LogsAudit;
use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminProgramsController extends Controller
{
    use LogsAudit;

    public function index(Request $request): Response
    {
        $programs = Program::with(['creator:id,name'])
            ->withCount('calls')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Programs', [
            'programs'     => $programs,
            'programTypes' => collect(ProgramType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t === ProgramType::A ? 'Program A – vlastný nápad' : 'Program B – firemné zadanie',
            ]),
            'callStatuses' => $this->callStatusOptions(),
            'can' => [
                'manage' => $request->user()->can('calls.edit'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('calls.create'), 403, 'Nemáte oprávnenie vytvárať programy.');

        $data = $this->validateProgram($request);

        $program = Program::create([
            'slug'        => $this->uniqueSlug($data['title'], 'programs'),
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'is_active'   => $data['is_active'],
            'starts_at'   => $data['starts_at'] ?? null,
            'ends_at'     => $data['ends_at'] ?? null,
            'created_by'  => $request->user()->id,
        ]);

        $this->audit($request, 'program.created', Program::class, $program->id, [
            'title' => $program->title,
            'type'  => $program->type->value,
        ]);

        return back()->with('success', "Program „{$program->title}“ bol vytvorený.");
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        abort_unless($request->user()->can('calls.edit'), 403, 'Nemáte oprávnenie upravovať programy.');

        $data = $this->validateProgram($request);

        $program->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'is_active'   => $data['is_active'],
            'starts_at'   => $data['starts_at'] ?? null,
            'ends_at'     => $data['ends_at'] ?? null,
        ]);

        $this->audit($request, 'program.updated', Program::class, $program->id, [
            'title'     => $program->title,
            'type'      => $program->type->value,
            'is_active' => $program->is_active,
        ]);

        return back()->with('success', "Program „{$program->title}“ bol upravený.");
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        abort_unless($request->user()->can('calls.edit'), 403, 'Nemáte oprávnenie mazať programy.');

        if ($program->calls()->exists()) {
            return back()->with('error', 'Program má priradené výzvy. Najprv odstráňte výzvy programu.');
        }

        $title = $program->title;
        $program->delete();

        $this->audit($request, 'program.deleted', Program::class, $program->id, ['title' => $title]);

        return back()->with('success', "Program „{$title}“ bol zmazaný.");
    }

    private function validateProgram(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type'        => ['required', Rule::enum(ProgramType::class)],
            'is_active'   => ['required', 'boolean'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function uniqueSlug(string $title, string $table): string
    {
        $base = Str::slug($title) ?: 'program';
        $slug = $base;
        $i = 2;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function callStatusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Koncept'],
            ['value' => 'open', 'label' => 'Otvorená'],
            ['value' => 'closed', 'label' => 'Uzavretá'],
            ['value' => 'archived', 'label' => 'Archivovaná'],
        ];
    }
}
