<?php
/**
 * @var $md
 * @var $buf
 */
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";

function faqpage() {
    $out = '';

    $out .= '<div class="faq-page">';
    $out .= '<div class="faq-container">';
    $out .= '<h1 class="faq-title">Запитання й відповіді</h1>';

    $faqLinks = [
        'login'         => ['url' => '/auth.php', 'text' => '"Увійти"'],
        'register'      => ['url' => '/auth.php?mode=register', 'text' => '"Ще не зареєстровані? Зареєструватися"'],
        'support'       => ['url' => '/messenger.php?type=3', 'text' => '"Підтримка"'],
        'profile'       => ['url' => '/profile.php', 'text' => 'Профілю'],
        'settings'      => ['url' => '/profile.php?md=2', 'text' => '"Налаштування профілю"'],
        'cleaners'      => ['url' => '/clean-cemeteries.php', 'text' => '"Прибирання кладовищ"'],
        'password_reset'=> ['url' => '/strepair.php', 'text' => '"Забули пароль?"'],
        'cabinet'       => ['url' => '/profile.php?md=10', 'text' => '"Кабінет прибиральника"'],
    ];

    $replaceLinks = function($text) use ($faqLinks) {
        foreach ($faqLinks as $key => $linkData) {
            $linkHtml = '<a href="' . $linkData['url'] . '" class="faq-link">' . htmlspecialchars($linkData['text']) . '</a>';
            $text = str_replace('{' . $key . '}', $linkHtml, $text);
        }
        return $text;
    };

    $faqSections = [
        'Реєстрація та обліковий запис' => [
            [ 'question' => 'Як зареєструватися на сайті?', 'answer' => 'Натисніть у верхньому меню кнопку {login}. У формі авторизації знайдіть посилання {register} та натисніть його. Відкриється форма реєстрації, де потрібно заповнити свої дані та підтвердити реєстрацію.' ],
            [ 'question' => 'Як змінити пароль?', 'answer' => 'Щоб змінити пароль, перейдіть до {profile} → {settings} → "Змінити пароль". Введіть старий пароль, а потім новий, для підтвердження натисніть "Змінити".' ],
            [ 'question' => 'Що робити якщо я забув свій пароль?', 'answer' => 'На сторінці входу натисніть посилання {password_reset}. Введіть email, який ви використовували при реєстрації, та натисніть "Надіслати". На вашу пошту прийде лист із посиланням для скидання паролю. Посилання дійсне протягом години.' ],
        ],
        'Прибирання кладовищ' => [
            [ 'question' => 'Як замовити прибиральника кладовища?', 'answer' => 'Перейдіть на сторінку {cleaners} у меню «Послуги». Виберіть регіон, район і кладовище за допомогою фільтрів. Оберіть прибиральника зі списку та натисніть "Замовити цього прибиральника". Заповніть форму: вкажіть квартал/ряд/місце, бажану дату та послуги. Після відправки замовлення з прибиральником можна спілкуватися через чат.' ],
            [ 'question' => 'Як стати прибиральником кладовища?', 'answer' => 'Спочатку зареєструйтеся або увійдіть на сайт. Перейдіть на сторінку {cleaners} та натисніть кнопку "Стати працівником". Після цього перейдіть у {profile} → {cabinet} і заповніть інформацію про себе: регіон, район, кладовища та послуги. Після збереження ваша картка стане активною для замовників.' ],
        ],
        'Поховання' => [
            [ 'question' => 'Як редагувати поховання?', 'answer' => 'Відкрийте сторінку поховання (картку з деталями). Якщо поховання додали ви, зверху з\'явиться кнопка "Редагувати". Натисніть її, внесіть зміни в поля (ПІБ, дати, фото тощо) та натисніть "Зберегти". Редагувати можна лише ті поховання, які ви самі додали.' ],
        ],
        'Модерація контенту' => [
            [ 'question' => 'Який контент проходить модерацію?', 'answer' => 'На модерацію потрапляють записи про поховання та кладовища, які додають або редагують користувачі. Після збереження запис отримує статус "На модерації".' ],
            [ 'question' => 'Де подивитися статус модерації?', 'answer' => 'Для поховань — у {profile} → "Публікації" (біля кожної картки показано статус). Для кладовищ — на сторінці конкретного кладовища під назвою відображається бейдж модерації.' ],
            [ 'question' => 'Що означають статуси та що робити, якщо запис відхилили?', 'answer' => '"На модерації" — перевірка триває, "Перевірено модератором" — запис схвалено і доступний усім, "Відхилено" — потрібно виправити дані/матеріали та зберегти їх повторно, після чого запис знову потрапить на модерацію.' ],
        ],
        'Підтримка' => [
            [ 'question' => 'Як зв\'язатися з підтримкою?', 'answer' => 'Якщо ви не авторизовані, у верхньому меню поруч із кнопкою "Увійти" є кнопка {support} — натисніть її, відкриється чат, де можна написати технічній підтримці. Якщо ви авторизовані, натисніть на іконку свого профілю у верхньому меню, у випадаючому меню оберіть {support} — відкриється чат з підтримкою.' ],
        ],
    ];

    foreach ($faqSections as $sectionTitle => $faqs) {
        $out .= '<div class="faq-section">';
        $out .= '<h2 class="faq-section-title">' . htmlspecialchars($sectionTitle) . '</h2>';
        foreach ($faqs as $faq) {
            $out .= '<div class="faq-item">';
            $out .= '<div class="faq-question">'.htmlspecialchars($faq['question']).'</div>';
            $out .= '<div class="faq-answer">' . $replaceLinks($faq['answer']) . '</div>';
            $out .= '</div>';
        }
        $out .= '</div>';
    }

    $out .= '</div>';
    $out .= '</div>';

    $out .= <<<JS
<script>
document.querySelectorAll('.faq-question').forEach(function(question){
    question.addEventListener('click', function(){
        var item = this.parentElement;
        var answer = this.nextElementSibling;

        if(item.classList.contains('active')) {
            answer.style.maxHeight = null;
            item.classList.remove('active');
        } else {
            document.querySelectorAll('.faq-item.active').forEach(function(activeItem){
                activeItem.classList.remove('active');
                activeItem.querySelector('.faq-answer').style.maxHeight = null;
            });
            item.classList.add('active');
            answer.style.maxHeight = answer.scrollHeight + "px";
        }
    });
});
</script>
JS;

    return $out;
}

// === Вывод страницы ===
View_Clear();
View_Add(Page_Up('FAQ'));
View_Add(Menu_Up());

View_Add('<div class="out">');

View_Add(faqpage());

View_Add('</div>');

View_Add(Page_Down());
View_Out();
View_Clear();
