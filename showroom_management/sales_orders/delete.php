<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id <= 0) {
    exit('Mã đơn hàng không hợp lệ!');
}

if (empty($_SESSION['delete_order_token'])) {
    $_SESSION['delete_order_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['delete_order_token'], $token)) {
        $error = 'Yêu cầu không hợp lệ. Hãy tải lại trang.';
    } else {

        try {
            $pdo->beginTransaction();

            // Khóa đơn hàng
            $stmt = $pdo->prepare(
                "SELECT order_id
                 FROM sales_orders
                 WHERE order_id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$id]);

            if (!$stmt->fetch()) {
                throw new Exception('Đơn hàng không tồn tại.');
            }

            // Lấy các xe thuộc đơn hàng
            $stmt = $pdo->prepare(
                "SELECT car_id, quantity
                 FROM order_details
                 WHERE order_id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$id]);

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hoàn trả tồn kho
            $restore = $pdo->prepare(
                "UPDATE cars
                 SET stock_quantity = stock_quantity + ?
                 WHERE car_id = ?"
            );

            foreach ($items as $item) {
                $restore->execute([
                    (int)$item['quantity'],
                    (int)$item['car_id']
                ]);

                if ($restore->rowCount() !== 1) {
                    throw new Exception(
                        'Không thể hoàn trả tồn kho ô tô.'
                    );
                }
            }

            // Xóa chi tiết đơn hàng
            $stmt = $pdo->prepare(
                "DELETE FROM order_details WHERE order_id = ?"
            );
            $stmt->execute([$id]);

            // Xóa đơn hàng
            $stmt = $pdo->prepare(
                "DELETE FROM sales_orders WHERE order_id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception('Không thể xóa đơn hàng.');
            }

            $pdo->commit();

            unset($_SESSION['delete_order_token']);

            header('Location: index.php?deleted=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $error = $e instanceof PDOException
                ? 'Không thể xóa đơn hàng. Có thể đơn hàng đang liên kết với dữ liệu thanh toán.'
                : $e->getMessage();
        }
    }
}

// Lấy thông tin đơn hàng để xác nhận
$stmt = $pdo->prepare(
    "SELECT so.*, c.full_name AS customer_name
     FROM sales_orders so
     LEFT JOIN customers c
        ON so.customer_id = c.customer_id
     WHERE so.order_id = ?"
);

$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    exit('Không tìm thấy đơn hàng.');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Xóa đơn hàng | Showroom</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #172033;
    margin: 0;
    padding: 50px 20px;
}

.container {
    max-width: 650px;
    margin: auto;
}

.card {
    background: white;
    border-radius: 20px;
    padding: 35px;
    border: 1px solid #e2e8f0;
}

.brand {
    color: #2563eb;
    font-weight: bold;
    letter-spacing: 3px;
    margin-bottom: 25px;
}

h1 {
    margin-bottom: 15px;
}

.warning {
    background: #fee2e2;
    color: #991b1b;
    padding: 18px;
    border-radius: 12px;
    margin: 25px 0;
    line-height: 1.6;
}

.info {
    background: #f8fafc;
    padding: 18px;
    border-radius: 12px;
    line-height: 2;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn {
    padding: 14px 20px;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    font-size: 15px;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-light {
    background: white;
    color: #172033;
    border: 1px solid #cbd5e1;
}

@media(max-width: 500px) {
    .actions {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<div class="container">

<div class="card">

    <div class="brand">SHOWROOM MANAGEMENT</div>

    <h1>Xác nhận xóa đơn hàng</h1>

    <p>Bạn đang chuẩn bị xóa đơn hàng sau:</p>

    <div class="info">

        <strong>Mã đơn:</strong>
        <?= h($order['order_code']) ?><br>

        <strong>Khách hàng:</strong>
        <?= h($order['customer_name']) ?><br>

        <strong>Tổng tiền:</strong>
        <?= number_format((float)$order['total_amount'], 0, ',', '.') ?> đ

    </div>

    <div class="warning">
        <strong>Cảnh báo!</strong><br>
        Đơn hàng và toàn bộ chi tiết liên quan sẽ bị xóa.
        Số lượng ô tô trong đơn sẽ được hoàn trả về kho.
        Thao tác này không thể hoàn tác.
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input type="hidden" name="id"
               value="<?= (int)$id ?>">

        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION['delete_order_token']) ?>">

        <div class="actions">

            <button type="submit" class="btn btn-danger">
                Xác nhận xóa đơn hàng
            </button>

            <a href="index.php" class="btn btn-light">
                Hủy bỏ
            </a>

        </div>

    </form>

</div>

</div>

</body>
</html>