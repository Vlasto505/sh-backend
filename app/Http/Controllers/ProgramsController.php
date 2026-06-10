<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Program;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramsController extends Controller
{
    public function index(): Response
    {
        $programs = Program::where('is_active', true)
            ->with(['calls' => fn ($q) => $q->where('status', 'open')->orderBy('closes_at')])
            ->orderBy('type')
            ->get();

        $openCalls = Call::where('status', 'open')
            ->with('program:id,title,type')
            ->orderBy('closes_at')
            ->get();

        return Inertia::render('Programs/Index', [
            'programs'  => $programs,
            'openCalls' => $openCalls,
        ]);
    }
}
