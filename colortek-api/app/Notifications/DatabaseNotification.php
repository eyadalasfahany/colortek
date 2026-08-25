<?php
declare(strict_types=1);
namespace App\Notifications;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Notifications\Notification;
abstract class DatabaseNotification extends Notification implements ShouldQueue { use Queueable; public function via($n): array { return ["database"]; } protected function payload(array $d): array { return $d; } }
