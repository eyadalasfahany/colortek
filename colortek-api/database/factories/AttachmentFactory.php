<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        Storage::fake('local');
        $path = 'attachments/'.fake()->uuid().'.pdf';

        Storage::disk('local')->put($path, 'fake file content');

        return [
            'type' => 'payment_proof',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by_user_id' => User::factory(),
        ];
    }

    public function paymentProof(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'payment_proof',
        ]);
    }

    public function fromUploadedFile(UploadedFile $file, User $user): static
    {
        return $this->state(function () use ($file, $user): array {
            $path = $file->store('attachments', 'local');

            return [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'uploaded_by_user_id' => $user->id,
            ];
        });
    }
}
