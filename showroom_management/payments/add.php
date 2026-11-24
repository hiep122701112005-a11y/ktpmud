<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
// Hàm bảo mật dữ liệu hiển thị
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}

// CSRF Token
if (empty($_SESSION['payments_csrf'])) {
    $_SESSION['payments_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$pageError = '';
$old = [
    'order_id' => '',
    'amount' => '',
    'payment_method' => 'cash',
    'payment_status' => 'pending',
    'note' => ''
];

// Lấy danh sách đơn hàng và số tiền đã thanh toán
$orders = [];
$orderMap = [];

try {
    $sql = "
        SELECT
            so.order_id,
            so.order_code,
            so.total_amount,
            c.full_name,
            c.phone,
            COALESCE((
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.order_id = so.order_id
                AND p.payment_status = 'completed'
            ), 0) AS paid_amount
        FROM sales_orders so
        LEFT JOIN customers c
            ON c.customer_id = so.customer_id
        ORDER BY so.order_id DESC
    ";

    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        $orderMap[$order['order_id']] = $order;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $pageError = 'Không thể tải danh sách đơn hàng.';
}

// Xử lý tạo giao dịch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['order_id'] = $_POST['order_id'] ?? '';
    $old['amount'] = trim($_POST['amount'] ?? '');
    $old['payment_method'] = $_POST['payment_method'] ?? 'cash';
    $old['payment_status'] = $_POST['payment_status'] ?? 'pending';
    $old['note'] = trim($_POST['note'] ?? '');

    // Kiểm tra CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['payments_csrf'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // Kiểm tra đơn hàng
    $orderId = (int)$old['order_id'];

    if ($orderId <= 0 || !isset($orderMap[$orderId])) {
        $errors[] = 'Vui lòng chọn đơn hàng hợp lệ.';
    }

    // Kiểm tra số tiền
    if ($old['amount'] === '' || !is_numeric($old['amount']) || (float)$old['amount'] <= 0) {
        $errors[] = 'Số tiền thanh toán phải lớn hơn 0.';
    }

    // Kiểm tra phương thức
    $methods = ['cash', 'bank_transfer', 'card'];

    if (!in_array($old['payment_method'], $methods, true)) {
        $errors[] = 'Phương thức thanh toán không hợp lệ.';
    }

    // Kiểm tra trạng thái
    $statuses = ['pending', 'completed', 'failed'];

    if (!in_array($old['payment_status'], $statuses, true)) {
        $errors[] = 'Trạng thái thanh toán không hợp lệ.';
    }

    // Không cho thanh toán vượt quá số tiền còn thiếu
    if (
        isset($orderMap[$orderId]) &&
        is_numeric($old['amount']) &&
        (float)$old['amount'] > 0 &&
        $old['payment_status'] === 'completed'
    ) {
        $order = $orderMap[$orderId];

        $remaining = (float)$order['total_amount']
                   - (float)$order['paid_amount'];

        if ((float)$old['amount'] > $remaining + 0.001) {
            $errors[] = 'Số tiền thanh toán vượt quá số tiền còn phải trả.';
        }
    }

    // Ghi dữ liệu vào MySQL
    if (empty($errors)) {
        try {
            $sql = "
                INSERT INTO payments (
                    order_id,
                    amount,
                    payment_method,
                    payment_status,
                    note
                ) VALUES (
                    :order_id,
                    :amount,
                    :payment_method,
                    :payment_status,
                    :note
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':order_id' => $orderId,
                ':amount' => $old['amount'],
                ':payment_method' => $old['payment_method'],
                ':payment_status' => $old['payment_status'],
                ':note' => $old['note'] !== '' ? $old['note'] : null
            ]);

            header('Location: index.php?added=1');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Không thể lưu giao dịch. Vui lòng kiểm tra lại dữ liệu.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo thanh toán | Showroom Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 45px;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            color: #172033;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 5px;
            margin-bottom: 25px;
        }

        h1 {
            font-size: 38px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #64748b;
            font-size: 18px;
            margin-bottom: 35px;
        }

        .card {
            background: white;
            padding: 35px;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 12px;
            font-size: 17px;
        }

        input, select, textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
            background: white;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #2563eb;
        }

        textarea {
            resize: vertical;
            min-height: 110px;
        }

        .info {
            background: #eff6ff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            color: #1e40af;
            line-height: 2;
        }

        .info strong {
            color: #172033;
        }

        .amount {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .success-info {
            color: #64748b;
            font-size: 14px;
            margin-top: 8px;
        }

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 15px 25px;
            border-radius: 12px;
            border: none;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: white;
            color: #172033;
            border: 1px solid #cbd5e1;
        }

        .btn:hover {
            opacity: 0.88;
        }

        @media(max-width: 600px) {
            body {
                padding: 20px;
            }

            .card {
                padding: 20px;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="brand">SHOWROOM MANAGEMENT</div>

    <h1>Tạo giao dịch thanh toán</h1>

    <p class="subtitle">
        Nhập thông tin để ghi nhận giao dịch thanh toán.
    </p>

    <?php if ($pageError): ?>
        <div class="error"><?= h($pageError) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Vui lòng kiểm tra:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['payments_csrf']) ?>"
            >

            <div class="form-group">

                <label>Đơn hàng *</label>

                <select name="order_id" id="orderSelect" required>

                    <option value="">-- Chọn đơn hàng --</option>

                    <?php foreach ($orders as $order): ?>

                        <option
                            value="<?= h($order['order_id']) ?>"
                            data-total="<?= h($order['total_amount']) ?>"
                            data-paid="<?= h($order['paid_amount']) ?>"
                            data-remaining="<?= max(0, (float)$order['total_amount'] - (float)$order['paid_amount']) ?>"
                            <?= (string)$old['order_id'] === (string)$order['order_id'] ? 'selected' : '' ?>
                        >
                            <?= h($order['order_code']) ?>
                            - <?= h($order['full_name'] ?? 'Khách hàng') ?>
                            - <?= money($order['total_amount']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="info" id="orderInfo">
                Vui lòng chọn đơn hàng để xem thông tin.
            </div>

            <div class="form-group">

                <label>Số tiền thanh toán (VNĐ) *</label>

                <input
                    type="number"
                    name="amount"
                    id="amount"
                    min="0.01"
                    step="0.01"
                    placeholder="Nhập số tiền thanh toán"
                    value="<?= h($old['amount']) ?>"
                    required
                >

                <div class="success-info">
                    Không được thanh toán vượt quá số tiền còn phải trả khi giao dịch ở trạng thái Hoàn tất.
                </div>

            </div>

            <div class="form-group">

                <label>Phương thức thanh toán *</label>

                <select name="payment_method" required>

                    <option value="cash"
                        <?= $old['payment_method'] === 'cash' ? 'selected' : '' ?>>
                        Tiền mặt
                    </option>

                    <option value="bank_transfer"
                        <?= $old['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>
                        Chuyển khoản
                    </option>

                    <option value="card"
                        <?= $old['payment_method'] === 'card' ? 'selected' : '' ?>>
                        Thẻ ngân hàng
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label>Trạng thái thanh toán *</label>

                <select name="payment_status" required>

                    <option value="pending"
                        <?= $old['payment_status'] === 'pending' ? 'selected' : '' ?>>
                        Chờ xử lý
                    </option>

                    <option value="completed"
                        <?= $old['payment_status'] === 'completed' ? 'selected' : '' ?>>
                        Hoàn tất
                    </option>

                    <option value="failed"
                        <?= $old['payment_status'] === 'failed' ? 'selected' : '' ?>>
                        Thất bại
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label>Ghi chú</label>

                <textarea
                    name="note"
                    placeholder="Nhập nội dung ghi chú nếu có..."
                ><?= h($old['note']) ?></textarea>

            </div>

            <div class="buttons">

                <button type="submit" class="btn btn-primary">
                    + Lưu thanh toán
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Hủy bỏ
                </a>

            </div>

        </form>

    </div>

</div>

<script>
const orderSelect = document.getElementById('orderSelect');
const orderInfo = document.getElementById('orderInfo');
const amountInput = document.getElementById('amount');

function formatMoney(value) {
    return Number(value).toLocaleString('vi-VN') + ' đ';
}

function updateOrderInfo(fillAmount = false) {

    const option = orderSelect.selectedOptions[0];

    if (!option || !option.value) {
        orderInfo.innerHTML =
            'Vui lòng chọn đơn hàng để xem thông tin.';
        return;
    }

    const total = Number(option.dataset.total);
    const paid = Number(option.dataset.paid);
    const remaining = Number(option.dataset.remaining);

    orderInfo.innerHTML = `
        <strong>Mã đơn hàng:</strong> ${option.text.split(' - ')[0]}<br>
        <strong>Tổng tiền:</strong> ${formatMoney(total)}<br>
        <strong>Đã thanh toán:</strong> ${formatMoney(paid)}<br>
        <strong>Còn phải trả:</strong>
        <span class="amount">${formatMoney(remaining)}</span>
    `;

    if (fillAmount) {
        amountInput.value = remaining > 0
            ? remaining.toFixed(2)
            : '';
    }
}

orderSelect.addEventListener('change', function() {
    updateOrderInfo(true);
});

updateOrderInfo(false);

if (!amountInput.value && orderSelect.value) {
    updateOrderInfo(true);
}
</script>

</body>
</html>