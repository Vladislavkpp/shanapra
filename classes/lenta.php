<?php

class Lenta
{
    private $dblink;

    public function __construct($dblink)
    {
        $this->dblink = $dblink;
    }

    // авторизован ли пользователь
    private function isAuthorized(): bool
    {
        return (isset($_SESSION['logged']) && $_SESSION['logged'] == 1 && isset($_SESSION['uzver']) && ($_SESSION['uzver'] > 0));
    }

    // Добавление сообщения
    public function addMessage(string $text, int $idxabon): bool
    {
        if (!$this->isAuthorized()) {
            return false;
        }

        $user_id = (int)$_SESSION['uzver'];
        $idxabon = (int)$idxabon;
        $text = mysqli_real_escape_string($this->dblink, trim($text));

        if ($text === '') return false;

        $sql = "INSERT INTO lenta (idxabon, idxuser, dttmadd, atext) 
            VALUES ($idxabon, $user_id, NOW(), '$text')";
        return mysqli_query($this->dblink, $sql);
    }


    public function FormMassageAdd(int $idxabon, string $successMessage = "Публікація успішно опублікована!", string $errorMessage = "Не вдалося додати повідомлення або ви не авторизовані."): string
    {
        $out = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
            $added = $this->addMessage($_POST['message'], $idxabon);
            $message = $added ? $successMessage : $errorMessage;
            $typeClass = $added ? 'success' : 'error';

            $out .= '
        <div id="successModal" class="lenta-modal">
            <div class="lenta-modal-content">
                <div class="lenta-modal-title-container">
                    <span class="modal-title-text">Сповіщення про публікацію</span>
                    <span class="lenta-close" onclick="closeSuccessModal()">
                        <img src="assets/images/closemodal.png" alt="Закрити" class="close-icon">
                    </span>
                </div>
                <div class="massage-text-modal ' . $typeClass . '">' . htmlspecialchars($message) . '</div>
            </div>
        </div>

        <script>
        (function(){
            var scrollPositionSuccess = 0;

            function openSuccessModal() {
                var modal = document.getElementById("successModal");
                modal.classList.add("show");

                scrollPositionSuccess = window.pageYOffset;
                document.body.style.top = -scrollPositionSuccess + "px";
                document.body.style.position = "fixed";
                document.body.style.width = "100%";
                document.body.style.overflow = "hidden";
                document.documentElement.style.overflow = "hidden";

                setTimeout(closeSuccessModal, 5000);
            }

            function closeSuccessModal() {
                var modal = document.getElementById("successModal");
                modal.classList.remove("show");

                document.body.style.position = "";
                document.body.style.top = "";
                document.body.style.width = "";
                document.body.style.overflow = "";
                document.documentElement.style.overflow = "";
                window.scrollTo(0, scrollPositionSuccess);
            }

            window.openSuccessModal = openSuccessModal;
            window.closeSuccessModal = closeSuccessModal;

            window.addEventListener("click", function(event){
                var modal = document.getElementById("successModal");
                if(event.target === modal) closeSuccessModal();
            });

            // сразу открыть после добавления
            openSuccessModal();
        })();
        </script>';
        }

        return $out;
    }



    // получаем сообщения
    public function getMessages(int $idxabon, int $limit = 50): array
    {
        $idxabon = (int)$idxabon;
        $limit = (int)$limit;
        $sql = "SELECT 
                l.idx,
                l.idxuser,
                l.atext,
                l.dttmadd,
                CONCAT(IFNULL(u.fname, ''), ' ', IFNULL(u.lname, '')) AS username
            FROM lenta l
            LEFT JOIN users u ON l.idxuser = u.idx
            WHERE l.idxabon = $idxabon
            ORDER BY l.dttmadd DESC
            LIMIT $limit";

        $res = mysqli_query($this->dblink, $sql);
        $messages = [];

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $messages[] = $row;
            }
        }

        return $messages;
    }

    public function showForm(): string
    {
        $out = '';

        if ($this->isAuthorized()) {
            $userId = (int)$_SESSION['uzver'];
            $res = mysqli_query($this->dblink, "SELECT * FROM users WHERE idx = $userId");
            $p = $res ? mysqli_fetch_assoc($res) : [];

            $avatar = !empty($p['avatar']) ? $p['avatar'] : '/avatars/ava.png';
            $fullname = htmlspecialchars(trim(($p['fname']) . ' ' . ($p['lname'])));
            $name = htmlspecialchars(trim(($p['fname'])));

            $out .= '
<div class="lenta-create-post">
    <div class="lenta-avatar">
        <a href="/profile.php" class="avatar-link">
            <img src="' . $avatar . '" alt="Аватар" class="user-avatar">
        </a>
    </div>
    <input type="text" placeholder="Що у вас нового, ' . $name . '?" class="lenta-input" readonly onclick="openPostModal()">
</div>

<div id="postModal" class="lenta-modal">
    <div class="lenta-modal-content">
        <div class="lenta-modal-title-container">
            <span class="modal-title-text">Створити публікацію</span>
            <span class="lenta-close" onclick="closePostModal()">
                <img src="assets/images/closemodal.png" alt="Закрити" class="close-icon">
            </span>
        </div>

        <div class="lenta-modal-header">
            <img src="' . $avatar . '" alt="Аватар" class="user-avatar small-avatar">
            <div class="lenta-user-info">
                <span class="user-name">' . $fullname . '</span>
                <span class="privacy-indicator">Публікація для всіх</span>
            </div>
        </div>

        <form method="post">
            <textarea name="message" placeholder="Що у вас нового, ' . $name . '?" required></textarea>
             <div id="charCount" class="char-count">0 / 2000</div>
            <button type="submit" class="lenta-btn">Опублікувати</button>
        </form>
    </div>
</div>';

            $out .= "<script>
(function(){
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--scrollbar-width', scrollbarWidth + 'px');

    function adjustTextarea(textarea) {
        const baseFontSize = 18;
        const minFontSize = 12;
        const maxHeight = 300;

        let currentFontSize = parseInt(window.getComputedStyle(textarea).fontSize, 10) || baseFontSize;

        textarea.style.overflow = 'hidden';
        textarea.style.height = 'auto';

        let scrollH = textarea.scrollHeight;

        while (scrollH > maxHeight && currentFontSize > minFontSize) {
            currentFontSize--;
            textarea.style.fontSize = currentFontSize + 'px';
            textarea.style.height = 'auto';
            scrollH = textarea.scrollHeight;
        }

        if (scrollH > maxHeight) {
            textarea.style.height = maxHeight + 'px';
            textarea.style.overflow = 'auto';
        } else {
            textarea.style.height = scrollH + 'px';
            textarea.style.overflow = 'hidden';
        }

        if (!textarea.value) {
            textarea.style.fontSize = baseFontSize + 'px';
        }
    }

    function toggleButton(textarea, button) {
        const maxChars = textarea.getAttribute('maxlength') || 2000;
        const length = textarea.value.length;

        if (length === 0 || length > maxChars) {
            button.disabled = true;
            button.classList.add('disabled');
        } else {
            button.disabled = false;
            button.classList.remove('disabled');
        }
    }

    function updateCharCount(textarea, counter) {
        const maxChars = textarea.getAttribute('maxlength') || 2000;
        const length = textarea.value.length;

        if (length > maxChars) {
            counter.textContent = length + ' / ' + maxChars + ' - Максимальна кількість символів';
            counter.style.color = 'red';
        } else {
            counter.textContent = length + ' / ' + maxChars;
            counter.style.color = '#888';
        }
    }

    var scrollPosition = 0;

    function openPostModal() {
        var modal = document.getElementById('postModal');
        modal.classList.add('show');

        scrollPosition = window.pageYOffset;
        document.body.style.top = -scrollPosition + 'px';
        document.documentElement.classList.add('modal-open');
        document.body.classList.add('modal-open');

        var textarea = modal.querySelector('textarea');
        var button = modal.querySelector('button[type=\"submit\"]');
        var counter = modal.querySelector('#charCount');

        if (textarea && button && counter) {
            textarea.removeEventListener('input', textarea._adjustHandler || (()=>{}));
            textarea._adjustHandler = function() {
                adjustTextarea(textarea);
                toggleButton(textarea, button);
                updateCharCount(textarea, counter);
            };
            textarea.addEventListener('input', textarea._adjustHandler);

            textarea.addEventListener('paste', function() {
                setTimeout(() => textarea._adjustHandler(), 0);
            });

            adjustTextarea(textarea);
            toggleButton(textarea, button);
            updateCharCount(textarea, counter);
        }
    }

    function closePostModal() {
        var modal = document.getElementById('postModal');
        modal.classList.remove('show');

        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
        document.body.style.top = '';
        window.scrollTo(0, scrollPosition);
    }

    window.addEventListener('click', function(event){
        var modal = document.getElementById('postModal');
        if(event.target === modal) closePostModal();
    });

    window.openPostModal = openPostModal;
    window.closePostModal = closePostModal;
})();
</script>";




        } else {
            $out .= "<div class='notf-auth'>
<p class='massage-auth'>Щоб додати повідомлення, необхідно увійти в систему.</p>
<a href='auth.php' class='link-auth'>Увійти</a>
</div>";
        }

        return $out;
    }


    // Вывод сообщений
    public function showMessages(int $idxabon): string
    {
        $messages = $this->getMessages($idxabon);
        $out = '';

        if (!$messages) {
            $html = "<p class='nomassage'>Публікацій немає.";

            if (isset($_SESSION['logged']) && $_SESSION['logged'] == 1) {
                $html .= "<a href='javascript:void(0)' onclick='openPostModal()' class='linkaddmass'>
                        Натисніть для додавання публікації
                      </a>";
            }

            $html .= "</p>";
            return $html;
        }


        //реакции
        $action1 = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart" viewBox="0 0 16 16">
  <path d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053c-.523 1.023-.641 2.5.314 4.385.92 1.815 2.834 3.989 6.286 6.357 3.452-2.368 5.365-4.542 6.286-6.357.955-1.886.838-3.362.314-4.385C13.486.878 10.4.28 8.717 2.01zM8 15C-7.333 4.868 3.279-3.04 7.824 1.143q.09.083.176.171a3 3 0 0 1 .176-.17C12.72-3.042 23.333 4.867 8 15"/>
</svg>';

        $action2 = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-dots" viewBox="0 0 16 16">
  <path d="M5 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0m4 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
  <path d="m2.165 15.803.02-.004c1.83-.363 2.948-.842 3.468-1.105A9 9 0 0 0 8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6a10.4 10.4 0 0 1-.524 2.318l-.003.011a11 11 0 0 1-.244.637c-.079.186.074.394.273.362a22 22 0 0 0 .693-.125m.8-3.108a1 1 0 0 0-.287-.801C1.618 10.83 1 9.468 1 8c0-3.192 3.004-6 7-6s7 2.808 7 6-3.004 6-7 6a8 8 0 0 1-2.088-.272 1 1 0 0 0-.711.074c-.387.196-1.24.57-2.634.893a11 11 0 0 0 .398-2"/>
</svg>';

        $action3 = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply" viewBox="0 0 16 16">
  <path d="M6.598 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.7 8.7 0 0 0-1.921-.306 7 7 0 0 0-.798.008h-.013l-.005.001h-.001L7.3 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L2.614 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028zM7.8 10.386q.103 0 .223.006c.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96z"/>
</svg>';



        foreach ($messages as $msg) {
            $username = htmlspecialchars(trim($msg['username'] ?? 'Гість'));
            $text = nl2br(htmlspecialchars($msg['atext']));
            $timeAgo = timeAgo($msg['dttmadd']);

            $authorId = (int)$msg['idxuser'];
            $avatar = '/avatars/ava.png';

            if ($authorId > 0) {
                $res = mysqli_query($this->dblink, "SELECT avatar FROM users WHERE idx = $authorId LIMIT 1");
                if ($res && $row = mysqli_fetch_assoc($res)) {
                    $userAvatar = trim($row['avatar']);
                    if ($userAvatar !== '') {
                        $userAvatar = ltrim($userAvatar, '/');

                        $avatar = str_starts_with($userAvatar, 'avatars/')
                            ? '/' . $userAvatar
                            : '/avatars/' . $userAvatar;
                    }
                }
            }



            $out .= "
    <div class='lenta-message'>
        <div class='post-header'>
            <img src='$avatar' alt='avatar' class='post-avatar'>
            <div class='post-user-info'>
                <b class='post-username'>$username</b>
                <small class='post-date'>$timeAgo</small>
            </div>
        </div>
        <div class='post-text'>$text</div>
        <div class='post-actions'>
        <button class='post-action-btn'>$action1 Реакция</button>
        <button class='post-action-btn'>$action2 Комментировать</button>
        <button class='post-action-btn'>$action3 Поделиться</button>
    </div>
    </div>
    ";
        }

        return $out;
    }

}
function timeAgo($datetime) {
    date_default_timezone_set('Europe/Kiev');
    $time = strtotime($datetime);
    if (!$time) return '';

    $diff = time() - $time;

    if ($diff < 10) {
        return 'только что';
    } elseif ($diff < 60) {
        $seconds = $diff;
        return $seconds . ' ' . pluralForm($seconds, ['секунда', 'секунды', 'секунд']) . ' назад';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' ' . pluralForm($minutes, ['минута', 'минуты', 'минут']) . ' назад';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . pluralForm($hours, ['час', 'часа', 'часов']) . ' назад';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' ' . pluralForm($days, ['день', 'дня', 'дней']) . ' назад';
    } else {
        return date('d.m.Y H:i', $time);
    }
}

function pluralForm($number, $forms) {
    $n = abs($number) % 100;
    $n1 = $n % 10;

    if ($n > 10 && $n < 20) return $forms[2];
    if ($n1 > 1 && $n1 < 5) return $forms[1];
    if ($n1 == 1) return $forms[0];
    return $forms[2];
}
