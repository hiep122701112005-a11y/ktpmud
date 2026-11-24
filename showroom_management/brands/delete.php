<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
require_once __DIR__ . "/../config/database.php";

// Chỉ cho phép xóa khi gửi biểu mẫu
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Yêu cầu không hợp lệ.");
}

// Kiểm tra mã bảo vệ
$token = $_POST["csrf_token"] ?? "";

if (
    !is_string($token) ||
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    exit("Yêu cầu không hợp lệ.");
}

// Lấy mã hãng xe cần xóa
$id = filter_input(
    INPUT_POST,
    "brand_id",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    exit("Mã hãng xe không hợp lệ.");
}

// Xóa hãng xe khỏi MySQL
try {
    $sql = "DELETE FROM brands
            WHERE brand_id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(":id", $id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        exit("Không tìm thấy hãng xe để xóa.");
    }

    header("Location: index.php?deleted=1");
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("Không thể xóa hãng xe này.");
}