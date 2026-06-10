<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAudit;
use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\Call;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    use LogsAudit;

    private const AUDIENCES = [
        'all'                  => 'Všetci aktívni používatelia',
        'role:student'         => 'Študenti',
        'role:mentor'          => 'Mentori',
        'role:evaluator'       => 'Komisia (hodnotitelia)',
        'role:company_contact' => 'Firmy / partneri',
        'call'                 => 'Účastníci konkrétnej výzvy',
    ];

    public function index(Request $request): Response
    {
        $broadcasts = Broadcast::with('sender:id,name')
            ->latest()
            ->paginate(15)
            ->through(fn (Broadcast $b) => [
                'id'               => $b->id,
                'subject'          => $b->subject,
                'body'             => $b->body,
                'audience'         => $b->audience,
                'recipients_count' => $b->recipients_count,
                'sender'           => $b->sender?->name,
                'created_at'       => $b->created_at,
            ]);

        return Inertia::render('Admin/Broadcasts', [
            'broadcasts' => $broadcasts,
            'audiences'  => collect(self::AUDIENCES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'calls'      => Call::orderByDesc('created_at')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(self::AUDIENCES))],
            'call_id'  => ['required_if:audience,call', 'nullable', Rule::exists('calls', 'id')],
            'subject'  => ['required', 'string', 'max:150'],
            'body'     => ['required', 'string', 'max:5000'],
        ]);

        [$recipients, $label] = $this->resolveAudience($data['audience'], $data['call_id'] ?? null);

        if ($recipients->isEmpty()) {
            return back()->with('error', 'Vybraná skupina neobsahuje žiadnych používateľov.');
        }

        Notification::send($recipients, new BroadcastNotification($data['subject'], $data['body']));

        $broadcast = Broadcast::create([
            'sent_by'          => $request->user()->id,
            'subject'          => $data['subject'],
            'body'             => $data['body'],
            'audience'         => $label,
            'recipients_count' => $recipients->count(),
        ]);

        $this->audit($request, 'broadcast.sent', Broadcast::class, $broadcast->id, [
            'audience'   => $label,
            'recipients' => $recipients->count(),
        ]);

        return back()->with('success', "Správa bola odoslaná {$recipients->count()} používateľom.");
    }

    /**
     * @return array{0:\Illuminate\Support\Collection<int,User>,1:string}
     */
    private function resolveAudience(string $audience, ?int $callId): array
    {
        if ($audience === 'all') {
            return [User::where('is_active', true)->get(), self::AUDIENCES['all']];
        }

        if (str_starts_with($audience, 'role:')) {
            $role = substr($audience, 5);

            return [User::role($role)->get(), self::AUDIENCES[$audience]];
        }

        // call
        $call = Call::findOrFail($callId);
        $recipients = User::whereHas('applications', fn ($q) => $q->where('call_id', $call->id))->get();

        return [$recipients, "Účastníci výzvy: {$call->title}"];
    }
}
