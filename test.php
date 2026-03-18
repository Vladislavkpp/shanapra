<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

echo Page_Up('Test');
echo Menu_Up();
echo <<<HTML
<style>
.test-layout {
    width: 100%;
}

.test-main {
    width: min(1180px, calc(100% - 36px));
    margin: 24px auto 0;
    padding: 24px;
    border-radius: 16px;
    background: #11273b;
    box-shadow: 0 12px 30px rgba(8, 30, 52, 0.09);
    color: #193754;
    font-family: "Manrope", "Segoe UI", Tahoma, sans-serif;
}

.test-main h1 {
    margin: 0 0 8px;
    font-size: 28px;
    line-height: 1.2;
}

@media (max-width: 680px) {
    .test-main {
        width: calc(100% - 24px);
        margin-top: 14px;
        border-radius: 14px;
        padding: 16px;
    }

    .test-main h1 {
        font-size: 22px;
    }
}
</style>
<div class="out">
    <div class="test-layout">
        <main class="test-main">
            <h1>Тестова сторінка</h1>
        </main>
    </div>
</div>
HTML;

echo Page_Down();
