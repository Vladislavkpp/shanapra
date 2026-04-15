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
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Невірний ID користувача']);
    exit;
}

$dblink = DbConnect();

global $rolesList;

$sql = "UPDATE users SET status = $status WHERE idx = $user_id";
$result = mysqli_query($dblink, $sql);

if ($result) {
    function getStatusLabelsHtml(int $status, array $rolesList): string {
        $labels = [];
        foreach ($rolesList as $bit => $label) {
            if (($status & $bit) === $bit) $labels[] = $label;
        }
        return $labels ? implode(', ', $labels) : 'Гість';
    }
    
    echo json_encode([
        'success' => true,
        'roles_html' => getStatusLabelsHtml($status, $rolesList)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Помилка оновлення ролей: ' . mysqli_error($dblink)
    ]);
}

