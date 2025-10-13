<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";


function valide1($u = null, $type = 'text', &$error = '')
{
    if ($u === null || trim($u) === '') {
        $error = 'Поле не може бути порожнім';
        return '';
    }

    $u = trim($u);

    // емейл
    if ($type === 'email') {
        if (!filter_var($u, FILTER_VALIDATE_EMAIL)) {
            $error = 'Некоректний формат e-mail';
            return '';
        }

        return strtolower($u);
    }

    // -пароль
    if ($type === 'password') {

        if (strlen($u) < 8 || strlen($u) > 64) {
            $error = 'Пароль має містити від 8 до 64 символів';
            return '';
        }

        if (preg_match('/[А-Яа-яЁёІіЇїЄє]/u', $u)) {
            $error = 'Пароль повинен бути лише латиницею';
            return '';
        }


        if (!preg_match('/[a-z]/', $u)) {
            $error = 'Потрібна мала літера';
            return '';
        }


        $forbidden = ['password', '123456', 'qwerty', 'admin', 'user', 'test'];
        foreach ($forbidden as $bad) {
            if (stripos($u, $bad) !== false) {
                $error = 'Пароль містить заборонене слово';
                return '';
            }
        }

        return htmlspecialchars($u, ENT_QUOTES, 'UTF-8');
    }

    return '';
}