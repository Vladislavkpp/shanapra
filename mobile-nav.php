<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

function MobileNavPage(): string
{
    global $hide_page_down;

    $device = function_exists('detectDevice')
        ? detectDevice($_SERVER['HTTP_USER_AGENT'] ?? '')
        : ['type' => 'desktop'];

    if (($device['type'] ?? 'desktop') !== 'mobile') {
        $hide_page_down = true;
        return '<link rel="stylesheet" href="/assets/css/in-dev.css"><div class="out-index out-index--404">' .
            MobileOnly_Content('/', 'Головна') .
            '</div>';
    }

    $groups = [
        [
            'title' => 'Інформація',
            'text' => 'Основні сторінки з описом платформи, правилами роботи та способами зв’язку.',
            'items' => [
                [
                    'title' => 'Про нас',
                    'text' => 'Що таке платформа, як вона працює та куди рухається далі.',
                    'href' => '/about_us.php',
                    'iconClass' => 'mobile-nav-card-icon--blue',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 9h.01" /><path d="M11 12h1v4h1" /></svg>',
                ],
                [
                    'title' => 'FAQ',
                    'text' => 'Відповіді на найпоширеніші питання про сервіс.',
                    'href' => '/faq.php',
                    'iconClass' => 'mobile-nav-card-icon--teal',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M11 15a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M18.5 18.5l2.5 2.5" /><path d="M4 6h16" /><path d="M4 12h4" /><path d="M4 18h4" /></svg>',
                ],
                [
                    'title' => 'Контакти',
                    'text' => 'Канали для звернення та зв’язку з командою.',
                    'href' => '/contacts.php',
                    'iconClass' => 'mobile-nav-card-icon--gold',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" /></svg>',
                ],
            ],
        ],
        [
            'title' => 'Пошук',
            'text' => 'Швидкі переходи до пошуку поховань і кладовищ у системі.',
            'items' => [
                [
                    'title' => 'Пошук поховань',
                    'text' => 'Пошук записів за ПІБ, датами та іншими даними.',
                    'href' => '/searchx.php',
                    'iconClass' => 'mobile-nav-card-icon--slate',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h1.5" /><path d="M15 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M20.2 20.2l1.8 1.8" /></svg>',
                ],
                [
                    'title' => 'Пошук кладовищ',
                    'text' => 'Пошук кладовищ за регіоном, районом і назвою.',
                    'href' => '/searchcem.php',
                    'iconClass' => 'mobile-nav-card-icon--rose',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M11 18l-2 -1l-6 3v-13l6 -3l6 3l6 -3v7.5" /><path d="M9 4v13" /><path d="M15 7v5" /><path d="M15 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M20.2 20.2l1.8 1.8" /></svg>',
                ],
            ],
        ],
        [
            'title' => 'Додавання',
            'text' => 'Швидкі переходи для створення нових записів у системі.',
            'items' => [
                [
                    'title' => 'Додати поховання',
                    'text' => 'Створення нового запису поховання з основними даними.',
                    'href' => '/graveaddform.php',
                    'iconClass' => 'mobile-nav-card-icon--gold',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 6c0 1.657 3.582 3 8 3s8 -1.343 8 -3s-3.582 -3 -8 -3s-8 1.343 -8 3" /><path d="M4 6v6c0 1.657 3.582 3 8 3c1.075 0 2.1 -.08 3.037 -.224" /><path d="M20 12v-6" /><path d="M4 12v6c0 1.657 3.582 3 8 3c.166 0 .331 -.002 .495 -.006" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>',
                ],
                [
                    'title' => 'Додати кладовище',
                    'text' => 'Окремий розділ для створення нового кладовища.',
                    'href' => '/searchcem/addcemetery',
                    'iconClass' => 'mobile-nav-card-icon--teal',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 18.5l-3 -1.5l-6 3v-13l6 -3l6 3l6 -3v8.5" /><path d="M9 4v13" /><path d="M15 7v8" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>',
                ],
            ],
        ],
        [
            'title' => 'Додаткові розділи',
            'text' => 'Напрями, які розвиваються окремо та не винесені в нижню навігацію.',
            'badge' => 'В розробці',
            'items' => [
                [
                    'title' => 'Пам’ятники',
                    'text' => 'Окремий напрям платформи для послуг із пам’ятниками.',
                    'href' => '/in-dev.php?from=/prod-monuments.php',
                    'iconClass' => 'mobile-nav-card-icon--violet',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 18l2 -13l2 -2l2 2l2 13" /><path d="M5 21v-3h14v3" /><path d="M3 21l18 0" /></svg>',
                ],
                [
                    'title' => 'Інші роботи',
                    'text' => 'Додаткові послуги та роботи в окремому розділі.',
                    'href' => '/in-dev.php?from=/other-job.php',
                    'iconClass' => 'mobile-nav-card-icon--rose',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12h18" /><path d="M12 3v18" /><path d="M7 7l10 10" /><path d="M17 7l-10 10" /></svg>',
                ],
                [
                    'title' => 'Церкви',
                    'text' => 'Майбутній розділ для взаємодії з громадами та обрядами.',
                    'href' => '/in-dev.php?from=/church.php',
                    'iconClass' => 'mobile-nav-card-icon--blue',
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M10 21v-4a2 2 0 0 1 4 0v4" /><path d="M10 5l4 0" /><path d="M12 3l0 5" /><path d="M6 21v-7m-2 2l8 -8l8 8m-2 -2v7" /></svg>',
                ],
            ],
        ],
    ];

    $out = '';
    $out .= '<div class="mobile-nav-page">';
    $out .= '<div class="mobile-nav-container">';
    $out .= '<section class="mobile-nav-hero">';
    $out .= '<p class="mobile-nav-kicker">Навігація сайтом</p>';
    $out .= '<h1 class="mobile-nav-title">Розділи сайту</h1>';
    $out .= '</section>';

    foreach ($groups as $group) {
        $out .= '<section class="mobile-nav-section">';
        $out .= '<div class="mobile-nav-section-head">';
        $out .= '<div class="mobile-nav-section-head-top">';
        $out .= '<h2 class="mobile-nav-section-title">' . htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
        if (!empty($group['badge'])) {
            $out .= '<span class="mobile-nav-section-badge">' . htmlspecialchars($group['badge'], ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="mobile-nav-grid">';

        foreach ($group['items'] as $item) {
            $out .= '<a class="mobile-nav-card" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">';
            $out .= '<span class="mobile-nav-card__icon ' . htmlspecialchars($item['iconClass'], ENT_QUOTES, 'UTF-8') . '">' . $item['icon'] . '</span>';
            $out .= '<span class="mobile-nav-card__body">';
            $out .= '<span class="mobile-nav-card__title">' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '</span>';
            $out .= '<span class="mobile-nav-card__text">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</span>';
            $out .= '</span>';
            $out .= '</a>';
        }

        $out .= '</div>';
        $out .= '</section>';
    }

    $out .= '<section class="mobile-nav-support">';
    $out .= '<div class="mobile-nav-support__copy">';
    $out .= '<p class="mobile-nav-support__eyebrow">Потрібна допомога?</p>';
    $out .= '<h2 class="mobile-nav-support__title">Не знайшли потрібний розділ?</h2>';
    $out .= '<p class="mobile-nav-support__text">Можна відкрити підтримку або перейти до FAQ, якщо потрібна підказка по навігації сайтом.</p>';
    $out .= '</div>';
    $out .= '<div class="mobile-nav-support__actions">';
    $out .= '<a href="/messenger.php?type=3" class="mobile-nav-support__btn mobile-nav-support__btn--primary">Відкрити підтримку</a>';
    $out .= '<a href="/faq.php" class="mobile-nav-support__btn">Перейти до FAQ</a>';
    $out .= '</div>';
    $out .= '</section>';

    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

View_Clear();
View_Add(Page_Up('Розділи сайту'));
View_Add('<link rel="stylesheet" href="/assets/css/404.css">');
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(MobileNavPage());
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
