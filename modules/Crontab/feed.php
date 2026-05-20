<?php

namespace F3CMS;

class fCrontab extends Feed
{
    const MTB    = 'crontab';
    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';
    const DEFAULT_DAILY_CHECK_OUTPUT_DIR = '/home/ubuntu/checkresult';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    public static function many($freq, $tally)
    {
        return mh()->select(self::fmTbl() . '(m)', '*', [
            'm.status' => self::ST_ON,
            'm.freq'   => $freq,
            'm.tally'  => $tally,
            'ORDER'    => ['id' => 'ASC'],
        ]);
    }

    public static function sendLatestDailyCheckLog(array $options = [])
    {
        $outputDir = rtrim((string) ($options['output_dir'] ?? self::dailyCheckOutputDirFromEnv()), '/');
        $recipient = trim((string) ($options['recipient'] ?? self::dailyCheckRecipientFromEnv()));

        if ($recipient === '') {
            $recipient = trim((string) f3()->get('webmaster'));
        }

        if ($recipient === '') {
            throw new \RuntimeException('No recipient specified. Set DAILY_CHECK_RECIPIENT or configure webmaster in F3 config.');
        }

        $latestLog = self::findLatestDailyCheckLogFile($outputDir);
        if ($latestLog === null) {
            throw new \RuntimeException('No daily security check log found in: ' . $outputDir);
        }

        $logBody = @file_get_contents($latestLog);
        if ($logBody === false) {
            throw new \RuntimeException('Unable to read latest log file: ' . $latestLog);
        }

        $subject = sprintf(
            '[%s] Daily Security Check %s',
            (string) f3()->get('site_title', 'F3CMS'),
            basename($latestLog)
        );

        f3()->set('daily_check_log_path', $latestLog);
        f3()->set('daily_check_log_body', nl2br(htmlspecialchars($logBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));

        $api = new MPThelper(f3()->get('mp.merchant'), f3()->get('mp.secret'));
        $result = $api->call('sendmail', [
            'subject' => $subject,
            'content' => Sender::renderBody('daily_security_check_latest'),
            'recipient' => $recipient,
        ]);

        if ($result === false || (is_string($result) && strpos($result, '[MediaPartner]') === 0)) {
            throw new \RuntimeException('Failed to send mail: ' . (is_string($result) ? $result : 'unknown error'));
        }

        return [
            'recipient' => $recipient,
            'log_path' => $latestLog,
            'result' => $result,
        ];
    }

    public static function dailyCheckOutputDirFromEnv()
    {
        $outputDir = getenv('DAILY_CHECK_OUTPUT_DIR');
        if ($outputDir === false || $outputDir === '') {
            return self::DEFAULT_DAILY_CHECK_OUTPUT_DIR;
        }

        return $outputDir;
    }

    public static function dailyCheckRecipientFromEnv()
    {
        $recipient = getenv('DAILY_CHECK_RECIPIENT');
        if ($recipient === false) {
            return '';
        }

        return $recipient;
    }

    private static function findLatestDailyCheckLogFile($outputDir)
    {
        if (!is_dir($outputDir)) {
            return null;
        }

        $matches = glob($outputDir . '/security_check_*.log');
        if ($matches === false || empty($matches)) {
            return null;
        }

        usort($matches, static function ($left, $right) {
            return filemtime($right) <=> filemtime($left);
        });

        return $matches[0] ?? null;
    }
}
