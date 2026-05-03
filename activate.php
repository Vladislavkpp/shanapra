<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

$hide_page_down = true;

$dblink = DbConnect();
$token = trim((string)($_GET['token'] ?? ''));
$isSuccess = false;
$pageTitle = 'Активація облікового запису';
$subtitle = 'Підтверджуємо посилання з листа та оновлюємо статус вашого акаунта.';
$message = '';
$stateClass = 'activation-container--error';

if ($token === '') {
    $pageTitle = 'Посилання недійсне';
    $subtitle = 'Схоже, посилання для активації неповне або пошкоджене.';
    $message = 'Перевірте адресу з листа або замовте новий лист для активації.';
} else {
    $act = $dblink->prepare("
        SELECT idx
        FROM users
        WHERE token = ?
          AND activ = 0
        LIMIT 1
    ");

    if (!$act) {
        $pageTitle = 'Не вдалося активувати акаунт';
        $subtitle = 'Сталася технічна помилка під час перевірки посилання.';
        $message = 'Спробуйте відкрити лист ще раз трохи пізніше.';
    } else {
        $act->bind_param("s", $token);
        $act->execute();
        $act->store_result();
        $act->bind_result($userIdx);

        if ($act->fetch()) {
            $upd = $dblink->prepare("
                UPDATE users
                SET activ = 1,
                    token = NULL
                WHERE idx = ?
                LIMIT 1
            ");

            if ($upd) {
                $upd->bind_param("i", $userIdx);
                if ($upd->execute()) {
                    $isSuccess = true;
                    if (function_exists('createUserNotification')) {
                        createUserNotification(
                            (int)$userIdx,
                            'Акаунт активовано',
                            'Ваш акаунт підтверджено. Тепер ви можете користуватися всіма можливостями сервісу.',
                            'account',
                            'normal',
                            '/profile.php',
                            'Відкрити профіль',
                            'activation',
                            null,
                            null,
                            null,
                            null,
                            1,
                            $dblink
                        );
                    }
                    $pageTitle = 'Ваш акаунт активовано';
                    $subtitle = 'Пошта підтверджена. Тепер можна повноцінно користуватися обліковим записом.';
                    $message = 'Активацію завершено успішно.';
                    $stateClass = 'activation-container--success';
                } else {
                    $pageTitle = 'Не вдалося активувати акаунт';
                    $subtitle = 'Сервер не зміг оновити статус облікового запису.';
                    $message = 'Спробуйте перейти за посиланням ще раз або запросіть новий лист.';
                }
                $upd->close();
            } else {
                $pageTitle = 'Не вдалося активувати акаунт';
                $subtitle = 'Сервер не зміг підготувати оновлення даних.';
                $message = 'Спробуйте перейти за посиланням ще раз трохи пізніше.';
            }
        } else {
            $pageTitle = 'Посилання недійсне';
            $subtitle = 'Таке посилання вже використане або більше не дійсне.';
            $message = 'Якщо акаунт ще не активовано, запросіть новий лист у профілі або при вході.';
        }

        $act->close();
    }
}

$isLoggedIn = !empty($_SESSION['logged']) && (int)$_SESSION['logged'] === 1;
$primaryHref = $isSuccess
    ? ($isLoggedIn ? '/profile.php' : '/auth.php')
    : ($isLoggedIn ? '/profile.php' : '/auth.php');
$primaryLabel = $isSuccess
    ? ($isLoggedIn ? 'Перейти в профіль' : 'Увійти в акаунт')
    : ($isLoggedIn ? 'Повернутися в профіль' : 'Перейти до входу');
$stateIcon = $isSuccess
    ? '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>'
    : '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 9v4"></path><path d="M12 16v.01"></path><path d="M5.07 19h13.86a2 2 0 0 0 1.75 -2.98l-6.93 -12.02a2 2 0 0 0 -3.5 0l-6.93 12.02a2 2 0 0 0 1.75 2.98"></path></svg>';

mysqli_close($dblink);

View_Clear();
View_Add(Page_Up('Активація облікового запису'));
View_Add(Menu_Up());
View_Add('<link rel="stylesheet" href="/assets/css/common.css">');
View_Add('<div class="out-active">');
View_Add('
<section class="activation-container ' . $stateClass . '">
    <div class="activation-card">
        <span class="activation-icon">' . $stateIcon . '</span>
        <span class="activation-kicker">Підтвердження email</span>
        <h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>
        <p class="activation-subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>
        <div class="activation-message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>
        <div class="activation-actions">
            <a class="activation-action" href="' . htmlspecialchars($primaryHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($primaryLabel, ENT_QUOTES, 'UTF-8') . '</a>
            <a class="activation-action activation-action--secondary" href="/">На головну</a>
        </div>
    </div>
</section>
');
View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
