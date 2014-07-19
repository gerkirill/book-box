<?php

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;

// include the prod configuration
require __DIR__.'/prod.php';

require_once(__DIR__ . '/../lib/redbean/rb.php');
R::setup(
    'mysql:host=localhost;dbname=bookbox',
    'root', 'vagrant'
);

// enable the debug mode
$app['debug'] = true;

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../var/logs/silex_dev.log',
));

$app->register($p = new WebProfilerServiceProvider(), array(
    'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
));
$app->mount('/_profiler', $p);