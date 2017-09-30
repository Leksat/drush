<?php

namespace Drush\Preflight;

use Consolidation\AnnotatedCommand\Hooks\InitializeHookInterface;
use Drush\Drush;
use Symfony\Component\Console\Input\InputInterface;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Log\LogLevel;
use Webmozart\PathUtil\Path;

/**
 * RedispatchToSiteLocal forces an `exec` to the site-local Drush if it
 * exist.  We must do this super-early, before loading Drupal's autoload
 * file.  If we do not, we will crash unless the site-local Drush and the
 * global Drush are using the exact same versions of all dependencies, which
 * will rarely line up sufficiently to prevent problems.
 */
class RedispatchToSiteLocal
{
    public function __construct()
    {
    }

    public static function redispatchIfSiteLocalDrush($argv, $root, $vendor)
    {
        // Try to find the site-local Drush. If there is none, we are done.
        $siteLocalDrush = static::findSiteLocalDrush($root);
        if (!$siteLocalDrush) {
            return false;
        }

        // If the site-local Drush is us, then we do not need to redispatch.
        if (Path::isBasePath($vendor, $siteLocalDrush)) {
            return false;
        }
        // Do another special check to detect the SUT for Drush functional tests.
        if (dirname(realpath($vendor)) == dirname(realpath($siteLocalDrush))) {
            return false;
        }

        // Redispatch!
        $command = $siteLocalDrush;
        array_shift($argv);
        $args = array_map(
            function ($item) {
                return escapeshellarg($item);
            },
            $argv
        );
        $command .= ' ' . implode(' ', $args);
        passthru($command, $status);
        return $status;
    }

    protected static function findSiteLocalDrush($root)
    {
        $candidates = [
            "$root/vendor/drush/drush/drush",
            dirname($root) . '/vendor/drush/drush/drush',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
    }
}
