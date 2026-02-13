<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SystemStatusController extends Controller
{
    public function index()
    {
        $status = [];

        // Server uptime
        $status['server'] = [
            'status' => 'online',
            'hostname' => gethostname(),
            'ip' => request()->server('SERVER_ADDR', '—'),
            'os' => PHP_OS_FAMILY . ' ' . php_uname('r'),
            'server_software' => request()->server('SERVER_SOFTWARE', 'Octane'),
            'uptime' => $this->getUptime(),
            'timezone' => config('app.timezone'),
            'current_time' => now()->format('Y-m-d H:i:s'),
        ];

        // PHP Info
        $status['php'] = [
            'version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => $this->getKeyExtensions(),
        ];

        // Laravel Info
        $status['laravel'] = [
            'version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
            'broadcast_driver' => config('broadcasting.default'),
        ];

        // Database
        try {
            $pdo = DB::connection()->getPdo();
            $dbVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $dbDriver = DB::connection()->getDriverName();
            $status['database'] = [
                'status' => 'connected',
                'driver' => $dbDriver,
                'version' => $dbVersion,
                'database' => config('database.connections.' . config('database.default') . '.database'),
            ];
        } catch (\Exception $e) {
            $status['database'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Disk usage
        $storagePath = storage_path();
        $totalDisk = @disk_total_space('/') ?: @disk_total_space($storagePath);
        $freeDisk = @disk_free_space('/') ?: @disk_free_space($storagePath);

        $status['disk'] = [
            'total' => $totalDisk ? $this->formatBytes($totalDisk) : '—',
            'free' => $freeDisk ? $this->formatBytes($freeDisk) : '—',
            'used' => ($totalDisk && $freeDisk) ? $this->formatBytes($totalDisk - $freeDisk) : '—',
            'usage_percent' => ($totalDisk && $freeDisk) ? round((($totalDisk - $freeDisk) / $totalDisk) * 100, 1) : 0,
        ];

        // Memory usage
        $memUsage = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);
        $status['memory'] = [
            'current' => $this->formatBytes($memUsage),
            'peak' => $this->formatBytes($memPeak),
            'limit' => ini_get('memory_limit'),
        ];

        // Tech Stack Summary
        $status['tech_stack'] = [
            ['name' => 'PHP', 'version' => phpversion(), 'icon' => 'php', 'color' => '#777BB4'],
            ['name' => 'Laravel', 'version' => app()->version(), 'icon' => 'laravel', 'color' => '#FF2D20'],
            ['name' => 'Laravel Octane', 'version' => $this->getPackageVersion('laravel/octane'), 'icon' => 'zap', 'color' => '#F7B731'],
            ['name' => 'Laravel Reverb', 'version' => $this->getPackageVersion('laravel/reverb'), 'icon' => 'radio', 'color' => '#6C5CE7'],
            ['name' => 'Laravel Sanctum', 'version' => $this->getPackageVersion('laravel/sanctum'), 'icon' => 'shield', 'color' => '#00B894'],
            ['name' => 'Spatie Permission', 'version' => $this->getPackageVersion('spatie/laravel-permission'), 'icon' => 'lock', 'color' => '#0984E3'],
            ['name' => 'MySQL / MariaDB', 'version' => $status['database']['version'] ?? '—', 'icon' => 'database', 'color' => '#00758F'],
            ['name' => 'Vue.js', 'version' => '3.5.24', 'icon' => 'vue', 'color' => '#42B883'],
            ['name' => 'Vite', 'version' => '7.2.4', 'icon' => 'bolt', 'color' => '#646CFF'],
            ['name' => 'Tailwind CSS', 'version' => '4.1.18', 'icon' => 'palette', 'color' => '#38BDF8'],
            ['name' => 'Pinia', 'version' => '3.0.4', 'icon' => 'box', 'color' => '#FFD859'],
            ['name' => 'Axios', 'version' => '1.13.4', 'icon' => 'globe', 'color' => '#5A29E4'],
        ];

        return response()->json($status);
    }

    private function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                return "{$days}d {$hours}h {$minutes}m";
            }
        }
        return '—';
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }

    private function getKeyExtensions(): array
    {
        $check = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'gd', 'curl', 'redis', 'pcntl', 'posix'];
        $result = [];
        foreach ($check as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        return $result;
    }

    private function getPackageVersion(string $package): string
    {
        $lockPath = base_path('composer.lock');
        if (!file_exists($lockPath)) {
            return '—';
        }

        $lock = json_decode(file_get_contents($lockPath), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        foreach ($packages as $pkg) {
            if ($pkg['name'] === $package) {
                return ltrim($pkg['version'] ?? '—', 'v');
            }
        }

        return '—';
    }
}
