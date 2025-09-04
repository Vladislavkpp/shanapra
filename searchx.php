<?php
require_once "function.php";
require_once "function_vra.php";

$cp = $_GET['page'] ?? 1;
$perpage = 5;

// Получаем параметры поиска из формы
$surname    = $_GET['surname'] ?? '';
$name       = $_GET['name'] ?? '';
$patronymic = $_GET['patronymic'] ?? '';

$dblink = DbConnect();
$rows = [];

if ($surname !== '' || $name !== '' || $patronymic !== '') {
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

    if ($patronymic !== '') {
        $sql .= " AND (mname LIKE ? OR SOUNDEX(mname) = SOUNDEX(?))";
        $like_patronymic = "%$patronymic%";
        $params[] = &$like_patronymic;
        $params[] = &$patronymic;
        $types .= "ss";
    }

    $stmt = $dblink->prepare($sql);

    if (!empty($params)) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    $stmt->execute();

    // --- Получаем результат через bind_result (как в твоём старом коде) ---
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

// Количество результатов
$cout = count($rows);

// Формируем строку поиска для отображения
$search_parts = [];
if ($surname)    $search_parts[] = $surname;
if ($name)       $search_parts[] = $name;
if ($patronymic) $search_parts[] = $patronymic;

$search_line = !empty($search_parts) ? implode(", ", $search_parts) : "Всі записи";

// --- Вывод ---
View_Clear();
View_Add(Page_Up('Результати пошуку'));
View_Add(Menu_Up());
View_Add('<div class="out">');

// Контейнер поиска
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
View_Add('<label for="search-input">Пошук по прізвищу</label>');
View_Add('</div>');
View_Add('<input type="submit" class="search-btn" value="Пошук">');
View_Add('</form>');
View_Add('<div class="search-divider"></div>');
View_Add('<button type="button" class="filter-btnx">Фільтр</button>');
View_Add('</div>');

View_Add('</div>');

// --- Карточки с пагинацией ---
View_Add('<div class="cards-out">');
$offset = ($cp - 1) * $perpage;
$rows_page = array_slice($rows, $offset, $perpage);

foreach ($rows_page as $c) {
    View_Add(Cardsx($c['idx'], $c['lname'], $c['fname'], $c['mname'], $c['dt1'], $c['dt2'], $c['photo1']));
}

View_Add('</div><br>'.xbr);

// Пагинация
View_Add('<div class="paginator-out">');
View_Add(Paginate::Show($cp, $cout, $perpage));
View_Add('</div><br>'.xbr);

View_Add(Page_Down());
View_Add('</div>');
View_Out();
