<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/roles.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

session_start();

// Проверка прав доступа
if (!isset($_SESSION['status']) || !hasRole($_SESSION['status'], ROLE_CREATOR)) {
    echo json_encode(['success' => false, 'message' => 'Доступ заборонено']);
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Невірний ID користувача']);
    exit;
}

$dblink = DbConnect();
$res = mysqli_query($dblink, "SELECT idx, fname, lname, tel, email, dttmreg, activ, status, mesto, avatar, rest, rate, cash, token FROM users WHERE idx = $user_id");

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Помилка запиту до бази даних']);
    exit;
}

$user = mysqli_fetch_assoc($res);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Користувача не знайдено']);
    exit;
}

echo json_encode(['success' => true, 'user' => $user]);
