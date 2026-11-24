<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

// Kết nối cơ sở dữ liệu MySQL
require_once __DIR__ . '/../config/database.php';

// Hàm chuyển về trang danh sách ô tô
function redirectToList($status = '') {
    $url = 'index.php';

    if ($status !== '') {
        $url .= '?' . $status;
    }

    header('Location: ' . $url);
    exit;
}

// Chỉ cho phép xóa bằng phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToList();
}

// Kiểm tra mã bảo vệ CSRF
$token = $_POST['csrf_token'] ?? null;

if (
    !isset($_SESSION['cars_csrf']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['cars_csrf'], $token)
) {
    redirectToList('error=delete');
}

// Nhận mã ô tô cần xóa
$car_id = filter_input(
    INPUT_POST,
    'car_id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

// Kiểm tra mã ô tô
if ($car_id === false || $car_id === null) {
    redirectToList('error=notfound');
}

try {

    // Xóa đúng một chiếc ô tô theo mã car_id
    $sql = "DELETE FROM cars
            WHERE car_id = :car_id
            LIMIT 1";

    // Chuẩn bị câu lệnh SQL
    $stmt = $pdo->prepare($sql);

    // Truyền mã xe vào câu lệnh SQL
    $stmt->bindValue(
        ':car_id',
        $car_id,
        PDO::PARAM_INT
    );

    // Thực hiện xóa
    $stmt->execute();

    // Kiểm tra kết quả
    if ($stmt->rowCount() === 1) {

        // Xóa thành công
        redirectToList('deleted=1');

    } else {

        // Không tìm thấy xe
        redirectToList('error=notfound');

    }

} catch (PDOException $e) {

    // Ghi lỗi vào nhật ký máy chủ
    error_log($e->getMessage());

    // Mã lỗi MySQL
    $mysqlError = (int)($e->errorInfo[1] ?? 0);

    if ($mysqlError === 1451) {

        // Không được xóa xe đang liên kết với đơn hàng
        redirectToList('error=linked');

    } else {

        // Lỗi xóa khác
        redirectToList('error=delete');

    }
}