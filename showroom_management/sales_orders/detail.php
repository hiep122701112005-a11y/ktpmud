<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
// Ham bao ve du lieu hien thi
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Dinh dang tien te
function money($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}

// Nhan ma don hang tu URL
$id = $_GET['id'] ?? $_GET['order_id'] ?? null;
$code = $_GET['code'] ?? $_GET['order_code'] ?? null;

try {

    // Lay thong tin don hang
    if ($code !== null && $code !== '') {
        $sql = "SELECT so.*,
                       cu.full_name AS customer_name,
                       cu.phone AS customer_phone,
                       cu.email AS customer_email,
                       cu.address AS customer_address,
                       u.full_name AS staff_name
                FROM sales_orders so
                LEFT JOIN customers cu
                    ON so.customer_id = cu.customer_id
                LEFT JOIN users u
                    ON so.user_id = u.user_id
                WHERE so.order_code = :code
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['code' => $code]);

    } elseif ($id !== null && $id !== '') {

        if (ctype_digit((string)$id)) {
            $sql = "SELECT so.*,
                           cu.full_name AS customer_name,
                           cu.phone AS customer_phone,
                           cu.email AS customer_email,
                           cu.address AS customer_address,
                           u.full_name AS staff_name
                    FROM sales_orders so
                    LEFT JOIN customers cu
                        ON so.customer_id = cu.customer_id
                    LEFT JOIN users u
                        ON so.user_id = u.user_id
                    WHERE so.order_id = :id
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => (int)$id]);

        } else {
            $sql = "SELECT so.*,
                           cu.full_name AS customer_name,
                           cu.phone AS customer_phone,
                           cu.email AS customer_email,
                           cu.address AS customer_address,
                           u.full_name AS staff_name
                    FROM sales_orders so
                    LEFT JOIN customers cu
                        ON so.customer_id = cu.customer_id
                    LEFT JOIN users u
                        ON so.user_id = u.user_id
                    WHERE so.order_code = :code
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['code' => $id]);
        }

    } else {
        die('Không tìm thấy mã đơn hàng!');
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die('Đơn hàng không tồn tại!');
    }

    // Lay danh sach xe trong don hang
    $sqlItems = "SELECT
                    od.detail_id,
                    od.car_id AS detail_car_id,
                    od.quantity AS order_quantity,
                    od.unit_price AS order_unit_price,
                    c.*
                 FROM order_details od
                 LEFT JOIN cars c ON od.car_id = c.car_id
                 WHERE od.order_id = :order_id
                 ORDER BY od.detail_id ASC";

    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([
        'order_id' => $order['order_id']
    ]);

    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Có lỗi khi tải thông tin đơn hàng. Vui lòng kiểm tra cơ sở dữ liệu.');
}

// Trang thai don hang
$statusLabels = [
    'pending'   => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

$status = $order['status'] ?? 'pending';
$statusText = $statusLabels[$status] ?? $status;

$orderDate = !empty($order['order_date'])
    ? date('d/m/Y H:i', strtotime($order['order_date']))
    : 'Chưa có thông tin';

$carLabels = [
    'car_name' => 'Tên ô tô',
    'name' => 'Tên xe',
    'model' => 'Mẫu xe',
    'year' => 'Năm sản xuất',
    'color' => 'Màu sắc',
    'brand_id' => 'Mã hãng xe',
    'fuel_type' => 'Nhiên liệu',
    'transmission' => 'Hộp số',
    'stock' => 'Tồn kho',
    'description' => 'Mô tả'
];

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #172033;
            padding: 45px 5%;
        }

        .container {
            max-width: 1250px;
            margin: auto;
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 5px;
            margin-bottom: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 35px;
        }

        h1 {
            font-size: 38px;
            margin-bottom: 12px;
        }

        .subtitle {
            color: #64748b;
            font-size: 17px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 30px;
            margin-bottom: 25px;
        }

        h2 {
            font-size: 23px;
            margin-bottom: 24px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .info-item label {
            display: block;
            color: #64748b;
            margin-bottom: 9px;
            font-size: 14px;
        }

        .info-item strong {
            font-size: 16px;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 20px;
            background: #fef3c7;
            color: #92400e;
            font-weight: bold;
        }

        .status.completed {
            background: #dcfce7;
            color: #166534;
        }

        .status.cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status.confirmed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 17px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .car-info {
            margin-top: 10px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.8;
        }

        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 25px;
            background: #eff6ff;
            border-radius: 15px;
            margin-top: 25px;
        }

        .total strong:last-child {
            font-size: 27px;
            color: #2563eb;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border: none;
            border-radius: 12px;
            padding: 15px 22px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-back {
            background: white;
            color: #172033;
            border: 1px solid #e2e8f0;
        }

        .btn-print {
            background: #2563eb;
            color: white;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        @media (max-width: 700px) {
            body {
                padding: 25px 4%;
            }

            h1 {
                font-size: 28px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 20px;
            }
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .buttons {
                display: none;
            }

            .card {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="brand">SHOWROOM MANAGEMENT</div>

    <div class="header">
        <div>
            <h1>Chi tiết đơn hàng</h1>
            <p class="subtitle">
                Thông tin chi tiết giao dịch tại showroom.
            </p>
        </div>

        <div class="buttons">
            <a href="index.php" class="btn btn-back">
                ← Danh sách đơn hàng
            </a>

            <button onclick="window.print()" class="btn btn-print">
                In đơn hàng
            </button>
        </div>
    </div>

    <!-- THONG TIN DON HANG -->
    <div class="card">

        <h2>Thông tin đơn hàng</h2>

        <div class="info-grid">

            <div class="info-item">
                <label>Mã đơn hàng</label>
                <strong><?= h($order['order_code']) ?></strong>
            </div>

            <div class="info-item">
                <label>Ngày tạo</label>
                <strong><?= h($orderDate) ?></strong>
            </div>

            <div class="info-item">
                <label>Nhân viên phụ trách</label>
                <strong><?= h($order['staff_name'] ?? 'Chưa phân công') ?></strong>
            </div>

            <div class="info-item">
                <label>Trạng thái</label>
                <strong>
                    <span class="status <?= h($status) ?>">
                        <?= h($statusText) ?>
                    </span>
                </strong>
            </div>

        </div>

    </div>

    <!-- THONG TIN KHACH HANG -->
    <div class="card">

        <h2>Thông tin khách hàng</h2>

        <div class="info-grid">

            <div class="info-item">
                <label>Họ và tên</label>
                <strong><?= h($order['customer_name'] ?? 'Không có dữ liệu') ?></strong>
            </div>

            <div class="info-item">
                <label>Số điện thoại</label>
                <strong><?= h($order['customer_phone'] ?? 'Chưa cập nhật') ?></strong>
            </div>

            <div class="info-item">
                <label>Email</label>
                <strong><?= h($order['customer_email'] ?? 'Chưa cập nhật') ?></strong>
            </div>

            <div class="info-item">
                <label>Địa chỉ</label>
                <strong><?= h($order['customer_address'] ?? 'Chưa cập nhật') ?></strong>
            </div>

        </div>

    </div>

    <!-- DANH SACH XE -->
    <div class="card">

        <h2>Danh sách ô tô trong đơn hàng</h2>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Thông tin ô tô</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($items) > 0): ?>

                    <?php foreach ($items as $index => $item): ?>

                        <?php
                        $carName = $item['car_name']
                            ?? $item['name']
                            ?? $item['model']
                            ?? ('Ô tô #' . $item['detail_car_id']);

                        $quantity = (int)$item['order_quantity'];
                        $unitPrice = (float)$item['order_unit_price'];
                        $subtotal = $quantity * $unitPrice;
                        ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <strong><?= h($carName) ?></strong>

                                <div class="car-info">

                                    <?php foreach ($item as $key => $value): ?>

                                        <?php
                                        $ignored = [
                                            'detail_id',
                                            'detail_car_id',
                                            'order_quantity',
                                            'order_unit_price',
                                            'car_id',
                                            'created_at',
                                            'updated_at',
                                            'image',
                                            'image_url',
                                            'photo',
                                            'thumbnail'
                                        ];

                                        if (in_array($key, $ignored) || $value === null || $value === '') {
                                            continue;
                                        }

                                        $label = $carLabels[$key] ?? $key;

                                        if (strpos($key, 'price') !== false) {
                                            $value = money($value);
                                        }
                                        ?>

                                        <?= h($label) ?>:
                                        <?= h($value) ?><br>

                                    <?php endforeach; ?>

                                </div>
                            </td>

                            <td><?= $quantity ?></td>

                            <td><?= money($unitPrice) ?></td>

                            <td><strong><?= money($subtotal) ?></strong></td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            Chưa có chi tiết ô tô trong đơn hàng.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- TONG TIEN -->
        <div class="total">

            <strong>Tổng tiền đơn hàng</strong>

            <strong><?= money($order['total_amount']) ?></strong>

        </div>

    </div>

    <div class="buttons">
        <a href="index.php" class="btn btn-back">
            ← Quay lại danh sách
        </a>
    </div>

</div>

</body>
</html>