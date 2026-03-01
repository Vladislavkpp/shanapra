<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @var $md
 */
require_once "function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/validator.php";

if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    header('Location: /profile.php');
    exit;
}

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');

$em = '';
$ep = '';
$lname = '';
$fname = '';

$errorMsg = '';
$successMsg = '';

if ($md == 10) {
    $em = $_POST['email'] ?? '';
    $ep = $_POST['password'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $fname = $_POST['fname'] ?? '';

    $emError = '';
    $epError = '';
    $fnameError = '';
    $lnameError = '';

    $em1 = valide1($em, 'email', $emError);
    $ep1 = valide1($ep, 'password', $epError);

    $fname1 = trim($fname);
    $lname1 = trim($lname);

    $lettersPattern = '/^[A-Za-zА-Яа-яІіЇїЄєЁё\'\-\s]{2,}$/u';

    if (!preg_match($lettersPattern, $fname1)) {
        $fnameError = 'Некоректне ім\'я';
    }

    if (!preg_match($lettersPattern, $lname1)) {
        $lnameError = 'Некоректне прізвище';
    }

    if ($em1 !== '' && $ep1 !== '' && $fnameError === '' && $lnameError === '') {
        $dblink = DbConnect();

        $emEscaped = mysqli_real_escape_string($dblink, $em1);
        $fnameEscaped = mysqli_real_escape_string($dblink, $fname1);
        $lnameEscaped = mysqli_real_escape_string($dblink, $lname1);

        // Проверяем есть ли уже пользователь с такой почтой
        $res = mysqli_query($dblink, 'SELECT idx FROM users WHERE email="' . $emEscaped . '"');

        if (mysqli_num_rows($res) > 0) {
            $errorMsg = "Користувач уже зареєстрований";
        } else {
            $passhash = md5($ep1);
            $sql = 'INSERT INTO users (email, pasw, fname, lname)
                    VALUES ("' . $emEscaped . '", "' . $passhash . '", "' . $fnameEscaped . '", "' . $lnameEscaped . '")';
            $ok = mysqli_query($dblink, $sql);

            if ($ok) {
                $userId = mysqli_insert_id($dblink);

                $_SESSION['logged'] = 1;
                $_SESSION['uzver'] = $userId;
                $_SESSION['last_activity'] = time();

                $successMsg = "Реєстрація успішна!";
                echo '<meta http-equiv="refresh" content="2;url=/profile.php">';
            } else {
                $errorMsg = "Помилка при збереженні користувача";
            }
        }

        mysqli_close($dblink);
    } else {
        $errorMsg = $emError ?: $epError ?: $fnameError ?: $lnameError ?: "Заповніть усі поля";
    }
}

View_Add('
<div class="regform-container ' .
    (!empty($errorMsg) ? 'has-error' : (!empty($successMsg) ? 'has-success' : '')) . '">
    <form action="/stregs.php" method="post">
        <input type="hidden" name="md" value="10">
        <div class="regform-title regform-text-center">Реєстрація користувача</div>
       
        <div class="regform-row fl">
            <div class="regform-input-container">
                <input class="regform-input" id="regLname" name="lname" type="text" value="' . htmlspecialchars($lname) . '" placeholder=" " required>
                <label for="regLname">Прізвище</label>
            </div>
            <div class="regform-input-container">
                <input class="regform-input" id="regFname" name="fname" type="text" value="' . htmlspecialchars($fname) . '" placeholder=" " required>
                <label for="regFname">Ім\'я</label>
            </div>
        </div>

        <div class="regform-row regform-vertical">
            <div class="regform-input-container">
                <input class="regform-input" id="regEmail" name="email" type="email" value="' . htmlspecialchars($em) . '" placeholder=" " required>
                <label for="regEmail">E-mail</label>
            </div>
        </div>
        
       <div class="regform-row regform-vertical">
    <div class="regform-input-container" style="position: relative;">
        <input class="regform-input" id="regPass" name="password" type="password" value="" placeholder=" " required>
        <label for="regPass">Пароль</label>

        <button type="button" id="togglePass" class="toggle-eye-btn" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0;">
            <svg class="toggle-eye-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
            </svg>
        </button>
    </div>
</div>

<script>
document.addEventListener(\'DOMContentLoaded\', () => {
    const toggleBtn = document.getElementById(\'togglePass\');
    const passwordInput = document.getElementById(\'regPass\');

    const eyeSVG = `<svg class="toggle-eye-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>`;

    const eyeOffSVG = `<svg class="toggle-eye-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.585 10.587a2 2 0 0 0 2.829 2.828" /><path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87" /><path d="M3 3l18 18" /></svg>`;

    toggleBtn.addEventListener(\'click\', () => {
        if (passwordInput.type === \'password\') {
            passwordInput.type = \'text\';
            toggleBtn.innerHTML = eyeOffSVG;
        } else {
            passwordInput.type = \'password\';
            toggleBtn.innerHTML = eyeSVG;
        }
    });
});
</script>

        ' . (!empty($errorMsg) ? '<div class="regerror1">' . $errorMsg . '</div>' : '') . '
        ' . (!empty($successMsg) ? '<div class="regsuccess1">' . $successMsg . '</div>' : '') . '

        <div class="regform-row regform-vertical but">
            <input class="regform-button" type="submit" value="Зареєструватися">
        </div>
        <div class="regform-row regform-center regform-login-line a">
            <span>Вже зареєстровані? <a class="regform-reg-link" href="/auth.php">Авторизуватися</a></span>
        </div>
    </form>
</div>
');

View_Add('</div>'); // .out

View_Add(Page_Down());
View_Out();
View_Clear();
