<?php

namespace F3CMS;

class kCrontab extends Kit
{
    const DEFAULT_DAILY_SECURITY_OUTPUT_DIR = '/home/ubuntu/checkresult';

    public static function parseDailySecurityCheckArguments(array $argv): array
    {
        $result = [];
        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $result['help'] = true;
                continue;
            }

            if (strncmp($arg, '--', 2) === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $result[$parts[0]] = $parts[1] ?? true;
            }
        }

        return $result;
    }

    public static function dailySecurityCheckUsage(string $scriptName): string
    {
        return <<<TXT
Usage:
  php bin/{$scriptName} [--project-root=/path/to/repo] [--output-dir=/home/ubuntu/checkresult]

Options:
  --project-root   Repository root. Default: parent of /bin
  --output-dir     Directory for report files. Default: /home/ubuntu/checkresult
  --dry-run        Show which checks would run and print the report to STDOUT without writing files
  --only=1,4,7     Run only the specified check IDs
  --disk-min-gb    Warning threshold for free disk space in GB. Default: 10
  --disk-min-pct   Warning threshold for free disk percentage. Default: 15
  --mem-warn-pct   Warning threshold for memory usage percentage. Default: 85
  --cpu-warn-pct   Warning threshold for CPU usage percentage. Default: 85
  --help           Show this help
TXT;
    }

    public static function dailySecurityCheckOptionsFromEnv(): array
    {
        $map = [
            'project-root' => 'DAILY_SECURITY_CHECK_PROJECT_ROOT',
            'output-dir' => 'DAILY_SECURITY_CHECK_OUTPUT_DIR',
            'only' => 'DAILY_SECURITY_CHECK_ONLY',
            'disk-min-gb' => 'DAILY_SECURITY_CHECK_DISK_MIN_GB',
            'disk-min-pct' => 'DAILY_SECURITY_CHECK_DISK_MIN_PCT',
            'mem-warn-pct' => 'DAILY_SECURITY_CHECK_MEM_WARN_PCT',
            'cpu-warn-pct' => 'DAILY_SECURITY_CHECK_CPU_WARN_PCT',
        ];

        $options = [];
        foreach ($map as $optionKey => $envKey) {
            $value = getenv($envKey);
            if ($value !== false && $value !== '') {
                $options[$optionKey] = $value;
            }
        }

        $dryRun = getenv('DAILY_SECURITY_CHECK_DRY_RUN');
        if ($dryRun !== false && $dryRun !== '' && !in_array(strtolower((string) $dryRun), ['0', 'false', 'no'], true)) {
            $options['dry-run'] = true;
        }

        return $options;
    }

    public static function runDailySecurityCheckCli(array $options = []): int
    {
        date_default_timezone_set('Asia/Taipei');

        $projectRoot = isset($options['project-root']) ? rtrim((string) $options['project-root'], '/') : dirname(__DIR__, 4);
        $outputDir = isset($options['output-dir']) ? rtrim((string) $options['output-dir'], '/') : self::DEFAULT_DAILY_SECURITY_OUTPUT_DIR;
        $now = new \DateTimeImmutable('now');
        $timestamp = $now->format('Ymd_His');
        $execution = [
            'dryRun' => !empty($options['dry-run']),
            'only' => self::parseOnlyOption((string) ($options['only'] ?? '')),
        ];

        $paths = [
            'projectRoot' => $projectRoot,
            'publicRoot' => $projectRoot . '/www',
            'appRoot' => $projectRoot . '/www/f3cms',
            'uploadImgRoot' => $projectRoot . '/www/f3cms/upload/img',
            'nginxConfRoot' => $projectRoot . '/conf/nginx',
            'phpConfRoot' => $projectRoot . '/conf/php',
            'reportDir' => $outputDir,
            'reportTxt' => $outputDir . '/security_check_' . $timestamp . '.log',
            'reportJson' => $outputDir . '/security_check_' . $timestamp . '.json',
        ];

        self::ensureDirectory($paths['reportDir']);

        $settings = self::buildDailySecurityCheckSettings($options);

        $report = [
            'generatedAt' => $now->format(\DateTimeInterface::ATOM),
            'projectRoot' => $projectRoot,
            'outputDir' => $outputDir,
            'dryRun' => $execution['dryRun'],
            'selectedChecks' => array_values($execution['only']),
            'checks' => [],
            'summary' => [
                'ok' => 0,
                'warn' => 0,
                'error' => 0,
                'info' => 0,
            ],
        ];

        self::runSelectedCheck($report, $execution, '1', '異常檔名', static function () use ($paths) {
            return self::checkSuspiciousFilenames($paths);
        });
        self::runSelectedCheck($report, $execution, '2', '異常註冊帳號', static function () {
            return self::checkSuspiciousAccounts();
        });
        self::runSelectedCheck($report, $execution, '3', '頁面遭插入文字', static function () {
            return self::checkTamperedContentMarkers();
        });
        self::runSelectedCheck($report, $execution, '4', '異常微型圖片', static function () use ($paths) {
            return self::checkTinyRecentImages($paths);
        });
        self::runSelectedCheck($report, $execution, '5', '一次性或異常信箱', static function () {
            return self::checkDisposableEmails();
        });
        self::runSelectedCheck($report, $execution, '6', '異常程式與持久化', static function () use ($paths) {
            return self::checkPersistenceAndProcesses($paths);
        });
        self::runSelectedCheck($report, $execution, '7', 'XSS 指標', static function () {
            return self::checkXssIndicators();
        });
        self::runSelectedCheck($report, $execution, '8', '網站組態檔檢查', static function () use ($paths) {
            return self::checkConfigDrift($paths);
        });
        self::runSelectedCheck($report, $execution, '9', '網頁目錄外洩檢查', static function () use ($paths) {
            return self::checkDirectoryExposure($paths);
        });
        self::runSelectedCheck($report, $execution, '10', '硬碟可用空間', static function () use ($settings, $paths) {
            return self::checkDiskAvailability($paths['projectRoot'], $settings);
        });
        self::runSelectedCheck($report, $execution, '11', '記憶體用量', static function () use ($settings) {
            return self::checkMemoryUsage($settings);
        });
        self::runSelectedCheck($report, $execution, '12', 'CPU 用量', static function () use ($settings) {
            return self::checkCpuUsage($settings);
        });

        $textReport = self::renderTextReport($report);
        $jsonReport = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

        if ($execution['dryRun']) {
            fwrite(STDOUT, $textReport);
            fwrite(STDOUT, "Dry-run mode enabled: report files were not written.\n");
            return 0;
        }

        file_put_contents($paths['reportTxt'], $textReport);
        file_put_contents($paths['reportJson'], $jsonReport);

        fwrite(STDOUT, "Report written:\n");
        fwrite(STDOUT, '- ' . $paths['reportTxt'] . "\n");
        fwrite(STDOUT, '- ' . $paths['reportJson'] . "\n");

        if ($report['summary']['error'] > 0 || $report['summary']['warn'] > 0) {
            return 2;
        }

        return 0;
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create report directory: ' . $path);
        }
    }

    private static function buildDailySecurityCheckSettings(array $options): array
    {
        return [
            'diskMinGb' => (float) ($options['disk-min-gb'] ?? 10),
            'diskMinPct' => (float) ($options['disk-min-pct'] ?? 15),
            'memWarnPct' => (float) ($options['mem-warn-pct'] ?? 85),
            'cpuWarnPct' => (float) ($options['cpu-warn-pct'] ?? 85),
        ];
    }

    private static function parseOnlyOption(string $only): array
    {
        if ($only === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $only)), static function ($value) {
            return $value !== '';
        });

        return array_values(array_unique($parts));
    }

    private static function addCheck(array &$report, array $check): void
    {
        $report['checks'][] = $check;
        if (!isset($report['summary'][$check['status']])) {
            $report['summary'][$check['status']] = 0;
        }
        $report['summary'][$check['status']]++;
    }

    private static function runSelectedCheck(array &$report, array $execution, string $id, string $title, callable $runner): void
    {
        if (!empty($execution['only']) && !in_array($id, $execution['only'], true)) {
            return;
        }

        if (!empty($execution['dryRun'])) {
            self::addCheck($report, self::makeCheck($id, $title, 'info', 'Dry-run: check execution skipped.'));
            return;
        }

        self::addCheck($report, $runner());
    }

    private static function makeCheck(string $id, string $title, string $status, string $summary, array $details = []): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    private static function checkSuspiciousFilenames(array $paths): array
    {
        $pattern = '/nics115pt|nics\d+|56903|5692733992203/i';
        $matches = FSHelper::findFilesByBasenamePattern($paths['publicRoot'], $pattern, $paths['projectRoot']);

        if (empty($matches)) {
            return self::makeCheck('1', '異常檔名', 'ok', 'No suspicious filenames found under public directories.');
        }

        return self::makeCheck('1', '異常檔名', 'warn', 'Suspicious filenames found under public directories.', $matches);
    }

    private static function checkSuspiciousAccounts(): array
    {
        $details = [];
        foreach (fMember::suspiciousAccounts('nics|56903') as $row) {
            $details[] = sprintf('tbl_member#%s %s', $row['id'], $row['account']);
        }
        foreach (fStaff::suspiciousAccounts('nics|56903') as $row) {
            $details[] = sprintf('tbl_staff#%s %s', $row['id'], $row['account']);
        }

        if (empty($details)) {
            return self::makeCheck('2', '異常註冊帳號', 'ok', 'No suspicious account names found in tbl_member/tbl_staff.');
        }

        return self::makeCheck('2', '異常註冊帳號', 'warn', 'Suspicious account names found.', $details);
    }

    private static function checkTamperedContentMarkers(): array
    {
        $details = [];
        foreach (fPost::suspiciousContentMarkers('nics115pt(_xss)?56903|nics56903|5692733992203') as $row) {
            $details[] = sprintf('tbl_post_lang#%s parent:%s %s', $row['id'], $row['parent_id'], self::compactSnippet($row['snippet']));
        }
        foreach (fPress::suspiciousContentMarkers('nics115pt(_xss)?56903|nics56903|5692733992203') as $row) {
            $details[] = sprintf('tbl_press_lang#%s parent:%s %s', $row['id'], $row['parent_id'], self::compactSnippet($row['snippet']));
        }

        if (empty($details)) {
            return self::makeCheck('3', '頁面遭插入文字', 'ok', 'No known marker strings found in post/press content.');
        }

        return self::makeCheck('3', '頁面遭插入文字', 'warn', 'Known marker strings found in content tables.', $details);
    }

    private static function checkTinyRecentImages(array $paths): array
    {
        $root = $paths['uploadImgRoot'];
        if (!is_dir($root)) {
            return self::makeCheck('4', '異常微型圖片', 'info', 'Upload image directory not found: ' . $root);
        }

        $details = [];
        foreach (FSHelper::findRecentSmallFiles($root, 86400, 15 * 1024, $paths['projectRoot']) as $match) {
            $details[] = sprintf(
                '%s size=%d mtime=%s',
                $match['path'],
                $match['size'],
                date('Y-m-d H:i:s', $match['mtime'])
            );
        }

        if (empty($details)) {
            return self::makeCheck('4', '異常微型圖片', 'ok', 'No images under 15KB modified in the last 24 hours.');
        }

        return self::makeCheck('4', '異常微型圖片', 'warn', 'Recent tiny images found under upload/img.', $details);
    }

    private static function checkDisposableEmails(): array
    {
        $domains = ['hidingmail.net', 'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.plus', 'yopmail.com'];
        $pattern = implode('|', array_map(static function ($domain) {
            return preg_quote('@' . $domain, '/');
        }, $domains));

        $details = [];
        foreach (fMember::suspiciousEmails($pattern) as $row) {
            $details[] = sprintf('tbl_member#%s %s', $row['id'], $row['email']);
        }
        foreach (fStaff::suspiciousEmails($pattern) as $row) {
            $details[] = sprintf('tbl_staff#%s %s', $row['id'], $row['email']);
        }

        if (empty($details)) {
            return self::makeCheck('5', '一次性或異常信箱', 'ok', 'No disposable email domains found in member/staff records.');
        }

        return self::makeCheck('5', '一次性或異常信箱', 'warn', 'Disposable or suspicious email domains found.', $details);
    }

    private static function checkPersistenceAndProcesses(array $paths): array
    {
        $details = [];
        $commandPattern = '/wget|curl|nc\s|bash -i|python -c|perl -e|php -r|base64_decode|eval\(|shell_exec|passthru|proc_open/i';

        $processOutput = self::runShell('ps axww -o pid=,command=');
        foreach (preg_split('/\R/', $processOutput) as $line) {
            $line = trim((string) $line);
            if ($line !== '' && preg_match($commandPattern, $line)) {
                $details[] = 'process: ' . $line;
            }
        }

        $crontabOutput = self::runShell('crontab -l 2>/dev/null');
        foreach (preg_split('/\R/', $crontabOutput) as $line) {
            if ($line !== '' && preg_match($commandPattern, $line)) {
                $details[] = 'crontab: ' . trim((string) $line);
            }
        }

        foreach (['/etc/crontab', '/etc/cron.d'] as $cronPath) {
            if (is_file($cronPath)) {
                foreach (file($cronPath, FILE_IGNORE_NEW_LINES) as $line) {
                    if ($line !== '' && preg_match($commandPattern, $line)) {
                        $details[] = $cronPath . ': ' . trim((string) $line);
                    }
                }
            }
        }

        foreach (FSHelper::findRecentFilesContainingPattern($paths['publicRoot'], ['php', 'phtml', 'php5', 'js', 'sh'], 86400, $commandPattern, $paths['projectRoot']) as $path) {
            $details[] = 'file: ' . $path;
        }

        if (empty($details)) {
            return self::makeCheck('6', '異常程式與持久化', 'ok', 'No suspicious processes, cron jobs, or recent webshell-like files found.');
        }

        return self::makeCheck('6', '異常程式與持久化', 'warn', 'Suspicious process, cron, or recent executable file indicators found.', array_values(array_unique($details)));
    }

    private static function checkXssIndicators(): array
    {
        $details = [];
        $pattern = '<script|javascript:|onerror=|onload=|<iframe|srcdoc=|document.cookie|alert\s*\(';
        foreach (fPost::suspiciousXssIndicators($pattern) as $row) {
            $details[] = sprintf('tbl_post_lang#%s parent:%s %s', $row['id'], $row['parent_id'], self::compactSnippet($row['snippet']));
        }
        foreach (fPress::suspiciousXssIndicators($pattern) as $row) {
            $details[] = sprintf('tbl_press_lang#%s parent:%s %s', $row['id'], $row['parent_id'], self::compactSnippet($row['snippet']));
        }

        if (empty($details)) {
            return self::makeCheck('7', 'XSS 指標', 'ok', 'No obvious XSS indicator strings found in post/press content.');
        }

        return self::makeCheck('7', 'XSS 指標', 'warn', 'Potential XSS indicator strings found in content tables.', $details);
    }

    private static function checkConfigDrift(array $paths): array
    {
        $details = [];
        $monitoredFiles = [
            $paths['phpConfRoot'] . '/php-ini-overrides.ini',
            $paths['phpConfRoot'] . '/www.conf',
            $paths['nginxConfRoot'] . '/nginx.conf',
            $paths['nginxConfRoot'] . '/sites/default.conf',
            $paths['appRoot'] . '/robots.txt',
        ];

        $inspection = FSHelper::inspectMonitoredFiles($monitoredFiles, 86400, $paths['projectRoot']);
        foreach ($inspection['missing'] as $path) {
            $details[] = 'missing monitored file: ' . $path;
        }
        foreach ($inspection['recentlyModified'] as $path) {
            $details[] = 'modified within 24h: ' . $path;
        }

        $phpOverride = $paths['phpConfRoot'] . '/php-ini-overrides.ini';
        foreach (FSHelper::findLiteralStringsInFiles([$phpOverride], ['display_errors = On', 'allow_url_include = On', 'expose_php = On'], $paths['projectRoot']) as $match) {
            $details[] = 'risky php setting detected: ' . $match['needle'];
        }

        if (empty($details)) {
            return self::makeCheck('8', '網站組態檔檢查', 'ok', 'No config drift or risky monitored changes detected.');
        }

        return self::makeCheck('8', '網站組態檔檢查', 'warn', 'Config drift or risky settings detected.', $details);
    }

    private static function checkDirectoryExposure(array $paths): array
    {
        $details = [];

        foreach (FSHelper::findFilesContainingPattern([$paths['nginxConfRoot'] . '/nginx.conf', $paths['nginxConfRoot'] . '/sites/default.conf'], '/autoindex\s+on\s*;/i', $paths['projectRoot']) as $file) {
            $details[] = 'autoindex on: ' . $file;
        }

        $targets = [];
        foreach (['.env', '.git', '.svn', '.DS_Store', 'composer.lock', 'package-lock.json'] as $name) {
            $targets[] = $paths['appRoot'] . '/' . $name;
        }
        foreach (FSHelper::findExistingPaths($targets, $paths['projectRoot']) as $path) {
            $details[] = 'public sensitive artifact: ' . $path;
        }

        $artifactPattern = '/\.(bak|old|orig|save|swp|dist|zip|tar|gz|sql)$/i';
        foreach (FSHelper::findFilesByFilenamePattern($paths['appRoot'], $artifactPattern, $paths['projectRoot']) as $path) {
            $details[] = 'public artifact: ' . $path;
        }

        if (empty($details)) {
            return self::makeCheck('9', '網頁目錄外洩檢查', 'ok', 'No obvious directory exposure or public artifact issues found.');
        }

        return self::makeCheck('9', '網頁目錄外洩檢查', 'warn', 'Potential public exposure indicators found.', array_values(array_unique($details)));
    }

    private static function checkDiskAvailability(string $path, array $settings): array
    {
        $freeBytes = @disk_free_space($path);
        $totalBytes = @disk_total_space($path);

        if ($freeBytes === false || $totalBytes === false || $totalBytes <= 0) {
            return self::makeCheck('10', '硬碟可用空間', 'error', 'Unable to read disk usage metrics.');
        }

        $freeGb = round($freeBytes / 1024 / 1024 / 1024, 2);
        $freePct = round(($freeBytes / $totalBytes) * 100, 2);
        $summary = sprintf('Free disk space: %.2f GB (%.2f%% free).', $freeGb, $freePct);

        if ($freeGb < $settings['diskMinGb'] || $freePct < $settings['diskMinPct']) {
            return self::makeCheck('10', '硬碟可用空間', 'warn', $summary, [
                sprintf('Thresholds: min %.2f GB or %.2f%% free.', $settings['diskMinGb'], $settings['diskMinPct']),
            ]);
        }

        return self::makeCheck('10', '硬碟可用空間', 'ok', $summary);
    }

    private static function checkMemoryUsage(array $settings): array
    {
        $memInfoPath = '/proc/meminfo';
        if (!is_readable($memInfoPath)) {
            return self::makeCheck('11', '記憶體用量', 'info', 'Memory metrics unavailable on this host.');
        }

        $memInfo = self::parseMemInfo(file($memInfoPath, FILE_IGNORE_NEW_LINES) ?: []);
        if (!isset($memInfo['MemTotal'])) {
            return self::makeCheck('11', '記憶體用量', 'error', 'Unable to parse /proc/meminfo.');
        }

        $total = (float) $memInfo['MemTotal'];
        $available = isset($memInfo['MemAvailable']) ? (float) $memInfo['MemAvailable'] : max(0.0, $total - ((float) ($memInfo['MemFree'] ?? 0)));
        $used = max(0.0, $total - $available);
        $usedPct = round(($used / $total) * 100, 2);
        $summary = sprintf('Memory usage: %.2f%% used (%.2f GB / %.2f GB).', $usedPct, $used / 1024 / 1024, $total / 1024 / 1024);

        if ($usedPct >= $settings['memWarnPct']) {
            return self::makeCheck('11', '記憶體用量', 'warn', $summary, [
                sprintf('Threshold: %.2f%% used.', $settings['memWarnPct']),
            ]);
        }

        return self::makeCheck('11', '記憶體用量', 'ok', $summary);
    }

    private static function checkCpuUsage(array $settings): array
    {
        $first = self::readCpuSample();
        if ($first === null) {
            return self::makeCheck('12', 'CPU 用量', 'info', 'CPU metrics unavailable on this host.');
        }

        usleep(200000);
        $second = self::readCpuSample();
        if ($second === null) {
            return self::makeCheck('12', 'CPU 用量', 'error', 'Unable to capture CPU usage sample.');
        }

        $idle = $second['idle'] - $first['idle'];
        $total = $second['total'] - $first['total'];
        if ($total <= 0) {
            return self::makeCheck('12', 'CPU 用量', 'error', 'CPU counters did not advance as expected.');
        }

        $usagePct = round((1 - ($idle / $total)) * 100, 2);
        $summary = sprintf('CPU usage: %.2f%% busy.', $usagePct);

        if ($usagePct >= $settings['cpuWarnPct']) {
            return self::makeCheck('12', 'CPU 用量', 'warn', $summary, [
                sprintf('Threshold: %.2f%% busy.', $settings['cpuWarnPct']),
            ]);
        }

        return self::makeCheck('12', 'CPU 用量', 'ok', $summary);
    }

    private static function renderTextReport(array $report): string
    {
        $lines = [];
        $lines[] = str_repeat('=', 72);
        $lines[] = 'Daily Security Check Report';
        $lines[] = 'Generated At: ' . $report['generatedAt'];
        $lines[] = 'Project Root: ' . $report['projectRoot'];
        $lines[] = 'Dry Run: ' . ($report['dryRun'] ? 'yes' : 'no');
        if (!empty($report['selectedChecks'])) {
            $lines[] = 'Selected Checks: ' . implode(',', $report['selectedChecks']);
        }
        $lines[] = str_repeat('=', 72);
        $lines[] = sprintf(
            'Summary: ok=%d warn=%d error=%d info=%d',
            $report['summary']['ok'],
            $report['summary']['warn'],
            $report['summary']['error'],
            $report['summary']['info']
        );

        foreach ($report['checks'] as $check) {
            $lines[] = '';
            $lines[] = sprintf('[%s] %s. %s', strtoupper($check['status']), $check['id'], $check['title']);
            $lines[] = $check['summary'];
            foreach ($check['details'] as $detail) {
                $lines[] = '  - ' . $detail;
            }
        }

        $lines[] = '';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function compactSnippet(string $snippet): string
    {
        $snippet = preg_replace('/\s+/', ' ', $snippet);
        return trim((string) $snippet);
    }

    private static function runShell(string $command): string
    {
        $output = @shell_exec($command);
        return $output === null ? '' : (string) $output;
    }

    private static function parseMemInfo(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z_]+):\s+(\d+)\s+kB$/', $line, $matches)) {
                $result[$matches[1]] = (int) $matches[2];
            }
        }

        return $result;
    }

    private static function readCpuSample(): ?array
    {
        $statPath = '/proc/stat';
        if (!is_readable($statPath)) {
            return null;
        }

        $line = strtok((string) file_get_contents($statPath), PHP_EOL);
        if (!is_string($line) || strpos($line, 'cpu ') !== 0) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($line));
        if ($parts === false || count($parts) < 5) {
            return null;
        }

        array_shift($parts);
        $values = array_map('intval', $parts);
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        $total = array_sum($values);

        return [
            'idle' => $idle,
            'total' => $total,
        ];
    }
}