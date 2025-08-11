<?php
/**
 * @var $md
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use vendor\vodovra\Dbs;
use vendor\vodovra\Page;
use vendor\vodovra\View;

$q = Dbs::Select('* from users where (email="' . $_SESSION['uzver'] . '")');
Page::SetTitle('User Page');
View::Add(Page::OutTop());
if (!$q) {
    View::Add('ALERT Помилка авторизації на сайті. <a href="/stlogin.php"> Спробуйте ще</a>');
} else {
    View::Add('<table class="userpage-table"><tr><td class="userpage-table-menu">'); //list-menu
    View::Add('<table>');
    //View::Add('<tr><td></td></tr>');
    View::Add('<tr><td><a href="/userpage.php?md=prof" class="">Профіль</a></td></tr>');
    if (1 == 1) {
        View::Add('<tr><td><a href="/userpage.php?md=" class="">Активація Профілю</a></td></tr>');
    }
    View::Add('<tr><td><a href="/userpage.php?md=" class="">Контакти</a></td></tr>');
    View::Add('<tr><td><a href="/userpage.php?md=" class="">Фінанси</a></td></tr>');
    View::Add('<tr><td>...</td></tr>');
    View::Add('<tr><td><a href="/index.php?md=356VBB-ER6RTY47656G-474563-6556HJYFYYU8-566YF7-56YUFGD42231" class="">Вихід</a></td></tr>');
    View::Add('</table>');
    View::Add('</td><td class="userpage-table-area">'); //workarea
    View::Add('<table>');
    if ($md == 'prof') {
        View::Add('<tr><td rowspan="3">');
        $a = $q[0]['avatar'];
        if ($a == '') {
            View::Add('<img class="userpage-ava" src="/Avatars/no_image.png">');
        } else {
            $b = '/avatars/' . $q[0]['idx'] . '.' . $a;
            if (is_file($_SERVER['DOCUMENT_ROOT'] . $b)) {
                View::Add('<img class="userpage-ava" src="' . $b . '">');
            }
        }
        View::Add('<br>Змінити</td><td rowspan="3">&nbsp;&nbsp;&nbsp;</td><td>Ім`я</td><td>' . $q[0]['fname'] . ' Змінити</td></tr>');
        View::Add('<tr><td>Призвіще</td><td>' . $q[0]['lname'] . ' Змінити</td></tr>');
        View::Add('<tr><td>Email</td><td>' . $q[0]['email']);
        if ($q[0]['activ'] == 1) {
            View::Add('img=Verified');
        }
        View::Add('</td></tr>');
        View::Add('<tr><td></td><td></td></tr>');
    }
    View::Add('</table>');
    View::Add('</td></tr></table>');
}
View::Add(Page::OutBottom());
View::Out();

