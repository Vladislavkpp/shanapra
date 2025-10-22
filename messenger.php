<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/chats.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/MessengerRender.php";
session_start();

function sendJson($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$isAjax = isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_idx']));

if (!isset($_SESSION['uzver'])) {
    if ($isAjax) sendJson(['status' => 'error', 'msg' => 'unauthorized']);
    header("Location: /auth.php");
    exit;
}

$dblink = DbConnect();
$chats = new Chats($dblink);
$renderer = new MessengerRender($dblink);
$user_id = (int)$_SESSION['uzver'];

if (isset($_GET['action']) && $_GET['action'] === 'get_chat_html' && isset($_GET['chat'])) {
    $chatId = (int)$_GET['chat'];
    $messages = $chats->getMessages($chatId);
    $currentChat = $chats->getChatById($chatId);
    echo $renderer->renderChatWindow($user_id, $currentChat, $messages, $chatId);
    exit;
}

// получение новых сообщений
if (isset($_GET['action']) && $_GET['action'] === 'get_new_messages' && isset($_GET['chat'], $_GET['last_msg_id'])) {
    $chat_id = (int)$_GET['chat'];
    $last_msg_id = (int)$_GET['last_msg_id'];
    $newMessages = $chats->getNewMessages($chat_id, $last_msg_id);
    sendJson(['status' => 'ok', 'messages' => $newMessages]);
}



// отправка сообщения
if (isset($_GET['action']) && $_GET['action'] === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $chat_idx = (int)($_POST['chat_idx'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($message === '') sendJson(['status'=>'error','msg'=>'Empty message']);

    $success = $chats->addMessage($chat_idx, $user_id, $message);
    if ($success) {
        $lastMsg = $chats->getNewMessages($chat_idx, 0);
        sendJson(['status'=>'ok','messages'=>$lastMsg]);
    } else {
        sendJson(['status'=>'error','msg'=>'DB insert failed']);
    }
}

$chat_id = isset($_GET['chat']) ? (int)$_GET['chat'] : 0;
$userChats = $chats->getUserChats($user_id);
$currentChat = $chat_id ? $chats->getChatById($chat_id) : null;
$messages = $chat_id ? $chats->getMessages($chat_id) : [];

// вывод
View_Add(Page_Up("Чати"));
View_Add(Menu_Up());
View_Add('<div class="outmsg">');
View_Add($renderer->renderContainer($user_id, $userChats, $currentChat, $messages, $chat_id));
View_Add('</div>');
View_Out();
