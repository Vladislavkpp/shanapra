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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'lenta_reaction_toggle') {
            $this->handleReactionRequest($idxabon);
            return;
        }

        if ($action === 'lenta_comment_add') {
            $this->handleCommentRequest($idxabon);
            return;
        }

        if ($action !== 'lenta_test_publish') {
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
                        rows="1"
                        maxlength="2000"
                        placeholder="Напишіть публікацію, спогад або уточнення до цієї картки..."
                        aria-label="Текст публікації"
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
        $messageIds = [];
        foreach ($messages as $message) {
            $messageIds[] = (int)($message['idx'] ?? 0);
        }

        $reactionStateMap = $this->loadReactionsState($messageIds, $currentUserId);
        $commentCountMap = $this->loadCommentCounts($messageIds);

        $out = '<div class="ltt-feed" data-ltt-feed-shell>';
        foreach ($messages as $message) {
            $postId = (int)($message['idx'] ?? 0);
            $reactionState = $reactionStateMap[$postId] ?? $this->createEmptyReactionState();
            $commentCount = (int)($commentCountMap[$postId] ?? 0);

            $out .= $this->renderPostCard($message, $idxabon, $reactionState, $commentCount, [
                'comment_url' => $this->buildBranchHref($idxabon, $postId),
            ]);
        }
        $out .= '</div>';

        return $out;
    }

    public function getBranchViewData(int $idxabon, int $postId, ?int $commentId = null, ?int $replyToId = null): array
    {
        $post = $this->loadPost($idxabon, $postId);
        $backUrl = '/cardout.php?idx=' . $idxabon . '#publications';

        if ($post === null) {
            return [
                'title' => 'Гілка коментарів',
                'meta' => 'Публікацію не знайдено',
                'link_html' => '<a href="' . $this->esc($backUrl) . '" class="grvdet-inline-link" data-branch-open>До всіх публікацій</a>',
                'content_html' => '<div class="ltt-empty">Публікацію для цієї гілки не знайдено або вона вже недоступна.</div>',
            ];
        }

        $currentUserId = (int)($_SESSION['uzver'] ?? 0);
        $reactionStateMap = $this->loadReactionsState([(int)$post['idx']], $currentUserId);
        $reactionState = $reactionStateMap[(int)$post['idx']] ?? $this->createEmptyReactionState();
        $comments = $this->loadComments($postId);
        $commentsById = [];
        $childrenMap = [0 => []];

        foreach ($comments as $comment) {
            $commentKey = (int)($comment['idx'] ?? 0);
            if ($commentKey <= 0) {
                continue;
            }

            $commentsById[$commentKey] = $comment;
            $parentKey = (int)($comment['parent_id'] ?? 0);
            if (!isset($childrenMap[$parentKey])) {
                $childrenMap[$parentKey] = [];
            }
            if (!isset($childrenMap[$commentKey])) {
                $childrenMap[$commentKey] = [];
            }
            $childrenMap[$parentKey][] = $comment;
        }

        $replyCountMap = $this->buildCommentReplyCountMap($childrenMap, 0);
        $selectedComment = null;
        if ($commentId !== null && $commentId > 0) {
            $selectedComment = $commentsById[$commentId] ?? null;
        }

        if ($commentId !== null && $commentId > 0 && $selectedComment === null) {
            return [
                'title' => 'Гілка коментарів',
                'meta' => 'Коментар не знайдено',
                'link_html' => '<a href="' . $this->esc($this->buildBranchHref($idxabon, $postId)) . '" class="grvdet-inline-link" data-branch-open>До гілки публікації</a>',
                'content_html' => '<div class="ltt-empty">Коментар для цієї гілки не знайдено або його було видалено.</div>',
            ];
        }

        $replyTarget = null;
        if ($replyToId !== null && $replyToId > 0) {
            $replyTarget = $commentsById[$replyToId] ?? null;
        }

        $branchChildren = $selectedComment !== null
            ? ($childrenMap[(int)$selectedComment['idx']] ?? [])
            : ($childrenMap[0] ?? []);

        $contentHtml = '<div class="ltt-branch" data-ltt-feed-shell><div class="ltt-branch-stack">';

        if ($selectedComment !== null) {
            $contentHtml .= '<div class="ltt-branch-hero-wrap">';
            $contentHtml .= $this->renderCommentCard($selectedComment, $idxabon, $postId, $currentUserId, $replyCountMap, [
                'card_class' => 'ltt-comment--hero',
                'branch_url' => $this->buildBranchHref($idxabon, $postId, (int)$selectedComment['idx']),
                'reply_url' => $this->buildBranchUrl($idxabon, $postId, (int)$selectedComment['idx'], (int)$selectedComment['idx']) . '#ltt-comment-form',
                'show_reply_button' => $replyTarget === null,
                'show_branch_button' => false,
                'badge' => 'Обраний коментар',
            ]);
            $contentHtml .= '</div>';
        } else {
            $contentHtml .= '<div class="ltt-branch-hero-wrap">';
            $contentHtml .= $this->renderPostCard($post, $idxabon, $reactionState, count($comments), [
                'card_class' => 'ltt-post--branch-hero',
                'comment_url' => $this->buildBranchHref($idxabon, $postId),
                'comment_label' => 'Коментарі',
                'badge' => 'Обрана публікація',
                'show_comment_action' => false,
            ]);
            $contentHtml .= '</div>';
        }

        if ($selectedComment === null || $replyTarget !== null) {
            $contentHtml .= $this->renderCommentComposer(
                $idxabon,
                $postId,
                $selectedComment !== null ? (int)$selectedComment['idx'] : null,
                $replyTarget,
                $selectedComment !== null
                    ? $this->buildBranchHref($idxabon, $postId, (int)$selectedComment['idx'])
                    : $this->buildBranchHref($idxabon, $postId)
            );
        }

        if (empty($branchChildren)) {
            $emptyText = $selectedComment !== null
                ? 'У цій гілці ще немає відповідей. Станьте першим, хто продовжить розмову.'
                : 'Коментарів до цієї публікації ще немає. Ви можете написати перший.';
            $contentHtml .= '<div class="ltt-empty ltt-empty--branch">' . $this->esc($emptyText) . '</div>';
        } else {
            $contentHtml .= '<div class="ltt-branch-thread">';
            $contentHtml .= $this->renderCommentChildren(
                $branchChildren,
                $childrenMap,
                $replyCountMap,
                $idxabon,
                $postId,
                $currentUserId,
                $selectedComment !== null ? 1 : 0
            );
            $contentHtml .= '</div>';
        }

        $contentHtml .= '</div></div>';

        $metaCount = $selectedComment !== null
            ? (int)($replyCountMap[(int)$selectedComment['idx']] ?? 0)
            : count($comments);
        $metaLabel = $selectedComment !== null ? 'У гілці: ' : 'Коментарів: ';
        $linkUrl = $selectedComment !== null ? $this->buildBranchHref($idxabon, $postId) : $backUrl;
        $linkText = $selectedComment !== null ? 'До гілки публікації' : 'До всіх публікацій';

        return [
            'title' => 'Гілка коментарів',
            'meta' => $metaLabel . $metaCount,
            'link_html' => '<a href="' . $this->esc($linkUrl) . '" class="grvdet-inline-link" data-branch-open>' . $this->esc($linkText) . '</a>',
            'content_html' => $contentHtml,
        ];
    }

    private function handleReactionRequest(int $idxabon): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isAuthorized()) {
            $this->jsonResponse([
                'status' => 'unauthorized',
                'message' => 'Щоб поставити реакцію, необхідно увійти в систему.',
                'login_url' => '/auth.php',
            ]);
        }

        $userId = (int)($_SESSION['uzver'] ?? 0);
        $targetId = (int)($_POST['idxabon'] ?? $idxabon);
        $lentaId = (int)($_POST['lenta_id'] ?? 0);
        $reactionType = $this->normalizeReactionType((string)($_POST['reaction_type'] ?? ''));

        if ($targetId <= 0 || $lentaId <= 0 || $reactionType === null) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Некоректні дані реакції.',
            ]);
        }

        $postRes = mysqli_query(
            $this->dblink,
            "SELECT idx FROM lenta WHERE idx = $lentaId AND idxabon = $targetId LIMIT 1"
        );
        if (!$postRes || mysqli_num_rows($postRes) === 0) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Публікацію для реакції не знайдено.',
            ]);
        }

        $currentReaction = null;
        $existingRes = mysqli_query(
            $this->dblink,
            "SELECT reaction_type FROM lenta_reactions WHERE lenta_id = $lentaId AND user_id = $userId LIMIT 1"
        );
        if ($existingRes && ($existingRow = mysqli_fetch_assoc($existingRes))) {
            $currentReaction = $this->normalizeReactionType((string)($existingRow['reaction_type'] ?? ''));
        }

        if ($currentReaction === $reactionType) {
            $ok = mysqli_query(
                $this->dblink,
                "DELETE FROM lenta_reactions WHERE lenta_id = $lentaId AND user_id = $userId LIMIT 1"
            );
            if (!$ok) {
                $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Не вдалося прибрати реакцію.',
                ]);
            }
            $status = 'removed';
        } elseif ($currentReaction !== null) {
            $safeReactionType = mysqli_real_escape_string($this->dblink, $reactionType);
            $ok = mysqli_query(
                $this->dblink,
                "UPDATE lenta_reactions SET reaction_type = '$safeReactionType', created_at = NOW() WHERE lenta_id = $lentaId AND user_id = $userId LIMIT 1"
            );
            if (!$ok) {
                $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Не вдалося змінити реакцію.',
                ]);
            }
            $status = 'updated';
        } else {
            $safeReactionType = mysqli_real_escape_string($this->dblink, $reactionType);
            $ok = mysqli_query(
                $this->dblink,
                "INSERT INTO lenta_reactions (lenta_id, user_id, reaction_type, created_at) VALUES ($lentaId, $userId, '$safeReactionType', NOW())"
            );
            if (!$ok) {
                $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Не вдалося зберегти реакцію.',
                ]);
            }
            $status = 'added';
        }

        $stateMap = $this->loadReactionsState([$lentaId], $userId);
        $state = $stateMap[$lentaId] ?? $this->createEmptyReactionState();

        $this->jsonResponse([
            'status' => $status,
            'message' => 'Реакцію оновлено.',
            'current_reaction' => $state['userReaction'],
            'summary_html' => $this->renderReactionSummary($state),
            'widget_html' => $this->renderReactionWidget($lentaId, $targetId, $state),
        ]);
    }

    private function handleCommentRequest(int $idxabon): void
    {
        $targetId = (int)($_POST['idxabon'] ?? $idxabon);
        $postId = (int)($_POST['post_id'] ?? 0);
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $commentText = trim((string)($_POST['comment_text'] ?? ''));
        $commentId = (int)($_POST['comment_id'] ?? 0);

        if ($targetId <= 0 || $postId <= 0) {
            $this->setFlash('error', 'Не вдалося визначити гілку для коментаря.');
            $this->redirectToCard($idxabon);
        }

        if (!$this->isAuthorized()) {
            $this->setFlash('error', 'Щоб коментувати, необхідно увійти в систему.');
            $this->redirectToUrl($this->buildBranchHref($targetId, $postId, $commentId > 0 ? $commentId : null));
        }

        $post = $this->loadPost($targetId, $postId);
        if ($post === null) {
            $this->setFlash('error', 'Публікацію для коментаря не знайдено.');
            $this->redirectToCard($targetId);
        }

        $messageLength = function_exists('mb_strlen') ? mb_strlen($commentText) : strlen($commentText);
        if ($commentText === '') {
            $this->setFlash('error', 'Введіть текст коментаря.');
            $this->redirectToUrl($this->buildBranchHref($targetId, $postId, $commentId > 0 ? $commentId : null));
        }

        if ($messageLength > 2000) {
            $this->setFlash('error', 'Максимальна довжина коментаря становить 2000 символів.');
            $this->redirectToUrl($this->buildBranchHref($targetId, $postId, $commentId > 0 ? $commentId : null));
        }

        if ($parentId > 0) {
            $commentRes = mysqli_query(
                $this->dblink,
                "SELECT idx FROM lenta_comments WHERE idx = $parentId AND lenta_id = $postId AND is_deleted = 0 LIMIT 1"
            );
            if (!$commentRes || mysqli_num_rows($commentRes) === 0) {
                $this->setFlash('error', 'Батьківський коментар для відповіді не знайдено.');
                $this->redirectToUrl($this->buildBranchHref($targetId, $postId));
            }
        }

        $userId = (int)($_SESSION['uzver'] ?? 0);
        $safeText = mysqli_real_escape_string($this->dblink, $commentText);
        $parentSql = $parentId > 0 ? (string)$parentId : 'NULL';
        $inserted = mysqli_query(
            $this->dblink,
            "
                INSERT INTO lenta_comments (lenta_id, user_id, parent_id, comment_text, created_at)
                VALUES ($postId, $userId, $parentSql, '$safeText', NOW())
            "
        );

        if (!$inserted) {
            $this->setFlash('error', 'Не вдалося зберегти коментар.');
            $this->redirectToUrl($this->buildBranchHref($targetId, $postId, $commentId > 0 ? $commentId : null));
        }

        $newCommentId = (int)mysqli_insert_id($this->dblink);
        $this->setFlash('success', $parentId > 0 ? 'Відповідь додано.' : 'Коментар додано.');

        if ($parentId > 0) {
            $this->redirectToUrl($this->buildBranchUrl($targetId, $postId, $parentId) . '#ltt-comment-' . $newCommentId);
        }

        $this->redirectToUrl($this->buildBranchUrl($targetId, $postId) . '#ltt-comment-' . $newCommentId);
    }

    private function loadReactionsState(array $messageIds, int $currentUserId): array
    {
        $state = [];
        $cleanIds = [];

        foreach ($messageIds as $messageId) {
            $messageId = (int)$messageId;
            if ($messageId <= 0) {
                continue;
            }

            $cleanIds[$messageId] = $messageId;
            $state[$messageId] = $this->createEmptyReactionState();
        }

        if (empty($cleanIds)) {
            return $state;
        }

        $idList = implode(',', $cleanIds);
        $catalog = $this->getReactionCatalog();

        $res = mysqli_query(
            $this->dblink,
            "
                SELECT lenta_id, reaction_type, COUNT(*) AS cnt
                FROM lenta_reactions
                WHERE lenta_id IN ($idList)
                GROUP BY lenta_id, reaction_type
            "
        );
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $messageId = (int)($row['lenta_id'] ?? 0);
                $reactionType = $this->normalizeReactionType((string)($row['reaction_type'] ?? ''));
                if ($messageId <= 0 || $reactionType === null || !isset($catalog[$reactionType])) {
                    continue;
                }

                $count = (int)($row['cnt'] ?? 0);
                if ($count <= 0) {
                    continue;
                }

                $state[$messageId]['counts'][$reactionType] = $count;
                $state[$messageId]['total'] += $count;
            }
        }

        if ($currentUserId > 0) {
            $userRes = mysqli_query(
                $this->dblink,
                "
                    SELECT lenta_id, reaction_type
                    FROM lenta_reactions
                    WHERE lenta_id IN ($idList)
                      AND user_id = $currentUserId
                "
            );
            if ($userRes) {
                while ($row = mysqli_fetch_assoc($userRes)) {
                    $messageId = (int)($row['lenta_id'] ?? 0);
                    $reactionType = $this->normalizeReactionType((string)($row['reaction_type'] ?? ''));
                    if ($messageId <= 0 || $reactionType === null) {
                        continue;
                    }

                    $state[$messageId]['userReaction'] = $reactionType;
                }
            }
        }

        foreach ($state as $messageId => $item) {
            $state[$messageId]['topReactions'] = $this->resolveTopReactions($item['counts']);
        }

        return $state;
    }

    private function loadCommentCounts(array $messageIds): array
    {
        $counts = [];
        $cleanIds = [];

        foreach ($messageIds as $messageId) {
            $messageId = (int)$messageId;
            if ($messageId <= 0) {
                continue;
            }

            $cleanIds[$messageId] = $messageId;
            $counts[$messageId] = 0;
        }

        if (empty($cleanIds)) {
            return $counts;
        }

        $idList = implode(',', $cleanIds);
        $res = mysqli_query(
            $this->dblink,
            "
                SELECT lenta_id, COUNT(*) AS cnt
                FROM lenta_comments
                WHERE lenta_id IN ($idList)
                  AND is_deleted = 0
                GROUP BY lenta_id
            "
        );

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $messageId = (int)($row['lenta_id'] ?? 0);
                if ($messageId <= 0) {
                    continue;
                }

                $counts[$messageId] = (int)($row['cnt'] ?? 0);
            }
        }

        return $counts;
    }

    private function renderReactionSummary(array $state): string
    {
        $total = (int)($state['total'] ?? 0);
        if ($total <= 0) {
            return '<div class="ltt-post-reactions is-empty" data-reaction-summary></div>';
        }

        $catalog = $this->getReactionCatalog();
        $topReactions = $state['topReactions'] ?? [];
        $iconsHtml = '';

        foreach ($topReactions as $reactionType) {
            if (!isset($catalog[$reactionType])) {
                continue;
            }

            $meta = $catalog[$reactionType];
            $iconsHtml .= '<span class="ltt-post-reactions__icon ltt-post-reactions__icon--' . $reactionType . '">';
            $iconsHtml .= '<img src="' . $this->esc($meta['icon']) . '" alt="' . $this->esc($meta['label']) . '" draggable="false">';
            $iconsHtml .= '</span>';
        }

        $totalLabel = $total === 1 ? 'реакція' : 'реакцій';

        return '<div class="ltt-post-reactions" data-reaction-summary>'
            . '<div class="ltt-post-reactions__icons">' . $iconsHtml . '</div>'
            . '<div class="ltt-post-reactions__text">'
            . '<span class="ltt-post-reactions__count">' . $total . '</span>'
            . '<span class="ltt-post-reactions__label">' . $this->esc($totalLabel) . '</span>'
            . '</div>'
            . '</div>';
    }

    private function renderReactionWidget(int $lentaId, int $idxabon, array $state): string
    {
        $currentReaction = $state['userReaction'] ?? null;
        $defaultReaction = $this->getDefaultReactionType();

        return '<div class="ltt-reaction-widget' . ($currentReaction ? ' is-active' : '') . '"'
            . ' data-ltt-reaction-widget'
            . ' data-lenta-id="' . $lentaId . '"'
            . ' data-idxabon="' . $idxabon . '"'
            . ' data-current-reaction="' . $this->esc((string)$currentReaction) . '"'
            . ' data-default-reaction="' . $this->esc($defaultReaction) . '">'
            . $this->renderReactionTrigger($currentReaction)
            . $this->renderReactionPicker($currentReaction)
            . '</div>';
    }

    private function renderReactionTrigger(?string $currentReaction): string
    {
        $catalog = $this->getReactionCatalog();
        $reactionMeta = ($currentReaction !== null && isset($catalog[$currentReaction]))
            ? $catalog[$currentReaction]
            : null;

        $buttonClass = 'ltt-post-action ltt-post-action--reaction';
        $label = 'Реакція';
        $iconMarkup = '<img src="/assets/reactions/candle_reaction.png" alt="" draggable="false">';

        if ($reactionMeta !== null) {
            $buttonClass .= ' is-active is-type-' . $currentReaction;
            $label = (string)$reactionMeta['button_label'];
            $iconMarkup = '<img src="' . $this->esc((string)$reactionMeta['icon']) . '" alt="" draggable="false">';
        }

        return '<button type="button" class="' . $buttonClass . '" data-reaction-trigger aria-haspopup="true" aria-expanded="false">'
            . '<span class="ltt-post-action__icon ltt-post-action__icon--reaction" aria-hidden="true">' . $iconMarkup . '</span>'
            . '<span>' . $this->esc($label) . '</span>'
            . '</button>';
    }

    private function renderReactionPicker(?string $currentReaction): string
    {
        $catalog = $this->getReactionCatalog();
        $out = '<div class="ltt-reaction-picker" data-reaction-picker role="menu" aria-label="Оберіть реакцію">';

        foreach ($catalog as $reactionType => $meta) {
            $isActive = $currentReaction === $reactionType;
            $out .= '<button type="button" class="ltt-reaction-option ltt-reaction-option--' . $reactionType . ($isActive ? ' is-active' : '') . '"'
                . ' data-reaction-option'
                . ' data-reaction-type="' . $reactionType . '"'
                . ' aria-label="' . $this->esc((string)$meta['label']) . '">';
            $out .= '<img src="' . $this->esc((string)$meta['icon']) . '" alt="" class="ltt-reaction-option__icon" draggable="false">';
            $out .= '<span class="ltt-reaction-option__tooltip">' . $this->esc((string)$meta['label']) . '</span>';
            $out .= '</button>';
        }

        $out .= '</div>';

        return $out;
    }

    private function resolveTopReactions(array $counts): array
    {
        if (empty($counts)) {
            return [];
        }

        $catalog = $this->getReactionCatalog();
        uasort($counts, function (int $leftCount, int $rightCount) use ($counts, $catalog): int {
            if ($leftCount === $rightCount) {
                return 0;
            }

            return $rightCount <=> $leftCount;
        });

        $orderedTypes = array_keys($counts);
        usort($orderedTypes, function (string $leftType, string $rightType) use ($counts, $catalog): int {
            $leftCount = (int)($counts[$leftType] ?? 0);
            $rightCount = (int)($counts[$rightType] ?? 0);

            if ($leftCount !== $rightCount) {
                return $rightCount <=> $leftCount;
            }

            $leftOrder = (int)($catalog[$leftType]['order'] ?? 999);
            $rightOrder = (int)($catalog[$rightType]['order'] ?? 999);
            return $leftOrder <=> $rightOrder;
        });

        return array_slice($orderedTypes, 0, 3);
    }

    private function createEmptyReactionState(): array
    {
        return [
            'counts' => [],
            'total' => 0,
            'userReaction' => null,
            'topReactions' => [],
        ];
    }

    private function getReactionCatalog(): array
    {
        return [
            'memory' => [
                'label' => "Пам'ять",
                'button_label' => "Пам'ять",
                'icon' => '/assets/reactions/candle_reaction.png',
                'order' => 10,
            ],
            'peace' => [
                'label' => 'Спочивай зі світом',
                'button_label' => 'Спочивай зі світом',
                'icon' => '/assets/reactions/peace_reaction.png',
                'order' => 20,
            ],
            'pray' => [
                'label' => 'Молимося',
                'button_label' => 'Молимося',
                'icon' => '/assets/reactions/pray_reaction.png',
                'order' => 30,
            ],
            'rose' => [
                'label' => 'Шануємо',
                'button_label' => 'Шануємо',
                'icon' => '/assets/reactions/rose_reaction.png',
                'order' => 40,
            ],
        ];
    }

    private function normalizeReactionType(string $reactionType): ?string
    {
        $reactionType = strtolower(trim($reactionType));
        if ($reactionType === '') {
            return null;
        }

        if ($reactionType === 'like' || $reactionType === 'candle') {
            $reactionType = 'memory';
        }

        if ($reactionType === 'prayer') {
            $reactionType = 'pray';
        }

        if ($reactionType === 'flower') {
            $reactionType = 'rose';
        }

        $catalog = $this->getReactionCatalog();
        return isset($catalog[$reactionType]) ? $reactionType : null;
    }

    private function getDefaultReactionType(): string
    {
        $catalog = $this->getReactionCatalog();
        $keys = array_keys($catalog);
        return (string)($keys[0] ?? 'memory');
    }


    private function jsonResponse(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function redirectToCard(int $idxabon): void
    {
        $this->redirectToUrl('/cardout.php?idx=' . $idxabon . '#publications');
    }

    private function redirectToUrl(string $url): void
    {
        header('Location: ' . $url);
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

    private function normalizeAvatarPath(string $avatar): string
    {
        $avatar = trim($avatar);
        if ($avatar === '') {
            return '/avatars/ava.png';
        }

        $avatar = ltrim($avatar, '/');
        return str_starts_with($avatar, 'avatars/')
            ? '/' . $avatar
            : '/avatars/' . $avatar;
    }

    private function loadPost(int $idxabon, int $postId): ?array
    {
        if ($idxabon <= 0 || $postId <= 0) {
            return null;
        }

        $res = mysqli_query(
            $this->dblink,
            "
                SELECT
                    l.idx,
                    l.idxabon,
                    l.idxuser,
                    l.atext,
                    l.dttmadd,
                    CONCAT(IFNULL(u.fname, ''), ' ', IFNULL(u.lname, '')) AS username
                FROM lenta l
                LEFT JOIN users u ON l.idxuser = u.idx
                WHERE l.idx = $postId
                  AND l.idxabon = $idxabon
                LIMIT 1
            "
        );

        if (!$res) {
            return null;
        }

        $row = mysqli_fetch_assoc($res);
        return is_array($row) ? $row : null;
    }

    private function loadComments(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $comments = [];
        $res = mysqli_query(
            $this->dblink,
            "
                SELECT
                    c.idx,
                    c.lenta_id,
                    c.user_id,
                    c.parent_id,
                    c.comment_text,
                    c.created_at,
                    c.updated_at,
                    CONCAT(IFNULL(u.fname, ''), ' ', IFNULL(u.lname, '')) AS username,
                    IFNULL(u.avatar, '') AS user_avatar
                FROM lenta_comments c
                LEFT JOIN users u ON c.user_id = u.idx
                WHERE c.lenta_id = $postId
                  AND c.is_deleted = 0
                ORDER BY c.created_at ASC, c.idx ASC
            "
        );

        if (!$res) {
            return [];
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $authorId = (int)($row['user_id'] ?? 0);
            $authorName = trim((string)($row['username'] ?? ''));
            if ($authorName === '') {
                $authorName = 'Користувач системи';
            }

            $comments[] = [
                'idx' => (int)($row['idx'] ?? 0),
                'lenta_id' => (int)($row['lenta_id'] ?? 0),
                'user_id' => $authorId,
                'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
                'comment_text' => (string)($row['comment_text'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'author_name' => $authorName,
                'author_avatar' => $this->normalizeAvatarPath((string)($row['user_avatar'] ?? '')),
                'author_profile_url' => $authorId > 0 ? '/public-profile.php?idx=' . $authorId : '#',
            ];
        }

        return $comments;
    }

    private function buildCommentReplyCountMap(array $childrenMap, int $rootParent = 0): array
    {
        $counts = [];
        $walk = function (int $parentId) use (&$walk, &$counts, $childrenMap): int {
            $children = $childrenMap[$parentId] ?? [];
            $total = 0;

            foreach ($children as $child) {
                $childId = (int)($child['idx'] ?? 0);
                if ($childId <= 0) {
                    continue;
                }

                $nestedCount = $walk($childId);
                $counts[$childId] = $nestedCount;
                $total += 1 + $nestedCount;
            }

            return $total;
        };

        $walk($rootParent);
        return $counts;
    }

    private function buildTextExcerpt(string $text, int $limit = 180): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $limit) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, $limit - 1)) . '...';
    }

    private function renderPostCard(array $message, int $idxabon, array $reactionState, int $commentCount, array $options = []): string
    {
        $postId = (int)($message['idx'] ?? 0);
        $currentUserId = (int)($_SESSION['uzver'] ?? 0);
        $authorId = (int)($message['idxuser'] ?? 0);
        $user = $this->resolveUser($authorId);
        $authorName = trim((string)($message['username'] ?? ''));
        if ($authorName === '') {
            $authorName = $user['name'] !== '' ? $user['name'] : 'Користувач системи';
        }

        $time = $this->formatTimeAgo((string)($message['dttmadd'] ?? ''));
        $text = trim((string)($message['atext'] ?? ''));
        $badge = trim((string)($options['badge'] ?? ''));
        $cardClass = trim((string)($options['card_class'] ?? ''));
        $commentUrl = (string)($options['comment_url'] ?? $this->buildBranchHref($idxabon, $postId));
        $shareUrl = (string)($options['share_url'] ?? ($this->buildBranchUrl($idxabon, $postId) . '&shared=1#publications'));
        $commentLabel = (string)($options['comment_label'] ?? 'Коментарі');
        $showCommentAction = !array_key_exists('show_comment_action', $options) || (bool)$options['show_comment_action'];

        $authorProfileUrl = $authorId > 0 ? '/public-profile.php?idx=' . $authorId : '#';
        $authorAttrs = 'data-author-id="' . $authorId . '"'
            . ' data-author-name="' . $this->esc($authorName) . '"'
            . ' data-author-avatar="' . $this->esc($user['avatar']) . '"'
            . ' data-author-profile="' . $this->esc($authorProfileUrl) . '"'
            . ' data-author-self="' . ($authorId > 0 && $authorId === $currentUserId ? '1' : '0') . '"';

        $out = '<article class="ltt-post' . ($cardClass !== '' ? ' ' . $cardClass : '') . '" data-ltt-post data-lenta-id="' . $postId . '" data-branch-card id="ltt-post-' . $postId . '">';
        if ($badge !== '') {
            $out .= '<div class="ltt-card-badge">' . $this->esc($badge) . '</div>';
        }

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
        $out .= '<div class="ltt-post-footer">';
        $out .= $this->renderReactionSummary($reactionState);
        $out .= '<div class="ltt-post-actions">';
        $out .= $this->renderReactionWidget($postId, $idxabon, $reactionState);
        if ($showCommentAction) {
            $out .= '<a href="' . $this->esc($commentUrl) . '" class="ltt-post-action ltt-post-action--link" data-branch-open>';
            $out .= '<span class="ltt-post-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 20l1.3 -3.9c-2.324 -3.437 -1.426 -7.872 2.1 -10.374c3.526 -2.501 8.59 -2.296 11.845 .48c3.255 2.777 3.695 7.266 1.029 10.501c-2.666 3.235 -7.615 4.215 -11.574 2.293l-4.7 1" /></svg></span>';
            $out .= '<span>' . $this->esc($commentLabel) . '</span>';
            $out .= '<span class="ltt-post-action__count">' . $commentCount . '</span>';
            $out .= '</a>';
        }
        $out .= '<button type="button" class="ltt-post-action" data-share-trigger data-share-url="' . $this->esc($shareUrl) . '" data-share-title="Посилання на публікацію">';
        $out .= '<span class="ltt-post-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 4v4c-6.575 1.028 -9.02 6.788 -10 12c-.037 .206 5.384 -5.962 10 -6v4l8 -7l-8 -7" /></svg></span>';
        $out .= '<span>Поділитися</span>';
        $out .= '</button>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</article>';

        return $out;
    }

    private function renderCommentComposer(int $idxabon, int $postId, ?int $commentId, ?array $replyTarget, string $cancelUrl): string
    {
        if (!$this->isAuthorized()) {
            return '
                <div class="ltt-auth-card ltt-auth-card--branch">
                    <div class="ltt-auth-copy">
                        <h3>Коментарі доступні після входу</h3>
                        <p>Увійдіть у свій обліковий запис, щоб продовжити цю гілку або залишити відповідь.</p>
                    </div>
                    <a href="/auth.php" class="ltt-auth-link">Увійти</a>
                </div>
            ';
        }

        $userId = (int)($_SESSION['uzver'] ?? 0);
        $user = $this->resolveUser($userId);
        $fullName = trim($user['name']);
        $shortName = trim((string)($user['first_name'] ?? ''));
        if ($shortName === '') {
            $shortName = $fullName !== '' ? $fullName : 'Користувачу';
        }

        $parentId = $replyTarget !== null ? (int)($replyTarget['idx'] ?? 0) : 0;
        $replyName = $replyTarget !== null ? trim((string)($replyTarget['author_name'] ?? '')) : '';
        $label = $replyTarget !== null
            ? 'Відповідь для ' . ($replyName !== '' ? $replyName : 'автора коментаря')
            : 'Новий коментар';
        $placeholder = $replyTarget !== null
            ? 'Напишіть відповідь у цю гілку...'
            : 'Напишіть коментар, спогад або уточнення до цієї публікації...';
        $hint = $replyTarget !== null
            ? '<div class="ltt-comment-compose__hint">Відповідь буде додана прямо до цієї гілки.</div>'
            : '';
        $replyTargetHtml = '';
        $threadClass = 'ltt-comment-compose-thread';
        $formClass = 'ltt-comment-compose';

        if ($replyTarget !== null) {
            $threadClass .= ' ltt-comment-compose-thread--reply';
            $formClass .= ' ltt-comment-compose--attached';

            $replyTargetAuthor = trim((string)($replyTarget['author_name'] ?? ''));
            if ($replyTargetAuthor === '') {
                $replyTargetAuthor = 'Користувач системи';
            }

            $replyTargetAvatar = (string)($replyTarget['author_avatar'] ?? '/avatars/ava.png');
            $replyTargetTime = $this->formatTimeAgo((string)($replyTarget['created_at'] ?? ''));
            $replyTargetText = $this->buildTextExcerpt((string)($replyTarget['comment_text'] ?? ''), 180);

            $replyTargetHtml = '
                <div class="ltt-comment-compose-attach">
                    <div class="ltt-comment-compose-attach__target">
                        <div class="ltt-comment-compose-attach__label">Відповідь до коментаря</div>
                        <div class="ltt-comment-compose-attach__head">
                            <span class="ltt-comment-compose-attach__person">
                                <img src="' . $this->esc($replyTargetAvatar) . '" alt="' . $this->esc($replyTargetAuthor) . '" class="ltt-comment-compose-attach__avatar">
                                <span class="ltt-comment-compose-attach__meta">
                                    <strong>' . $this->esc($replyTargetAuthor) . '</strong>
                                    <span>' . $this->esc($replyTargetTime) . '</span>
                                </span>
                            </span>
                            <a href="' . $this->esc($cancelUrl) . '" class="ltt-comment-compose-attach__cancel" data-branch-open>Скасувати</a>
                        </div>
                        <div class="ltt-comment-compose-attach__body">' . nl2br($this->esc($replyTargetText)) . '</div>
                    </div>
                </div>
            ';
        }

        $formHtml = '
            <form method="post" class="' . $formClass . '" data-lenta-comment-form>
                <input type="hidden" name="action" value="lenta_comment_add">
                <input type="hidden" name="idxabon" value="' . $idxabon . '">
                <input type="hidden" name="post_id" value="' . $postId . '">
                <input type="hidden" name="comment_id" value="' . (int)$commentId . '">
                <input type="hidden" name="parent_id" value="' . $parentId . '">
                <div class="ltt-comment-compose__head">
                    <img src="' . $this->esc($user['avatar']) . '" alt="' . $this->esc($fullName) . '" class="ltt-comment-compose__avatar">
                    <div class="ltt-comment-compose__meta">
                        <b>' . $this->esc($fullName !== '' ? $fullName : 'Користувач системи') . '</b>
                        <span>' . $this->esc($label) . '</span>
                    </div>
                </div>
                ' . $hint . '
                <label class="ltt-comment-compose__field">
                    <textarea
                        name="comment_text"
                        rows="1"
                        maxlength="2000"
                        placeholder="' . $this->esc($placeholder) . '"
                        aria-label="Текст коментаря"
                        required
                    ></textarea>
                </label>
                <div class="ltt-comment-compose__foot">
                    <span class="ltt-composer-counter" data-lenta-comment-counter>0 / 2000</span>
                    <button type="submit" class="ltt-submit" data-lenta-comment-submit>Надіслати</button>
                </div>
            </form>
        ';

        return '
            <div class="' . $threadClass . '" id="ltt-comment-form">
                <div class="ltt-comment-compose-thread__rail" aria-hidden="true">
                    <span class="ltt-comment-compose-thread__dot ltt-comment-compose-thread__dot--top"></span>
                    <span class="ltt-comment-compose-thread__line"></span>
                    <span class="ltt-comment-compose-thread__dot ltt-comment-compose-thread__dot--bottom"></span>
                </div>
                <div class="ltt-comment-compose-thread__content">
                    ' . $replyTargetHtml . '
                    ' . $formHtml . '
                </div>
            </div>
        ';
    }

    private function renderCommentChildren(
        array $comments,
        array $childrenMap,
        array $replyCountMap,
        int $idxabon,
        int $postId,
        int $currentUserId,
        int $depth = 0
    ): string {
        $out = '<div class="ltt-comment-list' . ($depth > 0 ? ' ltt-comment-list--nested' : '') . '">';

        foreach ($comments as $comment) {
            $commentId = (int)($comment['idx'] ?? 0);
            $branchUrl = $this->buildBranchHref($idxabon, $postId, $commentId);
            $replyUrl = $this->buildBranchUrl($idxabon, $postId, $commentId, $commentId) . '#ltt-comment-form';

            $out .= $this->renderCommentCard($comment, $idxabon, $postId, $currentUserId, $replyCountMap, [
                'card_class' => $depth > 0 ? 'ltt-comment--nested-card' : '',
                'branch_url' => $branchUrl,
                'reply_url' => $replyUrl,
                'show_branch_button' => true,
                'depth' => $depth,
            ]);

            $children = $childrenMap[$commentId] ?? [];
            if (!empty($children)) {
                $out .= $this->renderCommentChildren($children, $childrenMap, $replyCountMap, $idxabon, $postId, $currentUserId, $depth + 1);
            }
        }

        $out .= '</div>';
        return $out;
    }

    private function renderCommentCard(array $comment, int $idxabon, int $postId, int $currentUserId, array $replyCountMap, array $options = []): string
    {
        $commentId = (int)($comment['idx'] ?? 0);
        $authorId = (int)($comment['user_id'] ?? 0);
        $authorName = trim((string)($comment['author_name'] ?? ''));
        $authorName = $authorName !== '' ? $authorName : 'Користувач системи';
        $time = $this->formatTimeAgo((string)($comment['created_at'] ?? ''));
        $text = trim((string)($comment['comment_text'] ?? ''));
        $authorProfileUrl = $authorId > 0 ? '/public-profile.php?idx=' . $authorId : '#';
        $replyCount = (int)($replyCountMap[$commentId] ?? 0);
        $cardClass = trim((string)($options['card_class'] ?? ''));
        $badge = trim((string)($options['badge'] ?? ''));
        $branchUrl = (string)($options['branch_url'] ?? $this->buildBranchHref($idxabon, $postId, $commentId));
        $shareUrl = (string)($options['share_url'] ?? ($this->buildBranchUrl($idxabon, $postId, $commentId) . '&shared=1' . '#ltt-comment-' . $commentId));
        $replyUrl = (string)($options['reply_url'] ?? $this->buildBranchUrl($idxabon, $postId, $commentId, $commentId) . '#ltt-comment-form');
        $showReplyButton = !array_key_exists('show_reply_button', $options) || (bool)$options['show_reply_button'];
        $showBranchButton = !array_key_exists('show_branch_button', $options) || (bool)$options['show_branch_button'];
        $depth = (int)($options['depth'] ?? 0);
        $isEdited = trim((string)($comment['updated_at'] ?? '')) !== '';

        $authorAttrs = 'data-author-id="' . $authorId . '"'
            . ' data-author-name="' . $this->esc($authorName) . '"'
            . ' data-author-avatar="' . $this->esc((string)($comment['author_avatar'] ?? '/avatars/ava.png')) . '"'
            . ' data-author-profile="' . $this->esc($authorProfileUrl) . '"'
            . ' data-author-self="' . ($authorId > 0 && $authorId === $currentUserId ? '1' : '0') . '"';

        $out = '<article class="ltt-comment' . ($cardClass !== '' ? ' ' . $cardClass : '') . ' depth-' . min($depth, 4) . '" id="ltt-comment-' . $commentId . '" data-branch-card>';
        if ($badge !== '') {
            $out .= '<div class="ltt-card-badge">' . $this->esc($badge) . '</div>';
        }

        $out .= '<div class="ltt-comment__head">';
        $out .= '<button type="button" class="ltt-comment__author grvdet-author-btn" ' . $authorAttrs . '>';
        $out .= '<img src="' . $this->esc((string)($comment['author_avatar'] ?? '/avatars/ava.png')) . '" alt="' . $this->esc($authorName) . '" class="ltt-comment__avatar">';
        $out .= '<span class="ltt-comment__meta">';
        $out .= '<strong>' . $this->esc($authorName) . '</strong>';
        $out .= '<span>' . $this->esc($time) . ($isEdited ? ' · змінено' : '') . '</span>';
        $out .= '</span>';
        $out .= '</button>';
        $out .= '</div>';
        $out .= '<div class="ltt-comment__body">' . nl2br($this->esc($text)) . '</div>';
        $out .= '<div class="ltt-comment__actions">';
        if ($showReplyButton) {
            $out .= '<a href="' . $this->esc($replyUrl) . '" class="ltt-comment-action" data-branch-open>';
            $out .= '<span class="ltt-comment-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12" /><path d="M11 8l-3 3l3 3" /><path d="M16 11h-8" /></svg></span>';
            $out .= '<span>Відповісти</span>';
            $out .= '</a>';
        }
        if ($showBranchButton) {
            $out .= '<a href="' . $this->esc($branchUrl) . '" class="ltt-comment-action ltt-comment-action--branch" data-branch-open>';
            $out .= '<span class="ltt-comment-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 18a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 6a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 6a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M7 8l0 8" /><path d="M9 18h6a2 2 0 0 0 2 -2v-5" /><path d="M14 14l3 -3l3 3" /></svg></span>';
            $out .= '<span>Гілка</span>';
            if ($replyCount > 0) {
                $out .= '<span class="ltt-comment-action__count">' . $replyCount . '</span>';
            }
            $out .= '</a>';
        }
        $out .= '<button type="button" class="ltt-comment-action ltt-comment-action--share" data-share-trigger data-share-url="' . $this->esc($shareUrl) . '" data-share-title="Посилання на коментар">';
        $out .= '<span class="ltt-comment-action__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 4v4c-6.575 1.028 -9.02 6.788 -10 12c-.037 .206 5.384 -5.962 10 -6v4l8 -7l-8 -7" /></svg></span>';
        $out .= '<span>Поділитися</span>';
        $out .= '</button>';
        $out .= '</div>';
        $out .= '</article>';

        return $out;
    }

    private function buildBranchUrl(int $idxabon, int $postId, ?int $commentId = null, ?int $replyToId = null): string
    {
        $query = [
            'idx' => $idxabon,
            'post' => $postId,
        ];

        if ($commentId !== null && $commentId > 0) {
            $query['comment_id'] = $commentId;
        }

        if ($replyToId !== null && $replyToId > 0) {
            $query['reply_to'] = $replyToId;
        }

        return '/cardout/branch?' . http_build_query($query);
    }

    private function buildBranchHref(int $idxabon, int $postId, ?int $commentId = null, ?int $replyToId = null, string $anchor = 'publications'): string
    {
        return $this->buildBranchUrl($idxabon, $postId, $commentId, $replyToId) . '#' . ltrim($anchor, '#');
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
