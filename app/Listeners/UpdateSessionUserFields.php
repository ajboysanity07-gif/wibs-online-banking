<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class UpdateSessionUserFields
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
public function handle(Login $event): void
{
    $sessionId = session()->getId();
    DB::table('sessions')
        ->where('id', $sessionId)
        ->update([
            'user_id' => $event->user->getAuthIdentifier(), // acctno or admin_id
            'user_type' => $event->guard, // e.g., 'web' or 'admin'
        ]);
}
}
