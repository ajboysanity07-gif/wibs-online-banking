<?php

namespace App\Listeners;

use App\Models\AppUser;
use App\Models\LoginHistory;
use App\Support\SchemaCapabilities;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordLoginHistory
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof AppUser) {
            return;
        }

        $userId = $event->user->user_id;
        $ipAddress = $this->request->ip();
        $userAgent = $this->request->userAgent();
        $loggedInAt = now();

        // The DB is remote, so write on the queue worker instead of making the
        // login request wait for the insert.
        dispatch(function () use ($userId, $ipAddress, $userAgent, $loggedInAt): void {
            if (! app(SchemaCapabilities::class)->hasTable('login_histories')) {
                return;
            }

            LoginHistory::create([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'logged_in_at' => $loggedInAt,
            ]);
        });
    }
}
