<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'call_id',
        'user_id',
        'team_id',
        'status',
        'title',
        'description',
        'problem_statement',
        'proposed_solution',
        'category',
        'qualification_stack',
        'submitted_at',
        'decided_at',
        'is_archived',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status'       => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'decided_at'   => 'datetime',
            'is_archived'  => 'boolean',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function mentorships(): HasMany
    {
        return $this->hasMany(Mentorship::class);
    }

    public function activeMentorship(): HasOne
    {
        return $this->hasOne(Mentorship::class)->where('status', 'active')->latestOfMany();
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class)->latest('met_at');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('order');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
                    ->where('collection', 'document');
    }
}
