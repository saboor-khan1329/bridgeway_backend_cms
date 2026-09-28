<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class SystemHealthController extends Controller
{
    public function index()
    {
        return view('admin.system.health', [
            'title'  => 'System Health',
            'checks' => $this->runChecks(),
        ]);
    }

    /** JSON endpoint — useful for external monitoring / ping scripts. */
    public function json()
    {
        $checks   = $this->runChecks();
        $allOk    = collect($checks)->every(fn ($c) => ($c['status'] ?? '') !== 'error');
        $httpCode = $allOk ? 200 : 503;

        return response()->json(['ok' => $allOk, 'checks' => $checks], $httpCode);
    }

    // ─── Individual checks ───────────────────────────────────────────────────

    private function runChecks(): array
    {
        return [
            'database'    => $this->checkDatabase(),
            'redis'       => $this->checkRedis(),
            'cache'       => $this->checkCache(),
            'queue'       => $this->checkQueue(),
            'storage'     => $this->checkStorage(),
            'application' => $this->applicationInfo(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = $this->ms($start);
            return ['status' => 'ok', 'label' => 'Connected', 'detail' => "{$ms} ms round-trip"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'label' => 'Connection failed', 'detail' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');

        if ($cacheDriver !== 'redis' && $queueDriver !== 'redis') {
            return [
                'status' => 'skipped',
                'label'  => 'Not in use',
                'detail' => "Cache: {$cacheDriver} / Queue: {$queueDriver}",
            ];
        }

        try {
            $start = microtime(true);
            Redis::connection()->ping();
            $ms = $this->ms($start);
            return [
                'status' => 'ok',
                'label'  => 'Responding',
                'detail' => "{$ms} ms — Cache: {$cacheDriver} / Queue: {$queueDriver}",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'label' => 'Redis error', 'detail' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        $driver = config('cache.default');
        $key    = '_health_check_' . now()->timestamp;

        try {
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            $ok = $val === 'ok';
            return [
                'status' => $ok ? 'ok' : 'error',
                'label'  => $ok ? 'Read/Write OK' : 'Read/Write FAILED',
                'detail' => "Driver: {$driver}",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'label' => "Cache error ({$driver})", 'detail' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        $driver   = config('queue.default');
        $queues   = ['default', 'onboarding', 'mail-outbox'];
        $sizes    = [];

        foreach ($queues as $q) {
            try {
                $sizes[$q] = Queue::size($q);
            } catch (\Throwable) {
                $sizes[$q] = '?';
            }
        }

        $failed = 0;
        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable) {}

        $pending = collect($sizes)->sum(fn ($v) => is_int($v) ? $v : 0);

        return [
            'status' => $failed > 0 ? 'warning' : 'ok',
            'label'  => $failed > 0 ? "{$failed} failed job(s)" : 'No failed jobs',
            'detail' => "Driver: {$driver} | Pending: {$pending}",
            'queues' => $sizes,
            'failed' => $failed,
        ];
    }

    private function checkStorage(): array
    {
        $path     = storage_path('app');
        $writable = is_writable($path);
        $onboarding = storage_path('app/onboarding');

        return [
            'status' => $writable ? 'ok' : 'error',
            'label'  => $writable ? 'Writable' : 'NOT writable',
            'detail' => $path,
            'onboarding_dir_exists' => is_dir($onboarding),
        ];
    }

    private function applicationInfo(): array
    {
        $env   = config('app.env');
        $debug = config('app.debug');

        return [
            'status'  => ($env === 'production' && $debug) ? 'warning' : 'ok',
            'label'   => "Laravel " . app()->version(),
            'detail'  => "PHP " . PHP_VERSION . " | ENV: {$env} | Debug: " . ($debug ? 'ON ⚠' : 'OFF'),
            'env'     => $env,
            'debug'   => $debug,
            'php'     => PHP_VERSION,
            'laravel' => app()->version(),
            'tz'      => config('app.timezone'),
            'url'     => config('app.url'),
        ];
    }

    private function ms(float $start): string
    {
        return number_format((microtime(true) - $start) * 1000, 2);
    }
}
