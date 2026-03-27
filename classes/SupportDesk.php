<?php

class SupportDesk
{
    private const RESOLUTION_CONFIRMATION_TIMEOUT_HOURS = 24;

    private mysqli $dblink;
    /** @var array<int, string> */
    private array $userNameCache = [];
    /** @var string[] */
    private array $activeClientStatuses = ['new', 'open', 'waiting_customer', 'resolved'];
    /** @var string[] */
    private array $allStatuses = ['new', 'open', 'waiting_customer', 'resolved', 'closed', 'spam'];

    public function __construct(mysqli $dblink)
    {
        $this->dblink = $dblink;
    }

    public function isActiveClientStatus(string $status): bool
    {
        return in_array($status, $this->activeClientStatuses, true);
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, $this->allStatuses, true);
    }

    public function isWebmaster(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = "SELECT status FROM users WHERE idx = " . $userId . " LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res || mysqli_num_rows($res) !== 1) {
            return false;
        }

        $row = mysqli_fetch_assoc($res);
        $status = (int)($row['status'] ?? 0);
        return function_exists('hasRole') && defined('ROLE_WEBMASTER') && hasRole($status, ROLE_WEBMASTER);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWebmasters(): array
    {
        $out = [];
        $roleBit = defined('ROLE_WEBMASTER') ? (int)ROLE_WEBMASTER : 0;
        if ($roleBit <= 0) {
            return $out;
        }

        $sql = "SELECT idx, fname, lname, avatar
                FROM users
                WHERE (status & {$roleBit}) = {$roleBit}
                ORDER BY fname ASC, lname ASC, idx ASC";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return $out;
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $userId = (int)$row['idx'];
            $name = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
            if ($name === '') {
                $name = 'Вебмайстер #' . $userId;
            }
            $this->userNameCache[$userId] = $name;
            $out[] = [
                'id' => $userId,
                'name' => $name,
                'avatar' => (string)($row['avatar'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveTicketForClient(?int $userId, ?int $guestId): ?array
    {
        $this->maybeCloseExpiredResolutionConfirmations();

        if (($userId ?? 0) > 0) {
            return $this->findLatestTicketByRequester('requester_user_id', (int)$userId, $this->activeClientStatuses);
        }

        if ($guestId !== null) {
            return $this->findLatestTicketByRequester('requester_guest_id', $guestId, $this->activeClientStatuses);
        }

        return null;
    }

    /**
     * @param string[] $statuses
     * @return array<string, mixed>|null
     */
    private function findLatestTicketByRequester(string $column, int $value, array $statuses): ?array
    {
        if ($value === 0 || $column === '') {
            return null;
        }

        $safeColumn = $column === 'requester_user_id' ? 'requester_user_id' : 'requester_guest_id';
        $statusSql = $this->buildEnumList($statuses);
        $ownershipSql = $safeColumn === 'requester_guest_id' ? ' AND requester_user_id IS NULL' : '';

        $sql = "SELECT *
                FROM support_tickets
                WHERE {$safeColumn} = {$value}
                  {$ownershipSql}
                  AND status IN ({$statusSql})
                ORDER BY updated_at DESC, id DESC
                LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res || mysqli_num_rows($res) !== 1) {
            return null;
        }

        $ticket = mysqli_fetch_assoc($res);
        return $ticket ? $this->decorateTicket($ticket) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTicketById(int $ticketId): ?array
    {
        if ($ticketId <= 0) {
            return null;
        }

        $this->maybeCloseExpiredResolutionConfirmations($ticketId);

        $sql = "SELECT * FROM support_tickets WHERE id = {$ticketId} LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res || mysqli_num_rows($res) !== 1) {
            return null;
        }

        $ticket = mysqli_fetch_assoc($res);
        return $ticket ? $this->decorateTicket($ticket) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTicketForClient(int $ticketId, ?int $userId, ?int $guestId): ?array
    {
        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            return null;
        }

        if (($userId ?? 0) > 0 && (int)($ticket['requester_user_id'] ?? 0) === (int)$userId) {
            return $ticket;
        }

        if (
            $guestId !== null
            && (int)($ticket['requester_guest_id'] ?? 0) === (int)$guestId
            && (int)($ticket['requester_user_id'] ?? 0) === 0
        ) {
            return $ticket;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTicketForStaff(int $ticketId, int $staffUserId): ?array
    {
        if (!$this->isWebmaster($staffUserId)) {
            return null;
        }

        return $this->getTicketById($ticketId);
    }

    /**
     * @return array<string, mixed>
     */
    public function createTicket(?int $userId, ?int $guestId, string $source = 'messenger', ?string $subject = null): array
    {
        $requesterUserId = ($userId ?? 0) > 0 ? (int)$userId : 'NULL';
        $requesterGuestId = ($userId ?? 0) > 0
            ? 'NULL'
            : ($guestId !== null ? (int)$guestId : 'NULL');
        $subjectSql = $subject !== null && trim($subject) !== ''
            ? "'" . mysqli_real_escape_string($this->dblink, trim($subject)) . "'"
            : 'NULL';
        $sourceEsc = mysqli_real_escape_string($this->dblink, trim($source) !== '' ? trim($source) : 'messenger');

        $sql = "INSERT INTO support_tickets (
                    requester_user_id,
                    requester_guest_id,
                    status,
                    assignee_user_id,
                    priority,
                    source,
                    subject,
                    created_at,
                    updated_at
                ) VALUES (
                    {$requesterUserId},
                    {$requesterGuestId},
                    'new',
                    NULL,
                    'normal',
                    '{$sourceEsc}',
                    {$subjectSql},
                    NOW(),
                    NOW()
                )";

        mysqli_query($this->dblink, $sql);
        $ticketId = (int)mysqli_insert_id($this->dblink);
        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Не вдалося створити звернення підтримки.');
        }

        $this->logEvent($ticketId, 'created', null, null, 'new', [
            'source' => $sourceEsc,
        ]);
        $this->publishTicketRealtime($ticketId, 'support:ticket:new', $ticket);
        return $ticket;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(int $ticketId, int $afterId = 0, int $limit = 200): array
    {
        if ($ticketId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $afterSql = $afterId > 0 ? " AND id > " . $afterId : '';
        $sql = "SELECT *
                FROM support_messages
                WHERE ticket_id = {$ticketId}{$afterSql}
                ORDER BY created_at ASC, id ASC
                LIMIT {$limit}";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return [];
        }

        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = $this->decorateMessage($row);
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendClientMessage(?int $ticketId, ?int $userId, ?int $guestId, string $message, ?string $imagePath = null): array
    {
        $message = trim($message);
        $imagePath = $imagePath !== null && trim($imagePath) !== '' ? trim($imagePath) : null;

        if ($message === '' && $imagePath === null) {
            throw new RuntimeException('Порожнє повідомлення');
        }

        $ticket = $ticketId > 0 ? $this->getTicketForClient((int)$ticketId, $userId, $guestId) : null;
        if (!$ticket) {
            $ticket = $this->findActiveTicketForClient($userId, $guestId);
        }

        if (!$ticket || in_array((string)($ticket['status'] ?? ''), ['closed', 'spam'], true)) {
            $ticket = $this->createTicket($userId, $guestId, 'messenger', null);
        }

        $currentStatus = (string)($ticket['status'] ?? 'new');
        $pendingResolutionRequest = $currentStatus === 'waiting_customer'
            ? $this->getPendingResolutionConfirmation((int)$ticket['id'])
            : null;

        $inserted = $this->insertMessage(
            (int)$ticket['id'],
            'customer',
            ($userId ?? 0) > 0 ? (int)$userId : null,
            ($userId ?? 0) > 0 ? null : $guestId,
            $message,
            $imagePath
        );

        $this->touchTicketAfterMessage((int)$ticket['id'], 'customer');

        if ($pendingResolutionRequest !== null) {
            $nextStatus = $this->isResolutionConfirmedMessage($message) ? 'resolved' : 'open';
            $nextReason = $nextStatus === 'resolved'
                ? 'customer_confirmed_resolution'
                : 'customer_reply_after_resolution_request';
            $this->changeStatusInternal((int)$ticket['id'], $nextStatus, ($userId ?? 0) > 0 ? (int)$userId : null, 'status_changed', [
                'reason' => $nextReason,
            ]);
        } elseif (in_array($currentStatus, ['waiting_customer', 'resolved'], true)) {
            $this->changeStatusInternal((int)$ticket['id'], 'open', ($userId ?? 0) > 0 ? (int)$userId : null, 'reopened', [
                'reason' => 'customer_reply',
            ]);
        }

        $freshTicket = $this->getTicketById((int)$ticket['id']);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося оновити звернення.');
        }

        if ((int)($inserted['id'] ?? 0) > 0) {
            $this->publishMessageRealtime($freshTicket, $inserted);
        }

        if ((int)$this->countTicketMessages((int)$ticket['id']) === 1) {
            $systemMessage = $this->insertMessage(
                (int)$ticket['id'],
                'system',
                null,
                null,
                'Ваше звернення отримано. Вебмайстер відповість найближчим часом.',
                null
            );
            $this->publishMessageRealtime($freshTicket, $systemMessage);
        }

        return [
            'ticket' => $freshTicket,
            'message' => $inserted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendStaffMessage(int $ticketId, int $staffUserId, string $message, ?string $imagePath = null, ?int $templateId = null): array
    {
        if (!$this->isWebmaster($staffUserId)) {
            throw new RuntimeException('Доступ заборонено.');
        }

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Звернення не знайдено.');
        }

        $message = trim($message);
        $imagePath = $imagePath !== null && trim($imagePath) !== '' ? trim($imagePath) : null;
        if ($message === '' && $imagePath === null) {
            throw new RuntimeException('Порожнє повідомлення');
        }

        $hadStaffMessages = $this->hasStaffMessages($ticketId);
        $oldAssignee = (int)($ticket['assignee_user_id'] ?? 0);
        if ($oldAssignee <= 0) {
            $this->assignTicketInternal($ticketId, null, $staffUserId, $staffUserId, 'auto_claim_from_reply', 'claimed');
        }

        $joinedMessage = null;
        if (!$hadStaffMessages) {
            $joinedMessage = $this->insertMessage(
                $ticketId,
                'system',
                null,
                null,
                'Спеціаліст приєднався до чату.',
                null
            );
        }

        $inserted = $this->insertMessage($ticketId, 'staff', $staffUserId, null, $message, $imagePath);
        $this->touchTicketAfterMessage($ticketId, 'staff');

        if ($this->messageRequestsCustomerInfo($message)) {
            $this->changeStatusInternal($ticketId, 'waiting_customer', $staffUserId, 'status_changed', [
                'reason' => 'needs_customer_info',
            ]);
        } elseif ((string)($ticket['status'] ?? 'new') !== 'open') {
            $this->changeStatusInternal($ticketId, 'open', $staffUserId, 'status_changed', [
                'reason' => 'staff_reply',
            ]);
        }

        if ($templateId !== null && $templateId > 0) {
            $this->logEvent($ticketId, 'template_used', $staffUserId, null, null, [
                'template_id' => $templateId,
            ]);
        }

        $freshTicket = $this->getTicketById($ticketId);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося оновити звернення.');
        }

        if ($joinedMessage !== null) {
            $this->publishMessageRealtime($freshTicket, $joinedMessage);
        }
        $this->publishMessageRealtime($freshTicket, $inserted);

        return [
            'ticket' => $freshTicket,
            'message' => $inserted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function claimTicket(int $ticketId, int $staffUserId): array
    {
        if (!$this->isWebmaster($staffUserId)) {
            throw new RuntimeException('Доступ заборонено.');
        }

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Звернення не знайдено.');
        }

        $fromUserId = (int)($ticket['assignee_user_id'] ?? 0);
        $this->assignTicketInternal($ticketId, $fromUserId > 0 ? $fromUserId : null, $staffUserId, $staffUserId, null, 'claimed');

        if ((string)($ticket['status'] ?? 'new') === 'new') {
            $this->changeStatusInternal($ticketId, 'open', $staffUserId, 'status_changed', [
                'reason' => 'claim',
            ]);
        }

        $freshTicket = $this->getTicketById($ticketId);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося взяти звернення в роботу.');
        }

        $this->publishTicketRealtime($ticketId, 'support:ticket:claimed', $freshTicket);
        return $freshTicket;
    }

    /**
     * @return array<string, mixed>
     */
    public function transferTicket(int $ticketId, int $actorUserId, int $toUserId, string $note = ''): array
    {
        if (!$this->isWebmaster($actorUserId) || !$this->isWebmaster($toUserId)) {
            throw new RuntimeException('Передача доступна лише вебмайстрам.');
        }

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Звернення не знайдено.');
        }

        $fromUserId = (int)($ticket['assignee_user_id'] ?? 0);
        $this->assignTicketInternal($ticketId, $fromUserId > 0 ? $fromUserId : null, $toUserId, $actorUserId, $note, 'transferred');

        if ((string)($ticket['status'] ?? 'new') === 'new') {
            $this->changeStatusInternal($ticketId, 'open', $actorUserId, 'status_changed', [
                'reason' => 'transfer',
            ]);
        }

        $freshTicket = $this->getTicketById($ticketId);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося передати звернення.');
        }

        $this->publishTicketRealtime($ticketId, 'support:ticket:transferred', $freshTicket);
        return $freshTicket;
    }

    /**
     * @return array<string, mixed>
     */
    public function changeStatus(int $ticketId, int $staffUserId, string $status): array
    {
        if (!$this->isWebmaster($staffUserId)) {
            throw new RuntimeException('Доступ заборонено.');
        }
        if (!$this->isValidStatus($status)) {
            throw new RuntimeException('Невірний статус.');
        }

        $this->changeStatusInternal($ticketId, $status, $staffUserId, 'status_changed', null);
        $freshTicket = $this->getTicketById($ticketId);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося змінити статус.');
        }

        $this->publishTicketRealtime($ticketId, 'support:ticket:status_changed', $freshTicket);
        return $freshTicket;
    }

    /**
     * @return array<string, mixed>
     */
    public function requestResolutionConfirmation(int $ticketId, int $staffUserId): array
    {
        if (!$this->isWebmaster($staffUserId)) {
            throw new RuntimeException('Доступ заборонено.');
        }

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Звернення не знайдено.');
        }

        $status = (string)($ticket['status'] ?? 'new');
        if (in_array($status, ['resolved', 'closed', 'spam'], true)) {
            throw new RuntimeException('Для цього звернення підтвердження вирішення вже недоступне.');
        }

        if ($this->getPendingResolutionConfirmation($ticketId) !== null) {
            $freshTicket = $this->getTicketById($ticketId);
            if (!$freshTicket) {
                throw new RuntimeException('Не вдалося оновити звернення.');
            }

            return [
                'ticket' => $freshTicket,
                'message' => null,
            ];
        }

        $assigneeUserId = (int)($ticket['assignee_user_id'] ?? 0);
        if ($assigneeUserId <= 0) {
            $this->assignTicketInternal($ticketId, null, $staffUserId, $staffUserId, 'auto_claim_before_resolution_confirmation', 'claimed');
        }

        $systemMessage = $this->insertMessage(
            $ticketId,
            'system',
            null,
            null,
            'Підтримка повідомила, що проблему вирішено. Якщо все гаразд, підтвердьте це кнопкою нижче або напишіть у чат. Якщо питання ще актуальне, просто надішліть повідомлення.',
            null
        );

        $this->changeStatusInternal($ticketId, 'waiting_customer', $staffUserId, 'status_changed', [
            'reason' => 'resolution_confirmation',
            'timeout_hours' => self::RESOLUTION_CONFIRMATION_TIMEOUT_HOURS,
            'deadline_at' => date('Y-m-d H:i:s', time() + (self::RESOLUTION_CONFIRMATION_TIMEOUT_HOURS * 3600)),
        ]);

        $freshTicket = $this->getTicketById($ticketId);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося оновити звернення.');
        }

        $this->publishMessageRealtime($freshTicket, $systemMessage);
        $this->publishTicketRealtime($ticketId, 'support:ticket:status_changed', $freshTicket);

        return [
            'ticket' => $freshTicket,
            'message' => $systemMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmResolutionByClient(int $ticketId, ?int $userId, ?int $guestId): array
    {
        $ticket = $this->getTicketForClient($ticketId, $userId, $guestId);
        if (!$ticket) {
            throw new RuntimeException('Звернення не знайдено.');
        }

        if ($this->getPendingResolutionConfirmation($ticketId) === null) {
            throw new RuntimeException('Підтвердження для цього звернення вже неактуальне.');
        }

        $message = $this->insertMessage(
            $ticketId,
            'customer',
            ($userId ?? 0) > 0 ? (int)$userId : null,
            ($userId ?? 0) > 0 ? null : $guestId,
            'Так, проблему вирішено. Дякую!',
            null
        );
        $this->touchTicketAfterMessage($ticketId, 'customer');
        $this->changeStatusInternal($ticketId, 'resolved', ($userId ?? 0) > 0 ? (int)$userId : null, 'status_changed', [
            'reason' => 'customer_confirmed_resolution',
        ]);

        $freshTicket = $this->getTicketById($ticketId);
        if (!$freshTicket) {
            throw new RuntimeException('Не вдалося оновити звернення.');
        }

        $this->publishMessageRealtime($freshTicket, $message);
        $this->publishTicketRealtime($ticketId, 'support:ticket:status_changed', $freshTicket);

        return [
            'ticket' => $freshTicket,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTicketsByBucket(int $staffUserId, string $bucket): array
    {
        if (!$this->isWebmaster($staffUserId)) {
            return [];
        }

        $this->maybeCloseExpiredResolutionConfirmations();

        $where = '1=0';
        switch ($bucket) {
            case 'queue':
                $where = "status = 'new' AND assignee_user_id IS NULL";
                break;
            case 'my':
                $where = "status = 'open' AND assignee_user_id = {$staffUserId}";
                break;
            case 'waiting':
                $where = "status = 'waiting_customer' AND assignee_user_id = {$staffUserId}";
                break;
            case 'resolved':
                $where = "status = 'resolved'";
                break;
            case 'closed':
                $where = "status = 'closed'";
                break;
            case 'spam':
                $where = "status = 'spam'";
                break;
            default:
                $where = "status = 'new' AND assignee_user_id IS NULL";
                break;
        }

        $sql = "SELECT *
                FROM support_tickets
                WHERE {$where}
                ORDER BY updated_at DESC, id DESC
                LIMIT 200";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return [];
        }

        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = $this->decorateTicket($row);
        }
        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function getBucketCounts(int $staffUserId): array
    {
        $this->maybeCloseExpiredResolutionConfirmations();

        return [
            'queue' => $this->countTicketsForWhere("status = 'new' AND assignee_user_id IS NULL"),
            'my' => $this->countTicketsForWhere("status = 'open' AND assignee_user_id = " . $staffUserId),
            'waiting' => $this->countTicketsForWhere("status = 'waiting_customer' AND assignee_user_id = " . $staffUserId),
            'resolved' => $this->countTicketsForWhere("status = 'resolved'"),
            'closed' => $this->countTicketsForWhere("status = 'closed'"),
            'spam' => $this->countTicketsForWhere("status = 'spam'"),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(string $search = ''): array
    {
        $where = 'is_active = 1';
        if ($search !== '') {
            $searchEsc = mysqli_real_escape_string($this->dblink, '%' . $search . '%');
            $where .= " AND (title LIKE '{$searchEsc}' OR body LIKE '{$searchEsc}')";
        }

        $sql = "SELECT *
                FROM support_templates
                WHERE {$where}
                ORDER BY updated_at DESC, id DESC";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return [];
        }

        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = [
                'id' => (int)$row['id'],
                'title' => (string)($row['title'] ?? ''),
                'body' => (string)($row['body'] ?? ''),
                'is_active' => (int)($row['is_active'] ?? 1),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function saveTemplate(int $actorUserId, string $title, string $body, ?int $templateId = null): array
    {
        if (!$this->isWebmaster($actorUserId)) {
            throw new RuntimeException('Доступ заборонено.');
        }

        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            throw new RuntimeException('Заповніть назву та текст шаблону.');
        }

        $titleEsc = mysqli_real_escape_string($this->dblink, $title);
        $bodyEsc = mysqli_real_escape_string($this->dblink, $body);

        if ($templateId !== null && $templateId > 0) {
            $sql = "UPDATE support_templates
                    SET title = '{$titleEsc}',
                        body = '{$bodyEsc}',
                        updated_by_user_id = {$actorUserId},
                        updated_at = NOW()
                    WHERE id = {$templateId}
                    LIMIT 1";
            mysqli_query($this->dblink, $sql);
            $id = $templateId;
        } else {
            $sql = "INSERT INTO support_templates (
                        title,
                        body,
                        is_active,
                        created_by_user_id,
                        updated_by_user_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        '{$titleEsc}',
                        '{$bodyEsc}',
                        1,
                        {$actorUserId},
                        {$actorUserId},
                        NOW(),
                        NOW()
                    )";
            mysqli_query($this->dblink, $sql);
            $id = (int)mysqli_insert_id($this->dblink);
        }

        $sql = "SELECT * FROM support_templates WHERE id = {$id} LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (!$row) {
            throw new RuntimeException('Не вдалося зберегти шаблон.');
        }

        return [
            'id' => (int)$row['id'],
            'title' => (string)($row['title'] ?? ''),
            'body' => (string)($row['body'] ?? ''),
            'is_active' => (int)($row['is_active'] ?? 1),
        ];
    }

    public function deleteTemplate(int $actorUserId, int $templateId): bool
    {
        if (!$this->isWebmaster($actorUserId)) {
            throw new RuntimeException('Доступ заборонено.');
        }
        if ($templateId <= 0) {
            throw new RuntimeException('Шаблон не знайдено.');
        }

        $sql = "UPDATE support_templates
                SET is_active = 0,
                    updated_by_user_id = {$actorUserId},
                    updated_at = NOW()
                WHERE id = {$templateId}
                LIMIT 1";
        return (bool)mysqli_query($this->dblink, $sql);
    }

    public function getSocketConfig(): array
    {
        return [
            'socket_url' => '',
            'internal_url' => '',
            'enabled' => false,
        ];
    }

    public function publishMessageRealtime(array $ticket, array $message): void
    {
        return;
    }

    public function publishTicketRealtime(int $ticketId, string $event, array $ticket): void
    {
        return;
    }

    /**
     * @return array<string, mixed>
     */
    private function decorateTicket(array $ticket): array
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $ticket['id'] = $ticketId;
        $ticket['requester_user_id'] = isset($ticket['requester_user_id']) ? (int)$ticket['requester_user_id'] : null;
        $ticket['requester_guest_id'] = isset($ticket['requester_guest_id']) ? (int)$ticket['requester_guest_id'] : null;
        $ticket['assignee_user_id'] = isset($ticket['assignee_user_id']) ? (int)$ticket['assignee_user_id'] : null;
        $ticket['legacy_chat_id'] = isset($ticket['legacy_chat_id']) ? (int)$ticket['legacy_chat_id'] : null;
        $ticket['requester_label'] = $this->resolveRequesterLabel($ticket);
        $ticket['assignee_label'] = ((int)($ticket['assignee_user_id'] ?? 0) > 0)
            ? $this->getUserDisplayName((int)$ticket['assignee_user_id'])
            : '';

        $lastMessage = $this->getLastMessageForTicket($ticketId);
        $ticket['last_message_preview'] = $lastMessage['body'] !== ''
            ? mb_strimwidth($lastMessage['body'], 0, 120, '...')
            : ($lastMessage['image_path'] !== '' ? '[Зображення]' : '');
        $ticket['last_message_at'] = $lastMessage['created_at'] !== '' ? $lastMessage['created_at'] : (string)($ticket['updated_at'] ?? '');
        $ticket = array_merge($ticket, $this->resolveRequesterMeta($ticket));
        $ticket['resolution_confirmation_pending'] = false;
        $ticket['resolution_confirmation_deadline_at'] = '';
        if ((string)($ticket['status'] ?? '') === 'waiting_customer') {
            $pendingResolutionRequest = $this->getPendingResolutionConfirmation($ticketId);
            if ($pendingResolutionRequest !== null) {
                $ticket['resolution_confirmation_pending'] = true;
                $ticket['resolution_confirmation_deadline_at'] = (string)($pendingResolutionRequest['deadline_at'] ?? '');
            }
        }

        return $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRequesterMeta(array $ticket): array
    {
        $userId = (int)($ticket['requester_user_id'] ?? 0);
        $guestId = (int)($ticket['requester_guest_id'] ?? 0);
        $name = (string)($ticket['requester_label'] ?? 'Клієнт');

        $meta = [
            'requester_email' => '',
            'requester_phone' => '',
            'requester_avatar' => '',
            'requester_initial' => $this->buildInitial($name),
            'requester_since' => $this->getRequesterFirstTicketDate($userId, $guestId),
            'requester_tickets_count' => $this->countTicketsForRequester($userId, $guestId),
        ];

        if ($userId > 0) {
            $sql = "SELECT email, tel, avatar, fname, lname
                    FROM users
                    WHERE idx = {$userId}
                    LIMIT 1";
            $res = mysqli_query($this->dblink, $sql);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if ($row) {
                $resolvedName = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
                if ($resolvedName !== '') {
                    $meta['requester_initial'] = $this->buildInitial($resolvedName);
                }
                $meta['requester_email'] = (string)($row['email'] ?? '');
                $meta['requester_phone'] = (string)($row['tel'] ?? '');
                $meta['requester_avatar'] = (string)($row['avatar'] ?? '');
            }
        }

        return $meta;
    }

    private function buildInitial(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'K';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($name, 0, 1));
        }

        return strtoupper(substr($name, 0, 1));
    }

    private function countTicketsForRequester(int $userId, int $guestId): int
    {
        if ($userId > 0) {
            return $this->countTicketsForWhere("requester_user_id = {$userId}");
        }
        if ($guestId !== 0) {
            return $this->countTicketsForWhere("requester_guest_id = {$guestId} AND requester_user_id IS NULL");
        }

        return 0;
    }

    private function getRequesterFirstTicketDate(int $userId, int $guestId): string
    {
        $where = '';
        if ($userId > 0) {
            $where = "requester_user_id = {$userId}";
        } elseif ($guestId !== 0) {
            $where = "requester_guest_id = {$guestId} AND requester_user_id IS NULL";
        }

        if ($where === '') {
            return '';
        }

        $sql = "SELECT created_at
                FROM support_tickets
                WHERE {$where}
                ORDER BY created_at ASC, id ASC
                LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return (string)($row['created_at'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function decorateMessage(array $message): array
    {
        $senderType = (string)($message['sender_type'] ?? 'system');
        $senderUserId = isset($message['sender_user_id']) ? (int)$message['sender_user_id'] : null;
        $senderGuestId = isset($message['sender_guest_id']) ? (int)$message['sender_guest_id'] : null;

        $displayName = 'Система';
        if ($senderType === 'customer') {
            if (($senderUserId ?? 0) > 0) {
                $displayName = $this->getUserDisplayName((int)$senderUserId);
            } elseif ($senderGuestId !== null) {
                $displayName = 'Гість #' . abs($senderGuestId);
            } else {
                $displayName = 'Клієнт';
            }
        } elseif ($senderType === 'staff') {
            $displayName = ($senderUserId ?? 0) > 0
                ? $this->getUserDisplayName((int)$senderUserId)
                : 'Вебмайстер';
        }

        return [
            'id' => (int)($message['id'] ?? 0),
            'ticket_id' => (int)($message['ticket_id'] ?? 0),
            'sender_type' => $senderType,
            'sender_user_id' => $senderUserId,
            'sender_guest_id' => $senderGuestId,
            'body' => (string)($message['body'] ?? ''),
            'image_path' => (string)($message['image_path'] ?? ''),
            'created_at' => (string)($message['created_at'] ?? ''),
            'display_name' => $displayName,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getLastMessageForTicket(int $ticketId): array
    {
        $sql = "SELECT body, image_path, created_at
                FROM support_messages
                WHERE ticket_id = {$ticketId}
                ORDER BY created_at DESC, id DESC
                LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return [
            'body' => (string)($row['body'] ?? ''),
            'image_path' => (string)($row['image_path'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }

    private function resolveRequesterLabel(array $ticket): string
    {
        $userId = (int)($ticket['requester_user_id'] ?? 0);
        if ($userId > 0) {
            return $this->getUserDisplayName($userId);
        }

        $guestId = (int)($ticket['requester_guest_id'] ?? 0);
        if ($guestId !== 0) {
            return 'Гість #' . abs($guestId);
        }

        return 'Клієнт';
    }

    private function getUserDisplayName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        if (isset($this->userNameCache[$userId])) {
            return $this->userNameCache[$userId];
        }

        $sql = "SELECT fname, lname FROM users WHERE idx = {$userId} LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $name = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
        if ($name === '') {
            $name = 'Користувач #' . $userId;
        }
        $this->userNameCache[$userId] = $name;
        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function insertMessage(int $ticketId, string $senderType, ?int $senderUserId, ?int $senderGuestId, string $body, ?string $imagePath): array
    {
        $bodySql = trim($body) !== ''
            ? "'" . mysqli_real_escape_string($this->dblink, $body) . "'"
            : 'NULL';
        $imageSql = $imagePath !== null && $imagePath !== ''
            ? "'" . mysqli_real_escape_string($this->dblink, $imagePath) . "'"
            : 'NULL';
        $senderTypeEsc = mysqli_real_escape_string($this->dblink, $senderType);
        $senderUserSql = $senderUserId !== null && $senderUserId > 0 ? (int)$senderUserId : 'NULL';
        $senderGuestSql = $senderGuestId !== null ? (int)$senderGuestId : 'NULL';

        $sql = "INSERT INTO support_messages (
                    ticket_id,
                    sender_type,
                    sender_user_id,
                    sender_guest_id,
                    body,
                    image_path,
                    created_at
                ) VALUES (
                    {$ticketId},
                    '{$senderTypeEsc}',
                    {$senderUserSql},
                    {$senderGuestSql},
                    {$bodySql},
                    {$imageSql},
                    NOW()
                )";

        mysqli_query($this->dblink, $sql);
        $messageId = (int)mysqli_insert_id($this->dblink);
        $sql = "SELECT * FROM support_messages WHERE id = {$messageId} LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (!$row) {
            throw new RuntimeException('Не вдалося зберегти повідомлення.');
        }
        return $this->decorateMessage($row);
    }

    private function hasStaffMessages(int $ticketId): bool
    {
        $sql = "SELECT id
                FROM support_messages
                WHERE ticket_id = {$ticketId}
                  AND sender_type = 'staff'
                LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        return $res && mysqli_num_rows($res) > 0;
    }

    private function messageRequestsCustomerInfo(string $message): bool
    {
        $normalized = $this->normalizeMessageText($message);
        if ($normalized === '') {
            return false;
        }

        $patterns = [
            '/уточн/u',
            '/додатков/u',
            '/детал/u',
            '/більше інформа/u',
            '/больше информа/u',
            '/надішліть/u',
            '/пришлите/u',
            '/вкажіть/u',
            '/укажите/u',
            '/повідомте/u',
            '/сообщите/u',
            '/скрін/u',
            '/скрин/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function isResolutionConfirmedMessage(string $message): bool
    {
        $normalized = $this->normalizeMessageText($message);
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\b(не|ні|нет)\b/u', $normalized)) {
            return false;
        }

        if (preg_match('/^(так|да|ок|okay|добре|добро|дякую|спасибо|підтверджую|подтверждаю|готово|вирішено|решено|працює)([[:punct:]\s]|$)/u', $normalized)) {
            return true;
        }

        return preg_match('/(проблему вирішено|проблема вирішена|все добре|все працює|усе добре|вопрос решен|всё работает|все работает)/u', $normalized) === 1;
    }

    private function normalizeMessageText(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $message = mb_strtolower($message, 'UTF-8');
        } else {
            $message = strtolower($message);
        }

        return preg_replace('/\s+/u', ' ', $message) ?? $message;
    }

    private function touchTicketAfterMessage(int $ticketId, string $senderType): void
    {
        $field = $senderType === 'staff' ? 'last_staff_message_at' : 'last_customer_message_at';
        $sql = "UPDATE support_tickets
                SET {$field} = NOW(),
                    updated_at = NOW()
                WHERE id = {$ticketId}
                LIMIT 1";
        mysqli_query($this->dblink, $sql);
    }

    private function countTicketMessages(int $ticketId): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM support_messages WHERE ticket_id = {$ticketId}";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return (int)($row['cnt'] ?? 0);
    }

    private function assignTicketInternal(int $ticketId, ?int $fromUserId, ?int $toUserId, int $actorUserId, ?string $note, string $eventType): void
    {
        $toSql = $toUserId !== null && $toUserId > 0 ? (int)$toUserId : 'NULL';
        $sql = "UPDATE support_tickets
                SET assignee_user_id = {$toSql},
                    updated_at = NOW()
                WHERE id = {$ticketId}
                LIMIT 1";
        mysqli_query($this->dblink, $sql);

        $fromSql = $fromUserId !== null && $fromUserId > 0 ? (int)$fromUserId : 'NULL';
        $noteSql = $note !== null && trim($note) !== ''
            ? "'" . mysqli_real_escape_string($this->dblink, trim($note)) . "'"
            : 'NULL';

        mysqli_query(
            $this->dblink,
            "INSERT INTO support_ticket_assignments (
                ticket_id,
                from_user_id,
                to_user_id,
                assigned_by_user_id,
                note,
                created_at
            ) VALUES (
                {$ticketId},
                {$fromSql},
                {$toSql},
                {$actorUserId},
                {$noteSql},
                NOW()
            )"
        );

        $this->logEvent($ticketId, $eventType, $actorUserId, null, null, [
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'note' => $note,
        ]);
    }

    private function changeStatusInternal(int $ticketId, string $newStatus, ?int $actorUserId, string $eventType, ?array $payload): void
    {
        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            throw new RuntimeException('Звернення не знайдено.');
        }

        $oldStatus = (string)($ticket['status'] ?? '');
        if ($oldStatus === $newStatus) {
            return;
        }

        $resolvedSql = $newStatus === 'resolved' ? 'NOW()' : (($newStatus === 'open' || $newStatus === 'new') ? 'NULL' : 'resolved_at');
        $closedSql = in_array($newStatus, ['closed', 'spam'], true) ? 'NOW()' : (($newStatus === 'open' || $newStatus === 'new' || $newStatus === 'resolved' || $newStatus === 'waiting_customer') ? 'NULL' : 'closed_at');

        $sql = "UPDATE support_tickets
                SET status = '" . mysqli_real_escape_string($this->dblink, $newStatus) . "',
                    resolved_at = {$resolvedSql},
                    closed_at = {$closedSql},
                    updated_at = NOW()
                WHERE id = {$ticketId}
                LIMIT 1";
        mysqli_query($this->dblink, $sql);

        $this->logEvent($ticketId, $eventType, $actorUserId, $oldStatus, $newStatus, $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPendingResolutionConfirmation(int $ticketId): ?array
    {
        if ($ticketId <= 0) {
            return null;
        }

        $sql = "SELECT created_at, payload_json
                FROM support_ticket_events
                WHERE ticket_id = {$ticketId}
                  AND to_status = 'waiting_customer'
                  AND payload_json LIKE '%\"reason\":\"resolution_confirmation\"%'
                ORDER BY id DESC
                LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if (!$row) {
            return null;
        }

        $payload = [];
        if (!empty($row['payload_json'])) {
            $decoded = json_decode((string)$row['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $deadlineAt = (string)($payload['deadline_at'] ?? '');
        if ($deadlineAt === '') {
            $timestamp = strtotime((string)($row['created_at'] ?? ''));
            if ($timestamp !== false) {
                $deadlineAt = date('Y-m-d H:i:s', $timestamp + (self::RESOLUTION_CONFIRMATION_TIMEOUT_HOURS * 3600));
            }
        }

        return [
            'created_at' => (string)($row['created_at'] ?? ''),
            'deadline_at' => $deadlineAt,
        ];
    }

    private function maybeCloseExpiredResolutionConfirmations(?int $ticketId = null): void
    {
        $ticketFilter = $ticketId !== null && $ticketId > 0 ? " AND t.id = " . (int)$ticketId : '';
        $hours = self::RESOLUTION_CONFIRMATION_TIMEOUT_HOURS;
        $sql = "SELECT t.id
                FROM support_tickets t
                INNER JOIN support_ticket_events e
                    ON e.id = (
                        SELECT e2.id
                        FROM support_ticket_events e2
                        WHERE e2.ticket_id = t.id
                          AND e2.to_status = 'waiting_customer'
                        ORDER BY e2.id DESC
                        LIMIT 1
                    )
                WHERE t.status = 'waiting_customer'
                  AND e.payload_json LIKE '%\"reason\":\"resolution_confirmation\"%'
                  AND e.created_at <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
                  AND NOT EXISTS (
                        SELECT 1
                        FROM support_messages sm
                        WHERE sm.ticket_id = t.id
                          AND sm.sender_type = 'customer'
                          AND sm.created_at > e.created_at
                    ){$ticketFilter}";
        $res = mysqli_query($this->dblink, $sql);
        if (!$res) {
            return;
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $expiredTicketId = (int)($row['id'] ?? 0);
            if ($expiredTicketId <= 0) {
                continue;
            }

            mysqli_query(
                $this->dblink,
                "UPDATE support_tickets
                 SET status = 'closed',
                     closed_at = NOW(),
                     updated_at = NOW()
                 WHERE id = {$expiredTicketId}
                   AND status = 'waiting_customer'
                 LIMIT 1"
            );

            if ((int)mysqli_affected_rows($this->dblink) <= 0) {
                continue;
            }

            $this->logEvent($expiredTicketId, 'status_changed', null, 'waiting_customer', 'closed', [
                'reason' => 'resolution_confirmation_timeout',
            ]);

            $systemMessage = $this->insertMessage(
                $expiredTicketId,
                'system',
                null,
                null,
                'Звернення закрито, оскільки клієнт не підтвердив вирішення протягом 24 годин.',
                null
            );

            $freshTicket = $this->getTicketById($expiredTicketId);
            if ($freshTicket) {
                $this->publishMessageRealtime($freshTicket, $systemMessage);
                $this->publishTicketRealtime($expiredTicketId, 'support:ticket:status_changed', $freshTicket);
            }
        }
    }

    private function logEvent(int $ticketId, string $eventType, ?int $actorUserId, ?string $fromStatus, ?string $toStatus, ?array $payload): void
    {
        $eventTypeEsc = mysqli_real_escape_string($this->dblink, $eventType);
        $actorSql = $actorUserId !== null && $actorUserId > 0 ? (int)$actorUserId : 'NULL';
        $fromSql = $fromStatus !== null ? "'" . mysqli_real_escape_string($this->dblink, $fromStatus) . "'" : 'NULL';
        $toSql = $toStatus !== null ? "'" . mysqli_real_escape_string($this->dblink, $toStatus) . "'" : 'NULL';
        $payloadSql = $payload !== null
            ? "'" . mysqli_real_escape_string($this->dblink, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "'"
            : 'NULL';

        $sql = "INSERT INTO support_ticket_events (
                    ticket_id,
                    event_type,
                    actor_user_id,
                    from_status,
                    to_status,
                    payload_json,
                    created_at
                ) VALUES (
                    {$ticketId},
                    '{$eventTypeEsc}',
                    {$actorSql},
                    {$fromSql},
                    {$toSql},
                    {$payloadSql},
                    NOW()
                )";
        mysqli_query($this->dblink, $sql);
    }

    private function countTicketsForWhere(string $where): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM support_tickets WHERE {$where}";
        $res = mysqli_query($this->dblink, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * @param string[] $items
     */
    private function buildEnumList(array $items): string
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = "'" . mysqli_real_escape_string($this->dblink, $item) . "'";
        }
        return implode(', ', $out);
    }

    private function postInternalRealtime(string $path, array $payload): void
    {
        $internalUrl = trim((string)($_ENV['SOCKET_INTERNAL_URL'] ?? ''));
        $secret = trim((string)($_ENV['SOCKET_INTERNAL_SECRET'] ?? ''));
        if ($internalUrl === '' || $secret === '') {
            return;
        }

        $base = rtrim($internalUrl, '/');
        $url = $base . '/' . ltrim($path, '/');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-Support-Internal-Secret: {$secret}\r\n",
                'content' => $body,
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        try {
            @file_get_contents($url, false, $context);
        } catch (Throwable $e) {
            // Realtime should not break the core support flow.
        }
    }
}
