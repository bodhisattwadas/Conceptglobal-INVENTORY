<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

class SecurityFlowAgent extends Command
{
    protected $signature = 'agent:security-flow {--run-tests : Run php artisan test through the existing test audit}';

    protected $description = 'Run a security and business-flow audit agent for the inventory application.';

    public function handle(): int
    {
        $this->info('Security flow agent started...');

        $rows = collect()
            ->merge($this->runExistingAudits())
            ->merge($this->checkPublicExposure())
            ->merge($this->checkRouteSecurity())
            ->merge($this->checkUploadValidation())
            ->merge($this->checkAuthFlow())
            ->merge($this->checkSessionSecurity())
            ->merge($this->checkInventoryFlow());

        $this->table(['Area', 'Result', 'Details'], $rows->all());

        $failed = $rows->contains(fn (array $row) => $row[1] === 'FAIL');
        $warnings = $rows->filter(fn (array $row) => $row[1] === 'WARN')->count();

        if ($failed) {
            $this->error('Security flow agent found blocking issues.');

            return self::FAILURE;
        }

        $warnings > 0
            ? $this->warn("Security flow agent completed with {$warnings} warning(s) to review.")
            : $this->info('Security flow agent completed without blocking issues.');

        return self::SUCCESS;
    }

    private function runExistingAudits(): array
    {
        $checks = [];

        foreach (['audit:security', 'audit:routes'] as $command) {
            $exitCode = $this->callSilently($command);
            $checks[] = [
                $command,
                $exitCode === self::SUCCESS ? 'OK' : 'FAIL',
                $exitCode === self::SUCCESS ? 'Existing audit passed.' : 'Existing audit failed; run '.$command.' for details.',
            ];
        }

        $testCommand = $this->option('run-tests') ? 'audit:test-failures --run' : 'audit:test-failures';
        $exitCode = $this->callSilently('audit:test-failures', ['--run' => $this->option('run-tests')]);
        $checks[] = [
            $testCommand,
            $exitCode === self::SUCCESS ? 'OK' : 'WARN',
            $exitCode === self::SUCCESS ? 'Known test mismatch audit passed.' : 'Known test mismatches exist; run '.$testCommand.' for details.',
        ];

        return $checks;
    }

    private function checkPublicExposure(): array
    {
        $checks = [];

        $sensitivePublicFiles = collect(['.env', 'composer.lock', 'package-lock.json'])
            ->filter(fn (string $file) => file_exists(public_path($file)))
            ->values();

        $checks[] = $sensitivePublicFiles->isEmpty()
            ? ['Public files', 'OK', 'No obvious sensitive files are present under public/.']
            : ['Public files', 'FAIL', 'Remove from public/: '.$sensitivePublicFiles->join(', ')];

        $mediaRoute = Route::getRoutes()->getByName('media.public');
        $checks[] = $mediaRoute && str_contains($this->routeSource(), "str_contains(\$path, '..')")
            ? ['Media route', 'OK', 'Public media route rejects traversal paths and checks public disk existence.']
            : ['Media route', 'WARN', 'Review media route path traversal and disk existence guards.'];

        return $checks;
    }

    private function checkRouteSecurity(): array
    {
        $checks = [];
        $stateChangingGetRoutes = [];
        $unguardedNamedRoutes = [];
        $roleSensitiveRoutes = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $uri = $route->uri();
            $middleware = $route->gatherMiddleware();

            if (in_array('GET', $route->methods(), true) && preg_match('/(delete|destroy|paid|complete|cancel|restore|update|store|create-user|mark-)/i', $uri.' '.$name)) {
                $stateChangingGetRoutes[] = $name ?: $uri;
            }

            if ($name && ! $this->isPublicRoute($route) && ! $this->hasMiddleware($middleware, ['auth', 'auth:web'])) {
                $unguardedNamedRoutes[] = $name;
            }

            if ($name && preg_match('/(^users\.|settings|coupons|finance|vendor-invoices|companies\.destroy)/', $name)) {
                if (! $this->hasMiddleware($middleware, ['can:', 'role', 'admin'])) {
                    $roleSensitiveRoutes[] = $name;
                }
            }
        }

        $checks[] = empty($stateChangingGetRoutes)
            ? ['GET side effects', 'OK', 'No obvious state-changing named GET routes found.']
            : ['GET side effects', 'WARN', 'Review possible state-changing GET routes: '.implode(', ', array_slice($stateChangingGetRoutes, 0, 8))];

        $checks[] = empty($unguardedNamedRoutes)
            ? ['Auth middleware', 'OK', 'Named application routes are protected or explicitly public.']
            : ['Auth middleware', 'WARN', 'Review unguarded named routes: '.implode(', ', array_slice($unguardedNamedRoutes, 0, 8))];

        $checks[] = empty($roleSensitiveRoutes)
            ? ['Role boundaries', 'OK', 'Role-sensitive routes declare role/can/admin middleware.']
            : ['Role boundaries', 'WARN', 'Role-sensitive routes rely only on auth: '.implode(', ', array_slice($roleSensitiveRoutes, 0, 10))];

        return $checks;
    }

    private function checkUploadValidation(): array
    {
        $uploadFiles = collect(glob(app_path('Livewire/**/*.php')) ?: [])
            ->merge(glob(app_path('Http/Controllers/**/*.php')) ?: [])
            ->filter(function (string $file) {
                $contents = file_get_contents($file);

                return str_contains($contents, 'WithFileUploads')
                    || str_contains($contents, '->file(')
                    || str_contains($contents, 'UploadedFile');
            })
            ->values();

        $missingValidation = $uploadFiles
            ->reject(function (string $file) {
                $contents = file_get_contents($file);

                return preg_match('/(image|file|mimes|mimetypes)/', $contents)
                    && preg_match('/max:\d+/', $contents);
            })
            ->map(fn (string $file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file))
            ->values();

        return [
            $missingValidation->isEmpty()
                ? ['Upload validation', 'OK', 'Upload surfaces include type and size validation.']
                : ['Upload validation', 'WARN', 'Review upload validation in: '.$missingValidation->join(', ')],
        ];
    }

    private function checkAuthFlow(): array
    {
        $loginRequest = $this->read('app/Http/Requests/Auth/LoginRequest.php');
        $registeredUserController = $this->read('app/Http/Controllers/Auth/RegisteredUserController.php');

        return [
            str_contains($loginRequest, 'RateLimiter::tooManyAttempts')
                ? ['Login throttling', 'OK', 'Login attempts are rate limited.']
                : ['Login throttling', 'FAIL', 'LoginRequest does not enforce rate limiting.'],
            str_contains($registeredUserController, 'Hash::make')
                ? ['Password hashing', 'OK', 'Registration hashes passwords before persistence.']
                : ['Password hashing', 'FAIL', 'Registration password hashing was not detected.'],
            str_contains($registeredUserController, "'username'")
                ? ['Registration flow', 'OK', 'Registration validates username for username-based login.']
                : ['Registration flow', 'WARN', 'Registration may not collect username used by login.'],
        ];
    }

    private function checkSessionSecurity(): array
    {
        return [
            config('session.http_only')
                ? ['Session httpOnly', 'OK', 'Session cookie is HTTP only.']
                : ['Session httpOnly', 'FAIL', 'Session cookie is readable by JavaScript.'],
            config('session.same_site')
                ? ['Session SameSite', 'OK', 'Session SameSite is configured as '.config('session.same_site').'.']
                : ['Session SameSite', 'WARN', 'Session SameSite is not configured.'],
            app()->environment('production') && ! config('session.secure')
                ? ['Secure cookies', 'WARN', 'Production should set SESSION_SECURE_COOKIE=true behind HTTPS.']
                : ['Secure cookies', 'OK', 'Secure cookie setting is acceptable for this environment.'],
        ];
    }

    private function checkInventoryFlow(): array
    {
        $requiredRoutes = [
            'dashboard',
            'products.index',
            'purchases.index',
            'purchases.store',
            'purchases.receive',
            'purchases.mark-received',
            'sales.index',
            'sales.store',
            'inventory.index',
            'inventory.show',
            'vendor-invoices.index',
            'vendor-invoices.mark-paid',
            'finance.transactions.index',
        ];

        $missing = collect($requiredRoutes)
            ->reject(fn (string $name) => Route::getRoutes()->getByName($name))
            ->values();

        return [
            $missing->isEmpty()
                ? ['Inventory flow routes', 'OK', 'Core dashboard, purchase, receiving, sales, stock, invoice, and finance routes exist.']
                : ['Inventory flow routes', 'FAIL', 'Missing core routes: '.$missing->join(', ')],
        ];
    }

    private function isPublicRoute(LaravelRoute $route): bool
    {
        $name = $route->getName();
        $uri = $route->uri();

        return $uri === 'up'
            || str_starts_with($uri, '_debugbar')
            || str_starts_with($uri, 'livewire/')
            || str_starts_with($uri, 'storage/')
            || in_array($name, [
                'login',
                'register',
                'default-livewire.update',
                'password.request',
                'password.email',
                'password.reset',
                'password.store',
                'media.public',
            ], true)
            || str_starts_with((string) $name, 'debugbar.')
            || str_starts_with((string) $name, 'livewire.');
    }

    private function hasMiddleware(array $middleware, array $needles): bool
    {
        foreach ($middleware as $entry) {
            foreach ($needles as $needle) {
                if ($entry === rtrim($needle, ':') || str_starts_with($entry, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function routeSource(): string
    {
        return $this->read('routes/web.php');
    }

    private function read(string $path): string
    {
        $fullPath = base_path($path);

        return file_exists($fullPath) ? file_get_contents($fullPath) : '';
    }
}
