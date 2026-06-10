<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Application;
use App\Models\Attachment;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class AttachmentController extends ApiController
{
    public function index(Application $application): JsonResponse
    {
        $this->authorize('view', $application);

        $attachments = $application->attachments()->latest()->get();

        return $this->success($attachments);
    }

    public function store(Request $request, Application $application): JsonResponse
    {
        $this->authorize('create', [Attachment::class, $application]);

        $validated = $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'])->max('10mb')],
        ]);

        $disk        = 'local';
        $created     = [];
        $storedPaths = [];

        try {
            DB::beginTransaction();

            foreach ($validated['files'] as $file) {
                $directory = 'attachments/applications/' . $application->id . '/' . now()->format('Y/m');
                $path      = $file->store($directory, $disk);
                $storedPaths[] = $path;

                $created[] = $application->attachments()->create([
                    'public_id'     => (string) Str::ulid(),
                    'collection'    => 'document',
                    'visibility'    => 'private',
                    'disk'          => $disk,
                    'path'          => $path,
                    'stored_name'   => basename($path),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            return $this->error('Prílohy sa nepodarilo uložiť.', 500);
        }

        AuditService::log('attachment.uploaded', $application, ['count' => count($created)]);

        return $this->success(['attachments' => $created], 201);
    }

    public function link(Attachment $attachment): JsonResponse
    {
        $this->authorize('view', $attachment);

        $expiresAt = now()->addSeconds(30);

        $url = Storage::disk($attachment->disk)->temporaryUrl($attachment->path, $expiresAt);

        return $this->success([
            'url'        => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        DB::transaction(function () use ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });

        AuditService::log('attachment.deleted', $attachment);

        return $this->success(['message' => 'Príloha bola odstránená.']);
    }
}
