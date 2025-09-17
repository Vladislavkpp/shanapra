<?php
require_once "function.php";
require_once "function_vra.php";

$cp = $_GET['page'] ?? 1;
$perpage = 5;


$surname = $_GET['surname'] ?? '';
$name = $_GET['name'] ?? '';
$patronymic = $_GET['patronymic'] ?? '';

$dblink = DbConnect();
$rows = [];
$sql = "SELECT * FROM grave WHERE (1=1)";
if ($surname != '' || $name != '' || $patronymic != '') {
    if ($surname != '') {
        $sql .= 'AND (lname LIKE "%' . $surname . '%")';
    }

    if ($name != '') {
        $sql .= 'AND (fname LIKE "%' . $name . '%")';
    }

    if ($patronymic != '') {
        $sql .= 'AND (mname LIKE "%' . $patronymic . '%")';

    }
    $res = mysqli_query($dblink, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

} else {
    // если все поля пустые — выводим всё
    $res = mysqli_query($dblink, "SELECT * FROM grave");
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

// Количество результатов
$cout = count($rows);

$search_line = "";


View_Clear();
View_Add(Page_Up('Результати пошуку'));
View_Add(Menu_Up());
View_Add('<div class="out">');

// Контейнер поиска
View_Add('<div class="search-container">');
View_Add('<div class="search-out" style="display:flex; gap:16px; align-items:center;">');

View_Add('<div class="search-left">');
View_Add('<span class="search-param">Пошук за параметрами: ' . $search_line . ' </span>');
View_Add('<div class="search-divider"></div>');
View_Add('<span class="search-count">Картки: ' . $cout . '</span>' );
View_Add('</div>');

View_Add('<div class="search-right">');
// форма поиска
View_Add('<form class="search-form" action="/searchx.php" method="get">');
View_Add('<div class="search-input-container">');
View_Add('<input type="text" name="surname" placeholder=" " id="search-input" class="search-inputx" autocomplete="off">');
View_Add('<label for="search-input">Пошук по прізвищу</label>');
View_Add('</div>');
View_Add('<input type="submit" class="search-btn" value="Пошук">');
View_Add('</form>');
View_Add('<div class="search-divider"></div>');
View_Add('<button type="button" class="filter-btnx">Фільтр</button>');
View_Add('</div>');

View_Add('</div>');

// Карточки с пагинацией
View_Add('<div class="cards-out">');

if ($cout === 0) {
    View_Add('<div class="no-results-wrap"><div class="no-results">За вашим запитом нічого не знайдено</div></div>');
} else {
    $offset = ($cp - 1) * $perpage;
    $rows_page = array_slice($rows, $offset, $perpage);

    foreach ($rows_page as $c) {
        View_Add(Cardsx($c['idx'], $c['lname'], $c['fname'], $c['mname'], $c['dt1'], $c['dt2'], $c['photo1']));
    }
}

View_Add('</div><br>' . xbr);


// Пагинация
if ($cout > 0) {
    View_Add('<div class="paginator-out">');
    View_Add(Paginate::Show($cp, $cout, $perpage));
    View_Add('</div><br>' . xbr);
}

View_Add(Page_Down());
View_Add('</div>');
View_Out();
