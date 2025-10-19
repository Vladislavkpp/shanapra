<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/chats.php";
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
$user_id = (int)$_SESSION['uzver'];

// Получаем новые сообщения
if (isset($_GET['action']) && $_GET['action'] === 'get_new_messages' && isset($_GET['chat'], $_GET['last_msg_id'])) {
    $chat_id = (int)$_GET['chat'];
    $last_msg_id = (int)$_GET['last_msg_id'];
    $newMessages = $chats->getNewMessages($chat_id, $last_msg_id);
    sendJson(['status' => 'ok', 'messages' => $newMessages]);
}

// Отправка сообщения
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

// непрочит. сделать потом
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['chat'])) {
    $chat_idx = (int)$_GET['chat'];
    $chats->markChatAsRead($chat_idx, $user_id);
    sendJson(['status'=>'ok']);
}

echo '<link rel="stylesheet" href="/assets/css/msg.css">';

$chat_id = isset($_GET['chat']) ? (int)$_GET['chat'] : 0;
$userChats = $chats->getUserChats($user_id);
$currentChat = $chat_id ? $chats->getChatById($chat_id) : null;
$messages = $chat_id ? $chats->getMessages($chat_id) : [];

$chatContainerClass = 'messenger-container';
if (!$currentChat) {
    $chatContainerClass .= ' no-chat-selected';
}

$out = '<div class="'.$chatContainerClass.'">';

// Левая колонка
$out .= '<div class="chat-list"><h3>Мої чати</h3>';
foreach ($userChats as $chat) {
    $isActive = ($chat['idx'] == $chat_id) ? 'active' : '';
    $otherUserId = ($chat['user_one'] == $user_id) ? $chat['user_two'] : $chat['user_one'];

    $res = mysqli_query($dblink, "SELECT fname, lname, avatar FROM users WHERE idx = " . (int)$otherUserId);
    $u = $res ? mysqli_fetch_assoc($res) : null;
    $name = $u ? htmlspecialchars($u['fname'] . ' ' . $u['lname']) : 'Користувач';
    $avatar = (!empty($u['avatar'])) ? $u['avatar'] : '/avatars/ava.png';

    $lastMsgData = $chat['last_message'] ?? null;
    $lastMsgText = $lastMsgData['message'] ?? 'Немає повідомлень';
    $lastMsgTime = $lastMsgData['idtadd'] ?? '';
    $time = $lastMsgTime ? date('H:i', strtotime($lastMsgTime)) : '';

    // непрочитанные
    $resUnread = mysqli_query($dblink, "SELECT COUNT(*) as unread FROM chatsmsg WHERE chat_idx = {$chat['idx']} AND sender_idx != {$user_id} AND is_read = 0");
    $unreadCount = ($resUnread && $row = mysqli_fetch_assoc($resUnread)) ? (int)$row['unread'] : 0;

    $out .= '<div class="chat-item '.$isActive.'" data-chat-id="'.$chat['idx'].'" onclick="window.location=\'messenger.php?chat='.$chat['idx'].'\'">';
    $out .= '<img src="'.htmlspecialchars($avatar).'" alt="Аватар" class="chat-item-avatar">';
    $out .= '<div class="chat-item-info">';
    $out .= '<div class="chat-item-name">'.$name.'</div>';
    $out .= '<div class="chat-item-lastmsg">';
    $out .= '<div class="message-text">'.htmlspecialchars(mb_strimwidth($lastMsgText,0,100,'...')).'</div>';
    $out .= '<div class="time">'.$time.'</div>';
    $out .= '</div>';
    if ($unreadCount > 0) $out .= '<div class="unread-badge">'.$unreadCount.'</div>';
    else $out .= '<div class="unread-badge" style="display:none;">0</div>';
    $out .= '</div></div>';
}
$out .= '</div>';

// Центральная колонка
$out .= '<div class="chat-window">';
if ($currentChat) {
    $otherUser = ($currentChat['user_one'] == $user_id) ? $currentChat['user_two'] : $currentChat['user_one'];
    $res = mysqli_query($dblink, "SELECT fname, lname, avatar FROM users WHERE idx = " . (int)$otherUser);
    $u = $res ? mysqli_fetch_assoc($res) : null;
    $name = $u ? htmlspecialchars($u['fname'] . ' ' . $u['lname']) : 'Користувач';
    $avatar = (!empty($u['avatar'])) ? $u['avatar'] : '/avatars/ava.png';

    $out .= '<div class="chat-header">';
    $out .= '<div class="chat-header-left" style="display:flex; align-items:center; gap:10px;">';
    $out .= '<img src="' . htmlspecialchars($avatar) . '" alt="Аватар" class="chat-header-avatar" style="width:40px; height:40px; border-radius:50%;">';
    $out .= '<span>' . $name . '</span>';
    $out .= '</div>';

    $out .= '<div class="chat-header-right">';
    $out .= '<button class="info-toggle-btn" data-tooltip="Інформація про користувача">';
    $out .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-question-mark">
    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
    <path d="M8 8a3.5 3 0 0 1 3.5 -3h1a3.5 3 0 0 1 3.5 3a3 3 0 0 1 -2 3a3 4 0 0 0 -2 4"/>
    <path d="M12 19l0 .01"/>
</svg>
</button>';


    $out .= '</div>';

    $out .= '</div>';

    $out .= '<div class="chat-messages" id="chatMessages" data-chat-id="' . (int)$chat_id . '">';
    $lastDate = null;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    foreach ($messages as $msg) {
        $cls = ($msg['sender_idx'] === $user_id) ? 'me' : 'other';
        $time = date('H:i', strtotime($msg['idtadd']));
        $date = date('Y-m-d', strtotime($msg['idtadd']));
        $dateLabel = ($date == $today) ? 'Сьогодні' : (($date == $yesterday) ? 'Вчора' : date('d.m.Y', strtotime($msg['idtadd'])));
        if ($lastDate !== $date) {
            $out .= '<div class="date-divider"><span>' . $dateLabel . '</span></div>';
            $lastDate = $date;
        }
        $out .= '<div class="message ' . $cls . '" data-idx="' . (int)$msg['idx'] . '">
                    <div class="msg-text">' . nl2br(htmlspecialchars($msg['message'])) . '</div>
                    <div class="msg-time">' . $time . '</div>
                 </div>';
    }
    $out .= '</div>';

    $out .= "<script>
document.addEventListener(\"DOMContentLoaded\", function() {
    const infoBtn = document.querySelector('.info-toggle-btn');
    const rightPanel = document.querySelector('.chat-info');
    const messengerContainer = document.querySelector('.messenger-container');

    const rightHidden = localStorage.getItem('chatRightHidden') === 'true';

  
    if (rightHidden) {
        messengerContainer.classList.add('right-hidden');
    } else {
        messengerContainer.classList.remove('right-hidden');
    }

    setTimeout(() => {
        rightPanel.style.transition = 'transform 0.3s ease';
        messengerContainer.style.transition = 'grid-template-columns  0.3s ease';
    }, 50);
    
    if (infoBtn && rightPanel && messengerContainer) {
        infoBtn.addEventListener('click', function() {
            const isHidden = messengerContainer.classList.toggle('right-hidden');
            localStorage.setItem('chatRightHidden', isHidden);
        });
    }
});

</script>";

    $out .= '<form class="chat-input" id="chatForm" method="post">
            <input type="hidden" name="chat_idx" value="' . $chat_id . '">

          
            <div id="charCounter">0/2000</div>

         
            <div class="input-area">
                <textarea name="message" placeholder="Повідомлення..." required></textarea>
                <button type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-send-fill" viewBox="0 0 16 16">
                        <path d="M15.964.686a.5.5 0 0 0-.65-.65L.767 5.855H.766l-.452.18a.5.5 0 0 0-.082.887l.41.26.001.002 4.995 3.178 3.178 4.995.002.002.26.41a.5.5 0 0 0 .886-.083zm-1.833 1.89L6.637 10.07l-.215-.338a.5.5 0 0 0-.154-.154l-.338-.215 7.494-7.494 1.178-.471z"/>
                    </svg>
                </button>
            </div>
         </form>';

    $out .= "<script>
(function(){
    const textarea = document.querySelector('#chatForm textarea');
    const counter = document.getElementById('charCounter');
    const button = document.querySelector('#chatForm button');
    const chatForm = document.getElementById('chatForm');
    const maxChars = 2000;

    function updateCounter() {
        const len = textarea.value.length;
        if (len > maxChars) {
            counter.textContent = len + ' / ' + maxChars + ' - Досягнуто максимум символів';
            counter.classList.add('max');
        } else {
            counter.textContent = len + ' / ' + maxChars;
            counter.classList.remove('max');
        }
    }

    function toggleButton() {
        const len = textarea.value.length;
        const disabled = (len === 0 || len > maxChars);
        button.disabled = disabled;
        button.classList.toggle('disabled', disabled);
    }

    function handleInput() {
        updateCounter();
        toggleButton();
    }

    if (textarea && counter && button) {
        textarea.addEventListener('input', handleInput);
        textarea.addEventListener('paste', () => setTimeout(handleInput, 0));
        textarea.addEventListener('focus', () => { counter.style.display = 'block'; });
        textarea.addEventListener('blur', () => { counter.style.display = 'none'; });
        handleInput();

 
        textarea.addEventListener('keydown', function(e) {
            if(e.key === 'Enter' && textarea.value.length > maxChars) {
                e.preventDefault();
            }
        });
    }

    if(chatForm) {
      
        chatForm.addEventListener('submit', function(e) {
            const text = textarea.value.trim();
            if(text.length === 0 || text.length > maxChars) {
                e.preventDefault(); 
                e.stopPropagation();
                alert('Повідомлення не може бути більше ' + maxChars + ' символів.');
                return false;
            }
        });

       
        button.addEventListener('click', function(e) {
            if(button.disabled || textarea.value.length > maxChars) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    }
})();
</script>";

} else {
    $out .= '<div class="chat-empty"><p>Виберіть чат, щоб почати переписку</p></div>';
}
$out .= '</div>';

// Правая колонка
$out .= '<div class="chat-info">';
if ($currentChat) {
    $otherUser = ($currentChat['user_one'] == $user_id) ? $currentChat['user_two'] : $currentChat['user_one'];
    $resUser = mysqli_query($dblink, "SELECT fname, lname, avatar FROM users WHERE idx=" . (int)$otherUser);
    $u = $resUser ? mysqli_fetch_assoc($resUser) : null;
    $userName = $u ? htmlspecialchars($u['fname'] . ' ' . $u['lname']) : 'Користувач';
    $userAvatar = (!empty($u['avatar'])) ? $u['avatar'] : '/avatars/ava.png';

    // Верхний блок
    $out .= '<div class="chat-info-top">';
    $out .= '<h3 class="chat-info-title">Інформація про користувача</h3>';
    $out .= '<div class="chat-info-user">';
    $out .= '<img src="'.htmlspecialchars($userAvatar).'" alt="Аватар" class="chat-info-avatar">';
    $out .= '<div class="chat-info-name">'.$userName.'</div>';
    $out .= '</div>';
    $out .= '<div class="chat-info-buttons-top">
    <button class="icon-btn" data-tooltip="Профіль">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.2c-3.3 0-9.9 1.7-9.9 5v2.7h19.8V19c0-3.3-6.6-5-9.9-5z"/>
        </svg>
    </button>
    <button class="icon-btn" data-tooltip="Дія 2">
        
    </button>
    <button class="icon-btn" data-tooltip="Дія 3">
        
    </button>
</div>
';
    $out .= '</div>'; // .chat-info-top

    // Нижний блок
    $out .= '<div class="chat-info-bottom">';
    $out .= '<button class="btn btn-danger full-width">Поскаржитись</button>';
    $out .= '<button class="btn btn-danger full-width">Заблокувати</button>';
    $out .= '<button class="btn btn-danger full-width">Видалити чат</button>';
    $out .= '</div>'; // .chat-info-bottom

} else {
    $out .= '<p>Виберіть чат</p>';
}
$out .= '</div></div>';

$out .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    let userId = '.(int)$user_id.';
    const chatContainer = document.getElementById("chatMessages");
    const chatForm = document.getElementById("chatForm");
    const chatId = chatContainer ? parseInt(chatContainer.dataset.chatId) : 0;
    let lastMsgId = 0;
    let fetching = false;
    const pollingInterval = 1500;
    let userIsScrolling = false;
    let scrollTimer;

    const scrollKey = "chatScroll_" + chatId;
    function saveScrollPosition() {
        if (chatContainer) localStorage.setItem(scrollKey, chatContainer.scrollTop);
    }
    function restoreScrollPosition() {
        const saved = localStorage.getItem(scrollKey);
        if (chatContainer && saved !== null) {
            chatContainer.scrollTop = parseInt(saved);
        }
    }

 
    function escapeHTML(s) {
        return s.replace(/[&<>"\']/g, ch => ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;" })[ch]);
    }

    function isNearBottom(container, threshold = 50) {
        return (container.scrollHeight - container.scrollTop - container.clientHeight) < threshold;
    }

    function updateChatListLastMessage(chatId, message, time) {
        const chatItem = document.querySelector(".chat-item[data-chat-id=\'" + chatId + "\']");
        if (!chatItem) return;
        const msgDiv = chatItem.querySelector(".chat-item-lastmsg .message-text");
        const timeDiv = chatItem.querySelector(".chat-item-lastmsg .time");
        if (msgDiv) msgDiv.textContent = message;
        if (timeDiv) timeDiv.textContent = time;
    }

    function appendDateDividerIfNeeded(dateStr, label) {
        if (!chatContainer.lastDate || chatContainer.lastDate !== dateStr) {
            const divider = document.createElement("div");
            divider.className = "date-divider";
            divider.innerHTML = "<span>" + label + "</span>";
            chatContainer.appendChild(divider);
            chatContainer.lastDate = dateStr;
        }
    }


    function addMessageToDOM(msg, options = {}) {
        if (!chatContainer || !msg.idx) return;
        if (chatContainer.querySelector(".message[data-idx=\'" + msg.idx + "\']")) return;

        const wasAtBottom = isNearBottom(chatContainer);

        const msgDate = new Date(msg.idtadd);
        const dateStr = msgDate.toISOString().split("T")[0];
        const today = new Date().toISOString().split("T")[0];
        const yesterday = new Date(Date.now() - 86400000).toISOString().split("T")[0];
        const dateLabel = (dateStr === today) ? "Сьогодні" : (dateStr === yesterday ? "Вчора" : msgDate.toLocaleDateString("uk-UA"));
        appendDateDividerIfNeeded(dateStr, dateLabel);

        const div = document.createElement("div");
        const senderId = Number(msg.sender_idx);
        div.className = "message " + (senderId === userId ? "me" : "other");
        div.dataset.idx = msg.idx;

        const time = msgDate.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
        const safeMsg = escapeHTML(msg.message).replace(/\n/g, "<br>");
        div.innerHTML = "<div class=\'msg-text\'>" + safeMsg + "</div><div class=\'msg-time\'>" + time + "</div>";

        chatContainer.appendChild(div);

        lastMsgId = Math.max(lastMsgId, parseInt(msg.idx) || 0);

        const shortMsg = msg.message.length > 40 ? msg.message.substr(0, 40) + "..." : msg.message;
        updateChatListLastMessage(msg.chat_idx, shortMsg, time);

    
        if (msg.chat_idx !== chatId) {
            const chatItem = document.querySelector(".chat-item[data-chat-id=\'" + msg.chat_idx + "\']");
            if (chatItem) {
                let badge = chatItem.querySelector(".unread-badge");
                if (!badge) {
                    badge = document.createElement("div");
                    badge.className = "unread-badge";
                    badge.textContent = 1;
                    chatItem.appendChild(badge);
                } else {
                    badge.style.display = "block";
                    badge.textContent = parseInt(badge.textContent || 0) + 1;
                }
            }
        }

      
        if (options.scrollToBottom) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        } else if (!userIsScrolling && wasAtBottom && senderId === userId) {
          
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    }

   
    async function safeFetchJson(res) {
        let t = await res.text();
        try { return JSON.parse(t); }
        catch(e) { console.error("Invalid JSON:", t); throw new Error("invalid json"); }
    }

    async function fetchNewMessages() {
        if(!chatId || fetching) return;
        fetching = true;
        try {
            const resp = await fetch("/messenger.php?action=get_new_messages&chat=" + chatId + "&last_msg_id=" + lastMsgId, { credentials: "same-origin" });
            const data = await safeFetchJson(resp);
            if(data.status === "ok" && Array.isArray(data.messages)) {
                for(let m of data.messages) addMessageToDOM(m, { scrollToBottom: false });
            } else if(data.status === "error" && data.msg === "unauthorized") {
                window.location.reload();
            }
        } catch(err) {
            console.error("fetchNewMessages error:", err);
        } finally { fetching = false; }
    }

    async function sendMessage(text) {
        if(!chatId) return;
        const fd = new FormData();
        fd.append("chat_idx", chatId);
        fd.append("message", text);
        try {
            const resp = await fetch("/messenger.php?action=send_message", { method: "POST", credentials: "same-origin", body: fd });
            const data = await safeFetchJson(resp);
            if(data.status === "ok" && Array.isArray(data.messages)) {
                for(let m of data.messages) addMessageToDOM(m, { scrollToBottom: true });
            } else {
                alert("Не вдалося надіслати повідомлення");
            }
        } catch(err) {
            console.error("Send message fetch error:", err);
            alert("Помилка мережі");
        }
    }

    function markChatAsRead(chatId) {
        const chatItem = document.querySelector(".chat-item[data-chat-id=\'" + chatId + "\']");
        if (chatItem) {
            const badge = chatItem.querySelector(".unread-badge");
            if (badge) badge.style.display = "none";
        }
        fetch("/messenger.php?action=mark_read&chat=" + chatId, { credentials: "same-origin" });
    }

    if(chatForm) {
        const textarea = chatForm.querySelector("textarea");
        const sendButton = chatForm.querySelector("button");
        const baseHeight = 40;
        textarea.style.height = baseHeight + "px";
        textarea.style.overflowY = "hidden";

        const style = window.getComputedStyle(textarea);
        const paddingTop = parseInt(style.paddingTop), paddingBottom = parseInt(style.paddingBottom),
              borderTop = parseInt(style.borderTopWidth), borderBottom = parseInt(style.borderBottomWidth);
        const extraHeight = paddingTop + paddingBottom + borderTop + borderBottom;

        function updateTextarea() {
            textarea.style.height = baseHeight + "px";
            const maxHeight = 200;
            let newHeight = textarea.scrollHeight;
            if (newHeight + extraHeight > maxHeight) { newHeight = maxHeight - extraHeight; textarea.style.overflowY = "auto"; }
            else { textarea.style.overflowY = "hidden"; }
            textarea.style.height = newHeight + "px";
        }

        function updateSendButton() {
            sendButton.disabled = (textarea.value.trim().length === 0);
        }

        textarea.addEventListener("input", function() { updateTextarea(); updateSendButton(); });
        chatForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const text = textarea.value.trim();
            if(!text) return;
            sendMessage(text);
            textarea.value = "";
            updateTextarea();
            updateSendButton();
        });
    }

 
    if(chatContainer) {
        restoreScrollPosition();
        chatContainer.addEventListener("scroll", () => {
            userIsScrolling = !isNearBottom(chatContainer, 50);
            saveScrollPosition();
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => { userIsScrolling = false; }, 3000);
        });
    }

    document.body.classList.add("noscroll");

    if(chatId) {
        markChatAsRead(chatId);
        fetchNewMessages();
        setInterval(fetchNewMessages, pollingInterval);
    }
});
</script>';


View_Add(Page_Up("Чати"));
View_Add(Menu_Up());
View_Add('<div class="outmsg">');
View_Add($out);
View_Add('</div>');
View_Out();
