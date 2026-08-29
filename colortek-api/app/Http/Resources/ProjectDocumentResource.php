<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project document. Unlike the shared AttachmentResource this carries where
 * the file came from, because the project view lists files gathered from tasks,
 * samples, payments and site visits together.
 *
 * @mixin Attachment
 */
final class ProjectDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'caption' => $this->caption,
            'source_type' => class_basename($this->attachable_type),
            'source_id' => $this->attachable_id,
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
