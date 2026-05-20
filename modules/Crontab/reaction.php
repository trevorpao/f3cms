<?php

namespace F3CMS;

class rCrontab extends Reaction
{
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

    public static function do_runDailySecurityCheck()
    {
        if (PHP_SAPI != 'cli') {
            exit('Only in cli mode');
        }

        f3()->get('cliLogger')->write('Info - Crontab::runDailySecurityCheck');
        exit(kCrontab::runDailySecurityCheckCli(kCrontab::dailySecurityCheckOptionsFromEnv()));
    }

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
