<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';

require_once "function.php";
$xx = isset($_POST['xx']) ? $_POST['xx'] : 0;

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');

View_Add("<link rel='stylesheet' href='function.css'>");
View_Add('<div class="contentgravelist" style="flex:1; display:flex; flex-direction: column; overflow-x: auto;">');

$dblink = DbConnect();

// Получаем все записи
$res = mysqli_query($dblink, 'SELECT * FROM grave WHERE idxkladb = 0;');
$t = mysqli_num_rows($res);
for ($i = 1; $i <= $t; $i++) {
    $a = mysqli_fetch_assoc($res);
    $gr[] = $a;
}

// Получаем количество уникальных pos1 (кварталов)
$res = mysqli_query($dblink, 'SELECT DISTINCT pos1 FROM grave WHERE idxkladb = 0 ORDER BY pos1 ASC;');
$k = mysqli_num_rows($res);

// Подключение к бд, чтение таблицы grave, вывод на экран
$res = mysqli_query($dblink, "SELECT * FROM grave;");
$cnt = mysqli_num_rows($res);

View_Add('<div class="table-wrapper">');
View_Add("<table class='grave-table'>");
View_Add("<thead><tr>");
View_Add("<th>ID</th>");
View_Add("<th>Прізвище</th>");
View_Add("<th>Ім'я</th>");
View_Add("<th>По батькові</th>");
View_Add("<th>Дата народження</th>");
View_Add("<th>Дата смерті</th>");
View_Add("</tr></thead>");
View_Add("<tbody>");

for ($i = 1; $i <= $cnt; $i++) {
    $a = mysqli_fetch_assoc($res);
    $link = "editgrave.php?id=" . $a["idx"];
    View_Add("<tr onclick=\"window.location='{$link}'\" style='cursor:pointer;'>");
    View_Add("<td>" . $a["idx"] . "</td>");
    View_Add("<td>" . $a["lname"] . "</td>");
    View_Add("<td>" . $a["fname"] . "</td>");
    View_Add("<td>" . $a["mname"] . "</td>");
    View_Add("<td>" . $a["dt1"] . "</td>");
    View_Add("<td>" . $a["dt2"] . "</td>");
    View_Add("</tr>");
}


View_Add("</tbody>");
View_Add("</table>");
View_Add('</div>');
View_Add("</div>");
View_Add(' </div > ');

View_Add(Page_Down());
View_Out();
View_Clear();
