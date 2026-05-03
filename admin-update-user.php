<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/roles.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/function.php';

session_start();

// Проверка прав доступа
if (!isset($_SESSION['status']) || !hasRole($_SESSION['status'], ROLE_CREATOR)) {
    echo json_encode(['success' => false, 'message' => 'Доступ заборонено']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Невірний ID користувача']);
    exit;
}

$dblink = DbConnect();

$fields = [];
$allowed_fields = ['fname', 'lname', 'tel', 'email', 'mesto', 'activ', 'rest', 'rate', 'cash'];

foreach ($allowed_fields as $field) {
    if (isset($_POST[$field])) {
        $value = mysqli_real_escape_string($dblink, $_POST[$field]);
        if ($field === 'activ' || $field === 'rest' || $field === 'rate' || $field === 'cash') {
            $fields[] = "`$field` = " . (is_numeric($value) ? $value : 0);
        } else {
            $fields[] = "`$field` = '$value'";
        }
    }
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'message' => 'Немає даних для оновлення']);
    exit;
}

$sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE idx = $user_id";

$result = mysqli_query($dblink, $sql);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Дані успішно оновлено']);
} else {
    echo json_encode(['success' => false, 'message' => 'Помилка оновлення даних: ' . mysqli_error($dblink)]);
}
