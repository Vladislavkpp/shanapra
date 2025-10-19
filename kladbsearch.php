<?php
require_once "function.php";
require_once "function_vra.php";

$cp = $_GET['page'] ?? 1;
$perpage = 5;

$region   = $_GET['region'] ?? '';
$district   = $_GET['district'] ?? '';
$title      = $_GET['title'] ?? '';

$dblink = DbConnect();
$rows = [];
$sql = "SELECT c.* FROM cemetery c JOIN district d ON c.district = d.idx WHERE 1=1";

if ($region != '' || $district != '' || $title != '') {

    if ($region !== '') {
        $sql .= " AND (d.region = '".mysqli_real_escape_string($dblink, $region)."')";
    }

    if ($district !== '') {
        $sql .= " AND (c.district = '".mysqli_real_escape_string($dblink, $district)."')";
    }

    if ($title !== '') {
        $sql .= " AND (c.title LIKE '%".mysqli_real_escape_string($dblink, $title)."%')";
    }

    $sql .= " ORDER BY c.idx ASC";

    $res = mysqli_query($dblink, $sql);
    if (!$res) {
        die("SQL Error: " . mysqli_error($dblink) . "<br>Query: " . $sql);
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

} else {
    // если все поля пустые — выводим все записи с сортировкой
    $res = mysqli_query($dblink, "SELECT * FROM cemetery ORDER BY idx ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}



// Количество результатов
$cout = count($rows);

$search_line = "";

View_Clear();
View_Add(Page_Up('Результати пошуку кладовищ'));
View_Add(Menu_Up());
View_Add('<div class="out-kladb">');
// Контейнер поиска
View_Add('<div class="searchklb-container">');
View_Add('<div class="searchklb-out">');

// Левая часть
View_Add('<div class="searchklb-left">');
View_Add('<span class="searchklb-param">Пошук за параметрами:</span>');
View_Add('<div class="searchklb-divider"></div>');
View_Add('<span class="searchklb-count">Картки: '.$cout.'</span>');
View_Add('</div>');
View_Add('<div class="searchklb-divider"></div>');
View_Add('<a href="kladbadd.php" class="searchklb-btn">+ Додати кладовище</a>');

// Правая часть
View_Add('<div class="searchklb-right">');
View_Add('<form class="searchklb-form" action="/kladbsearch.php" method="get">');
View_Add('<input type="text" name="title" placeholder="Пошук по назві" class="searchklb-input">');
View_Add('<input type="submit" class="searchklb-btn" value="Пошук">');
View_Add('</form>');
View_Add('<div class="searchklb-divider"></div>');
View_Add('<button type="button" class="filterklb-btnx">Фільтр</button>');
View_Add('</div>');

View_Add('</div>');
View_Add('</div>');

View_Add('<div class="out-kladb-mobile">');
// Контейнер поиска
View_Add('<div class="searchklb-container-mobile">');
View_Add('<div class="searchklb-out-mobile">');

// Левая часть
View_Add('<div class="searchklb-left">');
View_Add('<span class="searchklb-param">Пошук за параметрами:</span>');
View_Add('<div class="searchklb-divider"></div>');
View_Add('<span class="searchklb-count">Картки: '.$cout.'</span>');
View_Add('</div>');

View_Add('
    <div class="searchklb-buttons">
        <button type="button" class="filterklb-btnx">Фільтр</button>
        <a href="kladbadd.php" class="searchklb-btn">+ Додати кладовище</a>
    </div>
');

View_Add('');
View_Add('</div>');

View_Add('</div>');
View_Add('</div>');

// Карточки с пагинацией
View_Add('<div class="cardk-out">');

if ($cout === 0) {
    View_Add('<div class="no-results-wrap"><div class="no-results">За вашим запитом нічого не знайдено</div></div>');
} else {
    $offset = ($cp - 1) * $perpage;
    $rows_page = array_slice($rows, $offset, $perpage);

    foreach ($rows_page as $c) {
        View_Add(CardsK($c['idx'], $c['title'], $c['town'], $c['district'], $c['adress'], $c['scheme']));
    }
}

View_Add('</div>' . xbr);

// Пагинация
if ($cout > 0) {
    View_Add('<div class="paginator-out kladb-page">');
    View_Add(Paginatex::Showx($cp, $cout, $perpage));
    View_Add('</div>' . xbr);
}
View_Add('</div></div>');

View_Add(Page_Down());
View_Out();
View_Clear();
