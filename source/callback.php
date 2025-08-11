<?php

/**
 * @var $md
 * @var $buf
 */
require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";

$client=new //Google_Client();
//$client->
//$client->
//$client->setRedirectUri('http://shanapra.com/callback.php');

if(isset($_GET['code'])) {
    //$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if(!isset($token['error'])) {
       // $client->setAccessToken($token['access_token']);

        //$oauth=new Google_Service_Oauth2($client);
        $userInfo=$oauth->userinfo->get();

        $_SESSION['user_email'] = $userInfo->email;
        $_SESSION['user_name'] = $userInfo->name;
        $_SESSION['user_picture'] = $userInfo->picture;

        header('Location: index.php');
        exit();
    } else {
        //View_Add("Ошибка авторизации") . htmlspecialchars($token['error_description']);
    }
} else {
    View_Add("Нет кода авторизации");
}
