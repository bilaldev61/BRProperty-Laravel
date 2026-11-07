<?php

use App\Models\ChatConversation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Anonymous assistant chats are deleted once they pass the retention window
// (config/ai.php). Needs the server's scheduler cron: `php artisan schedule:run`
// every minute.
Schedule::command('model:prune', ['--model' => [ChatConversation::class]])->daily();
