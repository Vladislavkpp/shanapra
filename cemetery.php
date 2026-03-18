<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

function cemeteryEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cemeteryDateRange(string $dt1, string $dt2): string
{
    $dt1 = trim($dt1);
    $dt2 = trim($dt2);

    $format = static function (string $date): string {
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }
        return date('d.m.Y', $ts);
    };

    $left = $format($dt1);
    $right = $format($dt2);

    if ($left !== '' && $right !== '') {
        return $left . ' - ' . $right;
    }
    if ($left !== '') {
        return $left . ' - ...';
    }
    if ($right !== '') {
        return '... - ' . $right;
    }

    return 'Дати не вказані';
}

function cemeteryNormalizeCoord(string $value): ?float
{
    $value = trim(str_replace(',', '.', $value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return (float)$value;
}

function cemeteryResolveCoordinates(?float $gpsx, ?float $gpsy): ?array
{
    if ($gpsx === null || $gpsy === null) {
        return null;
    }

    $variants = [
        ['lat' => $gpsx, 'lon' => $gpsy, 'source' => 'xy'],
        ['lat' => $gpsy, 'lon' => $gpsx, 'source' => 'yx'],
    ];

    $best = null;
    $bestScore = -999;
    foreach ($variants as $variant) {
        $lat = $variant['lat'];
        $lon = $variant['lon'];
        $score = 0;

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            $score = -999;
        } else {
            $score += 2;
            if ($lat >= 44 && $lat <= 53 && $lon >= 22 && $lon <= 41) {
                $score += 3;
            } elseif ($lat >= 35 && $lat <= 60 && $lon >= 10 && $lon <= 60) {
                $score += 1;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $variant;
        }
    }

    if ($bestScore < 0 || $best === null) {
        return null;
    }

    return $best;
}

function cemeteryFormatCoord(float $value): string
{
    return rtrim(rtrim(sprintf('%.7F', $value), '0'), '.');
}

function cemeteryLoadGravesPage(mysqli $dblink, int $cemeteryId, int $page, int $perPage): array
{
    if ($cemeteryId <= 0 || $perPage <= 0) {
        return [];
    }

    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT idx, lname, fname, mname, dt1, dt2, photo1
        FROM grave
        WHERE idxkladb = $cemeteryId
        ORDER BY idx DESC
        LIMIT $perPage OFFSET $offset
    ";
    $res = mysqli_query($dblink, $query);
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    return $rows;
}

function cemeteryRenderGravesCards(array $graves): string
{
    if (empty($graves)) {
        return '<div class="cemdet-empty-inline">Для цього кладовища ще немає пов\'язаних карток поховань.</div>';
    }

    $out = '<div class="cemdet-grids">';
    foreach ($graves as $grave) {
        $graveIdx = (int)($grave['idx'] ?? 0);
        $nameParts = array_filter([
            trim((string)($grave['lname'] ?? '')),
            trim((string)($grave['fname'] ?? '')),
            trim((string)($grave['mname'] ?? '')),
        ], static fn($v) => $v !== '');
        $graveName = !empty($nameParts) ? implode(' ', $nameParts) : 'Без ПІБ';

        $photo = (string)($grave['photo1'] ?? '');
        if ($photo === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $photo)) {
            $photo = '/graves/noimage.jpg';
        }

        $out .= '<a class="cemdet-grave" href="/cardout.php?idx=' . $graveIdx . '">';
        $out .= '<span class="cemdet-grave-media"><img src="' . cemeteryEsc($photo) . '" alt="' . cemeteryEsc($graveName) . '"></span>';
        $out .= '<div class="cemdet-grave-data">';
        $out .= '<b class="cemdet-grave-title">' . cemeteryEsc($graveName) . '</b>';
        $out .= '<span class="cemdet-grave-dates">' . cemeteryDateRange((string)($grave['dt1'] ?? ''), (string)($grave['dt2'] ?? '')) . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }
    $out .= '</div>';

    return $out;
}

function cemeteryRenderPagerButton(int $page, string $label, bool $active = false, bool $disabled = false, string $extraClass = ''): string
{
    $classes = 'cemdet-pager-btn';
    if ($extraClass !== '') {
        $classes .= ' ' . $extraClass;
    }
    if ($active) {
        $classes .= ' is-active';
    }
    $disabledAttr = $disabled ? ' disabled aria-disabled="true"' : '';
    $dataAttr = $disabled ? '' : ' data-page="' . $page . '"';
    return '<button type="button" class="' . $classes . '"' . $dataAttr . $disabledAttr . '>' . $label . '</button>';
}

function cemeteryRenderGravesPager(int $currentPage, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $currentPage = max(1, min($currentPage, $totalPages));
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    $out = '<nav class="cemdet-pager" aria-label="Пагінація карток поховань">';
    $out .= cemeteryRenderPagerButton(max(1, $currentPage - 1), '&lsaquo; Назад', false, $currentPage <= 1, 'cemdet-pager-btn--nav');

    if ($start > 1) {
        $out .= cemeteryRenderPagerButton(1, '1', $currentPage === 1);
        if ($start > 2) {
            $out .= '<span class="cemdet-pager-gap">…</span>';
        }
    }

    for ($page = $start; $page <= $end; $page++) {
        $out .= cemeteryRenderPagerButton($page, (string)$page, $page === $currentPage);
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $out .= '<span class="cemdet-pager-gap">…</span>';
        }
        $out .= cemeteryRenderPagerButton($totalPages, (string)$totalPages, $currentPage === $totalPages);
    }

    $out .= cemeteryRenderPagerButton(min($totalPages, $currentPage + 1), 'Вперед &rsaquo;', false, $currentPage >= $totalPages, 'cemdet-pager-btn--nav');
    $out .= '</nav>';

    return $out;
}

$idx = (int)($_GET['idx'] ?? 0);
$dblink = DbConnect();
$isAjaxGraves = isset($_GET['ajax_graves']) && (string)$_GET['ajax_graves'] === '1';

$cemetery = null;
$gravesCount = 0;
$latestGraves = [];
$gravesPerPage = 10;
$gravesPage = max(1, (int)($_GET['page'] ?? 1));
$gravesTotalPages = 1;

if ($idx > 0) {
    $query = "
        SELECT c.*,
               m.title AS town_name,
               d.title AS district_name,
               r.title AS region_name
        FROM cemetery c
        LEFT JOIN misto m ON c.town = m.idx
        LEFT JOIN district d ON c.district = d.idx
        LEFT JOIN region r ON d.region = r.idx
        WHERE c.idx = $idx
        LIMIT 1
    ";
    $res = mysqli_query($dblink, $query);
    if ($res) {
        $cemetery = mysqli_fetch_assoc($res) ?: null;
    }
}

if ($cemetery) {
    $countRes = mysqli_query($dblink, "SELECT COUNT(*) AS cnt FROM grave WHERE idxkladb = $idx");
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $gravesCount = (int)($countRow['cnt'] ?? 0);
    }

    $gravesTotalPages = max(1, (int)ceil($gravesCount / $gravesPerPage));
    $gravesPage = min($gravesPage, $gravesTotalPages);
    $latestGraves = cemeteryLoadGravesPage($dblink, $idx, $gravesPage, $gravesPerPage);
}

if ($isAjaxGraves) {
    header('Content-Type: application/json; charset=UTF-8');
    if (!$cemetery) {
        echo json_encode(['ok' => false, 'message' => 'Кладовище не знайдено'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode([
            'ok' => true,
            'cards_html' => cemeteryRenderGravesCards($latestGraves),
            'pager_html' => cemeteryRenderGravesPager($gravesPage, $gravesTotalPages),
            'page' => $gravesPage,
            'total_pages' => $gravesTotalPages,
            'total_items' => $gravesCount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    mysqli_close($dblink);
    exit;
}

mysqli_close($dblink);

$title = $cemetery ? trim((string)($cemetery['title'] ?? '')) : '';
$safeTitle = cemeteryEsc($title !== '' ? $title : 'Кладовище');
$pageTitle = $cemetery ? ($title !== '' ? $title : 'Кладовище') : 'Кладовище не знайдено';

$town = trim((string)($cemetery['town_name'] ?? ''));
$district = trim((string)($cemetery['district_name'] ?? ''));
$region = trim((string)($cemetery['region_name'] ?? ''));
$address = trim((string)($cemetery['adress'] ?? ''));
$gpsxRaw = trim((string)($cemetery['gpsx'] ?? ''));
$gpsyRaw = trim((string)($cemetery['gpsy'] ?? ''));

$scheme = (string)($cemetery['scheme'] ?? '');
$isSchemeMissing = ($scheme === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $scheme));
if ($isSchemeMissing) {
    $scheme = '/cemeteries/noscheme.png';
}

$gpsx = cemeteryNormalizeCoord($gpsxRaw);
$gpsy = cemeteryNormalizeCoord($gpsyRaw);
$coordData = cemeteryResolveCoordinates($gpsx, $gpsy);
$hasCoordinates = ($coordData !== null);
$isZeroCoordinates = false;

$mapEmbed = '';
$mapLink = '';
if ($hasCoordinates) {
    $lat = (float)$coordData['lat'];
    $lon = (float)$coordData['lon'];
    $isZeroCoordinates = (abs($lat) < 0.0000001 && abs($lon) < 0.0000001);
    if (!$isZeroCoordinates) {
        $latLon = cemeteryFormatCoord($lat) . ',' . cemeteryFormatCoord($lon);
        $mapEmbed = 'https://maps.google.com/maps?q=' . rawurlencode($latLon) . '&z=16&output=embed';
        $mapLink = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latLon);
    }
}

$chips = [];
if ($town !== '') {
    $chips[] = '<span class="cemdet-chip">' . cemeteryEsc($town) . '</span>';
}
if ($district !== '') {
    $chips[] = '<span class="cemdet-chip">' . cemeteryEsc($district) . ' р-н</span>';
}
if ($region !== '') {
    $chips[] = '<span class="cemdet-chip">' . cemeteryEsc($region) . ' обл.</span>';
}

$locationParts = [];
if ($district !== '') {
    $locationParts[] = cemeteryEsc($district) . ' район';
}
if ($region !== '') {
    $locationParts[] = cemeteryEsc($region) . ' область';
}
$locationLine = !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана';
$currentUserId = (int)($_SESSION['uzver'] ?? 0);
$canEdit = ($cemetery && $currentUserId > 0 && (int)($cemetery['idxadd'] ?? 0) === $currentUserId);

View_Clear();
View_Add(Page_Up($pageTitle));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/cemetery-detail.css?v=5">');

View_Add('<div class="out out-cemdet">');
View_Add('<main class="cemdet-page">');

if (!$cemetery) {
    View_Add('
    <section class="cemdet-empty">
        <h1>Кладовище не знайдено</h1>
        <p>Запис за вказаним ідентифікатором відсутній або був видалений.</p>
        <a href="/kladbsearch.php" class="cemdet-btn cemdet-btn--dark">Повернутися до пошуку</a>
    </section>
    ');
} else {
    $addressHtml = $address !== '' ? cemeteryEsc($address) : 'Адреса не вказана';
    $coordsHtml = $hasCoordinates
        ? 'Lat: ' . cemeteryEsc(cemeteryFormatCoord((float)$coordData['lat'])) . ', Lon: ' . cemeteryEsc(cemeteryFormatCoord((float)$coordData['lon']))
        : 'Координати не вказані';
    $addedAt = trim((string)($cemetery['dtadd'] ?? ''));
    $addedAtHtml = $addedAt !== '' && $addedAt !== '0000-00-00' ? cemeteryEsc($addedAt) : 'Немає даних';
    $moderationStatus = strtolower(trim((string)($cemetery['moderation_status'] ?? 'pending')));
    if (!in_array($moderationStatus, ['pending', 'approved', 'rejected'], true)) {
        $moderationStatus = 'pending';
    }

    $moderationBadgeHtml = '';
    if ($moderationStatus === 'pending') {
        $moderationBadgeHtml = '<span class="cemdet-moderation cemdet-moderation--pending">На модерації</span>';
    } elseif ($moderationStatus === 'approved') {
        $moderationBadgeHtml = '<span class="cemdet-moderation cemdet-moderation--approved" aria-label="Перевірено модерацією"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg><span class="cemdet-moderation-text">Перевірено</span></span>';
    } else {
        $moderationBadgeHtml = '<span class="cemdet-moderation cemdet-moderation--rejected">Відхилено</span>';
    }

    View_Add('<div class="cemdet-sticky" data-cemdet-sticky>');
    View_Add('<img src="' . cemeteryEsc($scheme) . '" alt="' . $safeTitle . '">');
    View_Add('<div><strong>' . $safeTitle . '</strong><span>ID #' . $idx . '</span></div>');
    View_Add('<button type="button" class="cemdet-sticky-top" data-scroll-top aria-label="Повернутися вгору"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="M5 12l7-7 7 7"></path></svg></button>');
    View_Add('</div>');

    View_Add('<section class="cemdet-hero" data-cemdet-hero>');
    View_Add($moderationBadgeHtml);
    View_Add('<div class="cemdet-media' . ($isSchemeMissing ? ' cemdet-media--empty' : '') . '">');
    View_Add('<img src="' . cemeteryEsc($scheme) . '" alt="' . $safeTitle . '">');
    if ($isSchemeMissing) {
        View_Add('<span class="cemdet-media-note">Схему кладовища не встановлено</span>');
    }
    View_Add('</div>');

    View_Add('<div class="cemdet-main">');
    View_Add('<div class="cemdet-breadcrumbs"><a href="/">Головна</a><span>/</span><a href="/kladbsearch.php">Кладовища</a><span>/</span><b>' . $safeTitle . '</b></div>');
    View_Add('<h1 class="cemdet-title">' . $safeTitle . '</h1>');

    View_Add('<div class="cemdet-chips">' . implode('', $chips) . '</div>');

    View_Add('<div class="cemdet-facts">');
    View_Add('<div class="cemdet-fact"><span>Локація</span><b>' . $locationLine . '</b></div>');
    View_Add('<div class="cemdet-fact"><span>Адреса</span><b>' . $addressHtml . '</b></div>');
    View_Add('<div class="cemdet-fact"><span>Координати</span><b>' . $coordsHtml . '</b></div>');
    View_Add('<div class="cemdet-fact"><span>Додано</span><b>' . $addedAtHtml . '</b></div>');
    View_Add('</div>');

    View_Add('<div class="cemdet-actions">');
    View_Add('<a href="/kladbsearch.php" class="cemdet-btn cemdet-btn--light">До списку кладовищ</a>');
    if ($hasCoordinates && !$isZeroCoordinates) {
        View_Add('<a href="' . $mapLink . '" class="cemdet-btn cemdet-btn--dark" target="_blank" rel="noopener">Відкрити в Google Maps</a>');
    }
    if ($canEdit) {
        View_Add('<a href="/kladbupdate.php?idx=' . $idx . '" class="cemdet-btn cemdet-btn--ghost">Редагувати</a>');
    }
    View_Add('</div>');
    View_Add('</div>');
    View_Add('</section>');

    View_Add('<section class="cemdet-stats">');
    View_Add('<article class="cemdet-stat"><span>Карток поховань</span><b>' . $gravesCount . '</b></article>');
    View_Add('<article class="cemdet-stat"><span>Фото-схема</span><b>' . ($isSchemeMissing ? 'Відсутня' : 'Додана') . '</b></article>');
    View_Add('<article class="cemdet-stat"><span>GPS-дані</span><b>' . (($hasCoordinates && !$isZeroCoordinates) ? 'Заповнено' : 'Відсутні') . '</b></article>');
    View_Add('<article class="cemdet-stat"><span>ID кладовища</span><b>#' . $idx . '</b></article>');
    View_Add('</section>');

    View_Add('<section class="cemdet-grid">');

    View_Add('<article class="cemdet-panel">');
    View_Add('<div class="cemdet-panel-head"><h2>Карта та навігація</h2></div>');
    if ($hasCoordinates && !$isZeroCoordinates) {
        View_Add('<div class="cemdet-map-wrap"><iframe src="' . $mapEmbed . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Мапа кладовища"></iframe></div>');
    } elseif ($isZeroCoordinates) {
        View_Add('<div class="cemdet-map-placeholder">GPS дані відсутні.</div>');
    } else {
        View_Add('<div class="cemdet-map-placeholder">Координати не додані. Тут буде інтерактивна мапа після заповнення GPS.</div>');
    }
    View_Add('</article>');

    View_Add('<article class="cemdet-panel">');
    View_Add('<div class="cemdet-panel-head"><h2>Додаткова інформація</h2></div>');
    View_Add('<p class="cemdet-muted">У поточній формі додавання кладовища зберігаються базові дані: назва, локація, адреса, координати та схема. Розширений опис (графік, контакти, правила відвідування) поки що не заповнюється.</p>');
    View_Add('<ul class="cemdet-list">');
    View_Add('<li>Зараз доступно: адреса, координати та схема розташування.</li>');
    View_Add('<li>Після розширення форми тут можна буде показувати графік роботи.</li>');
    View_Add('<li>Також тут можна виводити контакти адміністрації та примітки.</li>');
    View_Add('</ul>');
    View_Add('</article>');

    View_Add('<article class="cemdet-panel cemdet-panel--wide">');
    View_Add('<div class="cemdet-panel-head"><h2>Картки поховань цього кладовища</h2><a href="/searchx.php" class="cemdet-inline-link">Всі картки</a></div>');
    View_Add('<div id="cemdet-graves-block" class="cemdet-graves-block" data-cemetery-id="' . $idx . '" data-current-page="' . $gravesPage . '" data-total-pages="' . $gravesTotalPages . '">');
    View_Add('<div id="cemdet-graves-list">' . cemeteryRenderGravesCards($latestGraves) . '</div>');
    View_Add('<div id="cemdet-graves-pager">' . cemeteryRenderGravesPager($gravesPage, $gravesTotalPages) . '</div>');
    View_Add('<div id="cemdet-graves-status" class="cemdet-graves-status" aria-live="polite"></div>');
    View_Add('</div>');
    View_Add('</article>');

    View_Add('</section>');

    View_Add('<script>
(function () {
    var block = document.getElementById("cemdet-graves-block");
    if (!block) return;

    var listNode = document.getElementById("cemdet-graves-list");
    var pagerNode = document.getElementById("cemdet-graves-pager");
    var statusNode = document.getElementById("cemdet-graves-status");
    var cemeteryId = Number(block.getAttribute("data-cemetery-id") || "0");
    var isLoading = false;

    if (!listNode || !pagerNode || !cemeteryId) return;

    function setStatus(text, isError) {
        if (!statusNode) return;
        statusNode.textContent = text || "";
        statusNode.classList.toggle("is-error", !!isError);
    }

    function setLoading(state) {
        isLoading = state;
        block.classList.toggle("is-loading", state);
    }

    function loadPage(page) {
        if (isLoading) return;
        setLoading(true);
        setStatus("", false);

        fetch("/cemetery.php?ajax_graves=1&idx=" + encodeURIComponent(String(cemeteryId)) + "&page=" + encodeURIComponent(String(page)), {
            credentials: "same-origin"
        })
            .then(function (response) {
                if (!response.ok) throw new Error("network");
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.ok) throw new Error("invalid");
                listNode.innerHTML = payload.cards_html || "";
                pagerNode.innerHTML = payload.pager_html || "";
                block.setAttribute("data-current-page", String(payload.page || page));
                block.setAttribute("data-total-pages", String(payload.total_pages || 1));
            })
            .catch(function () {
                setStatus("Не вдалося завантажити сторінку карток. Спробуйте ще раз.", true);
            })
            .finally(function () {
                setLoading(false);
            });
    }

    block.addEventListener("click", function (event) {
        var button = event.target.closest(".cemdet-pager-btn[data-page]");
        if (!button || !pagerNode.contains(button)) return;
        event.preventDefault();
        if (button.disabled || button.classList.contains("is-active")) return;
        var page = Number(button.getAttribute("data-page") || "0");
        if (!Number.isFinite(page) || page < 1) return;
        loadPage(page);
    });

    var sticky = document.querySelector("[data-cemdet-sticky]");
    var hero = document.querySelector("[data-cemdet-hero]");
    if (sticky && hero) {
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
    }
})();
</script>');
}

View_Add('</main>');
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
