<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(Menu_Profile());

//Основной екран

$dblink = DbConnect();
if ($md == 22) {
    if (isset($_POST['lname'])) {
        $a = $_POST['lname'];
        $sql = 'UPDATE users SET lname="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
        mysqli_query($dblink, $sql);
        $md = 2;
    }
}

if ($md == 23) {
    if (isset($_POST['fname'])) {
        $a = $_POST['fname'];
        $sql = 'UPDATE users SET fname="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
        mysqli_query($dblink, $sql);
        $md = 2;
    }
}

if ($md == 24) {
    if (isset($_POST['pasw1'])) {
        $a = $_POST['pasw1'];
        $b = $_POST['pasw2'];
        $sql = 'SELECT pasw FROM users WHERE idx=' . $_SESSION['uzver'];
        $res = mysqli_query($dblink, $sql);
        $p = mysqli_fetch_assoc($res);
        if (md5($a) == $p['pasw']) {
            $sql = 'UPDATE users SET pasw="' . md5($b) . '" WHERE idx=' . $_SESSION['uzver'];
            mysqli_query($dblink, $sql);
        }

        $md = 2;
    }
}

if ($md == 33) {
    if (isset($_POST['tel'])) {
        $a = $_POST['tel'];
        $sql = 'UPDATE users SET tel="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
        mysqli_query($dblink, $sql);
        $md = 3;
    }
}

if (($md == 0) || ($md == '')) {
    if ($_SESSION['logged'] == 1) {
        $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
        $res = mysqli_query($dblink, $sql);
        $cnt = mysqli_num_rows($res);
        if ($cnt == 1) {
            $p = mysqli_fetch_assoc($res);
            View_Add(
                '<div class="profile-card">' .
                '<div class="avatar"><img class="avatar-image" src="'
            );

            if ($p['avatar'] != '') {
                View_Add($p['avatar']);
            } else {
                View_Add('/avatars/ava.png');
            }

            View_Add(
                '"></div>' .
                '<div class="avatar-name">' .
                '<input type="text" name="lname" class="avatar-lname" value="' . $p['lname'] . '">' .
                '<input type="text" name="fname" class="avatar-fname" value="' . $p['fname'] . '">' .
                '</div>' .
                '<hr class="profile-separator">' .
                '</div>'
            );

        }
    }
} else {
    //не авторизован
    //переход на главную через 5 сек.

    /*sleep(5); // Задержка в 5 секунд
header("Location: https://www.example.com"); // Перенаправление на указанный URL
exit(); //  Обязательно завершите выполнение скрипта после редиректа

    header("refresh: 10; url=http://google.ru/");
    */
}

if ($md == 2) {
    if ($_SESSION['logged'] == 1) {
        $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
        $res = mysqli_query($dblink, $sql);
        $cnt = mysqli_num_rows($res);
        if ($cnt == 1) {
            $p = mysqli_fetch_assoc($res);
            View_Add(
                '<div class="settings-block">' .
                ' <h2 class="settings-title">Налаштування профілю</h2>' .
                '    <hr class="settings-divider">' .
                '    ' .
                '  <form class="updatelname" action="?" method="post">' .
                '    <label class="labelflname">Прізвище</label>' .
                '     <div class="input-row">' .
                '    <input type="text" name="lname" id="lname-input" class="lnamesettings" value="' . $p['lname'] . '">' .
                '    <button type="submit" class="sub-sett-btn">Оновити</button>' .
                '    </div>' .
                '    <input type="hidden" name="md" value="22">' .
                '</form>' .
                '' .
                '<form class="updatefname" action="?" method="post">' .
                '    <label class="labelflname">Ім`я</label>' .
                '     <div class="input-row">' .
                '    <input type="text" name="fname" id="fname-input" class="fnamesettings" value="' . $p['fname'] . '">' .
                '    <button type="submit" class="sub-sett-btn">Оновити</button>' .
                '    </div>' .
                '    <input type="hidden" name="md" value="23">' .
                '</form>' .
                '' .
                '<div class="dividerset2"></div>' .
                '' .
                '  <h3 class="password-title">Зміна паролю</h3>' .
                '' .
                '  <form class="updatepasw" action="?" method="post">' .
                '    <div class="floating-input">' .
                '        <input type="password" name="pasw1" class="paswsettings" placeholder=" " value="">' .
                '        <label>Старий пароль</label>' .
                '    </div>' .
                '' .
                '    <div class="floating-input">' .
                '        <input type="password" name="pasw2" class="paswsettings" placeholder=" " value="">' .
                '        <label>Новий пароль</label>' .
                '    </div>' .
                '' .
                '    <button type="submit" class="sub-sett-btn">Оновити</button>' .
                '    <input type="hidden" name="md" value="24">' .
                '</form>' .
                '' .
                '</div>'
            );


        }
    }
}

if ($md == 3) {
    if ($_SESSION['logged'] == 1) {
        $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
        $res = mysqli_query($dblink, $sql);
        $cnt = mysqli_num_rows($res);
        if ($cnt == 1) {
            $p = mysqli_fetch_assoc($res);
            View_Add(
                '<div class="contact-card">' .
                '    <h2 class="contact-title">Контактна інформація</h2>' .
                '    <hr class="contact-divider">' .
                '' .
                '    <form class="updatetel" action="?" method="post">' .
                '        <label for="tel" class="contact-label">Номер телефону</label>' .
                '        <div class="tel-row">' .
                '            <input type="text" name="tel" id="tel" class="contact-input" value="' . $p['tel'] . '">' .
                '            <input type="hidden" name="md" value="33">' .
                '            <button type="submit" class="contact-btn">Оновити</button>' .
                '        </div>' .
                '    </form>' .
                '' .
                '    <div class="email-display">' .
                '        <label class="contact-label">Електронна пошта</label>' .
                '        <div class="email-value">' . $p['email'] . '</div>' .
                '    </div>' .
                '</div>'
            );



        }
    }
}

//Фінансова інформація
//Добавить внутрішню валюту

if ($md == 4) {
    if ($_SESSION['logged'] == 1) {
        $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
        $res = mysqli_query($dblink, $sql);
        $cnt = mysqli_num_rows($res);
        if ($cnt == 1) {
            $p = mysqli_fetch_assoc($res);
            $formattedCash = number_format($p['cash'], 0, '', '.');
            $formattedInnerCurrency = number_format($p['rate'], 0, '', '.');
            $formattedIncome = number_format($p['cash'], 0, '', '.');  // доходы
            //$formattedExpenses = number_format($p['cash'], 0, '', '.'); // витрати
            View_Add(
                '<div class="wallet-full-wrapper">' .
                '    <div class="wallet-container">' .
                '        <div class="wallet-header">' .
                '            <img src="/assets/images/wallet.jpg" alt="Wallet Icon" class="wallet-icon">' .
                '            <div class="wallet-texts">' .
                '                <span class="wallet-title">Мій гаманець</span>' .
                '                <span class="wallet-subtitle">Вітаємо!</span>' .
                '            </div>' .
                '        </div>' .
                '        <div class="wallet-balance-box">' .
                '            <div class="balance-header">' .
                '                <img src="/assets/images/balance.jpg" alt="Balance Icon" class="balance-icon">' .
                '                <span class="balance-label">Основний баланс</span>' .
                '            </div>' .
                '            <div class="balance-amount">₴ ' . $formattedCash . '</div>' .
                '            <hr class="balance-divider">' .
                '            <div class="balance-header" style="margin-top: 12px;">' .
                '                <img src="/assets/images/val2.png" alt="" class="balance-icon">' .
                '                <span class="balance-label">Внутрішня валюта</span>' .
                '            </div>' .
                '            <div class="balance-amount">' .
                '                <img src="/assets/images/crest.png" alt="" style="width: 24px; vertical-align: middle; margin-right: -5px; margin-left: -3px; margin-top: -5px;">' .
                '                ' . $formattedInnerCurrency .
                '            </div>' .
                '            <button class="wallet-topup-btn">Поповнити</button>' .
                '        </div>' .
                '    </div>' .
                '    <div class="finance-summary">' .
                '        <div class="finance-card income">' .
                '            <span class="finance-title">Доходи</span>' .
                '            <span class="finance-amount">₴ ' . $formattedIncome . '</span>' .
                '        </div>' .
                '        <div class="finance-card expenses">' .
                '            <span class="finance-title">Витрати</span>' .
                '            <span class="finance-amount">₴ 0</span>' .
                '        </div>' .
                '    </div>' .
                '</div>'
            );

// $formattedIncome - доходы
            // $formattedExpenses - расходы
        }
    }
}

function Menu_Profile(): string
{
    $out =
        '<div class="Menu_Profile">' .
        '<a href="?md=0" class="menu-link">Загальна інформація</a>' .
        '<a href="?md=2" class="menu-link">Налаштування профілю</a>' .
        '<div class="divider"></div>' .
        '<a href="?md=3" class="menu-link">Контактна інформація</a>' .
        '<a href="?md=4" class="menu-link">Фінансова інформація</a>' .
        '<div class="divider"></div>' .
        '<a href="?md=5" class="menu-link">Додаткове</a>' .
        '<a href="?exit=1" class="logout-btn">Вийти</a>' .
        '</div>';

    return $out;
}




if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_start();
    $_SESSION = [];
    session_destroy();
    header("Location: /index.php");
    exit;
}


View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
