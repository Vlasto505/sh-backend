<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GdprController extends Controller
{
    /**
     * Export all personal data held about the authenticated user (GDPR – right of access).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'account_type'      => $user->account_type->value,
                'is_active'         => $user->is_active,
                'gdpr_consented_at' => $user->gdpr_consented_at?->toIso8601String(),
                'registered_at'     => $user->created_at?->toIso8601String(),
            ],
            'roles' => $user->getRoleNames(),
            'applications' => $user->applications()->with('call:id,title')->get()->map(fn ($a) => [
                'public_id'           => $a->public_id,
                'title'               => $a->title,
                'status'              => $a->status->value,
                'call'                => $a->call?->title,
                'category'            => $a->category,
                'qualification_stack' => $a->qualification_stack,
                'description'         => $a->description,
                'problem_statement'   => $a->problem_statement,
                'proposed_solution'   => $a->proposed_solution,
                'submitted_at'        => $a->submitted_at?->toIso8601String(),
            ]),
            'teams_led'    => $user->teamsAsLeader()->get(['id', 'name']),
            'teams_member' => $user->teams()->get(['teams.id', 'teams.name']),
            'organizations' => $user->organizations()->get(['organizations.id', 'name', 'ico']),
            'consultations_authored' => Consultation::where('author_id', $user->id)->get(['summary', 'met_at']),
            'notifications' => $user->notifications()->get()->map(fn ($n) => [
                'message'    => $n->data['message'] ?? '',
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, 'moje-osobne-udaje-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
