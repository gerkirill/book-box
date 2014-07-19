<?php

use Silex\Application;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Dropbox as dbx;

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider());
$app->register(new SessionServiceProvider());

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    // add custom globals, filters, tags, ...
    $twig->addGlobal('currentUser', $app['persi']->getCurrentUser());
    $twig->addFilter(new Twig_SimpleFilter('md5', 'md5'));
    return $twig;
}));

$app['dbox.auth'] = $app->share(function() {
    $appInfo = dbx\AppInfo::loadFromJsonFile(__DIR__ . '/../config/dropbox.json');
    $webAuth = new dbx\WebAuthNoRedirect($appInfo, "PHP-Example/1.0");
    return $webAuth;
});
$app['persi'] = $app->share(function() use ($app) {
    $persi = new Personalizer();
    $persi->setCurrentUserId($app['session']->get('user'));
    return $persi;
});
$app['user.dboxClient'] = $app->share(function($app) {
    $user = $app['persi']->getCurrentUser();
    if (!$user) {
        throw new \Exception('Can not create user.dboxClient service instance until user is logged in');
    }
    if (!$user->dbAccessToken) {
        throw new \Exception('User has not linked dropbox account yet');
    }
    return new dbx\Client($user->dbAccessToken, "PHP-Example/1.0");
});

$app['injectBasename'] = $app->protect(function($entries) {
    return array_map(function($entry) {
        $entry['basename'] = preg_replace('%^.*/%', '', $entry['path']);
        return $entry;
    }, $entries);
});

$app['tmpFileManager'] = $app->share(function() {
    return new TmpFileManager(__DIR__ . '/../var/cache');
});

return $app;
