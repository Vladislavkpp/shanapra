<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

global $hide_page_down;
$hide_page_down = true;

$requestedUri = isset($_GET['from']) ? trim((string)$_GET['from']) : ((string)($_SERVER['REQUEST_URI'] ?? ''));
$requestedUriSafe = htmlspecialchars($requestedUri, ENT_QUOTES, 'UTF-8');

View_Clear();
View_Add(Page_Up('Технічні роботи'));
View_Add(<<<'HTML'
<style>
body {
    margin: 0;
    background:
        radial-gradient(circle at top left, rgba(255, 196, 0, 0.14), transparent 32%),
        radial-gradient(circle at 82% 18%, rgba(0, 179, 255, 0.16), transparent 28%),
        linear-gradient(135deg, #07111f 0%, #0c1930 42%, #050b17 100%);
    color: #f4f7fb;
    overflow-x: hidden;
}

body {
    padding-top: env(safe-area-inset-top);
    padding-bottom: env(safe-area-inset-bottom);
    background-color: #0a2a4a;
}

#wrapper {
        padding-top: 0px!important;
    }

.maintenance-screen,
.maintenance-screen * {
    box-sizing: border-box;
    font-family: "Manrope", "Segoe UI", sans-serif;
}

.maintenance-screen {
    position: relative;
    min-height: 100vh;
    padding: 32px 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.maintenance-screen::before,
.maintenance-screen::after {
    content: "";
    position: absolute;
    inset: auto;
    border-radius: 999px;
    filter: blur(10px);
    opacity: 0.55;
    pointer-events: none;
}

.maintenance-screen::before {
    width: 340px;
    height: 340px;
    top: -120px;
    left: -80px;
    background: radial-gradient(circle, rgba(255, 196, 0, 0.34), rgba(255, 196, 0, 0));
    animation: maintenanceOrbOne 9s ease-in-out infinite;
}

.maintenance-screen::after {
    width: 420px;
    height: 420px;
    right: -120px;
    bottom: -160px;
    background: radial-gradient(circle, rgba(0, 179, 255, 0.28), rgba(0, 179, 255, 0));
    animation: maintenanceOrbTwo 11s ease-in-out infinite;
}

.maintenance-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(146, 170, 199, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(146, 170, 199, 0.08) 1px, transparent 1px);
    background-size: 42px 42px;
    mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.92), rgba(0, 0, 0, 0.18));
    opacity: 0.55;
    animation: maintenanceGrid 18s linear infinite;
}

.maintenance-panel {
    position: relative;
    width: 100%;
    max-width: 760px;
    padding: 32px;
    border-radius: 30px;
    background: linear-gradient(180deg, rgba(10, 20, 37, 0.9), rgba(6, 13, 24, 0.88));
    border: 1px solid rgba(255, 255, 255, 0.09);
    box-shadow:
        0 28px 90px rgba(0, 0, 0, 0.52),
        inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.maintenance-panel::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255, 196, 0, 0.09), transparent 32%, rgba(0, 179, 255, 0.08) 74%, transparent 100%);
    pointer-events: none;
}

.maintenance-status {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 40px;
    padding: 0 16px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.09);
    color: #f9cc52;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.14em;
}

.maintenance-status__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ffd84d;
    box-shadow: 0 0 0 0 rgba(255, 216, 77, 0.62);
    animation: maintenancePulse 1.9s infinite;
}

.maintenance-layout {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) 250px;
    gap: 24px;
    align-items: center;
    margin-top: 26px;
}

.maintenance-copy {
    min-width: 0;
}

.maintenance-title {
    margin: 0;
    font-size: clamp(2rem, 4.8vw, 4rem);
    line-height: 0.96;
    font-weight: 900;
    letter-spacing: -0.04em;
}

.maintenance-title span {
    display: block;
    color: #ffd34a;
}

.maintenance-text {
    margin: 18px 0 0;
    max-width: 520px;
    color: rgba(231, 238, 247, 0.82);
    font-size: 1rem;
    line-height: 1.75;
}

.maintenance-uri {
    margin-top: 18px;
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(191, 208, 230, 0.84);
    font-size: 13px;
    line-height: 1.5;
    word-break: break-word;
}

.maintenance-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.maintenance-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 20px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 800;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease, border-color 0.25s ease;
}

.maintenance-btn--primary {
    color: #08111d;
    background: linear-gradient(135deg, #ffd84d 0%, #f6b700 100%);
    box-shadow: 0 16px 36px rgba(246, 183, 0, 0.24);
}

.maintenance-btn--secondary {
    color: #edf4ff;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.maintenance-btn:hover {
    transform: translateY(-2px);
}

.maintenance-btn--primary:hover {
    box-shadow: 0 20px 44px rgba(246, 183, 0, 0.28);
}

.maintenance-visual {
    position: relative;
    height: 250px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.maintenance-ring,
.maintenance-ring::before,
.maintenance-ring::after {
    position: absolute;
    border-radius: 50%;
}

.maintenance-ring {
    width: 212px;
    height: 212px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: radial-gradient(circle at center, rgba(255, 214, 77, 0.12), rgba(255, 214, 77, 0) 62%);
    animation: maintenanceRotate 16s linear infinite;
}

.maintenance-ring::before,
.maintenance-ring::after {
    content: "";
    inset: 18px;
    border: 1px dashed rgba(116, 197, 255, 0.28);
}

.maintenance-ring::after {
    inset: 42px;
    border-style: solid;
    border-color: rgba(255, 255, 255, 0.06);
}

.maintenance-bolt {
    position: relative;
    width: 122px;
    height: 122px;
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffd84d;
    background: linear-gradient(180deg, rgba(255, 216, 77, 0.18), rgba(255, 216, 77, 0.04));
    border: 1px solid rgba(255, 216, 77, 0.24);
    box-shadow:
        0 22px 48px rgba(0, 0, 0, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
    animation: maintenanceBolt 3.2s ease-in-out infinite;
}

.maintenance-bolt svg {
    width: 64px;
    height: 64px;
    display: block;
    filter: drop-shadow(0 0 18px rgba(255, 216, 77, 0.3));
}

.maintenance-sparks {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.maintenance-spark {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #7fd5ff;
    box-shadow: 0 0 18px rgba(127, 213, 255, 0.6);
    opacity: 0.72;
}

.maintenance-spark--one {
    top: 34px;
    right: 34px;
    animation: maintenanceSparkOne 2.6s ease-in-out infinite;
}

.maintenance-spark--two {
    bottom: 40px;
    left: 24px;
    width: 10px;
    height: 10px;
    background: #ffd84d;
    box-shadow: 0 0 20px rgba(255, 216, 77, 0.66);
    animation: maintenanceSparkTwo 3.4s ease-in-out infinite;
}

.maintenance-spark--three {
    top: 112px;
    left: 2px;
    width: 6px;
    height: 6px;
    animation: maintenanceSparkThree 3s ease-in-out infinite;
}

@keyframes maintenanceOrbOne {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50% { transform: translate3d(32px, 24px, 0) scale(1.08); }
}

@keyframes maintenanceOrbTwo {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50% { transform: translate3d(-28px, -18px, 0) scale(0.94); }
}

@keyframes maintenanceGrid {
    0% { transform: translateY(0); }
    100% { transform: translateY(42px); }
}

@keyframes maintenancePulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 216, 77, 0.55); }
    70% { box-shadow: 0 0 0 12px rgba(255, 216, 77, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 216, 77, 0); }
}

@keyframes maintenanceRotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes maintenanceBolt {
    0%, 100% { transform: translateY(0); box-shadow: 0 22px 48px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.12); }
    50% { transform: translateY(-8px); box-shadow: 0 26px 60px rgba(0, 0, 0, 0.4), 0 0 28px rgba(255, 216, 77, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.12); }
}

@keyframes maintenanceSparkOne {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.55; }
    50% { transform: translateY(-14px) scale(1.3); opacity: 1; }
}

@keyframes maintenanceSparkTwo {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); opacity: 0.45; }
    50% { transform: translate3d(14px, -12px, 0) scale(1.22); opacity: 1; }
}

@keyframes maintenanceSparkThree {
    0%, 100% { transform: translate3d(0, 0, 0); opacity: 0.38; }
    50% { transform: translate3d(10px, 8px, 0); opacity: 0.92; }
}

@media (max-width: 760px) {
    .maintenance-screen {
        padding: 18px 14px;
    }

    .maintenance-panel {
        padding: 22px 18px 20px;
        border-radius: 24px;
    }

    .maintenance-layout {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .maintenance-visual {
        order: -1;
        height: 206px;
    }

    .maintenance-ring {
        width: 172px;
        height: 172px;
    }

    .maintenance-bolt {
        width: 104px;
        height: 104px;
        border-radius: 24px;
    }

    .maintenance-bolt svg {
        width: 52px;
        height: 52px;
    }

    .maintenance-actions {
        flex-direction: column;
    }

    .maintenance-btn {
        width: 100%;
    }

    .maintenance-text {
        font-size: 0.95rem;
    }
}
</style>
HTML);
View_Add('<section class="maintenance-screen">');
View_Add('<div class="maintenance-grid" aria-hidden="true"></div>');
View_Add('<div class="maintenance-panel">');
View_Add('<div class="maintenance-status"><span class="maintenance-status__dot"></span><span>Технічні роботи</span></div>');
View_Add('<div class="maintenance-layout">');
View_Add('<div class="maintenance-copy">');
View_Add('<h1 class="maintenance-title">Тимчасово<span>недоступно</span></h1>');
View_Add('<p class="maintenance-text">Ми оновлюємо цей розділ і тимчасово проводимо технічні роботи. Частина функцій може бути недоступною, але сторінка повернеться в роботу після завершення оновлення.</p>');
if ($requestedUriSafe !== '') {
    View_Add('<div class="maintenance-uri">' . $requestedUriSafe . '</div>');
}
View_Add('<div class="maintenance-actions">');
View_Add('<a class="maintenance-btn maintenance-btn--primary" href="/">На головну</a>');
View_Add('<a class="maintenance-btn maintenance-btn--secondary" href="javascript:location.reload()">Оновити сторінку</a>');
View_Add('</div>');
View_Add('</div>');
View_Add('<div class="maintenance-visual" aria-hidden="true">');
View_Add('<div class="maintenance-ring"></div>');
View_Add('<div class="maintenance-bolt">');
View_Add('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-bolt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 2l.018 .001l.016 .001l.083 .005l.011 .002h.011l.038 .009l.052 .008l.016 .006l.011 .001l.029 .011l.052 .014l.019 .009l.015 .004l.028 .014l.04 .017l.021 .012l.022 .01l.023 .015l.031 .017l.034 .024l.018 .011l.013 .012l.024 .017l.038 .034l.022 .017l.008 .01l.014 .012l.036 .041l.026 .027l.006 .009c.12 .147 .196 .322 .218 .513l.001 .012l.002 .041l.004 .064v6h5a1 1 0 0 1 .868 1.497l-.06 .091l-8 11c-.568 .783 -1.808 .38 -1.808 -.588v-6h-5a1 1 0 0 1 -.868 -1.497l.06 -.091l8 -11l.01 -.013l.018 -.024l.033 -.038l.018 -.022l.009 -.008l.013 -.014l.04 -.036l.028 -.026l.008 -.006a1 1 0 0 1 .402 -.199l.011 -.001l.027 -.005l.074 -.013l.011 -.001l.041 -.002z" /></svg>');
View_Add('</div>');
View_Add('<div class="maintenance-sparks"><span class="maintenance-spark maintenance-spark--one"></span><span class="maintenance-spark maintenance-spark--two"></span><span class="maintenance-spark maintenance-spark--three"></span></div>');
View_Add('</div>');
View_Add('</div>');
View_Add('</div>');
View_Add('</section>');
View_Add(Page_Down());
View_Out();
View_Clear();
