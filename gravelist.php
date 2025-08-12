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
View_Add(Menu_Left());
View_Add("<link rel='stylesheet' href='function.css'>");
View_Add('<div class="contentgravelist" style="flex:1; display:flex; flex-direction: column; overflow-x: auto;">');

$dblink = DbConnect();

$res = mysqli_query($dblink, 'SELECT * FROM grave WHERE idxkladb = 0;');
$t = mysqli_num_rows($res);
for ($i = 1; $i <= $t; $i++) {
    $a = mysqli_fetch_assoc($res);
    $gr[] = $a;
}

// Получаем количество уникальных pos1 (кварталов)
$res = mysqli_query($dblink, 'SELECT DISTINCT pos1 FROM grave WHERE idxkladb = 0 ORDER BY pos1 ASC;');
$k = mysqli_num_rows($res);

/*
for ($i = 1; $i <= $k; $i++) {
    $a = mysqli_fetch_assoc($res);
    $gr[] = $a;
    View_Add('-' . $a["pos1"]);
}*/


View_Add("
<div class='stat-blocks'>
    <div class='stat-card'>
        <div class='stat-title'>Усього поховань у базі</div>
        <div class='stat-value'>$t</div>
    </div>
    <div class='stat-card'>
        <div class='stat-title'>Зайнятих кварталів</div>
        <div class='stat-value'>$k</div>
    </div>
</div>
");

//Подключение к бд, чтение таблицы grave, вывод на экран
$res = mysqli_query($dblink, "SELECT * FROM grave;");
$cnt = mysqli_num_rows($res);
View_Add('<div class="table-wrapper">');
View_Add("<table class='grave-table'>");
View_Add("<thead><tr>");
View_Add("<th>idx</th>");
View_Add("<th>fname</th>");
View_Add("<th>lname</th>");
View_Add("<th>mname</th>");
View_Add("<th>dt1</th>");
View_Add("<th>dt2</th>");
View_Add("<th>dtadd</th>");
View_Add("<th>idxadd</th>");
View_Add("<th>idxkladb</th>");
View_Add("<th>pos1</th>");
View_Add("<th>pos2</th>");
View_Add("<th>pos3</th>");
View_Add("<th>verify</th>");
View_Add("<th>photo1</th>");
View_Add("<th>photo2</th>");
View_Add("<th>photo3</th>");
View_Add("<th>photo123verify</th>");
View_Add("</tr></thead>");
View_Add("<tbody>");
for ($i = 1; $i <= $cnt; $i++) {
    $a = mysqli_fetch_assoc($res);
    View_Add("<tr>");
    View_Add("<td>" . $a["idx"] . "</td>");
    View_Add("<td>" . $a["fname"] . "</td>");
    View_Add("<td>" . $a["lname"] . "</td>");
    View_Add("<td>" . $a["mname"] . "</td>");
    View_Add("<td>" . $a["dt1"] . "</td>");
    View_Add("<td>" . $a["dt2"] . "</td>");
    View_Add("<td>" . $a["idtadd"] . "</td>");
    View_Add("<td>" . $a["idxadd"] . "</td>");
    View_Add("<td>" . $a["idxkladb"] . "</td>");
    View_Add("<td>" . $a["pos1"] . "</td>");
    View_Add("<td>" . $a["pos2"] . "</td>");
    View_Add("<td>" . $a["pos3"] . "</td>");
    View_Add("<td>" . $a["verify"] . "</td>");
    View_Add("<td>" . $a["photo1"] . "</td>");
    View_Add("<td>" . $a["photo2"] . "</td>");
    View_Add("<td>" . $a["photo3"] . "</td>");
    View_Add("<td>" . $a["photo123verify"] . "</td>");
    View_Add("</tr>");
}

View_Add("</table>");
View_Add('</div>');


$res = mysqli_query($dblink, 'SELECT pos1 AS kvartal, MAX(pos2) AS max_pos2, MAX(pos3) AS max_pos3 FROM grave GROUP BY pos1 ORDER BY pos1;');
$cnt = mysqli_num_rows($res);
View_Add('<div class="table-wrapper">');
View_Add("<table class='grave-maxpos'>");
View_Add("<thead><tr>");
View_Add("<th>Квартал</th>");
View_Add("<th>Макс.(ряд)</th>");
View_Add("<th>Макс.(место)</th>");
View_Add("</tr></thead>");
View_Add("<tbody>");
for ($i = 1; $i <= $cnt; $i++) {
    $a = mysqli_fetch_assoc($res);

    View_Add("<tr>");
    View_Add("<td>{$a['kvartal']}</td>");
    View_Add("<td>{$a['max_pos2']}</td>");
    View_Add("<td>{$a['max_pos3']}</td>");
    View_Add("</tr>");
}
View_Add("</tbody>");
View_Add("</table>");
View_Add("</div>");


function FormList(): string
{
    global $dblink;
    $out = '<form action="?" method="post">';
    $out .= '<div class="form-list">
    <div class="input-container">
        <select name="xx">';
    $sql = 'SELECT DISTINCT pos1 FROM grave GROUP BY pos1 ASC';
    $res = mysqli_query($dblink, $sql);
    $cnt = mysqli_num_rows($res);
    for ($l = 1; $l <= $cnt; $l++) {
        $x = mysqli_fetch_assoc($res);
        $out .= '<option>' . $x['pos1'] . '</option>';

    }
    $out .= '</select>
        </div >
        <div class="submitform" >
    <button type = "submit" class="btnlistform" > Відправити</button>
    </form>
</div ></div > ';
    return $out;
}

function GraveList01($xx = '0'): string
{
    global $dblink;

    $sql = 'SELECT * FROM grave WHERE pos1 = ' . $xx;
    $res = mysqli_query($dblink, $sql);
    $cnt = mysqli_num_rows($res);
    $a = array();
    $mx = $my = 0;
    for ($l = 1; $l <= $cnt; $l++) {
        $x = mysqli_fetch_assoc($res);
        $ax = $x['pos2'];
        $ay = $x['pos3'];
        $a[$ax][$ay] = $x['idx'];

        if ($x['pos2'] > $mx) {
            $mx = $x['pos2'];
        }
        if ($x['pos3'] > $my) {
            $my = $x['pos3'];
        }
    }
    $out = '<pre>';
    $out .= print_r($a, 1);
    $out .= '<table class="gravestable">';
    for ($qa = 1; $qa <= $mx; $qa++) {
        $out .= '<tr>';
        for ($qb = 1; $qb <= $my; $qb++) {
            $i = 0;
            if (isset($a[$qa][$qb])) {
                $i = $a[$qa][$qb];
            }
            $out .= '<td class="gravesfoto">';
            $f = '/graves/' . $i . '/foto1.jpg';
            if (is_file($_SERVER['DOCUMENT_ROOT'] . $f)) {
                $out .= '<img class="gravesfoto" src="' . $f . '">';
            } else {
                $out .= '<img class="gravesfoto" src="' . no_grave_photo . '">';
            }
            $out .= '</td>';
        }
        $out .= '</tr>';
    }
    return $out . '</table>';
}

/*
$res=mysqli_query($dblink, 'SELECT DISTINCT pos1 FROM grave WHERE idxkladb = 0 GROUP BY pos1 ASC;');
$k=mysqli_num_rows($res);
for ($i=1;$i<=$k;$i++)
{
    $a=mysqli_fetch_assoc($res);
    $gr[]=$a;
    View_Add(' - '.$a["pos1"]);
}
View_Add("В базе кварталов в таблице pos1 =$k");*/

//Вывести на экран кол-во захоронений на кладбище
// Вывксти на экран кол-во кварталов заселенных
//Вывести на экран поквартально с сортировкой по номеру квартала данные про захоронение ASC
//По каждому кварталу определить макс. pos2, pos3, и вывести согласно коорд. захоронения
View_Add('<hr>');
View_Add(FormList());
View_Add('<hr>');
View_Add(GraveList01($xx));
View_Add('<hr>');
View_Add("</div>");
View_Add(' </div > ');
//print_r($gr);
View_Add(Page_Down());
View_Out();
View_Clear();
