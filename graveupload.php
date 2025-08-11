<?php

/**
 * @var $md
 * @var $buf
 */
//require_once $_SERVER['DOCUMENT_ROOT'] .'/vendor/autoload.php';
require_once "function.php";


View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add(Menu_Left());


function Fupload(): string
{
    global $id;
    $id = 4;
    $out = '<div class="fupload-form">
<form action="?" method="post" enctype="multipart/form-data">
    <input type="hidden" name="md" value="addimg">
    <input type="hidden" name="idxx" value="' . $id . '">
    <input type="hidden" name="MAX_FILE_SIZE" value="16000000" />
    <table class="upload-table">
        <tr><td colspan="3">
            <input type="file" name="imgfile" accept=".jpg,.jpeg,.png"><br><br>
        </td></tr>
        <tr>
            <td colspan="2">
                <input type="submit" name="submit" value="Загрузить">
            </td>
        </tr>
    </table>
</form></div>';
    return $out;
}


if ($_POST['md'] == 'addimg') {
    $xidxx = $_POST['idxx'] ?? '';
    if (is_dir('/fotouploads')) {
        View_Add("Папка существует");
    } else {
        @mkdir($_SERVER['DOCUMENT_ROOT'].'/fotouploads',0777,true);
    }




    if ($xidxx != '') {
        if (isset($_FILES['imgfile']) && $_FILES['imgfile']['error'] === 0) {
            $tmpfile = $_FILES['imgfile']['tmp_name'];
            $originalName = $_FILES['imgfile']['name'];

            $upf = strtolower(rus2lat(pathinfo($originalName, PATHINFO_FILENAME)));
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                View_Add("Недопустимый формат файла.<br>");
            } else {
                $basename = $upf . '.' . $ext;
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/fotouploads/';
                $upfile = $uploadDir . $basename;

                while (file_exists($upfile)) {
                    $upf .= 'x';
                    $basename = $upf . '.' . $ext;
                    $upfile = $uploadDir . $basename;
                }

                if (move_uploaded_file($tmpfile, $upfile)) {
                    View_Add("Файл успешно загружен: <strong>$basename</strong><br>");
                    // $sql = "UPDATE table SET img='$basename' WHERE idx='$xidxx'";
                    // mysqli_query($dblink, $sql);
                } else {
                    View_Add("Ошибка при сохранении файла.<br>");
                }
            }
        } else {
            View_Add("Файл не загрузился или произошла ошибка.<br>");
        }
    } else {
        View_Add("Идентификатор записи не передано.<br>");
    }

}

//function isdir / function mcdir


//Основной екран


View_Add(Fupload());

View_Add('</div>');
View_Add(Page_Down());
View_Out();
View_Clear();

