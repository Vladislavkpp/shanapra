<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/SupportDesk.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/SupportRender.php';

session_start();

function supportDeskEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function supportDeskSendJson(array $data): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function supportDeskDenyJson(int $code): void
{
    $code = $code === 401 ? 401 : 403;
    http_response_code($code);
    supportDeskSendJson([
        'status' => 'error',
        'msg' => $code === 401 ? 'Потрібна авторизація' : 'Доступ заборонено',
    ]);
}

function supportDeskSaveUploadedImage(int $entityId = 0): string
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

    $prefix = $entityId > 0 ? $entityId : 'desk';
    $filename = 'support_staff_' . $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $fullPath = $uploadDir . $filename;
    if (!move_uploaded_file($_FILES['img']['tmp_name'], $fullPath)) {
        throw new RuntimeException('Не вдалося зберегти зображення.');
    }

    return '/chat_images/' . $filename;
}

function supportDeskIsMobileRequest(): bool
{
    $userAgent = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') {
        return false;
    }

    return preg_match('/android|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile/u', $userAgent) === 1;
}

function supportDeskRenderDesktopOnlyPage(): void
{
    View_Clear();
    View_Add(Page_Up('Support Desk'));
    View_Add('<link rel="stylesheet" href="/assets/css/in-dev.css">');
    View_Add(Menu_Up());
    View_Add('<div class="out-index out-index--404">');
    View_Add('
        <section class="page-404">
            <div class="page-404__inner">
                <div class="page-404__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M0 0h24v24H0z" stroke="none" fill="none"></path>
                        <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"></path>
                        <path d="M7 20h10"></path>
                        <path d="M9 16v4"></path>
                        <path d="M15 16v4"></path>
                    </svg>
                </div>
                <h1 class="page-404__title">Support Desk доступний лише на десктопній версії</h1>
                <p class="page-404__text">Щоб працювати зі зверненнями, відкрийте цю сторінку з компʼютера або ноутбука. На телефоні консоль оператора недоступна.</p>
                <div class="page-404__actions">
                    <a class="page-404__btn page-404__btn--primary" href="/">На головну</a>
                </div>
            </div>
        </section>
    ');
    View_Add('</div>');
    View_Out();
    exit;
}

function supportDeskRenderAccessPage(int $code): void
{
    global $hide_page_down;

    $code = $code === 401 ? 401 : 403;
    $requestUri = supportDeskEsc((string)($_SERVER['REQUEST_URI'] ?? ''));
    $isUnauthorized = $code === 401;
    $title = $isUnauthorized ? 'Потрібна авторизація' : 'Доступ заборонено';
    $text = $isUnauthorized
        ? 'Для перегляду цієї сторінки потрібен вхід в обліковий запис.'
        : 'Ви авторизовані, але ваш обліковий запис не має прав доступу до Support Desk.';
    $primaryUrl = $isUnauthorized ? '/auth.php' : '/';
    $primaryLabel = $isUnauthorized ? 'Увійти' : 'На головну';

    http_response_code($code);
    $hide_page_down = true;
    View_Clear();
    View_Add(Page_Up($title . ' — ' . $code));
    View_Add('<link rel="stylesheet" href="/assets/css/404.css">');
    View_Add(Menu_Up());
    View_Add('<div class="out-index out-index--404">');
    View_Add('<div class="page-404"><div class="page-404__inner"><div class="page-404__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div><div class="page-404__code" aria-hidden="true">' . $code . '</div><h1 class="page-404__title">' . supportDeskEsc($title) . '</h1><p class="page-404__text">' . supportDeskEsc($text) . '</p>' . ($requestUri !== '' ? '<div class="page-404__uri">' . $requestUri . '</div>' : '') . '<div class="page-404__actions"><a href="' . $primaryUrl . '" class="page-404__btn page-404__btn--primary">' . supportDeskEsc($primaryLabel) . '</a></div></div></div>');
    View_Add('</div>');
    View_Add(Page_Down());
    View_Out();
    exit;
}

$userId = isset($_SESSION['uzver']) ? (int)$_SESSION['uzver'] : 0;
$userStatus = (int)($_SESSION['status'] ?? 0);
$accessDeniedCode = $userId > 0 ? 403 : 401;
$hasSupportDeskAccess = $userId > 0
    && function_exists('hasRole')
    && defined('ROLE_WEBMASTER')
    && hasRole($userStatus, ROLE_WEBMASTER);

if (isset($_GET['action']) && !$hasSupportDeskAccess) {
    supportDeskDenyJson($accessDeniedCode);
}

if (!$hasSupportDeskAccess) {
    supportDeskRenderAccessPage($accessDeniedCode);
}

if (!isset($_GET['action']) && supportDeskIsMobileRequest()) {
    supportDeskRenderDesktopOnlyPage();
}

$dblink = DbConnect();
$supportDesk = new SupportDesk($dblink);
$supportRender = new SupportRender();
$bucketMap = ['queue', 'my', 'waiting', 'resolved', 'closed', 'spam'];

if (isset($_GET['action'])) {
    try {
        $action = (string)$_GET['action'];
        if ($action === 'support_get_ticket') {
            $ticketId = (int)($_GET['ticket_id'] ?? 0);
            $ticket = $supportDesk->getTicketForStaff($ticketId, $userId);
            supportDeskSendJson([
                'status' => 'ok',
                'ticket' => $ticket,
                'messages' => $ticket ? $supportDesk->getMessages((int)$ticket['id']) : [],
            ]);
        }

        if ($action === 'support_get_messages') {
            $ticketId = (int)($_GET['ticket_id'] ?? 0);
            $ticket = $supportDesk->getTicketForStaff($ticketId, $userId);
            if (!$ticket) {
                supportDeskSendJson(['status' => 'error', 'msg' => 'Звернення не знайдено']);
            }
            supportDeskSendJson([
                'status' => 'ok',
                'ticket' => $ticket,
                'messages' => $supportDesk->getMessages((int)$ticket['id'], (int)($_GET['last_id'] ?? 0)),
            ]);
        }

        if ($action === 'support_send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $imgPath = supportDeskSaveUploadedImage($ticketId);
            $result = $supportDesk->sendStaffMessage(
                $ticketId,
                $userId,
                trim((string)($_POST['message'] ?? '')),
                $imgPath !== '' ? $imgPath : null,
                (int)($_POST['template_id'] ?? 0) ?: null
            );
            supportDeskSendJson([
                'status' => 'ok',
                'ticket' => $result['ticket'],
                'message' => $result['message'],
            ]);
        }

        if ($action === 'support_request_resolution' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $supportDesk->requestResolutionConfirmation((int)($_POST['ticket_id'] ?? 0), $userId);
            supportDeskSendJson([
                'status' => 'ok',
                'ticket' => $result['ticket'],
                'message' => $result['message'],
            ]);
        }

        if ($action === 'support_claim_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $ticket = $supportDesk->claimTicket((int)($_POST['ticket_id'] ?? 0), $userId);
            supportDeskSendJson(['status' => 'ok', 'ticket' => $ticket]);
        }

        if ($action === 'support_transfer_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $ticket = $supportDesk->transferTicket(
                (int)($_POST['ticket_id'] ?? 0),
                $userId,
                (int)($_POST['to_user_id'] ?? 0),
                trim((string)($_POST['note'] ?? ''))
            );
            supportDeskSendJson(['status' => 'ok', 'ticket' => $ticket]);
        }

        if ($action === 'support_change_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $ticket = $supportDesk->changeStatus((int)($_POST['ticket_id'] ?? 0), $userId, (string)($_POST['status'] ?? 'new'));
            supportDeskSendJson(['status' => 'ok', 'ticket' => $ticket]);
        }

        if ($action === 'support_list_queue') {
            supportDeskSendJson([
                'status' => 'ok',
                'tickets' => $supportDesk->listTicketsByBucket($userId, 'queue'),
                'counts' => $supportDesk->getBucketCounts($userId),
            ]);
        }

        if ($action === 'support_list_my_tickets') {
            supportDeskSendJson([
                'status' => 'ok',
                'tickets' => $supportDesk->listTicketsByBucket($userId, 'my'),
                'counts' => $supportDesk->getBucketCounts($userId),
            ]);
        }

        if ($action === 'support_list_bucket') {
            $bucket = (string)($_GET['bucket'] ?? 'queue');
            if (!in_array($bucket, $bucketMap, true)) {
                $bucket = 'queue';
            }
            supportDeskSendJson([
                'status' => 'ok',
                'bucket' => $bucket,
                'tickets' => $supportDesk->listTicketsByBucket($userId, $bucket),
                'counts' => $supportDesk->getBucketCounts($userId),
            ]);
        }

        if ($action === 'support_templates_list') {
            supportDeskSendJson([
                'status' => 'ok',
                'templates' => $supportDesk->listTemplates(trim((string)($_GET['q'] ?? ''))),
            ]);
        }

        if ($action === 'support_template_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $template = $supportDesk->saveTemplate(
                $userId,
                (string)($_POST['title'] ?? ''),
                (string)($_POST['body'] ?? ''),
                (int)($_POST['template_id'] ?? 0) ?: null
            );
            supportDeskSendJson(['status' => 'ok', 'template' => $template]);
        }

        if ($action === 'support_template_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $ok = $supportDesk->deleteTemplate($userId, (int)($_POST['template_id'] ?? 0));
            supportDeskSendJson(['status' => $ok ? 'ok' : 'error']);
        }
    } catch (Throwable $e) {
        supportDeskSendJson(['status' => 'error', 'msg' => $e->getMessage()]);
    }
}

$bucketTickets = [];
foreach ($bucketMap as $bucket) {
    $bucketTickets[$bucket] = $supportDesk->listTicketsByBucket($userId, $bucket);
}
$counts = $supportDesk->getBucketCounts($userId);
$selectedTicketId = (int)($_GET['ticket_id'] ?? 0);
$selectedTicket = $selectedTicketId > 0 ? $supportDesk->getTicketForStaff($selectedTicketId, $userId) : null;
if (!$selectedTicket) {
    foreach ($bucketMap as $bucket) {
        if (!empty($bucketTickets[$bucket])) {
            $selectedTicket = $bucketTickets[$bucket][0];
            break;
        }
    }
}
$messages = $selectedTicket ? $supportDesk->getMessages((int)$selectedTicket['id']) : [];
$webmasters = $supportDesk->getWebmasters();
$templates = $supportDesk->listTemplates();
View_Add(Page_Up('Support Desk'));
View_Add(Menu_Up());
View_Add('<div class="outmsg">');
View_Add(NormalizePublicMarkup($supportRender->renderStaffPage(
    $bucketTickets,
    $counts,
    $selectedTicket,
    $messages,
    $webmasters,
    $templates,
    [
        'current_user_id' => $userId,
    ]
)));
View_Add('</div>');
View_Out();
