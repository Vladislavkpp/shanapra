<?php
session_start();
echo "<pre>";
print_r($_POST);
echo "</pre>";
?>

    <form action="" method="post">
        <input type="text" name="fname" placeholder="Ім’я">
        <input type="text" name="lname" placeholder="Прізвище">
        <button type="submit">Відправити</button>
    </form>
<?php
