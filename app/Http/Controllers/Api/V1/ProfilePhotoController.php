<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class ProfilePhotoController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', File::image()->max('3mb')],
        ]);

        $user      = $request->user();
        $file      = $validated['file'];
        $disk      = 'public';
        $directory = 'profile_photos/users/' . $user->id;
        $path      = null;

        try {
            DB::beginTransaction();

            $oldPhoto = $user->profilePhoto;
            $path     = $file->store($directory, $disk);

            $newPhoto = $user->profilePhoto()->create([
                'public_id'     => (string) Str::ulid(),
                'collection'    => 'profile_photo',
                'visibility'    => 'public',
                'disk'          => $disk,
                'path'          => $path,
                'stored_name'   => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);

            if ($oldPhoto) {
                Storage::disk($oldPhoto->disk)->delete($oldPhoto->path);
                $oldPhoto->delete();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($path) {
                Storage::disk($disk)->delete($path);
            }

            return $this->error('Profilovú fotku sa nepodarilo uložiť.', 500);
        }

        AuditService::log('user.profile_photo_updated', $user);

        return $this->success([
            'profile_photo'     => $newPhoto,
            'profile_photo_url' => $newPhoto->publicUrl(),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $attachment = $request->user()->profilePhoto;

        if (!$attachment) {
            return $this->error('Profilová fotka neexistuje.', 404);
        }

        DB::transaction(function () use ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });

        AuditService::log('user.profile_photo_deleted', $request->user());

        return $this->success(['message' => 'Profilová fotka bola odstránená.']);
    }
}
