<?php
require_once "function.php";
require_once "function_vra.php";

function RenderKSearchCard(array $c): string
{
    $idx = (int)($c['idx'] ?? 0);
    $title = htmlspecialchars((string)($c['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars((string)($c['adress'] ?? ''), ENT_QUOTES, 'UTF-8');
    $moderationStatus = strtolower(trim((string)($c['moderation_status'] ?? '')));
    $scheme = (string)($c['scheme'] ?? '');
    $isSchemeMissing = ($scheme === '' || !is_file($_SERVER['DOCUMENT_ROOT'] . $scheme));

    if ($isSchemeMissing) {
        $scheme = '/cemeteries/noscheme.png';
    }

    $town = trim((string)($c['town_name'] ?? ''));
    $district = trim((string)($c['district_name'] ?? ''));
    $region = trim((string)($c['region_name'] ?? ''));

    $safeTown = htmlspecialchars($town, ENT_QUOTES, 'UTF-8');
    $safeDistrict = htmlspecialchars($district, ENT_QUOTES, 'UTF-8');
    $safeRegion = htmlspecialchars($region, ENT_QUOTES, 'UTF-8');

    $locationParts = [];
    if ($district !== '') {
        $locationParts[] = $safeDistrict . ' район';
    }
    if ($region !== '') {
        $locationParts[] = $safeRegion . ' область';
    }
    $location = !empty($locationParts) ? implode(', ', $locationParts) : 'Локація не вказана';

    $link = '/cemetery.php?idx=' . $idx;
    $moderationBadgeHtml = '';
    if ($moderationStatus === 'pending') {
        $moderationBadgeHtml = '<span class="kcard-moderation kcard-moderation--pending">На модерації</span>';
    } elseif ($moderationStatus === 'approved') {
        $moderationBadgeHtml = '<span class="kcard-moderation kcard-moderation--approved" aria-label="Перевірено модерацією"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg><span class="kcard-moderation-text">Перевірено</span></span>';
    }

    $out = '<article class="kcard">';
    $out .= '<div class="kcard-media' . ($isSchemeMissing ? ' kcard-media--empty' : '') . '">';
    $out .= '<img src="' . htmlspecialchars($scheme, ENT_QUOTES, 'UTF-8') . '" alt="' . $title . '">';
    $out .= $moderationBadgeHtml;
    if ($isSchemeMissing) {
        $out .= '<span class="kcard-media-note">Фотографію не встановлено</span>';
    }
    $out .= '</div>';
    $out .= '<div class="kcard-body">';
    $out .= '<h3 class="kcard-title">' . ($title !== '' ? $title : 'Без назви') . '</h3>';
    $out .= '<div class="kcard-meta">';
    if ($town !== '') {
        $out .= '<span class="kcard-chip">' . $safeTown . '</span>';
    }
    if ($district !== '') {
        $out .= '<span class="kcard-chip">' . $safeDistrict . ' р-н</span>';
    }
    if ($region !== '') {
        $out .= '<span class="kcard-chip">' . $safeRegion . ' обл.</span>';
    }
    $out .= '</div>';
    $out .= '<p class="kcard-location">' . $location . '</p>';
    if ($address !== '') {
        $out .= '<p class="kcard-address"><b>Адреса:</b> ' . $address . '</p>';
    }
    $out .= '</div>';
    $out .= '<div class="kcard-footer">';
    $out .= '<span class="kcard-id">ID #' . $idx . '</span>';
    $out .= '<a href="' . $link . '" class="kcard-link"><span>Деталі</span>';
    $out .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right">';
    $out .= '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>';
    $out .= '<path d="M5 12l14 0" />';
    $out .= '<path d="M15 16l4 -4" />';
    $out .= '<path d="M15 8l4 4" />';
    $out .= '</svg></a>';
    $out .= '</div>';
    $out .= '</article>';

    return $out;
}

$cp = (int)($_GET['page'] ?? 1);
if ($cp < 1) {
    $cp = 1;
}
$perpage = 6;

$title = trim($_GET['title'] ?? '');
$region = $_GET['region'] ?? '';
$district = $_GET['district'] ?? '';

$dblink = DbConnect();
$rows = [];

$sql = "
SELECT c.*,
       m.title AS town_name,
       d.title AS district_name,
       r.title AS region_name
FROM cemetery c
LEFT JOIN misto m ON c.town = m.idx
LEFT JOIN district d ON c.district = d.idx
LEFT JOIN region r ON d.region = r.idx
WHERE 1 = 1
  AND LOWER(COALESCE(c.moderation_status, 'pending')) <> 'rejected'
";

if ($title !== '') {
    $safeTitle = mysqli_real_escape_string($dblink, $title);
    $sql .= ' AND c.title LIKE "%' . $safeTitle . '%"';
}

if ($region !== '') {
    $sql .= ' AND d.region = ' . (int)$region;
}

if ($district !== '') {
    $sql .= ' AND c.district = ' . (int)$district;
}

$sql .= " ORDER BY c.idx ASC";

$res = mysqli_query($dblink, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

$cout = count($rows);

$regionOptions = '<option value="">Виберіть область</option>';
$regionCustomOptions = '';
$regionTriggerText = 'Виберіть область';
$resRegions = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
if ($resRegions) {
    while ($r = mysqli_fetch_assoc($resRegions)) {
        $id = (int)$r['idx'];
        $label = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
        $selected = ($region !== '' && (int)$region === $id) ? ' selected' : '';
        $regionOptions .= '<option value="' . $id . '"' . $selected . '>' . $label . '</option>';
        $regionCustomOptions .= '<span class="custom-option" data-value="' . $id . '">' . $label . '</span>';
        if ($selected !== '') {
            $regionTriggerText = $label;
        }
    }
}

$districtOptions = '<option value="">Виберіть область</option>';
$districtCustomOptions = '';
$districtTriggerText = 'Виберіть область';
if ($region !== '') {
    $resDistricts = mysqli_query(
        $dblink,
        "SELECT idx, title FROM district WHERE region = " . (int)$region . " ORDER BY title"
    );
    if ($resDistricts && mysqli_num_rows($resDistricts) > 0) {
        $districtOptions = '<option value="">Виберіть район</option>';
        $districtTriggerText = 'Виберіть район';
        while ($d = mysqli_fetch_assoc($resDistricts)) {
            $id = (int)$d['idx'];
            $label = htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8');
            $selected = ($district !== '' && (int)$district === $id) ? ' selected' : '';
            $districtOptions .= '<option value="' . $id . '"' . $selected . '>' . $label . '</option>';
            $districtCustomOptions .= '<span class="custom-option" data-value="' . $id . '">' . $label . '</span>';
            if ($selected !== '') {
                $districtTriggerText = $label;
            }
        }
    }
}

$searchParts = [];
if ($title !== '') {
    $searchParts[] = 'Назва: ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
}
if ($region !== '') {
    $searchParts[] = 'Область: ' . htmlspecialchars($regionTriggerText, ENT_QUOTES, 'UTF-8');
}
if ($district !== '') {
    $searchParts[] = 'Район: ' . htmlspecialchars($districtTriggerText, ENT_QUOTES, 'UTF-8');
}
$searchLine = !empty($searchParts) ? implode('; ', $searchParts) : '—';

mysqli_close($dblink);

View_Clear();
View_Add(Page_Up('Результати пошуку кладовищ'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/cemetery-style.css?v=1">');

View_Add('<div class="out-xsearch">');
View_Add('<div class="search-container">');
View_Add('<div class="search-out search-toolbar">');

View_Add('<div class="search-badges">');
View_Add('<div class="search-badge search-badge--params">Пошук за параметрами: ' . $searchLine . '</div>');
View_Add('<div class="search-badge">Всього кладовищ: <strong>' . $cout . '</strong></div>');
View_Add('</div>');

View_Add('<a href="/addcemetery.php" class="searchklb-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-add-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>Додати кладовище</a>');

View_Add('<div class="search-toolbar-right">');
View_Add('<form class="search-form" action="/kladbsearch.php" method="get">');
if ($region !== '') {
    View_Add('<input type="hidden" name="region" value="' . (int)$region . '">');
}
if ($district !== '') {
    View_Add('<input type="hidden" name="district" value="' . (int)$district . '">');
}
View_Add('<input type="hidden" name="page" value="1">');
View_Add('<div class="search-input-wrap">');
View_Add('<input type="text" name="title" value="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" placeholder="Пошук за назвою кладовища" class="search-inputx" autocomplete="off">');
View_Add('<button type="submit" class="search-submit-btn" aria-label="Пошук"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>');
View_Add('</div>');
View_Add('</form>');

View_Add('<div class="filter-dropdown">');
View_Add('<button type="button" class="filter-btnx" id="filterToggle"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="filter-btn-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227" /></svg>Фільтр</button>');

View_Add('
<div class="filter-panel" id="filterPanel" aria-hidden="true">
    <form class="filter-form" action="/kladbsearch.php" method="get">
        <input type="hidden" name="page" value="1">
        ' . ($title !== '' ? '<input type="hidden" name="title" value="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' : '') . '

        <div class="filter-header">
            <div class="filter-title">Розширений фільтр</div>
            <button type="button" class="filter-close" aria-label="Закрити фільтр">&times;</button>
        </div>

        <div class="filter-grid">
            <div class="filter-field">
                <div class="input-container">
                    <select name="region" id="filter-region" class="login-Input" style="display:none;">
                        ' . $regionOptions . '
                    </select>
                    <div class="custom-select-wrapper" id="region-wrapper">
                        <div class="custom-select-trigger">' . htmlspecialchars($regionTriggerText, ENT_QUOTES, 'UTF-8') . '</div>
                        <div class="custom-options">' . $regionCustomOptions . '</div>
                    </div>
                    <label class="label-active">Область</label>
                </div>
            </div>

            <div class="filter-field">
                <div class="input-container">
                    <select name="district" id="filter-district" class="login-Input" style="display:none;">
                        ' . $districtOptions . '
                    </select>
                    <div class="custom-select-wrapper" id="district-wrapper">
                        <div class="custom-select-trigger">' . htmlspecialchars($districtTriggerText, ENT_QUOTES, 'UTF-8') . '</div>
                        <div class="custom-options">' . $districtCustomOptions . '</div>
                    </div>
                    <label class="label-active">Район</label>
                </div>
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="filter-apply">Застосувати</button>
            <button type="reset" class="filter-reset">Скинути</button>
        </div>
    </form>
</div>');

View_Add('</div>');
View_Add('</div>');
View_Add('</div>');

View_Add('<div class="cardk-out">');

if ($cout === 0) {
    View_Add('<div class="no-results-wrap"><div class="no-results">За вашим запитом нічого не знайдено</div></div>');
} else {
    $offset = ($cp - 1) * $perpage;
    $rowsPage = array_slice($rows, $offset, $perpage);

    foreach ($rowsPage as $c) {
        View_Add(RenderKSearchCard($c));
    }
}

View_Add('</div>');
View_Add('</div>');

if ($cout > 0) {
    View_Add('<div class="paginator-out">');
    View_Add(Paginatex::Showx($cp, $cout, $perpage));
    View_Add('</div>');
}

View_Add('</div>');

View_Add('
<script>
document.addEventListener("DOMContentLoaded", function () {
    var filterToggle = document.getElementById("filterToggle");
    var filterPanel = document.getElementById("filterPanel");
    if (!filterToggle || !filterPanel) return;

    var closeBtn = filterPanel.querySelector(".filter-close");
    var resetBtn = filterPanel.querySelector(".filter-reset");

    var regionSelect = document.getElementById("filter-region");
    var districtSelect = document.getElementById("filter-district");
    var regionWrapper = document.getElementById("region-wrapper");
    var districtWrapper = document.getElementById("district-wrapper");

    function openPanel() {
        filterPanel.classList.add("open");
        filterPanel.setAttribute("aria-hidden", "false");
        filterToggle.classList.add("active");
    }

    function closePanel() {
        filterPanel.classList.remove("open");
        filterPanel.setAttribute("aria-hidden", "true");
        filterToggle.classList.remove("active");
    }

    filterToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        if (filterPanel.classList.contains("open")) {
            closePanel();
        } else {
            openPanel();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            closePanel();
        });
    }

    document.addEventListener("click", function (e) {
        if (!filterPanel.contains(e.target) && !filterToggle.contains(e.target)) {
            if (!e.target.closest(".custom-select-wrapper")) {
                closePanel();
            }
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closePanel();
        }
    });

    function initCustomSelect(wrapper) {
        if (!wrapper) return;
        var trigger = wrapper.querySelector(".custom-select-trigger");
        var options = wrapper.querySelector(".custom-options");
        var select = wrapper.previousElementSibling;

        if (!trigger || !options || !select) return;

        trigger.addEventListener("click", function (e) {
            e.stopPropagation();

            document.querySelectorAll(".custom-select-wrapper").forEach(function (w) {
                if (w !== wrapper) {
                    w.classList.remove("open");
                    var wOptions = w.querySelector(".custom-options");
                    if (wOptions) {
                        wOptions.style.display = "none";
                    }
                }
            });

            wrapper.classList.toggle("open");
            options.style.display = wrapper.classList.contains("open") ? "flex" : "none";
        });

        function bindOptions() {
            options.querySelectorAll(".custom-option").forEach(function (opt) {
                opt.onclick = function (e) {
                    e.stopPropagation();
                    trigger.textContent = opt.textContent;
                    select.value = opt.dataset.value || "";
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    wrapper.classList.remove("open");
                    options.style.display = "none";
                };
            });
        }

        bindOptions();
        wrapper._bindOptions = bindOptions;
    }

    function resetDistrictsUi() {
        if (!districtSelect) return;

        districtSelect.innerHTML = "";
        var opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "Виберіть область";
        districtSelect.appendChild(opt);

        if (districtWrapper) {
            var trigger = districtWrapper.querySelector(".custom-select-trigger");
            var options = districtWrapper.querySelector(".custom-options");
            if (trigger) trigger.textContent = "Виберіть область";
            if (options) options.innerHTML = "";
            if (districtWrapper._bindOptions) districtWrapper._bindOptions();
        }
    }

    function loadDistricts(regionId, selected) {
        if (!districtSelect) return;

        districtSelect.innerHTML = "";
        var loadingOpt = document.createElement("option");
        loadingOpt.value = "";
        loadingOpt.textContent = "Завантаження...";
        districtSelect.appendChild(loadingOpt);

        if (districtWrapper) {
            var loadingTrigger = districtWrapper.querySelector(".custom-select-trigger");
            if (loadingTrigger) loadingTrigger.textContent = "Завантаження...";
        }

        if (!regionId) {
            resetDistrictsUi();
            return;
        }

        fetch("?ajax_districts=1&region_id=" + encodeURIComponent(regionId))
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var tmp = document.createElement("select");
                tmp.innerHTML = html;

                districtSelect.innerHTML = "";
                var placeholderOpt = document.createElement("option");
                placeholderOpt.value = "";
                placeholderOpt.textContent = "Виберіть район";
                districtSelect.appendChild(placeholderOpt);

                if (districtWrapper) {
                    var options = districtWrapper.querySelector(".custom-options");
                    if (options) options.innerHTML = "";
                }

                tmp.querySelectorAll("option").forEach(function (opt) {
                    if (!opt.value) return;

                    var optionNode = document.createElement("option");
                    optionNode.value = opt.value;
                    optionNode.textContent = opt.textContent;
                    if (selected && selected == opt.value) {
                        optionNode.selected = true;
                    }
                    districtSelect.appendChild(optionNode);

                    if (districtWrapper) {
                        var customNode = document.createElement("span");
                        customNode.className = "custom-option";
                        customNode.dataset.value = opt.value;
                        customNode.textContent = opt.textContent;
                        var customOptions = districtWrapper.querySelector(".custom-options");
                        if (customOptions) customOptions.appendChild(customNode);
                    }
                });

                if (districtWrapper) {
                    var trigger = districtWrapper.querySelector(".custom-select-trigger");
                    if (trigger) {
                        var selectedText = "Виберіть район";
                        if (selected) {
                            var selectedNode = tmp.querySelector("option[value=\"" + selected + "\"]");
                            if (selectedNode) {
                                selectedText = selectedNode.textContent;
                            }
                        }
                        trigger.textContent = selectedText;
                    }
                    if (districtWrapper._bindOptions) districtWrapper._bindOptions();
                }
            })
            .catch(function () {
                districtSelect.innerHTML = "";
                var errOpt = document.createElement("option");
                errOpt.value = "";
                errOpt.textContent = "Помилка завантаження";
                districtSelect.appendChild(errOpt);

                if (districtWrapper) {
                    var trigger = districtWrapper.querySelector(".custom-select-trigger");
                    if (trigger) trigger.textContent = "Помилка завантаження";
                }
            });
    }

    if (regionWrapper) initCustomSelect(regionWrapper);
    if (districtWrapper) initCustomSelect(districtWrapper);

    document.addEventListener("click", function (e) {
        if (!e.target.closest(".custom-select-wrapper")) {
            document.querySelectorAll(".custom-select-wrapper").forEach(function (w) {
                w.classList.remove("open");
                var options = w.querySelector(".custom-options");
                if (options) options.style.display = "none";
            });
        }
    });

    if (regionSelect && districtSelect) {
        if (regionSelect.value) {
            loadDistricts(regionSelect.value, districtSelect.value || null);
        } else {
            resetDistrictsUi();
        }

        regionSelect.addEventListener("change", function () {
            loadDistricts(this.value, null);
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener("click", function (e) {
            e.preventDefault();
            window.location.href = "/kladbsearch.php";
        });
    }
});
</script>
');

View_Add(Page_Down());

View_Out();
View_Clear();
