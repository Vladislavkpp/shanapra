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
        $socketJson = htmlspecialchars(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        $out = '<link rel="stylesheet" href="/assets/css/support-client.css">';
        $out .= '<div class="support-client-page" id="supportClientRoot" data-ticket-id="' . $ticketId . '" data-socket-config="' . $socketJson . '">';
        $out .= '<header class="support-client-header">';
        $out .= '<div>';
        $out .= '<p class="support-client-kicker">Технічна підтримка</p>';
        $out .= '<h1 class="support-client-title">Ми на звʼязку</h1>';
        $out .= '<p class="support-client-subtitle">Пишіть прямо в чат. Якщо звернення вже відкрите, ви потрапите в поточний діалог.</p>';
        $out .= '</div>';
        $out .= '<div class="support-client-ticket-meta">';
        $out .= '<span class="support-ticket-badge support-ticket-badge--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<span class="support-ticket-code">' . ($ticketId > 0 ? ('#' . $ticketId) : 'Нове звернення') . '</span>';
        $out .= '</div>';
        $out .= '</header>';
        $out .= '<div class="support-client-shell">';
        $out .= '<section class="support-client-thread">';
        $out .= '<div class="support-client-messages" id="supportClientMessages">';
        $out .= $this->renderMessages($messages, 'client');
        $out .= '</div>';
        $out .= '<form class="support-client-form" id="supportClientForm" enctype="multipart/form-data">';
        $out .= '<input type="hidden" name="ticket_id" value="' . $ticketId . '">';
        $out .= '<div class="support-client-form-top"><div class="support-client-preview" id="supportClientPreview" aria-hidden="true"></div></div>';
        $out .= '<div class="support-client-form-row">';
        $out .= '<label class="support-client-attach" for="supportClientImg"><input type="file" name="img" id="supportClientImg" accept="image/jpeg,image/png,image/gif,image/webp" hidden>+</label>';
        $out .= '<textarea name="message" id="supportClientMessage" placeholder="Опишіть ваше питання..." rows="1"></textarea>';
        $out .= '<button type="submit" id="supportClientSend">Надіслати</button>';
        $out .= '</div>';
        $out .= '</form>';
        $out .= '</section>';
        $out .= '<aside class="support-client-aside">';
        $out .= '<div class="support-client-card">';
        $out .= '<h2>Як це працює</h2>';
        $out .= '<ul>';
        $out .= '<li>Звернення автоматично потрапляє в чергу вебмайстрів.</li>';
        $out .= '<li>Ви можете продовжити діалог у цьому ж вікні.</li>';
        $out .= '<li>Якщо питання вже вирішене, нове повідомлення знову відкриє звернення.</li>';
        $out .= '</ul>';
        $out .= '</div>';
        $out .= '</aside>';
        $out .= '</div>';
        $out .= '</div>';

        if (!empty($config['socket_url'])) {
            $socketSrc = htmlspecialchars(rtrim((string)$config['socket_url'], '/') . '/socket.io/socket.io.js', ENT_QUOTES, 'UTF-8');
            $out .= '<script src="' . $socketSrc . '"></script>';
        }
        $out .= '<script src="/assets/js/support-client.js"></script>';
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
        $socketJson = htmlspecialchars(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $webmastersJson = htmlspecialchars(json_encode($webmasters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $templatesJson = htmlspecialchars(json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

        $tabs = [
            'queue' => 'Нові',
            'my' => 'Мої',
            'waiting' => 'Очікують клієнта',
            'resolved' => 'Рішені',
            'closed' => 'Закриті',
            'spam' => 'Спам',
        ];

        $out = '<link rel="stylesheet" href="/assets/css/support-desk.css">';
        $out .= '<div class="support-desk-page" id="supportDeskRoot" data-selected-ticket-id="' . $selectedId . '" data-socket-config="' . $socketJson . '" data-webmasters="' . $webmastersJson . '" data-templates="' . $templatesJson . '">';
        $out .= '<header class="support-desk-header">';
        $out .= '<div><p class="support-desk-kicker">Operator Console</p><h1>Support Desk</h1><p>Окрема черга звернень для вебмайстрів з live-оновленнями.</p></div>';
        $out .= '<a class="support-desk-back" href="/messenger.php?type=3" target="_blank" rel="noopener">Відкрити клієнтський чат</a>';
        $out .= '</header>';
        $out .= '<div class="support-desk-shell">';
        $out .= '<aside class="support-desk-sidebar">';
        $out .= '<div class="support-desk-tabs">';
        foreach ($tabs as $key => $label) {
            $count = (int)($counts[$key] ?? 0);
            $out .= '<button type="button" class="support-desk-tab' . ($key === 'queue' ? ' is-active' : '') . '" data-bucket="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '<span>' . $count . '</span></button>';
        }
        $out .= '</div>';
        $out .= '<div class="support-desk-list" id="supportDeskList">';
        foreach ($tabs as $key => $label) {
            $out .= '<div class="support-desk-list-pane' . ($key === 'queue' ? ' is-active' : '') . '" data-bucket-pane="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
            $out .= $this->renderTicketList($bucketTickets[$key] ?? [], $selectedId);
            $out .= '</div>';
        }
        $out .= '</div>';
        $out .= '</aside>';
        $out .= '<section class="support-desk-detail" id="supportDeskDetail" data-ticket-id="' . $selectedId . '" data-ticket-status="' . htmlspecialchars($selectedStatus, ENT_QUOTES, 'UTF-8') . '">';
        $out .= $this->renderStaffDetail($selectedTicket, $messages, $webmasters, $templates);
        $out .= '</section>';
        $out .= '</div>';
        if (!empty($config['socket_url'])) {
            $socketSrc = htmlspecialchars(rtrim((string)$config['socket_url'], '/') . '/socket.io/socket.io.js', ENT_QUOTES, 'UTF-8');
            $out .= '<script src="' . $socketSrc . '"></script>';
        }
        $out .= '<script src="/assets/js/support-desk.js"></script>';
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
            $out .= '<button type="button" class="support-ticket-list-item' . ($ticketId === $selectedId ? ' is-selected' : '') . '" data-ticket-open="' . $ticketId . '">';
            $out .= '<div class="support-ticket-list-head"><strong>' . htmlspecialchars((string)($ticket['requester_label'] ?? 'Клієнт'), ENT_QUOTES, 'UTF-8') . '</strong><span>#' . $ticketId . '</span></div>';
            $out .= '<div class="support-ticket-list-preview">' . htmlspecialchars((string)($ticket['last_message_preview'] ?? 'Без повідомлень'), ENT_QUOTES, 'UTF-8') . '</div>';
            $out .= '<div class="support-ticket-list-meta"><span class="support-ticket-badge support-ticket-badge--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($this->statusLabel($status), ENT_QUOTES, 'UTF-8') . '</span><span>' . htmlspecialchars((string)($ticket['last_message_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';
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
    public function renderStaffDetail(?array $ticket, array $messages, array $webmasters, array $templates): string
    {
        if (!$ticket) {
            return '<div class="support-desk-empty-panel">Оберіть звернення в списку, щоб побачити діалог і дії.</div>';
        }

        $ticketId = (int)($ticket['id'] ?? 0);
        $status = (string)($ticket['status'] ?? 'new');
        $assignee = (int)($ticket['assignee_user_id'] ?? 0);

        $out = '<div class="support-desk-ticket-head">';
        $out .= '<div><p class="support-desk-ticket-kicker">Звернення #' . $ticketId . '</p><h2>' . htmlspecialchars((string)($ticket['requester_label'] ?? 'Клієнт'), ENT_QUOTES, 'UTF-8') . '</h2></div>';
        $out .= '<div class="support-desk-ticket-meta">';
        $out .= '<span class="support-ticket-badge support-ticket-badge--' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($this->statusLabel($status), ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<span class="support-desk-ticket-assignee">' . ($assignee > 0 ? htmlspecialchars((string)($ticket['assignee_label'] ?? ''), ENT_QUOTES, 'UTF-8') : 'Без виконавця') . '</span>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="support-desk-actions">';
        $out .= '<button type="button" class="support-action-btn" data-claim-ticket="' . $ticketId . '">Взяти в роботу</button>';
        $out .= '<select id="supportDeskTransferUser">';
        $out .= '<option value="">Передати...</option>';
        foreach ($webmasters as $webmaster) {
            $out .= '<option value="' . (int)$webmaster['id'] . '">' . htmlspecialchars((string)$webmaster['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $out .= '</select>';
        $out .= '<button type="button" class="support-action-btn support-action-btn--ghost" data-transfer-ticket="' . $ticketId . '">Передати</button>';
        $out .= '<select id="supportDeskStatus">';
        foreach (['new', 'open', 'waiting_customer', 'resolved', 'closed', 'spam'] as $option) {
            $selected = $option === $status ? ' selected' : '';
            $out .= '<option value="' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($this->statusLabel($option), ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $out .= '</select>';
        $out .= '<button type="button" class="support-action-btn support-action-btn--ghost" data-status-ticket="' . $ticketId . '">Змінити статус</button>';
        $out .= '</div>';
        $out .= '<div class="support-desk-messages" id="supportDeskMessages">' . $this->renderMessages($messages, 'staff') . '</div>';
        $out .= '<div class="support-desk-compose">';
        $out .= '<div class="support-desk-templates">';
        $out .= '<input type="search" id="supportDeskTemplateSearch" placeholder="Пошук шаблону">';
        $out .= '<div class="support-desk-template-list" id="supportDeskTemplateList">';
        foreach ($templates as $template) {
            $out .= '<button type="button" class="support-template-item" data-template-id="' . (int)$template['id'] . '" data-template-title="' . htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8') . '" data-template-body="' . htmlspecialchars((string)$template['body'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8') . '</button>';
        }
        $out .= '</div>';
        $out .= '<form id="supportDeskTemplateManager" class="support-desk-template-manager">';
        $out .= '<input type="hidden" name="template_id" value="">';
        $out .= '<input type="text" name="title" id="supportDeskTemplateTitle" placeholder="Назва шаблону">';
        $out .= '<textarea name="body" id="supportDeskTemplateBody" rows="3" placeholder="Текст шаблону"></textarea>';
        $out .= '<div class="support-desk-template-actions">';
        $out .= '<button type="submit" class="support-action-btn support-action-btn--ghost">Зберегти шаблон</button>';
        $out .= '<button type="button" class="support-action-btn support-action-btn--ghost" id="supportDeskTemplateDelete">Видалити</button>';
        $out .= '</div>';
        $out .= '</form>';
        $out .= '</div>';
        $out .= '<form id="supportDeskReplyForm" enctype="multipart/form-data">';
        $out .= '<input type="hidden" name="ticket_id" value="' . $ticketId . '">';
        $out .= '<input type="hidden" name="template_id" value="">';
        $out .= '<div class="support-desk-preview" id="supportDeskPreview" aria-hidden="true"></div>';
        $out .= '<div class="support-desk-compose-row">';
        $out .= '<label class="support-desk-attach" for="supportDeskImg"><input type="file" name="img" id="supportDeskImg" accept="image/jpeg,image/png,image/gif,image/webp" hidden>+</label>';
        $out .= '<textarea name="message" id="supportDeskReplyMessage" placeholder="Відповідь клієнту..." rows="3"></textarea>';
        $out .= '<button type="submit">Надіслати</button>';
        $out .= '</div>';
        $out .= '</form>';
        $out .= '</div>';
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    public function renderMessages(array $messages, string $context = 'client'): string
    {
        if (empty($messages)) {
            return '<div class="support-empty-thread">Повідомлень поки немає. Напишіть першим.</div>';
        }

        $out = '';
        foreach ($messages as $message) {
            $senderType = (string)($message['sender_type'] ?? 'system');
            $out .= '<article class="support-message support-message--' . htmlspecialchars($senderType, ENT_QUOTES, 'UTF-8') . '" data-message-id="' . (int)($message['id'] ?? 0) . '">';
            $out .= '<div class="support-message-meta"><strong>' . htmlspecialchars((string)($message['display_name'] ?? 'Система'), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars((string)($message['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';
            if (!empty($message['image_path'])) {
                $src = htmlspecialchars((string)$message['image_path'], ENT_QUOTES, 'UTF-8');
                $out .= '<div class="support-message-image"><a href="' . $src . '" target="_blank" rel="noopener"><img src="' . $src . '" alt=""></a></div>';
            }
            if ((string)($message['body'] ?? '') !== '') {
                $out .= '<div class="support-message-body">' . nl2br(htmlspecialchars((string)$message['body'], ENT_QUOTES, 'UTF-8')) . '</div>';
            }
            if ($context === 'staff') {
                $out .= '<div class="support-message-type">' . htmlspecialchars($this->senderLabel($senderType), ENT_QUOTES, 'UTF-8') . '</div>';
            }
            $out .= '</article>';
        }
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
}
