<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // In local/testing: surface N+1 queries and discarded fillable attributes
        // immediately rather than silently failing in production.
        if ($this->app->isLocal() || $this->app->runningUnitTests()) {
            Model::preventLazyLoading();
            Model::preventSilentlyDiscardingAttributes();
        }

        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters (§14.3, US-01.5).
     *
     * Defined once here, referenced by name in routes/web.php so there is
     * no duplicated throttle config anywhere in the codebase.
     *
     * Thresholds:
     *   login          5 attempts/min per email+IP  — brute-force protection
     *   password.reset 3 attempts/min per IP        — reset-link flooding
     *   guest.job      60 req/min per IP            — technician flow (~1/sec steady)
     *   photo.upload   10 uploads/min per IP        — large-payload abuse
     *   qr.lookup      30 req/min per IP            — QR redirect spam
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip())
                ->response(fn () => back()->withErrors([
                    'email' => 'Too many sign-in attempts. Please try again in a minute.',
                ]));
        });

        RateLimiter::for('password.reset', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip())
                ->response(fn () => back()->withErrors([
                    'email' => 'Too many requests. Please try again in a minute.',
                ]));
        });

        RateLimiter::for('guest.job', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('photo.upload', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('qr.lookup', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Invitation send — 10 sends/min per authenticated user (§14.3 flood protection)
        RateLimiter::for('invitation.send', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(10)->by($user ? $user->id : $request->ip());
        });
    }
}
