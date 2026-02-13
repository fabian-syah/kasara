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

        // Security Status (Integrated)
        $status['security'] = $this->getSecurityData();

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

    // ============================================================
    // SECURITY MONITORING & AUTO-DEFENDER
    // ============================================================

    private function getSecurityData(): array
    {
        $security = [
            'defender_active' => Cache::get('security_defender_active', true),
            'threat_level' => 'low', // low, medium, high, critical
            'failed_logins_1h' => 0,
            'failed_logins_24h' => 0,
            'blocked_ips' => [],
            'recent_attacks' => [],
            'alerts' => [],
            'firewall_active' => false,
            'fail2ban_active' => false,
            'last_attack_time' => null,
        ];

        if (PHP_OS_FAMILY !== 'Linux') {
            $security['alerts'][] = [
                'level' => 'info',
                'message' => 'Security monitoring hanya tersedia di Linux VPS',
                'time' => now()->toIso8601String(),
            ];
            return $security;
        }

        // 1. Parse auth.log for failed attempts
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

            // Count failures in last 1h and 24h
            foreach ($failedAttempts as $attempt) {
                $age = $now - $attempt['timestamp'];
                if ($age <= 3600) {
                    $security['failed_logins_1h']++;
                }
                if ($age <= 86400) {
                    $security['failed_logins_24h']++;
                }
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

            // Build recent attacks list (top attackers)
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

            // Set last attack time
            if (!empty($failedAttempts)) {
                $lastAttempt = end($failedAttempts);
                $security['last_attack_time'] = $lastAttempt['time_str'];
            }

            // Generate alerts for brute-force attacks
            foreach ($attackerIps as $ip => $info) {
                if ($info['count'] >= 10) {
                    $security['alerts'][] = [
                        'level' => 'critical',
                        'message' => "Brute-force terdeteksi dari {$ip} ({$info['count']}x percobaan via {$info['service']})",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];

                    // Auto-block if defender is active
                    if ($security['defender_active'] && $info['count'] >= 10) {
                        $this->autoBlockIp($ip);
                        $security['alerts'][] = [
                            'level' => 'success',
                            'message' => "Auto-Defender: IP {$ip} otomatis diblokir ({$info['count']}x gagal login)",
                            'time' => now()->toIso8601String(),
                            'ip' => $ip,
                        ];
                        // Mark as auto-blocked in recent attacks
                        foreach ($security['recent_attacks'] as &$attack) {
                            if ($attack['ip'] === $ip) {
                                $attack['auto_blocked'] = true;
                            }
                        }
                    }
                } elseif ($info['count'] >= 5) {
                    $security['alerts'][] = [
                        'level' => 'warning',
                        'message' => "Percobaan login mencurigakan dari {$ip} ({$info['count']}x via {$info['service']})",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];
                }
            }
        }

        // 1.5 Detect HTTP Flood / DDoS (Web Traffic)
        $this->detectHttpFlood($security);

        // 2. Check fail2ban status
        $fail2banStatus = @shell_exec('fail2ban-client status 2>/dev/null');
        if ($fail2banStatus) {
            $security['fail2ban_active'] = true;

            // Get banned IPs from sshd jail
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

        // 3. Check iptables blocked IPs (from our custom chain)
        $iptables = @shell_exec('iptables -L APEX-DEFENDER -n 2>/dev/null');
        if ($iptables) {
            $security['firewall_active'] = true;
            preg_match_all('/DROP\s+all\s+--\s+([\d.]+)/', $iptables, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $ip) {
                    // Avoid duplicates from fail2ban
                    $exists = false;
                    foreach ($security['blocked_ips'] as $blocked) {
                        if ($blocked['ip'] === $ip) {
                            $exists = true;
                            break;
                        }
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
            // Check if iptables is at least available
            $hasIptables = @shell_exec('which iptables 2>/dev/null');
            $security['firewall_active'] = !empty(trim($hasIptables ?? ''));
        }

        // 4. Determine threat level
        if ($security['failed_logins_1h'] >= 50 || count($security['recent_attacks']) >= 5) {
            $security['threat_level'] = 'critical';
        } elseif ($security['failed_logins_1h'] >= 20 || count($security['recent_attacks']) >= 3) {
            $security['threat_level'] = 'high';
        } elseif ($security['failed_logins_1h'] >= 5 || count($security['recent_attacks']) >= 1) {
            $security['threat_level'] = 'medium';
        }

        // 5. Add info alert if no threats detected
        if (empty($security['alerts'])) {
            $security['alerts'][] = [
                'level' => 'success',
                'message' => 'Tidak ada ancaman terdeteksi. Sistem aman.',
                'time' => now()->toIso8601String(),
            ];
        }

        return $security;
    }

    private function parseFailedAttempts(string $logPath): array
    {
        $attempts = [];
        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines)
            return [];

        // Only process last 2000 lines for performance
        $lines = array_slice($lines, -2000);
        $currentYear = date('Y');

        foreach ($lines as $line) {
            // Match SSH failed attempts
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
        }

        return $attempts;
    }

    private function autoBlockIp(string $ip): bool
    {
        // Don't block private/local IPs
        if ($this->isPrivateIp($ip))
            return false;

        // Check if already blocked (cached)
        $blockedKey = "defender_blocked_{$ip}";
        if (Cache::has($blockedKey))
            return false;

        // Try to create our custom chain if it doesn't exist
        @shell_exec('iptables -N APEX-DEFENDER 2>/dev/null');
        @shell_exec('iptables -I INPUT -j APEX-DEFENDER 2>/dev/null');

        // Block the IP
        $result = @shell_exec("iptables -A APEX-DEFENDER -s {$ip} -j DROP 2>&1");

        // Cache that we blocked this IP (24h TTL)
        Cache::put($blockedKey, true, 86400);

        // Log it
        \Illuminate\Support\Facades\Log::warning("APEX Defender: Auto-blocked IP {$ip}");

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

        // Check if already blocked
        $check = @shell_exec("iptables -C APEX-DEFENDER -s {$ip} -j DROP 2>&1");
        if (strpos($check, 'iptables: Bad rule') === false && empty($check)) {
            return response()->json(['message' => "IP {$ip} sudah diblokir"], 422);
        }

        @shell_exec("iptables -A APEX-DEFENDER -s {$ip} -j DROP 2>&1");
        Cache::put("defender_blocked_{$ip}", true, 86400);

        \Illuminate\Support\Facades\Log::warning("APEX Defender: Manual block IP {$ip} by user " . auth()->id());

        return response()->json(['success' => true, 'message' => "IP {$ip} berhasil diblokir"]);
    }

    public function unblockIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ip = $request->ip;

        @shell_exec("iptables -D APEX-DEFENDER -s {$ip} -j DROP 2>&1");
        Cache::forget("defender_blocked_{$ip}");

        // Also try to unban from fail2ban
        @shell_exec("fail2ban-client set sshd unbanip {$ip} 2>/dev/null");

        \Illuminate\Support\Facades\Log::info("APEX Defender: Unblocked IP {$ip} by user " . auth()->id());

        return response()->json(['success' => true, 'message' => "IP {$ip} berhasil di-unblokir"]);
    }

    public function toggleDefender(Request $request)
    {
        $current = Cache::get('security_defender_active', true);
        $newState = !$current;
        Cache::put('security_defender_active', $newState, 60 * 60 * 24 * 365); // 1 year

        $statusLabel = $newState ? 'AKTIF' : 'NONAKTIF';
        \Illuminate\Support\Facades\Log::info("APEX Defender: Status diubah ke {$statusLabel} oleh user " . auth()->id());

        return response()->json([
            'success' => true,
            'defender_active' => $newState,
            'message' => "Auto-Defender sekarang {$statusLabel}",
        ]);
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function detectHttpFlood(array &$security)
    {
        // Limit log lines to parse (tail)
        $maxLines = 2000;
        $scanWindow = 60; // scan last 60 seconds
        $threshold = 100; // block if > 100 reqs / minute (adjust as needed)

        // Find access logs
        $logPaths = array_merge(
            glob('/var/log/nginx/*access.log') ?: [],
            glob('/var/log/apache2/*access.log') ?: [],
            glob('/var/log/httpd/*access.log') ?: [],
            glob('/usr/local/lsws/logs/*access.log') ?: [], // OpenLiteSpeed
            glob('/usr/local/apache/logs/*access.log') ?: []
        );

        // Also check default paths if glob failed or returned nothing specific
        if (empty($logPaths)) {
            if (file_exists('/var/log/nginx/access.log'))
                $logPaths[] = '/var/log/nginx/access.log';
            if (file_exists('/var/log/apache2/access.log'))
                $logPaths[] = '/var/log/apache2/access.log';
            if (file_exists('/usr/local/lsws/logs/access.log'))
                $logPaths[] = '/usr/local/lsws/logs/access.log';
        }

        $ipCounts = [];
        $now = time();

        foreach ($logPaths as $path) {
            // Use tail to read only recent lines
            $cmd = "tail -n {$maxLines} " . escapeshellarg($path);
            $lines = explode("\n", shell_exec($cmd) ?? '');

            foreach ($lines as $line) {
                if (empty($line))
                    continue;

                // Nginx/Apache common log format: IP - - [Date] "Request" Status Bytes ...
                // Regex to extract IP and Date
                if (preg_match('/^(\S+) \- \S+ \[([^\]]+)\]/', $line, $m)) {
                    $ip = $m[1];
                    $dateStr = $m[2]; // e.g. 13/Feb/2026:15:40:19 +0700

                    // Parse date
                    // Nginx format: 13/Feb/2026:15:40:19 +0700
                    // We need to convert to something strtotime understands or use DateTime
                    $dateStrClean = preg_replace('/:/', ' ', $dateStr, 1); // Replace first colon with space -> 13/Feb/2026 15:40:19 +0700
                    $timestamp = strtotime($dateStrClean);

                    // Alternate parsing if strtotime fails
                    if (!$timestamp) {
                        try {
                            $dt = \DateTime::createFromFormat('d/M/Y:H:i:s O', $dateStr);
                            $timestamp = $dt ? $dt->getTimestamp() : 0;
                        } catch (\Exception $e) {
                            $timestamp = 0;
                        }
                    }

                    // Only count if within scan window (last 60s)
                    if ($timestamp && ($now - $timestamp) <= $scanWindow) {
                        if (!isset($ipCounts[$ip])) {
                            $ipCounts[$ip] = 0;
                        }
                        $ipCounts[$ip]++;
                    }
                }
            }
        }

        // Analyze counts
        foreach ($ipCounts as $ip => $count) {
            if ($count >= 50) { // Suspicious level
                $isAttack = $count >= $threshold;

                // Add to recent attacks list
                $security['recent_attacks'][] = [
                    'ip' => $ip,
                    'attempts' => $count,
                    'last_attempt' => date('Y-m-d H:i:s'),
                    'service' => 'HTTP/Web',
                    'auto_blocked' => false,
                ];

                // Determine threat level
                if ($isAttack) {
                    $alertMsg = "DDoS/HTTP Flood terdeteksi dari {$ip} ({$count} reqs/menit)";
                    $security['alerts'][] = [
                        'level' => 'critical',
                        'message' => $alertMsg,
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];
                    $security['threat_level'] = 'critical';

                    // Auto-block
                    if ($security['defender_active']) {
                        if ($this->autoBlockIp($ip)) {
                            $security['alerts'][] = [
                                'level' => 'success',
                                'message' => "Auto-Defender: Web Attacker {$ip} diblokir ({$count} reqs/menit)",
                                'time' => now()->toIso8601String(),
                                'ip' => $ip,
                            ];
                            // Mark as blocked in the list we just added
                            foreach ($security['recent_attacks'] as &$attack) {
                                if ($attack['ip'] === $ip) {
                                    $attack['auto_blocked'] = true;
                                }
                            }
                        }
                    }
                } else {
                    $security['alerts'][] = [
                        'level' => 'warning',
                        'message' => "Trafik tinggi mencurigakan dari {$ip} ({$count} reqs/menit)",
                        'time' => now()->toIso8601String(),
                        'ip' => $ip,
                    ];
                    if ($security['threat_level'] !== 'critical') {
                        $security['threat_level'] = 'high';
                    }
                }
            }
        }
    }
}
