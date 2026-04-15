<?php
/**
 * Сторінка «Контактна інформація»
 */
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

function ContactPage(): string
{
    $supportUrl = '/messenger.php?type=3';
    $faqUrl = '/faq.php';
    $isLogged = isset($_SESSION['logged']) && $_SESSION['logged'] == 1;

    $iconFaq = '<svg class="contact-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.875 6.27c.7 .398 1.13 1.143 1.125 1.948v7.284c0 .809 -.443 1.555 -1.158 1.948l-6.75 4.27a2.269 2.269 0 0 1 -2.184 0l-6.75 -4.27a2.225 2.225 0 0 1 -1.158 -1.948v-7.285c0 -.809 .443 -1.554 1.158 -1.947l6.75 -3.98a2.33 2.33 0 0 1 2.25 0l6.75 3.98h-.033" /><path d="M12 16v.01" /><path d="M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483" /></svg>';
    $iconChat = '<svg class="contact-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 14l-3 -3h-7a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1h9a1 1 0 0 1 1 1v10" /><path d="M14 15v2a1 1 0 0 1 -1 1h-7l-3 3v-10a1 1 0 0 1 1 -1h2" /></svg>';
    $iconEmail = '<svg class="contact-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" /></svg>';

    $out = '';
    $out .= '<div class="contact-page">';
    $out .= '<div class="contact-container">';

    $out .= '<section class="contact-hero">';
    $out .= '<h1 class="contact-title">Контактна інформація</h1>';
    $out .= '<p class="contact-lead">Питання, пропозиції та технічна підтримка в одному місці.</p>';
    $out .= '</section>';

    $out .= '<div class="contact-grid">';

    $out .= '<article class="contact-tile">';
    $out .= '<span class="contact-tile-icon">' . $iconFaq . '</span>';
    $out .= '<h2 class="contact-tile-heading">Запитання й відповіді</h2>';
    $out .= '<p class="contact-tile-text">Отримайте відповіді на найпоширеніші питання щодо реєстрації, пошуку поховань, тощо.</p>';
    $out .= '<a href="' . htmlspecialchars($faqUrl) . '" class="contact-tile-btn">Перейти до FAQ</a>';
    $out .= '</article>';

    $out .= '<article class="contact-tile">';
    $out .= '<span class="contact-tile-icon">' . $iconChat . '</span>';
    $out .= '<h2 class="contact-tile-heading">Онлайн-чат підтримки</h2>';
    $out .= '<p class="contact-tile-text">Найшвидший спосіб отримати допомогу. Чат із підтримкою — відповіді протягом робочого дня.</p>';
    $out .= '<a href="' . htmlspecialchars($supportUrl) . '" class="contact-tile-btn">Відкрити чат</a>';
    $out .= '</article>';

    $out .= '<article class="contact-tile">';
    $out .= '<span class="contact-tile-icon">' . $iconEmail . '</span>';
    $out .= '<h2 class="contact-tile-heading">Електронна пошта</h2>';
    $out .= '<p class="contact-tile-text">Для офіційних запитів, скарг та детальних питань. Листи переглядаємо щодня.</p>';
    $out .= '<a href="mailto:contacts@shanapra.com" class="contact-tile-btn contact-tile-btn-email">contacts@shanapra.com</a>';
    $out .= '</article>';

    $out .= '</div>';

    $out .= '<div class="contact-footer-note">';
    if ($isLogged) {
        $out .= '<p class="contact-footer-text">Потрібна допомога? Скористайтеся розділом <a href="' . htmlspecialchars($supportUrl) . '" class="contact-inline-link">Підтримка</a> або перегляньте <a href="' . htmlspecialchars($faqUrl) . '" class="contact-inline-link">FAQ</a> з відповідями на часті запитання.</p>';
    } else {
        $out .= '<p class="contact-footer-text">Потрібна допомога? <a href="/auth.php" class="contact-inline-link">Увійдіть</a> в обліковий запис або натисніть <a href="' . htmlspecialchars($supportUrl) . '" class="contact-inline-link">Підтримка</a> у верхньому меню — напишіть нам у будь-який час.</p>';
    }
    $out .= '</div>';

    $out .= '</div>';
    $out .= '</div>';
    return $out;
}

View_Clear();
View_Add(Page_Up('Контактна інформація'));
View_Add(Menu_Up());

View_Add('<div class="out">');

View_Add(ContactPage());
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
