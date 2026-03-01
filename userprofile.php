<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
$dblink = DbConnect();

echo '<link rel="stylesheet" href="/assets/css/userpage.css">';

session_start();
$my_id = isset($_SESSION['uzver']) ? intval($_SESSION['uzver']) : 0;

if (!isset($_GET['idx']) || !is_numeric($_GET['idx'])) {
    die("Невірний ID користувача.");
}

$user_id = intval($_GET['idx']);

if ($my_id > 0 && $user_id === $my_id) {
    header("Location: /profile.php");
    exit;
}

$res = mysqli_query($dblink, "SELECT idx, fname, lname, avatar FROM users WHERE idx = $user_id");
if (!$res || mysqli_num_rows($res) == 0) {
    die("Користувач не знайдений.");
}

$user = mysqli_fetch_assoc($res);


function userpage($user)
{
    $dblink = DbConnect();
    if (isset($_POST['create_chat']) && isset($_SESSION['uzver'])) {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/chats.php";
        $chats = new Chats($dblink);

        $me = $_SESSION['uzver'];
        $target = (int)$_POST['create_chat'];

        $chat_idx = $chats->createChat($me, $target);
        header("Location: /messenger.php?chat=" . $chat_idx);
        exit;
    }

    $out = '';

    $out .= '<div class="profile-page-wrapper" style="width:80%; margin:0 auto;">';

    $out .= '<div class="profile-container">';
    $out .= '<div class="banner-profile"></div>';
    $out .= '<div class="user-card" style="display:flex; gap:20px;">';

    // Аватарка
    $out .= '<div class="avataruser">';
    $out .= '<img class="avatar-image" src="' . ($user['avatar'] != '' ? $user['avatar'] : '/avatars/ava.png') . '" alt="Аватар">';
    $out .= '</div>';

    $out .= '<div class="profile-info">';

    $out .= '<div class="avatar-name-row">';
    $out .= '<span class="avatar-fullname">' . htmlspecialchars($user['lname']) . ' ' . htmlspecialchars($user['fname']) . '</span>';
    $out .= '<span class="avatar-followers">Підписників: 0</span>';
    $out .= '</div>';
    $out .= '</div>';

    // Правая часть
    $out .= '<div class="profile-actions">';
    $out .= '<form method="post" style="display:inline;">';
    $out .= '<button type="submit" class="btn-write" name="create_chat" value="' . (int)$user['idx'] . '">Написати</button>';
    $out .= '</form>';
    $out .= '<button class="btn-follow">Підписатися</button>';
    $out .= '</div>';

    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';


    return $out;
}

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="outuser">');

View_Add(userpage($user));

View_Add('</div>');
View_Add('<div class="lentauser">');



View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
