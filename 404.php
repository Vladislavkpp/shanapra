<?php
/**
 * Сторінка помилки 404 — сторінку не знайдено
 * @var $md
 * @var $buf
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_destroy();
    header('Location: /');
    exit;
}

function Content(): string
{
    $requestUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
    $homeUrl = '/index.php';

    $out = '<div class="page-404">';
    $out .= '<div class="page-404__inner">';
    $out .= '<div class="page-404__icon">';
    $out .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
    $out .= '</div>';
    $out .= '<div class="page-404__code" aria-hidden="true">404</div>';
    $out .= '<h1 class="page-404__title">Сторінку не знайдено</h1>';
    $out .= '<p class="page-404__text">Запитана сторінка не існує або була переміщена. Перевірте адресу або поверніться на головну.</p>';
    if ($requestUri !== '') {
        $out .= '<div class="page-404__uri">' . $requestUri . '</div>';
    }
    $out .= '<div class="page-404__actions">';
    $out .= '<a href="' . $homeUrl . '" class="page-404__btn page-404__btn--primary">На головну</a>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

// === Вивід сторінки ===
View_Clear();
View_Add(Page_Up('Сторінка не знайдена — 404'));
View_Add('<link rel="stylesheet" href="/assets/css/404.css">');
View_Add(Menu_Up());
View_Add('<div class="out-index out-index--404">');
View_Add(Content());
View_Add('</div>');
//View_Add(Page_Down());
View_Out();
View_Clear();