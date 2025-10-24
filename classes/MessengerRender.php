<?php

class MessengerRender {
    private $dblink;

    public function __construct($dblink) {
        $this->dblink = $dblink;
    }

    public function renderContainer($user_id, $userChats, $currentChat, $messages, $chat_id, $type = 0) {
        $out = '<link rel="stylesheet" href="/assets/css/msg.css">';

        $isMobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT']);

        // Мобильная версия
        if ($isMobile) {
            $out .= '<div class="mobile-chat-container">';

            // Список чатов
            $out .= '<div class="mobile-chat-list'.(!$chat_id ? ' active' : '').'">';
            $out .= $this->renderChatList($user_id, $userChats, $chat_id, $type);
            $out .= '</div>';

            // Окно чата
            $out .= '<div class="mobile-chat-window'.($chat_id ? ' active' : '').'">';
            $out .= $this->renderChatWindow($user_id, $currentChat, $messages, $chat_id, $type);
            $out .= '</div>';

            $out .= '</div>';

        } else {
            $chatContainerClass = 'messenger-container' . (!$currentChat ? ' no-chat-selected' : '');
            $out .= '<div class="'.$chatContainerClass.'">';
            $out .= $this->renderChatList($user_id, $userChats, $chat_id, $type);
            $out .= $this->renderChatWindow($user_id, $currentChat, $messages, $chat_id, $type);
            $out .= $this->renderChatInfo($user_id, $currentChat);
            $out .= '</div>';
        }

        $out .= $this->renderScripts($user_id, $chat_id);
        return $out;
    }

    private function renderChatList($user_id, $userChats, $chat_id, $type = 0) {
        $title = 'Мої чати';
        switch ($type) {
            case 1:
                $title = 'Особисті чати';
                break;
            case 2:
                $title = 'Робочі чати';
                break;
            case 3:
                $title = 'Підтримка';
                break;
        }

        $out = '<div class="chat-list">';
        $out .= '<h3>' . $title . '</h3>';
        if ($type != 3) {
            $out .= '
<div class="chat-type-switch">
    <a href="/messenger.php?type=1" class="chat-type-btn ' . ($type == 1 ? 'active' : '') . '">
        <div class="chat-type-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16">
                <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.95.77-2.966C.744 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7M5 8a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
            </svg>
        </div>
        <div class="chat-type-label">Особисті</div>
    </a>
    <a href="/messenger.php?type=2" class="chat-type-btn ' . ($type == 2 ? 'active' : '') . '">
        <div class="chat-type-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16">
                <path d="M6.5 1A1.5 1.5 0 0 0 5 2.5V3H1.5A1.5 1.5 0 0 0 0 4.5v1.384l7.614 2.03a1.5 1.5 0 0 0 .772 0L16 5.884V4.5A1.5 1.5 0 0 0 14.5 3H11v-.5A1.5 1.5 0 0 0 9.5 1zm0 1h3a.5.5 0 0 1 .5.5V3H6v-.5a.5.5 0 0 1 .5-.5"/>
                <path d="M0 12.5A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5V6.85L8.129 8.947a.5.5 0 0 1-.258 0L0 6.85z"/>
            </svg>
        </div>
        <div class="chat-type-label">Робочі</div>
    </a>
</div>
';
        }

        // Если нет чатов
        if (empty($userChats)) {
            $out .= '<div class="no-chats">Немає чатів</div></div>';
            return $out;
        }

        foreach ($userChats as $chat) {
            // фильтрация по типу
            if ($type > 0) {
                if ((int)$chat['type'] !== $type) continue;
            } else {
                if (!in_array((int)$chat['type'], [1, 2])) continue;
            }

            $isActive = ($chat['idx'] == $chat_id) ? 'active' : '';
            $otherUserId = ($chat['user_one'] == $user_id) ? $chat['user_two'] : $chat['user_one'];

            $res = mysqli_query($this->dblink, "SELECT fname, lname, avatar FROM users WHERE idx = " . (int)$otherUserId);
            $u = $res ? mysqli_fetch_assoc($res) : null;

            $name = $u ? htmlspecialchars(trim($u['fname'] . ' ' . $u['lname'])) : 'Користувач';
            $avatar = (!empty($u['avatar'])) ? $u['avatar'] : '/avatars/ava.png';

            // Последнее сообщение
            $lastMsgData = $chat['last_message'] ?? null;
            $lastMsgText = $lastMsgData['message'] ?? 'Немає повідомлень';
            $lastMsgTime = $lastMsgData['idtadd'] ?? '';
            $time = $lastMsgTime ? date('H:i', strtotime($lastMsgTime)) : '';

            $link = '/messenger.php?type=' . (int)$chat['type'] . '&chat=' . (int)$chat['idx'];


            $out .= '<div class="chat-item ' . $isActive . '" data-link="' . htmlspecialchars($link) . '" data-chat-id="' . $chat['idx'] . '">';
            $out .= '  <img src="' . htmlspecialchars($avatar) . '" alt="Аватар" class="chat-item-avatar">';
            $out .= '  <div class="chat-item-info">';
            $out .= '    <div class="chat-item-name">' . $name . '</div>';
            $out .= '    <div class="chat-item-lastmsg">';
            $out .= '      <div class="message-text">' . htmlspecialchars(mb_strimwidth($lastMsgText, 0, 100, '...')) . '</div>';
            $out .= '      <div class="time">' . $time . '</div>';
            $out .= '    </div>';
            $out .= '  </div>';
            $out .= '</div>';
        }

        return $out . '</div>';
    }


    public function renderChatWindow($user_id, $currentChat, $messages, $chat_id, $type) {
        $out = '<div class="chat-window">';
        if (!$currentChat) {
            return $out.'<div class="chat-empty"><p>Виберіть чат, щоб почати переписку</p></div></div>';
        }

        $otherUser = ($currentChat['user_one'] == $user_id) ? $currentChat['user_two'] : $currentChat['user_one'];
        $res = mysqli_query($this->dblink, "SELECT fname, lname, avatar FROM users WHERE idx=".(int)$otherUser);
        $u = $res ? mysqli_fetch_assoc($res) : null;
        $name = $u ? htmlspecialchars($u['fname'].' '.$u['lname']) : 'Користувач';
        $avatar = (!empty($u['avatar'])) ? $u['avatar'] : '/avatars/ava.png';

        // Шапка
        $out .= '
    <div class="chat-header">
        <div class="chat-header-left" style="display:flex; align-items:center; gap:10px;">
            <img src="'.htmlspecialchars($avatar).'" alt="Аватар" class="chat-header-avatar" style="width:40px;height:40px;border-radius:50%;">
            <span>'.$name.'</span>
        </div>
        <div class="chat-header-right"></div>
    </div>
    ';

        // Сообщения
        $out .= '<div class="chat-messages" id="chatMessages" data-chat-id="'.(int)$chat_id.'">';
        $lastDate = null;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($messages as $msg) {
            $time = date('H:i', strtotime($msg['idtadd']));
            $date = date('Y-m-d', strtotime($msg['idtadd']));
            $dateLabel = ($date == $today) ? 'Сьогодні' : (($date == $yesterday) ? 'Вчора' : date('d.m.Y', strtotime($msg['idtadd'])));
            if ($lastDate !== $date) {
                $out .= '<div class="date-divider"><span>'.$dateLabel.'</span></div>';
                $lastDate = $date;
            }

            // Класс для сообщения
            $isJoin = ((int)$msg['sender_idx'] === -1 && $msg['message'] === "Спеціаліст приєднався до чату");
            $cls = $isJoin ? 'system join' : (((int)$msg['sender_idx'] === (int)$user_id) ? 'me' : 'other');

            $out .= '
        <div class="message '.$cls.'" data-idx="'.(int)$msg['idx'].'">
            <div class="msg-text">'.nl2br(htmlspecialchars($msg['message'])).'</div>
            <div class="msg-time">'.$time.'</div>
        </div>';
        }
        $out .= '</div>';

        if ((int)$currentChat['user_two'] === -1 && $type === 3 && $user_id === -1) {
            $hasJoin = false;
            foreach ($messages as $msg) {
                if ((int)$msg['sender_idx'] === -1 && $msg['message'] === "Спеціаліст приєднався до чату") {
                    $hasJoin = true;
                    break;
                }
            }

            if (!$hasJoin) {
                $out .= '<div class="chat-join-container">
                <button id="joinChatBtn" class="join-chat-btn">Приєднатися до чату</button>
            </div>';
            } else {
                $out .= $this->renderInputField($chat_id);
            }
        } else {
            $out .= $this->renderInputField($chat_id);
        }

        return $out.'</div>';
    }

    private function renderInputField($chat_id) {
        return '
    <form class="chat-input" id="chatForm" method="post">
        <input type="hidden" name="chat_idx" value="'.(int)$chat_id.'">
        <div class="input-top">
            <div id="charCounter">0/2000</div>
        </div>
        <div class="input-area">
            <textarea name="message" id="chatMessage" placeholder="Повідомлення..." required></textarea>
            <button type="submit" id="sendBtn" disabled>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     fill="currentColor" viewBox="0 0 16 16">
                    <path d="M15.964.686a.5.5 0 0 0-.65-.65L.767 5.855H.766l-.452.18a.5.5 0 0 0-.082.887l.41.26.001.002 4.995 3.178 3.178 4.995.002.002.26.41a.5.5 0 0 0 .886-.083zm-1.833 1.89L6.637 10.07l-.215-.338a.5.5 0 0 0-.154-.154l-.338-.215 7.494-7.494 1.178-.471z"/>
                </svg>
            </button>
        </div>
    </form>';
    }




    private function renderChatInfo($user_id, $currentChat) {
        $out = '<div class="chat-info">';

        if (!$currentChat) return $out.'<p>Виберіть чат</p></div>';

        $chatType = isset($currentChat['type']) ? (int)$currentChat['type'] : 0;

        $otherUser = ($currentChat['user_one'] == $user_id) ? $currentChat['user_two'] : $currentChat['user_one'];
        $resUser = mysqli_query($this->dblink, "SELECT fname, lname, avatar FROM users WHERE idx=" . (int)$otherUser);
        $u = $resUser ? mysqli_fetch_assoc($resUser) : null;

        $userName = $u ? htmlspecialchars(trim($u['fname'] . ' ' . $u['lname'])) : (($otherUser < 0) ? 'Гість #' . abs($otherUser) : 'Користувач');
        $userAvatar = (!empty($u['avatar'])) ? $u['avatar'] : '/avatars/ava.png';


        $out .= '<div class="chat-info-top">
                <h3 class="chat-info-title">Інформація про користувача</h3>
                <div class="chat-info-user">
                    <img src="'.htmlspecialchars($userAvatar).'" alt="Аватар" class="chat-info-avatar">
                    <div class="chat-info-name">'.$userName.'</div>
                </div>';

        if ($chatType === 3) {


            if ($user_id == -1) {
                if ($otherUser > 0) {
                    $out .= '<div class="chat-info-buttons-top">
                            <a href="/userprofile.php?idx='.(int)$otherUser.'" class="icon-btn" data-tooltip="Профіль">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.2c-3.3 0-9.9 1.7-9.9 5v2.7h19.8V19c0-3.3-6.6-5-9.9-5z"/>
                                </svg>
                            </a>
                         </div>';
                } else {
                    // Гость
                    $out .= '<div class="chat-info-guest-id">Гість #' . abs($otherUser) . '</div>';
                }

                // Нижние кнопки для поддержки
                $out .= '</div>
                    <div class="chat-info-bottom">
                        <button class="btn btn-danger full-width">Завершити чат</button>
                        <button class="btn btn-danger full-width">Заблокувати</button>
                        <button class="btn btn-danger full-width">Видалити чат</button>
                    </div>';
            }

            else {
                $out .= '</div>';
            }

        } else {
            $out .= '<div class="chat-info-buttons-top">
                    <a href="/userprofile.php?idx='.(int)$otherUser.'" class="icon-btn" data-tooltip="Профіль">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.2c-3.3 0-9.9 1.7-9.9 5v2.7h19.8V19c0-3.3-6.6-5-9.9-5z"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div class="chat-info-bottom">
                <button class="btn btn-danger full-width">Поскаржитись</button>
                <button class="btn btn-danger full-width">Заблокувати</button>
                <button class="btn btn-danger full-width">Видалити чат</button>
            </div>';
        }

        return $out.'</div>';
    }


    private function renderScripts($user_id, $chat_id) {
        ob_start();
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const userId = <?= (int)$user_id ?>;

                let chatContainer = document.getElementById("chatMessages");
                let chatForm = document.getElementById("chatForm");
                const urlParams = new URLSearchParams(window.location.search);
                let chatId = chatContainer ? parseInt(chatContainer.dataset.chatId || 0) : (urlParams.get("chat") ? parseInt(urlParams.get("chat")) : 0);


                // ===
                const joinBtn = document.getElementById("joinChatBtn");
                if (joinBtn) {
                    joinBtn.addEventListener("click", async function() {
                        if (!chatId) return;
                        try {
                            const formData = new FormData();
                            formData.append("chat_idx", chatId);
                            formData.append("message", "Спеціаліст приєднався до чату");
                            const res = await fetch("messenger.php?action=send_message", { method: "POST", body: formData });
                            const data = await res.json();
                            if (data.status === "ok") {

                                joinBtn.parentElement.remove();

                                const inputHTML = `<?php echo addslashes($this->renderInputField($chat_id)); ?>`;
                                const chatWrapper = document.querySelector(".chat-window");
                                chatWrapper.insertAdjacentHTML('beforeend', inputHTML);

                                const newForm = document.getElementById("chatForm");
                                if (newForm) bindFormHandlers(newForm);

                                if (Array.isArray(data.messages)) {
                                    data.messages.forEach(msg => appendMessage(msg));
                                }
                            }
                        } catch (e) { console.error(e); }
                    });
                }

                function appendMessage(msg) {
                    if (!chatContainer) return;
                    if (chatContainer.querySelector(`.message[data-idx="${msg.idx}"]`)) return;

                    const isJoinSystem = msg.message.includes("приєднався до чату");
                    const cls = isJoinSystem
                        ? "system join"
                        : ((parseInt(msg.sender_idx) === userId) ? "me" : "other");

                    const div = document.createElement("div");
                    div.className = "message " + cls;
                    div.dataset.idx = msg.idx;

                    if (isJoinSystem) {
                        div.innerHTML = `<div class="msg-text">${escapeHTML(msg.message)}</div>`;
                    } else {
                        const time = msg.idtadd ? new Date(msg.idtadd).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) : '';
                        div.innerHTML = `<div class="msg-text">${escapeHTML(msg.message)}</div>` + (time ? `<div class="msg-time">${time}</div>` : '');
                    }

                    chatContainer.appendChild(div);
                    chatContainer.scrollTop = chatContainer.scrollHeight;

                    updateChatListWithMessage(chatId, msg);
                }

                // ====

                let lastMsgId = 0, fetching = false;
                const pollingInterval = 1500;
                let pollingTimer = null;
                let userIsScrolling = false, scrollTimer = null;

                // блоки
                let chatList = document.querySelector(".chat-list");
                let chatWindow = document.querySelector(".chat-window");
                let chatInfo = document.querySelector(".chat-info");
                const mobileListWrap = document.querySelector(".mobile-chat-list");
                const mobileWindowWrap = document.querySelector(".mobile-chat-window");
                const mobileInfoWrap = document.querySelector(".mobile-chat-info");

                // Функция обновления записи в списке чатов
                function updateChatListWithMessage(chatIdParam, msg) {
                    try {
                        const id = String(chatIdParam || 0);

                        if (!chatList) chatList = document.querySelector(".chat-list");
                        if (!chatList) return;

                        const item = chatList.querySelector(`.chat-item[data-chat-id="${id}"]`);
                        if (!item) return;

                        // Обновляем текст последнего сообщения
                        const textEl = item.querySelector('.chat-item-lastmsg .message-text');
                        let text = (msg && msg.message) ? String(msg.message) : '';
                        text = text.replace(/\s+/g, ' ').trim();
                        const maxLen = 100;
                        if (text.length > maxLen) text = text.slice(0, maxLen - 3) + '...';
                        if (textEl) textEl.textContent = text || 'Немає повідомлень';


                        const timeEl = item.querySelector('.chat-item-lastmsg .time');
                        let timeStr = '';
                        if (msg && msg.idtadd) {
                            const dt = new Date(msg.idtadd);
                            if (!isNaN(dt.getTime())) {
                                const hh = String(dt.getHours()).padStart(2, '0');
                                const mm = String(dt.getMinutes()).padStart(2, '0');
                                timeStr = `${hh}:${mm}`;
                            }
                        }
                        if (!timeStr) {
                            const now = new Date();
                            timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                        }
                        if (timeEl) timeEl.textContent = timeStr;


                        const switchBlock = chatList.querySelector('.chat-type-switch');
                        if (switchBlock) {

                            const next = switchBlock.nextElementSibling;
                            if (next && next !== item) {
                                chatList.insertBefore(item, next);
                            } else if (next === item) {

                            } else {

                                switchBlock.insertAdjacentElement('afterend', item);
                            }
                        } else {
                            const firstChat = chatList.querySelector('.chat-item');
                            if (firstChat && firstChat !== item) {
                                chatList.insertBefore(item, firstChat);
                            } else if (!firstChat) {

                                chatList.appendChild(item);
                            }
                        }

                        item.classList.add('highlighted');
                        setTimeout(() => item.classList.remove('highlighted'), 600);

                    } catch (e) {
                        console.error("updateChatListWithMessage error:", e);
                    }
                }



                function showChatList() {
                    if (window.innerWidth <= 600) {
                        mobileListWrap?.classList.add("active");
                        mobileWindowWrap?.classList.remove("active");
                        mobileInfoWrap?.classList.remove("active");
                    } else {
                        chatList?.classList.add("active");
                        chatWindow?.classList.remove("active");
                        chatInfo?.classList.remove("active");
                    }
                }
                function showChatWindow() {
                    if (window.innerWidth <= 600) {
                        mobileListWrap?.classList.remove("active");
                        mobileWindowWrap?.classList.add("active");
                        mobileInfoWrap?.classList.remove("active");
                    } else {
                        chatList?.classList.remove("active");
                        chatWindow?.classList.add("active");
                        chatInfo?.classList.remove("active");
                    }
                }
                function showChatInfo() {
                    if (window.innerWidth <= 600) {
                        mobileListWrap?.classList.remove("active");
                        mobileWindowWrap?.classList.remove("active");
                        mobileInfoWrap?.classList.add("active");
                    } else {
                        chatList?.classList.remove("active");
                        chatWindow?.classList.remove("active");
                        chatInfo?.classList.add("active");
                    }
                }

                function storageKeyForChat(id) { return "chatScroll_" + (parseInt(id) || 0); }
                function saveScrollPosition() { if (chatContainer && chatId) localStorage.setItem(storageKeyForChat(chatId), String(chatContainer.scrollTop)); }
                function getSavedScroll() {
                    try {
                        const v = localStorage.getItem(storageKeyForChat(chatId));
                        return v === null ? null : parseInt(v, 10);
                    } catch (e) { return null; }
                }

                function restoreScrollPositionWithRetry(maxAttempts = 6) {
                    if (!chatContainer) return;
                    const saved = getSavedScroll();
                    if (saved === null || isNaN(saved)) return;

                    let attempt = 0;
                    function tryRestore() {
                        attempt++;
                        const maxTop = Math.max(0, chatContainer.scrollHeight - chatContainer.clientHeight);
                        if (maxTop > 0 || attempt >= maxAttempts) {
                            chatContainer.scrollTop = Math.min(saved, maxTop);
                        } else {
                            setTimeout(tryRestore, 80 * attempt);
                        }
                    }
                    tryRestore();
                }

                function isNearBottom(c, t = 50) { return (c.scrollHeight - c.scrollTop - c.clientHeight) < t; }

                window.addEventListener("beforeunload", saveScrollPosition);

                function stopPolling() {
                    if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
                    fetching = false;
                }

                function escapeHTML(s) { return String(s).replace(/[&<>"']/g, m => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m])); }

                async function fetchNewMessages() {
                    if (fetching || !chatId || !chatContainer) return;
                    fetching = true;
                    try {
                        const res = await fetch(`messenger.php?action=get_new_messages&chat=${chatId}&last_msg_id=${lastMsgId}`);
                        if (!res.ok) throw new Error("Network error");
                        const data = await res.json();

                        if (data.status === "ok" && Array.isArray(data.messages) && data.messages.length > 0) {
                            const oldBottom = isNearBottom(chatContainer, 50);

                            data.messages.forEach(msg => {
                                const msgId = parseInt(msg.idx) || 0;

                                if (chatContainer.querySelector(`.message[data-idx="${msgId}"]`)) {
                                    lastMsgId = Math.max(lastMsgId, msgId);
                                    return;
                                }

                                const isJoinSystem = msg.message.includes("приєднався до чату");
                                const cls = isJoinSystem
                                    ? "system join"
                                    : ((parseInt(msg.sender_idx) === userId) ? "me" : "other");

                                const div = document.createElement("div");
                                div.className = "message " + cls;
                                div.dataset.idx = msgId;

                                if (isJoinSystem) {
                                    div.innerHTML = `<div class="msg-text">${escapeHTML(msg.message)}</div>`;
                                } else {
                                    const time = new Date(msg.idtadd).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
                                    div.innerHTML = `<div class="msg-text">${escapeHTML(msg.message)}</div><div class="msg-time">${time}</div>`;
                                }

                                chatContainer.appendChild(div);
                                lastMsgId = Math.max(lastMsgId, msgId);

                                // Обновляем строку чата в списке
                                updateChatListWithMessage(chatId, msg);
                            });

                            if (oldBottom) chatContainer.scrollTop = chatContainer.scrollHeight;
                        }
                    } catch (e) {
                        console.error("Fetch error:", e);
                    }
                    fetching = false;
                }


                function bindFormHandlers(formEl) {
                    if (!formEl || formEl._bound) return;
                    formEl._bound = true;

                    const textarea = formEl.querySelector("textarea");
                    const sendBtn = formEl.querySelector("button[type='submit']");
                    const counter = document.getElementById("charCounter");
                    const maxLen = 2000;
                    const minHeight = 41;
                    const maxHeight = 150;

                    function updateButtonState() {
                        const len = textarea.value.length;
                        if (sendBtn) sendBtn.disabled = (len === 0 || len > maxLen);
                        if (counter) {
                            counter.textContent = `${len}/${maxLen}`;
                            counter.style.color = len > maxLen ? "red" : "";
                        }
                    }
                    function autoResize() {
                        textarea.style.height = minHeight + "px";
                        if (!textarea.value) { textarea.style.overflowY = "hidden"; return; }
                        const newHeight = Math.min(textarea.scrollHeight + 2, maxHeight);
                        textarea.style.height = newHeight + "px";
                        textarea.style.overflowY = (textarea.scrollHeight > maxHeight) ? "auto" : "hidden";
                    }

                    if (counter) counter.style.display = "none";
                    textarea.addEventListener("focus", () => {
                        if (counter) counter.style.display = "block";
                        const sb = document.querySelector(".scroll-down-btn");
                        if (sb) sb.style.display = "none";
                    });
                    textarea.addEventListener("blur", () => {
                        if (counter) counter.style.display = "none";
                    });

                    textarea.addEventListener("input", () => { autoResize(); updateButtonState(); });

                    textarea.style.height = minHeight + "px";
                    updateButtonState();

                    formEl.addEventListener("submit", async e => {
                        e.preventDefault();
                        if (formEl._sending) return;
                        const message = textarea.value.trim();
                        if (!message || message.length > maxLen) return;

                        formEl._sending = true;
                        if (sendBtn) sendBtn.disabled = true;

                        try {
                            const formData = new FormData(formEl);
                            const res = await fetch("messenger.php?action=send_message", { method: "POST", body: formData });
                            const data = await res.json();
                            if (data.status === "ok" && Array.isArray(data.messages)) {
                                data.messages.forEach(msg => {
                                    const msgId = parseInt(msg.idx) || 0;
                                    if (chatContainer.querySelector(`.message[data-idx="${msgId}"]`)) {
                                        lastMsgId = Math.max(lastMsgId, msgId);
                                        return;
                                    }
                                    const cls = (parseInt(msg.sender_idx) === userId) ? 'me' : 'other';
                                    const time = new Date(msg.idtadd).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
                                    const div = document.createElement('div');
                                    div.className = 'message ' + cls;
                                    div.dataset.idx = msgId;
                                    div.innerHTML = `<div class="msg-text">${escapeHTML(msg.message)}</div><div class="msg-time">${time}</div>`;
                                    chatContainer.appendChild(div);
                                    lastMsgId = Math.max(lastMsgId, msgId);

                                    // Обновляем строку чата в списке после отправки сообщения
                                    updateChatListWithMessage(chatId, msg);
                                });
                                textarea.value = "";
                                autoResize();
                                updateButtonState();
                                if (chatContainer && !isNearBottom(chatContainer, 50)) {
                                    const sb = document.querySelector(".scroll-down-btn");
                                    if (sb) sb.style.display = "block";
                                }
                                if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
                            }
                        } catch (err) {
                            console.error("Send error:", err);
                        } finally {
                            formEl._sending = false;
                            updateButtonState();
                        }
                    });
                }

                function initChatFor(newChatId, html) {
                    stopPolling();
                    chatId = parseInt(newChatId) || 0;

                    if (html !== undefined && html !== null) {
                        if (window.innerWidth <= 600 && mobileWindowWrap) {
                            mobileWindowWrap.innerHTML = html;
                        } else {
                            const desktopWrapper = document.querySelector(".chat-window");
                            if (desktopWrapper) desktopWrapper.innerHTML = html;
                        }
                    }

                    chatContainer = document.getElementById("chatMessages");
                    chatForm = document.getElementById("chatForm");

                    lastMsgId = 0;
                    if (chatContainer) {
                        chatContainer.querySelectorAll(".message").forEach(m => {
                            const id = parseInt(m.dataset.idx || 0);
                            if (id) lastMsgId = Math.max(lastMsgId, id);
                        });
                    }

                    // кнопка вниз
                    let scrollBtn = document.querySelector(".scroll-down-btn");
                    if (!scrollBtn && chatContainer && chatContainer.parentElement) {
                        scrollBtn = document.createElement("button");
                        scrollBtn.className = "scroll-down-btn";
                        scrollBtn.innerHTML = '<img src="/assets/images/avaarrow.png" alt="Вниз" style="width:20px;height:20px">';
                        Object.assign(scrollBtn.style, {
                            position: "absolute",
                            bottom: "80px",
                            right: "20px",
                            zIndex: "9",
                            width: "50px",
                            height: "50px",
                            borderRadius: "50%",
                            background: "#e4e4e4",
                            border: "none",
                            cursor: "pointer",
                            display: "none",
                            boxShadow: "0 4px 12px rgba(0,0,0,0.4)"
                        });
                        chatContainer.parentElement.appendChild(scrollBtn);
                        scrollBtn.addEventListener("click", () => {
                            if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
                            scrollBtn.style.display = "none";
                        });
                    }

                    if (chatContainer) {
                        restoreScrollPositionWithRetry();
                        chatContainer.style.visibility = "visible";
                    }

                    if (chatContainer && !chatContainer._hasScroll) {
                        chatContainer._hasScroll = true;
                        chatContainer.addEventListener("scroll", () => {
                            userIsScrolling = !isNearBottom(chatContainer, 50);
                            saveScrollPosition();
                            clearTimeout(scrollTimer);
                            scrollTimer = setTimeout(() => { userIsScrolling = false; }, 3000);
                            if (document.activeElement !== (chatForm ? chatForm.querySelector("textarea") : null)) {
                                const sb = document.querySelector(".scroll-down-btn");
                                if (sb) sb.style.display = isNearBottom(chatContainer, 50) ? "none" : "block";
                            }
                        });
                    }

                    if (chatForm) bindFormHandlers(chatForm);
                    if (pollingTimer) clearInterval(pollingTimer);
                    pollingTimer = setInterval(fetchNewMessages, pollingInterval);
                }

                function attachChatItemHandlers() {
                    document.querySelectorAll(".chat-item").forEach(item => {
                        if (item._hasClick) return;
                        item._hasClick = true;
                        item.addEventListener("click", async e => {
                            e.preventDefault();
                            const id = item.dataset.chatId;
                            const link = item.dataset.link;
                            if (!id) return;

                            const linkParams = new URLSearchParams(link.split("?")[1] || "");
                            const type = linkParams.get("type") ? parseInt(linkParams.get("type")) : 0;
                            chatType = type;

                            const newUrl = `messenger.php?type=${type}&chat=${id}`;
                            history.pushState(null, "", newUrl);

                            if (window.innerWidth <= 600) {
                                showChatWindow();
                            } else {
                                return window.location = newUrl;
                            }

                            try {
                                const res = await fetch(`messenger.php?action=get_chat_html&type=${type}&chat=${id}`);
                                const html = await res.text();
                                initChatFor(id, html);
                            } catch (err) {
                                console.error("Ошибка загрузки чата:", err);
                            }
                        });
                    });
                }

                function attachInfoButton() {
                    const infoButton = document.querySelector(".chat-header-right button");
                    if (infoButton && !infoButton._hasClick) {
                        infoButton._hasClick = true;
                        infoButton.addEventListener("click", () => {
                            if (window.innerWidth <= 600) showChatInfo();
                        });
                    }
                }

                if (window.innerWidth <= 600) {
                    chatId ? showChatWindow() : showChatList();
                } else {
                    chatList?.classList.add("active");
                    chatWindow?.classList.add("active");
                    chatInfo?.classList.add("active");
                }

                attachChatItemHandlers();
                attachInfoButton();

                if (chatId) {
                    if (chatContainer) {
                        initChatFor(chatId, null);
                    } else {
                        (async () => {
                            try {
                                const res = await fetch(`messenger.php?action=get_chat_html&chat=${chatId}`);
                                const html = await res.text();
                                initChatFor(chatId, html);
                                attachInfoButton();
                            } catch (err) {
                                console.error("Ошибка загрузки начального чата:", err);
                            }
                        })();
                    }
                }

                window.addEventListener("popstate", () => {
                    if (window.innerWidth <= 600) {
                        showChatList();
                    } else {
                        location.reload();
                    }
                });

                window.addEventListener("resize", () => {
                    if (window.innerWidth > 600) {
                        chatList?.classList.add("active");
                        chatWindow?.classList.add("active");
                        chatInfo?.classList.add("active");
                    } else {
                        const urlParams2 = new URLSearchParams(window.location.search);
                        const currentChatId = urlParams2.get("chat");
                        currentChatId ? showChatWindow() : showChatList();
                    }
                    attachChatItemHandlers();
                    attachInfoButton();
                });

                setTimeout(() => {
                    attachChatItemHandlers();
                    attachInfoButton();
                    chatForm = document.getElementById("chatForm");
                    if (chatForm) bindFormHandlers(chatForm);
                }, 200);
            });
        </script>
        <?php
        return ob_get_clean();
    }
}