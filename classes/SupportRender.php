<?php

class SupportRender
{
    /**
     * @param array<string, mixed>|null $ticket
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $config
     */
    public function renderClientPage(?array $ticket, array $messages, array $config): string
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $status = (string)($ticket['status'] ?? 'new');
        $statusLabel = $this->statusLabel($status);
        $resolutionPending = !empty($ticket['resolution_confirmation_pending']);
        $isAuthenticated = !empty($config['is_authenticated']);
        $captchaPassed = !empty($config['captcha_passed']);

        $out = '<link rel="stylesheet" href="/assets/css/msg.css">';
        $out .= '<link rel="stylesheet" href="/assets/css/support-client.css">';
        $out .= '<div class="messenger-page messenger-page--support" id="supportClientRoot" data-ticket-id="' . $ticketId . '" data-mobile-view="chat" data-is-authenticated="' . ($isAuthenticated ? '1' : '0') . '" data-captcha-passed="' . ($captchaPassed ? '1' : '0') . '">';
        $out .= '<section class="messenger-thread-panel support-thread-panel">';
        $out .= '<div class="chat-window support-chat-window">';
        $out .= '<header class="chat-header support-chat-header">';
        $out .= '<div class="chat-header__meta support-chat-header__meta">';
        $out .= '<div class="support-chat-header__lead">';
        $out .= '<a class="support-chat-header__home" href="/" aria-label="Повернутися на головну"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-home" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg></a>';
        $out .= '<span class="support-chat-header__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-messages"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 14l-3 -3h-7a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1h9a1 1 0 0 1 1 1v10" /><path d="M14 15v2a1 1 0 0 1 -1 1h-7l-3 3v-10a1 1 0 0 1 1 -1h2" /></svg></span>';
        $out .= '</div>';
        $out .= '<div class="support-chat-header__copy">';
        $out .= '<p class="messenger-list__eyebrow support-chat-header__eyebrow">Служба Shana</p>';
        $out .= '<strong>Технічна підтримка</strong>';
        $out .= '<span>Пишіть прямо тут. Якщо звернення вже відкрите, ви потрапите в поточний діалог.</span>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="support-chat-header__status">';
        $out .= '<span class="support-ticket-badge support-ticket-badge--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '" data-support-ticket-status>' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<span class="support-ticket-code" data-support-ticket-code>' . ($ticketId > 0 ? ('#' . $ticketId) : 'Нове звернення') . '</span>';
        $out .= '</div>';
        $out .= '</header>';
        $out .= '<div class="chat-messages support-chat-messages" id="supportClientMessages">';
        $out .= $this->renderMessages($messages, 'client');
        $out .= '</div>';
        $out .= $this->renderClientResolutionBanner($resolutionPending);
        $out .= '<form class="chat-input support-chat-input" id="supportClientForm" enctype="multipart/form-data">';
        $out .= '<input type="hidden" name="ticket_id" value="' . $ticketId . '">';
        $out .= '<div class="chat-input__preview support-client-preview" id="supportClientPreview" aria-hidden="true"></div>';
        $out .= '<div class="chat-input__row">';
        $out .= '<label class="chat-input__attach support-client-attach" for="supportClientImg" title="Додати зображення"><input type="file" name="img" id="supportClientImg" accept="image/jpeg,image/png,image/gif,image/webp" hidden><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></label>';
        $out .= '<textarea name="message" id="supportClientMessage" placeholder="Опишіть ваше питання..." rows="1" maxlength="2000"></textarea>';
        $out .= '<button type="submit" id="supportClientSend" disabled><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14l11 -11" /><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" /></svg></button>';
        $out .= '</div>';
        $out .= '<div class="chat-input__meta support-chat-input__meta"><span>Всі відповіді залишаться в цьому діалозі.</span><span>Можна додавати текст і зображення.</span></div>';
        $out .= '</form>';
        if (!$isAuthenticated) {
            $out .= $this->renderClientCaptchaModal();
        }
        $out .= '</div>';
        $out .= '</section>';
        $out .= '<aside class="messenger-info-panel support-info-panel">';
        $out .= '<div class="chat-info support-chat-info">';
        $out .= '<div class="chat-info__card support-chat-info__card">';
        $out .= '<span class="support-chat-info__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-info-circle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 9h.01" /><path d="M11 12h1v4h1" /></svg></span>';
        $out .= '<h2>Як це працює</h2>';
        $out .= '<p>Чат відкриває або продовжує одне активне звернення, тому вся історія підтримки залишається в одному місці.</p>';
        $out .= '</div>';
        $out .= '<div class="chat-info__section">';
        $out .= '<div class="chat-info__row"><span>Статус</span><strong data-support-ticket-status-label>' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $out .= '<div class="chat-info__row"><span>Номер звернення</span><strong data-support-ticket-code-label>' . ($ticketId > 0 ? ('#' . $ticketId) : 'Нове звернення') . '</strong></div>';
        $out .= '<div class="chat-info__row"><span>Канал</span><strong>Клієнтський чат</strong></div>';
        $out .= '</div>';
        $out .= '<div class="chat-info__section support-chat-info__section">';
        $out .= '<ul class="support-chat-steps">';
        $out .= '<li>Нове повідомлення автоматично потрапляє в чергу вебмайстрів.</li>';
        $out .= '<li>Відповідь підтримки зʼявиться прямо в цьому чаті.</li>';
        $out .= '<li>Якщо звернення вже закрите, нове повідомлення знову активує діалог.</li>';
        $out .= '</ul>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</aside>';
        $out .= '</div>';

        $out .= '<script src="/assets/js/support-client.js"></script>';
        return $out;
    }

    private function renderClientCaptchaModal(): string
    {
        $out = '<div class="modal support-client-captcha-modal" id="supportClientCaptchaModal" aria-hidden="true">';
        $out .= '<div class="modal-backdrop"></div>';
        $out .= '<div class="modal-content support-client-captcha-modal__content" role="dialog" aria-modal="true" aria-labelledby="supportClientCaptchaTitle">';
        $out .= '<div class="support-client-captcha-modal__header">';
        $out .= '<div><h3 id="supportClientCaptchaTitle">Підтвердіть, що ви не бот</h3><p>Для першого звернення в підтримку потрібно пройти коротку перевірку.</p></div>';
        $out .= '</div>';
        $out .= '<div class="support-client-captcha-modal__body">';
        $out .= '<p class="support-client-captcha-modal__question">Скільки буде <strong id="supportClientCaptchaQuestion"></strong> ?</p>';
        $out .= '<label class="support-client-captcha-modal__field"><span>Ваша відповідь</span><input type="text" id="supportClientCaptchaAnswer" inputmode="numeric" autocomplete="off"></label>';
        $out .= '<div class="support-client-captcha-modal__error" id="supportClientCaptchaError" hidden></div>';
        $out .= '</div>';
        $out .= '<div class="support-client-captcha-modal__actions"><button type="button" class="btn-confirm support-client-captcha-modal__submit" id="supportClientCaptchaSubmit">Підтвердити</button></div>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $bucketTickets
     * @param array<string, int> $counts
     * @param array<string, mixed>|null $selectedTicket
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $webmasters
     * @param array<int, array<string, mixed>> $templates
     * @param array<string, mixed> $config
     */
    public function renderStaffPage(array $bucketTickets, array $counts, ?array $selectedTicket, array $messages, array $webmasters, array $templates, array $config): string
    {
        $selectedId = (int)($selectedTicket['id'] ?? 0);
        $selectedStatus = (string)($selectedTicket['status'] ?? 'new');
        $currentUserId = (int)($config['current_user_id'] ?? 0);
        $webmastersJson = htmlspecialchars(json_encode($webmasters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $templatesJson = htmlspecialchars(json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $mobileView = $selectedId > 0 ? 'chat' : 'list';

        $tabs = [
            'queue' => ['label' => 'Нові', 'icon' => ''],
            'my' => [
                'label' => 'Мої',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>',
            ],
            'waiting' => [
                'label' => 'Очікує',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clock"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l3 3" /></svg>',
            ],
            'resolved' => [
                'label' => 'Рішені',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>',
            ],
            'closed' => [
                'label' => 'Закриті',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>',
            ],
            'spam' => [
                'label' => 'Спам',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-face-id-error"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 8v-2a2 2 0 0 1 2 -2h2" /><path d="M4 16v2a2 2 0 0 0 2 2h2" /><path d="M16 4h2a2 2 0 0 1 2 2v2" /><path d="M16 20h2a2 2 0 0 0 2 -2v-2" /><path d="M9 10h.01" /><path d="M15 10h.01" /><path d="M9.5 15.05a3.5 3.5 0 0 1 5 0" /></svg>',
            ],
        ];

        $out = '<link rel="stylesheet" href="/assets/css/msg.css">';
        $out .= '<link rel="stylesheet" href="/assets/css/support-desk.css">';
        $out .= '<div class="support-desk-page">';
        $queueCount = (int)($counts['queue'] ?? 0);
        $myCount = (int)($counts['my'] ?? 0);

        $out .= '<header class="support-desk-header">';
        $out .= '<div class="support-desk-header__brand">';
        $out .= '<span class="support-desk-header__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-headphones"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 15a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2l0 -3" /><path d="M15 15a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2l0 -3" /><path d="M4 15v-3a8 8 0 0 1 16 0v3" /></svg></span>';
        $out .= '<div class="support-desk-header__main"><h1>Служба підтримки</h1></div>';
        $out .= '<span class="support-desk-header__divider" aria-hidden="true"></span>';
        $out .= '<div class="support-desk-header__stats">';
        $out .= '<div class="support-desk-header__stat"><span>Нових заявок</span><strong data-support-desk-header-queue>' . $queueCount . '</strong></div>';
        $out .= '<div class="support-desk-header__stat"><span>В роботі</span><strong data-support-desk-header-my>' . $myCount . '</strong></div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</header>';
        $out .= '<div class="messenger-page messenger-page--supportdesk" id="supportDeskRoot" data-selected-ticket-id="' . $selectedId . '" data-current-user-id="' . $currentUserId . '" data-webmasters="' . $webmastersJson . '" data-templates="' . $templatesJson . '" data-mobile-view="' . $mobileView . '">';
        $out .= '<aside class="messenger-sidebar support-desk-sidebar">';
        $out .= '<div class="messenger-list support-desk-sidebar-shell">';
        $out .= '<div class="messenger-list__head support-desk-list-head">';
        $out .= '<div><p class="messenger-list__eyebrow">Черга підтримки</p><h2 class="support-desk-list-title">Звернення</h2><p class="messenger-list__subtitle">Фільтруйте чергу та відкривайте потрібний діалог.</p></div>';
        $out .= '</div>';
        $out .= '<div class="support-desk-tabs">';
        foreach ($tabs as $key => $tab) {
            $count = (int)($counts[$key] ?? 0);
            $out .= '<button type="button" class="support-desk-tab' . ($key === 'queue' ? ' is-active' : '') . '" data-bucket="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
            if ($tab['icon'] !== '') {
                $out .= '<span class="support-desk-tab__icon" aria-hidden="true">' . $tab['icon'] . '</span>';
            }
            $out .= '<span class="support-desk-tab__label">' . htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            $out .= '<span class="support-desk-tab__count">' . $count . '</span>';
            $out .= '</button>';
        }
        $out .= '</div>';
        $out .= '<div class="support-desk-list" id="supportDeskList">';
        foreach ($tabs as $key => $tab) {
            $out .= '<div class="support-desk-list-pane' . ($key === 'queue' ? ' is-active' : '') . '" data-bucket-pane="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
            $out .= $this->renderTicketList($bucketTickets[$key] ?? [], $selectedId);
            $out .= '</div>';
        }
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</aside>';
        $out .= '<section class="messenger-thread-panel support-desk-detail" id="supportDeskDetail" data-ticket-id="' . $selectedId . '" data-ticket-status="' . htmlspecialchars($selectedStatus, ENT_QUOTES, 'UTF-8') . '">';
        $out .= $this->renderStaffDetail($selectedTicket, $messages, $webmasters, $templates, $currentUserId);
        $out .= '</section>';
        $out .= '<aside class="messenger-info-panel support-desk-info-panel" id="supportDeskInfoPanel">';
        $out .= $this->renderStaffInfo($selectedTicket);
        $out .= '</aside>';
        $out .= $this->renderStaffTemplatesModal();
        $out .= $this->renderStaffTransferModal();
        $out .= $this->renderStaffSpamModal();
        $out .= '<script src="/assets/js/support-desk.js"></script>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $tickets
     */
    public function renderTicketList(array $tickets, int $selectedId = 0): string
    {
        if (empty($tickets)) {
            return '<div class="support-desk-empty">Поки що тут порожньо.</div>';
        }

        $out = '';
        foreach ($tickets as $ticket) {
            $ticketId = (int)($ticket['id'] ?? 0);
            $status = (string)($ticket['status'] ?? 'new');
            $out .= '<button type="button" class="chat-item support-ticket-list-item' . ($ticketId === $selectedId ? ' is-active is-selected' : '') . '" data-ticket-open="' . $ticketId . '">';
            $out .= '<span class="support-ticket-list-side">';
            $out .= '<span class="support-ticket-list-avatar" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /><path d="M15 19l2 2l4 -4" /></svg>
            </span>';
            $out .= '</span>';
            $out .= '<span class="chat-item__body">';
            $out .= '<span class="chat-item__top"><strong>' . htmlspecialchars((string)($ticket['requester_label'] ?? 'Клієнт'), ENT_QUOTES, 'UTF-8') . '</strong><span class="chat-item__time">' . htmlspecialchars($this->formatTicketMoment((string)($ticket['last_message_at'] ?? '')), ENT_QUOTES, 'UTF-8') . '</span></span>';
            $out .= '<span class="chat-item__bottom"><span class="chat-item__preview">' . htmlspecialchars((string)($ticket['last_message_preview'] ?? 'Без повідомлень'), ENT_QUOTES, 'UTF-8') . '</span></span>';
            $out .= '</span>';
            $out .= '<span class="support-ticket-list-meta"><span class="support-ticket-list-badges"><span class="support-ticket-badge support-ticket-badge--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . ' support-ticket-badge--list">' . htmlspecialchars($this->statusLabel($status), ENT_QUOTES, 'UTF-8') . '</span>' . $this->renderTransferredListBadge($ticket) . '</span><span class="support-ticket-list-code">#' . $ticketId . '</span></span>';
            $out .= '</button>';
        }
        return $out;
    }

    /**
     * @param array<string, mixed>|null $ticket
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $webmasters
     * @param array<int, array<string, mixed>> $templates
     */
    public function renderStaffDetail(?array $ticket, array $messages, array $webmasters, array $templates, int $currentUserId = 0): string
    {
        if (!$ticket) {
            return '<div class="chat-window support-desk-chat-window"><div class="chat-empty support-desk-empty-state"><div class="chat-empty__card support-desk-empty-state__card"><span class="support-desk-empty-state__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-circle-search"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M11.303 19.955a9.818 9.818 0 0 1 -3.603 -.955l-4.7 1l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c1.73 1.476 2.665 3.435 2.76 5.433" /><path d="M15 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M20.2 20.2l1.8 1.8" /></svg></span><p class="chat-empty__eyebrow support-desk-empty-state__eyebrow">Support Desk</p><h2>Оберіть звернення</h2><p>Відкрийте діалог у лівій колонці, щоб побачити листування, статус і дії по зверненню.</p><div class="support-desk-empty-state__hint"><span>Ліворуч доступні всі звернення за статусами: нові, в роботі, очікують відповіді та завершені.</span></div></div></div></div>';
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $status = (string)($ticket['status'] ?? 'new');
        $assignee = (int)($ticket['assignee_user_id'] ?? 0);

        $out = '<div class="chat-window support-desk-chat-window">';
        $out .= '<header class="chat-header support-desk-chat-header">';
        $out .= '<button type="button" class="chat-nav-btn chat-back-btn support-desk-back-btn" aria-label="Назад до списку звернень">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6l6-6"/></svg>
        </button>';
        $out .= '<div class="chat-header__meta">';
        $out .= '<span class="support-desk-chat-avatar" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /><path d="M15 19l2 2l4 -4" /></svg>
        </span>';
        $out .= '<div class="support-desk-chat-headline">';
        $out .= '<strong>' . htmlspecialchars((string)($ticket['requester_label'] ?? 'Клієнт'), ENT_QUOTES, 'UTF-8') . '</strong>';
        $out .= '<span>Звернення #' . $ticketId . '</span>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="support-desk-ticket-side">';
        $out .= '<div class="support-desk-ticket-meta">';
        $out .= '<span class="support-ticket-badge support-ticket-badge--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '" data-ticket-status-badge>' . htmlspecialchars($this->statusLabel($status), ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= $this->renderStaffAssigneeMeta($ticket);
        $out .= '</div>';
        $out .= '<div class="support-desk-ticket-actions" data-ticket-header-actions>' . $this->renderStaffHeaderActions($ticket, $webmasters, $currentUserId) . '</div>';
        $out .= '</div>';
        $out .= '</header>';
        $out .= '<div class="chat-messages support-desk-messages" id="supportDeskMessages">' . $this->renderMessages($messages, 'staff') . '</div>';
        $out .= '<div class="support-desk-compose-shell">';
        $out .= '<form id="supportDeskReplyForm" class="chat-input support-desk-compose" enctype="multipart/form-data">';
        $out .= '<input type="hidden" name="ticket_id" value="' . $ticketId . '">';
        $out .= '<div class="chat-input__row support-desk-compose-row">';
        $out .= '<button type="button" class="chat-input__attach support-desk-action-trigger support-desk-template-trigger support-desk-tooltip-trigger" data-tooltip="Відкрити шаблони відповідей" aria-label="Відкрити шаблони відповідей"><span class="support-desk-action-trigger__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg></span><span class="support-desk-action-trigger__label">Шаблон</span></button>';
        $out .= '<button type="button" class="chat-input__attach support-desk-action-trigger support-desk-action-trigger--danger support-desk-spam-trigger support-desk-tooltip-trigger" data-tooltip="Позначити звернення як спам" aria-label="Позначити звернення як спам" data-ticket-spam><span class="support-desk-action-trigger__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-flag"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 5a5 5 0 0 1 7 0a5 5 0 0 0 7 0v9a5 5 0 0 1 -7 0a5 5 0 0 0 -7 0v-9" /><path d="M5 21v-7" /></svg></span><span class="support-desk-action-trigger__label">Спам</span></button>';
        $out .= '<textarea name="message" id="supportDeskReplyMessage" placeholder="Відповідь клієнту..." rows="1" maxlength="2000"></textarea>';
        $out .= '<input type="hidden" name="template_id" id="supportDeskTemplateId" value="">';
        $out .= '<button type="submit" id="supportDeskSendBtn" disabled><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14l11 -11" /><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" /></svg></button>';
        $out .= '</div>';
        $out .= '<div class="chat-input__meta"><span>Шаблони відповідей доступні через кнопку ліворуч.</span><span>Enter для відправки, Shift+Enter для нового рядка</span></div>';
        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }

    private function renderClientResolutionBanner(bool $resolutionPending): string
    {
        $hidden = $resolutionPending ? '' : ' hidden';
        $out = '<div class="support-client-resolution" id="supportClientResolution"' . $hidden . '>';
        if ($resolutionPending) {
            $out .= $this->renderClientResolutionBannerInner();
        }
        $out .= '</div>';
        return $out;
    }

    private function renderClientResolutionBannerInner(): string
    {
        $out = '<div class="support-client-resolution__card">';
        $out .= '<div class="support-client-resolution__copy"><strong>Підтвердіть, будь ласка, що проблему вирішено</strong><span>Якщо питання ще актуальне, просто напишіть нове повідомлення в чат.</span></div>';
        $out .= '<button type="button" class="support-client-resolution__btn" data-support-confirm-resolution>Підтвердити вирішення</button>';
        $out .= '</div>';
        return $out;
    }

    /**
     * @param array<string, mixed>|null $ticket
     */
    public function renderStaffInfo(?array $ticket): string
    {
        $out = '<div class="chat-info support-desk-info">';
        $out .= '<div class="chat-info__mobile-head"><span>Інформація</span></div>';

        if (!$ticket) {
            $out .= '<div class="chat-info__empty">Оберіть звернення, щоб побачити інформацію про клієнта.</div></div>';
            return $out;
        }

        $name = (string)($ticket['requester_label'] ?? 'Клієнт');
        $email = trim((string)($ticket['requester_email'] ?? ''));
        $phone = trim((string)($ticket['requester_phone'] ?? ''));
        $avatar = trim((string)($ticket['requester_avatar'] ?? ''));
        $initial = trim((string)($ticket['requester_initial'] ?? 'K'));
        $since = $this->formatInfoDate((string)($ticket['requester_since'] ?? ''));
        $ticketsCount = (int)($ticket['requester_tickets_count'] ?? 0);
        $currentTicketCreated = $this->formatInfoDate((string)($ticket['created_at'] ?? ''));
        $deviceName = trim((string)($ticket['requester_device_name'] ?? ''));
        $deviceType = trim((string)($ticket['requester_device_type'] ?? ''));
        $browser = trim((string)($ticket['requester_browser'] ?? ''));
        $operatingSystem = trim((string)($ticket['requester_os'] ?? ''));
        $engine = trim((string)($ticket['requester_engine'] ?? ''));
        $cpu = trim((string)($ticket['requester_cpu'] ?? ''));
        $location = trim((string)($ticket['requester_location'] ?? ''));
        $lastActivity = $this->formatInfoDateTime((string)($ticket['requester_last_activity'] ?? ''));

        $out .= '<div class="support-desk-info__head">';
        $out .= '<h2>Інформація про клієнта</h2>';
        $out .= '</div>';

        $out .= '<div class="chat-info__card support-desk-info__card">';
        if ($avatar !== '') {
            $out .= '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" class="chat-info__avatar">';
        } else {
            $out .= '<div class="support-desk-info__avatar-fallback">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $out .= '<h2>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</h2>';
        $out .= '<p>' . htmlspecialchars($since !== '' ? ('Клієнт у підтримці з ' . $since) : 'Інформація по зверненнях клієнта', ENT_QUOTES, 'UTF-8') . '</p>';
        $out .= '</div>';

        $out .= '<div class="chat-info__section support-desk-info__section">';
        $out .= '<h3>Контактна інформація</h3>';
        $out .= '<div class="support-desk-info__contact-grid">';
        $out .= $this->renderInfoContactCard('Email', $email !== '' ? $email : 'Не вказано', 'mail');
        $out .= $this->renderInfoContactCard('Телефон', $phone !== '' ? $phone : 'Не вказано', 'phone');
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="chat-info__section support-desk-info__section">';
        $out .= '<h3>Статистика</h3>';
        $out .= '<div class="support-desk-info__stats">';
        $out .= '<div class="support-desk-info__stat"><strong>' . $ticketsCount . '</strong><span>Всього звернень</span></div>';
        $out .= '<div class="support-desk-info__stat"><strong>' . htmlspecialchars($currentTicketCreated !== '' ? $currentTicketCreated : 'Немає', ENT_QUOTES, 'UTF-8') . '</strong><span>Створено звернення</span></div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="chat-info__section support-desk-info__section">';
        $out .= '<h3>Пристрій та сесія</h3>';
        $out .= '<div class="support-desk-info__device-grid">';
        $out .= $this->renderInfoDetailCard('Пристрій', $deviceName !== '' ? $deviceName : 'Немає даних', $deviceType !== '' ? $deviceType : 'Тип не визначено');
        $browserHint = $operatingSystem !== '' ? $operatingSystem : 'Система не визначена';
        if ($engine !== '') {
            $browserHint .= ($browserHint !== '' ? ' • ' : '') . 'Engine: ' . $engine;
        }
        $out .= $this->renderInfoDetailCard('Браузер', $browser !== '' ? $browser : 'Немає даних', $browserHint);
        $locationHint = $lastActivity !== '' ? ('Активність: ' . $lastActivity) : 'Час активності невідомий';
        if ($cpu !== '') {
            $locationHint .= ' • CPU: ' . $cpu;
        }
        $out .= $this->renderInfoDetailCard('Локація', $location !== '' ? $location : 'Немає даних', $locationHint);
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    public function renderMessages(array $messages, string $context = 'client'): string
    {
        if (empty($messages)) {
            if ($context === 'client') {
                return $this->renderClientEmptyState();
            }
            return '<div class="chat-empty-thread">Повідомлень поки немає. Напишіть першим.</div>';
        }

        $out = '';
        $lastDate = null;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($messages as $message) {
            $senderType = (string)($message['sender_type'] ?? 'system');
            $createdAt = (string)($message['created_at'] ?? '');
            $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
            $date = $timestamp ? date('Y-m-d', $timestamp) : '';
            if ($date !== '' && $date !== $lastDate) {
                $dateLabel = $date === $today
                    ? 'Сьогодні'
                    : ($date === $yesterday ? 'Вчора' : date('d.m.Y', $timestamp));
                $out .= '<div class="date-divider"><span>' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</span></div>';
                $lastDate = $date;
            }

            $out .= '<article class="message ' . $this->messageClass($senderType, $context) . ' support-chat-message support-chat-message--' . htmlspecialchars($senderType, ENT_QUOTES, 'UTF-8') . '" data-message-id="' . (int)($message['id'] ?? 0) . '" data-date-key="' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '">';
            if ($senderType !== 'customer' || $context === 'staff') {
                $out .= '<div class="support-chat-message__sender">' . htmlspecialchars($this->messageSenderLabel($senderType, $context), ENT_QUOTES, 'UTF-8') . '</div>';
            }
            if (!empty($message['image_path'])) {
                $src = htmlspecialchars((string)$message['image_path'], ENT_QUOTES, 'UTF-8');
                $out .= '<div class="message__image support-chat-message__image"><a href="' . $src . '" target="_blank" rel="noopener"><img src="' . $src . '" alt=""></a></div>';
            }
            if ((string)($message['body'] ?? '') !== '') {
                $out .= '<div class="message__text">' . nl2br(htmlspecialchars((string)$message['body'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }
            if ($timestamp) {
                $out .= '<div class="message__time">' . htmlspecialchars(date('H:i', $timestamp), ENT_QUOTES, 'UTF-8') . '</div>';
            }
            $out .= '</article>';
        }
        return $out;
    }

    private function renderClientEmptyState(): string
    {
        $out = '<div class="chat-empty-thread support-chat-empty-state">';
        $out .= '<span class="support-chat-empty-state__icon" aria-hidden="true"><img src="/assets/images/suppchat.gif" alt="" class="support-chat-empty-state__icon-image"></span>';
        $out .= '<span class="support-chat-empty-state__eyebrow">Технічна підтримка</span>';
        $out .= '<strong>Ваше звернення почнеться тут</strong>';
        $out .= '<span>Опишіть питання нижче, і ми відкриємо діалог з підтримкою. Уся переписка залишиться в цьому чаті.</span>';
        $out .= '</div>';
        return $out;
    }

    private function messageClass(string $senderType, string $context): string
    {
        if ($context === 'staff') {
            if ($senderType === 'staff') {
                return 'message--me';
            }
            if ($senderType === 'customer') {
                return 'message--other';
            }

            return 'message--system';
        }

        if ($senderType === 'customer') {
            return 'message--me';
        }
        if ($senderType === 'staff') {
            return 'message--other';
        }

        return 'message--system';
    }

    private function messageSenderLabel(string $senderType, string $context): string
    {
        if ($context === 'client') {
            switch ($senderType) {
                case 'staff':
                    return 'Підтримка';
                case 'customer':
                    return 'Ви';
                default:
                    return 'Система';
            }
        }

        return $this->senderLabel($senderType);
    }

    private function formatTicketMoment(string $dateTime): string
    {
        if ($dateTime === '') {
            return '';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return $dateTime;
        }

        $today = date('Y-m-d');
        $date = date('Y-m-d', $timestamp);

        if ($date === $today) {
            return date('H:i', $timestamp);
        }

        return date('d.m', $timestamp);
    }

    private function formatInfoDate(string $dateTime): string
    {
        if ($dateTime === '') {
            return '';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return $dateTime;
        }

        return date('d.m.Y', $timestamp);
    }

    private function formatInfoDateTime(string $dateTime): string
    {
        if ($dateTime === '') {
            return '';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return $dateTime;
        }

        return date('d.m.Y H:i', $timestamp);
    }

    private function renderInfoContactCard(string $label, string $value, string $type): string
    {
        $icon = $type === 'phone'
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -16 -16a2 2 0 0 1 2 -2" /></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" /></svg>';

        $action = '';
        if ($value !== 'Не вказано') {
            $href = $type === 'phone'
                ? 'tel:' . preg_replace('/[^0-9+]/', '', $value)
                : 'mailto:' . $value;
            $action = '<a class="support-desk-info__contact-action" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
            $action .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 7h-5a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-5"/><path d="M10 14l10 -10"/><path d="M15 4h5v5"/></svg>';
            $action .= '</a>';
        }

        $out = '<div class="support-desk-info__contact">';
        $out .= '<span class="support-desk-info__contact-icon" aria-hidden="true">' . $icon . '</span>';
        $out .= '<div class="support-desk-info__contact-copy"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        $out .= $action;
        $out .= '</div>';
        return $out;
    }

    private function renderInfoDetailCard(string $label, string $value, string $hint): string
    {
        $out = '<div class="support-desk-info__detail-card">';
        $out .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong>';
        $out .= '<small>' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</small>';
        $out .= '</div>';
        return $out;
    }

    private function senderLabel(string $senderType): string
    {
        switch ($senderType) {
            case 'staff':
                return 'Вебмайстер';
            case 'customer':
                return 'Клієнт';
            default:
                return 'Система';
        }
    }

    private function statusLabel(string $status): string
    {
        switch ($status) {
            case 'open':
                return 'В роботі';
            case 'waiting_customer':
                return 'Очікує клієнта';
            case 'resolved':
                return 'Вирішено';
            case 'closed':
                return 'Закрито';
            case 'spam':
                return 'Спам';
            default:
                return 'Нове';
        }
    }

    /**
     * @param array<string, mixed>|null $ticket
     */
    private function isTransferredTicket(?array $ticket): bool
    {
        return !empty($ticket['is_transferred']) && trim((string)($ticket['transferred_by_label'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed>|null $ticket
     */
    private function renderTransferredListBadge(?array $ticket): string
    {
        if (!$this->isTransferredTicket($ticket)) {
            return '';
        }

        return '<span class="support-ticket-transfer-badge">Передано</span>';
    }

    /**
     * @param array<string, mixed>|null $ticket
     */
    private function renderStaffAssigneeMeta(?array $ticket): string
    {
        $assigneeUserId = (int)($ticket['assignee_user_id'] ?? 0);
        $assigneeLabel = $assigneeUserId > 0
            ? trim((string)($ticket['assignee_label'] ?? ''))
            : 'Без виконавця';
        if ($assigneeLabel === '') {
            $assigneeLabel = 'Без виконавця';
        }

        $out = '<span class="support-desk-ticket-assignee" data-ticket-assignee>';
        $out .= '<span class="support-desk-ticket-assignee__name">' . htmlspecialchars($assigneeLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($this->isTransferredTicket($ticket)) {
            $out .= '<span class="support-desk-ticket-assignee__meta">Передав: ' . htmlspecialchars((string)($ticket['transferred_by_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $out .= '</span>';

        return $out;
    }

    /**
     * @param array<string, mixed>|null $ticket
     */
    private function renderStaffResolveButton(?array $ticket, int $currentUserId = 0): string
    {
        $assigneeUserId = (int)($ticket['assignee_user_id'] ?? 0);
        if (
            !$ticket
            || (string)($ticket['status'] ?? '') !== 'open'
            || $currentUserId <= 0
            || $assigneeUserId !== $currentUserId
        ) {
            return '';
        }

        return '<button type="button" class="support-desk-resolve-btn support-desk-tooltip-trigger" data-ticket-resolve data-tooltip="Відзначити звернення як вирішене"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg><span>Відзначити як вирішено</span></button>';
    }

    /**
     * @param array<string, mixed>|null $ticket
     * @param array<int, array<string, mixed>> $webmasters
     */
    private function renderStaffHeaderActions(?array $ticket, array $webmasters, int $currentUserId = 0): string
    {
        if (!$ticket) {
            return '';
        }

        return $this->renderStaffTransferButton($ticket, $webmasters, $currentUserId)
            . $this->renderStaffResolveButton($ticket, $currentUserId);
    }

    /**
     * @param array<string, mixed>|null $ticket
     * @param array<int, array<string, mixed>> $webmasters
     */
    private function renderStaffTransferButton(?array $ticket, array $webmasters, int $currentUserId = 0): string
    {
        if (!$ticket) {
            return '';
        }

        $assigneeUserId = (int)($ticket['assignee_user_id'] ?? 0);
        $hasTransferTargets = false;
        foreach ($webmasters as $webmaster) {
            $webmasterId = (int)($webmaster['id'] ?? 0);
            if ($webmasterId <= 0 || $webmasterId === $currentUserId || $webmasterId === $assigneeUserId) {
                continue;
            }
            $hasTransferTargets = true;
            break;
        }

        return '<button type="button" class="support-desk-transfer-btn support-desk-tooltip-trigger" data-ticket-transfer aria-label="Передати звернення іншому спеціалісту" data-tooltip="Передати іншому спеціалісту"' . ($hasTransferTargets ? '' : ' disabled') . '><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-transfer"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M20 10h-16l5.5 -6" /><path d="M4 14h16l-5.5 6" /></svg></button>';
    }

    private function renderStaffTemplatesModal(): string
    {
        $out = '<div class="modal support-desk-templates-modal" id="supportDeskTemplatesModal" aria-hidden="true">';
        $out .= '<div class="modal-backdrop" data-template-modal-close></div>';
        $out .= '<div class="modal-content support-desk-templates-modal__content" role="dialog" aria-modal="true" aria-labelledby="supportDeskTemplatesTitle">';
        $out .= '<div class="support-desk-templates-modal__header">';
        $out .= '<div class="support-desk-templates-modal__title"><span class="support-desk-templates-modal__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg></span>';
        $out .= '<div><h3 id="supportDeskTemplatesTitle">Шаблони відповідей</h3><p>Оберіть шаблон для швидкої відповіді</p></div></div>';
        $out .= '<button type="button" class="support-desk-templates-modal__close" data-template-modal-close aria-label="Закрити"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg></button>';
        $out .= '</div>';
        $out .= '<div class="support-desk-templates-modal__search"><span class="support-desk-templates-modal__search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3 -4.3"/></svg></span><input type="search" id="supportDeskTemplateSearch" placeholder="Пошук шаблону або введіть команду (наприклад /привіт)..." autocomplete="off"></div>';
        $out .= '<div class="support-desk-templates-modal__filters" id="supportDeskTemplateFilters"></div>';
        $out .= '<div class="support-desk-templates-modal__list" id="supportDeskTemplateList"></div>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }

    private function renderStaffTransferModal(): string
    {
        $out = '<div class="modal support-desk-transfer-modal" id="supportDeskTransferModal" aria-hidden="true">';
        $out .= '<div class="modal-backdrop" data-transfer-modal-close></div>';
        $out .= '<div class="modal-content support-desk-transfer-modal__content" role="dialog" aria-modal="true" aria-labelledby="supportDeskTransferTitle">';
        $out .= '<div class="support-desk-transfer-modal__header">';
        $out .= '<div class="support-desk-transfer-modal__title"><span class="support-desk-transfer-modal__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M20 10h-16l5.5 -6" /><path d="M4 14h16l-5.5 6" /></svg></span>';
        $out .= '<div><h3 id="supportDeskTransferTitle">Передача звернення</h3><p>Оберіть спеціаліста, якому потрібно передати цей чат.</p></div></div>';
        $out .= '<button type="button" class="support-desk-transfer-modal__close" data-transfer-modal-close aria-label="Закрити"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg></button>';
        $out .= '</div>';
        $out .= '<div class="support-desk-transfer-modal__body">';
        $out .= '<div class="support-desk-transfer-modal__field">';
        $out .= '<span class="support-desk-transfer-modal__label">Кому передати</span>';
        $out .= '<div class="support-desk-transfer-modal__select" id="supportDeskTransferSelect">';
        $out .= '<button type="button" class="support-desk-transfer-modal__trigger" id="supportDeskTransferTrigger" aria-haspopup="listbox" aria-expanded="false"><span class="support-desk-transfer-modal__trigger-text" data-transfer-trigger-text>Оберіть спеціаліста</span><span class="support-desk-transfer-modal__trigger-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6l6 -6"/></svg></span></button>';
        $out .= '<div class="support-desk-transfer-modal__options" id="supportDeskTransferOptions" role="listbox" hidden></div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="support-desk-transfer-modal__summary" id="supportDeskTransferSummary">Оберіть спеціаліста зі списку, щоб підтвердити передачу звернення.</div>';
        $out .= '</div>';
        $out .= '<div class="modal-footer support-desk-transfer-modal__footer">';
        $out .= '<button type="button" class="btn-cancel" data-transfer-modal-close>Скасувати</button>';
        $out .= '<button type="button" class="btn-confirm support-desk-transfer-modal__confirm" id="supportDeskTransferConfirm" disabled>Підтвердити передачу</button>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }

    private function renderStaffSpamModal(): string
    {
        $out = '<div class="modal support-desk-spam-modal" id="supportDeskSpamModal" aria-hidden="true">';
        $out .= '<div class="modal-backdrop" data-spam-modal-close></div>';
        $out .= '<div class="modal-content support-desk-spam-modal__content" role="dialog" aria-modal="true" aria-labelledby="supportDeskSpamTitle">';
        $out .= '<div class="modal-header"><h3 id="supportDeskSpamTitle">Позначити звернення як спам?</h3></div>';
        $out .= '<div class="modal-body">Після підтвердження звернення буде переміщене в розділ "Спам". Якщо це зроблено помилково, статус можна буде повернути вручну.</div>';
        $out .= '<div class="modal-footer">';
        $out .= '<button type="button" class="btn-cancel" data-spam-modal-close>Скасувати</button>';
        $out .= '<button type="button" class="btn-confirm support-desk-spam-modal__confirm" id="supportDeskSpamConfirm">Так, це спам</button>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }
}
