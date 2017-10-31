<?php

namespace F3CMS;

class rPost extends Reaction
{
    public function get($folder)
    {
        $check = 0;

        try {
            sleep(1);
            echo $folder .' - v'.PHP_EOL;
        } catch (S3Exception $e) {
            echo $folder .'::'. $e->getExceptionCode() .''.PHP_EOL;
        }

        return $check;
    }
}
