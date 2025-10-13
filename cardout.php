<?php
/**
 * Сторінка детальної інформації про поховання
 */
require_once $_SERVER['DOCUMENT_ROOT'] . "/function.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/classes/lenta.php";

$dblink = DbConnect();
$lenta = new Lenta($dblink);

function DateFormat(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') return '';
    $ts = strtotime($date);
    if (!$ts) return '';
    return date('d.m.Y', $ts);
}

function CardInfo(mysqli $dblink, int $idx): string
{
    $idx = (int)$idx;

    $sql = "
    SELECT 
        g.*, 
        c.title AS cemetery_title,
        d.title AS district_title,
        r.title AS region_title,
        m.title AS town_title,
        CONCAT(u.lname, ' ', u.fname) AS added_by
    FROM grave g
    LEFT JOIN cemetery c ON g.idxkladb = c.idx
    LEFT JOIN district d ON c.district = d.idx
    LEFT JOIN region r ON d.region = r.idx
    LEFT JOIN misto m ON c.town = m.idx
    LEFT JOIN users u ON g.idxadd = u.idx
    WHERE g.idx = $idx
    LIMIT 1
";

    $res = mysqli_query($dblink, $sql);
    if (!$res || mysqli_num_rows($res) == 0) {
        return '<div class="cardout"><p>Запис не знайдено.</p></div>';
    }

    $r = mysqli_fetch_assoc($res);

    $fio = trim(($r['lname'] ?? '') . ' ' . ($r['fname'] ?? '') . ' ' . ($r['mname'] ?? ''));
    $d1  = ($r['dt1'] && $r['dt1'] !== '0000-00-00') ? DateFormat($r['dt1']) : 'Дата не вказана';
    $d2  = ($r['dt2'] && $r['dt2'] !== '0000-00-00') ? DateFormat($r['dt2']) : 'Дата не вказана';

    // Фото
    $photos = [];
    foreach (['photo1', 'photo2'] as $p) {
        if (!empty($r[$p]) && is_file($_SERVER['DOCUMENT_ROOT'] . $r[$p])) {
            $photos[] = $r[$p];
        }
    }
    if (empty($photos)) {
        $photos[] = '/graves/noimage.jpg';
    }

    // Слайдер
    $photoHtml = '<div class="cardout-photo-slider">';
    $photoHtml .= '<img id="card-photo" src="' . htmlspecialchars($photos[0]) . '" alt="' . htmlspecialchars($fio) . '">';

    if (count($photos) > 1) {
        $photoHtml .= '
        <button class="photo-prev" onclick="changePhoto(-1)">&#10094;</button>
        <button class="photo-next" onclick="changePhoto(1)">&#10095;</button>';
    }

    $photoHtml .= '</div>';

    $photoJs = '';
    if (count($photos) > 1) {
        $photoJs = '
    <script>
        let photos = ' . json_encode(array_values($photos)) . ';
        let currentPhoto = 0;
        const imgElement = document.getElementById("card-photo");

        function changePhoto(dir) {
            const newIndex = currentPhoto + dir;

            if (newIndex < 0 || newIndex >= photos.length) {
                return; 
            }

            currentPhoto = newIndex;
            imgElement.src = photos[currentPhoto];
        }
        
        function updateButtons() {
    document.querySelector(".photo-prev").disabled = (currentPhoto === 0);
    document.querySelector(".photo-next").disabled = (currentPhoto === photos.length - 1);
}

function changePhoto(dir) {
    const newIndex = currentPhoto + dir;
    if (newIndex < 0 || newIndex >= photos.length) return;

    currentPhoto = newIndex;
    imgElement.src = photos[currentPhoto];
    updateButtons();
}

updateButtons();
    </script>';
    }


    $out = '
<div class="cardout">
    <div class="cardout-top">
        <div class="cardout-photo">' . $photoHtml . '</div>
        <div class="cardout-data">
            <h3 class="cardout-subtitle">
                <span class="cardout-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard" viewBox="0 0 16 16">
                        <path d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5"/>
                        <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z"/>
                    </svg>
                </span>
                Детальна інформація
            </h3>
            <h2>' . htmlspecialchars($fio) . '</h2>
            <div class="cardout-dates">
                <div class="date-block">
                    <div class="date-label">Дата народження</div>
                    <div class="date-value">' . $d1 . '</div>
                </div>
                <div class="date-block">
                    <div class="date-label">Дата смерті</div>
                    <div class="date-value">' . $d2 . '</div>
                </div>
            </div>

            <h3 class="cardout-subtitle">
                <span class="cardout-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                    </svg>
                </span>
                Місце розташування
            </h3>
            <p class="location-data">' . (
        !empty($r['region_title']) || !empty($r['district_title']) || !empty($r['town_title'])
            ? (!empty($r['region_title']) ? htmlspecialchars($r['region_title']) . ' область' : '')
            . (!empty($r['district_title']) ? ', ' . htmlspecialchars($r['district_title']) . ' район' : '')
            . (!empty($r['town_title']) ? ', населений пункт: ' . htmlspecialchars($r['town_title']) : '')
            : 'Інформація про місце розташування не вказана'
        ) . '</p>
        </div>
    </div>

    <div class="cemeteryinfo">
        <h3 class="cardout-subtitle-3">
            <span class="cardout-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-map" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11л-4 .8v12.98z"/>
                </svg>
            </span>
            Місце поховання
        </h3>

        <div class="grave-info">' . (
        !empty($r['cemetery_title']) || !empty($r['pos1']) || !empty($r['pos2']) || !empty($r['pos3'])
            ? ((!empty($r['cemetery_title'])
                ? '<p class="cemetery-title"><b>Кладовище:</b> ' . htmlspecialchars($r['cemetery_title']) . '</p>'
                : '<p class="cemetery-title"><b>Кладовище:</b> <span class="nocem">Інформація не вказана</span></p>')
            . '<div class="grave-locations">'
            . (!empty($r['pos1']) ? '<div class="grave-item"><span>Квартал</span><strong>' . htmlspecialchars($r['pos1']) . '</strong></div>' : '')
            . (!empty($r['pos2']) ? '<div class="grave-item"><span>Ряд</span><strong>' . htmlspecialchars($r['pos2']) . '</strong></div>' : '')
            . (!empty($r['pos3']) ? '<div class="grave-item"><span>Місце</span><strong>' . htmlspecialchars($r['pos3']) . '</strong></div>' : '')
            . '</div>'
        )
            : '<p>Інформація про місце поховання не вказана</p>'
        ) . '</div>
    </div>
' . $photoJs . '
</div>';

    return $out;
}


function advertising(){
    $out ='<div class="adv">
<h3>Рекламий блок</h3>
    <p>Тут може бути ваша реклама</p>
    <a href="#" class="button">Дізнатись більше</a>

</div>';
    return $out;
}
$idx = (int)$_GET['idx'];

$lpage = '<div class="lenta-container">';
$lpage .= $lenta->FormMassageAdd($idx);
$lpage .= $lenta->showForm();
$lpage .= $lenta->showMessages($idx);
$lpage .= '</div>';

// вывод страницы
if (!isset($_GET['idx']) || !is_numeric($_GET['idx'])) {
    die('Некоректний запит');
}
$idx = (int)$_GET['idx'];

View_Clear();
View_Add(Page_Up('Деталі поховання'));
View_Add('<link rel="stylesheet" href="/assets/css/cardout.css">');
View_Add('<link rel="stylesheet" href="/assets/css/lenta.css">');
View_Add(Menu_Up());


$out = '
<div class="outpage">
    <div class="cardout-wrapper">
        ' . CardInfo($dblink, $idx) . '
        ' . advertising() . '
    </div>
    ' . $lpage . '
</div>';
View_Add($out);

View_Add(Page_Down());
View_Out();
View_Clear();
