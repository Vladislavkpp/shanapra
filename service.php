<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

if (isset($_GET['activate_cleaner'])) {
    $isLogged = isset($_SESSION['logged']) && (int)$_SESSION['logged'] === 1;

    if (!$isLogged) {
        header('Location: /auth.php');
        exit;
    }

    $userId = (int)($_SESSION['uzver'] ?? 0);
    if ($userId > 0) {
        $res = mysqli_query($dblink, "SELECT status FROM users WHERE idx = $userId LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $status = (int)($row['status'] ?? 0);
            if (!hasRole($status, ROLE_CLEANER)) {
                $status = addRole($status, ROLE_CLEANER);
                mysqli_query($dblink, "UPDATE users SET status = $status WHERE idx = $userId");
            }

            $_SESSION['status'] = $status;
        }
    }

    header('Location: /profile.php?md=10');
    exit;
}

function ServicePage(): string
{
    $isLogged = isset($_SESSION['logged']) && (int)$_SESSION['logged'] === 1;
    $workerHref = $isLogged ? '/service.php?activate_cleaner=1' : '/auth.php';

    $quickActions = [
        [
            'title' => 'Замовити догляд',
            'text' => 'Підібрати виконавця та оформити замовлення онлайн.',
            'href' => '/clean-cemeteries.php',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 10h16" /><path d="M4 14h16" /><path d="M9 18l3 3l3 -3" /><path d="M9 6l3 -3l3 3" /></svg>',
        ],
        [
            'title' => 'Стати виконавцем',
            'text' => 'Заповнити профіль працівника та приймати замовлення.',
            'href' => $workerHref,
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h3" /><path d="M16 22l5 -5" /><path d="M21 21.5v-4.5h-4.5" /></svg>',
        ],
        [
            'title' => 'Підтримка',
            'text' => 'Отримати консультацію та відповідь у чаті.',
            'href' => '/messenger.php?type=3',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 14l-3 -3h-7a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1h9a1 1 0 0 1 1 1v10" /><path d="M14 15v2a1 1 0 0 1 -1 1h-7l-3 3v-10a1 1 0 0 1 1 -1h2" /></svg>',
        ],
    ];

    $availableServices = [
        [
            'title' => 'Прибирання та догляд за похованнями',
            'text' => 'Пошук працівника за регіоном, районом і кладовищем, перегляд карток виконавців та оформлення замовлення без зайвих дзвінків.',
            'textMobile' => 'Пошук виконавця, перегляд карток і швидке оформлення замовлення онлайн.',
            'meta' => 'Доступно зараз',
            'href' => '/clean-cemeteries.php',
            'action' => 'Перейти до послуги',
            'iconClass' => 'service-card-icon--teal',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-2a3 3 0 0 1 3 -3h8a3 3 0 0 1 3 3v2h-14" /><path d="M10 16v-5h-4v-4h4v-4h4v4h4v4h-4v5" /></svg>',
        ],
        [
            'title' => 'Кабінет виконавця',
            'text' => 'Окремий робочий простір для працівників: опис послуг, робочі зони, замовлення та комунікація з клієнтами в межах платформи.',
            'textMobile' => 'Робочий кабінет працівника з послугами, зонами роботи та замовленнями.',
            'meta' => 'Для працівників',
            'href' => $workerHref,
            'action' => $isLogged ? 'Відкрити кабінет' : 'Увійти та продовжити',
            'iconClass' => 'service-card-icon--blue',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h3" /><path d="M16 22l5 -5" /><path d="M21 21.5v-4.5h-4.5" /></svg>',
        ],
        [
            'title' => 'Додати поховання',
            'text' => 'Швидкий старт для нових записів: створіть картку поховання, додайте фото, дані про місце та передайте інформацію на модерацію.',
            'textMobile' => 'Створення картки поховання з фото, даними місця та відправкою на модерацію.',
            'meta' => 'Онлайн-форма',
            'href' => '/graveaddform.php',
            'action' => 'Створити запис',
            'iconClass' => 'service-card-icon--gold',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 6c0 1.657 3.582 3 8 3s8 -1.343 8 -3s-3.582 -3 -8 -3s-8 1.343 -8 3" /><path d="M4 6v6c0 1.657 3.582 3 8 3c1.075 0 2.1 -.08 3.037 -.224" /><path d="M20 12v-6" /><path d="M4 12v6c0 1.657 3.582 3 8 3c.166 0 .331 -.002 .495 -.006" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>',
        ],
    ];

    $plannedServices = [
        [
            'title' => 'Виготовлення пам’ятників',
            'text' => 'Плануємо окремий напрям з підбором майстрів, консультацією та оформленням замовлення через платформу.',
            'href' => '/in-dev.php?from=/prod-monuments.php',
            'iconClass' => 'service-mini-icon--slate',
        ],
        [
            'title' => 'Інші роботи',
            'text' => 'Сервіси благоустрою, сезонні роботи та додаткові послуги, які не обмежуються лише кладовищами.',
            'href' => '/in-dev.php?from=/other-job.php',
            'iconClass' => 'service-mini-icon--rose',
        ],
        [
            'title' => 'Співпраця з церквами',
            'text' => 'Майбутній напрям для зв’язку з релігійними громадами, замовлення обрядів та супровідних послуг.',
            'href' => '/in-dev.php?from=/church.php',
            'iconClass' => 'service-mini-icon--violet',
        ],
    ];

    $steps = [
        'Оберіть потрібну послугу або перейдіть у відповідний розділ.',
        'Заповніть форму, виберіть регіон чи виконавця та перевірте деталі.',
        'Підтвердьте замовлення або зверніться в підтримку, якщо потрібна допомога.',
    ];

    $out = '';
    $out .= '<div class="service-page">';
    $out .= '<div class="service-container">';

    $out .= '<section class="service-hero">';
    $out .= '<p class="service-hero-kicker">Сервіси платформи</p>';
    $out .= '<h1 class="service-title">Послуги</h1>';
    $out .= '<p class="service-lead">Оберіть потрібний напрям: уже доступні сервіси для догляду, роботи виконавця та створення нових записів, а також послуги, які готуються до запуску.</p>';
    $out .= '</section>';

    $out .= '<section class="service-quick-panel">';
    foreach ($quickActions as $item) {
        $out .= '<a class="service-quick-card" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">';
        $out .= '<span class="service-quick-card__icon">' . $item['icon'] . '</span>';
        $out .= '<span class="service-quick-card__title">' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<span class="service-quick-card__text">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '</a>';
    }
    $out .= '</section>';

    $out .= '<section class="service-section">';
    $out .= '<div class="service-section-head">';
    $out .= '<h2 class="service-section-title">Доступні послуги</h2>';
    $out .= '<p class="service-section-text">Основні напрямки, якими вже можна скористатися.</p>';
    $out .= '</div>';

    $out .= '<div class="service-card-list">';
    foreach ($availableServices as $item) {
        $out .= '<article class="service-card">';
        $out .= '<div class="service-card__icon ' . htmlspecialchars($item['iconClass'], ENT_QUOTES, 'UTF-8') . '">' . $item['icon'] . '</div>';
        $out .= '<div class="service-card__body">';
        $out .= '<div class="service-card__meta">' . htmlspecialchars($item['meta'], ENT_QUOTES, 'UTF-8') . '</div>';
        $out .= '<h3 class="service-card__title">' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
        $out .= '<p class="service-card__text service-card__text--desktop">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</p>';
        $out .= '<p class="service-card__text service-card__text--mobile">' . htmlspecialchars($item['textMobile'] ?? $item['text'], ENT_QUOTES, 'UTF-8') . '</p>';
        $out .= '</div>';
        $out .= '<a class="service-card__action" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['action'], ENT_QUOTES, 'UTF-8') . '</a>';
        $out .= '</article>';
    }
    $out .= '</div>';
    $out .= '</section>';

    $out .= '<section class="service-columns">';
    $out .= '<article class="service-info-card service-info-card--steps">';
    $out .= '<h2 class="service-section-title">Як це працює</h2>';
    $out .= '<ol class="service-steps">';
    foreach ($steps as $step) {
        $out .= '<li>' . htmlspecialchars($step, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $out .= '</ol>';
    $out .= '</article>';

    $out .= '<article class="service-info-card">';
    $out .= '<h2 class="service-section-title">Скоро на платформі</h2>';
    $out .= '<div class="service-mini-list">';
    foreach ($plannedServices as $item) {
        $out .= '<a class="service-mini-card" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">';
        $out .= '<span class="service-mini-card__icon ' . htmlspecialchars($item['iconClass'], ENT_QUOTES, 'UTF-8') . '"></span>';
        $out .= '<span class="service-mini-card__body">';
        $out .= '<span class="service-mini-card__title">' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '<span class="service-mini-card__text">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</span>';
        $out .= '</span>';
        $out .= '</a>';
    }
    $out .= '</div>';
    $out .= '</article>';
    $out .= '</section>';

    $out .= '<section class="service-support">';
    $out .= '<div class="service-support__copy">';
    $out .= '<p class="service-support__eyebrow">Потрібна допомога?</p>';
    $out .= '<h2 class="service-support__title">Можна почати з підтримки або FAQ</h2>';
    $out .= '<p class="service-support__text">Якщо маєте питання, зверніться в підтримку або перегляньте відповіді у FAQ.</p>';
    $out .= '</div>';
    $out .= '<div class="service-support__actions">';
    $out .= '<a href="/messenger.php?type=3" class="service-support__btn service-support__btn--primary">Відкрити підтримку</a>';
    $out .= '<a href="/faq.php" class="service-support__btn">Перейти до FAQ</a>';
    $out .= '</div>';
    $out .= '</section>';

    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

View_Clear();
View_Add(Page_Up('Послуги'));
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(ServicePage());
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
