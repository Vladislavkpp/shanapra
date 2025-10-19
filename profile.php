<?php

/**
 * @var $md
 * @var $buf
 */

require_once "function.php";

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add(Menu_Profile_Mobile());
View_Add('<div class="out">');
View_Add(Menu_Profile());

$message = '';
$messageType = '';

if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) {
    header('Location: /auth.php');
    exit;
}


$dblink = DbConnect();

// Смена фамилии
if ($md == 22 && isset($_POST['lname'])) {
    $a = $_POST['lname'];
    $sql = 'UPDATE users SET lname="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
    mysqli_query($dblink, $sql);
    $md = 2;
}

// Смена имени
if ($md == 23 && isset($_POST['fname'])) {
    $a = $_POST['fname'];
    $sql = 'UPDATE users SET fname="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
    mysqli_query($dblink, $sql);
    $md = 2;
}

// Смена пароля
if ($md == 24 && isset($_POST['pasw1'], $_POST['pasw2'])) {
    $oldPassword = $_POST['pasw1'];
    $newPassword = $_POST['pasw2'];

    $sql = 'SELECT pasw FROM users WHERE idx=' . $_SESSION['uzver'];
    $res = mysqli_query($dblink, $sql);
    $p = mysqli_fetch_assoc($res);

    if (md5($oldPassword) == $p['pasw']) {
        $sql = 'UPDATE users SET pasw="' . md5($newPassword) . '" WHERE idx=' . $_SESSION['uzver'];
        mysqli_query($dblink, $sql);
        $messageType = "alert-setting-success";
        $message = "Пароль успішно змінено!";
    } else {
        $messageType = "alert-setting-error";
        $message = "Старий пароль не співпадає!";
    }

    $md = 2;
}


// Обновление телефона
if ($md == 33 && isset($_POST['tel'])) {
    $a = $_POST['tel'];
    $sql = 'UPDATE users SET tel="' . $a . '" WHERE idx=' . $_SESSION['uzver'];
    mysqli_query($dblink, $sql);
    $md = 3;
}

// Загальна информация
if (($md == 0) || ($md == '')) {
    if ($_SESSION['logged'] == 1) {
        $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
        $res = mysqli_query($dblink, $sql);
        if (mysqli_num_rows($res) == 1) {
            $p = mysqli_fetch_assoc($res);
            View_Add('<div class="profile-container">');
View_Add('<div class="banner-profile">
</div>');
            View_Add('<div class="profile-card">');


            View_Add('<div class="avatar"><img class="avatar-image" src="' . ($p['avatar'] != '' ? $p['avatar'] : '/avatars/ava.png') . '"></div>');

            if (empty($p['fname']) && empty($p['lname'])) {
                View_Add('
                    <div class="profile-empty">
                        <p>Ви ще не вказали інформацію про себе. <a href="/profile.php/?md=2" class="profile-fill-link">Заповнити зараз</a></p>
                    </div>
                ');

                if (empty($p['tel'])) {
                    View_Add('
                        <div class="profile-emptyt">
                            <p>Ви ще не вказали номер телефону. <a href="/profile.php/?md=3" class="profile-fill-link">Додати телефон</a></p>
                        </div>
                    ');
                }


            } else {
                if (empty($p['tel'])) {
                    View_Add('
                        <div class="profile-empty tel-empty" style="margin:0;">
                            <p>Ви ще не вказали номер телефону. <a href="/profile.php/?md=3" class="profile-fill-link">Додати телефон</a></p>
                        </div>
                    ');
                }

                View_Add('<div class="profile-info" style="flex:1; display:flex; align-items:flex-start; gap:20px;">');
                View_Add(
                    '<div class="avatar-name-row">' .
                    '<div class="avatar-fullname">' . htmlspecialchars($p['lname']) . ' ' . htmlspecialchars($p['fname']) . '</div>' .
                    '<div class="avatar-followers">Підписників:</div>' .
                    '</div>'
                );






                View_Add('</div>');
            }

            View_Add('<hr class="profile-separator"></div>');
            View_Add('</div>');
        }
    }
}

//Настройки профиля
if ($md == 2 && $_SESSION['logged'] == 1) {
    $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
    $res = mysqli_query($dblink, $sql);
    if (mysqli_num_rows($res) == 1) {
        $p = mysqli_fetch_assoc($res);
        if (!empty($_SESSION['message'])) {
            $messageText = $_SESSION['message'];
            $messageType = $_SESSION['messageType'] ?? 'alert-error';
            unset($_SESSION['message'], $_SESSION['messageType']);
        }

        if (!empty($messageText)) {
            // Определяем стили в зависимости от типа
            if ($messageType === 'success') {
                $background = '#e6ffed';
                $border = '#28a745';
                $color = '#155724';
            } else {
                $background = '#ffe6e6';
                $border = '#dc3545';
                $color = '#721c24';
            }
        }

        if (!empty($messageText)) {
            View_Add('
        <div id="alert-file" class="alert-file" style="background: ' . $background . '; border: 1px solid ' . $border . '; color: ' . $color . ';">
            ' . htmlspecialchars($messageText) . '
        </div>

        <script>
        (function(){
            var overlay = document.getElementById("alert-file");
            if (!overlay) return;

            overlay.style.opacity = 0;
            overlay.style.transition = "opacity 0.5s ease";

            
            requestAnimationFrame(function(){
                overlay.style.opacity = 1;
            });

           
            setTimeout(function(){
                overlay.style.opacity = 0;
               
                setTimeout(function(){ overlay.remove(); }, 500);
            }, 5000);
        })();
        </script>
    ');
        }
    }

            View_Add('
            <div class="settings-block adaptive-block ' . (!empty($message) ? 'has-message' : '') . '">
                <h2 class="settings-title">Налаштування профілю</h2>
                <hr class="settings-divider">

                <h3 class="settings-podtitle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard" viewBox="0 0 16 16">
                        <path d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5"/>
                        <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z"/>
                    </svg>
                    Особиста інформація
                </h3>

                <form class="updatelname" action="/profile.php/?md=22" method="post">
                    <div class="input-row">
                        <input type="text" name="lname" class="namesettings" value="' . $p['lname'] . '">
                        <label>Прізвище</label>
                        <button type="submit" class="sub-sett-btn">Оновити</button>
                    </div>
                    <input type="hidden" name="md" value="22">
                </form>

                <form class="updatefname" action="/profile.php/?md=23" method="post">
                    <div class="input-row">
                        <input type="text" name="fname" class="namesettings" value="' . $p['fname'] . '">
                        <label>Ім`я</label>
                        <button type="submit" class="sub-sett-btn">Оновити</button>
                    </div>
                    <input type="hidden" name="md" value="23">
                </form>

                <div class="dividerset2"></div>

                <h3 class="settings-podtitle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-square" viewBox="0 0 16 16">
                        <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                        <path d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1v-1c0-1-1-4-6-4s-6 3-6 4v1a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z"/>
                    </svg>
                    Аватар профілю
                </h3>
        ');

            // Блок аватарки
            View_Add('
            <div class="avatar-settings">
                <div class="avatar-preview">
                    <img src="' . ($p['avatar'] != '' ? $p['avatar'] : '/avatars/ava.png') . '" alt="Аватар" class="user-avatar">
                </div>
                <div class="avatar-actions">
                    <button type="button" class="btn-avatar change-avatar">Змінити аватар</button>
        ');

            if ($p['avatar'] != '') {
                View_Add('
        <form action="/edit-avatar.php" method="post" style="display:inline;">
            <button type="submit" name="delete_avatar" class="btn-deleteava delete-avatar">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16">
        <path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5m-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5M4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06m6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528M8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5"/>
    </svg>
</button>
        </form>
    ');
            }


            View_Add('</div></div>');


            // Попап для аватаркти
            View_Add('
    <div id="avatar-popup" class="avatar-popup">
        <div class="avatar-popup-content">
            <div class="avatar-popup-header">
                <span class="avatar-popup-title">Виберіть зображення</span>
                <button class="avatar-popup-close">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
        <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
    </svg>
</button>

            </div>
            <hr class="avatar-popup-divider">

            <form action="/edit-avatar.php" method="post" enctype="multipart/form-data">
                <div class="avatar-popup-body">
                    <label for="avatar-input" class="avatar-upload-box">
                         <div class="avatar-upload-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0053a0" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 8h.01" />
            <path d="M12.5 21h-6.5a3 3 0 0 1-3-3v-12a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v6.5" />
            <path d="M3 16l5-5c.928-.893 2.072-.893 3 0l4 4" />
            <path d="M14 14l1-1c.67-.644 1.45-.824 2.182-.54" />
            <path d="M16 19h6" />
            <path d="M19 16v6" />
        </svg>
    </div>
                        <div class="avatar-upload-text">Завантажити зображення</div>
                    </label>
                    <input type="file" name="avatar" id="avatar-input" class="avatar-input" accept="image/*">
                </div>
                <div id="avatar-preview-popup" class="avatar-preview-popup" style="display:none;">
                    <img id="avatar-img" alt="Прев`ю" />
                    <div class="avatar-preview-buttons">
                      <button type="submit" class="avatar-save-btn">Зберегти</button>
                      <button type="button" id="avatar-cancel-btn" class="avatar-cancel-btn">Скасувати</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
');


            View_Add('
            <script>
document.addEventListener("DOMContentLoaded", function() {
    const popup = document.getElementById("avatar-popup");
    const closeBtn = popup.querySelector(".avatar-popup-close");
    const input = document.getElementById("avatar-input");
    const previewWrapper = document.getElementById("avatar-preview-popup");
    const previewImg = document.getElementById("avatar-img");
    const uploadBox = popup.querySelector(".avatar-upload-box");
    const cancelBtn = document.getElementById("avatar-cancel-btn");

    // Открытие попапа
    document.querySelectorAll(".change-avatar").forEach(btn => {
        btn.addEventListener("click", () => {
            popup.classList.add("show");
            previewWrapper.style.display = "none";
            uploadBox.style.display = "flex";
            input.value = "";
        });
    });

   
    closeBtn.addEventListener("click", () => popup.classList.remove("show"));

   
    popup.addEventListener("click", e => {
        if (e.target === popup) popup.classList.remove("show");
    });

 
    input.addEventListener("change", function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewWrapper.style.display = "flex";
                uploadBox.style.display = "none";
            }
            reader.readAsDataURL(this.files[0]);
        } else {
            previewWrapper.style.display = "none";
            uploadBox.style.display = "flex";
        }
    });

    cancelBtn.addEventListener("click", () => {
        previewWrapper.style.display = "none";
        uploadBox.style.display = "flex";
        input.value = "";  
        previewImg.src = ""; 
    });
});
</script>
        ');

            View_Add('
            <div class="dividerset2"></div>
            <h3 class="password-title">Зміна паролю</h3>
            <form class="updatepasw" action="/profile.php/?md=24" method="post">
                <div class="floating-input">
                    <input type="password" name="pasw1" class="paswsettings" placeholder=" ">
                    <label>Старий пароль</label>
                </div>
                <div class="floating-input">
                    <input type="password" name="pasw2" class="paswsettings" placeholder=" ">
                    <label>Новий пароль</label>
                </div>
                ' . (!empty($message) ? '<div class="alert-setting ' . $messageType . '">' . $message . '</div>' : '') . '
                <button type="submit" class="sub-sett-btn">Оновити</button>
                <input type="hidden" name="md" value="24">
            </form>
        </div>
        ');
        }



// Контактная информация
if ($md == 3 && $_SESSION['logged'] == 1) {
    $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
    $res = mysqli_query($dblink, $sql);
    if (mysqli_num_rows($res) == 1) {
        $p = mysqli_fetch_assoc($res);

        View_Add('
            <div class="contact-card">
                <h2 class="contact-title">Контактна інформація</h2>
                <hr class="contact-divider">
                <form class="updatetel" action="/profile.php/?md=33" method="post">
                    <label for="tel" class="contact-label">Номер телефону</label>
                    <div class="tel-row">
                        <input type="text" name="tel" id="tel" class="contact-input" value="' . $p['tel'] . '">
                        <input type="hidden" name="md" value="33">
                        <button type="submit" class="contact-btn">Оновити</button>
                    </div>
                </form>
                <div class="email-display">
                    <label class="contact-label">Електронна пошта</label>
                    <div class="email-value">' . $p['email'] . '</div>
                </div>
            </div>
        ');
    }
}

// Финансовая информация
if ($md == 4 && $_SESSION['logged'] == 1) {
    $sql = 'SELECT * FROM users WHERE idx=' . $_SESSION['uzver'];
    $res = mysqli_query($dblink, $sql);
    if (mysqli_num_rows($res) == 1) {
        $p = mysqli_fetch_assoc($res);

        $formattedCash = number_format($p['cash'], 0, '', '.');
        $formattedInnerCurrency = number_format($p['rest'], 0, '', '.');
        $formattedIncome = number_format($p['cash'], 0, '', '.');

        View_Add('
            <div class="wallet-full-wrapper">
                <div class="wallet-container">
                    <div class="wallet-header">
                        <img src="/assets/images/wallet.jpg" alt="Wallet Icon" class="wallet-icon">
                        <div class="wallet-texts">
                            <span class="wallet-title">Мій гаманець</span>
                            <span class="wallet-subtitle">Вітаємо!</span>
                        </div>
                    </div>
                    <div class="wallet-balance-box">
                        <div class="balance-header">
                            <img src="/assets/images/balance.jpg" alt="Balance Icon" class="balance-icon">
                            <span class="balance-label">Основний баланс</span>
                        </div>
                        <div class="balance-amount">₴ ' . $formattedCash . '</div>
                        <hr class="balance-divider">
                        <div class="balance-header" style="margin-top: 12px;">
                            <img src="/assets/images/val2.png" alt="" class="balance-icon">
                            <span class="balance-label">Внутрішня валюта</span>
                        </div>
                        <div class="balance-amount">
                            <img src="/assets/images/ratekrest.png" alt="" style="width: 24px; vertical-align: middle; margin-right: -5px; margin-left: -3px; margin-top: -5px;">
                            ' . $formattedInnerCurrency . '
                        </div>
                        <button class="wallet-topup-btn">Поповнити</button>
                    </div>
                </div>
                <div class="finance-summary">
                    <div class="finance-card income">
                        <span class="finance-title">Доходи</span>
                        <span class="finance-amount">₴ ' . $formattedIncome . '</span>
                    </div>
                    <div class="finance-card expenses">
                        <span class="finance-title">Витрати</span>
                        <span class="finance-amount">₴ 0</span>
                    </div>
                </div>
            </div>
        ');
    }
}

// Меню профиля
function Menu_Profile(): string
{
    $currentMd = isset($_GET['md']) ? (int)$_GET['md'] : 0;

    $links = [
        0 => 'Загальна інформація',
        2 => 'Налаштування профілю',
        3 => 'Контактна інформація',
        4 => 'Фінансова інформація',
        5 => 'Додаткове'
    ];

    $a1 = '<div class="Menu_Profile">';

    foreach ($links as $md => $title) {
        $activeClass = ($md === $currentMd) ? 'active' : '';
        $a1 .= '<a href="profile.php?md='.$md.'" class="menu-link '.$activeClass.'">'.$title.'</a>';
        if ($md === 2 || $md === 4) {
            $a1 .= '<div class="divider"></div>';
        }
    }

    $a1 .= '<a href="profile.php?exit=1" class="logout-btn">Вийти</a>';
    $a1 .= '</div>';

    return $a1;
}


function Menu_Profile_Mobile(): string
{
    return '
    <button class="profile-menu-btn" id="openProfileMenu">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-columns-gap" viewBox="0 0 16 16">
            <path d="M6 1v3H1V1zM1 0a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1zm14 12v3h-5v-3zm-5-1a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1zM6 8v7H1V8zM1 7a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1zm14-6v7h-5V1zm-5-1a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1z"/>
        </svg> Меню профілю
    </button>

    <div id="profileMenu" class="profile-menu-overlay">
        <span class="close-btn" id="closeProfileMenu">&times;</span>
        <div class="profile-menu-title">Меню профілю</div>
        <hr class="profile-menu-separator">
        <div class="profile-menu-list">
            <a class="profile-menu-item" href="profile.php?md=0">Загальна інформація</a>
            <a class="profile-menu-item" href="profile.php?md=2">Налаштування профілю</a>
            <a class="profile-menu-item" href="profile.php?md=3">Контактна інформація</a>
            <a class="profile-menu-item" href="profile.php?md=4">Фінансова інформація</a>
            <a class="profile-menu-item" href="profile.php?md=5">Додаткове</a>
        </div>
        <hr class="profile-menu-separator">
        <a class="profile-menu-logout-btn" href="profile.php?exit=1">Вихід</a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const openBtn = document.getElementById("openProfileMenu");
            const closeBtn = document.getElementById("closeProfileMenu");
            const menu = document.getElementById("profileMenu");

            if (openBtn && closeBtn && menu) {
                openBtn.addEventListener("click", function() { menu.style.display = "flex"; });
                closeBtn.addEventListener("click", function() { menu.style.display = "none"; });
            }
        });
    </script>
    ';
}

if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_start();
    $_SESSION = [];
    session_destroy();
    header("Location: /index.php");
    exit;
}

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();
