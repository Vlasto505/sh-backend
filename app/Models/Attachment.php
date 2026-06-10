<?php

namespace App\Models;

use App\Enums\DocumentVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'public_id',
        'collection',
        'document_type',
        'visibility',
        'disk',
        'path',
        'original_name',
        'stored_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => DocumentVisibility::class,
            'size'       => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function publicUrl(): ?string
    {
        if ($this->visibility !== DocumentVisibility::Public) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function temporaryUrl(int $seconds = 30): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addSeconds($seconds),
        );
    }
}
