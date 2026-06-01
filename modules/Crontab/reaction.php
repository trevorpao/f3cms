<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Reaction boundary for scheduler and cron entry orchestration, covering
 *   CLI-only job dispatch, daily security tasks, campaign worker triggering, and
 *   bearer-protected remote execution of registered cron jobs.
 *
 * Design Intent
 * - Keep scheduler entry concerns close to the Crontab module while preserving the FORKS
 *   split: this Reaction owns gateway/auth/dispatch flow, registered job truth stays in
 *   fCrontab, and worker/task business logic remains in the owning module collaborators.
 *
 * Strict Constraints
 * - This Reaction must not absorb job implementation logic, campaign business flow, or
 *   daily-check generation internals.
 * - Reflection-style dispatch may be coordinated here, but module-owned jobs must remain
 *   owned by their target modules and helpers.
 *
 * Porting Guidelines
 * - Port as the scheduler/application entry layer for cron and protected remote job
 *   execution.
 * - Preserve the split between gateway/auth/dispatch here, persisted job definitions in
 *   Crontab Feed, and actual job behavior in the owning modules.
 */
class rCrontab extends Reaction
{
    /**
     * Architecture Role
     * - Execute the CLI scheduler loop by loading due cron definitions and spawning the
     *   corresponding worker instances.
     *
     * Strict Constraints
     * - This method is a scheduler gateway only; it must not absorb the business logic
     *   of the jobs it dispatches.
     *
     * Porting Note
     * - Port as a CLI-only cron-runner entry that fans out to worker processes.
     */
    public static function do_job()
    {
        if (PHP_SAPI != 'cli') {
            exit('Only in cli mode');
        }

        $freq  = f3()->get('PARAMS.freq');
        $tally = f3()->get('PARAMS.tally');

        $worker = [];

        $data = fCrontab::many($freq, $tally);

        f3()->get('cliLogger')->write('Info - ' . $freq . '::' . $tally);

        if (count($data) > 0) {
            foreach ($data as $k => $loopJob) {
                if (!empty($loopJob['module'])) {
                    f3()->get('cliLogger')->write('Info - ' . $loopJob['module'] . '::' . $loopJob['method']);
                    $worker[$k] = new Worker($loopJob['module'], $loopJob['method']);
                    $worker[$k]->startWorker();
                }
            }
        }
    }

    /**
     * Architecture Role
     * - Trigger the daily security check pipeline from the CLI scheduler boundary.
     *
     * Strict Constraints
     * - The Reaction coordinates entry and exit only; security-check option resolution
     *   and execution remain owned by the Crontab collaborator.
     *
     * Porting Note
     * - Port as a CLI-only daily security check entrypoint.
     */
    public static function do_runDailySecurityCheck()
    {
        if (PHP_SAPI != 'cli') {
            exit('Only in cli mode');
        }

        f3()->get('cliLogger')->write('Info - Crontab::runDailySecurityCheck');
        exit(kCrontab::runDailySecurityCheckCli(kCrontab::dailySecurityCheckOptionsFromEnv()));
    }

    /**
     * Architecture Role
     * - Send the most recent daily security check report from the CLI scheduler boundary.
     *
     * Strict Constraints
     * - Mailing/report lookup orchestration may be coordinated here, but log selection
     *   and mail delivery semantics remain with the Crontab collaborators.
     *
     * Porting Note
     * - Port as a CLI-only mail-delivery entry for the latest security report.
     */
    public static function do_sendLatestDailyCheck()
    {
        if (PHP_SAPI != 'cli') {
            exit('Only in cli mode');
        }

        f3()->get('cliLogger')->write('Info - Crontab::sendLatestDailyCheck');

        try {
            $result = fCrontab::sendLatestDailyCheckLog();
            $message = sprintf(
                'Mail sent successfully to %s using log %s',
                $result['recipient'],
                $result['log_path']
            );

            f3()->get('cliLogger')->write('Info - ' . $message);
            echo $message . PHP_EOL;
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
            f3()->get('cliLogger')->write('Error - ' . $message);
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
    }

    /**
     * Architecture Role
     * - Start the Campaign pending-log worker from the CLI scheduler boundary.
     *
     * Strict Constraints
     * - This method chooses worker limit and delegates execution, but it must not absorb
     *   campaign processing semantics itself.
     *
     * Porting Note
     * - Port as a CLI-only campaign-worker trigger over the Campaign collaborator.
     */
    public static function do_runCampaignWorker()
    {
        if (PHP_SAPI != 'cli') {
            exit('Only in cli mode');
        }

        $limit = (int) getenv('CAMPAIGN_WORKER_LIMIT');
        if ($limit <= 0) {
            $limit = kCampaign::DEFAULT_WORKER_LIMIT;
        }

        f3()->get('cliLogger')->write('Info - Crontab::runCampaignWorker limit=' . $limit);
        exit(kCampaign::consumePendingLogsCli($limit));
    }

    /**
     * Architecture Role
     * - Execute registered cron definitions through the bearer-protected remote/webhook
     *   entrypoint.
     *
     * Strict Constraints
     * - This Reaction authenticates and dispatches remote cron execution, but registered
     *   job truth stays in fCrontab and job implementation remains with target modules.
     *
     * Porting Note
     * - Port as a protected remote cron-dispatch endpoint with explicit bearer gating.
     */
    public static function do_exec($f3, $args)
    {
        if (empty($args['tally'])) {
            return self::_return(8004);
        }

        if (empty($args['freq'])) {
            return self::_return(8004);
        }

        if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return self::_return(8004);
        }

        // if the Authorization is not equal to the secret key, then it is invalid
        $authorization = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        if ($authorization !== f3()->get('webhook.secret')) {
            return self::_return(8201, ['msg' => 'Bearer authorization Failed']);
        }

        $data   = fCrontab::many($args['freq'], $args['tally']);
        $result = [];

        if (!empty($data)) {
            foreach ($data as $k => $loopJob) {
                if (!empty($loopJob['module'])) {
                    $tmp = date('Y-m-d H:i:s') . ' Exec - ' . $loopJob['module'] . '::' . $loopJob['method'];
                    if (method_exists($loopJob['module'], $loopJob['method'])) {
                        call_user_func($loopJob['module'] . '::' . $loopJob['method']);
                        $tmp .= ' ==> Done';
                    } else {
                        $tmp .= ' ==> Missed';
                    }

                    $result[] = $tmp;
                }
            }
        }

        return self::_return(1, $result);
    }
}
