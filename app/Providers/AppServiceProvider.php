<?php

namespace App\Providers;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        RateLimiter::for('game-join', function (Request $request): Limit {
            $code = $request->input('code');

            return Limit::perMinute(5)
                ->by(hash('sha256', ($request->ip() ?? 'unknown').'|'.(is_string($code) ? strtoupper(trim($code)) : 'invalid')))
                ->after(fn (Response $response): bool => $response->getStatusCode() !== 200)
                ->response(fn (Request $request, array $headers) => response()->json(['message' => 'Too many unsuccessful join attempts. Wait a minute and try again.'], 429, $headers));
        });
        Event::listen(Verified::class, function (Verified $event): void {
            if ($event->user instanceof User) {
                SendWelcomeEmail::dispatch($event->user->id);
            }
        });
        // Friends sharing Wi-Fi must not exhaust one another's polling allowance.
        foreach (['game-state' => 120, 'game-action' => 60] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($attempts)
                ->by(hash('sha256', $request->session()->get('chanting.identity', $request->ip() ?? 'unknown'))));
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
