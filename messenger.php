<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/chats.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/MessengerRender.php";

session_start();

if (!isset($_SESSION['captcha_passed']) && isset($_COOKIE['captcha_passed']) && $_COOKIE['captcha_passed'] === 'true') {
    $_SESSION['captcha_passed'] = true;
}

function sendJson($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$isAjax = isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_idx']));
$type = isset($_GET['type']) ? (int)$_GET['type'] : 0;

if (!$isAjax && !isset($_GET['type'])) {
    header("Location: /messenger.php?type=1");
    exit;
}

$user_id = isset($_SESSION['uzver']) ? (int)$_SESSION['uzver'] : null;

/* айди гостя */
if (!isset($_SESSION['guest_id'])) {
    if (isset($_COOKIE['guest_id'])) {
        $guest_id = (int)$_COOKIE['guest_id'];
        if ($guest_id === 0) $guest_id = -rand(10000, 99999);
        $_SESSION['guest_id'] = $guest_id;
    } else {
        $_SESSION['guest_id'] = -rand(10000, 99999);
        setcookie('guest_id', $_SESSION['guest_id'], time() + 60*60*24*30, "/");
    }
}
$guest_id = $_SESSION['guest_id'];

$currentUser = ($user_id !== null) ? $user_id : $guest_id;

$dblink = DbConnect();
$chats = new Chats($dblink);
$renderer = new MessengerRender($dblink);

if ($user_id === null && $type !== 3 && !$isAjax) {
    header("Location: /auth.php");
    exit;
}

if (isset($_GET['action'])) {

    // Получение чата
    if ($_GET['action'] === 'get_chat_html' && isset($_GET['chat'])) {
        $chatId = (int)$_GET['chat'];
        $messages = $chats->getMessages($chatId);
        echo $renderer->renderChatWindow($currentUser, $chats->getChatById($chatId), $messages, $chatId, $type);
        exit;
    }

    // Получение новых сообщений
    if ($_GET['action'] === 'get_new_messages' && isset($_GET['chat'], $_GET['last_msg_id'])) {
        $chat_id = (int)$_GET['chat'];
        $last_msg_id = (int)$_GET['last_msg_id'];
        $newMessages = $chats->getNewMessages($chat_id, $last_msg_id);
        sendJson(['status' => 'ok', 'messages' => $newMessages]);
    }

    // Отправка сообщения
    if ($_GET['action'] === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $chat_idx = (int)($_POST['chat_idx'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($message === '') sendJson(['status' => 'error', 'msg' => 'Порожнє повідомлення']);

        // Проверка каптчи
        if ($type === 3 && $user_id === null) {
            if (!isset($_SESSION['captcha_passed']) || $_SESSION['captcha_passed'] !== true) {
                sendJson(['status' => 'captcha_required', 'msg' => 'Потрібно підтвердити капчу.']);
            }
        }

        $senderId = $currentUser;
        $success = $chats->addMessage($chat_idx, $senderId, $message);

        // Автоответ от поддержки
        $currentChatData = $chats->getChatById($chat_idx);
        if ($currentChatData && (int)$currentChatData['type'] === 3 && $senderId !== -1) {
            $resCheck = mysqli_query($dblink, "
                SELECT idx FROM chatsmsg 
                WHERE chat_idx = $chat_idx 
                  AND sender_idx = -1 
                  AND message LIKE 'Дякуємо, що звернулися до технічної підтримки%'
                LIMIT 1
            ");
            if ($resCheck && mysqli_num_rows($resCheck) === 0) {
                sleep(1);
                $autoMsg = "Дякуємо, що звернулися до технічної підтримки.\nВаше повідомлення отримано — наш спеціаліст відповість вам найближчим часом.";
                $chats->addMessage($chat_idx, -1, $autoMsg);
            }
        }

        $lastMsg = $chats->getNewMessages($chat_idx, 0);
        if ($success) sendJson(['status' => 'ok', 'messages' => $lastMsg]);
        else sendJson(['status' => 'error', 'msg' => 'Failed to add message']);
    }

}



$chat_id = isset($_GET['chat']) ? (int)$_GET['chat'] : 0;
$userChats = [];
$currentChat = null;
$pageTitle = 'Мої чати';

if ($type === 3) {
    $pageTitle = "Підтримка";
    $isSupport = ($user_id !== null && in_array($user_id, [-1])); // поддержка

    if ($isSupport) {
        $userChats = $chats->getSupportChatsList();
        if ($chat_id) {
            $currentChat = $chats->getChatById($chat_id);
            $messages = $chats->getMessages($chat_id);
        } elseif (!empty($userChats)) {
            $currentChat = $userChats[0];
            $messages = $chats->getMessages($currentChat['idx']);
            $chat_id = $currentChat['idx'];
        } else {
            $messages = [];
        }
    } else {
        // Для пользователя / гостя
        if ($user_id !== null) {
            $existingChat = $chats->getUserSupportChat($user_id);
            if ($existingChat) {
                $chat_id = $existingChat['idx'];
                $currentChat = $existingChat;
            } else {
                $chat_id = $chats->createSupportChat($user_id);
                $currentChat = [
                    'idx' => $chat_id,
                    'user_one' => $user_id,
                    'user_two' => -1,
                    'type' => 3
                ];
                $chats->addMessage($chat_id, -1, "Вітаємо вас в чаті!");
                sleep(1);
                $chats->addMessage($chat_id, -1, "Чим ми можемо вам допомогти?");
            }
        } else {
            $existingChat = $chats->getGuestChatByUser($guest_id);
            if ($existingChat) {
                $chat_id = $existingChat['idx'];
                $currentChat = $existingChat;
            } else {
                $chat_id = $chats->createGuestChat($guest_id, -1, 3);
                $currentChat = [
                    'idx' => $chat_id,
                    'user_one' => $guest_id,
                    'user_two' => -1,
                    'type' => 3
                ];
                $chats->addMessage($chat_id, -1, "Вітаємо вас в чаті!");
                sleep(1);
                $chats->addMessage($chat_id, -1, "Чим ми можемо вам допомогти?");
            }
        }
        $userChats = [$currentChat];
        $messages = $chats->getMessages($chat_id);
    }
} elseif ($type === 1 || $type === 2) {
    $userChats = $chats->getChatsByType($user_id, $type);
    $pageTitle = $type === 1 ? "Особисті чати" : "Робочі чати";
    $currentChat = $chat_id ? $chats->getChatById($chat_id) : null;
    $messages = $chat_id ? $chats->getMessages($chat_id) : [];
} else {
    $allChats = $chats->getUserChats($user_id, 0);
    $userChats = array_filter($allChats, fn($c) => in_array((int)$c['type'], [1,2]));
    $pageTitle = "Усі чати";
    $currentChat = $chat_id ? $chats->getChatById($chat_id) : null;
    $messages = $chat_id ? $chats->getMessages($chat_id) : [];
}

View_Add(Page_Up($pageTitle));
View_Add(Menu_Up());
View_Add('<div class="outmsg">');
View_Add($renderer->renderContainer($currentUser, $userChats, $currentChat, $messages, $chat_id, $type));
View_Add('</div>');
View_Out();
