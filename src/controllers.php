<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Dropbox as dbx;

// link dropbox account
$app->get('/', function () use ($app) {
    $user = $app['persi']->getCurrentUser();
    if (!$user->dbAccessToken) {
        return new RedirectResponse('/link');
    } elseif (!$user->booksFolder) {
        return new RedirectResponse('/choose');
    } else {
        return new RedirectResponse('/done');
    }
})
->bind('homepage');

$app->get('/link', function() use ($app) {
    $authorizeUrl = $app['dbox.auth']->start();
    return $app['twig']->render('index.html', array('authUrl' => $authorizeUrl));
});

// select OPDS-published folder
$app->get('/choose/{path}', function ($path) use ($app) {
    try {
        $folderMetadata = $app['user.dboxClient']->getMetadataWithChildren("/" . $path);
    } catch (\Exception $e) {
        return new RedirectResponse('/');
    }
    return $app['twig']->render( 'account.html', [
        'entries' => $app['injectBasename']($folderMetadata['contents']),
        'path' => $path
    ]);
})
->value('path', '')
->assert('path', '.*');

$app->get('/done', function() use ($app) {
    $user = $app['persi']->getCurrentUser();
    if (!$user->booksFolder) {
        return new RedirectResponse('/');
    }
    return $app['twig']->render('done.html', []);
});

// finish dropbox auth process
$app->post('/code', function (Request $request) use ($app) {
    $authCode = trim($request->get('code'));
    list($accessToken, $dropboxUserId) = $app['dbox.auth']->finish($authCode);

    $user = $app['persi']->getCurrentUser();
    $user->dbAccessToken = $accessToken;
    $user->dbUserId = $dropboxUserId;
    R::store($user);

    return new RedirectResponse('/');
});

// list books in a subpath of OPDS-published folder
$app->get('/opds/{path}', function($path, Request $request) use ($app) {
    $user = $app['persi']->getCurrentUser();
    // connect to dropbox and list contents
    $folderMetadata = $app['user.dboxClient']->getMetadataWithChildren($user->booksFolder . ($path ? "/$path" : ''));
    return $app['twig']->render('opds-file-list.xml', ['entries' => $app['injectBasename']($folderMetadata['contents'])]);
})
->value('path', '')
->assert('path', '.*')
->bind('opds');

$app->get('/opds-download/{path}', function($path, Request $request) use ($app) {
    $user  = $app['persi']->getCurrentUser();
    $path = '/' . $path;
    if (0 !== strpos($path, $user->booksFolder)) {
        throw new \Exception('This file is out of the OPDS-shared folder');
    }
    list($tmpDownloadUrl, $expires) = $app['user.dboxClient']->createTemporaryDirectLink($path);
    return new RedirectResponse($tmpDownloadUrl);
//    list($cachedFilePath, $cachedFileHandle) = $app['tmpFileManager']->createFile('downloads/');
//    $fileMetadata = $app['user.dboxClient']->getFile($path, $cachedFileHandle);
//    fclose($cachedFileHandle);
//    return $app->sendFile($cachedFilePath);
})
->assert('path', '.+')
->bind('opds-download');

// display context menu at the folder chooser
$app->get('/folder-menu/{path}', function($path) use ($app) {
    return $app['twig']->render( 'folder-menu.html', [
        'path' => $path,
        'parentPath' => preg_replace('%/?[^/]*$%', '', $path)
    ] );
})
->value('path', '')
->assert('path', '.*');

// currently selected folder to share with opds
$app->get('/selected-folder', function() use ($app) {
    $user = $app['persi']->getCurrentUser();
    return $app['twig']->render( 'selected-folder.html', [
        'folder' => $user->booksFolder
    ] );
});

// update OPDS-shared folder
$app->get('/select-folder/{path}', function($path ) use ($app) {
    $user = $app['persi']->getCurrentUser();
    $user->booksFolder = '/' . $path;
    R::store($user);
    return new RedirectResponse( '/' );
})
->value('path', '')
->assert('path', '.*');

$app->match('/login', function(Request $request) use ($app) {
    $data = [];
    if ('POST' == $request->getMethod()) {
        $user = $app['persi']->getUserByCredentials($request->get('login'), $request->get('password'));
        if ($user) {
            $app['session']->set('user', $user->id);
            return new RedirectResponse('/');
        }
        $data['login'] = $request->get('login');
        $data['errors']['credentials'] = true;
    }
    return $app['twig']->render('login.html', $data);
})
->bind('login');

$app->match('/register', function(Request $request) use ($app) {
    $login = trim($request->get('login'));
    $data = ['login' => $login, 'password' => $request->get('password')];
    if ('POST' == $request->getMethod()) {
        if ('' == $login) {
            $data['errors']['login']['empty'] = true;
        } elseif (R::findOne( 'user', ' email = ? ', [$login] )) {
            $data['errors']['login']['unique'] = true;
        }
        if('' == $request->get('password')) {
            $data['errors']['password']['empty'] = true;
        }
        if (empty($data['errors'])) {
            $newUser = R::dispense('user');
            $newUser->email = $request->get('login');
            $newUser->password = $request->get('password');
            $app['session']->set('user', R::store($newUser));
            return new RedirectResponse('/');
        }
    }
    return $app['twig']->render('register.html', $data);
})
->bind('register');

$app->get('/logout', function() use ($app) {
    $app['session']->set('user', null);
    return new RedirectResponse('/');
})
->bind('logout');

$app->get('/navigation', function() use($app) {
    return $app['twig']->render('navigation.html');
});

// handling errors
$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html',
        'errors/'.substr($code, 0, 2).'x.html',
        'errors/'.substr($code, 0, 1).'xx.html',
        'errors/default.html',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});

$app->finish(function (Request $request, Response $response) use ($app) {
    $app['tmpFileManager']->cleanup();
});

$app->before(function(Request $request) use ($app) {
    $route = $app['request']->attributes->get('_route');
    $publicRoutes = ['login', 'register', 'logout'];
    $basicHttpAuthRoutes = ['opds', 'opds-download'];
    if (in_array($route, $basicHttpAuthRoutes)) {
        $user = $app['persi']->authBasicHttp($request);
        if (!$user) {
            return new Response('Not Authorized', 401, ['WWW-Authenticate' => 'Basic realm="Bookbox OPDS"']);
        }
        $app['persi']->setCurrentUserId($user->id);
    }
    else {
        if (!in_array($route, $publicRoutes) && !$app['session']->get('user')) {
            return new RedirectResponse('/login');
        }
    }
});