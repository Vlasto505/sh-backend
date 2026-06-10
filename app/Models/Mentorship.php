<?php

namespace App\Models;

use App\Enums\MentorshipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mentorship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'application_id',
        'mentor_id',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status'     => MentorshipStatus::class,
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
