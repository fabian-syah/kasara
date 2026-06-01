<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
            'load_average' => $this->getLoadAverage(),
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

        // Real System Memory (from /proc/meminfo)
        $status['memory'] = $this->getSystemMemory();

        // Real CPU Usage
        $status['cpu'] = $this->getCpuUsage();

        // Network Connections
        $status['network'] = $this->getNetworkInfo();

        // Tech Stack Summary
        $status['tech_stack'] = [
            ['name' => 'PHP', 'version' => phpversion(), 'icon' => 'php', 'color' => '#777BB4'],
            ['name' => 'Laravel', 'version' => app()->version(), 'icon' => 'laravel', 'color' => '#FF2D20'],
            ['name' => 'Laravel Octane', 'version' => $this->getPackageVersion('laravel/octane'), 'icon' => 'octane', 'color' => '#F7B731'],
            ['name' => 'Laravel Reverb', 'version' => $this->getPackageVersion('laravel/reverb'), 'icon' => 'reverb', 'color' => '#6C5CE7'],
            ['name' => 'Laravel Sanctum', 'version' => $this->getPackageVersion('laravel/sanctum'), 'icon' => 'sanctum', 'color' => '#00B894'],
            ['name' => 'Spatie Permission', 'version' => $this->getPackageVersion('spatie/laravel-permission'), 'icon' => 'spatie', 'color' => '#0984E3'],
            ['name' => 'MySQL / MariaDB', 'version' => $status['database']['version'] ?? '—', 'icon' => 'mysql', 'color' => '#00758F'],
            ['name' => 'Vue.js', 'version' => '3.5.24', 'icon' => 'vue', 'color' => '#42B883'],
            ['name' => 'Vite', 'version' => '7.2.4', 'icon' => 'vite', 'color' => '#646CFF'],
            ['name' => 'Tailwind CSS', 'version' => '4.1.18', 'icon' => 'tailwind', 'color' => '#38BDF8'],
            ['name' => 'Pinia', 'version' => '3.0.4', 'icon' => 'pinia', 'color' => '#FFD859'],
            ['name' => 'Axios', 'version' => '1.13.4', 'icon' => 'axios', 'color' => '#5A29E4'],
        ];

        // Security Status (Real monitoring)
        $status['security'] = $this->getSecurityData();

        return response()->json($status);
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

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

    private function getLoadAverage(): array
    {
        if (PHP_OS_FAMILY === 'Linux' && function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2),
            ];
        }
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }

    private function getSystemMemory(): array
    {
        $result = [
            'total' => '—',
            'used' => '—',
            'free' => '—',
            'usage_percent' => 0,
            'swap_total' => '—',
            'swap_used' => '—',
            'php_current' => $this->formatBytes(memory_get_usage(true)),
            'php_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'php_limit' => ini_get('memory_limit'),
        ];

        if (PHP_OS_FAMILY === 'Linux') {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo) {
                $data = [];
                foreach (explode("\n", $meminfo) as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $m)) {
                        $data[$m[1]] = (int) $m[2] * 1024;
                    }
                }

                $total = $data['MemTotal'] ?? 0;
                $free = $data['MemFree'] ?? 0;
                $buffers = $data['Buffers'] ?? 0;
                $cached = $data['Cached'] ?? 0;
                $sReclaimable = $data['SReclaimable'] ?? 0;
                $available = $data['MemAvailable'] ?? ($free + $buffers + $cached + $sReclaimable);
                $used = $total - $available;

                $result['total'] = $this->formatBytes($total);
                $result['used'] = $this->formatBytes($used);
                $result['free'] = $this->formatBytes($available);
                $result['usage_percent'] = $total > 0 ? round(($used / $total) * 100, 1) : 0;

                $swapTotal = $data['SwapTotal'] ?? 0;
                $swapFree = $data['SwapFree'] ?? 0;
                $result['swap_total'] = $this->formatBytes($swapTotal);
                $result['swap_used'] = $this->formatBytes($swapTotal - $swapFree);
            }
        }

        return $result;
    }

    private function getCpuUsage(): array
    {
        $result = [
            'cores' => 1,
            'model' => '—',
            'usage_percent' => 0,
            'processes' => 0,
        ];

        if (PHP_OS_FAMILY === 'Linux') {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuinfo) {
                preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $modelMatch);
                $result['model'] = isset($modelMatch[1]) ? trim($modelMatch[1]) : '—';
                $result['cores'] = substr_count($cpuinfo, 'processor');
            }

            // CPU usage from /proc/stat (delta-based)
            $stat1 = @file_get_contents('/proc/stat');
            if ($stat1) {
                $line = strtok($stat1, "\n");
                if (preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $m)) {
                    $idle = (int) $m[4] + (int) $m[5];
                    $total = array_sum(array_map('intval', array_slice($m, 1)));
                    $prevIdle = Cache::get('cpu_prev_idle', 0);
                    $prevTotal = Cache::get('cpu_prev_total', 0);

                    if ($prevTotal > 0 && $total > $prevTotal) {
                        $diffIdle = $idle - $prevIdle;
                        $diffTotal = $total - $prevTotal;
                        $result['usage_percent'] = round((1 - ($diffIdle / $diffTotal)) * 100, 1);
                    }

                    Cache::put('cpu_prev_idle', $idle, 30);
                    Cache::put('cpu_prev_total', $total, 30);
                }
            }

            $procCount = @shell_exec('ps aux 2>/dev/null | wc -l');
            $result['processes'] = $procCount ? max(0, (int) trim($procCount) - 1) : 0;
        }

        return $result;
    }

    private function getNetworkInfo(): array
    {
        $result = [
            'active_connections' => 0,
            'established' => 0,
            'time_wait' => 0,
            'close_wait' => 0,
            'listening_ports' => [],
        ];

        if (PHP_OS_FAMILY !== 'Linux') {
            return $result;
        }

        // Active connections by state
        $ss = @shell_exec('ss -tan 2>/dev/null | tail -n +2');
        if ($ss) {
            $lines = array_filter(explode("\n", trim($ss)));
            $result['active_connections'] = count($lines);

            foreach ($lines as $line) {
                if (preg_match('/^(\S+)/', $line, $m)) {
                    $state = strtoupper($m[1]);
                    if ($state === 'ESTAB') $result['established']++;
                    elseif ($state === 'TIME-WAIT') $result['time_wait']++;
                    elseif ($state === 'CLOSE-WAIT') $result['close_wait']++;
                }
            }
        }

        // Listening ports (important services)
        $listening = @shell_exec('ss -tlnp 2>/dev/null | tail -n +2');
        if ($listening) {
            $importantPorts = [22, 80, 443, 3306, 6379, 8000, 8080, 9000];
            foreach (explode("\n", trim($listening)) as $line) {
                if (preg_match('/:(\d+)\s/', $line, $portMatch)) {
                    $port = (int) $portMatch[1];
                    if (in_array($port, $importantPorts)) {
                        $process = '—';
                        if (preg_match('/users:\(\("([^"]+)"/', $line, $procMatch)) {
                            $process = $procMatch[1];
                        }
                        $result['listening_ports'][] = [
                            'port' => $port,
                            'process' => $process,
                        ];
                    }
                }
            }
        }

        return $result;
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

    // ============================================================
    // REAL SECURITY MONITORING & AUTO-DEFENDER
    // ============================================================

    private function getSecurityData(): array
    {
        $security = [
            'defender_active' => Cache::get('security_defender_active', true),
            'threat_level' => 'low',
            'failed_logins_1h' => 0,
            'failed_logins_24h' => 0,
            'app_failed_logins_1h' => 0,
            'blocked_ips' => [],
            'recent_attacks' => [],
            'alerts' => [],
            'firewall_active' => false,
            'fail2ban_active' => false,
            'last_attack_time' => null,
            'ssl' => $this->getSslStatus(),
            'open_ports' => [],
            'services_status' => $this->getServicesStatus(),
            'file_integrity' => $this->getFileIntegrity(),
            'security_headers_score' => $this->getSecurityHeadersScore(),
        ];

        if (PHP_OS_FAMILY !== 'Linux') {
            $security['alerts'][] = [
                'level' => 'info',
                'message' => 'Full security monitoring hanya tersedia di Linux VPS',
                'time' => now()->toIso8601String(),
            ];
            // Still check app-level security on non-Linux
            $this->checkAppLevelSecurity($security);
            return $security;
        }

        // 1. Parse auth.log for SSH failed attempts
        $authLogPaths = ['/var/log/auth.log', '/var/log/secure'];
        $authLog = null;
        foreach ($authLogPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $authLog = $path;
                break;
            }
        }

        if ($authLog) {
            $failedAttempts = $this->parseFailedAttempts($authLog);
            $now = time();

            foreach ($failedAttempts as $attempt) {
                $age = $now - $attempt['timestamp'];
                if ($age <= 3600) $security['failed_logins_1h']++;
                if ($age <= 86400) $security['failed_logins_24h']++;
            }

            // Group by IP for attack detection
            $ipAttempts = [];
            foreach ($failedAttempts as $attempt) {
                if ($now - $attempt['timestamp'] <= 3600) {
                    $ip = $attempt['ip'];
                    if (!isset($ipAttempts[$ip])) {
                        $ipAttempts[$ip] = ['count' => 0, 'last_time' => '', 'service' => $attempt['service']];
                    }
                    $ipAttempts[$ip]['count']++;
                    $ipAttempts[$ip]['last_time'] = $attempt['time_str'];
                }
            }

            arsort($ipAttempts);
            $attackerIps = array_slice($ipAttempts, 0, 10, true);
            foreach ($attackerIps as $ip => $info) {
                if ($info['count'] >= 3) {
                    $security['recent_attacks'][] = [
                        'ip' => $ip,
                        'attempts' => $info['count'],
                        'last_attempt' => $info['last_time'],
                        'service' => $info['service'],
                        'auto_blocked' => false,
                    ];
                }
            }

            if (!empty($failedAttempts)) {
                $lastAttempt = end($failedAttempts);
                $security['last_attack_time'] = $lastAttempt['time_str'];
            }

            // Generate alerts & auto-block for brute-force
            foreach ($attackerIps as $ip => $info) {
                if ($info['count'] >= 10) {
                    $security['alerts'][] = [
                        'level' => 'critical',
                        'message' => "Brute-force terdeteksi dari {$ip} ({$info['count']}x via {$info['service']})",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];

                    if ($security['defender_active']) {
                        $this->autoBlockIp($ip);
                        $security['alerts'][] = [
                            'level' => 'success',
                            'message' => "Auto-Defender: IP {$ip} diblokir ({$info['count']}x gagal login)",
                            'time' => now()->toIso8601String(),
                            'ip' => $ip,
                        ];
                        foreach ($security['recent_attacks'] as &$attack) {
                            if ($attack['ip'] === $ip) {
                                $attack['auto_blocked'] = true;
                            }
                        }
                    }
                } elseif ($info['count'] >= 5) {
                    $security['alerts'][] = [
                        'level' => 'warning',
                        'message' => "Login mencurigakan dari {$ip} ({$info['count']}x via {$info['service']})",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];
                }
            }
        }

        // 2. HTTP Flood / DDoS Detection
        $this->detectHttpFlood($security);

        // 3. Application-level security (Laravel login failures)
        $this->checkAppLevelSecurity($security);

        // 4. Check fail2ban status
        $fail2banStatus = @shell_exec('fail2ban-client status 2>/dev/null');
        if ($fail2banStatus) {
            $security['fail2ban_active'] = true;
            $sshdStatus = @shell_exec('fail2ban-client status sshd 2>/dev/null');
            if ($sshdStatus && preg_match('/Banned IP list:\s*(.+)$/m', $sshdStatus, $matches)) {
                $bannedIps = array_filter(array_map('trim', explode(' ', $matches[1])));
                foreach ($bannedIps as $ip) {
                    $security['blocked_ips'][] = [
                        'ip' => $ip,
                        'source' => 'fail2ban',
                        'jail' => 'sshd',
                    ];
                }
            }
        }

        // 5. Check iptables / firewall
        $iptables = @shell_exec('iptables -L APEX-DEFENDER -n 2>/dev/null');
        if ($iptables && is_string($iptables)) {
            $security['firewall_active'] = true;
            preg_match_all('/DROP\s+all\s+--\s+([\d.]+)/', $iptables, $matches);
            if (!empty($matches[1])) {
                foreach ((array) $matches[1] as $ip) {
                    $exists = false;
                    foreach ($security['blocked_ips'] as $blocked) {
                        if ($blocked['ip'] === $ip) { $exists = true; break; }
                    }
                    if (!$exists) {
                        $security['blocked_ips'][] = [
                            'ip' => $ip,
                            'source' => 'defender',
                            'jail' => null,
                        ];
                    }
                }
            }
        } else {
            $hasIptables = @shell_exec('which iptables 2>/dev/null');
            $security['firewall_active'] = !empty(trim($hasIptables ?? ''));
        }

        // 6. UFW status check
        $ufwStatus = @shell_exec('ufw status 2>/dev/null');
        if ($ufwStatus && strpos($ufwStatus, 'active') !== false) {
            $security['firewall_active'] = true;
        }

        // 7. Open ports scan (real nmap-like via ss)
        $security['open_ports'] = $this->scanOpenPorts();

        // 8. Determine threat level
        $totalThreats = $security['failed_logins_1h'] + $security['app_failed_logins_1h'];
        $attackCount = count($security['recent_attacks']);

        if ($totalThreats >= 50 || $attackCount >= 5) {
            $security['threat_level'] = 'critical';
        } elseif ($totalThreats >= 20 || $attackCount >= 3) {
            $security['threat_level'] = 'high';
        } elseif ($totalThreats >= 5 || $attackCount >= 1) {
            $security['threat_level'] = 'medium';
        }

        // 9. Success message if no threats
        if (empty($security['alerts'])) {
            $security['alerts'][] = [
                'level' => 'success',
                'message' => 'Tidak ada ancaman terdeteksi. Sistem aman.',
                'time' => now()->toIso8601String(),
            ];
        }

        return $security;
    }

    // ============================================================
    // REAL SSL/TLS CERTIFICATE CHECK
    // ============================================================

    private function getSslStatus(): array
    {
        $result = [
            'valid' => false,
            'issuer' => '—',
            'expires_at' => '—',
            'days_remaining' => 0,
            'protocol' => '—',
            'domain' => '—',
        ];

        // Check SSL cert for the app domain
        $appUrl = config('app.url', '');
        $parsed = parse_url($appUrl);
        $host = $parsed['host'] ?? null;

        if (!$host) {
            return $result;
        }

        $result['domain'] = $host;

        // Use openssl to check certificate
        $certOutput = @shell_exec("echo | openssl s_client -servername {$host} -connect {$host}:443 2>/dev/null | openssl x509 -noout -dates -issuer -subject 2>/dev/null");

        if ($certOutput) {
            // Parse expiry
            if (preg_match('/notAfter=(.+)$/m', $certOutput, $m)) {
                $expiresAt = strtotime(trim($m[1]));
                if ($expiresAt) {
                    $result['expires_at'] = date('Y-m-d H:i:s', $expiresAt);
                    $result['days_remaining'] = max(0, (int) ceil(($expiresAt - time()) / 86400));
                    $result['valid'] = $expiresAt > time();
                }
            }

            // Parse issuer
            if (preg_match('/issuer\s*=\s*(.+)$/m', $certOutput, $m)) {
                $issuerStr = trim($m[1]);
                if (preg_match('/O\s*=\s*([^,\/]+)/', $issuerStr, $orgMatch)) {
                    $result['issuer'] = trim($orgMatch[1]);
                } else {
                    $result['issuer'] = $issuerStr;
                }
            }

            // Check TLS version
            $tlsCheck = @shell_exec("echo | openssl s_client -servername {$host} -connect {$host}:443 2>/dev/null | grep 'Protocol'");
            if ($tlsCheck && preg_match('/Protocol\s*:\s*(.+)$/m', $tlsCheck, $m)) {
                $result['protocol'] = trim($m[1]);
            }
        }

        return $result;
    }

    // ============================================================
    // REAL SERVICES STATUS CHECK
    // ============================================================

    private function getServicesStatus(): array
    {
        $services = [];

        if (PHP_OS_FAMILY !== 'Linux') {
            return $services;
        }

        $checkServices = [
            'nginx' => 'Web Server',
            'apache2' => 'Web Server',
            'mysql' => 'Database',
            'mariadb' => 'Database',
            'redis-server' => 'Cache',
            'redis' => 'Cache',
            'fail2ban' => 'Security',
            'ssh' => 'Remote Access',
            'sshd' => 'Remote Access',
            'ufw' => 'Firewall',
        ];

        foreach ($checkServices as $service => $category) {
            $status = @shell_exec("systemctl is-active {$service} 2>/dev/null");
            $status = trim($status ?? '');

            if ($status === 'active' || $status === 'inactive' || $status === 'failed') {
                $services[] = [
                    'name' => $service,
                    'category' => $category,
                    'status' => $status,
                    'running' => $status === 'active',
                ];
            }
        }

        return $services;
    }

    // ============================================================
    // REAL FILE INTEGRITY MONITORING
    // ============================================================

    private function getFileIntegrity(): array
    {
        $result = [
            'status' => 'ok',
            'last_check' => null,
            'modified_files' => [],
            'suspicious_files' => [],
        ];

        // Check critical Laravel files for unexpected modifications
        $criticalFiles = [
            base_path('.env'),
            base_path('composer.json'),
            base_path('artisan'),
            app_path('Http/Kernel.php'),
            config_path('app.php'),
            config_path('auth.php'),
            config_path('database.php'),
            public_path('index.php'),
        ];

        $cacheKey = 'file_integrity_hashes';
        $storedHashes = Cache::get($cacheKey, []);
        $currentHashes = [];
        $modified = [];

        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $hash = md5_file($file);
                $relativePath = str_replace(base_path() . '/', '', $file);
                $currentHashes[$relativePath] = $hash;

                if (!empty($storedHashes) && isset($storedHashes[$relativePath])) {
                    if ($storedHashes[$relativePath] !== $hash) {
                        $modified[] = [
                            'file' => $relativePath,
                            'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
                        ];
                    }
                }
            }
        }

        // Store current hashes (first run = baseline)
        if (empty($storedHashes)) {
            Cache::put($cacheKey, $currentHashes, 60 * 60 * 24 * 30); // 30 days
        }

        $result['modified_files'] = $modified;
        $result['last_check'] = now()->toIso8601String();

        // Check for suspicious PHP files in public directory
        if (PHP_OS_FAMILY === 'Linux') {
            $suspiciousCmd = "find " . escapeshellarg(public_path()) . " -name '*.php' -newer " . escapeshellarg(public_path('index.php')) . " -not -name 'index.php' 2>/dev/null | head -10";
            $suspicious = @shell_exec($suspiciousCmd);
            if ($suspicious) {
                $files = array_filter(explode("\n", trim($suspicious)));
                foreach ($files as $file) {
                    $result['suspicious_files'][] = str_replace(base_path() . '/', '', $file);
                }
            }
        }

        if (!empty($modified) || !empty($result['suspicious_files'])) {
            $result['status'] = 'warning';
        }

        return $result;
    }

    // ============================================================
    // SECURITY HEADERS SCORE (Real check)
    // ============================================================

    private function getSecurityHeadersScore(): array
    {
        $headers = [
            'strict_transport_security' => false,
            'x_frame_options' => false,
            'x_content_type_options' => false,
            'x_xss_protection' => false,
            'referrer_policy' => false,
            'permissions_policy' => false,
            'content_security_policy' => false,
        ];

        // Check if SecurityHeaders middleware is registered
        $middlewareFile = app_path('Http/Middleware/SecurityHeaders.php');
        if (file_exists($middlewareFile)) {
            $content = file_get_contents($middlewareFile);
            $headers['strict_transport_security'] = strpos($content, 'Strict-Transport-Security') !== false;
            $headers['x_frame_options'] = strpos($content, 'X-Frame-Options') !== false;
            $headers['x_content_type_options'] = strpos($content, 'X-Content-Type-Options') !== false;
            $headers['x_xss_protection'] = strpos($content, 'X-XSS-Protection') !== false;
            $headers['referrer_policy'] = strpos($content, 'Referrer-Policy') !== false;
            $headers['permissions_policy'] = strpos($content, 'Permissions-Policy') !== false;
            $headers['content_security_policy'] = strpos($content, 'Content-Security-Policy') !== false;
        }

        $total = count($headers);
        $passed = count(array_filter($headers));

        return [
            'score' => $total > 0 ? round(($passed / $total) * 100) : 0,
            'passed' => $passed,
            'total' => $total,
            'details' => $headers,
        ];
    }

    // ============================================================
    // APPLICATION-LEVEL SECURITY (Laravel login failures)
    // ============================================================

    private function checkAppLevelSecurity(array &$security): void
    {
        // Check Laravel's rate limiter cache for failed login attempts
        // This monitors actual application login failures (not just SSH)
        $appFailures = Cache::get('app_failed_logins', []);
        $now = time();
        $recentFailures = [];

        foreach ($appFailures as $entry) {
            if (($now - ($entry['timestamp'] ?? 0)) <= 3600) {
                $recentFailures[] = $entry;
            }
        }

        $security['app_failed_logins_1h'] = count($recentFailures);

        // Group by IP
        $ipGroups = [];
        foreach ($recentFailures as $entry) {
            $ip = $entry['ip'] ?? 'unknown';
            if (!isset($ipGroups[$ip])) {
                $ipGroups[$ip] = 0;
            }
            $ipGroups[$ip]++;
        }

        // Alert if any IP has too many app-level failures
        foreach ($ipGroups as $ip => $count) {
            if ($count >= 10) {
                $security['alerts'][] = [
                    'level' => 'warning',
                    'message' => "App login brute-force dari {$ip} ({$count}x gagal dalam 1 jam)",
                    'time' => now()->toIso8601String(),
                    'ip' => $ip,
                ];

                // Auto-block at app level too
                if ($security['defender_active'] && $count >= 20 && PHP_OS_FAMILY === 'Linux') {
                    $this->autoBlockIp($ip);
                }
            }
        }

        // Check for suspicious patterns in Laravel log
        $this->checkLaravelLogForThreats($security);
    }

    private function checkLaravelLogForThreats(array &$security): void
    {
        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return;
        }

        // Read last 500 lines efficiently
        $lines = [];
        $fp = @fopen($logFile, 'r');
        if (!$fp) return;

        // Seek to end and read backwards
        fseek($fp, 0, SEEK_END);
        $fileSize = ftell($fp);
        $readSize = min($fileSize, 100000); // Read last 100KB
        fseek($fp, -$readSize, SEEK_END);
        $content = fread($fp, $readSize);
        fclose($fp);

        $lines = explode("\n", $content);
        $lines = array_slice($lines, -500);

        $sqlInjectionCount = 0;
        $xssCount = 0;
        $pathTraversalCount = 0;
        $today = now()->format('Y-m-d');

        foreach ($lines as $line) {
            if (strpos($line, $today) === false) continue;

            // Detect SQL injection attempts in logs
            if (preg_match('/SQLSTATE|sql.*syntax|union.*select|drop\s+table|insert\s+into.*values/i', $line)) {
                $sqlInjectionCount++;
            }

            // Detect XSS attempts
            if (preg_match('/<script|javascript:|on\w+\s*=|eval\s*\(/i', $line)) {
                $xssCount++;
            }

            // Detect path traversal
            if (preg_match('/\.\.[\/\\\\]|etc\/passwd|proc\/self/i', $line)) {
                $pathTraversalCount++;
            }
        }

        if ($sqlInjectionCount > 5) {
            $security['alerts'][] = [
                'level' => 'critical',
                'message' => "SQL Injection attempts terdeteksi ({$sqlInjectionCount}x hari ini)",
                'time' => now()->toIso8601String(),
            ];
        }

        if ($xssCount > 5) {
            $security['alerts'][] = [
                'level' => 'warning',
                'message' => "XSS attempts terdeteksi ({$xssCount}x hari ini)",
                'time' => now()->toIso8601String(),
            ];
        }

        if ($pathTraversalCount > 3) {
            $security['alerts'][] = [
                'level' => 'warning',
                'message' => "Path traversal attempts terdeteksi ({$pathTraversalCount}x hari ini)",
                'time' => now()->toIso8601String(),
            ];
        }
    }

    // ============================================================
    // OPEN PORTS SCANNER (Real)
    // ============================================================

    private function scanOpenPorts(): array
    {
        $ports = [];

        if (PHP_OS_FAMILY !== 'Linux') {
            return $ports;
        }

        // Use ss to get all listening TCP ports
        $output = @shell_exec('ss -tlnp 2>/dev/null | tail -n +2');
        if (!$output) return $ports;

        $knownPorts = [
            22 => ['name' => 'SSH', 'risk' => 'medium'],
            80 => ['name' => 'HTTP', 'risk' => 'low'],
            443 => ['name' => 'HTTPS', 'risk' => 'low'],
            3306 => ['name' => 'MySQL', 'risk' => 'high'],
            6379 => ['name' => 'Redis', 'risk' => 'high'],
            5432 => ['name' => 'PostgreSQL', 'risk' => 'high'],
            27017 => ['name' => 'MongoDB', 'risk' => 'high'],
            8080 => ['name' => 'HTTP Alt', 'risk' => 'medium'],
            8000 => ['name' => 'Dev Server', 'risk' => 'medium'],
            9000 => ['name' => 'PHP-FPM', 'risk' => 'low'],
            21 => ['name' => 'FTP', 'risk' => 'critical'],
            23 => ['name' => 'Telnet', 'risk' => 'critical'],
            25 => ['name' => 'SMTP', 'risk' => 'medium'],
            53 => ['name' => 'DNS', 'risk' => 'medium'],
            11211 => ['name' => 'Memcached', 'risk' => 'high'],
        ];

        foreach (explode("\n", trim($output)) as $line) {
            // Extract port and binding address
            if (preg_match('/\s+([\d.*:]+):(\d+)\s/', $line, $m)) {
                $bindAddr = $m[1];
                $port = (int) $m[2];
                $process = '—';

                if (preg_match('/users:\(\("([^"]+)"/', $line, $procMatch)) {
                    $process = $procMatch[1];
                }

                // Determine if exposed externally
                $isExternal = ($bindAddr === '0.0.0.0' || $bindAddr === '*' || $bindAddr === '::');
                $portInfo = $knownPorts[$port] ?? ['name' => "Port {$port}", 'risk' => 'unknown'];

                // Only flag risky external ports
                if ($isExternal && isset($knownPorts[$port])) {
                    $ports[] = [
                        'port' => $port,
                        'name' => $portInfo['name'],
                        'process' => $process,
                        'bind' => $bindAddr,
                        'external' => $isExternal,
                        'risk' => $isExternal ? $portInfo['risk'] : 'low',
                    ];
                }
            }
        }

        return $ports;
    }

    // ============================================================
    // LOG PARSERS
    // ============================================================

    private function parseFailedAttempts(string $logPath): array
    {
        $attempts = [];

        // Use tail for performance (last 3000 lines)
        $cmd = "tail -n 3000 " . escapeshellarg($logPath);
        $content = @shell_exec($cmd);
        if (!$content) {
            // Fallback to file reading
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) return [];
            $lines = array_slice($lines, -3000);
            $content = implode("\n", $lines);
        }

        $lines = explode("\n", $content);
        $currentYear = date('Y');

        foreach ($lines as $line) {
            // SSH failed password / invalid user
            if (preg_match('/^(\w+\s+\d+\s+[\d:]+)\s.*(?:Failed password|Invalid user|authentication failure).*(?:from|rhost=)\s*([\d.]+)/i', $line, $m)) {
                $timeStr = $m[1] . ' ' . $currentYear;
                $timestamp = @strtotime($timeStr);
                if ($timestamp && $timestamp <= time()) {
                    $attempts[] = [
                        'ip' => $m[2],
                        'timestamp' => $timestamp,
                        'time_str' => date('Y-m-d H:i:s', $timestamp),
                        'service' => 'SSH',
                    ];
                }
            }
            // Also catch FTP/other PAM failures
            elseif (preg_match('/^(\w+\s+\d+\s+[\d:]+)\s.*pam_unix.*authentication failure.*rhost=([\d.]+)/i', $line, $m)) {
                $timeStr = $m[1] . ' ' . $currentYear;
                $timestamp = @strtotime($timeStr);
                if ($timestamp && $timestamp <= time()) {
                    $attempts[] = [
                        'ip' => $m[2],
                        'timestamp' => $timestamp,
                        'time_str' => date('Y-m-d H:i:s', $timestamp),
                        'service' => 'PAM',
                    ];
                }
            }
        }

        return $attempts;
    }

    // ============================================================
    // HTTP FLOOD / DDoS DETECTION (Real)
    // ============================================================

    private function detectHttpFlood(array &$security): void
    {
        $maxLines = 3000;
        $scanWindow = 60;
        $threshold = 100;

        $logPaths = array_merge(
            glob('/var/log/nginx/*access.log') ?: [],
            glob('/var/log/apache2/*access.log') ?: [],
            glob('/var/log/httpd/*access.log') ?: [],
            glob('/usr/local/lsws/logs/*access.log') ?: []
        );

        if (empty($logPaths)) {
            $defaults = ['/var/log/nginx/access.log', '/var/log/apache2/access.log', '/usr/local/lsws/logs/access.log'];
            foreach ($defaults as $path) {
                if (file_exists($path)) $logPaths[] = $path;
            }
        }

        $ipCounts = [];
        $now = time();

        foreach ($logPaths as $path) {
            if (!is_readable($path)) continue;

            $cmd = "tail -n {$maxLines} " . escapeshellarg($path);
            $output = @shell_exec($cmd);
            if (!$output) continue;

            foreach (explode("\n", $output) as $line) {
                if (empty($line)) continue;

                if (preg_match('/^(\S+) \- \S+ \[([^\]]+)\]/', $line, $m)) {
                    $ip = $m[1];
                    $dateStr = $m[2];

                    $timestamp = 0;
                    try {
                        $dt = \DateTime::createFromFormat('d/M/Y:H:i:s O', $dateStr);
                        $timestamp = $dt ? $dt->getTimestamp() : 0;
                    } catch (\Exception $e) {
                        $dateStrClean = preg_replace('/:/', ' ', $dateStr, 1);
                        $timestamp = @strtotime($dateStrClean) ?: 0;
                    }

                    if ($timestamp && ($now - $timestamp) <= $scanWindow) {
                        $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
                    }
                }
            }
        }

        foreach ($ipCounts as $ip => $count) {
            if ($count >= 50) {
                $isAttack = $count >= $threshold;

                $security['recent_attacks'][] = [
                    'ip' => $ip,
                    'attempts' => $count,
                    'last_attempt' => date('Y-m-d H:i:s'),
                    'service' => 'HTTP/Web',
                    'auto_blocked' => false,
                ];

                if ($isAttack) {
                    $security['alerts'][] = [
                        'level' => 'critical',
                        'message' => "DDoS/HTTP Flood dari {$ip} ({$count} reqs/menit)",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];
                    $security['threat_level'] = 'critical';

                    if ($security['defender_active']) {
                        if ($this->autoBlockIp($ip)) {
                            $security['alerts'][] = [
                                'level' => 'success',
                                'message' => "Auto-Defender: {$ip} diblokir ({$count} reqs/menit)",
                                'time' => now()->toIso8601String(),
                                'ip' => $ip,
                            ];
                            foreach ($security['recent_attacks'] as &$attack) {
                                if ($attack['ip'] === $ip) $attack['auto_blocked'] = true;
                            }
                        }
                    }
                } else {
                    $security['alerts'][] = [
                        'level' => 'warning',
                        'message' => "Trafik tinggi dari {$ip} ({$count} reqs/menit)",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];
                }
            }
        }
    }

    // ============================================================
    // AUTO-BLOCK & IP MANAGEMENT
    // ============================================================

    private function autoBlockIp(string $ip): bool
    {
        if ($this->isPrivateIp($ip)) return false;

        $blockedKey = "defender_blocked_{$ip}";
        if (Cache::has($blockedKey)) return false;

        // Validate IP format strictly
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;

        @shell_exec('iptables -N APEX-DEFENDER 2>/dev/null');
        @shell_exec('iptables -I INPUT -j APEX-DEFENDER 2>/dev/null');

        $escapedIp = escapeshellarg($ip);
        @shell_exec("iptables -A APEX-DEFENDER -s {$escapedIp} -j DROP 2>&1");

        Cache::put($blockedKey, true, 86400);
        Log::warning("APEX Defender: Auto-blocked IP {$ip}");

        return true;
    }

    public function blockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ip = $request->ip;

        if ($this->isPrivateIp($ip)) {
            return response()->json(['message' => 'Tidak bisa blokir IP private/local'], 422);
        }

        @shell_exec('iptables -N APEX-DEFENDER 2>/dev/null');
        @shell_exec('iptables -I INPUT -j APEX-DEFENDER 2>/dev/null');

        $escapedIp = escapeshellarg($ip);
        $check = @shell_exec("iptables -C APEX-DEFENDER -s {$escapedIp} -j DROP 2>&1");
        if (strpos($check, 'iptables: Bad rule') === false && empty($check)) {
            return response()->json(['message' => "IP {$ip} sudah diblokir"], 422);
        }

        @shell_exec("iptables -A APEX-DEFENDER -s {$escapedIp} -j DROP 2>&1");
        Cache::put("defender_blocked_{$ip}", true, 86400);

        Log::warning("APEX Defender: Manual block IP {$ip} by user " . ($request->user()->id ?? 'unknown'));

        return response()->json(['success' => true, 'message' => "IP {$ip} berhasil diblokir"]);
    }

    public function unblockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ip = $request->ip;

        $escapedIp = escapeshellarg($ip);
        @shell_exec("iptables -D APEX-DEFENDER -s {$escapedIp} -j DROP 2>&1");
        Cache::forget("defender_blocked_{$ip}");

        @shell_exec("fail2ban-client set sshd unbanip {$escapedIp} 2>/dev/null");

        Log::info("APEX Defender: Unblocked IP {$ip} by user " . ($request->user()->id ?? 'unknown'));

        return response()->json(['success' => true, 'message' => "IP {$ip} berhasil di-unblokir"]);
    }

    public function toggleDefender(Request $request)
    {
        $current = Cache::get('security_defender_active', true);
        $newState = !$current;
        Cache::put('security_defender_active', $newState, 60 * 60 * 24 * 365);

        $statusLabel = $newState ? 'AKTIF' : 'NONAKTIF';
        Log::info("APEX Defender: Status diubah ke {$statusLabel} oleh user " . ($request->user()->id ?? 'unknown'));

        return response()->json([
            'success' => true,
            'defender_active' => $newState,
            'message' => "Auto-Defender sekarang {$statusLabel}",
        ]);
    }

    /**
     * Record failed login attempt (call this from AuthController)
     */
    public static function recordFailedLogin(string $ip, string $email = ''): void
    {
        $failures = Cache::get('app_failed_logins', []);
        $failures[] = [
            'ip' => $ip,
            'email' => $email,
            'timestamp' => time(),
            'time_str' => now()->toIso8601String(),
        ];

        // Keep only last 24h
        $cutoff = time() - 86400;
        $failures = array_filter($failures, fn($f) => ($f['timestamp'] ?? 0) > $cutoff);

        Cache::put('app_failed_logins', array_values($failures), 86400);
    }

    /**
     * Reset file integrity baseline
     */
    public function resetIntegrityBaseline(Request $request)
    {
        Cache::forget('file_integrity_hashes');
        return response()->json(['success' => true, 'message' => 'Baseline integritas file di-reset']);
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    // ============================================================
    // DATABASE BACKUP & DOWNLOAD
    // ============================================================

    /**
     * Create a full database backup and stream it as download (SQL format)
     */
    public function backupDatabase(Request $request)
    {
        $dbConnection = config('database.default');
        $dbConfig = config("database.connections.{$dbConnection}");
        $driver = $dbConfig['driver'] ?? 'pgsql';
        $database = $dbConfig['database'] ?? 'apex_pos';
        $host = $dbConfig['host'] ?? 'db';
        $port = $dbConfig['port'] ?? '5432';
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$database}_{$timestamp}.sql";

        // Build dump command based on driver
        if ($driver === 'pgsql') {
            $cmd = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl 2>&1',
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database)
            );
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $cmd = sprintf(
                'mysqldump -h %s -P %s -u %s -p%s %s --single-transaction --routines --triggers 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database)
            );
        } else {
            return response()->json(['message' => "Driver '{$driver}' tidak didukung untuk backup"], 422);
        }

        // Execute dump
        $output = shell_exec($cmd);

        if (empty($output) || strpos($output, 'error') !== false && strlen($output) < 200) {
            Log::error("Database backup failed: {$output}");
            return response()->json([
                'success' => false,
                'message' => 'Backup gagal: ' . substr($output ?? 'Unknown error', 0, 200),
            ], 500);
        }

        Log::info("Database backup created by user " . ($request->user()->id ?? 'unknown') . ": {$filename}");

        // Stream as download
        return response($output, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($output),
        ]);
    }

    /**
     * Get list of available backup info (size estimate, table count)
     */
    public function backupInfo(Request $request)
    {
        $dbConnection = config('database.default');
        $dbConfig = config("database.connections.{$dbConnection}");
        $driver = $dbConfig['driver'] ?? 'pgsql';
        $database = $dbConfig['database'] ?? 'apex_pos';

        $tables = [];
        $totalRows = 0;

        try {
            if ($driver === 'pgsql') {
                $tableList = DB::select("
                    SELECT tablename, 
                           pg_size_pretty(pg_total_relation_size(quote_ident(tablename))) as size,
                           (SELECT count(*) FROM information_schema.columns WHERE table_name = tablename) as columns
                    FROM pg_tables 
                    WHERE schemaname = 'public' 
                    ORDER BY pg_total_relation_size(quote_ident(tablename)) DESC
                ");

                foreach ($tableList as $table) {
                    $rowCount = DB::table($table->tablename)->count();
                    $totalRows += $rowCount;
                    $tables[] = [
                        'name' => $table->tablename,
                        'rows' => $rowCount,
                        'size' => $table->size,
                        'columns' => $table->columns,
                    ];
                }

                // Total DB size
                $dbSize = DB::selectOne("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
                $totalSize = $dbSize->size ?? '—';

            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                $tableList = DB::select("
                    SELECT TABLE_NAME as table_name, 
                           ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb,
                           TABLE_ROWS as row_count
                    FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = ?
                    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                ", [$database]);

                foreach ($tableList as $table) {
                    $totalRows += $table->row_count ?? 0;
                    $tables[] = [
                        'name' => $table->table_name,
                        'rows' => $table->row_count ?? 0,
                        'size' => ($table->size_mb ?? 0) . ' MB',
                        'columns' => 0,
                    ];
                }

                $totalSize = round(array_sum(array_column($tableList, 'size_mb')), 2) . ' MB';
            } else {
                return response()->json(['message' => 'Driver tidak didukung'], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca info database: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'database' => $database,
            'driver' => $driver,
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
            'total_size' => $totalSize ?? '—',
            'tables' => $tables,
        ]);
    }
}
