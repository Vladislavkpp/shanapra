<?php
/**
 * @var $md
 * @var $buf
 */
require_once "function.php";

$dblink = DbConnect();

View_Clear();
View_Add(Page_Up());
View_Add(Menu_Up());
View_Add('<div class="out">');
View_Add("<link rel='stylesheet' href='function.css'>");

$showMessage = false;
$messageHtml = '';
$messageType = '';
$messageText = '';

if (isset($_POST['id'])) {
    // если форма отправлена – обновляем
    $id = $_POST['id'];
    $lname = $_POST['lname'];
    $fname = $_POST['fname'];
    $mname = $_POST['mname'];
    $dt1 = $_POST['dt1'];
    $dt2 = $_POST['dt2'];

    $sql = 'UPDATE grave SET 
                lname="' . $lname . '",
                fname="' . $fname . '",
                mname="' . $mname . '",
                dt1="' . $dt1 . '",
                dt2="' . $dt2 . '"
            WHERE idx=' . $id;
    $res = mysqli_query($dblink, $sql);

    if ($res) {
        $showMessage = true;
        $messageType = 'alert-success';
        $messageText = 'Запис №'.$id.' успішно оновлено!';
    } else {
        $showMessage = true;
        $messageType = 'alert-error';
        $messageText = 'Помилка оновлення запису №'.$id;
    }
}

if (!$showMessage) {
    if (isset($_GET['id'])) {

        $id = $_GET['id'];
        $sql = 'SELECT * FROM grave WHERE idx=' . $id;
        $res = mysqli_query($dblink, $sql);
        $row = mysqli_fetch_assoc($res);

        if ($row) {
            View_Add('<div class="update-grave">');
            View_Add('<h2 class="form-title">Редагування запису №' . $row['idx'] . '</h2>');

            View_Add('<form method="post" action="editgrave.php">');
            View_Add('<input type="hidden" name="id" value="' . $row['idx'] . '">');

            View_Add('<div class="fullname">');
            View_Add('<div><label>Прізвище:</label><input type="text" name="lname" value="' . $row['lname'] . '"></div>');
            View_Add('<div><label>Ім\'я:</label><input type="text" name="fname" value="' . $row['fname'] . '"></div>');
            View_Add('<div><label>По батькові:</label><input type="text" name="mname" value="' . $row['mname'] . '"></div>');
            View_Add('</div>');

            View_Add('<div class="dates">');
            View_Add('<div><label>Дата народження:</label><input type="date" name="dt1" value="' . $row['dt1'] . '"></div>');
            View_Add('<div><label>Дата смерті:</label><input type="date" name="dt2" value="' . $row['dt2'] . '"></div>');
            View_Add('</div>');

            View_Add('<input type="submit" value="Зберегти">');
            View_Add('</form>');
            View_Add('</div>');

        } else {
            $showMessage = true;
            $messageType = 'alert-error';
            $messageText = 'Запис не знайдено!';
        }
    } else {
        $showMessage = true;
        $messageType = 'alert-error';
        $messageText = 'Помилка редагування!';
    }
}

if ($showMessage) {
    View_Add('<div class="update-grave has-message">
                <div class="alert '.$messageType.'">'.$messageText.'</div>
                <p class="back-link"><a href="graveupdate.php">Повернутися до списку</a></p>
              </div>');
}

View_Add('</div>'); // .out
View_Add(Page_Down());
View_Out();
View_Clear();
