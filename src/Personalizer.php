<?php

use Symfony\Component\HttpFoundation\Request;

class Personalizer {

    private $userId;

    public function setCurrentUserId($userId) {
        $this->userId = $userId;
    }

    public function getCurrentUser() {
        if (!$this->userId) {
            return null;
        }
        return R::load( 'user', $this->userId );
    }

    public function getUserByCredentials($login, $password) {
        if (empty($login) || empty($password)) {
            return NULL;
        }
        // check against database
        $user = R::findOne( 'user', ' email = ? ', [$login] );
        if (!$user || $user->password != $password) {
            return NULL;
        }
        return $user;
    }

    public function authBasicHttp(Request $request) {
        // get username and pass from headers
        $credentials = preg_replace('/^Basic /i', '', $request->headers->get('Authorization'));
        $credentials = base64_decode($credentials);
        // Notice: Undefined offset: 1
        @list($userName, $pass) = explode(':', $credentials, 2);
        return $this->getUserByCredentials($userName, $pass);
    }
} 