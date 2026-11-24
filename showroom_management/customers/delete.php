<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
// Kiểm tra mã khách hàng
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    exit("Mã khách hàng không hợp lệ.");
}

try {
    // Kiểm tra khách hàng có tồn tại không
    $check = $pdo->prepare(
        "SELECT customer_id FROM customers WHERE customer_id = :id"
    );

    $check->execute([':id' => $id]);

    if (!$check->fetch()) {
        exit("Không tìm thấy khách hàng cần xóa.");
    }

    // Xóa đúng khách hàng theo mã
    $stmt = $pdo->prepare(
        "DELETE FROM customers WHERE customer_id = :id"
    );

    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() !== 1) {
        exit("Không thể xóa khách hàng.");
    }

    header("Location: index.php?deleted=1");
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(409);

    echo "<h2>Không thể xóa khách hàng!</h2>";
    echo "<p>Khách hàng có thể đang liên kết với đơn hàng.</p>";
    echo '<a href="index.php">Quay lại danh sách khách hàng</a>';
    exit;
}