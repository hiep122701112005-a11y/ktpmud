<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
// Hàm hiển thị dữ liệu an toàn
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Định dạng tiền Việt Nam
function money($value) {
    return number_format(
        (float)$value, 0, ',', '.'
    ) . ' đ';
}

// Lấy từ khóa tìm kiếm
$q = trim(
    is_string($_GET['q'] ?? null) ? $_GET['q'] : ''
);

$error = '';
$payments = [];

// Truy vấn danh sách thanh toán
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
            c.full_name AS customer_name,
            c.phone AS customer_phone
        FROM payments p
        INNER JOIN sales_orders so
            ON p.order_id = so.order_id
        LEFT JOIN customers c
            ON so.customer_id = c.customer_id
        WHERE 1=1
    ";

    $params = [];

    // Tìm kiếm thanh toán
    if ($q !== '') {

        $sql .= "
            AND (
                so.order_code LIKE ?
                OR c.full_name LIKE ?
                OR c.phone LIKE ?
                OR CAST(p.payment_id AS CHAR) LIKE ?
            )
        ";

        $keyword = "%{$q}%";

        $params = [
            $keyword,
            $keyword,
            $keyword,
            $keyword
        ];
    }

    $sql .= "
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = 'Không thể tải danh sách thanh toán.';
}

// Phương thức thanh toán
$methodLabels = [
    'cash' => 'Tiền mặt',
    'bank_transfer' => 'Chuyển khoản',
    'card' => 'Thẻ ngân hàng'
];

// Trạng thái thanh toán
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

    <title>Quản lý thanh toán | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #172033;
        }

        .container {
            max-width: 1500px;
            margin: auto;
            padding: 45px 35px;
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 4px;
            margin-bottom: 25px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 55px;
        }

        h1 {
            font-size: 42px;
            margin: 0 0 15px;
        }

        .subtitle {
            color: #64748b;
            font-size: 20px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 14px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            border: 1px solid #dbe3ef;
            background: white;
            color: #172033;
            cursor: pointer;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .btn-edit {
            background: white;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }

        .btn-small {
            padding: 9px 13px;
            font-size: 13px;
        }

        .btn:hover {
            opacity: 0.88;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            overflow: hidden;
        }

        .card-header {
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .card-header h2 {
            margin: 0 0 12px;
            font-size: 27px;
        }

        .muted {
            color: #64748b;
        }

        .search {
            display: flex;
            gap: 12px;
        }

        .search input {
            width: 380px;
            max-width: 100%;
            padding: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 16px;
        }

        .search button {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px 25px;
            font-weight: bold;
            cursor: pointer;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            padding: 20px 15px;
            border-top: 1px solid #e2e8f0;
        }

        td {
            padding: 22px 15px;
            border-top: 1px solid #e2e8f0;
        }

        .amount {
            color: #2563eb;
            font-weight: bold;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 9px 13px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .completed {
            background: #dcfce7;
            color: #166534;
        }

        .failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .row-actions {
            display: flex;
            gap: 8px;
        }

        .notice {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .notice-success {
            background: #dcfce7;
            color: #166534;
        }

        .notice-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            text-align: center;
            padding: 55px;
            color: #64748b;
        }

        .footer {
            margin-top: 25px;
            color: #64748b;
        }

        @media (max-width: 700px) {

            .container {
                padding: 25px 15px;
            }

            h1 {
                font-size: 30px;
            }

            .search {
                width: 100%;
            }

            .search input {
                width: 100%;
            }

            .card-header {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="brand">
        SHOWROOM MANAGEMENT
    </div>

    <!-- TIÊU ĐỀ -->
    <div class="header">

        <div>

            <h1>Quản lý thanh toán</h1>

            <div class="subtitle">
                Theo dõi các giao dịch và trạng thái thanh toán.
            </div>

        </div>

        <!-- CÁC NÚT CHỨC NĂNG -->
        <div class="header-actions">

            <a href="../index.php" class="btn">
                Trang chủ
            </a>

            <a href="add.php" class="btn btn-primary">
                + Tạo thanh toán
            </a>

        </div>

    </div>


    <!-- THÔNG BÁO THÊM THÀNH CÔNG -->
    <?php if (isset($_GET['added'])): ?>

        <div class="notice notice-success">
            Thêm thanh toán thành công!
        </div>

    <?php endif; ?>


    <!-- THÔNG BÁO XÓA THÀNH CÔNG -->
    <?php if (isset($_GET['deleted'])): ?>

        <div class="notice notice-success">
            Xóa thanh toán thành công!
        </div>

    <?php endif; ?>


    <!-- THÔNG BÁO LỖI -->
    <?php if ($error !== ''): ?>

        <div class="notice notice-error">
            <?= h($error) ?>
        </div>

    <?php endif; ?>


    <!-- DANH SÁCH THANH TOÁN -->
    <div class="card">

        <div class="card-header">

            <div>

                <h2>Danh sách thanh toán</h2>

                <div class="muted">
                    <?= count($payments) ?> kết quả
                </div>

            </div>

            <!-- TÌM KIẾM -->
            <form method="GET" class="search">

                <input
                    type="text"
                    name="q"
                    placeholder="Tìm mã đơn, khách hàng, số điện thoại..."
                    value="<?= h($q) ?>"
                >

                <button type="submit">
                    Tìm kiếm
                </button>

            </form>

        </div>


        <!-- BẢNG DỮ LIỆU -->
        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Mã thanh toán</th>
                        <th>Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Số tiền</th>
                        <th>Phương thức</th>
                        <th>Trạng thái</th>
                        <th>Ngày thanh toán</th>
                        <th>Ghi chú</th>
                        <th>Thao tác</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($payments) > 0): ?>

                    <?php foreach ($payments as $payment): ?>

                        <?php

                        $status = $payment['payment_status'];

                        $method = $payment['payment_method'];

                        $date = !empty($payment['payment_date'])
                            ? date(
                                'd/m/Y H:i',
                                strtotime($payment['payment_date'])
                            )
                            : 'Chưa có';

                        $statusClass = in_array(
                            $status,
                            ['pending', 'completed', 'failed'],
                            true
                        ) ? $status : 'pending';

                        ?>

                        <tr>

                            <!-- MÃ THANH TOÁN -->
                            <td>

                                <strong>
                                    #<?= (int)$payment['payment_id'] ?>
                                </strong>

                            </td>


                            <!-- MÃ ĐƠN HÀNG -->
                            <td>

                                <a
                                    href="../sales_orders/detail.php?id=<?= (int)$payment['order_id'] ?>"
                                    style="color:#2563eb;font-weight:bold;"
                                >
                                    <?= h($payment['order_code']) ?>
                                </a>

                            </td>


                            <!-- KHÁCH HÀNG -->
                            <td>

                                <strong>
                                    <?= h($payment['customer_name']) ?>
                                </strong>

                                <br>

                                <span class="muted">
                                    <?= h($payment['customer_phone']) ?>
                                </span>

                            </td>


                            <!-- SỐ TIỀN -->
                            <td class="amount">

                                <?= money($payment['amount']) ?>

                            </td>


                            <!-- PHƯƠNG THỨC -->
                            <td>

                                <?= h(
                                    $methodLabels[$method] ?? $method
                                ) ?>

                            </td>


                            <!-- TRẠNG THÁI -->
                            <td>

                                <span class="badge <?= h($statusClass) ?>">

                                    <?= h(
                                        $statusLabels[$status] ?? $status
                                    ) ?>

                                </span>

                            </td>


                            <!-- NGÀY THANH TOÁN -->
                            <td>

                                <?= h($date) ?>

                            </td>


                            <!-- GHI CHÚ -->
                            <td>

                                <?= h($payment['note'] ?: 'Không có') ?>

                            </td>


                            <!-- NÚT SỬA VÀ XÓA -->
                            <td>

                                <div class="row-actions">

                                    <a
                                        class="btn btn-edit btn-small"
                                        href="edit.php?id=<?= (int)$payment['payment_id'] ?>"
                                    >
                                        Sửa
                                    </a>

                                    <a
                                        class="btn btn-danger btn-small"
                                        href="delete.php?id=<?= (int)$payment['payment_id'] ?>"
                                    >
                                        Xóa
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="9" class="empty">

                            <?php if ($q !== ''): ?>

                                Không tìm thấy giao dịch phù hợp.

                            <?php else: ?>

                                Chưa có giao dịch thanh toán nào.

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- CHÂN TRANG -->
    <div class="footer">

        Dữ liệu được lấy từ MySQL:
        showroom_db → payments

    </div>

</div>

</body>
</html>