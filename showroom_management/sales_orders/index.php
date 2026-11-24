<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$keyword = trim($_GET['q'] ?? '');

$sql = "
    SELECT
        so.order_id,
        so.order_code,
        so.order_date,
        so.total_amount,
        so.status,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        u.full_name AS staff_name,
        COALESCE(SUM(od.quantity), 0) AS total_quantity
    FROM sales_orders so
    LEFT JOIN customers c
        ON so.customer_id = c.customer_id
    LEFT JOIN users u
        ON so.user_id = u.user_id
    LEFT JOIN order_details od
        ON so.order_id = od.order_id
";

$params = [];

if ($keyword !== '') {
    $sql .= "
        WHERE so.order_code LIKE :code
        OR c.full_name LIKE :customer
        OR c.phone LIKE :phone
    ";

    $search = "%{$keyword}%";

    $params = [
        ':code' => $search,
        ':customer' => $search,
        ':phone' => $search
    ];
}

$sql .= "
    GROUP BY
        so.order_id,
        so.order_code,
        so.order_date,
        so.total_amount,
        so.status,
        c.full_name,
        c.phone,
        u.full_name
    ORDER BY so.order_id DESC
";

$orders = [];
$errorMessage = '';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $errorMessage = 'Không thể tải danh sách đơn hàng.';
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$statusMap = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quản lý đơn hàng | Showroom</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #0f172a;
}

.container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 45px 35px;
}

.eyebrow {
    color: #2563eb;
    font-size: 15px;
    font-weight: bold;
    letter-spacing: 4px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 55px;
}

h1 {
    font-size: 38px;
    margin: 25px 0 15px;
}

.subtitle {
    color: #64748b;
    font-size: 19px;
}

.actions {
    display: flex;
    gap: 12px;
}

.btn {
    display: inline-block;
    padding: 15px 22px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: bold;
    border: none;
    cursor: pointer;
    font-size: 15px;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-light {
    background: white;
    color: #1e293b;
    border: 1px solid #e2e8f0;
}

.btn-danger {
    background: #fee2e2;
    color: #dc2626;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 22px;
    overflow: hidden;
}

.panel-head {
    padding: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.panel-head h2 {
    margin: 0 0 12px;
}

.muted {
    color: #64748b;
}

.search {
    display: flex;
    gap: 12px;
}

.search input {
    width: 340px;
    max-width: 100%;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    font-size: 15px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th {
    background: #f8fafc;
    color: #475569;
    padding: 20px 15px;
    text-align: left;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 20px 15px;
    border-bottom: 1px solid #e2e8f0;
}

.order-code {
    color: #2563eb;
    font-weight: bold;
}

.price {
    color: #1d4ed8;
    font-weight: bold;
}

.status {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}

.completed {
    background: #dcfce7;
    color: #166534;
}

.cancelled {
    background: #fee2e2;
    color: #dc2626;
}

.small {
    padding: 9px 12px;
    font-size: 13px;
    margin: 2px;
}

.notice {
    padding: 16px;
    margin-bottom: 20px;
    border-radius: 12px;
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

.footer {
    margin-top: 25px;
    color: #64748b;
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <div class="eyebrow">SHOWROOM MANAGEMENT</div>

            <h1>Quản lý đơn hàng</h1>

            <div class="subtitle">
                Theo dõi đơn hàng, khách hàng và doanh thu.
            </div>
        </div>

        <div class="actions">
            <a href="../index.php" class="btn btn-light">
                Trang chủ
            </a>

            <a href="add.php" class="btn btn-primary">
                + Tạo đơn hàng
            </a>
        </div>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div style="background:#dcfce7;color:#166534;padding:16px;border-radius:12px;margin-bottom:20px;">
            Tạo đơn hàng thành công!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div style="background:#dcfce7;color:#166534;padding:16px;border-radius:12px;margin-bottom:20px;">
            Cập nhật đơn hàng thành công!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div style="background:#dcfce7;color:#166534;padding:16px;border-radius:12px;margin-bottom:20px;">
            Xóa đơn hàng thành công!
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="notice"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="panel">

        <div class="panel-head">

            <div>
                <h2>Danh sách đơn hàng</h2>
                <div class="muted">
                    <?= count($orders) ?> kết quả
                </div>
            </div>

            <form method="GET" class="search">
                <input
                    type="text"
                    name="q"
                    placeholder="Tìm mã đơn, khách hàng, số điện thoại..."
                    value="<?= e($keyword) ?>"
                >

                <button class="btn btn-primary" type="submit">
                    Tìm kiếm
                </button>
            </form>

        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Nhân viên</th>
                        <th>Ngày tạo</th>
                        <th>Số lượng xe</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($orders) > 0): ?>

                    <?php foreach ($orders as $order): ?>

                        <?php
                        $status = $order['status'];
                        $statusLabel = $statusMap[$status] ?? $status;
                        ?>

                        <tr>
                            <td class="order-code">
                                <?= e($order['order_code']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= e($order['customer_name'] ?? 'Không có') ?>
                                </strong>
                                <br>
                                <span class="muted">
                                    <?= e($order['customer_phone'] ?? '') ?>
                                </span>
                            </td>

                            <td>
                                <?= e($order['staff_name'] ?? 'Không có') ?>
                            </td>

                            <td>
                                <?= $order['order_date']
                                    ? date('d/m/Y H:i', strtotime($order['order_date']))
                                    : 'Chưa có' ?>
                            </td>

                            <td>
                                <?= (int)$order['total_quantity'] ?>
                            </td>

                            <td class="price">
                                <?= number_format((float)$order['total_amount'], 0, ',', '.') ?> đ
                            </td>

                            <td>
                                <span class="status <?= e($status) ?>">
                                    <?= e($statusLabel) ?>
                                </span>
                            </td>

                            <td>
                                <a class="btn btn-light small"
                                   href="detail.php?id=<?= (int)$order['order_id'] ?>">
                                    Chi tiết
                                </a>

                                <a class="btn btn-light small"
                                   href="edit.php?id=<?= (int)$order['order_id'] ?>">
                                    Sửa
                                </a>

                                <a class="btn btn-danger small"
                                   href="delete.php?id=<?= (int)$order['order_id'] ?>">
                                    Xóa
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            Chưa có đơn hàng nào.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>
    </div>

    <div class="footer">
        Dữ liệu được lấy từ MySQL: showroom_db → sales_orders
    </div>

</div>

</body>
</html>