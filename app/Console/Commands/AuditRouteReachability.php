<?php

namespace App\Console\Commands;

use App\Enums\PurchaseStatus;
use App\Models\FinanceTransaction;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AuditRouteReachability extends Command
{
    protected $signature = 'audit:routes';

    protected $description = 'Check registered GET routes for obvious non-reachable pages.';

    public function handle(): int
    {
        $this->info('Checking GET route reachability...');

        $user = User::query()->first();
        if (! $user) {
            $this->error('No user exists. Authenticated route checks require at least one user.');

            return self::FAILURE;
        }

        Auth::login($user);
        $kernel = app(Kernel::class);

        $rows = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || $this->shouldSkip($route)) {
                continue;
            }

            $url = $this->makeUrl($route);
            if (! $url) {
                $rows[] = [$route->getName() ?? '-', $route->uri(), 'SKIPPED', 'Missing sample model/data'];

                continue;
            }

            try {
                $request = Request::create($url, 'GET');
                $response = $kernel->handle($request);
                $status = $response->getStatusCode();
                $kernel->terminate($request, $response);
                $ok = in_array($status, [200, 302], true);
                $rows[] = [$route->getName() ?? '-', $url, $ok ? 'OK' : 'FAIL', (string) $status];
            } catch (\Throwable $e) {
                $rows[] = [$route->getName() ?? '-', $url, 'ERROR', $e->getMessage()];
            }
        }

        $failures = collect($rows)->filter(fn ($row) => in_array($row[2], ['FAIL', 'ERROR'], true));

        $this->table(['Name', 'URL', 'Result', 'Status/Reason'], $rows);

        if ($failures->isNotEmpty()) {
            $this->error($failures->count().' route(s) failed reachability checks.');

            return self::FAILURE;
        }

        $this->info('No non-reachable GET routes found in this audit.');

        return self::SUCCESS;
    }

    private function shouldSkip(LaravelRoute $route): bool
    {
        $uri = $route->uri();

        return str_starts_with($uri, '_debugbar')
            || str_starts_with($uri, 'livewire/')
            || str_starts_with($uri, 'storage/')
            || $uri === 'up'
            || str_contains($uri, 'verify-email/{id}/{hash}')
            || str_contains($uri, 'reset-password/{token}')
            || str_contains($uri, 'finance/transactions/print/{printId}');
    }

    private function makeUrl(LaravelRoute $route): ?string
    {
        $parameters = [];

        foreach ($route->parameterNames() as $parameter) {
            $parameters[$parameter] = match ($parameter) {
                'sale' => Sale::query()->value('id'),
                'purchase' => $this->samplePurchaseId($route),
                'printId' => FinanceTransaction::query()->value('id'),
                default => null,
            };

            if (! $parameters[$parameter]) {
                return null;
            }
        }

        if ($route->getName()) {
            return route($route->getName(), $parameters, false);
        }

        $uri = $route->uri();
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{'.$key.'}', $value, $uri);
        }

        return '/'.ltrim($uri, '/');
    }

    private function samplePurchaseId(LaravelRoute $route): ?int
    {
        $query = Purchase::query();

        if ($route->getName() === 'purchases.edit') {
            $query->whereIn('status', [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED]);
        }

        return $query->value('id');
    }
}
