<?php

namespace App\Providers;

use App\Models\LoanRequest;
use App\Notifications\LoanRequestWorkflowStatusNotification;
use App\Policies\LoanRequestPolicy;
use App\Services\Locations\LocationProvider;
use App\Services\Locations\PhAddressLocationProvider;
use App\Support\SchemaCapabilities;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SchemaCapabilities::class);
        $this->app->singleton(LocationProvider::class, function (): LocationProvider {
            $provider = config('locations.provider', 'ph-address');

            return match ($provider) {
                'ph-address' => $this->app->make(PhAddressLocationProvider::class),
                default => $this->app->make(PhAddressLocationProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(LoanRequest::class, LoanRequestPolicy::class);
        $this->configureDefaults();
        $this->registerLoanWorkflowNotificationListeners();

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null
        );
    }

    private function registerLoanWorkflowNotificationListeners(): void
    {
        Event::listen(NotificationSent::class, function (
            NotificationSent $event,
        ): void {
            if (! $event->notification instanceof LoanRequestWorkflowStatusNotification) {
                return;
            }

            $notificationEventId = $event->notification->notificationEventId();

            if ($notificationEventId === null) {
                return;
            }

            DB::table('loan_request_notification_events')
                ->where('id', $notificationEventId)
                ->update([
                    'result' => 'sent',
                    'sent_at' => now(),
                    'last_attempt_at' => now(),
                    'failed_at' => null,
                    'provider_error' => null,
                    'updated_at' => now(),
                ]);
        });

        Event::listen(NotificationFailed::class, function (
            NotificationFailed $event,
        ): void {
            if (! $event->notification instanceof LoanRequestWorkflowStatusNotification) {
                return;
            }

            $notificationEventId = $event->notification->notificationEventId();

            if ($notificationEventId === null) {
                return;
            }

            DB::table('loan_request_notification_events')
                ->where('id', $notificationEventId)
                ->update([
                    'result' => 'failed',
                    'failed_at' => now(),
                    'last_attempt_at' => now(),
                    'provider_error' => $this->sanitizeNotificationError(
                        $event->data,
                    ),
                    'updated_at' => now(),
                ]);
        });
    }

    private function sanitizeNotificationError(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['message']
                ?? $value['error']
                ?? json_encode($value);
        }

        if (is_object($value) && method_exists($value, 'getMessage')) {
            $value = $value->getMessage();
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($value));

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 240);
    }
}
