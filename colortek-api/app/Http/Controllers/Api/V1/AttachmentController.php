<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachmentStoreRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Services\Attachments\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachmentService) {}

    public function store(AttachmentStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Attachment::class);

        $attachment = $this->attachmentService->store(
            $request->file('file'),
            $request->validated('type'),
            $request->user(),
            $request->validated('caption'),
        );

        return response()->json([
            'data' => AttachmentResource::make($attachment),
        ], 201);
    }

    public function show(Request $request, int $id): StreamedResponse
    {
        $attachment = $this->attachmentService->findOrFail($id);
        $this->authorize('view', $attachment);

        return $this->attachmentService->stream($attachment);
    }
}
