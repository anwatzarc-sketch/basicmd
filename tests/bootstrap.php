<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader (which includes Aster\Tests\ via
 * autoload-dev) and nothing else - environment loading and container
 * bootstrapping happen per-test-case in DatabaseTestCase, not globally here,
 * so a plain unit test that touches no infrastructure never pays for a DB
 * connection.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
