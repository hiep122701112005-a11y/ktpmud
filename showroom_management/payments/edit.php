<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
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
        (float)$value, 0, ',', '.'
    ) . ' đ';
}

// Tạo mã bảo vệ biểu mẫu
if (empty($_SESSION['payments_csrf'])) {
    $_SESSION['payments_csrf'] = bin2hex(random_bytes(32));
}

// Nhận mã giao dịch từ URL
$paymentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$paymentId) {
    exit('Mã giao dịch không hợp lệ.');
}

$error = '';
$notice = isset($_GET['updated'])
    ? 'Cập nhật thanh toán thành công!'
    : '';

// Lấy dữ liệu giao dịch hiện tại
$stmt = $pdo->prepare("
    SELECT
        p.*,
        so.order_code,
        so.total_amount,
        c.full_name AS customer_name
    FROM payments p
    INNER JOIN sales_orders so
        ON p.order_id = so.order_id
    LEFT JOIN customers c
        ON so.customer_id = c.customer_id
    WHERE p.payment_id = ?
    LIMIT 1
");

$stmt->execute([$paymentId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    exit('Không tìm thấy giao dịch thanh toán.');
}

// Dữ liệu mặc định cho biểu mẫu
$data = [
    'amount' => (string)$payment['amount'],
    'payment_method' => (string)$payment['payment_method'],
    'payment_status' => (string)$payment['payment_status'],
    'note' => (string)($payment['note'] ?? '')
];

$methods = [
    'cash' => 'Tiền mặt',
    'bank_transfer' => 'Chuyển khoản',
    'card' => 'Thẻ ngân hàng'
];

$statuses = [
    'pending' => 'Chờ xử lý',
    'completed' => 'Hoàn tất',
    'failed' => 'Thất bại'
];

// Xử lý khi nhấn Lưu thay đổi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($data as $key => $value) {
        $input = $_POST[$key] ?? '';

        $data[$key] = is_string($input)
            ? trim($input)
            : '';
    }

    $token = $_POST['csrf_token'] ?? '';

    // Kiểm tra dữ liệu trước khi lưu
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['payments_csrf'], $token)
    ) {
        $error = 'Biểu mẫu không hợp lệ. Hãy tải lại trang.';

    } elseif (
        !preg_match(
            '/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/',
            $data['amount']
        ) ||
        (float)$data['amount'] <= 0
    ) {
        $error = 'Số tiền phải lớn hơn 0 và đúng định dạng.';

    } elseif (!array_key_exists($data['payment_method'], $methods)) {
        $error = 'Phương thức thanh toán không hợp lệ.';

    } elseif (!array_key_exists($data['payment_status'], $statuses)) {
        $error = 'Trạng thái thanh toán không hợp lệ.';

    } else {

        try {
            $pdo->beginTransaction();

            // Khóa đơn hàng để cập nhật an toàn
            $stmt = $pdo->prepare("
                SELECT order_id, total_amount
                FROM sales_orders
                WHERE order_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$payment['order_id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception('Đơn hàng không còn tồn tại.');
            }

            // Khóa giao dịch hiện tại
            $stmt = $pdo->prepare("
                SELECT payment_id
                FROM payments
                WHERE payment_id = ?
                AND order_id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $paymentId,
                $payment['order_id']
            ]);

            if (!$stmt->fetch()) {
                throw new Exception('Giao dịch không còn tồn tại.');
            }

            // Tính tổng tiền đã thanh toán của
            // các giao dịch khác trong cùng đơn hàng
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM payments
                WHERE order_id = ?
                AND payment_status = 'completed'
                AND payment_id <> ?
            ");

            $stmt->execute([
                $payment['order_id'],
                $paymentId
            ]);

            $paidOther = (float)$stmt->fetchColumn();

            $remaining = (float)$order['total_amount']
                       - $paidOther;

            // Chỉ giao dịch hoàn tất mới tính vào tiền đã trả
            if (
                $data['payment_status'] === 'completed' &&
                (float)$data['amount'] > $remaining + 0.001
            ) {
                throw new Exception(
                    'Số tiền vượt quá số tiền còn phải trả của đơn hàng.'
                );
            }

            // Cập nhật giao dịch
            $sql = "
                UPDATE payments
                SET
                    amount = :amount,
                    payment_method = :method,
                    payment_status = :status,
                    note = :note
                WHERE payment_id = :id
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':amount' => $data['amount'],
                ':method' => $data['payment_method'],
                ':status' => $data['payment_status'],
                ':note' => $data['note'] !== ''
                    ? $data['note']
                    : null,
                ':id' => $paymentId
            ]);

            $pdo->commit();

            header(
                'Location: edit.php?id=' .
                $paymentId . '&updated=1'
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $error = 'Không thể cập nhật dữ liệu thanh toán.';
            } else {
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Sửa thanh toán | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            background: #f1f5f9;
            color: #172033;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 950px;
            margin: auto;
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 4px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 36px;
            margin: 0 0 12px;
        }

        .subtitle {
            color: #64748b;
            font-size: 17px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 35px;
        }

        .info {
            background: #eff6ff;
            padding: 22px;
            border-radius: 12px;
            line-height: 2;
            margin-bottom: 25px;
        }

        .info strong {
            color: #172033;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 10px;
        }

        input, select, textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: white;
            font-size: 16px;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 14px 22px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-light {
            background: white;
            color: #172033;
            border: 1px solid #cbd5e1;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .hint {
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .card {
                padding: 20px;
            }

            h1 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="brand">SHOWROOM MANAGEMENT</div>

    <h1>Chỉnh sửa thanh toán</h1>

    <p class="subtitle">
        Cập nhật thông tin giao dịch của showroom.
    </p>

    <?php if ($notice !== ''): ?>
        <div class="alert success">
            <?= h($notice) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert danger">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <!-- Thông tin giao dịch -->
        <div class="info">

            <strong>Mã thanh toán:</strong>
            #<?= (int)$paymentId ?><br>

            <strong>Mã đơn hàng:</strong>
            <?= h($payment['order_code']) ?><br>

            <strong>Khách hàng:</strong>
            <?= h($payment['customer_name']) ?><br>

            <strong>Tổng tiền đơn hàng:</strong>
            <?= money($payment['total_amount']) ?>

        </div>

        <!-- Biểu mẫu cập nhật -->
        <form
            method="POST"
            action="edit.php?id=<?= (int)$paymentId ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['payments_csrf']) ?>"
            >

            <div class="form-group">

                <label>Số tiền thanh toán (VNĐ) *</label>

                <input
                    type="number"
                    name="amount"
                    min="0.01"
                    max="9999999999999.99"
                    step="0.01"
                    value="<?= h($data['amount']) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Phương thức thanh toán *</label>

                <select name="payment_method" required>

                    <?php foreach ($methods as $key => $label): ?>

                        <option
                            value="<?= h($key) ?>"
                            <?= $data['payment_method'] === $key
                                ? 'selected'
                                : '' ?>
                        >
                            <?= h($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Trạng thái thanh toán *</label>

                <select name="payment_status" required>

                    <?php foreach ($statuses as $key => $label): ?>

                        <option
                            value="<?= h($key) ?>"
                            <?= $data['payment_status'] === $key
                                ? 'selected'
                                : '' ?>
                        >
                            <?= h($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <p class="hint">
                    Chỉ những giao dịch ở trạng thái
                    Hoàn tất mới được tính vào tổng
                    số tiền khách hàng đã thanh toán.
                </p>

            </div>

            <div class="form-group">

                <label>Ghi chú</label>

                <textarea
                    name="note"
                    placeholder="Nhập ghi chú..."
                ><?= h($data['note']) ?></textarea>

            </div>

            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Lưu thay đổi
                </button>

                <a
                    href="index.php"
                    class="btn btn-light"
                >
                    Quay lại danh sách
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>