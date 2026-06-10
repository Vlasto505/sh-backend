<?php

namespace App\Models;

use App\Enums\CallStatus;
use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id',
        'slug',
        'title',
        'description',
        'status',
        'opens_at',
        'closes_at',
        'min_team_size',
        'max_team_size',
        'evaluation_criteria',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'              => CallStatus::class,
            'opens_at'            => 'datetime',
            'closes_at'           => 'datetime',
            'evaluation_criteria' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CallStatus::Open
            && ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }
}
