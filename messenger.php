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

$isAjax = isset($_GET['action'])
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_idx']))
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']));
$device = function_exists('detectDevice')
    ? detectDevice($_SERVER['HTTP_USER_AGENT'] ?? '')
    : ['type' => 'desktop'];

if (!$isAjax && (($device['type'] ?? 'desktop') === 'mobile')) {
    $_GET['from'] = (string)($_SERVER['REQUEST_URI'] ?? '/messenger.php');
    require $_SERVER['DOCUMENT_ROOT'] . '/maintenance.php';
    exit;
}

$type = isset($_GET['type']) ? (int)$_GET['type'] : 0;

if (!$isAjax && !isset($_GET['type'])) {
    header('Location: ' . PublicUrl('/messenger.php?type=1'));
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
    header('Location: ' . PublicUrl('/auth.php'));
    exit;
}

if (($_POST['action'] ?? '') === 'delete_chat') {
    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $resp = ['status' => 'error'];

    if ($chat_id > 0) {
        $res1 = mysqli_query($dblink, "DELETE FROM chatsmsg WHERE chat_idx = $chat_id");
        if (!$res1) $resp['sql_error'] = mysqli_error($dblink);

        $res2 = mysqli_query($dblink, "DELETE FROM chats WHERE idx = $chat_id");
        if (!$res2) $resp['sql_error'] = mysqli_error($dblink);

        if ($res1 && $res2) $resp['status'] = 'ok';
    }

    sendJson($resp);
}

if (($_POST['action'] ?? '') === 'end_chat') {
    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $resp = ['status' => 'error'];

    if ($chat_id > 0) {
        $resCheck = mysqli_query($dblink, "
            SELECT idx FROM chatsmsg 
            WHERE chat_idx = $chat_id 
              AND message LIKE '%приєднався до чату%'
            LIMIT 1
        ");

        if ($resCheck && mysqli_num_rows($resCheck) > 0) {
            $msg_idx = mysqli_real_escape_string($dblink, $_POST['msg_idx'] ?? "sys-" . time() . "-" . rand(0,999));
            $msg_text = "Спеціаліст завершив чат";

            $resExist = mysqli_query($dblink, "
                SELECT idx FROM chatsmsg 
                WHERE chat_idx = $chat_id 
                  AND idx = '$msg_idx'
                LIMIT 1
            ");

            if (!$resExist || mysqli_num_rows($resExist) === 0) {
                $ok = $chats->addMessage($chat_id, -1, $msg_text, $msg_idx);
            } else {
                $ok = true;
            }

            if ($ok) {
                $resp = [
                    'status' => 'ok',
                    'msg_idx' => $msg_idx,
                    'message' => $msg_text,
                    'sender_idx' => -1
                ];
            }
        } else {
            $resp['status'] = 'no_join_message';
        }
    }

    sendJson($resp);
}

if (isset($_GET['action'])) {

    // Получение чата
    if ($_GET['action'] === 'get_chat_html' && isset($_GET['chat'])) {
        $chatId = (int)$_GET['chat'];
        $messages = $chats->getMessages($chatId);
        echo NormalizePublicMarkup($renderer->renderChatWindow($currentUser, $chats->getChatById($chatId), $messages, $chatId, $type));
        exit;
    }

    // Получение информации о чате (для мобильной панели)
    if ($_GET['action'] === 'get_chat_info' && isset($_GET['chat'])) {
        $chatId = (int)$_GET['chat'];
        $chat = $chats->getChatById($chatId);
        echo NormalizePublicMarkup($renderer->renderChatInfo($currentUser, $chat));
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
        $imgPath = '';

        // Загрузка изображения в сообщение
        if (!empty($_FILES['img']['name']) && $_FILES['img']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['img']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                sendJson(['status' => 'error', 'msg' => 'Дозволені формати: JPG, PNG, GIF, WebP']);
            }
            if ($_FILES['img']['size'] > $maxSize) {
                sendJson(['status' => 'error', 'msg' => 'Розмір файлу не більше 5 МБ']);
            }

            $ext = strtolower(pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/chat_images/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $filename = $chat_idx . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $fullPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['img']['tmp_name'], $fullPath)) {
                $imgPath = '/chat_images/' . $filename;
            }
        }

        if ($message === '' && $imgPath === '') {
            sendJson(['status' => 'error', 'msg' => 'Порожнє повідомлення']);
        }

        // Проверка каптчи
        if ($type === 3 && $user_id === null) {
            if (!isset($_SESSION['captcha_passed']) || $_SESSION['captcha_passed'] !== true) {
                sendJson(['status' => 'captcha_required', 'msg' => 'Потрібно підтвердити капчу.']);
            }
        }

        $senderId = $currentUser;
        $success = $chats->addMessage($chat_idx, $senderId, $message, null, $imgPath !== '' ? $imgPath : null);

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
View_Add('
<div id="deleteChatModal" class="modal hidden">
    <div class="modal-backdrop"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Підтвердження видалення</h3>
        </div>
        <div class="modal-body">
            Ви впевнені, що хочете видалити цей чат?
        </div>
        <div class="modal-footer">
        <button id="confirmDeleteBtn" class="btn-confirm">Видалити</button>
            <button id="cancelDeleteBtn" class="btn-cancel">Скасувати</button>
          
        </div>
    </div>
</div>
');

View_Out();
