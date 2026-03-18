<?php

require_once __DIR__ . "/lenta.php";

class LentaGrave
{
    private mysqli $dblink;
    private Lenta $lenta;

    public function __construct(mysqli $dblink)
    {
        $this->dblink = $dblink;
        $this->lenta = new Lenta($dblink);
    }

    public function handlePublishRequest(int $idxabon): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (string)($_POST['action'] ?? '') !== 'lenta_test_publish') {
            return;
        }

        $targetId = (int)($_POST['idxabon'] ?? $idxabon);
        $message = trim((string)($_POST['message'] ?? ''));

        if ($targetId <= 0) {
            $this->setFlash('error', 'Не вдалося визначити картку для публікації.');
            $this->redirectToCard($idxabon);
        }

        $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if ($message === '') {
            $this->setFlash('error', 'Введіть текст публікації.');
            $this->redirectToCard($targetId);
        }

        if ($messageLength > 2000) {
            $this->setFlash('error', 'Максимальна довжина публікації становить 2000 символів.');
            $this->redirectToCard($targetId);
        }

        $saved = $this->lenta->addMessage($message, $targetId);
        if ($saved) {
            $this->setFlash('success', 'Публікацію додано.');
        } elseif (!$this->isAuthorized()) {
            $this->setFlash('error', 'Щоб опублікувати повідомлення, необхідно увійти в систему.');
        } else {
            $this->setFlash('error', 'Не вдалося зберегти публікацію.');
        }

        $this->redirectToCard($targetId);
    }

    public function countMessages(int $idxabon): int
    {
        if ($idxabon <= 0) {
            return 0;
        }

        $res = mysqli_query($this->dblink, "SELECT COUNT(*) AS cnt FROM lenta WHERE idxabon = $idxabon");
        if (!$res) {
            return 0;
        }

        $row = mysqli_fetch_assoc($res);
        return (int)($row['cnt'] ?? 0);
    }

    public function renderFlash(): string
    {
        $flash = $_SESSION['lenta_test_flash'] ?? null;
        unset($_SESSION['lenta_test_flash']);

        if (!is_array($flash) || empty($flash['text'])) {
            return '';
        }

        $type = ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success';
        return '<div class="ltt-flash ltt-flash--' . $type . '" data-lenta-test-flash>' . $this->esc((string)$flash['text']) . '</div>';
    }

    public function renderComposer(int $idxabon): string
    {
        if (!$this->isAuthorized()) {
            return '
                <div class="ltt-auth-card">
                    <div class="ltt-auth-copy">
                        <h3>Публікації доступні після входу</h3>
                        <p>Увійдіть у свій обліковий запис, щоб додавати нові повідомлення до стрічки цієї картки.</p>
                    </div>
                    <a href="/auth.php" class="ltt-auth-link">Увійти</a>
                </div>
            ';
        }

        $userId = (int)($_SESSION['uzver'] ?? 0);
        $user = $this->resolveUser($userId);
        $avatar = $user['avatar'];
        $fullName = trim($user['name']);
        $shortName = trim((string)($user['first_name'] ?? ''));
        if ($shortName === '') {
            $shortName = $fullName !== '' ? $fullName : 'Користувачу';
        }

        return '
            <form method="post" class="ltt-composer" data-lenta-test-form>
                <input type="hidden" name="action" value="lenta_test_publish">
                <input type="hidden" name="idxabon" value="' . $idxabon . '">
                <div class="ltt-composer-head">
                    <img src="' . $this->esc($avatar) . '" alt="' . $this->esc($fullName) . '" class="ltt-composer-avatar">
                    <div class="ltt-composer-user">
                        <b>' . $this->esc($fullName !== '' ? $fullName : 'Користувач системи') . '</b>
                        <span>Нова публікація для картки поховання</span>
                    </div>
                </div>
                <label class="ltt-composer-field">
                    <span class="ltt-composer-label">Що нового, ' . $this->esc($shortName) . '?</span>
                    <textarea
                        name="message"
                        rows="4"
                        maxlength="2000"
                        placeholder="Напишіть публікацію, спогад або уточнення до цієї картки..."
                        required
                    ></textarea>
                </label>
                <div class="ltt-composer-foot">
                    <span class="ltt-composer-counter" data-lenta-test-counter>0 / 2000</span>
                    <button type="submit" class="ltt-submit" data-lenta-test-submit>Опублікувати</button>
                </div>
            </form>
        ';
    }

    public function renderMessages(int $idxabon, int $limit = 10): string
    {
        $messages = $this->lenta->getMessages($idxabon, $limit);
        if (empty($messages)) {
            return '<div class="ltt-empty">Публікацій ще немає. Додайте перше повідомлення для цієї картки.</div>';
        }

        $currentUserId = (int)($_SESSION['uzver'] ?? 0);
        $out = '<div class="ltt-feed">';
        foreach ($messages as $message) {
            $authorId = (int)($message['idxuser'] ?? 0);
            $user = $this->resolveUser($authorId);
            $authorName = trim((string)($message['username'] ?? ''));
            if ($authorName === '') {
                $authorName = $user['name'] !== '' ? $user['name'] : 'Користувач системи';
            }

            $text = trim((string)($message['atext'] ?? ''));
            $time = $this->formatTimeAgo((string)($message['dttmadd'] ?? ''));
            $authorProfileUrl = $authorId > 0 ? '/public-profile.php?idx=' . $authorId : '#';
            $authorAttrs = 'data-author-id="' . $authorId . '"'
                . ' data-author-name="' . $this->esc($authorName) . '"'
                . ' data-author-avatar="' . $this->esc($user['avatar']) . '"'
                . ' data-author-profile="' . $this->esc($authorProfileUrl) . '"'
                . ' data-author-self="' . ($authorId > 0 && $authorId === $currentUserId ? '1' : '0') . '"';

            $out .= '<article class="ltt-post">';
            $out .= '<div class="ltt-post-head">';
            $out .= '<button type="button" class="ltt-post-author grvdet-author-btn" ' . $authorAttrs . '>';
            $out .= '<img src="' . $this->esc($user['avatar']) . '" alt="' . $this->esc($authorName) . '" class="ltt-post-avatar">';
            $out .= '<div class="ltt-post-meta">';
            $out .= '<span class="ltt-post-author-name">' . $this->esc($authorName) . '</span>';
            $out .= '<span>' . $this->esc($time) . '</span>';
            $out .= '</div>';
            $out .= '</button>';
            $out .= '</div>';
            $out .= '<div class="ltt-post-body">' . nl2br($this->esc($text)) . '</div>';
            $out .= '<div class="ltt-post-actions">';
            $out .= '<button type="button" class="ltt-post-action">';
            $out .= '<span class="ltt-post-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-heart"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" /></svg></span>';
            $out .= '<span>Реакція</span>';
            $out .= '</button>';
            $out .= '<button type="button" class="ltt-post-action">';
            $out .= '<span class="ltt-post-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-circle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 20l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c3.255 2.777 3.695 7.266 1.029 10.501c-2.666 3.235 -7.615 4.215 -11.574 2.293l-4.7 1" /></svg></span>';
            $out .= '<span>Коментар</span>';
            $out .= '</button>';
            $out .= '<button type="button" class="ltt-post-action">';
            $out .= '<span class="ltt-post-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-share-3"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 4v4c-6.575 1.028 -9.02 6.788 -10 12c-.037 .206 5.384 -5.962 10 -6v4l8 -7l-8 -7" /></svg></span>';
            $out .= '<span>Поділитися</span>';
            $out .= '</button>';
            $out .= '</div>';
            $out .= '</article>';
        }
        $out .= '</div>';

        return $out;
    }

    private function redirectToCard(int $idxabon): void
    {
        header('Location: /cardout.php?idx=' . $idxabon . '#publications');
        exit;
    }

    private function setFlash(string $type, string $text): void
    {
        $_SESSION['lenta_test_flash'] = [
            'type' => $type,
            'text' => $text,
        ];
    }

    private function isAuthorized(): bool
    {
        return isset($_SESSION['logged'], $_SESSION['uzver'])
            && (int)$_SESSION['logged'] === 1
            && (int)$_SESSION['uzver'] > 0;
    }

    private function resolveUser(int $userId): array
    {
        $fallback = [
            'avatar' => '/avatars/ava.png',
            'name' => '',
            'first_name' => '',
        ];

        if ($userId <= 0) {
            return $fallback;
        }

        $res = mysqli_query(
            $this->dblink,
            "SELECT fname, lname, avatar FROM users WHERE idx = $userId LIMIT 1"
        );
        if (!$res) {
            return $fallback;
        }

        $row = mysqli_fetch_assoc($res);
        if (!$row) {
            return $fallback;
        }

        $avatar = trim((string)($row['avatar'] ?? ''));
        if ($avatar !== '') {
            $avatar = ltrim($avatar, '/');
            $avatar = str_starts_with($avatar, 'avatars/')
                ? '/' . $avatar
                : '/avatars/' . $avatar;
        } else {
            $avatar = $fallback['avatar'];
        }

        return [
            'avatar' => $avatar,
            'name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
            'first_name' => trim((string)($row['fname'] ?? '')),
        ];
    }

    private function formatTimeAgo(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return 'Невідомий час';
        }

        $diff = time() - $timestamp;
        if ($diff < 10) {
            return 'щойно';
        }
        if ($diff < 60) {
            return $diff . ' с тому';
        }
        if ($diff < 3600) {
            $minutes = (int)floor($diff / 60);
            return $minutes . ' хв тому';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' год тому';
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ' дн тому';
        }

        return date('d.m.Y H:i', $timestamp);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
