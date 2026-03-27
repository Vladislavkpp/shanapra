<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

$dblink = DbConnect();
$userId = (int)($_GET['idx'] ?? 0);
$currentUserId = (int)($_SESSION['uzver'] ?? 0);

function publicProfileEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function publicProfileAvatar(?string $path, string $fallback = '/avatars/ava.png'): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return $fallback;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    if (!is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
        return $fallback;
    }

    return $path;
}

function publicProfileFormatDate(?string $date, string $fallback = 'Не вказано'): string
{
    $date = trim((string)$date);
    if ($date === '' || $date === '0000-00-00') {
        return $fallback;
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('d.m.Y', $timestamp);
}

function publicProfileFormatLifeRange(?string $birth, ?string $death): string
{
    $birthFormatted = publicProfileFormatDate($birth, '');
    $deathFormatted = publicProfileFormatDate($death, '');

    if ($birthFormatted !== '' && $deathFormatted !== '') {
        return $birthFormatted . ' - ' . $deathFormatted;
    }
    if ($birthFormatted !== '') {
        return $birthFormatted . ' - ...';
    }
    if ($deathFormatted !== '') {
        return '... - ' . $deathFormatted;
    }

    return 'Дати не вказані';
}

function publicProfilePhotoPath(?string $path, string $fallback = '/graves/noimage.jpg'): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return $fallback;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    if (!is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
        return $fallback;
    }

    return $path;
}

function publicProfileRenderStatusBadge(?string $status): string
{
    $status = strtolower(trim((string)$status));
    if (!in_array($status, ['pending', 'approved'], true)) {
        $status = 'pending';
    }

    if ($status === 'approved') {
        return '<span class="pubprof-status pubprof-status--approved">Перевірено</span>';
    }

    return '<span class="pubprof-status pubprof-status--pending">На модерації</span>';
}

function publicProfileRenderGraves(array $graves): string
{
    if (empty($graves)) {
        return '';
    }

    $out = '';
    foreach ($graves as $grave) {
        $graveId = (int)($grave['idx'] ?? 0);
        $nameParts = array_filter([
            trim((string)($grave['lname'] ?? '')),
            trim((string)($grave['fname'] ?? '')),
            trim((string)($grave['mname'] ?? '')),
        ], static fn($value) => $value !== '');
        $graveName = !empty($nameParts) ? implode(' ', $nameParts) : 'Без ПІБ';
        $lifeRange = publicProfileFormatLifeRange((string)($grave['dt1'] ?? ''), (string)($grave['dt2'] ?? ''));
        $photo = publicProfilePhotoPath((string)($grave['photo1'] ?? ''));
        $cemeteryTitle = trim((string)($grave['cemetery_title'] ?? ''));
        $cemeteryTitle = $cemeteryTitle !== '' ? $cemeteryTitle : 'Кладовище не вказано';
        $out .= '
            <a href="/cardout.php?idx=' . $graveId . '" class="pubprof-post">
                <span class="pubprof-post__media">
                    <img src="' . publicProfileEsc($photo) . '" alt="' . publicProfileEsc($graveName) . '">
                </span>
                <div class="pubprof-post__body">
                    <h3>' . publicProfileEsc($graveName) . '</h3>
                    <p>' . publicProfileEsc($lifeRange) . '</p>
                    <div class="pubprof-post__meta">
                        <span>' . publicProfileEsc($cemeteryTitle) . '</span>
                    </div>
                </div>
            </a>
        ';
    }

    return $out;
}

$user = null;
$graves = [];
$gravesTotal = 0;
$gravesPerPage = 6;
if ($userId > 0) {
    $res = mysqli_query($dblink, "SELECT idx, fname, lname, avatar FROM users WHERE idx = $userId LIMIT 1");
    if ($res) {
        $user = mysqli_fetch_assoc($res) ?: null;
    }

    if ($user) {
        $countRes = mysqli_query(
            $dblink,
            "SELECT COUNT(*) AS cnt
             FROM grave
             WHERE idxadd = $userId
               AND LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'"
        );
        if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
            $gravesTotal = (int)($countRow['cnt'] ?? 0);
        }

        $gravesRes = mysqli_query(
            $dblink,
            "SELECT g.idx, g.lname, g.fname, g.mname, g.dt1, g.dt2, g.photo1, g.moderation_status, c.title AS cemetery_title
             FROM grave g
             LEFT JOIN cemetery c ON g.idxkladb = c.idx
             WHERE g.idxadd = $userId
               AND LOWER(COALESCE(g.moderation_status, 'pending')) <> 'rejected'
             ORDER BY g.idx DESC
             LIMIT $gravesPerPage"
        );
        if ($gravesRes) {
            while ($row = mysqli_fetch_assoc($gravesRes)) {
                $graves[] = $row;
            }
        }
    }
}

if (($_GET['action'] ?? '') === 'load_graves') {
    header('Content-Type: application/json; charset=utf-8');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $payload = [
        'status' => 'error',
        'html' => '',
        'next_offset' => $offset,
        'has_more' => false,
    ];

    if ($userId > 0) {
        $countRes = mysqli_query(
            $dblink,
            "SELECT COUNT(*) AS cnt
             FROM grave
             WHERE idxadd = $userId
               AND LOWER(COALESCE(moderation_status, 'pending')) <> 'rejected'"
        );
        $total = 0;
        if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
            $total = (int)($countRow['cnt'] ?? 0);
        }

        $rows = [];
        $gravesRes = mysqli_query(
            $dblink,
            "SELECT g.idx, g.lname, g.fname, g.mname, g.dt1, g.dt2, g.photo1, g.moderation_status, c.title AS cemetery_title
             FROM grave g
             LEFT JOIN cemetery c ON g.idxkladb = c.idx
             WHERE g.idxadd = $userId
               AND LOWER(COALESCE(g.moderation_status, 'pending')) <> 'rejected'
             ORDER BY g.idx DESC
             LIMIT $gravesPerPage OFFSET $offset"
        );
        if ($gravesRes) {
            while ($row = mysqli_fetch_assoc($gravesRes)) {
                $rows[] = $row;
            }
        }

        $nextOffset = $offset + count($rows);
        $payload = [
            'status' => 'ok',
            'html' => publicProfileRenderGraves($rows),
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < $total,
        ];
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$pageTitle = 'Публічний профіль';
if ($user) {
    $fullName = trim((string)$user['lname'] . ' ' . (string)$user['fname']);
    if ($fullName !== '') {
        $pageTitle = $fullName;
    }
}

View_Clear();
View_Add(Page_Up($pageTitle));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/public-profile.css?v=1">');
View_Add('<div class="public-profile">');
View_Add('<main class="pubprof-page">');

if (!$user) {
    View_Add('
        <section class="pubprof-empty">
            <h1>Користувача не знайдено</h1>
            <p>Профіль за вказаним ідентифікатором відсутній або був видалений.</p>
            <div class="pubprof-actions">
                <a href="/" class="pubprof-btn pubprof-btn--primary">На головну</a>
            </div>
        </section>
    ');
} else {
    $fullName = trim((string)$user['lname'] . ' ' . (string)$user['fname']);
    $fullName = $fullName !== '' ? $fullName : 'Користувач';
    $avatar = publicProfileAvatar((string)($user['avatar'] ?? ''));

    View_Add('
        <div class="pubprof-sticky" data-pubprof-sticky>
            <img src="' . publicProfileEsc($avatar) . '" alt="' . publicProfileEsc($fullName) . '">
            <div>
                <strong>' . publicProfileEsc($fullName) . '</strong>
                <span>Публічний профіль</span>
            </div>
            <button type="button" class="pubprof-sticky-top" data-scroll-top aria-label="Повернутися вгору">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 19V5"></path>
                    <path d="M5 12l7-7 7 7"></path>
                </svg>
            </button>
        </div>
        <section class="pubprof-card" data-pubprof-hero>
            <div class="pubprof-hero">
                <div class="pubprof-avatar">
                    <img src="' . publicProfileEsc($avatar) . '" alt="' . publicProfileEsc($fullName) . '">
                </div>
                <div class="pubprof-info">
                    <div class="pubprof-badges">
                        <span class="pubprof-badge">Публічний профіль</span>
                    </div>
                    <h1 class="pubprof-name">' . publicProfileEsc($fullName) . '</h1>
                    <p class="pubprof-note">Публічна сторінка користувача. Тут можна ознайомитися з основною інформацією та швидко зв\'язатися.</p>
                </div>
                <div class="pubprof-actions">
    ');

    if ($currentUserId > 0 && $currentUserId === (int)$user['idx']) {
        View_Add('
            <a href="/profile.php" class="pubprof-btn pubprof-btn--primary">
                <span>До профілю</span>
            </a>
        ');
    } elseif ($currentUserId > 0) {
        View_Add('
            <div class="pubprof-btn pubprof-btn--primary pubprof-btn--disabled" aria-disabled="true">
                <span class="pubprof-btn__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message-off"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h2" /><path d="M8 13h4" /><path d="M14 18h4a3 3 0 0 0 3 -3v-6m0 -4a3 3 0 0 0 -3 -3h-12a3 3 0 0 0 -3 3v8a3 3 0 0 0 3 3h2v3l3 -3" /><path d="M3 3l18 18" /></svg>
                </span>
                <span>Особисті чати вимкнено</span>
            </div>
        ');
    } elseif ($currentUserId <= 0) {
        View_Add('
            <a href="/auth.php" class="pubprof-btn pubprof-btn--primary">
                <span class="pubprof-btn__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-message"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 9h8" /><path d="M8 13h6" /><path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12" /></svg>
                </span>
                <span>Увійти щоб написати</span>
            </a>
        ');
    }

    View_Add('
                </div>
            </div>
        </section>
        <section class="pubprof-posts" data-public-posts data-user-id="' . $userId . '" data-total="' . $gravesTotal . '">
            <div class="pubprof-posts__head">
                <h2>Останні публікації</h2>
                <span>Карток: ' . $gravesTotal . '</span>
            </div>
    ');

    if (empty($graves)) {
        View_Add('<div class="pubprof-posts__empty">Користувач ще не додавав картки поховань.</div>');
    } else {
        View_Add('<div class="pubprof-posts__grid" data-public-posts-grid>');
        View_Add(publicProfileRenderGraves($graves));
        View_Add('</div>');
        if ($gravesTotal > $gravesPerPage) {
            View_Add('<div class="pubprof-posts__loader" data-public-posts-loader></div>');
        }
    }

    View_Add('
        </section>
        <script>
        (function () {
            var postsSection = document.querySelector("[data-public-posts]");
            if (!postsSection) return;

            var grid = postsSection.querySelector("[data-public-posts-grid]");
            var loader = postsSection.querySelector("[data-public-posts-loader]");
            var userId = postsSection.getAttribute("data-user-id");
            var total = parseInt(postsSection.getAttribute("data-total") || "0", 10);
            var offset = grid ? grid.children.length : 0;
            var isLoading = false;
            var hasMore = offset < total;

            function loadMore() {
                if (!hasMore || isLoading || !userId) return;
                isLoading = true;
                if (loader) loader.classList.add("is-visible");

                var url = "/public-profile.php?idx=" + encodeURIComponent(userId) + "&action=load_graves&offset=" + offset;
                fetch(url, { credentials: "same-origin" })
                    .then(function (response) { return response.json(); })
                    .then(function (payload) {
                        if (!payload || payload.status !== "ok") {
                            return;
                        }
                        if (grid && payload.html) {
                            grid.insertAdjacentHTML("beforeend", payload.html);
                        }
                        offset = payload.next_offset || offset;
                        hasMore = !!payload.has_more;
                        if (!hasMore && loader) {
                            loader.remove();
                        }
                    })
                    .catch(function () {})
                    .finally(function () {
                        isLoading = false;
                        if (loader) loader.classList.remove("is-visible");
                    });
            }

            if ("IntersectionObserver" in window && loader) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            loadMore();
                        }
                    });
                }, { rootMargin: "200px" });
                observer.observe(loader);
            } else {
                window.addEventListener("scroll", function () {
                    if (!hasMore || isLoading) return;
                    var scrollBottom = window.innerHeight + window.scrollY;
                    if (scrollBottom >= document.body.offsetHeight - 300) {
                        loadMore();
                    }
                });
            }
        })();

        (function () {
            var sticky = document.querySelector("[data-pubprof-sticky]");
            var hero = document.querySelector("[data-pubprof-hero]");
            if (!sticky || !hero) return;

            var scrollTopBtn = sticky.querySelector("[data-scroll-top]");
            if (scrollTopBtn) {
                scrollTopBtn.addEventListener("click", function () {
                    window.scrollTo({ top: 0, behavior: "smooth" });
                });
            }

            var toggleSticky = function (visible) {
                sticky.classList.toggle("is-visible", visible);
            };

            if ("IntersectionObserver" in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        toggleSticky(!entry.isIntersecting);
                    });
                }, { rootMargin: "-80px 0px 0px 0px", threshold: 0 });
                observer.observe(hero);
            } else {
                window.addEventListener("scroll", function () {
                    var rect = hero.getBoundingClientRect();
                    toggleSticky(rect.bottom <= 80);
                });
            }
        })();
        </script>
    ');
}

View_Add('</main>');
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
