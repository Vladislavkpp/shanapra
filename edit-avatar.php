<?php
require_once "function.php";

$dblink = DbConnect();

if (!isset($_SESSION['uzver'])) {
    die("Необхідна авторизація");
}

$userId = intval($_SESSION['uzver']);

/**
 * Удаляет папку пользователя со всеми файлами
 */
function removeUzverDir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir . "/" . $object;
                if (is_dir($path)) {
                    removeUzverDir($path);
                } else {
                    @unlink($path);
                }
            }
        }
        @rmdir($dir);
    }
}

/**
 * Сжимает изображение до оптимального размера
 */
function compressImage($sourcePath, $targetPath, $quality = 75, $maxSizeKB = 300) {
    $info = getimagesize($sourcePath);
    $mime = $info['mime'];

    switch($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    $success = false;

    if ($mime == 'image/jpeg') {
        $success = imagejpeg($image, $targetPath, $quality);
    } elseif ($mime == 'image/png') {
        $pngQuality = 9 - round(($quality * 9) / 100);
        $success = imagepng($image, $targetPath, $pngQuality);
    }

    if ($success && filesize($targetPath) > ($maxSizeKB * 1024)) {
        $currentQuality = $quality;

        while ($currentQuality > 30 && filesize($targetPath) > ($maxSizeKB * 1024)) {
            $currentQuality -= 10;

            if ($mime == 'image/jpeg') {
                imagejpeg($image, $targetPath, $currentQuality);
            } elseif ($mime == 'image/png') {
                $pngQuality = 9 - round(($currentQuality * 9) / 100);
                imagepng($image, $targetPath, $pngQuality);
            }
        }

        if (filesize($targetPath) > ($maxSizeKB * 1024)) {
            $currentWidth = imagesx($image);
            $currentHeight = imagesy($image);


            $newWidth = $currentWidth * 0.5;
            $newHeight = $currentHeight * 0.5;

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);


            if ($mime == 'image/png') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);

            // Сохраняем с нормальным качеством после ресайза
            if ($mime == 'image/jpeg') {
                imagejpeg($resizedImage, $targetPath, 75);
            } elseif ($mime == 'image/png') {
                imagepng($resizedImage, $targetPath, 6);
            }

            imagedestroy($resizedImage);
        }
    }

    imagedestroy($image);
    return true;
}

/**
 * Обновление / удаление аватарки
 */
function updateAvatar($dblink, $userId, $file = null, $delete = false) {

    $userDir = __DIR__ . "/avatars/" . $userId;

    if (!session_id()) session_start();
    $_SESSION['message'] = '';
    $_SESSION['messageType'] = '';

    $res = mysqli_query($dblink, "SELECT avatar FROM users WHERE idx = {$userId}");
    $row = mysqli_fetch_assoc($res);
    $oldAvatar = $row ? $row['avatar'] : null;

    //Удаление аватарки
    if ($delete) {
        removeUzverDir($userDir);
        mysqli_query($dblink, "UPDATE users SET avatar='' WHERE idx={$userId}");
        $_SESSION['message'] = "Аватар успешно удалён";
        $_SESSION['messageType'] = "success";
        header("Location: /profile.php?md=2");
        exit;
    }

    // Загрузка новой аватарки
    if ($file && $file['error'] === UPLOAD_ERR_OK) {


        $maxSize = 300 * 1024; // 300 КБ
        $needsCompression = $file['size'] > $maxSize;

        list($width, $height) = getimagesize($file['tmp_name']);
        $maxWidth = 1080;
        $maxHeight = 1080;
        if ($width > $maxWidth || $height > $maxHeight) {
            $_SESSION['message'] = "Занадто велике розширення зображення. Максимум {$maxWidth}x{$maxHeight} пікселів";
            $_SESSION['messageType'] = "error";
            header("Location: /profile.php?md=2");
            exit;
        }

        if (is_dir($userDir)) {
            removeUzverDir($userDir);
        }
        mkdir($userDir, 0777, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];
        if (!in_array($ext, $allowed)) {
            $_SESSION['message'] = "Недопустимый формат файла";
            $_SESSION['messageType'] = "error";
            header("Location: /profile.php?md=2");
            exit;
        }

        $newName = "avatar_" . time() . "." . $ext;
        $targetPath = $userDir . "/" . $newName;

        if ($needsCompression) {
            // Используем сжатие
            if (compressImage($file['tmp_name'], $targetPath, 80, 300)) {
                $avatarPathInDb = "avatars/{$userId}/" . $newName;
                mysqli_query(
                    $dblink,
                    "UPDATE users SET avatar='" . mysqli_real_escape_string($dblink, $avatarPathInDb) . "' WHERE idx={$userId}"
                );
                $_SESSION['message'] = "Аватар успешно обновлён (файл был сжат для оптимизации размера)";
                $_SESSION['messageType'] = "success";
                header("Location: /profile.php?md=2");
                exit;
            } else {
                $_SESSION['message'] = "Ошибка при сжатии изображения";
                $_SESSION['messageType'] = "error";
                header("Location: /profile.php?md=2");
                exit;
            }
        } else {
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $avatarPathInDb = "avatars/{$userId}/" . $newName;
                mysqli_query(
                    $dblink,
                    "UPDATE users SET avatar='" . mysqli_real_escape_string($dblink, $avatarPathInDb) . "' WHERE idx={$userId}"
                );
                $_SESSION['message'] = "Аватар успешно обновлён";
                $_SESSION['messageType'] = "success";
                header("Location: /profile.php?md=2");
                exit;
            } else {
                $_SESSION['message'] = "Ошибка загрузки файла";
                $_SESSION['messageType'] = "error";
                header("Location: /profile.php?md=2");
                exit;
            }
        }
    }

    // Если файл не был загружен
    header("Location: /profile.php?md=2");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_avatar'])) {
        updateAvatar($dblink, $userId, null, true);
    } elseif (isset($_FILES['avatar'])) {
        updateAvatar($dblink, $userId, $_FILES['avatar']);
    } else {
        header("Location: /profile.php?md=2");
        exit;
    }
}