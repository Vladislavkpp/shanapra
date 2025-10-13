<?php
session_start();
$auth_lifetime = 1800;
$auth_cookie_name = 'user_auth';

//Выход
if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_unset();
    session_destroy();

    if (isset($_COOKIE[$auth_cookie_name])) {
        setcookie($auth_cookie_name, '', time() - 3600, '/');
        unset($_COOKIE[$auth_cookie_name]);
    }

    header("Location: /");
    exit;
}

if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
    setcookie($auth_cookie_name, $_SESSION['uzver'], time() + $auth_lifetime, '/');
}

if ((!isset($_SESSION['logged']) || $_SESSION['logged'] != 1) && isset($_COOKIE[$auth_cookie_name])) {
    $_SESSION['logged'] = 1;
    $_SESSION['uzver'] = $_COOKIE[$auth_cookie_name];
}


const xbr = "\n";
const no_grave_photo = '/graves/no_image_0.png';
$buf = '';
$dblink = null;
$start = getmicrotime();
$md = isset($_GET['md']) ? $_GET['md'] : '';
$md = isset($_POST['md']) ? $_POST['md'] : $md;
$md = strtolower($md);
$ip = $_SERVER['REMOTE_ADDR'];
$login = '';
$password = '';
$loginix = 0;
$today = date('d-m-Y', time());
$todaysql = date('Y-m-d', time());
$todaydot = date('d.m.Y', time());
$yestertodaydot = date('d.m.Y', time() - 86400);


/*Для обработки выхода с каждой страницы*/
if (isset($_GET['exit']) && $_GET['exit'] == 1) {
    session_destroy();
    header("Location: /index.php");
    exit;
}

function rus2lat($string)
{
    $rus = array('ё', 'ж', 'ц', 'ч', 'ш', 'щ', 'ю', 'я', 'Ё', 'Ж', 'Ц', 'Ч', 'Ш', 'Щ', 'Ю', 'Я', 'Ъ', 'Ь', 'ъ', 'ь');
    $lat = array('e', 'zh', 'c', 'ch', 'sh', 'sh', 'ju', 'ja', 'E', 'ZH', 'C', 'CH', 'SH', 'SH', 'JU', 'JA', '', '', '', '');
    $string = str_replace($rus, $lat, $string);
    return strtr($string, "АБВГДЕЗИЙКЛМНОПРСТУФХЫЭабвгдезийклмнопрстуфхыэ ", "ABVGDEZIJKLMNOPRSTUFHIEabvgdezijklmnoprstufhie_");
}

function getmicrotime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function warn($x = ''): string
{
    return '<div class="warn">' . $x . '</div>';
}

/**Створює підключення до бази даних
 * @return int|null|bool|mysqli
 */
function DbConnect(): int|null|bool|mysqli
{

    $db = 'bicycleb_shana';
    $dbuser = 'bicycleb_shanarw';
    $dbpassword = '@komnata44';
    $dblink = mysqli_connect("localhost", $dbuser, $dbpassword, $db);
    mysqli_query($dblink, "set character_set_client='utf8'");
    mysqli_query($dblink, "set character_set_results='utf8'");
    mysqli_query($dblink, "set collation_connection='utf8_general_ci'");


    return $dblink;
}

function View_Out()
{global $start;
/*
    $end = getmicrotime();
    $elapsed = $end - $start;

    $seconds = floor($elapsed);
    $milliseconds = round(($elapsed - $seconds) * 1000);

    echo '<div class="execution-time">';
    echo 'Сторінку завантажено за: ' . $seconds . ' сек ' . $milliseconds . ' мс';
    echo '</div>';
*/
    echo View_Get();
    View_Clear();
}

function View_Get(): string
{
    global $buf;
    return $buf;
}

/**Підготовлює буфер сторінки для нового виводу
 */
function View_Clear()
{
    global $buf;
    $buf = '';
}

function View_Add(null|string $a = '')
{
    global $buf;
    $buf .= $a;
}

function Menu_Up(): string {
    $out = '<div class="Menu_Up">';

    $out .= '<div class="menu-left">';

    $out .= '<div class="burger"><span></span><span></span><span></span></div>';
    $out .= '<div class="logo"><a href="/"><img src="/assets/images/logobrand3.png" alt="Логотип"></a></div>';
    $out .= '<a href="/" class="title">ІПС Шана</a>';
    $out .= '</div>';

    $out .= '<div class="menu-divider"></div>'; // вертикальная линия

    // Центральное меню
    $out .= '<div class="menu-center">';
    $out .= '<ul class="menu_ups"> 
        <li><a href="/">Головна</a></li>
        <li><a href="/workshana.php">Робота</a> 
            <ul class="submenu"> 
                <li><a href="/clean-cemeteries.php">Прибирання кладовищ</a></li>
                <li><a href="/prod-monuments.php">Виготовлення пам\'ятників</a></li>
                <li><a href="/other-job.php">Інші роботи</a></li>
            </ul>
        </li>
        <li><a href="/church.php">Церкви</a></li>
        <li><a href="/clients.php">Наші клієнти</a></li>
        <li><a href="/graveadd.php">Додати поховання</a></li>
        </ul>';
    $out .= '</div>';

    // Правая часть — вход / аватар
    if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
        $dblink = DbConnect();
        $sql = 'SELECT avatar, cash, fname, lname FROM users WHERE idx = ' . intval($_SESSION['uzver']);
        $res = mysqli_query($dblink, $sql);
        if ($res && $user = mysqli_fetch_assoc($res)) {
            $avatar = ($user['avatar'] != '') ? $user['avatar'] : '/avatars/ava.png';
            $formattedCash = number_format($user['cash'], 0, '', '.');
            $firstName = $user['fname'];
            $lastName = $user['lname'];
            $lastNameShort = mb_substr($lastName, 0, 1) . '.';
            $fullname = ($firstName . ' ' . $lastName);
        }

        $out .= '
<div class="header-right">

    <div class="dropdown">
        <input type="checkbox" id="menu-left" class="dropdown-toggle">
        <label for="menu-left" class="menu-button" data-tooltip="Друзі">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="bi bi-people-fill">
              <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
            </svg>
        </label>

        <div class="dropdown-menu">
            <div class="menu-friends">
                <span class="menu-name">Друзі</span>
            </div>
            <div class="menu-separator"></div>
            <a>Поки що немає друзів</a>
        </div>
    </div>

<div class="dropdown" id="support-menu" style="position:absolute; visibility:hidden; pointer-events:none;">
    <input type="checkbox" id="menu-support" class="dropdown-toggle">
    <label for="menu-support" class="menu-button" data-tooltip="Підтримка" style="display:none;"></label>

    <div class="dropdown-menu">
        <div class="menu-friends">
    <svg id="open-support-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16" style="cursor:pointer;">
        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
    </svg>
    <span class="menu-name">Підтримка</span>
</div>

        <div class="menu-separator"></div>
        
        <a href="">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpms-icon bi bi-gear-fill" viewBox="0 0 16 16">
            <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4m9.886-3.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382zM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0"/>
        </svg>
    </span>
    Зв`язатися з розробником
</a>

        <a href="">
    <span class="icon-wrapper">
         <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpms-icon bi bi-gear-fill" viewBox="0 0 16 16">
            <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414zM0 4.697v7.104l5.803-3.558zM6.761 8.83l-6.57 4.026A2 2 0 0 0 2 14h6.256A4.5 4.5 0 0 1 8 12.5a4.49 4.49 0 0 1 1.606-3.446l-.367-.225L8 9.586zM16 4.697v4.974A4.5 4.5 0 0 0 12.5 8a4.5 4.5 0 0 0-1.965.45l-.338-.207z"/>
            <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1.5a.5.5 0 0 1-1 0V11a.5.5 0 0 1 1 0m0 3a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
        </svg>
    </span>
    Повідомити про проблему
</a>

    </div>
</div>

    <div class="dropdown">
        <input type="checkbox" id="menu-avatar" class="dropdown-toggle">
        <label for="menu-avatar" class="avatar-button" data-tooltip="Акаунт">
        <div class="avatar-wrapper">
            <img src="' . $avatar . '" alt="Аватар" class="header-avatar">
            <div class="avatar-arrow">
            <img src="/assets/images/avaarrow.png" alt="Стрелка">
             </div>
            </div>
        </label>

        <div class="dropdown-menu block">
            <div class="menu-profile" onclick="window.location.href=\'/profile.php\'">
                <img src="' . $avatar . '" class="menu-avatar">
                <span class="menu-name">' . $fullname . '</span>
            </div>
            <div class="menu-separator"></div>
            <div class="menu-separator"></div>

<a href="/profile.php?md=2">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpm-icon bi bi-gear-fill" viewBox="0 0 16 16">
          <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
        </svg>
    </span>
    Налаштування профілю
</a>

<a href="/profile.php?md=4">
    <span class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="dpm-icon bi bi-credit-card-2-back-fill" viewBox="0 0 16 16">
          <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5H0zm11.5 1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM0 11v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1z"/>
        </svg>
    </span>
    Баланс: ' . $formattedCash . ' ₴
</a>

<a href="#" class="open-support">
   <span class="icon-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
           class="dpm-icon bi bi-chat-square-dots-fill" viewBox="0 0 16 16">
        <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.5a1 1 0 0 0-.8.4l-1.9 2.533a1 1 0 0 1-1.6 0L5.3 12.4a1 1 0 0 0-.8-.4H2a2 2 0 0 1-2-2zm5 4a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
      </svg>
   </span>
   Підтримка
</a>


<div class="menu-separator"></div>

<a href="/profile.php?exit=1">
    <span class="icon-wrapper">
        <img src="/assets/images/logout.png" alt="Вийти" class="dpm-icon">
    </span>
    Вийти
</a>


        </div>
    </div>

</div>
';
$out .='<script>
document.addEventListener("DOMContentLoaded", () => {
    const toggles = document.querySelectorAll(".dropdown-toggle");
    const dropdowns = document.querySelectorAll(".dropdown");

    function closeAll() {
        toggles.forEach(t => t.checked = false);
    }

    document.addEventListener("click", (e) => {
        let clickedInside = false;

        dropdowns.forEach(drop => {
            if (drop.contains(e.target)) clickedInside = true;
        });

        if (!clickedInside) closeAll();
    });

    toggles.forEach(toggle => {
        toggle.addEventListener("change", () => {
            if (toggle.checked) {
                toggles.forEach(t => {
                    if (t !== toggle) t.checked = false;
                });
            }
        });
    });
    document.querySelectorAll(".open-support").forEach(btn => {
    btn.addEventListener("click", (e) => {
        e.preventDefault();
        document.querySelectorAll(".dropdown-toggle").forEach(t => t.checked = false); 
        const supportToggle = document.getElementById("menu-support");
        if (supportToggle) supportToggle.checked = true;
    });
});
    
    document.querySelectorAll(".open-support").forEach(btn => {
    btn.addEventListener("click", (e) => {
        e.preventDefault();
        document.querySelectorAll(".dropdown-toggle").forEach(t => t.checked = false); 
        const supportToggle = document.getElementById("menu-support");
        if (supportToggle) {
            supportToggle.checked = true; 
         
            const dropdown = document.getElementById("support-menu");
            dropdown.style.visibility = "visible";
            dropdown.style.pointerEvents = "auto";
        }
    });
});

    document.getElementById("open-support-arrow").addEventListener("click", (e) => {
    e.preventDefault();
    document.querySelectorAll(".dropdown-toggle").forEach(t => t.checked = false);

    const supportToggle = document.getElementById("menu-avatar");
    if (supportToggle) {
        supportToggle.checked = true;
    }
});


});
</script>
';



    } else {
        $out .= '<div class="login-btn"><a class="login-link" href="/auth.php">Увійти</a></div>';
    }

    $out .= '</div>';


    $out .= '
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const burger = document.querySelector(".burger");
        const menu = document.querySelector(".menu-center");

        if (burger && menu) {
            burger.addEventListener("click", function () {
                burger.classList.toggle("active");
                menu.classList.toggle("active");
            });
        }
    });
    </script>
    ';


    return $out;
}



function Menu_Left(): string
{
    $out = '<div class="Menu_Left">';
    $out .= '<a href="index.php" class="menu-link">Головна</a>';
    $out .= '<a href="graveadd.php" class="menu-link">Поховання</a>';
    $out .= '</div>';
    return $out;
}


function Page_Up($ttl = ''): string
{
    $out = '<!DOCTYPE html>' . xbr .
        '<html lang="uk">' . xbr .
        '<head>' . xbr .
        '<title>ІПС Shana | ' . $ttl . '</title>' . xbr .
        '<base href="http://shanapra.com/">' . xbr .
        '<link rel="icon" type="image/x-icon" href="/assets/images/logobrand3.png">' . xbr .
        '<meta charset="utf-8">' . xbr .
        '<meta http-equiv="Content-Type" content="text/html">' . xbr .
        '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=1, shrink-to-fit=yes">' . xbr .
        '<meta name="robots" content="all">' . xbr .
        '<link rel="stylesheet" href="/assets/css/common.css">' . xbr .
        '<script>
 
  history.pushState(null, "", location.href);
  window.addEventListener("popstate", function() {
      window.location.href = "/";
      //не полностью работает так как надо.
  });
</script>
' . xbr .
        '</head>' . xbr .
        '<body class="bg-dark">' . xbr .
        '<div id="wrapper" class="wrapper">' . xbr;
    return $out;
}

function Page_Down(): string
{
    $out = '<div class="Page_Down">';
    $out .= '<ul class="menu_down">
<li><a href="/">About Us</a></li>
<li><a href="/">FAQ</a></li>
<li><a href="/">Contacts</a></li>
<li><a href="/">NpInfo</a></li>
<li><a href="/">Copyright</a></li>
<li><a href="/">Links</a></li>
</ul>';
    $out .= '<hr class="page-down-hr">';
    $out .= '<div class="copyright">© 2025 shanapra</div>';
    $out .= '</div>' . xbr;
    $out .= '</body></html>';
    return $out;
}


function Contentx(): string
{
    $out = '<div class = "content">';

    $out .= '</div>';
    return $out;
}

function View_Add_Warn($mes = ''): string
{
    global $md;
    $out = '<div class="warn">md=' . $md . '** ' . $mes . '</div>';

    return $out;
}

function DbsCount(): int
{
    global $dblink;
    $dblink = DbConnect();
    $sql = 'SELECT count(idx) as t1 FROM grave';
    $res = mysqli_query($dblink, $sql);
    if (!$res) {
        $out = 0;
    } else {
        $ou = mysqli_fetch_assoc($res);
        $out = $ou['t1'];
    }
    return $out;
}

/*function RegionSelect($n="",$c=""):string
{
    return
    '<select name="'.$n.'" class="'.$c.'" required> ' .
    ' <option value="" disabled selected>Виберіть місто</option> ' .
    ' <option>Київ</option> ' .
    '<option>Вінниця</option> ' .
    '<option>Дніпро</option> ' .
    '<option>Донецьк</option> ' .
    '<option>Житомир</option>' .
    '<option>Запоріжжя</option>' .
    '<option>Івано‑Франківськ</option>' .
    '<option>Кропивницький</option>' .
    '<option>Луганськ</option>' .
    '<option>Луцьк</option>' .
    '<option>Львів</option>' .
    '<option>Миколаїв</option>' .
    '<option>Одеса</option>' .
    '<option>Полтава</option>' .
    '<option>Рівне</option>' .
    '<option>Сімферополь</option>' .
    '<option>Суми</option>' .
    '<option>Тернопіль</option>' .
    '<option>Ужгород</option>' .
    '<option>Харків</option>' .
    '<option>Херсон</option>' .
    '<option>Хмельницький</option>' .
    '<option>Черкаси</option>' .
    '<option>Чернівці</option>' .
    '<option>Чернігів</option>' .

    '</select>' ;
}*/

if (isset($_GET['ajax_districts']) && isset($_GET['region_id'])) {
    $region_id = intval($_GET['region_id']);
    $dblink = DbConnect();

    // Берём id и название
    $res = mysqli_query($dblink, "SELECT idx, title FROM district WHERE region = $region_id ORDER BY title");
    mysqli_close($dblink);

    if ($res && mysqli_num_rows($res) > 0) {
        echo '<option value="">Виберіть район</option>';
        while ($row = mysqli_fetch_assoc($res)) {
            // value = id, текст = название
            echo '<option value="' . (int)$row['idx'] . '">' . htmlspecialchars($row['title']) . '</option>';
        }
    } else {
        echo '<option value="">Райони не знайдено</option>';
    }
    exit;
}



function RegionSelect($n = "region", $c = "")
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $out = '<select name="' . $n . '" id="region" class="' . $c . '" onchange="loadDistricts(this.value)" required>';
    $out .= '<option value="" disabled selected>Виберіть область</option>';

    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . $row['idx'] . '">' . $row['title'] . '</option>';
    }

    $out .= '</select>';
    return $out;
}


function RegionForKladb($n = "region", $c = "")
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $out = '<select name="' . $n . '" id="region" class="' . $c . '" onchange="loadDistricts(this.value)">';
    $out .= '<option value="" selected hidden>Виберіть область</option>';

    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . $row['idx'] . '">' . $row['title'] . '</option>';
    }

    $out .= '</select>';
    return $out;
}

function RegionForCem($n = "region", $c = "")
{
    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $out = '<select name="' . $n . '" id="region" class="' . $c . '" required onchange="loadDistricts(this.value)">';

    $out .= '<option value="" selected hidden>Виберіть область</option>';

    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . $row['idx'] . '">' . $row['title'] . '</option>';
    }

    $out .= '</select>';
    return $out;
}


// Районы по области
function getDistricts($region_id)
{
    $dblink = DbConnect();
    $region_id = (int)$region_id;

    $res = mysqli_query($dblink, "SELECT idx, title FROM district WHERE region = $region_id ORDER BY title");

    $out = '<option value="">Оберіть район</option>';
    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="' . (int)$row['idx'] . '">' . htmlspecialchars($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}

// Населенные пункты по району и области
function getSettlements($region_id, $district_id)
{
    $dblink = DbConnect();
    $region_id = (int)$region_id;
    $district_id = (int)$district_id;

    $sql = "SELECT idx, title 
            FROM misto 
            WHERE idxregion = $region_id 
              AND idxdistrict = $district_id 
            ORDER BY title";

    $res = mysqli_query($dblink, $sql);

    $out = '<option value="">Оберіть нас. пункт</option>';
    while ($row = mysqli_fetch_assoc($res)) {

        $out .= '<option value="' . (int)$row['idx'] . '">' . htmlspecialchars($row['title']) . '</option>';
    }

    mysqli_close($dblink);
    return $out;
}



// Добавление нового населённого пункта
function addSettlement($region_id, $district_id, $name)
{
    $dblink = DbConnect();
    $region_id = (int)$region_id;
    $district_id = (int)$district_id;
    $name = mysqli_real_escape_string($dblink, trim($name));

    if ($name === "") {
        return "Помилка: пуста назва";
    }

    $sql = "INSERT INTO misto (title, idxdistrict, idxregion) VALUES ('$name', $district_id, $region_id)";
    if (mysqli_query($dblink, $sql)) {
        $out = "OK: додано";
    } else {
        $out = "Помилка: " . mysqli_error($dblink);
    }

    mysqli_close($dblink);
    return $out;
}



function Cardsx(
    int $idx = 0,
    string $f = '',
    string $i = '',
    string $o = '',
    string $d1 = '',
    string $d2 = '',
    string $img = ''
): string {

    if (!is_file($_SERVER['DOCUMENT_ROOT'].$img)) {
        $img = '/graves/noimage.jpg';
    }

    $d1Unknown = empty($d1) || $d1 === '0000-00-00' || $d1 === '0000-00-00';
    $d2Unknown = empty($d2) || $d2 === '0000-00-00' || $d2 === '0000-00-00';

    if ($d1Unknown && $d2Unknown) {
        $dates = 'Дати не вказані';
    } elseif ($d1Unknown) {
        $dates = 'Дата не вказана - '.DateFormat($d2);
    } elseif ($d2Unknown) {
        $dates = DateFormat($d1).' - Дата не вказана';
    } else {
        $dates = DateFormat($d1).' - '.DateFormat($d2);
    }

    $out  = '<div class="cardx">';

    // фото
    $out .= '  <div class="cardx-img">';
    $out .= '      <img src="'.$img.'" class="cardx-image" alt="'.$f.' '.$i.' '.$o.'" title="'.$f.' '.$i.' '.$o.'">';
    $out .= '  </div>';

    // блок с данными
    $out .= '  <div class="cardx-data">';
    $out .= '      <div class="text2center font-bold font-white height50">';
    $out .=            $f.' '.$i.' '.$o.'<br>';
    $out .= '      </div>';
    $out .= '      <div class="text2center font-white">';
    $out .=            $dates.'<br>';
    $out .= '      </div>';
    $out .= '      <div class="text2right">';
    $out .= '          <a href="/cardout.php?idx='.$idx.'">Детали</a>';
    $out .= '      </div>';
    $out .= '  </div>';

    $out .= '</div>';

    return $out;
}

function DateFormatUnknown(string $date): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00') {
        return 'Дата не вказана';
    }
    return DateFormat($date);
}


function CardsK(
    int $idx = 0,
    string $title = '',
    string $town = '',
    string $district = '',
    string $adress = '',
    string $img = ''
): string {

    $dblink = DbConnect();

//район
    if (!empty($district) && ctype_digit((string)$district)) {
        $res = mysqli_query($dblink, "SELECT title FROM district WHERE idx=".(int)$district." LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $district = $row['title'];
        }
    }


    //нас пункт
    if (!empty($town) && ctype_digit((string)$town)) {
        $res = mysqli_query($dblink, "SELECT title FROM misto WHERE idx=".(int)$town." LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $town = $row['title'];
        }
    }

    if (!is_file($_SERVER['DOCUMENT_ROOT'].$img) || empty($img)) {
        $img = '/cemeteries/noscheme.png';
    }

    $out  = '<div class="cardk">';

    // фото
    $out .= '  <div class="cardk-img">';
    $out .= '      <img src="'.$img.'" class="cardk-image" alt="'.htmlspecialchars($title).'" title="'.htmlspecialchars($title).'">';
    $out .= '  </div>';

    // блок с данными
    $out .= '  <div class="cardk-data">';

    // Заголовок
    $out .= '      <div class="cardk-title font-bold">'.$title.'</div>';


    if ($town !== '') {
        $out .= '      <div class="cardk-town"><b>Місто:</b> '.$town.'</div>';
    }

    // Район
    if ($district !== '') {
        $out .= '      <div class="cardk-district"><b>Район:</b> '.$district.'</div>';
    }

    // Адрес
    if ($adress !== '') {
        $out .= '      <div class="cardk-adress"><b>Адреса:</b> '.$adress.'</div>';
    }


    $out .= '  </div>'; // .cardk-data
    $out .= '  <div class="cardk-footer">';
    $out .= '      <a href="/cemetery.php?idx='.$idx.'" class="cardk-link">Деталі</a>';
    $out .= '  </div>';
    $out .= '</div>';   // .cardk

    return $out;
}



class Paginatex
{
    public static function Showx(int $current, int $total, int $perpage): string
    {
        $countPages = ceil($total / $perpage);
        if ($countPages <= 1) return '';

        $prevIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M15 18l-6-6 6-6"/>
</svg>';

        $nextIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M9 18l6-6-6-6"/>
</svg>';

        $firstIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M11 19l-7-7 7-7M18 5v14"/>
</svg>';

        $lastIcon = '
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" 
    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path d="M13 5l7 7-7 7M6 19V5"/>
</svg>';


        $html = '<ul>';

        // для текущего путя.
        $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');

        $query = $_GET;
        unset($query['page']);
        $zapr = http_build_query($query);
        $zapr = $zapr ? "&$zapr" : "";

        // В начало
        if ($current > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=1' . $zapr . '">' . $firstIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $firstIcon . '</span></li>';
        }

        // Назад
        if ($current > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . ($current - 1) . $zapr . '">' . $prevIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $prevIcon . '</span></li>';
        }

        // Номера страниц
        $start = max(1, $current);
        $end = min($start + 2, $countPages);
        if ($end - $start < 2 && $start > 1) {
            $start = max(1, $end - 2);
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i == $current) {
                $html .= '<li><span class="current">' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $baseUrl . '?page=' . $i . $zapr . '">' . $i . '</a></li>';
            }
        }

        // Вперед
        if ($current < $countPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . ($current + 1) . $zapr . '">' . $nextIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $nextIcon . '</span></li>';
        }

        // В конец
        if ($current < $countPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . $countPages . $zapr . '">' . $lastIcon . '</a></li>';
        } else {
            $html .= '<li><span class="disabled">' . $lastIcon . '</span></li>';
        }

        $html .= '</ul>';
        return $html;
    }
}

function CemeterySelect($districtId = 0, $selectedId = null) {
    $out = '<option value="">Виберіть кладовище</option>';

    if ($districtId > 0) {
        $dblink = DbConnect();
        $districtId = intval($districtId);

        $sql = "SELECT idx, title FROM cemetery WHERE district = $districtId ORDER BY title ASC";
        $res = mysqli_query($dblink, $sql);

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $sel = ($selectedId && $selectedId == $row['idx']) ? ' selected' : '';
                $out .= '<option value="' . $row['idx'] . '"' . $sel . '>' . htmlspecialchars($row['title']) . '</option>';
            }
        }
    }

    return $out;
}

function gravecompress($sourcePath, $targetPath, $maxSizeKB = 300, $maxWidth = 1920, $maxHeight = 1080, $quality = 85) {
    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($sourcePath); break;
        case 'image/png': $image = imagecreatefrompng($sourcePath); break;
        case 'image/gif': $image = imagecreatefromgif($sourcePath); break;
        default: return false;
    }

    $origWidth = imagesx($image);
    $origHeight = imagesy($image);

    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
    $newWidth = intval($origWidth * $ratio);
    $newHeight = intval($origHeight * $ratio);

    if ($ratio < 1) {
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime == 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        if ($mime == 'image/gif') {
            $transparent_index = imagecolortransparent($image);
            if ($transparent_index >= 0) {
                $transparent_color = imagecolorsforindex($image, $transparent_index);
                $transparent_index_new = imagecolorallocate($resized, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($resized, 0, 0, $transparent_index_new);
                imagecolortransparent($resized, $transparent_index_new);
            }
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($image);
        $image = $resized;
    }

    if ($mime == 'image/jpeg') {
        imagejpeg($image, $targetPath, $quality);
    } elseif ($mime == 'image/png') {
        $pngQuality = 9 - round($quality / 10);
        imagepng($image, $targetPath, $pngQuality);
    } elseif ($mime == 'image/gif') {
        imagegif($image, $targetPath);
    }

    $filesizeKB = filesize($targetPath) / 1024;
    if ($filesizeKB > $maxSizeKB && ($mime == 'image/jpeg' || $mime == 'image/png')) {

        $currentQuality = $quality;
        while ($filesizeKB > $maxSizeKB && $currentQuality > 30) {
            $currentQuality -= 5;
            if ($mime == 'image/jpeg') imagejpeg($image, $targetPath, $currentQuality);
            if ($mime == 'image/png') {
                $pngQuality = 9 - round($currentQuality / 10);
                imagepng($image, $targetPath, $pngQuality);
            }
            $filesizeKB = filesize($targetPath) / 1024;
        }
    }

    imagedestroy($image);
    return true;
}
