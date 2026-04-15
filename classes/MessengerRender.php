<?php

class MessengerRender
{
    private $dblink;

    public function __construct($dblink)
    {
        $this->dblink = $dblink;
    }

    public function renderContainer($user_id, $userChats, $currentChat, $messages, $chat_id, $type = 2)
    {
        $chatId = (int)$chat_id;
        $mobileView = $chatId > 0 ? 'chat' : 'list';

        $out = '<link rel="stylesheet" href="/assets/css/msg.css">';
        $out .= '<div class="messenger-page messenger-page--workchat" id="messengerRoot" data-chat-id="' . $chatId . '" data-type="' . (int)$type . '" data-mobile-view="' . $mobileView . '">';
        $out .= '<aside class="messenger-sidebar">';
        $out .= $this->renderChatList($user_id, $userChats, $chatId);
        $out .= '</aside>';
        $out .= '<section class="messenger-thread-panel" id="messengerThreadPanel">';
        $out .= $this->renderChatWindow($user_id, $currentChat, $messages, $chatId, $type);
        $out .= '</section>';
        $out .= '<aside class="messenger-info-panel" id="messengerInfoPanel">';
        $out .= $this->renderChatInfo($user_id, $currentChat);
        $out .= '</aside>';
        $out .= '</div>';

        $out .= '<div id="chatImageLightbox" class="chat-lightbox" aria-hidden="true" role="dialog" aria-label="Перегляд зображення">
            <div class="chat-lightbox-backdrop"></div>
            <button type="button" class="chat-lightbox-close" aria-label="Закрити">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <div class="chat-lightbox-content">
                <img src="" alt="" class="chat-lightbox-img">
            </div>
        </div>';

        $out .= $this->renderScripts($user_id);
        return $out;
    }

    private function renderChatList($user_id, $userChats, $chat_id)
    {
        $out = '<div class="messenger-list">';
        $out .= '<div class="messenger-list__head">';
        $out .= '<div class="messenger-list__head-main">';
        $out .= '<span class="messenger-list__head-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l3.65 -3.65a9 9 0 1 1 3.35 2.29l-7 1.36z" /></svg>
        </span>';
        $out .= '<div class="messenger-list__head-copy"><p class="messenger-list__eyebrow">Внутрішній messenger</p><h1>Робочі чати</h1></div>';
        $out .= '</div>';
        $out .= '</div>';

        if (empty($userChats)) {
            $out .= '<div class="messenger-list__empty">Робочих чатів поки немає.</div>';
            $out .= '</div>';
            return $out;
        }

        foreach ($userChats as $chat) {
            if ((int)($chat['type'] ?? 0) !== 2) {
                continue;
            }

            $partner = $this->getChatPartnerData($user_id, $chat);
            $lastMessage = $chat['last_message'] ?? null;
            $preview = $this->getChatPreview($lastMessage);
            $time = '';
            if (!empty($lastMessage['idtadd'])) {
                $time = date('H:i', strtotime((string)$lastMessage['idtadd']));
            }

            $isActive = (int)$chat['idx'] === $chat_id ? ' is-active' : '';
            $out .= '<button type="button" class="chat-item' . $isActive . '" data-chat-id="' . (int)$chat['idx'] . '" data-link="/messenger.php?type=2&chat=' . (int)$chat['idx'] . '">';
            $out .= '<img src="' . htmlspecialchars($partner['avatar'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($partner['name'], ENT_QUOTES, 'UTF-8') . '" class="chat-item__avatar">';
            $out .= '<span class="chat-item__body">';
            $out .= '<span class="chat-item__top"><strong>' . htmlspecialchars($partner['name'], ENT_QUOTES, 'UTF-8') . '</strong><span class="chat-item__time">' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span></span>';
            $out .= '<span class="chat-item__bottom"><span class="chat-item__preview">' . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') . '</span></span>';
            $out .= '</span>';
            $out .= '</button>';
        }

        $out .= '</div>';
        return $out;
    }

    public function renderChatWindow($user_id, $currentChat, $messages, $chat_id, $type)
    {
        $out = '<div class="chat-window">';

        if (!$currentChat || (int)($currentChat['type'] ?? 0) !== 2) {
            $out .= '<div class="chat-empty">
                <div class="chat-empty__card">
                    <p class="chat-empty__eyebrow">Робочі чати</p>
                    <h2>Оберіть чат</h2>
                    <p>Відкрийте діалог зі списку ліворуч, щоб побачити переписку та інформацію по чату.</p>
                </div>
            </div></div>';
            return $out;
        }

        $partner = $this->getChatPartnerData($user_id, $currentChat);

        $out .= '<header class="chat-header">';
        $out .= '<button type="button" class="chat-nav-btn chat-back-btn" aria-label="Назад до списку чатів">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </button>';
        $out .= '<div class="chat-header__meta">';
        $out .= '<img src="' . htmlspecialchars($partner['avatar'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($partner['name'], ENT_QUOTES, 'UTF-8') . '" class="chat-header__avatar">';
        $out .= '<div><strong>' . htmlspecialchars($partner['name'], ENT_QUOTES, 'UTF-8') . '</strong><span>Робочий чат</span></div>';
        $out .= '</div>';
        $out .= '<button type="button" class="chat-nav-btn chat-info-btn" aria-label="Інформація про чат">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-dots"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M18 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
        </button>';
        $out .= '</header>';

        $out .= '<div class="chat-messages" id="chatMessages" data-chat-id="' . (int)$chat_id . '">';
        $out .= $this->renderMessages($user_id, $messages);
        $out .= '</div>';
        $out .= '<button type="button" class="chat-scroll-bottom" id="chatScrollBottom" aria-label="Прокрутити донизу">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14" /><path d="M18 13l-6 6" /><path d="M6 13l6 6" /></svg>
        </button>';
        $out .= $this->renderInputField($chat_id);
        $out .= '</div>';

        return $out;
    }

    private function renderMessages($user_id, $messages)
    {
        if (empty($messages)) {
            return '<div class="chat-empty-thread">Повідомлень поки немає. Напишіть першим.</div>';
        }

        $out = '';
        $lastDate = null;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($messages as $msg) {
            $date = !empty($msg['idtadd']) ? date('Y-m-d', strtotime((string)$msg['idtadd'])) : '';
            if ($date !== '' && $date !== $lastDate) {
                $dateLabel = $date === $today
                    ? 'Сьогодні'
                    : ($date === $yesterday ? 'Вчора' : date('d.m.Y', strtotime((string)$msg['idtadd'])));
                $out .= '<div class="date-divider"><span>' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</span></div>';
                $lastDate = $date;
            }

            $isSystemMessage = $this->isSystemMessage($msg);
            $messageClass = $isSystemMessage
                ? 'message--system'
                : (((int)$msg['sender_idx'] === (int)$user_id) ? 'message--me' : 'message--other');

            $time = !empty($msg['idtadd']) ? date('H:i', strtotime((string)$msg['idtadd'])) : '';
            $out .= '<article class="message ' . $messageClass;
            if ($isSystemMessage) {
                $out .= ' support-chat-message support-chat-message--system';
            }
            $out .= '" data-idx="' . (int)$msg['idx'] . '" data-date-key="' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '">';

            if ($isSystemMessage) {
                $out .= '<div class="support-chat-message__sender">Система</div>';
            }

            if (!empty($msg['img'])) {
                $imgSrc = htmlspecialchars((string)$msg['img'], ENT_QUOTES, 'UTF-8');
                $imageClass = $isSystemMessage ? 'message__image support-chat-message__image' : 'message__image';
                $out .= '<div class="' . $imageClass . '"><a href="' . $imgSrc . '" class="msg-img-link" data-full-img="' . $imgSrc . '"><img src="' . $imgSrc . '" alt=""></a></div>';
            }

            if (trim((string)($msg['message'] ?? '')) !== '') {
                $out .= '<div class="message__text">' . nl2br(htmlspecialchars((string)$msg['message'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }

            if ($time !== '') {
                $out .= '<div class="message__time">' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            $out .= '</article>';
        }

        return $out;
    }

    private function isSystemMessage(array $message): bool
    {
        $messageType = strtolower(trim((string)($message['message_type'] ?? '')));
        if ($messageType === 'system') {
            return true;
        }

        return (int)($message['sender_idx'] ?? 0) === -1;
    }

    private function renderInputField($chat_id)
    {
        return '
        <form class="chat-input" id="chatForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="chat_idx" value="' . (int)$chat_id . '">
            <div class="chat-input__preview" id="chatImagePreview" aria-hidden="true"></div>
            <div class="chat-input__row">
                <label class="chat-input__attach" for="chatImgInput" title="Додати зображення">
                    <input type="file" name="img" id="chatImgInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </label>
                <textarea name="message" id="chatMessage" placeholder="Напишіть повідомлення..." rows="1" maxlength="2000"></textarea>
                <button type="submit" id="sendBtn" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-send"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 14l11 -11" /><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" /></svg>
                </button>
            </div>
            <div class="chat-input__meta">
                <span id="charCounter">0 / 2000</span>
                <span>Enter для відправки, Shift+Enter для нового рядка</span>
            </div>
        </form>';
    }

    public function renderChatInfo($user_id, $currentChat)
    {
        $out = '<div class="chat-info">';
        $out .= '<div class="chat-info__mobile-head"><button type="button" class="chat-nav-btn chat-info-back-btn" aria-label="Назад до чату">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </button><span>Інформація</span></div>';

        if (!$currentChat || (int)($currentChat['type'] ?? 0) !== 2) {
            $out .= '<div class="chat-info__empty">Оберіть робочий чат, щоб побачити деталі.</div></div>';
            return $out;
        }

        $partner = $this->getChatPartnerData($user_id, $currentChat);
        $orders = $this->getOrdersForChat((int)$currentChat['idx']);
        $createdAt = !empty($currentChat['idtadd']) ? date('d.m.Y H:i', strtotime((string)$currentChat['idtadd'])) : '';
        $lastMessage = $this->getLastMessage((int)$currentChat['idx']);
        $lastActivity = !empty($lastMessage['idtadd']) ? date('d.m.Y H:i', strtotime((string)$lastMessage['idtadd'])) : $createdAt;

        $out .= '<div class="chat-info__head workchat-info__head">';
        $out .= '<h2>Інформація про користувача</h2>';
        $out .= '</div>';

        $out .= '<div class="chat-info__card">';
        $out .= '<img src="' . htmlspecialchars($partner['avatar'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($partner['name'], ENT_QUOTES, 'UTF-8') . '" class="chat-info__avatar">';
        $out .= '<h2>' . htmlspecialchars($partner['name'], ENT_QUOTES, 'UTF-8') . '</h2>';
        $out .= '</div>';

        if (!empty($orders)) {
            $out .= $this->renderOrderInfoSection($orders);
        }

        $out .= '<div class="chat-info__section">';
        $out .= '<div class="chat-info__row"><span>Тип</span><strong>Робочий чат</strong></div>';
        $out .= '<div class="chat-info__row"><span>Створено</span><strong>' . htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $out .= '<div class="chat-info__row"><span>Остання активність</span><strong>' . htmlspecialchars($lastActivity, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $out .= '</div>';

        $out .= '<div class="chat-info__actions">';
        if (!empty($partner['profile_url'])) {
            $out .= '<a href="' . htmlspecialchars($partner['profile_url'], ENT_QUOTES, 'UTF-8') . '" class="chat-info__action chat-info__action--ghost">Відкрити профіль</a>';
        }
        $out .= '<button type="button" class="chat-info__action chat-info__action--danger" id="deleteChatBtn">Видалити чат</button>';
        $out .= '</div>';

        $out .= '</div>';
        return $out;
    }

    private function renderOrderInfoSection(array $orders): string
    {
        $out = '<section class="chat-info__section chat-info__section--orders">';
        $out .= '<div class="chat-info__section-head">';
        $out .= '<span class="chat-info__section-label">Замовлення</span>';
        $out .= '<span class="chat-info__section-badge">' . count($orders) . '</span>';
        $out .= '</div>';

        foreach ($orders as $order) {
            $status = (string)($order['normalized_status'] ?? 'pending');
            $statusLabel = (string)($order['status_label'] ?? $this->getOrderStatusLabel($status));
            $preferredDate = $this->formatDateValue((string)($order['preferred_date'] ?? ''), false);
            $createdAt = $this->formatDateValue((string)($order['created_at'] ?? ''), true);
            $completedAt = $this->formatDateValue((string)($order['completed_at'] ?? ''), true, '');
            $cemeteryPlace = trim((string)($order['cemetery_place'] ?? ''));
            $approximatePrice = trim((string)($order['approximate_price'] ?? ''));
            $comment = trim((string)($order['comment'] ?? ''));
            $rejectionReason = trim((string)($order['rejection_reason'] ?? ''));
            $completionComment = trim((string)($order['completion_comment'] ?? ''));
            $services = isset($order['services']) && is_array($order['services']) ? $order['services'] : [];
            $preferredDateLabel = $preferredDate !== 'Не вказано' ? $preferredDate : 'Дата не вказана';
            $priceLabel = $approximatePrice !== '' ? $approximatePrice : 'Не вказано';
            $cemeteryLabel = $cemeteryPlace !== '' ? $cemeteryPlace : 'Не вказано';

            $out .= '<article class="chat-order-card">';
            $out .= '<div class="chat-order-card__head">';
            $out .= '<div class="chat-order-card__title-wrap"><span class="chat-order-card__eyebrow">Картка замовлення</span><strong>Замовлення #' . (int)$order['idx'] . '</strong></div>';
            $out .= '<span class="chat-order-card__status chat-order-card__status--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span>';
            $out .= '</div>';

            $out .= '<div class="chat-order-card__rows">';
            $out .= '<div class="chat-order-card__meta-grid">';
            $out .= $this->renderOrderMetaItem('Бажана дата', $preferredDateLabel, ' chat-order-card__meta-item--wide');
            $out .= $this->renderOrderMetaItem('Орієнтовна вартість', $priceLabel);
            $out .= $this->renderOrderMetaItem('Створено', $createdAt);
            $out .= '</div>';
            $out .= '<div class="chat-order-card__row chat-order-card__row--stack"><span>Місце виконання</span><strong class="chat-order-card__primary-value">' . htmlspecialchars($cemeteryLabel, ENT_QUOTES, 'UTF-8') . '</strong></div>';

            if (!empty($services)) {
                $out .= '<div class="chat-order-card__row chat-order-card__row--stack"><span>Обрані послуги</span><div class="chat-order-card__chips">';
                foreach ($services as $serviceName) {
                    $out .= '<span class="chat-order-card__chip">' . htmlspecialchars((string)$serviceName, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                $out .= '</div></div>';
            }

            if ($comment !== '') {
                $out .= '<div class="chat-order-card__note"><span>Коментар</span><p>' . nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8')) . '</p></div>';
            }

            if ($status === 'completion_pending') {
                $out .= '<div class="chat-order-card__note chat-order-card__note--soft"><span>Статус звіту</span><p>Фото надіслані, очікує підтвердження клієнта.</p></div>';
            }

            if ($status === 'rejected' && $rejectionReason !== '') {
                $out .= '<div class="chat-order-card__note chat-order-card__note--danger"><span>Причина відмови</span><p>' . nl2br(htmlspecialchars($rejectionReason, ENT_QUOTES, 'UTF-8')) . '</p></div>';
            }

            if ($status === 'cancelled' && $rejectionReason !== '') {
                $out .= '<div class="chat-order-card__note chat-order-card__note--danger"><span>Причина скасування</span><p>' . nl2br(htmlspecialchars($rejectionReason, ENT_QUOTES, 'UTF-8')) . '</p></div>';
            }

            if (($status === 'completed' || $status === 'completion_pending') && $completedAt !== '') {
                $out .= '<div class="chat-order-card__row"><span>Дата звіту</span><strong>' . htmlspecialchars($completedAt, ENT_QUOTES, 'UTF-8') . '</strong></div>';
            }

            if (($status === 'completed' || $status === 'completion_pending') && $completionComment !== '') {
                $out .= '<div class="chat-order-card__note"><span>Коментар до звіту</span><p>' . nl2br(htmlspecialchars($completionComment, ENT_QUOTES, 'UTF-8')) . '</p></div>';
            }

            $out .= '</div>';
            $out .= '</article>';
        }

        $out .= '</section>';
        return $out;
    }

    private function renderOrderMetaItem(string $label, string $value, string $extraClass = ''): string
    {
        return '<div class="chat-order-card__meta-item' . $extraClass . '"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong></div>';
    }

    private function getOrdersForChat(int $chatId): array
    {
        if ($chatId <= 0 || !function_exists('dbTableExists') || !dbTableExists($this->dblink, 'cleaner_orders')) {
            return [];
        }

        $orders = [];
        $sql = "SELECT idx, cleaner_id, client_id, client_name, client_phone, client_email, cemetery_place, preferred_date, comment,
                       selected_services_json, approximate_price, status, rejection_reason, completed_at, completion_comment, created_at
                FROM cleaner_orders
                WHERE chat_idx = {$chatId}
                ORDER BY created_at DESC, idx DESC";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return [];
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $row['normalized_status'] = $this->normalizeOrderStatus($row);
            $row['status_label'] = $this->getOrderStatusLabel((string)$row['normalized_status']);
            $row['services'] = $this->getOrderServices(
                (int)($row['idx'] ?? 0),
                (int)($row['cleaner_id'] ?? 0),
                (string)($row['selected_services_json'] ?? '')
            );
            $orders[] = $row;
        }

        return $orders;
    }

    private function getOrderServices(int $orderId, int $cleanerId, string $selectedServicesJson = ''): array
    {
        if ($orderId <= 0 || !function_exists('dbTableExists') || !dbTableExists($this->dblink, 'cleaner_services')) {
            return [];
        }

        $services = [];

        if (dbTableExists($this->dblink, 'cleaner_order_services')) {
            $sql = "SELECT cs.service_name
                    FROM cleaner_order_services cos
                    INNER JOIN cleaner_services cs ON cs.id = cos.service_id
                    WHERE cos.order_id = {$orderId}
                    ORDER BY cs.sort_order ASC, cs.id ASC";
            $res = mysqli_query($this->dblink, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $serviceName = trim((string)($row['service_name'] ?? ''));
                    if ($serviceName !== '') {
                        $services[] = $serviceName;
                    }
                }
            }
        }

        if (!empty($services) || $selectedServicesJson === '') {
            return $services;
        }

        $decodedIds = json_decode($selectedServicesJson, true);
        if (!is_array($decodedIds)) {
            return [];
        }

        $serviceIds = [];
        foreach ($decodedIds as $serviceId) {
            $serviceId = (int)$serviceId;
            if ($serviceId > 0) {
                $serviceIds[$serviceId] = $serviceId;
            }
        }

        if (empty($serviceIds)) {
            return [];
        }

        $userFilter = $cleanerId > 0 ? " AND user_id = {$cleanerId}" : '';
        $sql = "SELECT id, service_name
                FROM cleaner_services
                WHERE id IN (" . implode(',', $serviceIds) . ")" . $userFilter . "
                ORDER BY sort_order ASC, id ASC";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return [];
        }

        $mappedServices = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $mappedServices[(int)$row['id']] = trim((string)($row['service_name'] ?? ''));
        }

        foreach ($serviceIds as $serviceId) {
            if (!empty($mappedServices[$serviceId])) {
                $services[] = $mappedServices[$serviceId];
            }
        }

        return $services;
    }

    private function normalizeOrderStatus(array $order): string
    {
        $status = trim((string)($order['status'] ?? ''));
        $hasCompletionReport = !empty($order['completed_at']) && (string)$order['completed_at'] !== '0000-00-00 00:00:00';

        if ($status === 'accepted' && $hasCompletionReport) {
            return 'completion_pending';
        }

        return $status !== '' ? $status : 'pending';
    }

    private function getOrderStatusLabel(string $status): string
    {
        $labels = [
            'pending' => 'Очікує прийняття',
            'accepted' => 'Прийнято',
            'completion_pending' => 'Очікує підтвердження',
            'rejected' => 'Відхилено',
            'completed' => 'Виконано',
            'cancelled' => 'Скасовано',
        ];

        return $labels[$status] ?? 'Замовлення';
    }

    private function formatDateValue(string $value, bool $withTime = false, string $fallback = 'Не вказано'): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return $fallback;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $fallback;
        }

        return date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $timestamp);
    }

    private function getChatPartnerData($user_id, $chat)
    {
        $otherUserId = ((int)$chat['user_one'] === (int)$user_id)
            ? (int)$chat['user_two']
            : (int)$chat['user_one'];

        if ($otherUserId <= 0) {
            return [
                'id' => $otherUserId,
                'name' => 'Робочий контакт',
                'avatar' => '/avatars/ava.png',
                'profile_url' => '',
            ];
        }

        $res = mysqli_query(
            $this->dblink,
            "SELECT fname, lname, avatar FROM users WHERE idx = " . $otherUserId . " LIMIT 1"
        );
        $user = $res ? mysqli_fetch_assoc($res) : null;

        $name = trim((string)($user['fname'] ?? '') . ' ' . (string)($user['lname'] ?? ''));
        if ($name === '') {
            $name = 'Користувач #' . $otherUserId;
        }

        $avatar = !empty($user['avatar']) ? (string)$user['avatar'] : '/avatars/ava.png';

        return [
            'id' => $otherUserId,
            'name' => $name,
            'avatar' => $avatar,
            'profile_url' => '/public-profile.php?idx=' . $otherUserId,
        ];
    }

    private function getChatPreview($lastMessage)
    {
        if (!$lastMessage) {
            return 'Без повідомлень';
        }

        $text = trim((string)($lastMessage['message'] ?? ''));
        if ($text !== '') {
            return mb_strimwidth($text, 0, 90, '...');
        }

        if (!empty($lastMessage['img'])) {
            return '[Зображення]';
        }

        return 'Без повідомлень';
    }

    private function getLastMessage($chatId)
    {
        $res = mysqli_query(
            $this->dblink,
            "SELECT idx, idtadd FROM chatsmsg WHERE chat_idx = " . (int)$chatId . " ORDER BY idtadd DESC LIMIT 1"
        );

        return $res ? mysqli_fetch_assoc($res) : null;
    }

    private function renderScripts($user_id)
    {
        ob_start();
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const root = document.getElementById("messengerRoot");
                if (!root) return;

                const userId = <?= (int)$user_id ?>;
                const threadPanel = document.getElementById("messengerThreadPanel");
                const infoPanel = document.getElementById("messengerInfoPanel");
                const deleteModal = document.getElementById("deleteChatModal");
                let currentChatId = parseInt(root.dataset.chatId || "0", 10) || 0;
                let lastMessageId = 0;
                let pollTimer = null;
                let initialScrollRestored = false;
                let isDeletingChat = false;

                function escapeHTML(value) {
                    return String(value).replace(/[&<>"']/g, function (char) {
                        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
                    });
                }

                function isMobile() {
                    return window.innerWidth <= 900;
                }

                function setMobileView(view) {
                    root.dataset.mobileView = view;
                    updateActiveListItem();
                    updateMobileChrome();
                }

                function updateMobileChrome() {
                    const shouldHideBottomDock = isMobile() && root.dataset.mobileView !== "list";
                    document.body.classList.toggle("messenger-mobile-chat-open", shouldHideBottomDock);
                }

                function updateViewportMetrics() {
                    if (!isMobile()) {
                        document.documentElement.style.setProperty("--msg-mobile-viewport-height", "100dvh");
                        document.documentElement.style.setProperty("--msg-mobile-viewport-offset-top", "0px");
                        document.documentElement.style.setProperty("--msg-mobile-viewport-offset-bottom", "0px");
                        return;
                    }

                    const viewport = window.visualViewport;
                    const viewportHeight = viewport ? viewport.height : window.innerHeight;
                    const viewportOffsetTop = viewport ? viewport.offsetTop : 0;
                    const viewportOffsetBottom = viewport
                        ? Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop)
                        : 0;

                    document.documentElement.style.setProperty("--msg-mobile-viewport-height", Math.round(viewportHeight) + "px");
                    document.documentElement.style.setProperty("--msg-mobile-viewport-offset-top", Math.round(Math.max(0, viewportOffsetTop)) + "px");
                    document.documentElement.style.setProperty("--msg-mobile-viewport-offset-bottom", Math.round(viewportOffsetBottom) + "px");
                }

                function normalizeMobilePageScroll() {
                    if (!isMobile() || root.dataset.mobileView !== "chat") {
                        return;
                    }

                    const activeEl = document.activeElement;
                    const isComposerFocused = !!(activeEl && activeEl.id === "chatMessage");
                    if (!isComposerFocused) {
                        return;
                    }

                    if (window.scrollY > 0) {
                        window.scrollTo(0, 0);
                    }
                    if (document.documentElement.scrollTop > 0) {
                        document.documentElement.scrollTop = 0;
                    }
                    if (document.body.scrollTop > 0) {
                        document.body.scrollTop = 0;
                    }
                }

                function updateComposeMetrics() {
                    const form = document.getElementById("chatForm");
                    if (!form) {
                        document.documentElement.style.setProperty("--msg-compose-height", "92px");
                        return;
                    }

                    const nextHeight = Math.max(72, Math.ceil(form.getBoundingClientRect().height));
                    document.documentElement.style.setProperty("--msg-compose-height", nextHeight + "px");
                }

                function queueLayoutRefresh(keepBottom) {
                    [0, 80, 220, 420].forEach(function (delay) {
                        window.setTimeout(function () {
                            updateViewportMetrics();
                            updateComposeMetrics();
                            normalizeMobilePageScroll();

                            if (!keepBottom || !isMobile()) {
                                updateScrollBottomButton();
                                return;
                            }

                            const activeEl = document.activeElement;
                            const chatMessages = document.getElementById("chatMessages");
                            const isComposerFocused = !!(activeEl && activeEl.id === "chatMessage");

                            if (isComposerFocused && chatMessages && isNearBottom()) {
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                                saveScrollPosition();
                            }

                            updateScrollBottomButton();
                        }, delay);
                    });
                }

                function syncViewWithState() {
                    if (!isMobile()) {
                        root.dataset.mobileView = "chat";
                        updateMobileChrome();
                        updateViewportMetrics();
                        return;
                    }

                    if (currentChatId > 0 && root.dataset.mobileView === "list") {
                        updateMobileChrome();
                        updateViewportMetrics();
                        return;
                    }

                    setMobileView(currentChatId > 0 ? "chat" : "list");
                    updateViewportMetrics();
                }

                function updateActiveListItem() {
                    const hideActiveState = isMobile() && root.dataset.mobileView === "list";
                    root.querySelectorAll(".chat-item").forEach(function (item) {
                        const isCurrentChat = parseInt(item.dataset.chatId || "0", 10) === currentChatId;
                        item.classList.toggle("is-active", !hideActiveState && isCurrentChat);
                    });
                }

                function autoResize(textarea) {
                    if (!textarea) return;
                    const maxHeight = 120;
                    textarea.style.height = "auto";
                    const nextHeight = Math.min(textarea.scrollHeight, maxHeight);
                    textarea.style.height = nextHeight + "px";
                    textarea.style.overflowY = textarea.scrollHeight > maxHeight ? "auto" : "hidden";
                    updateComposeMetrics();
                }

                function updateComposeState() {
                    const form = document.getElementById("chatForm");
                    if (!form) return;
                    const textarea = form.querySelector("textarea[name='message']");
                    const sendBtn = document.getElementById("sendBtn");
                    const counter = document.getElementById("charCounter");
                    const fileInput = form.querySelector("input[name='img']");
                    const textLength = textarea ? textarea.value.length : 0;
                    const hasImage = !!(fileInput && fileInput.files && fileInput.files.length > 0);
                    if (sendBtn) {
                        sendBtn.disabled = textLength === 0 && !hasImage;
                    }
                    if (counter) {
                        counter.textContent = textLength + " / 2000";
                    }
                    updateComposeMetrics();
                }

                function setLastMessageId() {
                    lastMessageId = 0;
                    const chatMessages = document.getElementById("chatMessages");
                    if (!chatMessages) return;
                    chatMessages.querySelectorAll(".message[data-idx]").forEach(function (message) {
                        const id = parseInt(message.dataset.idx || "0", 10) || 0;
                        if (id > lastMessageId) {
                            lastMessageId = id;
                        }
                    });
                }

                function scrollMessagesToBottom() {
                    const chatMessages = document.getElementById("chatMessages");
                    if (chatMessages) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                        saveScrollPosition();
                    }
                }

                function scrollStorageKey(chatId) {
                    return "messenger_scroll_" + String(chatId || 0);
                }

                function saveScrollPosition() {
                    const chatMessages = document.getElementById("chatMessages");
                    if (!chatMessages || !currentChatId) return;
                    try {
                        sessionStorage.setItem(scrollStorageKey(currentChatId), String(chatMessages.scrollTop));
                    } catch (error) {}
                }

                function restoreScrollPosition(forceBottom) {
                    const chatMessages = document.getElementById("chatMessages");
                    if (!chatMessages) return;

                    if (forceBottom) {
                        scrollMessagesToBottom();
                        updateScrollBottomButton();
                        return;
                    }

                    let restored = false;
                    try {
                        const saved = sessionStorage.getItem(scrollStorageKey(currentChatId));
                        if (saved !== null) {
                            chatMessages.scrollTop = parseInt(saved, 10) || 0;
                            restored = true;
                        }
                    } catch (error) {}

                    if (!restored) {
                        chatMessages.scrollTop = 0;
                    }

                    updateScrollBottomButton();
                }

                function isNearBottom() {
                    const chatMessages = document.getElementById("chatMessages");
                    if (!chatMessages) return true;
                    return (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < 80;
                }

                function updateScrollBottomButton() {
                    const button = document.getElementById("chatScrollBottom");
                    if (!button) return;
                    button.classList.toggle("is-visible", !isNearBottom());
                }

                function updatePreviewCard(fileInput, previewBox, onChange) {
                    if (!previewBox) return;

                    previewBox.innerHTML = "";
                    previewBox.classList.remove("is-visible");
                    previewBox.setAttribute("aria-hidden", "true");

                    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                        updateComposeMetrics();
                        if (typeof onChange === "function") onChange();
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function () {
                        previewBox.classList.add("is-visible");
                        previewBox.removeAttribute("aria-hidden");
                        previewBox.innerHTML = '<div class="chat-image-preview-card"><img src="' + escapeHTML(reader.result || "") + '" alt=""><button type="button" class="chat-image-preview-remove">&times;</button></div>';
                        const removeBtn = previewBox.querySelector(".chat-image-preview-remove");
                        if (removeBtn) {
                            removeBtn.addEventListener("click", function () {
                                fileInput.value = "";
                                updatePreviewCard(fileInput, previewBox, onChange);
                            });
                        }
                        if (typeof onChange === "function") onChange();
                        updateComposeMetrics();
                    };
                    reader.readAsDataURL(fileInput.files[0]);
                }

                function bindLightbox() {
                    const lightbox = document.getElementById("chatImageLightbox");
                    if (!lightbox || lightbox.dataset.bound === "1") return;
                    lightbox.dataset.bound = "1";

                    const img = lightbox.querySelector(".chat-lightbox-img");
                    const close = function () {
                        lightbox.classList.remove("open");
                        lightbox.setAttribute("aria-hidden", "true");
                        if (img) img.removeAttribute("src");
                        document.body.style.overflow = "";
                    };

                    document.body.addEventListener("click", function (event) {
                        const link = event.target.closest(".msg-img-link");
                        if (!link) return;
                        event.preventDefault();
                        if (img) {
                            img.src = link.dataset.fullImg || link.getAttribute("href") || "";
                        }
                        lightbox.classList.add("open");
                        lightbox.setAttribute("aria-hidden", "false");
                        document.body.style.overflow = "hidden";
                    });

                    const backdrop = lightbox.querySelector(".chat-lightbox-backdrop");
                    const closeBtn = lightbox.querySelector(".chat-lightbox-close");
                    if (backdrop) backdrop.addEventListener("click", close);
                    if (closeBtn) closeBtn.addEventListener("click", close);
                    document.addEventListener("keydown", function (event) {
                        if (event.key === "Escape" && lightbox.classList.contains("open")) {
                            close();
                        }
                    });
                }

                function updateChatListPreview(message) {
                    const item = root.querySelector('.chat-item[data-chat-id="' + String(currentChatId) + '"]');
                    if (!item || !message) return;

                    const preview = item.querySelector(".chat-item__preview");
                    const time = item.querySelector(".chat-item__time");
                    const nextPreview = (message.message && String(message.message).trim())
                        ? String(message.message).trim()
                        : (message.img ? "[Зображення]" : "Без повідомлень");

                    if (preview) {
                        preview.textContent = nextPreview.length > 90 ? nextPreview.slice(0, 87) + "..." : nextPreview;
                    }

                    if (time) {
                        const date = message.idtadd ? new Date(message.idtadd) : new Date();
                        const hh = String(date.getHours()).padStart(2, "0");
                        const mm = String(date.getMinutes()).padStart(2, "0");
                        time.textContent = hh + ":" + mm;
                    }
                }

                function parseMessageDate(value) {
                    if (!value) return null;
                    const date = new Date(value);
                    return Number.isNaN(date.getTime()) ? null : date;
                }

                function formatDateKey(date) {
                    if (!date) return "";
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, "0");
                    const day = String(date.getDate()).padStart(2, "0");
                    return year + "-" + month + "-" + day;
                }

                function formatDateLabel(date) {
                    if (!date) return "";
                    const currentDateKey = formatDateKey(date);
                    const now = new Date();
                    const todayKey = formatDateKey(now);
                    const yesterday = new Date(now);
                    yesterday.setDate(yesterday.getDate() - 1);
                    const yesterdayKey = formatDateKey(yesterday);

                    if (currentDateKey === todayKey) return "Сьогодні";
                    if (currentDateKey === yesterdayKey) return "Вчора";

                    const day = String(date.getDate()).padStart(2, "0");
                    const month = String(date.getMonth() + 1).padStart(2, "0");
                    return day + "." + month + "." + date.getFullYear();
                }

                function isSystemMessage(message) {
                    const messageType = String(message.message_type || "").trim().toLowerCase();
                    return messageType === "system" || parseInt(message.sender_idx || 0, 10) === -1;
                }

                function buildMessageHtml(message, includeDateDivider) {
                    const isSystem = isSystemMessage(message);
                    const senderClass = isSystem
                        ? "message--system"
                        : (parseInt(message.sender_idx || 0, 10) === userId ? "message--me" : "message--other");
                    const createdAt = parseMessageDate(message.idtadd);
                    const dateKey = formatDateKey(createdAt);
                    const time = createdAt
                        ? createdAt.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
                        : "";
                    const imageClass = isSystem ? "message__image support-chat-message__image" : "message__image";
                    const senderPart = isSystem
                        ? '<div class="support-chat-message__sender">Система</div>'
                        : "";
                    const imgPart = message.img
                        ? '<div class="' + imageClass + '"><a href="' + escapeHTML(message.img) + '" class="msg-img-link" data-full-img="' + escapeHTML(message.img) + '"><img src="' + escapeHTML(message.img) + '" alt=""></a></div>'
                        : "";
                    const textPart = message.message && String(message.message).trim()
                        ? '<div class="message__text">' + escapeHTML(message.message).replace(/\n/g, "<br>") + '</div>'
                        : "";
                    const dateDividerPart = includeDateDivider && dateKey
                        ? '<div class="date-divider"><span>' + escapeHTML(formatDateLabel(createdAt)) + '</span></div>'
                        : "";
                    const extraClass = isSystem ? " support-chat-message support-chat-message--system" : "";
                    return dateDividerPart + '<article class="message ' + senderClass + extraClass + '" data-idx="' + parseInt(message.idx || 0, 10) + '" data-date-key="' + escapeHTML(dateKey) + '">' + senderPart + imgPart + textPart + '<div class="message__time">' + escapeHTML(time) + '</div></article>';
                }

                function appendMessages(messages) {
                    const chatMessages = document.getElementById("chatMessages");
                    if (!chatMessages || !Array.isArray(messages) || messages.length === 0) return;

                    const empty = chatMessages.querySelector(".chat-empty-thread");
                    if (empty) empty.remove();

                    const stickToBottom = isNearBottom();
                    let lastRenderedDateKey = "";
                    chatMessages.querySelectorAll(".message[data-date-key]").forEach(function (messageNode) {
                        if (messageNode.dataset.dateKey) {
                            lastRenderedDateKey = messageNode.dataset.dateKey;
                        }
                    });

                    messages.forEach(function (message) {
                        const messageId = parseInt(message.idx || "0", 10) || 0;
                        if (!messageId || chatMessages.querySelector('.message[data-idx="' + String(messageId) + '"]')) {
                            return;
                        }
                        const nextDateKey = formatDateKey(parseMessageDate(message.idtadd));
                        chatMessages.insertAdjacentHTML("beforeend", buildMessageHtml(message, nextDateKey !== "" && nextDateKey !== lastRenderedDateKey));
                        if (nextDateKey) {
                            lastRenderedDateKey = nextDateKey;
                        }
                        if (messageId > lastMessageId) {
                            lastMessageId = messageId;
                        }
                        updateChatListPreview(message);
                    });

                    if (stickToBottom) {
                        scrollMessagesToBottom();
                    } else {
                        updateScrollBottomButton();
                    }
                }

                async function pollMessages() {
                    if (!currentChatId || isDeletingChat) return;

                    try {
                        const res = await fetch("messenger.php?action=get_new_messages&chat=" + encodeURIComponent(currentChatId) + "&last_msg_id=" + encodeURIComponent(lastMessageId), {
                            credentials: "same-origin",
                            cache: "no-store"
                        });
                        const data = await res.json();
                        if (data.status === "ok" && Array.isArray(data.messages) && data.messages.length > 0) {
                            appendMessages(data.messages);
                            return;
                        }
                        if (data.status === "error" && data.redirect_url) {
                            if (pollTimer) {
                                window.clearInterval(pollTimer);
                                pollTimer = null;
                            }
                            currentChatId = 0;
                            root.dataset.chatId = "0";
                            window.location.replace(data.redirect_url);
                        }
                    } catch (error) {
                        console.error(error);
                    }
                }

                function startPolling() {
                    if (pollTimer) {
                        window.clearInterval(pollTimer);
                    }
                    if (!currentChatId) return;
                    pollTimer = window.setInterval(pollMessages, 4000);
                }

                function bindCompose() {
                    const form = document.getElementById("chatForm");
                    if (!form || form.dataset.bound === "1") return;
                    form.dataset.bound = "1";

                    const textarea = form.querySelector("textarea[name='message']");
                    const fileInput = form.querySelector("input[name='img']");
                    const previewBox = document.getElementById("chatImagePreview");

                    if (textarea) {
                        autoResize(textarea);
                        textarea.addEventListener("input", function () {
                            autoResize(textarea);
                            updateComposeState();
                        });
                        textarea.addEventListener("focus", function () {
                            queueLayoutRefresh(true);
                        });
                        textarea.addEventListener("blur", function () {
                            queueLayoutRefresh(false);
                        });
                        textarea.addEventListener("keydown", function (event) {
                            if (event.key === "Enter" && !event.shiftKey) {
                                event.preventDefault();
                                const sendBtn = document.getElementById("sendBtn");
                                if (sendBtn && !sendBtn.disabled) {
                                    form.requestSubmit();
                                }
                            }
                        });
                    }

                    if (fileInput) {
                        fileInput.addEventListener("change", function () {
                            updatePreviewCard(fileInput, previewBox, updateComposeState);
                        });
                    }

                    form.addEventListener("submit", async function (event) {
                        event.preventDefault();
                        const sendBtn = document.getElementById("sendBtn");
                        if (sendBtn) {
                            sendBtn.disabled = true;
                        }

                        try {
                            const res = await fetch("messenger.php?action=send_message", {
                                method: "POST",
                                body: new FormData(form),
                                credentials: "same-origin"
                            });
                            const data = await res.json();

                            if (data.status === "ok" && Array.isArray(data.messages)) {
                                appendMessages(data.messages);
                                if (textarea) {
                                    textarea.value = "";
                                    autoResize(textarea);
                                }
                                if (fileInput) {
                                    fileInput.value = "";
                                }
                                if (previewBox) {
                                    previewBox.innerHTML = "";
                                    previewBox.classList.remove("is-visible");
                                    previewBox.setAttribute("aria-hidden", "true");
                                }
                            } else if (data.msg) {
                                alert(data.msg);
                            }
                        } catch (error) {
                            console.error(error);
                            alert("Не вдалося надіслати повідомлення.");
                        } finally {
                            updateComposeState();
                            scrollMessagesToBottom();
                        }
                    });

                    updateComposeState();
                    updateComposeMetrics();
                }

                function bindChatScroll() {
                    const chatMessages = document.getElementById("chatMessages");
                    const scrollButton = document.getElementById("chatScrollBottom");
                    if (!chatMessages || chatMessages.dataset.bound === "1") return;
                    chatMessages.dataset.bound = "1";

                    chatMessages.addEventListener("scroll", function () {
                        saveScrollPosition();
                        updateScrollBottomButton();
                    });

                    if (scrollButton && scrollButton.dataset.bound !== "1") {
                        scrollButton.dataset.bound = "1";
                        scrollButton.addEventListener("click", function () {
                            scrollMessagesToBottom();
                            saveScrollPosition();
                            updateScrollBottomButton();
                        });
                    }
                }

                function bindMobileNav() {
                    const backBtn = root.querySelector(".chat-back-btn");
                    const infoBtn = root.querySelector(".chat-info-btn");
                    const infoBackBtn = root.querySelector(".chat-info-back-btn");

                    if (backBtn && backBtn.dataset.bound !== "1") {
                        backBtn.dataset.bound = "1";
                        backBtn.addEventListener("click", function () {
                            if (!isMobile()) return;
                            setMobileView("list");
                            history.pushState(null, "", "/messenger.php?type=2");
                        });
                    }

                    if (infoBtn && infoBtn.dataset.bound !== "1") {
                        infoBtn.dataset.bound = "1";
                        infoBtn.addEventListener("click", function () {
                            if (!isMobile()) return;
                            setMobileView("info");
                        });
                    }

                    if (infoBackBtn && infoBackBtn.dataset.bound !== "1") {
                        infoBackBtn.dataset.bound = "1";
                        infoBackBtn.addEventListener("click", function () {
                            if (!isMobile()) return;
                            setMobileView("chat");
                        });
                    }
                }

                function bindDeleteChat() {
                    const deleteBtn = document.getElementById("deleteChatBtn");
                    const confirmBtn = document.getElementById("confirmDeleteBtn");
                    const cancelBtn = document.getElementById("cancelDeleteBtn");
                    if (!deleteBtn || !deleteModal || !confirmBtn || !cancelBtn) return;
                    if (deleteBtn.dataset.bound === "1") return;
                    deleteBtn.dataset.bound = "1";

                    const closeModal = function () {
                        deleteModal.classList.remove("show");
                    };

                    deleteBtn.addEventListener("click", function () {
                        deleteModal.classList.add("show");
                    });

                    cancelBtn.onclick = closeModal;
                    const backdrop = deleteModal.querySelector(".modal-backdrop");
                    if (backdrop) backdrop.onclick = closeModal;

                    confirmBtn.onclick = async function () {
                        if (!currentChatId || isDeletingChat) return;

                        isDeletingChat = true;
                        confirmBtn.disabled = true;
                        cancelBtn.disabled = true;
                        if (pollTimer) {
                            window.clearInterval(pollTimer);
                            pollTimer = null;
                        }

                        try {
                            const res = await fetch("messenger.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body: new URLSearchParams({
                                    action: "delete_chat",
                                    chat_id: currentChatId
                                }),
                                credentials: "same-origin"
                            });
                            const data = await res.json();
                            if (data.status !== "ok") {
                                alert(data.msg || "Не вдалося видалити чат.");
                                isDeletingChat = false;
                                confirmBtn.disabled = false;
                                cancelBtn.disabled = false;
                                startPolling();
                                return;
                            }

                            closeModal();
                            currentChatId = 0;
                            root.dataset.chatId = "0";
                            window.location.replace(data.redirect_url || "/messenger.php?type=2");
                        } catch (error) {
                            console.error(error);
                            isDeletingChat = false;
                            confirmBtn.disabled = false;
                            cancelBtn.disabled = false;
                            startPolling();
                            alert("Не вдалося видалити чат.");
                        }
                    };
                }

                async function parseHtmlResponse(response) {
                    const contentType = (response.headers.get("content-type") || "").toLowerCase();
                    if (contentType.indexOf("application/json") !== -1) {
                        const payload = await response.json();
                        throw payload;
                    }

                    return response.text();
                }

                async function loadChat(chatId, updateHistory, preserveScroll) {
                    if (!chatId || isDeletingChat) return;

                    try {
                        const [threadRes, infoRes] = await Promise.all([
                            fetch("messenger.php?action=get_chat_html&type=2&chat=" + encodeURIComponent(chatId), {
                                credentials: "same-origin",
                                cache: "no-store"
                            }),
                            fetch("messenger.php?action=get_chat_info&type=2&chat=" + encodeURIComponent(chatId), {
                                credentials: "same-origin",
                                cache: "no-store"
                            })
                        ]);

                        const threadHtml = await parseHtmlResponse(threadRes);
                        const infoHtml = await parseHtmlResponse(infoRes);

                        threadPanel.innerHTML = threadHtml;
                        infoPanel.innerHTML = infoHtml;
                        currentChatId = chatId;
                        root.dataset.chatId = String(chatId);
                        setLastMessageId();
                        bindCompose();
                        bindChatScroll();
                        bindMobileNav();
                        bindDeleteChat();
                        bindLightbox();
                        updateActiveListItem();
                        startPolling();
                        restoreScrollPosition(!!preserveScroll ? false : true);
                        queueLayoutRefresh(false);

                        if (isMobile()) {
                            setMobileView("chat");
                        }

                        if (updateHistory) {
                            history.pushState(null, "", "/messenger.php?type=2&chat=" + chatId);
                        }
                    } catch (error) {
                        if (error && error.redirect_url) {
                            currentChatId = 0;
                            root.dataset.chatId = "0";
                            window.location.replace(error.redirect_url);
                            return;
                        }
                        console.error(error);
                    }
                }

                function bindChatList() {
                    root.querySelectorAll(".chat-item").forEach(function (item) {
                        if (item.dataset.bound === "1") return;
                        item.dataset.bound = "1";
                        item.addEventListener("click", function () {
                            const chatId = parseInt(item.dataset.chatId || "0", 10) || 0;
                            if (!chatId) return;
                            loadChat(chatId, true, false);
                        });
                    });
                }

                async function handlePopState() {
                    const params = new URLSearchParams(window.location.search);
                    const chatId = parseInt(params.get("chat") || "0", 10) || 0;

                    if (!chatId) {
                        currentChatId = 0;
                        root.dataset.chatId = "0";
                        if (isMobile()) {
                            setMobileView("list");
                        } else {
                            updateMobileChrome();
                            location.reload();
                        }
                        return;
                    }

                    await loadChat(chatId, false, true);
                }

                bindLightbox();
                bindChatList();
                bindCompose();
                bindChatScroll();
                bindMobileNav();
                bindDeleteChat();
                updateActiveListItem();
                setLastMessageId();
                startPolling();
                syncViewWithState();
                updateMobileChrome();
                updateViewportMetrics();
                updateComposeMetrics();
                if (!initialScrollRestored) {
                    restoreScrollPosition(currentChatId > 0);
                    initialScrollRestored = true;
                }
                queueLayoutRefresh(false);

                window.addEventListener("popstate", handlePopState);
                window.addEventListener("resize", function () {
                    syncViewWithState();
                    updateViewportMetrics();
                    updateComposeMetrics();
                    normalizeMobilePageScroll();
                });
                window.addEventListener("scroll", normalizeMobilePageScroll, { passive: true });
                if (window.visualViewport) {
                    window.visualViewport.addEventListener("resize", updateViewportMetrics);
                    window.visualViewport.addEventListener("resize", updateComposeMetrics);
                    window.visualViewport.addEventListener("scroll", updateViewportMetrics);
                    window.visualViewport.addEventListener("scroll", updateComposeMetrics);
                    window.visualViewport.addEventListener("resize", normalizeMobilePageScroll);
                    window.visualViewport.addEventListener("scroll", normalizeMobilePageScroll);
                }
                window.addEventListener("beforeunload", saveScrollPosition);
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
