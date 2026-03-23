<?php
require_once "function.php";
require_once "function_vra.php";

$cp = $_GET['page'] ?? 1;
$perpage = 5;

$q           = trim($_GET['q'] ?? '');
$surname     = $_GET['surname'] ?? '';
$name        = $_GET['name'] ?? '';
$patronymic  = $_GET['patronymic'] ?? '';
$region      = $_GET['region'] ?? '';
$district    = $_GET['district'] ?? '';
$idxkladb    = $_GET['idxkladb'] ?? '';

$dblink = DbConnect();
$rows = [];
$sql = "
SELECT grave.*,
       district.title AS district_name,
       region.title AS region_name
FROM grave
LEFT JOIN cemetery ON cemetery.idx = grave.idxkladb
LEFT JOIN district ON district.idx = cemetery.district
LEFT JOIN region ON region.idx = district.region
WHERE 1 = 1
  AND LOWER(COALESCE(grave.moderation_status, 'pending')) <> 'rejected'
";

// ПІБ (загальний запит)
if ($q !== '') {
    $tokens = preg_split('/\s+/', $q);
    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        $safeToken = mysqli_real_escape_string($dblink, $token);
        $sql .= ' AND (lname LIKE "%' . $safeToken . '%" OR fname LIKE "%' . $safeToken . '%" OR mname LIKE "%' . $safeToken . '%")';
    }
} else {
    if ($surname !== '') {
        $safeSurname = mysqli_real_escape_string($dblink, $surname);
        $sql .= ' AND (lname LIKE "%' . $safeSurname . '%")';
    }

    if ($name !== '') {
        $safeName = mysqli_real_escape_string($dblink, $name);
        $sql .= ' AND (fname LIKE "%' . $safeName . '%")';
    }

    if ($patronymic !== '') {
        $safePatronymic = mysqli_real_escape_string($dblink, $patronymic);
        $sql .= ' AND (mname LIKE "%' . $safePatronymic . '%")';
    }
}

// Місце розташування / кладовище
if ($region !== '') {
    $sql .= ' AND region.idx = ' . (int)$region;
}

if ($district !== '') {
    $sql .= ' AND district.idx = ' . (int)$district;
}

if ($idxkladb !== '') {
    $sql .= ' AND grave.idxkladb = ' . (int)$idxkladb;
}

$res = mysqli_query($dblink, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $rows[] = $row;
}

// Количество результатов
$cout = count($rows);

$search_line = "";

// Данные для селектів фільтра
// Області
$regionOptions = '<option value="">Виберіть область</option>';
$regionCustomOptions = '';
$regionTriggerText = 'Виберіть область';
$resRegions = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
if ($resRegions) {
    while ($r = mysqli_fetch_assoc($resRegions)) {
        $id = (int)$r['idx'];
        $title = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
        $sel = ($region !== '' && (int)$region === $id) ? ' selected' : '';
        $regionOptions .= '<option value="' . $id . '"' . $sel . '>' . $title . '</option>';
        $regionCustomOptions .= '<span class="custom-option" data-value="' . $id . '">' . $title . '</span>';
        if ($sel) {
            $regionTriggerText = $title;
        }
    }
}

// Райони
$districtOptions = '<option value="">Спочатку виберіть область</option>';
$districtCustomOptions = '';
$districtTriggerText = 'Спочатку виберіть область';
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
            $title = htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8');
            $sel = ($district !== '' && (int)$district === $id) ? ' selected' : '';
            $districtOptions .= '<option value="' . $id . '"' . $sel . '>' . $title . '</option>';
            $districtCustomOptions .= '<span class="custom-option" data-value="' . $id . '">' . $title . '</span>';
            if ($sel) {
                $districtTriggerText = $title;
            }
        }
    }
}

// Кладовища
$cemeteryOptions = '<option value="">Спочатку виберіть район</option>';
$cemeteryCustomOptions = '';
$cemeteryTriggerText = 'Спочатку виберіть район';
if ($district !== '') {
    $cemeterySelectHtml = CemeterySelect((int)$district, $idxkladb !== '' ? (int)$idxkladb : null);
    $cemeteryOptions = $cemeterySelectHtml;
    
    // Parse cemetery options for custom select
    $cemeteryTriggerText = 'Виберіть кладовище';
    preg_match_all('/<option value="([^"]*)"([^>]*)>([^<]*)<\/option>/', $cemeterySelectHtml, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $val = $match[1];
        $text = $match[3];
        $selected = strpos($match[2], 'selected') !== false;
        if ($val !== '') {
            $cemeteryCustomOptions .= '<span class="custom-option" data-value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
            if ($selected || ($idxkladb !== '' && (int)$idxkladb === (int)$val)) {
                $cemeteryTriggerText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

$searchParts = [];
if ($q !== '') {
    $searchParts[] = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');
} else {
    $fioParts = array_filter([$surname, $name, $patronymic], static function ($part) {
        return $part !== '';
    });
    if (!empty($fioParts)) {
        $searchParts[] = htmlspecialchars(implode(' ', $fioParts), ENT_QUOTES, 'UTF-8');
    }
}
if ($region !== '') {
    $searchParts[] = 'Область: ' . htmlspecialchars($regionTriggerText, ENT_QUOTES, 'UTF-8');
}
if ($district !== '') {
    $searchParts[] = 'Район: ' . htmlspecialchars($districtTriggerText, ENT_QUOTES, 'UTF-8');
}
if ($idxkladb !== '') {
    $searchParts[] = 'Кладовище: ' . htmlspecialchars($cemeteryTriggerText, ENT_QUOTES, 'UTF-8');
}
$search_line = !empty($searchParts) ? implode('; ', $searchParts) : '—';
$searchParamsMobileClass = $search_line === '—' ? ' search-badge--mobile-hidden' : '';


View_Clear();
View_Add(Page_Up('Результати пошуку'));
View_Add(Menu_Up());
View_Add('<style>@import url(\'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap\'); .out-xsearch, .out-xsearch * { font-family: "Manrope", "Segoe UI", Tahoma, sans-serif !important; } .menu-up-new, .menu-up-new * { font-family: "Segoe UI", Tahoma, sans-serif !important; }</style>');
View_Add('<div class="out-xsearch">');

// Контейнер поиска
View_Add('<div class="search-container">');
View_Add('<div class="search-out search-toolbar">');

View_Add('<div class="search-badges">');
View_Add('<div class="search-badge search-badge--params' . $searchParamsMobileClass . '">Пошук за параметрами: ' . $search_line . '</div>');
View_Add('<div class="search-badge">Всього публікацій: <strong>' . $cout . '</strong></div>');
View_Add('</div>');

View_Add('<a href="graveaddform.php" class="searchklb-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-add-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>Додати поховання</a>');

View_Add('<div class="search-toolbar-right">');
View_Add('<form class="search-form" action="/searchx.php" method="get">');
if ($region !== '') {
    View_Add('<input type="hidden" name="region" value="' . (int)$region . '">');
}
if ($district !== '') {
    View_Add('<input type="hidden" name="district" value="' . (int)$district . '">');
}
if ($idxkladb !== '') {
    View_Add('<input type="hidden" name="idxkladb" value="' . (int)$idxkladb . '">');
}
View_Add('<input type="hidden" name="page" value="1">');
View_Add('<div class="search-input-wrap">');
View_Add('<input type="text" name="q" value="' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '" placeholder="Пошук за прізвищем / ім`ям / по батькові" class="search-inputx" autocomplete="off">');
View_Add('<button type="submit" class="search-submit-btn" aria-label="Пошук"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>');
View_Add('</div>');
View_Add('</form>');
View_Add('<div class="filter-dropdown">');
View_Add('<button type="button" class="filter-btnx" id="filterToggle"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="filter-btn-icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227" /></svg>Фільтр</button>');

// Панель розширеного фільтру
View_Add('
    <div class="filter-panel" id="filterPanel" aria-hidden="true">
        <form class="filter-form" action="/searchx.php" method="get">
            <input type="hidden" name="page" value="1">
            ' . ($q !== '' ? '<input type="hidden" name="q" value="' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '">' : '') . '
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
                            <div class="custom-select-trigger but">' . htmlspecialchars($districtTriggerText, ENT_QUOTES, 'UTF-8') . '</div>
                            <div class="custom-options">' . $districtCustomOptions . '</div>
                        </div>
                        <label class="label-active">Район</label>
                    </div>
                </div>
                <div class="filter-field filter-field--full">
                    <div class="input-container">
                        <select name="idxkladb" id="filter-cemetery" class="login-Input" style="display:none;">
                            ' . $cemeteryOptions . '
                        </select>
                        <div class="custom-select-wrapper" id="cemetery-wrapper">
                            <div class="custom-select-trigger">' . htmlspecialchars($cemeteryTriggerText, ENT_QUOTES, 'UTF-8') . '</div>
                            <div class="custom-options">' . $cemeteryCustomOptions . '</div>
                        </div>
                        <label class="label-active">Кладовище</label>
                    </div>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="filter-apply">Застосувати</button>
                <button type="reset" class="filter-reset">Скинути</button>
            </div>
        </form>
    </div>
');
View_Add('</div>');
View_Add('</div>');

View_Add('</div>');

// Карточки с пагинацией
View_Add('<div class="cards-out">');

if ($cout === 0) {
    View_Add('<div class="no-results-wrap"><div class="no-results">Немає публікацій за вашим запитом</div></div>');
} else {
    $offset = ($cp - 1) * $perpage;
    $rows_page = array_slice($rows, $offset, $perpage);

    foreach ($rows_page as $c) {
        View_Add(Cardsx(
            $c['idx'],
            $c['lname'],
            $c['fname'],
            $c['mname'],
            $c['dt1'],
            $c['dt2'],
            $c['photo1'],
            $c['district_name'],
            $c['region_name'],
            $c['moderation_status'] ?? ''
        ));

    }
}

View_Add('</div>' . xbr);
View_Add('</div>' . xbr);

// Пагинация
if ($cout > 0) {
    View_Add('<div class="paginator-out">');
    View_Add(Paginatex::Showx($cp, $cout, $perpage));
    View_Add('</div>' . xbr);
}
View_Add('</div>');


View_Add('</div>' . xbr);

View_Add('
<script>
document.addEventListener("DOMContentLoaded", function () {
    var filterToggle = document.getElementById("filterToggle");
    var filterPanel = document.getElementById("filterPanel");
    if (!filterToggle || !filterPanel) return;

    var closeBtn = filterPanel.querySelector(".filter-close");
    var resetBtn = filterPanel.querySelector(".filter-reset");
    var filterForm = filterPanel.querySelector(".filter-form");

    // Елементи фільтра місця
    var regionSelect   = document.getElementById("filter-region");
    var districtSelect = document.getElementById("filter-district");
    var cemeterySelect = document.getElementById("filter-cemetery");
    
    var regionWrapper = document.getElementById("region-wrapper");
    var districtWrapper = document.getElementById("district-wrapper");
    var cemeteryWrapper = document.getElementById("cemetery-wrapper");
    var mobileFilterMedia = window.matchMedia("(max-width: 768px)");

    function getCustomOptions(wrapper) {
        if (!wrapper) return null;
        if (wrapper._portalOptions && wrapper._portalOptions.isConnected) {
            return wrapper._portalOptions;
        }

        var options = wrapper.querySelector(".custom-options");
        if (options) {
            wrapper._portalOptions = options;
        }

        return options;
    }

    function restoreCustomOptions(wrapper) {
        var options = getCustomOptions(wrapper);
        if (!wrapper || !options) return null;

        if (options.parentNode !== wrapper) {
            wrapper.appendChild(options);
        }

        options.classList.remove("custom-options--portal");
        wrapper._portalOptions = options;
        return options;
    }

    function isCustomSelectTarget(target) {
        return !!(target && (target.closest(".custom-select-wrapper") || target.closest(".custom-options--portal")));
    }

    function cleanupCustomOptions(wrapper) {
        if (!wrapper) return;
        var options = getCustomOptions(wrapper);
        if (!options) return;
        wrapper.classList.remove("open-upward");
        wrapper.classList.remove("custom-select-wrapper--floating");
        options.classList.remove("custom-options--portal");
        options.style.position = "";
        options.style.left = "";
        options.style.right = "";
        options.style.top = "";
        options.style.bottom = "";
        options.style.width = "";
        options.style.maxHeight = "";
        options.style.zIndex = "";
    }

    function closeCustomSelect(wrapper) {
        if (!wrapper) return;
        var options = getCustomOptions(wrapper);
        wrapper.classList.remove("open");
        cleanupCustomOptions(wrapper);
        if (options) {
            options.style.display = "none";
            restoreCustomOptions(wrapper);
        }
    }

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll(".custom-select-wrapper").forEach(function (wrapper) {
            if (wrapper !== exceptWrapper) {
                closeCustomSelect(wrapper);
            }
        });
    }

    function positionCustomOptions(wrapper) {
        if (!wrapper) return;
        var options = getCustomOptions(wrapper);
        var trigger = wrapper.querySelector(".custom-select-trigger");
        if (!options || !trigger) return;

        if (!mobileFilterMedia.matches) {
            restoreCustomOptions(wrapper);
            cleanupCustomOptions(wrapper);
            return;
        }

        var triggerRect = trigger.getBoundingClientRect();
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        var sideGap = 12;
        var bottomReserve = 92;
        var availableBelow = viewportHeight - triggerRect.bottom - bottomReserve;
        var availableAbove = triggerRect.top - 18;
        var openUpward = availableBelow < 180 && availableAbove > availableBelow;
        var maxHeight = Math.max(132, Math.min(240, openUpward ? (availableAbove - 8) : (availableBelow - 8)));
        var width = Math.min(triggerRect.width, viewportWidth - sideGap * 2);
        var left = Math.min(Math.max(sideGap, triggerRect.left), viewportWidth - sideGap - width);

        if (options.parentNode !== document.body) {
            document.body.appendChild(options);
        }

        options.classList.add("custom-options--portal");

        options.style.position = "fixed";
        options.style.left = left + "px";
        options.style.right = "auto";
        options.style.width = width + "px";
        options.style.maxHeight = maxHeight + "px";
        options.style.zIndex = "10050";

        if (openUpward) {
            options.style.top = "auto";
            options.style.bottom = Math.max(12, viewportHeight - triggerRect.top + 6) + "px";
            wrapper.classList.add("open-upward");
        } else {
            options.style.bottom = "auto";
            options.style.top = Math.max(12, triggerRect.bottom + 6) + "px";
            wrapper.classList.remove("open-upward");
        }

        wrapper.classList.add("custom-select-wrapper--floating");
    }

    function refreshOpenCustomOptions() {
        document.querySelectorAll(".custom-select-wrapper.open").forEach(function (wrapper) {
            positionCustomOptions(wrapper);
        });
    }

    function openPanel() {
        filterPanel.classList.add("open");
        filterPanel.setAttribute("aria-hidden", "false");
        filterToggle.classList.add("active");
    }

    function closePanel() {
        closeAllCustomSelects();
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

    // Закрытие при клике вне панели, но не при клике на кастомные селекты
    document.addEventListener("click", function (e) {
        if (!filterPanel.contains(e.target) && !filterToggle.contains(e.target)) {
            if (!isCustomSelectTarget(e.target)) {
                closePanel();
            }
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closePanel();
        }
    });

    // Инициализация кастомных селектов
    function initCustomSelect(wrapper) {
        if (!wrapper) return;
        var trigger = wrapper.querySelector(".custom-select-trigger");
        var options = getCustomOptions(wrapper);
        var select = wrapper.previousElementSibling;
        
        if (!trigger || !options || !select) return;
        wrapper._portalOptions = options;

        trigger.addEventListener("click", function (e) {
            e.stopPropagation();
            
            closeAllCustomSelects(wrapper);

            wrapper.classList.toggle("open");
            if (wrapper.classList.contains("open")) {
                options.style.display = "flex";
                positionCustomOptions(wrapper);
            } else {
                closeCustomSelect(wrapper);
            }
        });

        function bindOptions() {
            options.querySelectorAll(".custom-option").forEach(function(opt) {
                opt.onclick = function(e) {
                    e.stopPropagation();
                    trigger.textContent = opt.textContent;
                    select.value = opt.dataset.value || "";
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    closeCustomSelect(wrapper);
                    // НЕ закрываем фильтр при выборе
                };
            });
        }

        bindOptions();
        wrapper._bindOptions = bindOptions;
    }

    // Инициализируем все кастомные селекты
    if (regionWrapper) initCustomSelect(regionWrapper);
    if (districtWrapper) initCustomSelect(districtWrapper);
    if (cemeteryWrapper) initCustomSelect(cemeteryWrapper);

    // Закрытие кастомных селектов при клике вне
    document.addEventListener("click", function(e) {
        if (!isCustomSelectTarget(e.target)) {
            closeAllCustomSelects();
        }
    });

    window.addEventListener("resize", refreshOpenCustomOptions);
    window.addEventListener("scroll", refreshOpenCustomOptions, { passive: true });
    if (filterForm) {
        filterForm.addEventListener("scroll", refreshOpenCustomOptions, { passive: true });
    }

    // Обновление кастомного селекта при изменении обычного
    function updateCustomSelect(select, wrapper) {
        if (!select || !wrapper) return;
        var trigger = wrapper.querySelector(".custom-select-trigger");
        var options = wrapper.querySelector(".custom-options");
        var selectedOption = select.options[select.selectedIndex];
        
        if (trigger) {
            trigger.textContent = selectedOption ? selectedOption.textContent : "Виберіть...";
        }
        
        if (options && wrapper._bindOptions) {
            wrapper._bindOptions();
        }
    }

    // Робота з селектами області / району / кладовища
    if (regionSelect && districtSelect) {
        function resetDistricts() {
            districtSelect.innerHTML = "";
            var opt = document.createElement("option");
            opt.value = "";
            opt.textContent = "Спочатку виберіть область";
            districtSelect.appendChild(opt);
            
            if (districtWrapper) {
                var trigger = districtWrapper.querySelector(".custom-select-trigger");
                var options = districtWrapper.querySelector(".custom-options");
                if (trigger) trigger.textContent = "Спочатку виберіть область";
                if (options) options.innerHTML = "";
            }

            if (cemeterySelect) {
                cemeterySelect.innerHTML = "";
                var copt = document.createElement("option");
                copt.value = "";
                copt.textContent = "Спочатку виберіть район";
                cemeterySelect.appendChild(copt);
            }
            
            if (cemeteryWrapper) {
                var trigger = cemeteryWrapper.querySelector(".custom-select-trigger");
                var options = cemeteryWrapper.querySelector(".custom-options");
                if (trigger) trigger.textContent = "Спочатку виберіть район";
                if (options) options.innerHTML = "";
                if (cemeteryWrapper._bindOptions) cemeteryWrapper._bindOptions();
            }
        }

        function loadDistricts(regionId, selected) {
            districtSelect.innerHTML = "";
            var loadingOpt = document.createElement("option");
            loadingOpt.value = "";
            loadingOpt.textContent = "Завантаження...";
            districtSelect.appendChild(loadingOpt);
            
            if (districtWrapper) {
                var trigger = districtWrapper.querySelector(".custom-select-trigger");
                if (trigger) trigger.textContent = "Завантаження...";
            }

            if (!regionId) {
                resetDistricts();
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
                    
                    var customOptions = "";
                    if (districtWrapper) {
                        var options = districtWrapper.querySelector(".custom-options");
                        if (options) options.innerHTML = "";
                    }

                    tmp.querySelectorAll("option").forEach(function (opt) {
                        if (!opt.value) return;
                        var o = document.createElement("option");
                        o.value = opt.value;
                        o.textContent = opt.textContent;
                        if (selected && selected == opt.value) {
                            o.selected = true;
                        }
                        districtSelect.appendChild(o);
                        
                        if (districtWrapper) {
                            var span = document.createElement("span");
                            span.className = "custom-option";
                            span.dataset.value = opt.value;
                            span.textContent = opt.textContent;
                            var options = districtWrapper.querySelector(".custom-options");
                            if (options) options.appendChild(span);
                        }
                    });
                    
                    if (districtWrapper) {
                        var trigger = districtWrapper.querySelector(".custom-select-trigger");
                        if (trigger) trigger.textContent = selected ? tmp.querySelector("option[value=\"" + selected + "\"]")?.textContent || "Виберіть район" : "Виберіть район";
                        if (districtWrapper._bindOptions) districtWrapper._bindOptions();
                    }

                    // Якщо змінили район, скинемо кладовище
                    if (cemeterySelect && !selected) {
                        cemeterySelect.innerHTML = "";
                        var copt = document.createElement("option");
                        copt.value = "";
                        copt.textContent = "Спочатку виберіть район";
                        cemeterySelect.appendChild(copt);
                    }
                    
                    if (cemeteryWrapper && !selected) {
                        var trigger = cemeteryWrapper.querySelector(".custom-select-trigger");
                        var options = cemeteryWrapper.querySelector(".custom-options");
                        if (trigger) trigger.textContent = "Спочатку виберіть район";
                        if (options) options.innerHTML = "";
                        if (cemeteryWrapper._bindOptions) cemeteryWrapper._bindOptions();
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

        // Ініціалізація при завантаженні
        if (regionSelect.value) {
            loadDistricts(regionSelect.value, districtSelect.value || null);
        } else {
            resetDistricts();
        }

        regionSelect.addEventListener("change", function () {
            loadDistricts(this.value, null);
        });
    }

    if (districtSelect && cemeterySelect) {
        function loadCemeteries(districtId, selected) {
            cemeterySelect.innerHTML = "";
            var placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = districtId ? "Виберіть кладовище" : "Спочатку виберіть район";
            cemeterySelect.appendChild(placeholder);
            
            if (cemeteryWrapper) {
                var trigger = cemeteryWrapper.querySelector(".custom-select-trigger");
                if (trigger) trigger.textContent = districtId ? "Завантаження..." : "Спочатку виберіть район";
            }

            if (!districtId) {
                if (cemeteryWrapper) {
                    var options = cemeteryWrapper.querySelector(".custom-options");
                    if (options) options.innerHTML = "";
                    if (cemeteryWrapper._bindOptions) cemeteryWrapper._bindOptions();
                }
                return;
            }

            fetch("?ajax_cemeteries=1&district_id=" + encodeURIComponent(districtId))
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var tmp = document.createElement("select");
                    tmp.innerHTML = html;
                    
                    if (cemeteryWrapper) {
                        var options = cemeteryWrapper.querySelector(".custom-options");
                        if (options) options.innerHTML = "";
                    }
                    
                    tmp.querySelectorAll("option").forEach(function (opt) {
                        if (!opt.value) return;
                        var o = document.createElement("option");
                        o.value = opt.value;
                        o.textContent = opt.textContent;
                        if (selected && selected == opt.value) {
                            o.selected = true;
                        }
                        cemeterySelect.appendChild(o);
                        
                        if (cemeteryWrapper) {
                            var span = document.createElement("span");
                            span.className = "custom-option";
                            span.dataset.value = opt.value;
                            span.textContent = opt.textContent;
                            var options = cemeteryWrapper.querySelector(".custom-options");
                            if (options) options.appendChild(span);
                        }
                    });
                    
                    if (cemeteryWrapper) {
                        var trigger = cemeteryWrapper.querySelector(".custom-select-trigger");
                        if (trigger) trigger.textContent = selected ? tmp.querySelector("option[value=\"" + selected + "\"]")?.textContent || "Виберіть кладовище" : "Виберіть кладовище";
                        if (cemeteryWrapper._bindOptions) cemeteryWrapper._bindOptions();
                    }
                })
                .catch(function () {
                    cemeterySelect.innerHTML = "";
                    var err = document.createElement("option");
                    err.value = "";
                    err.textContent = "Помилка завантаження";
                    cemeterySelect.appendChild(err);
                    
                    if (cemeteryWrapper) {
                        var trigger = cemeteryWrapper.querySelector(".custom-select-trigger");
                        if (trigger) trigger.textContent = "Помилка завантаження";
                    }
                });
        }

        // Ініціалізація при завантаженні
        if (districtSelect.value) {
            loadCemeteries(districtSelect.value, cemeterySelect.value || null);
        }

        districtSelect.addEventListener("change", function () {
            loadCemeteries(this.value, null);
        });
    }
    
    // Обработка кнопки сброса
    if (resetBtn) {
        resetBtn.addEventListener("click", function(e) {
            e.preventDefault();
            
            // Сбрасываем все селекты
            if (regionSelect) {
                regionSelect.value = "";
                if (regionWrapper) {
                    var trigger = regionWrapper.querySelector(".custom-select-trigger");
                    if (trigger) trigger.textContent = "Виберіть область";
                }
            }
            
            if (districtSelect) {
                districtSelect.innerHTML = "";
                var opt = document.createElement("option");
                opt.value = "";
                opt.textContent = "Спочатку виберіть область";
                districtSelect.appendChild(opt);
                if (districtWrapper) {
                    var trigger = districtWrapper.querySelector(".custom-select-trigger");
                    var options = districtWrapper.querySelector(".custom-options");
                    if (trigger) trigger.textContent = "Спочатку виберіть область";
                    if (options) options.innerHTML = "";
                }
            }
            
            if (cemeterySelect) {
                cemeterySelect.innerHTML = "";
                var copt = document.createElement("option");
                copt.value = "";
                copt.textContent = "Спочатку виберіть район";
                cemeterySelect.appendChild(copt);
                if (cemeteryWrapper) {
                    var trigger = cemeteryWrapper.querySelector(".custom-select-trigger");
                    var options = cemeteryWrapper.querySelector(".custom-options");
                    if (trigger) trigger.textContent = "Спочатку виберіть район";
                    if (options) options.innerHTML = "";
                    if (cemeteryWrapper._bindOptions) cemeteryWrapper._bindOptions();
                }
            }
            
            // Перезагружаем страницу без параметров фильтра
            window.location.href = "/searchx.php";
        });
    }
});
</script>
');

View_Add(Page_Down());

View_Out();
View_Clear();
