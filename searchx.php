<?php
require_once "function.php";
require_once "function_vra.php";

$cp = $_GET['page'] ?? 1;
$perpage = 5;

// Получаем параметры поиска
$surname = $_GET['surname'] ?? '';
$name    = $_GET['name'] ?? '';
$mname   = $_GET['mname'] ?? '';

$dblink = DbConnect();
$rows = [];

// ----- Формируем массив $rows -----
if ($surname !== '' || $name !== '' || $mname !== '') {
    $sql = "SELECT * FROM grave WHERE 1=1";
    $params = [];
    $types = '';

    if ($surname !== '') {
        $sql .= " AND (lname LIKE ? OR SOUNDEX(lname) = SOUNDEX(?))";
        $like_surname = "%$surname%";
        $params[] = &$like_surname;
        $params[] = &$surname;
        $types .= "ss";
    }

    if ($name !== '') {
        $sql .= " AND (fname LIKE ? OR SOUNDEX(fname) = SOUNDEX(?))";
        $like_name = "%$name%";
        $params[] = &$like_name;
        $params[] = &$name;
        $types .= "ss";
    }

    if ($mname !== '') {
        $sql .= " AND (mname LIKE ? OR SOUNDEX(mname) = SOUNDEX(?))";
        $like_mname = "%$mname%";
        $params[] = &$like_mname;
        $params[] = &$mname;
        $types .= "ss";
    }

    $stmt = $dblink->prepare($sql);

    if (!empty($params)) {
        array_unshift($params, $types);
        call_user_func_array([$stmt, 'bind_param'], $params);
    }

    $stmt->execute();

    $meta = $stmt->result_metadata();
    $fields = [];
    $data = [];
    while ($field = $meta->fetch_field()) {
        $fields[] = &$data[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $fields);

    while ($stmt->fetch()) {
        $row = [];
        foreach ($data as $k => $v) {
            $row[$k] = $v;
        }
        $rows[] = $row;
    }

} else {
    // если все поля пустые — выводим всё
    $res = mysqli_query($dblink, "SELECT * FROM grave");
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}


$cout = count($rows);


$search_parts = [];
if ($surname !== '') $search_parts[] = "$surname";
if ($name !== '')    $search_parts[] = "$name";
if ($mname !== '')   $search_parts[] = "$mname";

$search_line = !empty($search_parts) ? implode(", ", $search_parts) : "Всі записи";



View_Clear();
View_Add(Page_Up('Результати пошуку'));
View_Add(Menu_Up());
View_Add('<div class="out">');


View_Add('<div class="search-container">');
View_Add('<div class="search-out" style="display:flex; gap:16px; align-items:center;">');


View_Add('<div class="search-left">');
View_Add('<span class="search-param">Пошук за параметрами: '.$search_line.' </span>');
View_Add('<div class="search-divider"></div>');
View_Add('<span class="search-count">Картки: '.$cout.'</span>');
View_Add('</div>');


View_Add('<div class="search-right">');

// форма поиска
View_Add('<form class="search-form" action="/searchx.php" method="get">');
View_Add('<div class="search-input-container">');
View_Add('<input type="text" name="surname" placeholder=" " id="search-input" class="search-inputx" autocomplete="off">');
View_Add('<label for="search-input">Пошук по базі</label>');
View_Add('</div>');
View_Add('<input type="submit" class="search-btn" value="Пошук">');
View_Add('</form>');
View_Add('<div class="search-divider"></div>');
View_Add('<button type="button" class="filter-btnx">Фільтр</button>');
View_Add('</div>');




View_Add('</div>');


View_Add('<div class="cards-out">');

$offset = ($cp - 1) * $perpage;
$rows_page = array_slice($rows, $offset, $perpage);

foreach ($rows_page as $c) {
    View_Add(Cardsx($c['idx'], $c['lname'], $c['fname'], $c['mname'], $c['dt1'], $c['dt2'], $c['photo1']));
}

View_Add('</div><br>'.xbr);

// ----- Пагинация -----
View_Add('<div class="paginator-out">');
View_Add(Paginate::Show($cp, $cout, $perpage));
View_Add('</div><br>'.xbr);

View_Add(Page_Down());
View_Add('</div>');
View_Out();
