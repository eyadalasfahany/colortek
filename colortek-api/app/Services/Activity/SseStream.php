<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Http\Resources\ActivityEventResource;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SseStream
{
    public function __construct(private ActivityQuery $activityQuery) {}

    public function response(User $user, ?int $lastEventId): StreamedResponse
    {
        return response()->stream(function () use ($user, $lastEventId): void {
            $cursor = $lastEventId ?? 0;
            $iterations = 0;

            while (! connection_aborted()) {
                $events = $this->activityQuery->forUser($user)
                    ->where('id', '>', $cursor)
                    ->orderBy('id')
                    ->limit(50)
                    ->get();

                foreach ($events as $event) {
                    echo 'id: '.$event->id."\n";
                    echo "event: activity\n";
                    echo 'data: '.json_encode(
                        ActivityEventResource::make($event)->resolve(),
                        JSON_THROW_ON_ERROR,
                    )."\n\n";
                    $cursor = $event->id;
                }

                if ($events->isNotEmpty()) {
                    @ob_flush();
                    flush();
                }

                sleep(1);

                if (app()->environment('testing') && ++$iterations >= 1) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
