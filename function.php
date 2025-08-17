<?php
session_start();
const xbr = "\n";
const no_grave_photo='/graves/no_image_0.png';
$buf = '';
$dblink = null;
//$start = getmicrotime();
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

function warn($x=''): string
{
    return '<div class="warn">'.$x.'</div>';
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
{
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
    $out .= '<div class="logo"><img src="/assets/images/logobrand2.png" alt="Логотип"></div>';
    $out .= '<div class="title">ІПС Шана</div>';
    $out .= '</div>';

    $out .= '<div class="menu-divider"></div>'; // вертикальная линия

    $out .= '<div class="menu-center">';
    $out .= '<ul class="menu_ups"> 
    <li><a href="/index.php">Головна</a></li>
    <li><a href="#">Робота</a> 
        <ul class="submenu"> 
            <li><a href="#">Прибирання</a></li>
            <li><a href="#">Виготовлення</a></li>
            <li><a href="#">Платний пошук</a></li>
        </ul>
    </li>
    <li><a href="/">Церкви</a></li>
    <li><a href="/">Наші клієнти</a></li>
    
   
    
    <li><a href="/graveadd.php">Додати поховання (Тест)</a></li>
    <li><a href="/docs/zadaniya.php">Завдання</a></li>
    </ul>';
    $out .= '</div>';

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
            $fullname = htmlspecialchars($firstName . ' ' . $lastNameShort);
        }

        $out .= '<div class="login-btn dropdown">';
        $out .= '<input type="checkbox" id="dropdown-toggle" class="dropdown-checkbox" />';
        // label с аватаром и именем
        $out .= '<label for="dropdown-toggle" class="avatar-wrapper">';
        $out .= '<img class="menu-avatar" alt="profile" src="' . $avatar . '">';
        $out .= '<span>' . $fullname . '</span>';
        $out .= '</label>';
        // меню
        $out .= '<div class="dropdown-menu">';
        $out .= '<a href="/profile.php"><img src="/assets/images/profileicon.png" class="menu-icon"> Профіль</a>';
        $out .= '<a href="#"><img src="/assets/images/notification.png" class="menu-icon"> Сповіщення</a>';
        $out .= '<a href="/profile.php?md=4"><img src="/assets/images/balanceprof.png" class="menu-icon"> Баланс: ₴' . $formattedCash . ' </a>';
        $out .= '<a href="profile.php?md=2"><img src="/assets/images/setting.png" class="menu-icon"> Налаштування</a>';
        $out .= '<a href="#"><img src="/assets/images/support.png" class="menu-icon"> Підтримка</a>';
        $out .= '<hr>';
        $out .= '<a href="?exit=1" class="logout"><img src="/assets/images/logbtn.png" class="menu-icon"> Вийти</a>';
        $out .= '</div>';
        $out .= '<label for="dropdown-toggle" class="page-overlay"></label>';
        $out .= '</div>';

    } else {
        $out .= '<div class="login-btn"><a class="login-link" href="/auth.php">Увійти</a></div>';
    }

    $out .= '</div>';
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


function Page_Up($ttl=''): string
{
    $out = '<!DOCTYPE html>' . xbr .
        '<html lang="uk">' . xbr .
        '<head>' . xbr .
        '<title>ІПС Shana | '.$ttl.'</title>' . xbr .
        '<meta charset="utf-8">' . xbr .
        '<meta http-equiv="Content-Type" content="text/html">' . xbr .
        '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=1, shrink-to-fit=yes">' . xbr .
        '<meta name="robots" content="all">' . xbr .
        '<link rel="stylesheet" href="/assets/css/common.css">' . xbr .
        '</head>' . xbr .
        '<body class="bg-dark">' . xbr .
        '<div id="wrapper" class="wrapper">' . xbr;
    return $out;
}

function Page_Down(): string
{
    $out = '<div class="Page_Down"><hr class="page-down-hr">';
    $out .= '<ul class="menu_down">
<li><a href = "/" >About Us</a></li>
<li><a href = "/" >FAQ</a></li>
<li><a href = "/" >Contacts</a></li>
<li><a href = "/" >NpInfo</a></li>
<li><a href = "/" >Copyright</a></li>
<li><a href = "/" >Links</a></li>
</ul>';
    $out .= '</div>' . xbr;
    $out .= '</div>';
    $out .= '</body></html>';
    return $out;
}

function Contentx(): string
{
    $out = '<div class = "content">';

    $out .= '</div>';
    return $out;
}

function View_Add_Warn($mes=''): string
{
    global $md;
    $out = '<div class="warn">md='.$md.'** '.$mes.'</div>';

    return $out;
}

function DbsCount():int
{
    global $dblink;
    $dblink=DbConnect();
    $sql = 'SELECT count(idx) as t1 FROM grave';
    $res = mysqli_query($dblink, $sql);
    if (!$res) {
        $out = 0;
    } else {
        $ou = mysqli_fetch_assoc($res);
        $out=$ou['t1'];
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

    $res = mysqli_query($dblink, "SELECT title FROM district WHERE region = $region_id ORDER BY title");
    mysqli_close($dblink);

    if ($res && mysqli_num_rows($res) > 0) {
        echo '<option value="">Виберіть район</option>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<option value="'.$row['title'].'">'.$row['title'].'</option>';
        }
    } else {
        echo '<option value="">Райони не знайдено</option>';
    }
    exit;
}


function RegionSelect($n="region",$c="") {
    $dblink = DbConnect();
    $res = mysqli_query($dblink, "SELECT idx, title FROM region ORDER BY title");
    mysqli_close($dblink);

    $out = '<select name="'.$n.'" id="region" class="'.$c.'" onchange="loadDistricts(this.value)" required>';
    $out .= '<option value="" disabled selected>Виберіть область</option>';

    while ($row = mysqli_fetch_assoc($res)) {
        $out .= '<option value="'.$row['idx'].'">'.$row['title'].'</option>';
    }

    $out .= '</select>';
    return $out;
}


