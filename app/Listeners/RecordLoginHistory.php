<?php

namespace App\Listeners;

use App\Models\AppUser;
use App\Models\LoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RecordLoginHistory
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof AppUser) {
            return;
        }

        if (! Schema::hasTable('login_histories')) {
            return;
        }

        LoginHistory::create([
            'user_id' => $event->user->user_id,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'logged_in_at' => now(),
        ]);
    }
}
