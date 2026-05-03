<?php
/**
 * Сторінка «В розробці» — тимчасово недоступно
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_destroy();
    header('Location: /');
    exit;
}

// === Вивід сторінки ===
View_Clear();
View_Add(Page_Up('Сторінка в розробці'));
View_Add('<link rel="stylesheet" href="/assets/css/in-dev.css">');
View_Add(Menu_Up());
View_Add('<div class="out-index out-index--404">');
View_Add(InDev_Content());
View_Add('</div>');
View_Out();
View_Clear();
