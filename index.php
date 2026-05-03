<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

if (isset($_GET['ajax_districts']) && !empty($_GET['region_id'])) {
    echo getDistricts((int)$_GET['region_id']);
    exit;
}

function indexTestEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function indexTestRegionOptions(): string
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, 'SELECT idx, title FROM region ORDER BY title');
    $out = '<option value="">Оберіть область</option>';

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $out .= '<option value="' . (int)$row['idx'] . '">' . indexTestEsc((string)$row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

$stats = [
    'graves' => 0,
    'cemeteries' => 0,
    'users' => 0,
    'recent_graves' => 0,
];

$dblink = DbConnect();

$resGraves = mysqli_query($dblink, 'SELECT COUNT(*) AS cnt FROM grave');
if ($resGraves && ($row = mysqli_fetch_assoc($resGraves))) {
    $stats['graves'] = (int)($row['cnt'] ?? 0);
}

$resCemetery = mysqli_query($dblink, 'SELECT COUNT(*) AS cnt FROM cemetery');
if ($resCemetery && ($row = mysqli_fetch_assoc($resCemetery))) {
    $stats['cemeteries'] = (int)($row['cnt'] ?? 0);
}

$resUsers = mysqli_query($dblink, 'SELECT COUNT(*) AS cnt FROM users');
if ($resUsers && ($row = mysqli_fetch_assoc($resUsers))) {
    $stats['users'] = (int)($row['cnt'] ?? 0);
}

$resRecent = mysqli_query($dblink, 'SELECT COUNT(*) AS cnt FROM grave WHERE idtadd >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
if ($resRecent && ($row = mysqli_fetch_assoc($resRecent))) {
    $stats['recent_graves'] = (int)($row['cnt'] ?? 0);
}
mysqli_close($dblink);

View_Clear();
View_Add(Page_Up('Головна'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/index.css?v=14">');

View_Add('
<div class="out out-index-test">
    <main class="itp-page">
        <section class="itp-hero">
            <div class="itp-hero__glows">
                <div class="itp-hero__glow itp-hero__glow--left"></div>
                <div class="itp-hero__glow itp-hero__glow--right"></div>
            </div>

            <div class="itp-hero__content">
                <p class="itp-kicker">ІНФОРМАЦІЙНО ПОШУКОВА СИСТЕМА</p>
                <h1 class="itp-title">Платформа пам\'яті та пошуку поховань</h1>
                <p class="itp-subtitle itp-subtitle--desktop">
                    ІПС «Шана» допомагає швидко знайти дані про поховання, кладовища та пов\'язані картки.
                    Платформа забезпечує швидкий доступ до інформації, зручну навігацію та простий пошук необхідних даних.
                </p>
                <p class="itp-subtitle itp-subtitle--mobile">
                    ІПС «Шана» забезпечує швидкий пошук даних про поховання, кладовища та пов’язані картки із зручною навігацією.
                </p>

                <div class="itp-actions">
                    <a href="/searchx.php" class="itp-btn itp-btn--soft">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h1.5" /><path d="M15 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M20.2 20.2l1.8 1.8" /></svg>
                        <span>Шукати поховання</span>
                    </a>
                    <a href="/searchcem" class="itp-btn itp-btn--soft">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-map-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M11 18l-2 -1l-6 3v-13l6 -3l6 3l6 -3v7.5" /><path d="M9 4v13" /><path d="M15 7v5" /><path d="M15 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M20.2 20.2l1.8 1.8" /></svg>
                        <span>Шукати кладовища</span>
                    </a>
                    <a href="/graveaddform.php" class="itp-btn itp-btn--soft itp-btn--add-grave">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                        <span>Додати поховання</span>
                    </a>
                </div>

                <div class="itp-hero-help">
                    <h2 class="itp-hero-help__title">Потрібна допомога з пошуком?</h2>
                    <p class="itp-hero-help__text">
                        Якщо бачите неточність у даних або потрібна консультація, напишіть нам у підтримку чи відкрийте контакти.
                    </p>
                    <div class="itp-hero-help__actions">
                        <a href="/messenger.php?type=3" class="itp-btn itp-btn--primary">Повідомити баг</a>
                        <a href="/contacts.php" class="itp-btn itp-btn--ghost">Контакти</a>
                    </div>
                </div>

            </div>

            <aside class="itp-search-card">
                <div class="itp-tabs" role="tablist" aria-label="Тип пошуку">
                    <button type="button" class="itp-tab is-active" data-tab="grave">Пошук поховання</button>
                    <button type="button" class="itp-tab" data-tab="cemetery">Пошук кладовища</button>
                </div>

                <div class="itp-panels">
                    <form class="itp-panel is-active" data-panel="grave" action="/searchx.php" method="get">
                        <input type="hidden" name="page" value="1">
                        <div class="itp-panel-body">
                            <div class="itp-row itp-row--stack">
                                <label>
                                    <span>Прізвище</span>
                                    <input type="text" name="surname" autocomplete="off">
                                </label>
                                <label>
                                    <span>Ім\'я</span>
                                    <input type="text" name="name" autocomplete="off">
                                </label>
                                <label>
                                    <span>По батькові</span>
                                    <input type="text" name="patronymic" autocomplete="off">
                                </label>
                            </div>
                            <div class="itp-row itp-row--two">
                                <label>
                                    <span>Дата народження</span>
                                    <input type="date" name="birthdate">
                                </label>
                                <label>
                                    <span>Дата смерті</span>
                                    <input type="date" name="deathdate">
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="itp-submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                            <span>Пошук</span>
                        </button>
                    </form>

                    <form class="itp-panel" data-panel="cemetery" action="/searchcem" method="get">
                        <input type="hidden" name="page" value="1">
                        <div class="itp-panel-body">
                            <div class="itp-row itp-row--stack">
                                <div class="itp-field">
                                    <span>Область</span>
                                    <div class="itp-select-host">
                                        <select id="itp-region" name="region">
                                            ' . indexTestRegionOptions() . '
                                        </select>
                                    </div>
                                </div>
                                <div class="itp-field">
                                    <span>Район</span>
                                    <div class="itp-select-host">
                                        <select id="itp-district" name="district" disabled>
                                            <option value="">Спочатку оберіть область</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="itp-field">
                                    <span>Назва кладовища</span>
                                    <input type="text" name="title" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="itp-panel-info" aria-hidden="true">
                            <p>Оберіть область і район для точнішого результату.</p>
                            <p>Не знаєте повну назву? Введіть частину.</p>
                        </div>
                        <button type="submit" class="itp-submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                            <span>Пошук</span>
                        </button>
                    </form>
                </div>
            </aside>
        </section>

        <section class="itp-metrics">
            <article class="itp-metric">
                <span>Карток поховань</span>
                <b>' . number_format($stats['graves'], 0, '', ' ') . '</b>
                <p>Загальна кількість поховань у базі даних системи.</p>
            </article>
            <article class="itp-metric">
                <span>Кладовищ у базі</span>
                <b>' . number_format($stats['cemeteries'], 0, '', ' ') . '</b>
                <p>Кількість карток кладовищ, доступних для пошуку та перегляду.</p>
            </article>
            <article class="itp-metric">
                <span>Користувачів системи</span>
                <b>' . number_format($stats['users'], 0, '', ' ') . '</b>
                <p>Зареєстровані користувачі, які працюють з платформою.</p>
            </article>
            <article class="itp-metric">
                <span>Додано за 30 днів</span>
                <b>' . number_format($stats['recent_graves'], 0, '', ' ') . '</b>
                <p>Нові картки поховань, які були додані за останній місяць.</p>
            </article>
        </section>

        <section class="itp-cards">
            <header class="itp-section-head">
                <h2>Ключові розділи платформи</h2>
            </header>

            <div class="itp-grid">
                <a class="itp-card" href="/searchx.php">
                    <h3>Пошук поховань</h3>
                    <p class="itp-card-text itp-card-text--desktop">Гнучкі фільтри по ПІБ, датах та додаткових параметрах.</p>
                    <p class="itp-card-text itp-card-text--mobile">Пошук поховань за ПІБ і датами.</p>
                    <span>
                        Відкрити розділ
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M15 16l4 -4" /><path d="M15 8l4 4" /></svg>
                    </span>
                </a>
                <a class="itp-card" href="/searchcem">
                    <h3>База даних кладовищ</h3>
                    <p class="itp-card-text itp-card-text--desktop">Перегляд карток кладовищ із геоданими, схемами та зв\'язаними похованнями.</p>
                    <p class="itp-card-text itp-card-text--mobile">Картки кладовищ із геоданими та похованнями.</p>
                    <span>
                        Перейти до списку
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M15 16l4 -4" /><path d="M15 8l4 4" /></svg>
                    </span>
                </a>
                <a class="itp-card" href="/graveaddform.php">
                    <h3>Додавання поховання</h3>
                    <p class="itp-card-text itp-card-text--desktop">Форма внесення картки з підтримкою фото та структурованих даних.</p>
                    <p class="itp-card-text itp-card-text--mobile">Додавання картки з фото та даними.</p>
                    <span>
                        Додати нову картку
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M15 16l4 -4" /><path d="M15 8l4 4" /></svg>
                    </span>
                </a>
                <a class="itp-card" href="/searchcem/addcemetery">
                    <h3>Додавання кладовища</h3>
                    <p class="itp-card-text itp-card-text--desktop">Створення нових кладовищ з координатами і прив\'язкою до локації.</p>
                    <p class="itp-card-text itp-card-text--mobile">Додавання кладовища з координатами.</p>
                    <span>
                        Створити кладовище
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M15 16l4 -4" /><path d="M15 8l4 4" /></svg>
                    </span>
                </a>
            </div>
        </section>

        <section class="itp-bottom-cta">
            <div class="itp-bottom-cta__content">
                <h2>Повідомити про проблему</h2>
                <p>Помітили баг, помилку в даних або маєте пропозицію щодо функціоналу? Відправте звернення в підтримку або зв\'яжіться з нами через контактний розділ.</p>
                <div class="itp-bottom-cta__meta">
                    <span class="itp-bottom-cta__meta-item">Відповідь протягом 24 годин</span>
                    <span class="itp-bottom-cta__meta-item">Консультації щодо пошуку й додавання даних</span>
                </div>
            </div>
            <div class="itp-bottom-cta__actions">
                <a href="/messenger.php?type=3" class="itp-btn itp-btn--primary">Повідомити баг</a>
                <a href="/contacts.php" class="itp-btn itp-btn--ghost">Контакти</a>
                <a href="/faq.php" class="itp-btn itp-btn--soft">FAQ</a>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll(".itp-tab"));
    var panels = Array.prototype.slice.call(document.querySelectorAll(".itp-panel"));
    if (tabs.length && panels.length) {
        function setTab(name) {
            tabs.forEach(function (tab) {
                tab.classList.toggle("is-active", tab.getAttribute("data-tab") === name);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle("is-active", panel.getAttribute("data-panel") === name);
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener("click", function () {
                var name = tab.getAttribute("data-tab");
                if (!name) return;
                setTab(name);
            });
        });
    }

    var regionSel = document.getElementById("itp-region");
    var districtSel = document.getElementById("itp-district");
    if (!regionSel || !districtSel) {
        return;
    }

    var placeholderById = {
        "itp-region": "Оберіть область",
        "itp-district": "Оберіть район"
    };

    function closeAllCustomSelects(exceptWrapper) {
        document.querySelectorAll(".custom-select-wrapper.open").forEach(function (wrapper) {
            if (exceptWrapper && wrapper === exceptWrapper) return;
            wrapper.classList.remove("open");
            wrapper.classList.remove("open-up");
            var optionsBox = wrapper.querySelector(".custom-options");
            if (optionsBox) optionsBox.style.display = "none";
        });
    }

    function getCustomWrapper(selectEl) {
        return selectEl.parentNode.querySelector(".custom-select-wrapper[data-select-id=\"" + selectEl.id + "\"]");
    }

    function ensureCustomSelect(selectEl) {
        var wrapper = getCustomWrapper(selectEl);
        if (wrapper) return wrapper;

        wrapper = document.createElement("div");
        wrapper.className = "custom-select-wrapper";
        wrapper.dataset.selectId = selectEl.id;

        var trigger = document.createElement("div");
        trigger.className = "custom-select-trigger";

        var optionsBox = document.createElement("div");
        optionsBox.className = "custom-options";

        trigger.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (selectEl.disabled) return;
            var willOpen = !wrapper.classList.contains("open");
            closeAllCustomSelects(wrapper);
            if (willOpen) {
                wrapper.classList.remove("open-up");
                var triggerRect = trigger.getBoundingClientRect();
                var viewportSpaceBelow = window.innerHeight - triggerRect.bottom;
                var optionsHeight = Math.min(optionsBox.scrollHeight || 220, 220) + 10;
                if (viewportSpaceBelow < optionsHeight) {
                    wrapper.classList.add("open-up");
                }
            } else {
                wrapper.classList.remove("open-up");
            }
            wrapper.classList.toggle("open", willOpen);
            optionsBox.style.display = willOpen ? "flex" : "none";
        });

        wrapper.addEventListener("mousedown", function (event) {
            event.stopPropagation();
        });

        wrapper.appendChild(trigger);
        wrapper.appendChild(optionsBox);
        selectEl.classList.add("itp-select-native-hidden");
        if (selectEl.nextSibling) {
            selectEl.parentNode.insertBefore(wrapper, selectEl.nextSibling);
        } else {
            selectEl.parentNode.appendChild(wrapper);
        }
        return wrapper;
    }

    function syncCustomSelect(selectEl) {
        var wrapper = ensureCustomSelect(selectEl);
        var trigger = wrapper.querySelector(".custom-select-trigger");
        var optionsBox = wrapper.querySelector(".custom-options");
        var options = Array.from(selectEl.options || []);
        var placeholder = placeholderById[selectEl.id] || "Оберіть";

        optionsBox.innerHTML = "";
        var selectedOption = options.find(function (opt) {
            return opt.value !== "" && opt.value === selectEl.value;
        });

        var triggerText = placeholder;
        if (selectedOption) triggerText = selectedOption.textContent;
        else if (options[0] && options[0].textContent) triggerText = options[0].textContent;
        trigger.textContent = triggerText;

        options.forEach(function (opt) {
            var optionNode = document.createElement("span");
            optionNode.textContent = opt.textContent;
            if (!opt.value) {
                optionNode.className = "custom-option disabled";
                optionsBox.appendChild(optionNode);
                return;
            }

            optionNode.className = "custom-option";
            if (opt.value === selectEl.value) optionNode.classList.add("is-selected");
            optionNode.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (selectEl.disabled) return;
                selectEl.value = opt.value;
                syncCustomSelect(selectEl);
                closeAllCustomSelects();
                var changeEvent;
                if (typeof Event === "function") {
                    changeEvent = new Event("change", { bubbles: true });
                } else {
                    changeEvent = document.createEvent("Event");
                    changeEvent.initEvent("change", true, true);
                }
                selectEl.dispatchEvent(changeEvent);
            });
            optionsBox.appendChild(optionNode);
        });

        wrapper.classList.toggle("disabled", !!selectEl.disabled);
    }

    function resetDistrict() {
        districtSel.innerHTML = "<option value=\"\">Спочатку оберіть область</option>";
        districtSel.disabled = true;
        syncCustomSelect(districtSel);
    }

    function loadDistricts(regionId) {
        districtSel.disabled = true;
        districtSel.innerHTML = "<option value=\"\">Завантаження...</option>";
        syncCustomSelect(districtSel);

        var ajaxEndpoint = window.location.pathname && window.location.pathname !== "/" ? window.location.pathname : "/index.php";
        fetch(ajaxEndpoint + "?ajax_districts=1&region_id=" + encodeURIComponent(regionId), { credentials: "same-origin" })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                districtSel.innerHTML = html;
                districtSel.disabled = false;
                syncCustomSelect(districtSel);
            })
            .catch(function () {
                districtSel.innerHTML = "<option value=\"\">Помилка завантаження</option>";
                districtSel.disabled = true;
                syncCustomSelect(districtSel);
            });
    }

    regionSel.addEventListener("change", function () {
        if (!regionSel.value) {
            resetDistrict();
            return;
        }
        loadDistricts(regionSel.value);
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".custom-select-wrapper")) {
            closeAllCustomSelects();
        }
    });

    function setupIosToolbarWatcher() {
        var ua = navigator.userAgent || "";
        var isIOS = /iP(ad|hone|od)/.test(ua);
        if (!isIOS) return;
        var isSafari = /WebKit/.test(ua) && !/CriOS|FxiOS|OPiOS|EdgiOS/.test(ua);
        if (!isSafari) return;

        var body = document.body;
        if (!body) return;

        var supportsVV = window.visualViewport && typeof window.visualViewport.height === "number";
        var maxInner = window.innerHeight;
        var minInner = window.innerHeight;

        function update() {
            var toolbarHidden = false;
            if (supportsVV) {
                var diff = Math.abs(window.innerHeight - window.visualViewport.height);
                toolbarHidden = diff < 2;
            } else {
                var h = window.innerHeight;
                if (h > maxInner) maxInner = h;
                if (h < minInner) minInner = h;
                toolbarHidden = (maxInner - minInner) > 40 && (maxInner - h) < 2;
            }
            body.classList.toggle("itp-ios-toolbar-hidden", toolbarHidden);
        }

        window.addEventListener("resize", update, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener("resize", update);
            window.visualViewport.addEventListener("scroll", update);
        }
        window.addEventListener("scroll", update, { passive: true });
        update();
    }

    syncCustomSelect(regionSel);
    syncCustomSelect(districtSel);
    setupIosToolbarWatcher();
})();
</script>
');

View_Add(Page_Down());
View_Out();
View_Clear();
