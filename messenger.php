<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/chats.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/MessengerRender.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/SupportDesk.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/SupportRender.php";

session_start();

if (!isset($_SESSION['captcha_passed']) && isset($_COOKIE['captcha_passed']) && $_COOKIE['captcha_passed'] === 'true') {
    $_SESSION['captcha_passed'] = true;
}

function sendJson($data): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function supportSaveUploadedImage(int $entityId = 0): string
{
    if (empty($_FILES['img']['name']) || (int)($_FILES['img']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['img']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Дозволені формати: JPG, PNG, GIF, WebP');
    }
    if ((int)($_FILES['img']['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Розмір файлу не більше 5 МБ');
    }

    $ext = strtolower(pathinfo((string)$_FILES['img']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $ext = 'jpg';
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/chat_images/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $prefix = $entityId > 0 ? $entityId : 'support';
    $filename = 'support_' . $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $fullPath = $uploadDir . $filename;
    if (!move_uploaded_file($_FILES['img']['tmp_name'], $fullPath)) {
        throw new RuntimeException('Не вдалося зберегти зображення.');
    }

    return '/chat_images/' . $filename;
}

$type = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$isAjax = isset($_GET['action'])
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_idx']))
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id']));
$device = function_exists('detectDevice')
    ? detectDevice($_SERVER['HTTP_USER_AGENT'] ?? '')
    : ['type' => 'desktop'];

if (!$isAjax && !isset($_GET['type'])) {
    header('Location: ' . PublicUrl('/messenger.php?type=2'));
    exit;
}

$user_id = (isset($_SESSION['logged']) && (int)$_SESSION['logged'] === 1 && isset($_SESSION['uzver']))
    ? (int)$_SESSION['uzver']
    : null;
if (!isset($_SESSION['guest_id'])) {
    if (isset($_COOKIE['guest_id'])) {
        $guest_id = (int)$_COOKIE['guest_id'];
        if ($guest_id === 0) {
            $guest_id = -rand(10000, 99999);
        }
        $_SESSION['guest_id'] = $guest_id;
    } else {
        $_SESSION['guest_id'] = -rand(10000, 99999);
        setcookie('guest_id', (string)$_SESSION['guest_id'], time() + 60 * 60 * 24 * 30, "/");
    }
}
$guest_id = (int)$_SESSION['guest_id'];
$currentUser = $user_id !== null ? $user_id : $guest_id;

$dblink = DbConnect();
$chats = new Chats($dblink);
$renderer = new MessengerRender($dblink);
$supportDesk = new SupportDesk($dblink);
$supportRender = new SupportRender();

if ($user_id === null && $type !== 3 && !$isAjax) {
    header('Location: ' . PublicUrl('/auth.php'));
    exit;
}

if ($type === 1) {
    $type = 2;
    $_GET['type'] = 2;
    if (!$isAjax) {
        $redirectChatId = (int)($_GET['chat'] ?? 0);
        $redirectUrl = '/messenger.php?type=2';
        if ($redirectChatId > 0) {
            $redirectUrl .= '&chat=' . $redirectChatId;
        }
        header('Location: ' . PublicUrl($redirectUrl));
        exit;
    }
}

if ($type !== 2 && $type !== 3) {
    $type = 2;
    $_GET['type'] = 2;
}

if ($type === 3) {
    $supportTicketId = (int)($_GET['chat'] ?? ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0));

    if (isset($_GET['action'])) {
        try {
            if ($_GET['action'] === 'support_get_ticket') {
                $ticket = $supportTicketId > 0
                    ? $supportDesk->getTicketForClient($supportTicketId, $user_id, $guest_id)
                    : $supportDesk->findActiveTicketForClient($user_id, $guest_id);
                sendJson([
                    'status' => 'ok',
                    'ticket' => $ticket,
                    'messages' => $ticket ? $supportDesk->getMessages((int)$ticket['id']) : [],
                ]);
            }

            if ($_GET['action'] === 'support_get_messages') {
                $ticket = $supportDesk->getTicketForClient($supportTicketId, $user_id, $guest_id);
                if (!$ticket) {
                    sendJson(['status' => 'error', 'msg' => 'Звернення не знайдено']);
                }
                $afterId = (int)($_GET['last_id'] ?? 0);
                sendJson([
                    'status' => 'ok',
                    'ticket' => $ticket,
                    'messages' => $supportDesk->getMessages((int)$ticket['id'], $afterId),
                ]);
            }

            if ($_GET['action'] === 'support_send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if ($user_id === null && (!isset($_SESSION['captcha_passed']) || $_SESSION['captcha_passed'] !== true)) {
                    sendJson(['status' => 'captcha_required', 'msg' => 'Потрібно підтвердити капчу.']);
                }

                $draftTicket = $supportTicketId > 0
                    ? $supportDesk->getTicketForClient($supportTicketId, $user_id, $guest_id)
                    : $supportDesk->findActiveTicketForClient($user_id, $guest_id);

                if (!$draftTicket && !empty($_FILES['img']['name'])) {
                    $draftTicket = $supportDesk->createTicket($user_id, $guest_id, 'messenger', null);
                    $supportTicketId = (int)$draftTicket['id'];
                }

                $imgPath = supportSaveUploadedImage((int)($draftTicket['id'] ?? $supportTicketId));
                $result = $supportDesk->sendClientMessage(
                    $supportTicketId > 0 ? $supportTicketId : null,
                    $user_id,
                    $guest_id,
                    trim((string)($_POST['message'] ?? '')),
                    $imgPath !== '' ? $imgPath : null
                );

                sendJson([
                    'status' => 'ok',
                    'ticket' => $result['ticket'],
                    'message' => $result['message'],
                    'messages' => $supportDesk->getMessages((int)$result['ticket']['id']),
                ]);
            }

            if ($_GET['action'] === 'support_confirm_resolution' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $result = $supportDesk->confirmResolutionByClient($supportTicketId, $user_id, $guest_id);
                sendJson([
                    'status' => 'ok',
                    'ticket' => $result['ticket'],
                    'message' => $result['message'],
                    'messages' => $supportDesk->getMessages((int)$result['ticket']['id']),
                ]);
            }
        } catch (Throwable $e) {
            sendJson(['status' => 'error', 'msg' => $e->getMessage()]);
        }
    }

    $ticket = $supportTicketId > 0
        ? $supportDesk->getTicketForClient($supportTicketId, $user_id, $guest_id)
        : $supportDesk->findActiveTicketForClient($user_id, $guest_id);
    $messages = $ticket ? $supportDesk->getMessages((int)$ticket['id']) : [];

    View_Add(Page_Up('Підтримка'));
    View_Add(Menu_Up());
    View_Add('<div class="outmsg">');
    View_Add(NormalizePublicMarkup($supportRender->renderClientPage($ticket, $messages, [
        'is_authenticated' => $user_id !== null,
        'captcha_passed' => isset($_SESSION['captcha_passed']) && $_SESSION['captcha_passed'] === true,
    ])));
    View_Add('</div>');
    View_Out();
    exit;
}

if (($_POST['action'] ?? '') === 'delete_chat') {
    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $resp = ['status' => 'error'];

    if ($chat_id > 0 && $chats->isParticipant($chat_id, $currentUser)) {
        $res1 = mysqli_query($dblink, "DELETE FROM chatsmsg WHERE chat_idx = $chat_id");
        if (!$res1) {
            $resp['sql_error'] = mysqli_error($dblink);
        }

        $res2 = mysqli_query($dblink, "DELETE FROM chats WHERE idx = $chat_id");
        if (!$res2) {
            $resp['sql_error'] = mysqli_error($dblink);
        }

        if ($res1 && $res2) {
            $resp['status'] = 'ok';
        }
    }

    sendJson($resp);
}

if (($_POST['action'] ?? '') === 'end_chat') {
    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $resp = ['status' => 'error'];

    if ($chat_id > 0 && $chats->isParticipant($chat_id, $currentUser)) {
        $resCheck = mysqli_query($dblink, "
            SELECT idx FROM chatsmsg
            WHERE chat_idx = $chat_id
              AND message LIKE '%приєднався до чату%'
            LIMIT 1
        ");

        if ($resCheck && mysqli_num_rows($resCheck) > 0) {
            $msg_idx = mysqli_real_escape_string($dblink, $_POST['msg_idx'] ?? "sys-" . time() . "-" . rand(0, 999));
            $msg_text = "Спеціаліст завершив чат";

            $resExist = mysqli_query($dblink, "
                SELECT idx FROM chatsmsg
                WHERE chat_idx = $chat_id
                  AND idx = '$msg_idx'
                LIMIT 1
            ");

            $ok = (!$resExist || mysqli_num_rows($resExist) === 0)
                ? $chats->addMessage($chat_id, -1, $msg_text, $msg_idx)
                : true;

            if ($ok) {
                $resp = [
                    'status' => 'ok',
                    'msg_idx' => $msg_idx,
                    'message' => $msg_text,
                    'sender_idx' => -1,
                ];
            }
        } else {
            $resp['status'] = 'no_join_message';
        }
    }

    sendJson($resp);
}

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_chat_html' && isset($_GET['chat'])) {
        $chatId = (int)$_GET['chat'];
        if (!$chats->isParticipant($chatId, $currentUser)) {
            sendJson(['status' => 'error', 'msg' => 'Чат недоступний']);
        }
        $messages = $chats->getMessages($chatId);
        echo NormalizePublicMarkup($renderer->renderChatWindow($currentUser, $chats->getChatById($chatId), $messages, $chatId, $type));
        exit;
    }

    if ($_GET['action'] === 'get_chat_info' && isset($_GET['chat'])) {
        $chatId = (int)$_GET['chat'];
        if (!$chats->isParticipant($chatId, $currentUser)) {
            sendJson(['status' => 'error', 'msg' => 'Чат недоступний']);
        }
        $chat = $chats->getChatById($chatId);
        echo NormalizePublicMarkup($renderer->renderChatInfo($currentUser, $chat));
        exit;
    }

    if ($_GET['action'] === 'get_new_messages' && isset($_GET['chat'], $_GET['last_msg_id'])) {
        $chat_id = (int)$_GET['chat'];
        $last_msg_id = (int)$_GET['last_msg_id'];
        if (!$chats->isParticipant($chat_id, $currentUser)) {
            sendJson(['status' => 'error', 'msg' => 'Чат недоступний']);
        }
        sendJson(['status' => 'ok', 'messages' => $chats->getNewMessages($chat_id, $last_msg_id)]);
    }

    if ($_GET['action'] === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $chat_idx = (int)($_POST['chat_idx'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));
        $imgPath = '';

        if (!$chats->isParticipant($chat_idx, $currentUser)) {
            sendJson(['status' => 'error', 'msg' => 'Чат недоступний']);
        }

        if (!empty($_FILES['img']['name'])) {
            try {
                $imgPath = supportSaveUploadedImage($chat_idx);
            } catch (Throwable $e) {
                sendJson(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }

        if ($message === '' && $imgPath === '') {
            sendJson(['status' => 'error', 'msg' => 'Порожнє повідомлення']);
        }

        $success = $chats->addMessage($chat_idx, $currentUser, $message, null, $imgPath !== '' ? $imgPath : null);
        $lastMsg = $chats->getNewMessages($chat_idx, 0);
        sendJson($success ? ['status' => 'ok', 'messages' => $lastMsg] : ['status' => 'error', 'msg' => 'Failed to add message']);
    }
}

$chat_id = isset($_GET['chat']) ? (int)$_GET['chat'] : 0;
$userChats = [];
$currentChat = null;
$pageTitle = 'Робочі чати';
$messages = [];

$userChats = $chats->getChatsByType((int)$user_id, 2);

if ($chat_id > 0 && $chats->isParticipant($chat_id, $currentUser)) {
    $currentChat = $chats->getChatById($chat_id);
    if ((int)($currentChat['type'] ?? 0) !== 2) {
        $currentChat = null;
    }
}

if ($currentChat) {
    $chat_id = (int)($currentChat['idx'] ?? 0);
    $messages = $chats->getMessages($chat_id);
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
