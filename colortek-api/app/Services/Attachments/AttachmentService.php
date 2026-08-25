<?php

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Enums\SampleStatus;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Attachment;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Sample;
use App\Models\Task;
use App\Models\User;
use App\Repositories\AttachmentRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentService
{
    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const MAX_BYTES = 10_485_760;

    public function __construct(private AttachmentRepository $repository) {}

    public function store(UploadedFile $file, string $type, User $user, ?string $caption = null): Attachment
    {
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new ModelNotFoundException(__('Unsupported file type.'));
        }

        if (($file->getSize() ?: 0) > self::MAX_BYTES) {
            throw new ModelNotFoundException(__('File is too large.'));
        }

        $path = $file->store('attachments', 'local');

        /** @var Attachment $attachment */
        $attachment = $this->repository->create([
            'type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by_user_id' => $user->id,
            'caption' => $caption,
        ]);

        return $attachment;
    }

    public function attachToTask(Task $task, Attachment $attachment): Attachment
    {
        $attachment->update([
            'attachable_type' => $task->getMorphClass(),
            'attachable_id' => $task->id,
        ]);

        return $attachment->fresh() ?? $attachment;
    }

    public function stream(Attachment $attachment): StreamedResponse
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            throw new ModelNotFoundException(__('Attachment not found'));
        }

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function findOrFail(int $id): Attachment
    {
        /** @var Attachment $attachment */
        $attachment = $this->repository->findOneOrFail($id);

        return $attachment;
    }

    public function isDeletable(Attachment $attachment): bool
    {
        if ($attachment->attachable_id === null) {
            return true;
        }

        $attachable = $attachment->attachable;
        if (! $attachable instanceof Model) {
            return false;
        }

        if ($attachable instanceof Task) {
            return $attachable->status !== TaskStatus::Completed;
        }

        if ($attachable instanceof Payment) {
            return ! in_array($attachable->status, [PaymentStatus::Journaled, PaymentStatus::Accounted], true);
        }

        if ($attachable instanceof Sample) {
            return ! in_array($attachable->status, [SampleStatus::Cancelled, SampleStatus::Superseded], true);
        }

        if ($attachable instanceof Journal) {
            return $attachable->status === JournalStatus::Open;
        }

        return false;
    }

    public function delete(Attachment $attachment): void
    {
        if (! $this->isDeletable($attachment)) {
            throw new TaskNotReadyToComplete(
                __('This attachment cannot be deleted because the owning record is locked.'),
                'attachment.locked',
            );
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
