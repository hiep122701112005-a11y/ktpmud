<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
// Kết nối MySQL
require_once __DIR__ . '/../config/database.php';

// Hàm hiển thị dữ liệu an toàn
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Hàm định dạng tiền Việt Nam
function money($value) {
    return number_format(
        (float)$value,
        0,
        ',',
        '.'
    ) . ' đ';
}

// Tạo mã bảo vệ khi xóa
if (empty($_SESSION['payments_delete_csrf'])) {
    $_SESSION['payments_delete_csrf'] =
        bin2hex(random_bytes(32));
}

// Lấy mã giao dịch thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = filter_input(
        INPUT_POST,
        'payment_id',
        FILTER_VALIDATE_INT
    );
} else {
    $paymentId = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );
}

// Kiểm tra mã thanh toán
if (!$paymentId || $paymentId <= 0) {
    exit('Mã thanh toán không hợp lệ.');
}

$error = '';

// Lấy thông tin giao dịch cần xóa
try {
    $sql = "
        SELECT
            p.payment_id,
            p.order_id,
            p.amount,
            p.payment_method,
            p.payment_status,
            p.payment_date,
            p.note,
            so.order_code,
            c.full_name AS customer_name
        FROM payments p
        INNER JOIN sales_orders so
            ON p.order_id = so.order_id
        LEFT JOIN customers c
            ON so.customer_id = c.customer_id
        WHERE p.payment_id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paymentId]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        exit('Không tìm thấy giao dịch thanh toán.');
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Không thể tải thông tin thanh toán.');
}

// Xử lý khi người dùng xác nhận xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    // Kiểm tra mã bảo vệ
    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION['payments_delete_csrf'],
            $token
        )
    ) {
        $error = 'Yêu cầu không hợp lệ. Hãy tải lại trang.';

    } else {

        try {

            // Bắt đầu giao dịch MySQL
            $pdo->beginTransaction();

            // Khóa đơn hàng để tránh cập nhật đồng thời
            $stmt = $pdo->prepare(
                "SELECT order_id
                 FROM sales_orders
                 WHERE order_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([
                $payment['order_id']
            ]);

            if (!$stmt->fetch()) {
                throw new Exception('Đơn hàng không tồn tại.');
            }

            // Kiểm tra giao dịch trước khi xóa
            $stmt = $pdo->prepare(
                "SELECT payment_id
                 FROM payments
                 WHERE payment_id = ?
                   AND order_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([
                $paymentId,
                $payment['order_id']
            ]);

            if (!$stmt->fetch()) {
                throw new Exception(
                    'Giao dịch thanh toán không còn tồn tại.'
                );
            }

            // Xóa đúng giao dịch được chọn
            $stmt = $pdo->prepare(
                "DELETE FROM payments
                 WHERE payment_id = ?
                 LIMIT 1"
            );

            $stmt->execute([$paymentId]);

            // Kiểm tra kết quả xóa
            if ($stmt->rowCount() !== 1) {
                throw new Exception(
                    'Không thể xóa giao dịch thanh toán.'
                );
            }

            // Hoàn tất giao dịch MySQL
            $pdo->commit();

            // Chuyển về danh sách thanh toán
            header('Location: index.php?deleted=1');
            exit;

        } catch (Throwable $e) {

            // Hủy các thay đổi nếu gặp lỗi
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $error = 'Không thể xóa giao dịch thanh toán.';
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

// Danh sách phương thức thanh toán
$methodLabels = [
    'cash' => 'Tiền mặt',
    'bank_transfer' => 'Chuyển khoản',
    'card' => 'Thẻ ngân hàng'
];

// Danh sách trạng thái
$statusLabels = [
    'pending' => 'Chờ xử lý',
    'completed' => 'Hoàn tất',
    'failed' => 'Thất bại'
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Xóa thanh toán | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 50px 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #172033;
        }

        .container {
            max-width: 760px;
            margin: auto;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 40px;
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 4px;
            margin-bottom: 28px;
        }

        h1 {
            font-size: 34px;
            margin: 0 0 15px;
        }

        .subtitle {
            color: #64748b;
            font-size: 17px;
            line-height: 1.6;
        }

        .info {
            background: #f8fafc;
            padding: 24px;
            border-radius: 14px;
            margin-top: 28px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 11px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
        }

        .info-value {
            font-weight: bold;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .amount {
            color: #2563eb;
            font-size: 20px;
        }

        .warning {
            background: #fee2e2;
            color: #991b1b;
            border-radius: 14px;
            padding: 23px;
            margin-top: 28px;
            line-height: 1.7;
        }

        .warning strong {
            display: block;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 16px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 15px 23px;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
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

        .btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }

            h1 {
                font-size: 27px;
            }

            .info-row {
                flex-direction: column;
                gap: 5px;
            }

            .info-value {
                text-align: left;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <div class="brand">
            SHOWROOM MANAGEMENT
        </div>

        <h1>Xác nhận xóa thanh toán</h1>

        <p class="subtitle">
            Bạn đang chuẩn bị xóa giao dịch thanh toán sau:
        </p>

        <!-- Thông tin thanh toán -->
        <div class="info">

            <div class="info-row">
                <span class="info-label">
                    Mã thanh toán
                </span>

                <span class="info-value">
                    #<?= (int)$payment['payment_id'] ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">
                    Mã đơn hàng
                </span>

                <span class="info-value">
                    <?= h($payment['order_code']) ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">
                    Khách hàng
                </span>

                <span class="info-value">
                    <?= h($payment['customer_name']) ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">
                    Số tiền
                </span>

                <span class="info-value amount">
                    <?= money($payment['amount']) ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">
                    Phương thức
                </span>

                <span class="info-value">
                    <?= h(
                        $methodLabels[$payment['payment_method']]
                        ?? $payment['payment_method']
                    ) ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">
                    Trạng thái
                </span>

                <span class="info-value">
                    <?= h(
                        $statusLabels[$payment['payment_status']]
                        ?? $payment['payment_status']
                    ) ?>
                </span>
            </div>

        </div>

        <!-- Cảnh báo -->
        <div class="warning">

            <strong>Cảnh báo!</strong>

            Giao dịch thanh toán này sẽ bị xóa khỏi
            hệ thống và không thể khôi phục bằng
            chức năng thông thường.

            Nếu giao dịch đã hoàn tất, số tiền
            tương ứng sẽ không còn được tính vào
            tổng số tiền đã thanh toán của đơn hàng.

            Đơn hàng và số lượng ô tô trong kho
            vẫn được giữ nguyên.

        </div>

        <!-- Thông báo lỗi -->
        <?php if ($error !== ''): ?>

            <div class="error">
                <?= h($error) ?>
            </div>

        <?php endif; ?>

        <!-- Biểu mẫu xác nhận -->
        <form method="POST">

            <input
                type="hidden"
                name="payment_id"
                value="<?= (int)$paymentId ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['payments_delete_csrf']) ?>"
            >

            <div class="actions">

                <button
                    type="submit"
                    class="btn btn-danger"
                >
                    Xác nhận xóa thanh toán
                </button>

                <a
                    href="index.php"
                    class="btn btn-light"
                >
                    Hủy bỏ
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>