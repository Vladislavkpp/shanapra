<?php
/**
 * @var $md
 * @var $buf
 */

require_once $_SERVER['DOCUMENT_ROOT'] ."/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] ."/classes/chats.php";
require_once $_SERVER['DOCUMENT_ROOT'] ."/roles.php";

$dblink = DbConnect();

// Обработка отправки заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
        $_SESSION['message'] = 'Будь ласка, увійдіть в систему для оформлення замовлення';
        $_SESSION['messageType'] = 'error';
        header('Location: /auth.php');
        exit;
    }
    
    $cleaner_id = (int)($_POST['cleaner_id'] ?? 0);
    $client_id = (int)$_SESSION['uzver'];
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $cemetery_id = !empty($_POST['cemetery_id']) ? (int)$_POST['cemetery_id'] : null;
    $quarter = !empty($_POST['quarter']) ? trim($_POST['quarter']) : '';
    $row = !empty($_POST['row']) ? trim($_POST['row']) : '';
    $place = !empty($_POST['place']) ? trim($_POST['place']) : '';
    
    // Формируем строку "Кладовище та ділянка"
    $cemetery_place = '';
    if ($cemetery_id) {
        $cemRes = mysqli_query($dblink, "SELECT title FROM cemetery WHERE idx = $cemetery_id LIMIT 1");
        if ($cemRow = mysqli_fetch_assoc($cemRes)) {
            $cemetery_place = htmlspecialchars($cemRow['title']);
            if ($quarter || $row || $place) {
                $parts = [];
                if ($quarter) $parts[] = 'Квартал: ' . $quarter;
                if ($row) $parts[] = 'Ряд: ' . $row;
                if ($place) $parts[] = 'Місце: ' . $place;
                $cemetery_place .= ' (' . implode(', ', $parts) . ')';
            }
        }
    }
    
    $preferred_date = trim($_POST['preferred_date'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $selected_services = isset($_POST['selected_services']) && is_array($_POST['selected_services']) 
        ? array_map('intval', $_POST['selected_services']) 
        : [];
    $approximate_price = trim($_POST['approximate_price'] ?? '');
    
    // Получаем email клиента
    $userRes = mysqli_query($dblink, "SELECT email FROM users WHERE idx = $client_id LIMIT 1");
    $userData = mysqli_fetch_assoc($userRes);
    $client_email = $userData['email'] ?? '';
    
    if ($cleaner_id <= 0) {
        $_SESSION['message'] = 'Помилка: не обрано прибиральника';
        $_SESSION['messageType'] = 'error';
        header('Location: /clean-cemeteries.php');
        exit;
    }
    
    // Создаем чат type=2 (рабочий чат)
    $chats = new Chats($dblink);
    $chat_idx = $chats->createChat($client_id, $cleaner_id, 2);
    
    // Формируем JSON выбранных услуг
    $services_json = json_encode($selected_services);
    
    // Сохраняем заказ
    $stmt = mysqli_prepare($dblink, "
        INSERT INTO cleaner_orders (
            cleaner_id, client_id, client_name, client_phone, client_email,
            cemetery_place, preferred_date, comment, selected_services_json,
            approximate_price, chat_idx, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $preferred_date_db = !empty($preferred_date) ? $preferred_date : null;
    mysqli_stmt_bind_param(
        $stmt, 
        'iissssssssi',
        $cleaner_id, $client_id, $customer_name, $customer_phone, $client_email,
        $cemetery_place, $preferred_date_db, $comment, $services_json,
        $approximate_price, $chat_idx
    );
    
    mysqli_stmt_execute($stmt);
    $order_id = mysqli_insert_id($dblink);
    mysqli_stmt_close($stmt);
    
    // Сохраняем связи услуг с заказом
    if (!empty($selected_services)) {
        $stmt = mysqli_prepare($dblink, "INSERT INTO cleaner_order_services (order_id, service_id) VALUES (?, ?)");
        foreach ($selected_services as $service_id) {
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $service_id);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }
    
    // Сохраняем данные для попапа
    $_SESSION['order_success'] = true;
    $_SESSION['order_chat_idx'] = $chat_idx;
    $_SESSION['order_cleaner_id'] = $cleaner_id;
    header('Location: /clean-cemeteries.php?order_success=1');
    exit;
}

// Получение фильтров
$filter_region = isset($_GET['region']) ? (int)$_GET['region'] : 0;
$filter_district = isset($_GET['district']) ? (int)$_GET['district'] : 0;
$filter_cemetery = isset($_GET['cemetery']) ? (int)$_GET['cemetery'] : 0;

// Получение списка регионов для фильтра
$regions = [];
$regRes = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title ASC");
while ($r = mysqli_fetch_assoc($regRes)) {
    $regions[] = $r;
}

// Получение районов для фильтра (если выбрана область)
$districts = [];
if ($filter_region > 0) {
    $distRes = mysqli_query($dblink, "SELECT idx, title FROM district WHERE region = $filter_region ORDER BY title ASC");
    while ($d = mysqli_fetch_assoc($distRes)) {
        $districts[] = $d;
    }
}

// Получение кладбищ для фильтра (если выбран район)
$cemeteries = [];
if ($filter_district > 0) {
    $cemRes = mysqli_query($dblink, "SELECT idx, title FROM cemetery WHERE district = $filter_district ORDER BY title ASC");
    while ($c = mysqli_fetch_assoc($cemRes)) {
        $cemeteries[] = $c;
    }
}

// Построение SQL запроса для получения уборщиков
$sql = "
    SELECT DISTINCT
        u.idx AS user_id,
        u.fname,
        u.lname,
        u.avatar,
        cp.description,
        cp.region_id,
        cp.district_id,
        cp.all_cemeteries_in_district,
        cp.rating,
        cp.reviews_count,
        r.title AS region_title,
        d.title AS district_title
    FROM users u
    INNER JOIN cleaner_profiles cp ON u.idx = cp.user_id
    LEFT JOIN region r ON cp.region_id = r.idx
    LEFT JOIN district d ON cp.district_id = d.idx
    WHERE cp.is_visible = 1
    AND (u.status & " . ROLE_CLEANER . ") = " . ROLE_CLEANER . "
";

$where_conditions = [];

if ($filter_region > 0) {
    $where_conditions[] = "cp.region_id = $filter_region";
}

if ($filter_district > 0) {
    $where_conditions[] = "cp.district_id = $filter_district";
}

if ($filter_cemetery > 0) {
    $sql .= "
        AND (
            cp.all_cemeteries_in_district = 1 
            OR EXISTS (
                SELECT 1 FROM cleaner_cemeteries cc 
                WHERE cc.user_id = u.idx AND cc.cemetery_id = $filter_cemetery
            )
        )
    ";
}

if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY cp.rating DESC, cp.reviews_count DESC";

$cleanersRes = mysqli_query($dblink, $sql);
$cleaners = [];
while ($cleaner = mysqli_fetch_assoc($cleanersRes)) {
    // Получаем услуги уборщика
    $servicesRes = mysqli_query($dblink, "
        SELECT id, service_name, price_text 
        FROM cleaner_services 
        WHERE user_id = " . $cleaner['user_id'] . " 
        ORDER BY sort_order ASC
    ");
    $cleaner['services'] = [];
    while ($s = mysqli_fetch_assoc($servicesRes)) {
        $cleaner['services'][] = $s;
    }
    
    // Получаем кладбища уборщика (если не все кладбища района)
    $cleaner['cemeteries'] = [];
    if (!$cleaner['all_cemeteries_in_district']) {
        $cemRes = mysqli_query($dblink, "
            SELECT c.idx, c.title 
            FROM cleaner_cemeteries cc
            INNER JOIN cemetery c ON cc.cemetery_id = c.idx
            WHERE cc.user_id = " . $cleaner['user_id']
        );
        while ($c = mysqli_fetch_assoc($cemRes)) {
            $cleaner['cemeteries'][] = $c;
        }
    }
    
    $cleaners[] = $cleaner;
}

// Текущий пользователь и роль прибиральника
$currentUserId = isset($_SESSION['logged']) && $_SESSION['logged'] == 1 ? (int)$_SESSION['uzver'] : 0;
$currentUserStatus = 0;
$isCleaner = false;
$userData = ['fname' => '', 'lname' => '', 'tel' => ''];
if ($currentUserId > 0) {
    $uRes = mysqli_query($dblink, "SELECT status, fname, lname, tel FROM users WHERE idx = $currentUserId LIMIT 1");
    if ($uRow = mysqli_fetch_assoc($uRes)) {
        $currentUserStatus = (int)($uRow['status'] ?? 0);
        $isCleaner = hasRole($currentUserStatus, ROLE_CLEANER);
        $userData['fname'] = htmlspecialchars($uRow['fname'] ?? '', ENT_QUOTES, 'UTF-8');
        $userData['lname'] = htmlspecialchars($uRow['lname'] ?? '', ENT_QUOTES, 'UTF-8');
        $userData['tel'] = htmlspecialchars($uRow['tel'] ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// === Вывод страницы ===
View_Clear();
View_Add(Page_Up('Прибирання Кладовищ'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/cleaners.css">');
View_Add('<div class="cleancem-out" data-current-user-id="'.$currentUserId.'" data-user-fname="'.$userData['fname'].'" data-user-lname="'.$userData['lname'].'" data-user-tel="'.$userData['tel'].'">');

//контент
View_Add('
<section class="cleaners-hero">
    <div class="cleaners-hero-inner">
        <div class="cleaners-hero-text">
            <div class="cleaners-badge">Сервіс догляду за похованнями</div>
            <h1 class="cleaners-title">Прибирання кладовищ</h1>
            <p class="cleaners-subtitle">
                Оберіть надійного доглядача для прибирання та догляду за місцем поховання.
                Усі заявки оформлюються онлайн — навіть якщо ви знаходитесь далеко.
            </p>
        </div>
        <div class="cleaners-hero-highlight">
            <div class="cleaners-hero-stat">
                <span class="cleaners-hero-stat-label">Формат</span>
                <span class="cleaners-hero-stat-value">Індивідуальні прибирання та абонементи</span>
            </div>
            <div class="cleaners-hero-stat">
                <span class="cleaners-hero-stat-label">Послуги</span>
                <span class="cleaners-hero-stat-value">Прибирання, оновлення декору, догляд за квітами</span>
            </div>
        </div>
    </div>
</section>

<section class="cleaners-filters">
    <div class="cleaners-filters-inner">
        <div class="cleaners-filters-header">
            <h2 class="cleaners-filters-title">Пошук прибиральників кладовищ</h2>
            <p class="cleaners-filters-subtitle">
                Виберіть регіон, кладовище та бажані послуги — це допоможе звузити пошук.
            </p>
        </div>
        <form class="cleaners-filters-form" action="/clean-cemeteries.php" method="get">
            <div class="cleaners-filters-row">
                <div class="cleaners-filter-group">
                    <label class="cleaners-filter-label">Область</label>
                    <select name="region" id="filter-region" style="display:none;">
                        <option value="">Всі області</option>');

foreach ($regions as $region) {
    $selected = ($filter_region == $region['idx']) ? 'selected' : '';
    View_Add('<option value="'.$region['idx'].'" '.$selected.'>'.htmlspecialchars($region['title']).'</option>');
}

$selectedRegionTitle = '';
if ($filter_region > 0) {
    foreach ($regions as $r) {
        if ($r['idx'] == $filter_region) {
            $selectedRegionTitle = htmlspecialchars($r['title']);
            break;
        }
    }
}

View_Add('
                    </select>
                    <div class="custom-select-wrapper">
                        <div class="custom-select-trigger">'.($selectedRegionTitle ?: 'Всі області').'</div>
                        <div class="custom-options">');
foreach ($regions as $region) {
    View_Add('<span class="custom-option" data-value="'.$region['idx'].'">'.htmlspecialchars($region['title']).'</span>');
}
View_Add('
                        </div>
                    </div>
                </div>
                <div class="cleaners-filter-group">
                    <label class="cleaners-filter-label">Район</label>
                    <select name="district" id="filter-district" style="display:none;" '.($filter_region <= 0 ? 'disabled' : '').'>
                        <option value="">Всі райони</option>');

foreach ($districts as $district) {
    $selected = ($filter_district == $district['idx']) ? 'selected' : '';
    View_Add('<option value="'.$district['idx'].'" '.$selected.'>'.htmlspecialchars($district['title']).'</option>');
}

$selectedDistrictTitle = '';
if ($filter_district > 0) {
    foreach ($districts as $d) {
        if ($d['idx'] == $filter_district) {
            $selectedDistrictTitle = htmlspecialchars($d['title']);
            break;
        }
    }
}

View_Add('
                    </select>
                    <div class="custom-select-wrapper" id="filter-district-wrapper" '.($filter_region <= 0 ? 'style="opacity: 0.5; pointer-events: none;"' : '').'>
                        <div class="custom-select-trigger">'.($selectedDistrictTitle ?: 'Всі райони').'</div>
                        <div class="custom-options"></div>
                    </div>
                </div>
                <div class="cleaners-filter-group">
                    <label class="cleaners-filter-label">Кладовище</label>
                    <select name="cemetery" id="filter-cemetery" style="display:none;" '.($filter_district <= 0 ? 'disabled' : '').'>
                        <option value="">Всі кладовища</option>');

foreach ($cemeteries as $cemetery) {
    $selected = ($filter_cemetery == $cemetery['idx']) ? 'selected' : '';
    View_Add('<option value="'.$cemetery['idx'].'" '.$selected.'>'.htmlspecialchars($cemetery['title']).'</option>');
}

$selectedCemeteryTitle = '';
if ($filter_cemetery > 0) {
    foreach ($cemeteries as $c) {
        if ($c['idx'] == $filter_cemetery) {
            $selectedCemeteryTitle = htmlspecialchars($c['title']);
            break;
        }
    }
}

View_Add('
                    </select>
                    <div class="custom-select-wrapper" id="filter-cemetery-wrapper" '.($filter_district <= 0 ? 'style="opacity: 0.5; pointer-events: none;"' : '').'>
                        <div class="custom-select-trigger">'.($selectedCemeteryTitle ?: 'Всі кладовища').'</div>
                        <div class="custom-options"></div>
                    </div>
                </div>
                <div class="cleaners-filter-group cleaners-filter-submit">
                    <button type="submit" class="cleaners-filter-btn">Застосувати фільтри</button>
                    '.($filter_region > 0 || $filter_district > 0 || $filter_cemetery > 0 ? '<a href="/clean-cemeteries.php" class="cleaners-filter-reset">Скинути</a>' : '').'
                </div>
            </div>
        </form>
    </div>
</section>

<section class="cleaners-list-section">
    <div class="cleaners-list-header">
        <div class="cleaners-list-header-left">
            <h2 class="cleaners-list-title">Доступні прибиральники</h2>
            <p class="cleaners-list-subtitle">
                '.(count($cleaners) > 0 ? 'Знайдено ' . count($cleaners) . ' прибиральників' : 'Прибиральники не знайдені').'
            </p>
        </div>
        '.(isset($_SESSION['logged']) && $_SESSION['logged'] == 1 ? '
        <div class="cleaners-list-header-btns">
            '.($isCleaner ? '<a href="/profile.php?md=10" class="btn-my-cabinet">Мій кабінет</a>' : '<button type="button" class="btn-become-cleaner" id="btn-become-cleaner">Стати працівником</button>').'
            <button type="button" class="btn-my-orders" id="btn-my-orders">Замовлені прибиральники</button>
        </div>
        ' : '').'
    </div>

    <div class="cleaners-list">');

if (empty($cleaners)) {
    View_Add('<p style="text-align: center; padding: 40px;">Прибиральники не знайдені за обраними фільтрами</p>');
} else {
    foreach ($cleaners as $cleaner) {
        $fullName = trim(htmlspecialchars($cleaner['lname'] . ' ' . $cleaner['fname']));
        $avatar = !empty($cleaner['avatar']) && $cleaner['avatar'] !== '' 
            ? (strpos($cleaner['avatar'], '/') === 0 ? $cleaner['avatar'] : '/' . ltrim($cleaner['avatar'], '/'))
            : '/avatars/ava.png';
        $avatar = htmlspecialchars($avatar);
        $initials = mb_substr($cleaner['fname'] ?? '', 0, 1) . mb_substr($cleaner['lname'] ?? '', 0, 1);
        $avatarBgClass = ($avatar == '/avatars/ava.png') ? ' cleaner-avatar-bg-' . ((int)($cleaner['user_id'] ?? 0) % 6) : '';
        
        $location = '';
        if (!empty($cleaner['region_title'])) {
            $location .= htmlspecialchars($cleaner['region_title']);
        }
        if (!empty($cleaner['district_title'])) {
            if (!empty($location)) $location .= ' · ';
            $location .= htmlspecialchars($cleaner['district_title']);
        }
        if ($cleaner['all_cemeteries_in_district']) {
            if (!empty($location)) $location .= ' · ';
            $location .= 'Всі кладовища району';
        } elseif (!empty($cleaner['cemeteries'])) {
            $cemNames = array_map(function($c) { return htmlspecialchars($c['title']); }, array_slice($cleaner['cemeteries'], 0, 2));
            if (!empty($location)) $location .= ' · ';
            $location .= implode(', ', $cemNames);
            if (count($cleaner['cemeteries']) > 2) {
                $location .= '...';
            }
        }
        
        $rating = $cleaner['rating'] ?? 0;
        $reviews = $cleaner['reviews_count'] ?? 0;
        $stars = str_repeat('★', min(5, floor($rating))) . str_repeat('☆', max(0, 5 - floor($rating)));
        
        $servicesList = '';
        $servicesData = [];
        foreach ($cleaner['services'] as $service) {
            $servicesList .= '<li>'.htmlspecialchars($service['service_name']).'</li>';
            $priceNum = 0;
            if (!empty($service['price_text']) && preg_match('/\d+/', $service['price_text'], $m)) {
                $priceNum = (int)$m[0];
            }
            $servicesData[] = [
                'id' => $service['id'],
                'name' => $service['service_name'],
                'price' => $service['price_text'],
                'price_num' => $priceNum
            ];
        }
        
        $servicesJson = htmlspecialchars(json_encode($servicesData), ENT_QUOTES, 'UTF-8');
        
        View_Add('
        <article class="cleaner-card">
            <div class="cleaner-card-main">
                <div class="cleaner-card-left">
                    <div class="cleaner-avatar'.$avatarBgClass.'"'.($avatar != '/avatars/ava.png' ? ' style="background-image: url(\''.$avatar.'\'); background-size: cover; background-position: center;"' : '').'>'.($avatar == '/avatars/ava.png' ? $initials : '').'</div>
                    <div class="cleaner-basic">
                        <div class="cleaner-name">'.$fullName.'</div>
                        <div class="cleaner-rating">
                            <span class="cleaner-stars">'.$stars.'</span>
                            <span class="cleaner-rating-text">'.number_format($rating, 1).' · '.$reviews.' відгуків</span>
                        </div>
                        <div class="cleaner-location">'.$location.'</div>
                    </div>
                </div>
                <div class="cleaner-card-right">
                    <div class="cleaner-services">
                        <div class="cleaner-services-title">Доступні послуги</div>
                        <ul class="cleaner-services-list">
                            '.$servicesList.'
                        </ul>
                    </div>
                    <div class="cleaner-meta">
                        <div class="cleaner-price">
                            <div class="cleaner-price-label">Вартість</div>
                            <div class="cleaner-price-value">'.(!empty($cleaner['services']) ? htmlspecialchars($cleaner['services'][0]['price_text']) : 'За домовленістю').'</div>
                            <div class="cleaner-price-note">або індивідуально за домовленістю</div>
                        </div>
                        '.($cleaner['user_id'] == $currentUserId ? '<span class="cleaner-self-badge">Це ви</span>' : '<button
                            type="button"
                            class="cleaner-order-btn"
                            data-cleaner-id="'.$cleaner['user_id'].'"
                            data-cleaner-name="'.$fullName.'"
                            data-cleaner-services=\''.$servicesJson.'\'
                        >
                            Замовити цього прибиральника
                        </button>').'
                    </div>
                </div>
            </div>
            '.(!empty($cleaner['description']) ? '<div class="cleaner-description">'.htmlspecialchars($cleaner['description']).'</div>' : '').'
        </article>');
    }
}

View_Add('
    </div>
</section>

<!-- Модальне вікно "Стати працівником" -->
<div class="become-cleaner-modal-overlay" id="become-cleaner-modal-overlay" style="display:none;">
    <div class="become-cleaner-modal" id="become-cleaner-modal">
        <div class="become-cleaner-modal-header">
            <h3 class="become-cleaner-modal-title">Ви стали прибиральником!</h3>
            <button type="button" class="become-cleaner-modal-close" id="become-cleaner-modal-close" aria-label="Закрити">&times;</button>
        </div>
        <div class="become-cleaner-modal-body">
            <p>Для активації вашої робочої картки перейдіть у профіль — кабінет прибиральника — і заповніть інформацію про себе.</p>
            <p>Після налаштування інформації ваша картка буде активна і видна всім користувачам.</p>
        </div>
        <div class="become-cleaner-modal-footer">
            <a href="/profile.php?md=10" class="btn-become-cleaner-go">Перейти в кабінет</a>
            <button type="button" class="btn-become-cleaner-decline" id="btn-become-cleaner-decline">Відмовитися від праці</button>
        </div>
    </div>
</div>

<!-- Модальне вікно "Мої замовлення" -->
<div class="orders-modal-overlay" id="orders-modal-overlay" style="display:none;">
    <div class="orders-modal" id="orders-modal">
        <div class="orders-modal-header">
            <h3 class="orders-modal-title">Мої замовлення</h3>
            <button type="button" class="orders-modal-close" id="orders-modal-close" aria-label="Закрити">&times;</button>
        </div>
        <div class="orders-modal-body" id="orders-modal-body">
            <div class="orders-modal-loading" id="orders-modal-loading">Завантаження...</div>
            <div class="orders-modal-list" id="orders-modal-list" style="display:none;"></div>
            <div class="orders-modal-empty" id="orders-modal-empty" style="display:none;">
                <p>У вас поки немає замовлень</p>
            </div>
        </div>
    </div>
</div>

<!-- Модальне вікно "Скасувати замовлення" -->
<div class="order-action-modal-overlay" id="client-cancel-order-modal" style="display:none;">
    <div class="order-action-modal order-action-modal-compact">
        <div class="order-action-modal-header">
            <h3>Скасування замовлення</h3>
            <button type="button" class="order-action-modal-close" id="client-cancel-order-close" aria-label="Закрити">&times;</button>
        </div>
        <form id="client-cancel-order-form" class="order-action-modal-body">
            <input type="hidden" id="client-cancel-order-id" value="">
            <div class="order-action-field">
                <label class="order-action-reason-title">Причина скасування</label>
                <div class="quick-reason-grid" id="client-cancel-reason-grid">
                    <label class="quick-reason-card">
                        <input type="radio" name="client-cancel-reason-choice" value="Змінилися обставини">
                        <span class="quick-reason-card-text">Змінилися обставини</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="client-cancel-reason-choice" value="Більше не актуально">
                        <span class="quick-reason-card-text">Більше не актуально</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="client-cancel-reason-choice" value="Помилково створено замовлення">
                        <span class="quick-reason-card-text">Помилково створено замовлення</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="client-cancel-reason-choice" value="Знайшли інший варіант">
                        <span class="quick-reason-card-text">Знайшли інший варіант</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="client-cancel-reason-choice" value="Незадоволений якістю">
                        <span class="quick-reason-card-text">Незадоволений якістю</span>
                    </label>
                    <label class="quick-reason-card">
                        <input type="radio" name="client-cancel-reason-choice" value="__other__">
                        <span class="quick-reason-card-text">Інша причина</span>
                    </label>
                </div>
                <div class="order-action-other-reason" id="client-cancel-other-field" style="display:none;">
                    <label for="client-cancel-order-other-reason">Вкажіть іншу причину</label>
                    <input type="text" id="client-cancel-order-other-reason" maxlength="255" placeholder="Напишіть причину скасування">
                </div>
            </div>
            <div class="order-action-modal-footer">
                <button type="button" class="btn-order-modal-cancel" id="client-cancel-order-cancel">Скасувати</button>
                <button type="submit" class="btn-order-modal-confirm" id="client-cancel-order-confirm">Підтвердити скасування</button>
            </div>
        </form>
    </div>
</div>

<section class="cleaner-order-section" id="cleaner-order-section" style="display:none;">
    <div class="cleaner-order-inner">
        <div class="cleaner-order-header">
            <h2 class="cleaner-order-title">Оформлення замовлення</h2>
            <p class="cleaner-order-subtitle">
                Заповніть форму нижче для оформлення замовлення. Після відправки ви зможете уточнити деталі в робочому чаті.
            </p>
        </div>

        <div class="cleaner-order-selected">
            <div class="cleaner-order-selected-label">Обраний прибиральник</div>
            <div class="cleaner-order-selected-name" id="order-cleaner-name">
                Поки що не обрано прибиральника
            </div>
            <div class="cleaner-order-selected-meta">
                <span id="order-cleaner-services" class="cleaner-order-selected-services">
                    Після вибору прибиральника тут зʼявляться його послуги.
                </span>
            </div>
        </div>

        <form class="cleaner-order-form" action="/clean-cemeteries.php" method="post" id="order-form" novalidate>
            <input type="hidden" name="submit_order" value="1">
            <input type="hidden" name="cleaner_id" id="order-cleaner-id" value="">

            <div class="cleaner-order-field cleaner-order-field-full" id="services-selection" style="display:none;">
                <label class="cleaner-order-label">Виберіть послуги</label>
                <div id="services-checkboxes"></div>
                <div id="approximate-price-display" class="approximate-price-display">
                    <strong>Орієнтовна вартість: <span id="approximate-price-value">-</span></strong>
                </div>
                <input type="hidden" name="approximate_price" id="approximate-price-input" value="">
            </div>

            <div class="cleaner-order-grid">
                <div class="cleaner-order-field">
                    <label class="cleaner-order-label">Ваше ім\'я</label>
                    <input type="text" name="customer_name" class="cleaner-input" placeholder="Як до вас звертатись?" required>
                </div>
                <div class="cleaner-order-field">
                    <label class="cleaner-order-label">Телефон для зв\'язку</label>
                    <div class="phone-input-wrapper">
                        <div class="country-select">
                            <span class="country-flag">🇺🇦</span>
                            <span class="country-code">+380</span>
                        </div>
                        <input type="text" id="order-phone-input" class="phone-input" placeholder="(XX) XXX XX XX" required>
                        <input type="hidden" name="customer_phone" id="order-phone-full">
                    </div>
                </div>
                <div class="cleaner-order-field cleaner-order-field-full but">
                    <label class="cleaner-order-label">Кладовище</label>
                    <select name="cemetery_id" id="order-cemetery-select" style="display:none;" required>
                        <option value="">Виберіть кладовище</option>
                    </select>
                    <div class="custom-select-wrapper" id="order-cemetery-wrapper">
                        <div class="custom-select-trigger">Виберіть кладовище</div>
                        <div class="custom-options"></div>
                    </div>
                </div>
                <div class="cleaner-order-field">
                    <label class="cleaner-order-label">Квартал</label>
                    <input type="number" name="quarter" id="order-quarter" class="cleaner-input" placeholder="Квартал" min="1" step="1">
                </div>
                <div class="cleaner-order-field">
                    <label class="cleaner-order-label">Ряд</label>
                    <input type="number" name="row" id="order-row" class="cleaner-input" placeholder="Ряд" min="1" step="1">
                </div>
                <div class="cleaner-order-field">
                    <label class="cleaner-order-label">Місце</label>
                    <input type="number" name="place" id="order-place" class="cleaner-input" placeholder="Місце" min="1" step="1">
                </div>
                <div class="cleaner-order-field">
                    <label class="cleaner-order-label">Бажана дата виконання</label>
                    <input type="date" name="preferred_date" class="cleaner-input">
                </div>
            </div>

            <div class="cleaner-order-field cleaner-order-field-full">
                <label class="cleaner-order-label">Додаткові побажання</label>
                <textarea
                    name="comment"
                    class="cleaner-textarea"
                    rows="4"
                    placeholder="Опишіть, що саме потрібно: прибирання, оновлення квітів, заміна елементів декору тощо."
                ></textarea>
            </div>

            <div class="cleaner-order-footer">
                <button type="submit" class="cleaner-order-submit">
                    Надіслати замовлення
                </button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Автозаполнение данных пользователя
    var cleancemOut = document.querySelector(".cleancem-out");
    if (cleancemOut) {
        var userFname = cleancemOut.getAttribute("data-user-fname") || "";
        var userLname = cleancemOut.getAttribute("data-user-lname") || "";
        var userTel = cleancemOut.getAttribute("data-user-tel") || "";
        
        // Заполнение имени
        if (userFname) {
            var nameInput = document.querySelector(\'input[name="customer_name"]\');
            if (nameInput && !nameInput.value) {
                nameInput.value = userFname;
            }
        }
        
        // Заполнение телефона
        if (userTel) {
            var phoneInput = document.getElementById("order-phone-input");
            var phoneFullInput = document.getElementById("order-phone-full");
            if (phoneInput && phoneFullInput && !phoneInput.value) {
                // Убираем все нецифровые символы
                var digits = userTel.replace(/\D/g, "");
                // Убираем префикс 380 если есть
                if (digits.startsWith("380")) {
                    digits = digits.substring(3);
                }
                // Форматируем телефон
                if (digits.length > 0) {
                    var formatted = "";
                    if (digits.length > 0) {
                        formatted = "(" + digits.substring(0, 2);
                        if (digits.length > 2) {
                            formatted += ") " + digits.substring(2, 5);
                            if (digits.length > 5) {
                                formatted += " " + digits.substring(5, 7);
                                if (digits.length > 7) {
                                    formatted += " " + digits.substring(7, 9);
                                }
                            }
                        } else {
                            formatted += ")";
                        }
                    }
                    phoneInput.value = formatted;
                    phoneFullInput.value = "+380" + digits;
                }
            }
        }
    }
    
    var buttons = document.querySelectorAll(".cleaner-order-btn");
    var orderSection = document.getElementById("cleaner-order-section");
    var nameEl = document.getElementById("order-cleaner-name");
    var servicesEl = document.getElementById("order-cleaner-services");
    var idInput = document.getElementById("order-cleaner-id");
    var servicesSelection = document.getElementById("services-selection");
    var servicesCheckboxes = document.getElementById("services-checkboxes");
    var approximatePriceDisplay = document.getElementById("approximate-price-display");
    var approximatePriceValue = document.getElementById("approximate-price-value");
    var approximatePriceInput = document.getElementById("approximate-price-input");
    
    var currentServices = [];
    var orderForm = document.getElementById("order-form");
    var submitBtn = orderForm ? orderForm.querySelector(".cleaner-order-submit") : null;
    
    function updateApproximatePrice() {
        var selected = Array.from(servicesCheckboxes.querySelectorAll("input[type=\'checkbox\']:checked"));
        if (selected.length === 0) {
            approximatePriceValue.textContent = "-";
            approximatePriceInput.value = "";
            return;
        }
        
        var total = 0;
        var allHavePrice = true;
        selected.forEach(function(cb) {
            var num = parseInt(cb.dataset.priceNum || "0", 10);
            if (num > 0) {
                total += num;
            } else {
                allHavePrice = false;
            }
        });
        
        if (allHavePrice && total > 0) {
            approximatePriceValue.textContent = total + " грн";
            approximatePriceInput.value = total + " грн";
        } else if (total > 0) {
            approximatePriceValue.textContent = "від " + total + " грн";
            approximatePriceInput.value = "від " + total + " грн";
        } else {
            approximatePriceValue.textContent = "За домовленістю";
            approximatePriceInput.value = "За домовленістю";
        }
    }
    
    var currentCleanerId = null;
    var currentCleanerCemeteries = [];
    var orderCemeterySelect = document.getElementById("order-cemetery-select");
    var orderCemeteryWrapper = document.getElementById("order-cemetery-wrapper");
    
    // Загрузка кладбищ уборщика
    function loadCleanerCemeteries(cleanerId) {
        if (!cleanerId || !orderCemeterySelect || !orderCemeteryWrapper) return;
        
        fetch("/clean-cemeteries.php?ajax_cleaner_cemeteries=1&cleaner_id=" + cleanerId)
            .then(r => r.json())
            .then(data => {
                orderCemeterySelect.innerHTML = "<option value=\'\'>Виберіть кладовище</option>";
                const optionsBox = orderCemeteryWrapper.querySelector(".custom-options");
                if (optionsBox) optionsBox.innerHTML = "";
                
                if (!data || !data.length) {
                    orderCemeteryWrapper.querySelector(".custom-select-trigger").textContent = "Кладовища не знайдені";
                    return;
                }
                
                currentCleanerCemeteries = data;
                
                data.forEach(function(cem) {
                    const opt = document.createElement("option");
                    opt.value = cem.idx;
                    opt.textContent = cem.title;
                    orderCemeterySelect.appendChild(opt);
                    
                    const span = document.createElement("span");
                    span.className = "custom-option";
                    span.dataset.value = cem.idx;
                    span.textContent = cem.title;
                    if (optionsBox) optionsBox.appendChild(span);
                });
                
                // Инициализируем custom select для кладбища
                initCustomSelect(orderCemeteryWrapper);
            })
            .catch(() => {
                if (orderCemeteryWrapper) {
                    orderCemeteryWrapper.querySelector(".custom-select-trigger").textContent = "Помилка завантаження";
                }
            });
    }
    
    if (buttons.length && orderSection) {
    buttons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            var cleanerId = this.getAttribute("data-cleaner-id") || "";
            var cleanerName = this.getAttribute("data-cleaner-name") || "";
            var servicesJson = this.getAttribute("data-cleaner-services") || "[]";
            
            try {
                currentServices = JSON.parse(servicesJson);
            } catch(e) {
                currentServices = [];
            }

            currentCleanerId = cleanerId;

            if (idInput) {
                idInput.value = cleanerId;
            }
            if (nameEl) {
                nameEl.textContent = cleanerName || "Обраний прибиральник";
            }
            
            // Загружаем кладбища уборщика
            loadCleanerCemeteries(cleanerId);
            
            // Формируем список услуг
            if (servicesCheckboxes) servicesCheckboxes.innerHTML = "";
            if (currentServices.length > 0) {
                currentServices.forEach(function(service) {
                    var label = document.createElement("label");
                    label.className = "cleaner-service-checkbox";
                    
                    var checkbox = document.createElement("input");
                    checkbox.type = "checkbox";
                    checkbox.name = "selected_services[]";
                    checkbox.value = service.id;
                    checkbox.dataset.price = service.price;
                    checkbox.dataset.priceNum = String(service.price_num || 0);
                    checkbox.addEventListener("change", updateApproximatePrice);
                    
                    var customBox = document.createElement("span");
                    customBox.className = "cleaner-service-checkbox-box";
                    
                    var span = document.createElement("span");
                    span.className = "cleaner-service-checkbox-label";
                    span.textContent = service.name + " - " + service.price;
                    
                    label.appendChild(checkbox);
                    label.appendChild(customBox);
                    label.appendChild(span);
                    if (servicesCheckboxes) servicesCheckboxes.appendChild(label);
                });
                if (servicesSelection) servicesSelection.style.display = "block";
                updateApproximatePrice();
            } else {
                if (servicesSelection) servicesSelection.style.display = "none";
                approximatePriceValue.textContent = "-";
                approximatePriceInput.value = "";
            }
            
            if (servicesEl) {
                if (currentServices.length > 0) {
                    var servicesText = currentServices.map(function(s) { return s.name; }).join(", ");
                    servicesEl.textContent = servicesText;
                } else {
                    servicesEl.textContent = "Послуги не вказані";
                }
            }

            orderSection.style.display = "block";
            orderSection.scrollIntoView({ behavior: "smooth", block: "start" });

            // при выборе прибиральника форма становится активной
            updateOrderSubmitState();
        });
    });
    }
    
    // Валидация и блокировка кнопки отправки
    function updateOrderSubmitState() {
        if (!orderForm || !submitBtn) return;
        var nameFilled = !!orderForm.customer_name && orderForm.customer_name.value.trim() !== "";
        var phoneDigits = (phoneFullInput && phoneFullInput.value) ? phoneFullInput.value.replace(/\D/g, "") : "";
        var phoneComplete = phoneDigits.length >= 12 && phoneDigits.startsWith("380");
        var cemeteryFilled = !!orderCemeterySelect && orderCemeterySelect.value;
        submitBtn.disabled = !(nameFilled && phoneComplete && cemeteryFilled && idInput && idInput.value);
    }

    if (orderForm && submitBtn) {
        submitBtn.disabled = true;
        ["input", "change"].forEach(function(evt) {
            orderForm.addEventListener(evt, updateOrderSubmitState);
        });
        
        orderForm.addEventListener("submit", function(e) {
            var nameVal = (orderForm.customer_name && orderForm.customer_name.value) ? orderForm.customer_name.value.trim() : "";
            var phoneDigits = (phoneFullInput && phoneFullInput.value) ? phoneFullInput.value.replace(/\D/g, "") : "";
            var phoneOk = phoneDigits.length >= 12 && phoneDigits.startsWith("380");
            var hasLatinOrNumbers = /[a-zA-Z0-9]/.test(nameVal);
            if (hasLatinOrNumbers) {
                e.preventDefault();
                alert("Ім\'я має містити тільки українські літери (кирилицю), пробіли, апостроф або дефіс.");
                return false;
            }
            if (!phoneOk) {
                e.preventDefault();
                alert("Введіть повний номер телефону у форматі (XX) XXX XX XX");
                return false;
            }
        });
    }
    
    // Валідація імені: тільки кирилиця, пробіли, апостроф, дефіс (блокуються латиниця та цифри)
    var customerNameInput = orderForm ? orderForm.querySelector("input[name=customer_name]") : null;
    if (customerNameInput) {
        customerNameInput.addEventListener("input", function() {
            var v = this.value;
            this.value = v.replace(/[a-zA-Z0-9]/g, "");
        });
        customerNameInput.addEventListener("keypress", function(e) {
            var k = e.key;
            if (/[a-zA-Z0-9]/.test(k)) e.preventDefault();
        });
        customerNameInput.addEventListener("paste", function(e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData("text");
            this.value = (this.value + text.replace(/[a-zA-Z0-9]/g, "")).slice(0, 200);
        });
    }
    
    // Валідація телефона: повний номер (9 цифр для +380)
    function isPhoneComplete() {
        if (!phoneFullInput) return false;
        var digits = phoneFullInput.value.replace(/\D/g, "");
        return digits === "380" + (digits.slice(0, 3) === "380" ? digits.slice(3) : digits).replace(/^380/, "") 
            ? digits.length >= 12 
            : (digits.startsWith("380") ? digits.length >= 12 : digits.length >= 9);
    }
    

    // Инициализация / обновление custom select
    function initCustomSelect(wrapper) {
        if (!wrapper) return;
        const trigger = wrapper.querySelector(".custom-select-trigger");
        const options = wrapper.querySelector(".custom-options");
        const select = wrapper.previousElementSibling;
        
        if (!trigger || !options || !select) return;
        
        // Вешаем обработчик на триггер только один раз
        if (!wrapper.dataset.csTriggerInited) {
            trigger.addEventListener("click", function(e) {
                e.stopPropagation();
                document.querySelectorAll(".custom-select-wrapper").forEach(w => {
                    const opts = w.querySelector(".custom-options");
                    if (w !== wrapper) {
                        w.classList.remove("open");
                        if (opts) opts.style.display = "none";
                    }
                });
                const open = wrapper.classList.toggle("open");
                options.style.display = open ? "flex" : "none";
            });
            wrapper.dataset.csTriggerInited = "1";
        }
        
        // Переинициализируем клики по опциям каждый раз,
        // чтобы новые элементы тоже работали
        options.querySelectorAll(".custom-option").forEach(opt => {
            opt.onclick = function() {
                trigger.textContent = opt.textContent;
                select.value = opt.dataset.value;
                select.dispatchEvent(new Event("change"));
                wrapper.classList.remove("open");
                options.style.display = "none";
            };
        });
    }
    
    // Инициализация всех custom select на странице
    document.querySelectorAll(".custom-select-wrapper").forEach(initCustomSelect);
    
    // Маска для телефона
    var phoneInput = document.getElementById("order-phone-input");
    var phoneFullInput = document.getElementById("order-phone-full");
    
    if (phoneInput && phoneFullInput) {
        function formatPhone(value) {
            let numbers = value.replace(/\D/g, "");
            if (numbers.startsWith("380")) {
                numbers = numbers.substring(3);
            }
            if (numbers.length > 9) {
                numbers = numbers.substring(0, 9);
            }
            let formatted = "";
            if (numbers.length > 0) {
                formatted = "(" + numbers.substring(0, 2);
                if (numbers.length > 2) {
                    formatted += ") " + numbers.substring(2, 5);
                    if (numbers.length > 5) {
                        formatted += " " + numbers.substring(5, 7);
                        if (numbers.length > 7) {
                            formatted += " " + numbers.substring(7, 9);
                        }
                    }
                } else {
                    formatted += ")";
                }
            }
            return formatted;
        }
        
        phoneInput.addEventListener("input", function(e) {
            const formatted = formatPhone(e.target.value);
            phoneInput.value = formatted;
            const digits = formatted.replace(/\D/g, "");
            const phoneDigits = digits.startsWith("380") ? digits.substring(3) : digits;
            if (phoneDigits.length > 0) {
                phoneFullInput.value = "+380" + phoneDigits;
            } else {
                phoneFullInput.value = "";
            }
        });
        
        phoneInput.addEventListener("blur", function() {
            const digits = phoneInput.value.replace(/\D/g, "");
            const phoneDigits = digits.startsWith("380") ? digits.substring(3) : digits;
            if (phoneDigits.length > 0) {
                phoneFullInput.value = "+380" + phoneDigits;
            } else {
                phoneFullInput.value = "";
            }
        });
    }
    
    // Валидация инпутов квартала/ряда/места (нельзя 0)
    var quarterInput = document.getElementById("order-quarter");
    var rowInput = document.getElementById("order-row");
    var placeInput = document.getElementById("order-place");
    
    [quarterInput, rowInput, placeInput].forEach(function(input) {
        if (input) {
            input.addEventListener("keydown", function(e) {
                if (["e", "E", "+", "-", "."].indexOf(e.key) !== -1) {
                    e.preventDefault();
                }
            });
            input.addEventListener("input", function() {
                this.value = this.value.replace(/[^0-9]/g, "");
                if (this.value === "0") {
                    this.value = "";
                }
            });
            input.addEventListener("change", function() {
                var val = parseInt(this.value, 10);
                if (isNaN(val) || val < 1) {
                    this.value = "";
                } else {
                    this.value = String(val);
                }
            });
        }
    });
    
    // Обработка фильтров с custom select
    var filterRegion = document.getElementById("filter-region");
    var filterDistrict = document.getElementById("filter-district");
    var filterCemetery = document.getElementById("filter-cemetery");
    var filterRegionWrapper = filterRegion ? filterRegion.nextElementSibling : null;
    var filterDistrictWrapper = document.getElementById("filter-district-wrapper");
    var filterCemeteryWrapper = document.getElementById("filter-cemetery-wrapper");

    function hydrateCustomOptionsFromSelect(select, wrapper) {
        if (!select || !wrapper) return;
        var optionsBox = wrapper.querySelector(".custom-options");
        if (!optionsBox) return;
        if (optionsBox.querySelector(".custom-option")) return;

        Array.from(select.options).forEach(function(opt) {
            if (!opt.value) return;
            var span = document.createElement("span");
            span.className = "custom-option";
            span.dataset.value = opt.value;
            span.textContent = opt.textContent;
            optionsBox.appendChild(span);
        });

        initCustomSelect(wrapper);
    }

    // После применения фильтров страница перезагружается:
    // заполняем custom-options из скрытых select, чтобы район/кладовище снова можно было менять.
    hydrateCustomOptionsFromSelect(filterDistrict, filterDistrictWrapper);
    hydrateCustomOptionsFromSelect(filterCemetery, filterCemeteryWrapper);
    
    if (filterRegionWrapper && filterRegionWrapper.classList.contains("custom-select-wrapper")) {
        initCustomSelect(filterRegionWrapper);
        
        filterRegion.addEventListener("change", function() {
            var regionId = this.value;
            var trigger = filterRegionWrapper.querySelector(".custom-select-trigger");
            if (trigger && regionId) {
                var selectedOption = filterRegion.querySelector("option[value=\'" + regionId + "\']");
                if (selectedOption) trigger.textContent = selectedOption.textContent;
            } else if (trigger) {
                trigger.textContent = "Всі області";
            }
            
            if (regionId) {
                fetch("/clean-cemeteries.php?ajax_districts=1&region_id=" + encodeURIComponent(regionId))
                    .then(r => r.text())
                    .then(html => {
                        if (!filterDistrict || !filterDistrictWrapper) return;

                        const tmp = document.createElement("div");
                        tmp.innerHTML = "<select>" + html + "</select>";
                        const options = tmp.querySelectorAll("option");

                        filterDistrict.innerHTML = "<option value=\'\'>Всі райони</option>";
                        const districtOptions = filterDistrictWrapper.querySelector(".custom-options");
                        if (districtOptions) districtOptions.innerHTML = "";

                        options.forEach(function(optNode) {
                            if (!optNode.value) return;

                            const opt = document.createElement("option");
                            opt.value = optNode.value;
                            opt.textContent = optNode.textContent;
                            filterDistrict.appendChild(opt);

                            const span = document.createElement("span");
                            span.className = "custom-option";
                            span.dataset.value = optNode.value;
                            span.textContent = optNode.textContent;
                            if (districtOptions) districtOptions.appendChild(span);
                        });

                        const districtTrigger = filterDistrictWrapper.querySelector(".custom-select-trigger");
                        if (districtTrigger) districtTrigger.textContent = "Всі райони";

                        filterDistrict.disabled = false;
                        filterDistrictWrapper.style.opacity = "1";
                        filterDistrictWrapper.style.pointerEvents = "auto";
                        initCustomSelect(filterDistrictWrapper);
                        
                        filterCemetery.innerHTML = "<option value=\'\'>Всі кладовища</option>";
                        if (filterCemeteryWrapper) {
                            filterCemeteryWrapper.querySelector(".custom-select-trigger").textContent = "Всі кладовища";
                            filterCemeteryWrapper.querySelector(".custom-options").innerHTML = "";
                        }
                        filterCemetery.disabled = true;
                        if (filterCemeteryWrapper) {
                            filterCemeteryWrapper.style.opacity = "0.5";
                            filterCemeteryWrapper.style.pointerEvents = "none";
                        }
                    });
            } else {
                if (filterDistrict) {
                    filterDistrict.innerHTML = "<option value=\'\'>Всі райони</option>";
                    filterDistrict.disabled = true;
                }
                if (filterDistrictWrapper) {
                    filterDistrictWrapper.querySelector(".custom-select-trigger").textContent = "Всі райони";
                    filterDistrictWrapper.style.opacity = "0.5";
                    filterDistrictWrapper.style.pointerEvents = "none";
                }
                if (filterCemetery) {
                    filterCemetery.innerHTML = "<option value=\'\'>Всі кладовища</option>";
                    filterCemetery.disabled = true;
                }
                if (filterCemeteryWrapper) {
                    filterCemeteryWrapper.querySelector(".custom-select-trigger").textContent = "Всі кладовища";
                    filterCemeteryWrapper.style.opacity = "0.5";
                    filterCemeteryWrapper.style.pointerEvents = "none";
                }
            }
        });
    }
    
    if (filterDistrict && filterDistrictWrapper) {
        filterDistrict.addEventListener("change", function() {
            var districtId = this.value;
            var trigger = filterDistrictWrapper.querySelector(".custom-select-trigger");
            if (trigger && districtId) {
                var selectedOption = filterDistrict.querySelector("option[value=\'" + districtId + "\']");
                if (selectedOption) trigger.textContent = selectedOption.textContent;
            } else if (trigger) {
                trigger.textContent = "Всі райони";
            }
            
            if (districtId) {
                fetch("/clean-cemeteries.php?ajax_cemeteries=1&district=" + districtId)
                    .then(r => r.text())
                    .then(html => {
                        if (!filterCemetery || !filterCemeteryWrapper) return;
                        
                        const tmp = document.createElement("div");
                        tmp.innerHTML = html;
                        const options = tmp.querySelectorAll("option");
                        
                        filterCemetery.innerHTML = "<option value=\'\'>Всі кладовища</option>";
                        const cemeteryOptions = filterCemeteryWrapper.querySelector(".custom-options");
                        if (cemeteryOptions) cemeteryOptions.innerHTML = "";
                        
                        options.forEach(function(opt) {
                            if (opt.value) {
                                filterCemetery.appendChild(opt.cloneNode(true));
                                
                                const span = document.createElement("span");
                                span.className = "custom-option";
                                span.dataset.value = opt.value;
                                span.textContent = opt.textContent;
                                if (cemeteryOptions) cemeteryOptions.appendChild(span);
                            }
                        });
                        
                        filterCemetery.disabled = false;
                        filterCemeteryWrapper.style.opacity = "1";
                        filterCemeteryWrapper.style.pointerEvents = "auto";
                        initCustomSelect(filterCemeteryWrapper);
                    });
            } else {
                if (filterCemetery) {
                    filterCemetery.innerHTML = "<option value=\'\'>Всі кладовища</option>";
                    filterCemetery.disabled = true;
                }
                if (filterCemeteryWrapper) {
                    filterCemeteryWrapper.querySelector(".custom-select-trigger").textContent = "Всі кладовища";
                    filterCemeteryWrapper.style.opacity = "0.5";
                    filterCemeteryWrapper.style.pointerEvents = "none";
                }
            }
        });
    }
    
    // Закрытие custom select при клике вне его
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".custom-select-wrapper")) {
            document.querySelectorAll(".custom-select-wrapper").forEach(w => {
                w.classList.remove("open");
                const options = w.querySelector(".custom-options");
                if (options) options.style.display = "none";
            });
        }
    });
    
    // Попап после отправки заказа
    '.(isset($_GET['order_success']) && isset($_SESSION['order_success']) && $_SESSION['order_success'] ? '
    var orderChatIdx = '.json_encode($_SESSION['order_chat_idx'] ?? '').';
    var orderSuccessModal = document.createElement("div");
    orderSuccessModal.className = "order-success-modal";
    orderSuccessModal.style.cssText = "position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 10000;";
    orderSuccessModal.innerHTML = `
        <div class="order-success-modal-card">
            <div class="order-success-modal-icon">
                <svg class="order-success-checkmark-svg" viewBox="0 0 64 64" width="64" height="64">
                    <circle cx="32" cy="32" r="30" fill="none" stroke="#2563eb" stroke-width="4"/>
                    <path fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" d="M18 34 L28 44 L46 24"/>
                </svg>
            </div>
            <h2 class="order-success-modal-title">Замовлення відправлено!</h2>
            <p class="order-success-modal-text">Ваше замовлення успішно створено. Ви можете уточнити деталі в робочому чаті.</p>
            <div class="order-success-modal-actions">
                <a class="order-success-modal-primary" href="/messenger.php?type=2&chat='.($_SESSION['order_chat_idx'] ?? '').'">Перейти в робочий чат</a>
                <button class="order-success-modal-secondary" type="button" data-close-success-modal="1">Закрити</button>
            </div>
        </div>
    `;
    document.body.appendChild(orderSuccessModal);
    const closeBtn = orderSuccessModal.querySelector("[data-close-success-modal=\'1\']");
    if (closeBtn) {
        closeBtn.addEventListener("click", function() {
            orderSuccessModal.remove();
            fetch("/clean-cemeteries.php?clear_order_session=1");
        });
    }
    orderSuccessModal.addEventListener("click", function(e) {
        if (e.target === orderSuccessModal) {
            orderSuccessModal.remove();
            fetch("/clean-cemeteries.php?clear_order_session=1");
        }
    });
    ' : '').'
    
    // Модальне вікно "Мої замовлення"
    var btnMyOrders = document.getElementById("btn-my-orders");
    var ordersModalOverlay = document.getElementById("orders-modal-overlay");
    var ordersModalClose = document.getElementById("orders-modal-close");
    var ordersModalBody = document.getElementById("orders-modal-body");
    var ordersModalLoading = document.getElementById("orders-modal-loading");
    var ordersModalList = document.getElementById("orders-modal-list");
    var ordersModalEmpty = document.getElementById("orders-modal-empty");
    var clientCancelOrderModal = document.getElementById("client-cancel-order-modal");
    var clientCancelOrderForm = document.getElementById("client-cancel-order-form");
    var clientCancelOrderId = document.getElementById("client-cancel-order-id");
    var clientCancelReasonOptions = document.querySelectorAll("input[name=\"client-cancel-reason-choice\"]");
    var clientCancelOtherField = document.getElementById("client-cancel-other-field");
    var clientCancelOtherReason = document.getElementById("client-cancel-order-other-reason");
    var clientCancelOrderConfirm = document.getElementById("client-cancel-order-confirm");
    var clientCancelOrderCancel = document.getElementById("client-cancel-order-cancel");
    var clientCancelOrderClose = document.getElementById("client-cancel-order-close");
    
    if (btnMyOrders && ordersModalOverlay) {
        var statusLabels = { pending: "Очікує прийняття", accepted: "Прийнято", completion_pending: "Очікує підтвердження", rejected: "Відхилено", completed: "Виконано", cancelled: "Скасовано" };
        
        function esc(s) {
            if (!s) return "";
            var d = document.createElement("div");
            d.textContent = s;
            return d.innerHTML;
        }

        function getCheckedReasonValue(reasonInputs) {
            if (!reasonInputs || !reasonInputs.length) return "";
            var selectedValue = "";
            reasonInputs.forEach(function(input) {
                if (input && input.checked) {
                    selectedValue = input.value || "";
                }
            });
            return selectedValue;
        }

        function toggleClientCancelOtherField() {
            var selectedReason = getCheckedReasonValue(clientCancelReasonOptions);
            var isOtherSelected = selectedReason === "__other__";
            if (clientCancelOtherField) {
                clientCancelOtherField.style.display = isOtherSelected ? "block" : "none";
            }
            if (clientCancelOtherReason) {
                clientCancelOtherReason.required = isOtherSelected;
                if (!isOtherSelected) {
                    clientCancelOtherReason.value = "";
                }
            }
        }

        function closeClientCancelModal() {
            if (!clientCancelOrderModal || !clientCancelOrderForm) return;
            clientCancelOrderModal.style.display = "none";
            clientCancelOrderForm.reset();
            toggleClientCancelOtherField();
            if (clientCancelOrderConfirm) {
                clientCancelOrderConfirm.disabled = false;
                clientCancelOrderConfirm.textContent = "Підтвердити скасування";
            }
        }

        function openClientCancelModal(orderId) {
            if (!clientCancelOrderModal || !clientCancelOrderId || !orderId) return;
            clientCancelOrderId.value = String(orderId);
            clientCancelOrderModal.style.display = "flex";
            toggleClientCancelOtherField();
            if (clientCancelReasonOptions && clientCancelReasonOptions.length > 0) {
                clientCancelReasonOptions[0].focus();
            }
        }

        function loadMyOrders() {
            ordersModalLoading.style.display = "block";
            ordersModalList.style.display = "none";
            ordersModalEmpty.style.display = "none";

            fetch("/clean-cemeteries.php?ajax_my_orders=1")
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    ordersModalLoading.style.display = "none";
                    if (data.orders && data.orders.length > 0) {
                        ordersModalList.style.display = "flex";
                        ordersModalList.innerHTML = data.orders.map(function(o) {
                            var normalizedStatus = o.status;
                            if (normalizedStatus === "accepted" && o.completed_at) {
                                normalizedStatus = "completion_pending";
                            }
                            var statusLabel = statusLabels[normalizedStatus] || normalizedStatus;
                            var orderId = parseInt(o.idx || 0, 10) || 0;
                            var chatLink = o.chat_idx
                                ? \'<a href="/messenger.php?type=2&chat=\' + o.chat_idx + \'" class="order-item-chat-link"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-action-icon icon icon-tabler icons-tabler-outline icon-tabler-message-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 18l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v4.5" /><path d="M17 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M22 22a2 2 0 0 0 -2 -2h-2a2 2 0 0 0 -2 2" /></svg><span class="btn-action-label">Написати в робочий чат</span></a>\'
                                : "";
                            var cancelBtn = (normalizedStatus === "pending" && orderId > 0)
                                ? \'<button type="button" class="order-item-cancel-btn" data-order-id="\' + orderId + \'">Скасувати замовлення</button>\'
                                : "";
                            var actionRow = (chatLink || cancelBtn)
                                ? \'<div class="order-item-actions">\' + chatLink + cancelBtn + \'</div>\'
                                : "";
                            var createdStr = o.created_at ? new Date(o.created_at.replace(" ", "T")).toLocaleDateString("uk-UA", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }) : "";
                            var completedStr = o.completed_at ? new Date(o.completed_at.replace(" ", "T")).toLocaleDateString("uk-UA", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }) : "";
                            var orderExtra = "";
                            if (normalizedStatus === "completion_pending") {
                                orderExtra += \'<p><strong>Статус звіту:</strong> Очікує підтвердження клієнта</p>\';
                            }
                            if (normalizedStatus === "rejected" && o.rejection_reason) {
                                orderExtra += \'<p><strong>Причина відмови:</strong> \' + esc(o.rejection_reason) + \'</p>\';
                            }
                            if (normalizedStatus === "cancelled" && o.rejection_reason) {
                                orderExtra += \'<p><strong>Причина скасування:</strong> \' + esc(o.rejection_reason) + \'</p>\';
                            }
                            if ((normalizedStatus === "completion_pending" || normalizedStatus === "completed") && completedStr) {
                                orderExtra += \'<p><strong>Дата звіту:</strong> \' + esc(completedStr) + \'</p>\';
                            }
                            if ((normalizedStatus === "completion_pending" || normalizedStatus === "completed") && o.completion_comment) {
                                orderExtra += \'<p><strong>Коментар до звіту:</strong> \' + esc(o.completion_comment) + \'</p>\';
                            }
                            return \'<div class="order-modal-item">\' +
                                \'<div class="order-modal-item-header">\' +
                                    \'<strong>\' + esc((o.cleaner_name || "Прибиральник").trim()) + \'</strong>\' +
                                    \'<span class="order-modal-status order-modal-status-\' + esc(normalizedStatus) + \'">\' + esc(statusLabel) + \'</span>\' +
                                \'</div>\' +
                                \'<div class="order-modal-item-info">\' +
                                    \'<p><strong>Кладовище:</strong> \' + esc(o.cemetery_place || "Не вказано") + \'</p>\' +
                                    \'<p><strong>Дата:</strong> \' + esc(o.preferred_date || "Не вказано") + \'</p>\' +
                                    \'<p><strong>Орієнтовна вартість:</strong> \' + esc(o.approximate_price || "Не вказано") + \'</p>\' +
                                    (o.comment ? \'<p><strong>Коментар:</strong> \' + esc(o.comment) + \'</p>\' : "") +
                                    orderExtra +
                                    \'<p class="order-modal-date">\' + esc(createdStr) + \'</p>\' +
                                \'</div>\' +
                                actionRow +
                            \'</div>\';
                        }).join("");
                    } else {
                        ordersModalEmpty.style.display = "block";
                    }
                })
                .catch(function() {
                    ordersModalLoading.style.display = "none";
                    ordersModalEmpty.style.display = "block";
                    ordersModalEmpty.innerHTML = "<p>Помилка завантаження</p>";
                });
        }
        
        btnMyOrders.addEventListener("click", function() {
            ordersModalOverlay.style.display = "flex";
            loadMyOrders();
        });

        if (ordersModalList) {
            ordersModalList.addEventListener("click", function(e) {
                var cancelBtn = e.target.closest(".order-item-cancel-btn");
                if (!cancelBtn) return;
                var orderId = parseInt(cancelBtn.getAttribute("data-order-id") || "0", 10);
                if (orderId <= 0) return;
                openClientCancelModal(orderId);
            });
        }

        if (clientCancelReasonOptions && clientCancelReasonOptions.length) {
            clientCancelReasonOptions.forEach(function(input) {
                input.addEventListener("change", toggleClientCancelOtherField);
            });
        }
        toggleClientCancelOtherField();

        if (clientCancelOrderForm) {
            clientCancelOrderForm.addEventListener("submit", function(e) {
                e.preventDefault();
                var orderId = parseInt((clientCancelOrderId && clientCancelOrderId.value) ? clientCancelOrderId.value : "0", 10);
                var selectedReason = getCheckedReasonValue(clientCancelReasonOptions);
                var cancelReason = selectedReason;
                if (orderId <= 0) {
                    alert("Некоректне замовлення");
                    return;
                }
                if (!selectedReason) {
                    alert("Оберіть причину скасування");
                    return;
                }
                if (selectedReason === "__other__") {
                    cancelReason = clientCancelOtherReason ? clientCancelOtherReason.value.trim() : "";
                    if (!cancelReason) {
                        alert("Вкажіть іншу причину скасування");
                        return;
                    }
                }
                if (!cancelReason) {
                    alert("Вкажіть причину скасування");
                    return;
                }

                if (clientCancelOrderConfirm) {
                    clientCancelOrderConfirm.disabled = true;
                    clientCancelOrderConfirm.textContent = "Скасування...";
                }

                var body = new URLSearchParams();
                body.set("ajax_cancel_order", "1");
                body.set("order_id", String(orderId));
                body.set("cancel_reason", cancelReason);

                fetch("/clean-cemeteries.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                    },
                    body: body.toString()
                })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data || data.status !== "ok") {
                            throw new Error((data && data.msg) ? data.msg : "Не вдалося скасувати замовлення");
                        }
                        closeClientCancelModal();
                        loadMyOrders();
                    })
                    .catch(function(err) {
                        alert(err && err.message ? err.message : "Не вдалося скасувати замовлення");
                    })
                    .finally(function() {
                        if (clientCancelOrderConfirm) {
                            clientCancelOrderConfirm.disabled = false;
                            clientCancelOrderConfirm.textContent = "Підтвердити скасування";
                        }
                    });
            });
        }

        if (clientCancelOrderCancel) {
            clientCancelOrderCancel.addEventListener("click", closeClientCancelModal);
        }
        if (clientCancelOrderClose) {
            clientCancelOrderClose.addEventListener("click", closeClientCancelModal);
        }
        if (clientCancelOrderModal) {
            clientCancelOrderModal.addEventListener("click", function(e) {
                if (e.target === clientCancelOrderModal) {
                    closeClientCancelModal();
                }
            });
        }
        
        function closeOrdersModal() {
            ordersModalOverlay.style.display = "none";
        }
        
        if (ordersModalClose) ordersModalClose.addEventListener("click", closeOrdersModal);
        ordersModalOverlay.addEventListener("click", function(e) {
            if (e.target === ordersModalOverlay) closeOrdersModal();
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape" && ordersModalOverlay.style.display === "flex") closeOrdersModal();
            if (e.key === "Escape" && clientCancelOrderModal && clientCancelOrderModal.style.display === "flex") closeClientCancelModal();
        });
    }
    
    // Кнопка "Стати працівником" та модальне вікно
    var btnBecomeCleaner = document.getElementById("btn-become-cleaner");
    var becomeCleanerModal = document.getElementById("become-cleaner-modal-overlay");
    var becomeCleanerClose = document.getElementById("become-cleaner-modal-close");
    var btnBecomeCleanerDecline = document.getElementById("btn-become-cleaner-decline");
    
    if (btnBecomeCleaner && becomeCleanerModal) {
        btnBecomeCleaner.addEventListener("click", function() {
            fetch("/clean-cemeteries.php?ajax_become_cleaner=1")
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        becomeCleanerModal.style.display = "flex";
                        btnBecomeCleaner.outerHTML = \'<a href="/profile.php?md=10" class="btn-my-cabinet">Мій кабінет</a>\';
                    }
                });
        });
    }
    
    function closeBecomeCleanerModal() {
        if (becomeCleanerModal) becomeCleanerModal.style.display = "none";
    }
    
    if (becomeCleanerClose) becomeCleanerClose.addEventListener("click", closeBecomeCleanerModal);
    if (becomeCleanerModal) becomeCleanerModal.addEventListener("click", function(e) {
        if (e.target === becomeCleanerModal) closeBecomeCleanerModal();
    });
    
    if (btnBecomeCleanerDecline) {
        btnBecomeCleanerDecline.addEventListener("click", function() {
            fetch("/clean-cemeteries.php?ajax_remove_cleaner=1")
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        closeBecomeCleanerModal();
                        location.reload();
                    }
                });
        });
    }
});
</script>');

// AJAX: стати працівником (додати роль)
if (isset($_GET['ajax_become_cleaner'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
    $userId = (int)$_SESSION['uzver'];
    $res = mysqli_query($dblink, "SELECT status FROM users WHERE idx = $userId LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        $status = (int)($row['status'] ?? 0);
        $newStatus = addRole($status, ROLE_CLEANER);
        mysqli_query($dblink, "UPDATE users SET status = $newStatus WHERE idx = $userId");
        $_SESSION['status'] = $newStatus;
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// AJAX: відмовитися від праці (зняти роль)
if (isset($_GET['ajax_remove_cleaner'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $userId = (int)$_SESSION['uzver'];
    $res = mysqli_query($dblink, "SELECT status FROM users WHERE idx = $userId LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        $status = (int)($row['status'] ?? 0);
        $newStatus = removeRole($status, ROLE_CLEANER);
        mysqli_query($dblink, "UPDATE users SET status = $newStatus WHERE idx = $userId");
        $_SESSION['status'] = $newStatus;
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// AJAX: скасувати замовлення клієнтом
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cancel_order'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
        echo json_encode(['status' => 'error', 'msg' => 'unauthorized']);
        exit;
    }

    $clientId = (int)$_SESSION['uzver'];
    $orderId = (int)($_POST['order_id'] ?? 0);
    $cancelReason = trim((string)($_POST['cancel_reason'] ?? ''));

    if ($orderId <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Некоректний ID замовлення'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($cancelReason === '') {
        echo json_encode(['status' => 'error', 'msg' => 'Вкажіть причину скасування'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (function_exists('mb_strlen') && mb_strlen($cancelReason, 'UTF-8') > 3000) {
        echo json_encode(['status' => 'error', 'msg' => 'Причина занадто довга (до 3000 символів)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtOrder = mysqli_prepare($dblink, "
        SELECT idx, status, chat_idx
        FROM cleaner_orders
        WHERE idx = ? AND client_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmtOrder, 'ii', $orderId, $clientId);
    mysqli_stmt_execute($stmtOrder);
    mysqli_stmt_bind_result($stmtOrder, $dbOrderId, $dbStatus, $dbChatIdx);
    $orderFound = mysqli_stmt_fetch($stmtOrder);
    mysqli_stmt_close($stmtOrder);

    if (!$orderFound) {
        echo json_encode(['status' => 'error', 'msg' => 'Замовлення не знайдено'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($dbStatus !== 'pending') {
        echo json_encode(['status' => 'error', 'msg' => 'Скасувати можна лише замовлення зі статусом "Очікує прийняття"'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtUpdate = mysqli_prepare($dblink, "
        UPDATE cleaner_orders
        SET status = 'cancelled',
            rejection_reason = ?,
            completed_at = NULL,
            completion_comment = NULL
        WHERE idx = ? AND client_id = ? AND status = 'pending'
    ");
    mysqli_stmt_bind_param($stmtUpdate, 'sii', $cancelReason, $orderId, $clientId);
    mysqli_stmt_execute($stmtUpdate);
    $affected = mysqli_stmt_affected_rows($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);

    if ($affected <= 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Не вдалося скасувати замовлення'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$dbChatIdx > 0) {
        $chats = new Chats($dblink);
        $msg = "Клієнт скасував замовлення.\nПричина скасування: " . $cancelReason;
        $chats->addMessage((int)$dbChatIdx, $clientId, $msg);
    }

    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX загрузка замовлень клієнта (Мої замовлення)
if (isset($_GET['ajax_my_orders'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
        echo json_encode(['orders' => [], 'error' => 'unauthorized']);
        exit;
    }
    $client_id = (int)$_SESSION['uzver'];
    $ordersRes = mysqli_query($dblink, "
        SELECT co.idx, co.cleaner_id, co.cemetery_place, co.preferred_date, co.approximate_price, 
               co.comment, co.chat_idx, co.status, co.rejection_reason, co.completed_at, co.completion_comment, co.created_at,
               TRIM(CONCAT(IFNULL(u.lname,''), ' ', IFNULL(u.fname,''))) AS cleaner_name
        FROM cleaner_orders co
        LEFT JOIN users u ON co.cleaner_id = u.idx
        WHERE co.client_id = $client_id
        ORDER BY co.created_at DESC
    ");
    $orders = [];
    while ($o = mysqli_fetch_assoc($ordersRes)) {
        $orders[] = $o;
    }
    echo json_encode(['orders' => $orders]);
    exit;
}

// AJAX загрузка кладбищ для фильтра
if (isset($_GET['ajax_cemeteries']) && isset($_GET['district'])) {
    $district_id = intval($_GET['district']);
    $res = mysqli_query($dblink, "SELECT idx, title FROM cemetery WHERE district = $district_id ORDER BY title ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        echo '<option value="'.$row['idx'].'">'.htmlspecialchars($row['title']).'</option>';
    }
    exit;
}

// AJAX загрузка кладбищ уборщика для формы заказа
if (isset($_GET['ajax_cleaner_cemeteries']) && isset($_GET['cleaner_id'])) {
    $cleaner_id = intval($_GET['cleaner_id']);
    
    // Получаем профиль уборщика
    $profileRes = mysqli_query($dblink, "
        SELECT all_cemeteries_in_district, district_id 
        FROM cleaner_profiles 
        WHERE user_id = $cleaner_id LIMIT 1
    ");
    $profile = mysqli_fetch_assoc($profileRes);
    
    $cemeteries = [];
    
    if ($profile && $profile['all_cemeteries_in_district']) {
        // Если работает на всех кладбищах района
        if ($profile['district_id']) {
            $cemRes = mysqli_query($dblink, "
                SELECT idx, title 
                FROM cemetery 
                WHERE district = " . (int)$profile['district_id'] . " 
                ORDER BY title ASC
            ");
            while ($c = mysqli_fetch_assoc($cemRes)) {
                $cemeteries[] = $c;
            }
        }
    } else {
        // Если работает на конкретных кладбищах
        $cemRes = mysqli_query($dblink, "
            SELECT c.idx, c.title 
            FROM cleaner_cemeteries cc
            INNER JOIN cemetery c ON cc.cemetery_id = c.idx
            WHERE cc.user_id = $cleaner_id
            ORDER BY c.title ASC
        ");
        while ($c = mysqli_fetch_assoc($cemRes)) {
            $cemeteries[] = $c;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($cemeteries);
    exit;
}

// Очистка сессии после показа попапа
if (isset($_GET['clear_order_session'])) {
    unset($_SESSION['order_success'], $_SESSION['order_chat_idx'], $_SESSION['order_cleaner_id']);
    exit;
}

View_Add('</div>'); // .out

View_Add(Page_Down());
View_Out();
View_Clear();

mysqli_close($dblink);
