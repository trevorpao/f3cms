<?php

namespace F3CMS;

class Twig extends \Twig\Environment
{
    /**
     * This exists so template cache files use the same
     * group between apache and cli
     */
    protected function writeCacheFile($file, $content)
    {
        FSHelper::mkdir([$file]);

        parent::writeCacheFile($file, $content);

        chmod($file, 0775);
    }
}
