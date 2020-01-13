<?php

use App\RomsFilter;

mb_internal_encoding('UTF-8');

require __DIR__ . '/vendor/autoload.php';

const WRONG_CONFIG_MESSAGE = 'Config is not correct.';

$config = require __DIR__ . '/config.php';

try {
    foreach (['regions_order', 'to_remove_patterns'] as $key) {
        if (
            !isset($config[$key])
            || !is_array($config[$key])
        ) {
            throw new Exception(WRONG_CONFIG_MESSAGE);
        }

        foreach ($config[$key] as $item) {
            if (!$item || !is_string($item)) {
                throw new Exception(WRONG_CONFIG_MESSAGE);
            }
        }
    }

    if (!isset($argv[1])) {
        throw new Exception('Directory path parameter not passed.');
    }

    $dirPath = $argv[1];

    if (!is_dir($dirPath)) {
        throw new Exception('Invalid directory path.');
    }

    $onlyInfo = true;

    if (isset($argv[2])) {
        if ($argv[2] != '-D') {
            throw new Exception("Unknown parameter: {$argv[2]}");
        }

        $onlyInfo = false;
    }

    if (!$onlyInfo && !is_writable($dirPath)) {
        throw new Exception('No permissions to change the contents of the directory.');
    }

    $filter = new RomsFilter(realpath($dirPath), $config['regions_order'], $config['to_remove_patterns'], $onlyInfo);

    $filter->handle();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
