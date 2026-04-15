<?php
define('CEMETERY_MAP_ROUTE', '/cemetery/map');
define('CEMETERY_MAP_CSS', '/assets/css/cemetery-map.css');
define('CEMETERY_MAP_DATA_ROUTE', '/cemetery-map.php');
define('CEMETERY_MAP_SELECTED_ID', (int)($_GET['idx'] ?? ($_GET['cemetery_id'] ?? 0)));

require __DIR__ . '/test.php';
