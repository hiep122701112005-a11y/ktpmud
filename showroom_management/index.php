<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Bao ve du lieu khi hien thi
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Dinh dang tien Viet Nam
function money($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}

// Dinh dang ngay
function formatDate($date) {
    if (!$date) {
        return 'Chưa có';
    }

    return date('d/m/Y H:i', strtotime($date));
}

// Cac thong ke ban dau
$stats = [
    'brands' => 0,
    'cars' => 0,
    'stock' => 0,
    'customers' => 0,
    'orders' => 0,
    'pending_orders' => 0,
    'payments' => 0,
    'revenue' => 0
];

$recentOrders = [];
$recentPayments = [];
$error = '';

try {

    // Dem so hang xe
    $stats['brands'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM brands"
    )->fetchColumn();

    // Dem so mau xe
    $stats['cars'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM cars"
    )->fetchColumn();

    // Tong so luong xe trong kho
    $stats['stock'] = (int)$pdo->query(
        "SELECT COALESCE(SUM(stock_quantity), 0) FROM cars"
    )->fetchColumn();

    // Dem so khach hang
    $stats['customers'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM customers"
    )->fetchColumn();

    // Dem don hang
    $stats['orders'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM sales_orders"
    )->fetchColumn();

    // Dem don hang cho xu ly
    $stats['pending_orders'] = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM sales_orders
         WHERE status = 'pending'"
    )->fetchColumn();

    // Dem giao dich da hoan tat
    $stats['payments'] = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM payments
         WHERE payment_status = 'completed'"
    )->fetchColumn();

    // Tinh doanh thu thuc te
    // Chi tinh giao dich thanh toan da hoan tat
    $stats['revenue'] = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE payment_status = 'completed'"
    )->fetchColumn();

    // Lay 5 don hang gan day
    $stmt = $pdo->query(
        "SELECT
            so.order_id,
            so.order_code,
            so.order_date,
            so.total_amount,
            so.status,
            c.full_name AS customer_name
         FROM sales_orders so
         LEFT JOIN customers c
            ON so.customer_id = c.customer_id
         ORDER BY so.order_id DESC
         LIMIT 5"
    );

    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lay 5 giao dich thanh toan gan day
    $stmt = $pdo->query(
        "SELECT
            p.payment_id,
            p.amount,
            p.payment_method,
            p.payment_status,
            p.payment_date,
            so.order_code
         FROM payments p
         LEFT JOIN sales_orders so
            ON p.order_id = so.order_id
         ORDER BY p.payment_id DESC
         LIMIT 5"
    );

    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = 'Không thể tải đầy đủ dữ liệu Dashboard.';
}

$orderStatusLabels = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

$paymentStatusLabels = [
    'pending' => 'Chờ xử lý',
    'completed' => 'Hoàn tất',
    'failed' => 'Thất bại'
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | Showroom Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f1f5f9;
    font-family: Arial, Helvetica, sans-serif;
    color: #0f172a;
    line-height: 1.6;
}

a {
    text-decoration: none;
}

.topbar {
    background: white;
    border-bottom: 1px solid #e2e8f0;
}

.topbar-inner {
    max-width: 1500px;
    margin: auto;
    padding: 20px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.logo {
    color: #2563eb;
    font-size: 17px;
    font-weight: bold;
    letter-spacing: 3px;
}

.top-label {
    color: #64748b;
    font-size: 14px;
}

.container {
    max-width: 1500px;
    margin: auto;
    padding: 40px 35px 60px;
}

.welcome {
    margin-bottom: 35px;
}

.welcome h1 {
    margin: 0 0 12px;
    font-size: 38px;
}

.subtitle {
    color: #64748b;
    font-size: 18px;
}

.section-title {
    font-size: 23px;
    margin: 0 0 22px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(15,23,42,.03);
}

.stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background: #eff6ff;
    border-radius: 13px;
    font-size: 24px;
    margin-bottom: 20px;
}

.stat-label {
    color: #64748b;
    font-size: 15px;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 30px;
    font-weight: bold;
    color: #0f172a;
    overflow-wrap: anywhere;
}

.stat-value.blue {
    color: #2563eb;
}

.stat-note {
    font-size: 13px;
    color: #64748b;
    margin-top: 8px;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.menu-card {
    display: block;
    background: white;
    padding: 27px;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    transition: transform .2s, box-shadow .2s;
}

.menu-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(15,23,42,.08);
}

.menu-icon {
    font-size: 29px;
    margin-bottom: 15px;
}

.menu-card h3 {
    color: #0f172a;
    font-size: 19px;
    margin: 0 0 10px;
}

.menu-card p {
    margin: 0;
    color: #64748b;
    font-size: 15px;
}

.menu-link {
    display: inline-block;
    margin-top: 18px;
    color: #2563eb;
    font-size: 14px;
    font-weight: bold;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 30px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    padding: 25px;
}

.panel-header h2 {
    margin: 0;
    font-size: 22px;
}

.view-all {
    color: #2563eb;
    font-weight: bold;
    font-size: 14px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 750px;
    border-collapse: collapse;
}

th {
    background: #f8fafc;
    color: #475569;
    padding: 18px 20px;
    text-align: left;
    font-size: 14px;
}

td {
    padding: 19px 20px;
    border-top: 1px solid #e2e8f0;
    font-size: 14px;
}

.code {
    color: #2563eb;
    font-weight: bold;
}

.price {
    color: #1d4ed8;
    font-weight: bold;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.status.pending {
    background: #fef3c7;
    color: #92400e;
}

.status.confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}

.status.completed {
    background: #dcfce7;
    color: #166534;
}

.status.cancelled,
.status.failed {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    padding: 40px;
    text-align: center;
    color: #64748b;
}

.alert {
    background: #fee2e2;
    color: #991b1b;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 25px;
}

.footer {
    color: #64748b;
    text-align: center;
    font-size: 14px;
    padding: 20px;
}

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .menu-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 650px) {
    .container {
        padding: 25px 15px;
    }

    .topbar-inner {
        padding: 18px 15px;
    }

    .welcome h1 {
        font-size: 28px;
    }

    .stats-grid,
    .menu-grid {
        grid-template-columns: 1fr;
    }

    .stat-value {
        font-size: 26px;
    }
}
</style>

</head>

<body>

<!-- THANH TIEU DE -->
<div class="topbar">

    <div class="topbar-inner">

        <div class="logo">
            SHOWROOM MANAGEMENT
        </div>

        <div class="top-label">
            Hệ thống quản lý showroom ô tô
        </div>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="users/index.php"
       style="color:#2563eb; font-weight:bold; text-decoration:none; margin-right:20px;">
        Quản lý tài khoản
    </a>
    <?php endif; ?>
        <a href="logout.php"
   style="background:#fee2e2;
          color:#dc2626;
          padding:12px 20px;
          border-radius:10px;
          font-weight:bold;
          text-decoration:none;">
    Đăng xuất
</a>

    </div>

</div>

<div class="container">

    <!-- LOI CHAO -->
    <div class="welcome">

        <h1>Tổng quan Showroom</h1>

        <div class="subtitle">
            Theo dõi tình hình kinh doanh và quản lý toàn bộ showroom.
        </div>

    </div>

    <?php if ($error !== ''): ?>
        <div class="alert">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- CAC THE THONG KE -->
    <h2 class="section-title">Thống kê tổng quan</h2>

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">🚘</div>

            <div class="stat-label">Mẫu ô tô</div>

            <div class="stat-value">
                <?= $stats['cars'] ?>
            </div>

            <div class="stat-note">
                Thuộc <?= $stats['brands'] ?> hãng xe
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📦</div>

            <div class="stat-label">Tổng xe tồn kho</div>

            <div class="stat-value">
                <?= $stats['stock'] ?>
            </div>

            <div class="stat-note">
                Số xe hiện còn trong kho
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">👥</div>

            <div class="stat-label">Khách hàng</div>

            <div class="stat-value">
                <?= $stats['customers'] ?>
            </div>

            <div class="stat-note">
                Khách hàng trong hệ thống
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🧾</div>

            <div class="stat-label">Đơn hàng</div>

            <div class="stat-value">
                <?= $stats['orders'] ?>
            </div>

            <div class="stat-note">
                <?= $stats['pending_orders'] ?> đơn chờ xử lý
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💰</div>

            <div class="stat-label">Doanh thu đã thu</div>

            <div class="stat-value blue" style="font-size:25px">
                <?= money($stats['revenue']) ?>
            </div>

            <div class="stat-note">
                Chỉ tính thanh toán đã hoàn tất
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💳</div>

            <div class="stat-label">Thanh toán hoàn tất</div>

            <div class="stat-value">
                <?= $stats['payments'] ?>
            </div>

            <div class="stat-note">
                Số giao dịch thành công
            </div>
        </div>

    </div>

    <!-- MENU QUAN LY -->
    <h2 class="section-title">Chức năng quản lý</h2>

    <div class="menu-grid">

        <a href="brands/index.php" class="menu-card">
            <div class="menu-icon">🏷️</div>

            <h3>Quản lý hãng xe</h3>

            <p>
                Thêm, sửa, xóa và quản lý các hãng ô tô.
            </p>

            <span class="menu-link">Mở quản lý hãng xe →</span>
        </a>

        <a href="cars/index.php" class="menu-card">
            <div class="menu-icon">🚗</div>

            <h3>Quản lý ô tô</h3>

            <p>
                Theo dõi mẫu xe, giá bán và số lượng tồn kho.
            </p>

            <span class="menu-link">Mở quản lý ô tô →</span>
        </a>

        <a href="customers/index.php" class="menu-card">
            <div class="menu-icon">👥</div>

            <h3>Quản lý khách hàng</h3>

            <p>
                Quản lý họ tên, số điện thoại và thông tin khách hàng.
            </p>

            <span class="menu-link">Mở quản lý khách hàng →</span>
        </a>

        <a href="sales_orders/index.php" class="menu-card">
            <div class="menu-icon">📋</div>

            <h3>Quản lý đơn hàng</h3>

            <p>
                Tạo đơn hàng, xem chi tiết và theo dõi giao dịch bán xe.
            </p>

            <span class="menu-link">Mở quản lý đơn hàng →</span>
        </a>

        <a href="payments/index.php" class="menu-card">
            <div class="menu-icon">💳</div>

            <h3>Quản lý thanh toán</h3>

            <p>
                Ghi nhận các khoản thanh toán và theo dõi trạng thái.
            </p>

            <span class="menu-link">Mở quản lý thanh toán →</span>
        </a>

    </div>

    <!-- DON HANG GAN DAY -->
    <div class="panel">

        <div class="panel-header">

            <h2>Đơn hàng gần đây</h2>

            <a class="view-all" href="sales_orders/index.php">
                Xem tất cả →
            </a>

        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Ngày tạo</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($recentOrders) > 0): ?>

                    <?php foreach ($recentOrders as $order): ?>

                        <?php
                        $status = $order['status'];
                        $label = $orderStatusLabels[$status] ?? $status;

                        $class = in_array(
                            $status,
                            ['pending','confirmed','completed','cancelled'],
                            true
                        ) ? $status : 'pending';
                        ?>

                        <tr>

                            <td>
                                <a class="code"
                                   href="sales_orders/detail.php?id=<?= (int)$order['order_id'] ?>">
                                    <?= h($order['order_code']) ?>
                                </a>
                            </td>

                            <td>
                                <?= h($order['customer_name'] ?? 'Không có') ?>
                            </td>

                            <td>
                                <?= h(formatDate($order['order_date'])) ?>
                            </td>

                            <td class="price">
                                <?= money($order['total_amount']) ?>
                            </td>

                            <td>
                                <span class="status <?= h($class) ?>">
                                    <?= h($label) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            Chưa có đơn hàng nào.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

    <!-- THANH TOAN GAN DAY -->
    <div class="panel">

        <div class="panel-header">

            <h2>Giao dịch thanh toán gần đây</h2>

            <a class="view-all" href="payments/index.php">
                Xem tất cả →
            </a>

        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Mã giao dịch</th>
                        <th>Mã đơn hàng</th>
                        <th>Ngày ghi nhận</th>
                        <th>Số tiền</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($recentPayments) > 0): ?>

                    <?php foreach ($recentPayments as $payment): ?>

                        <?php
                        $status = $payment['payment_status'];
                        $label = $paymentStatusLabels[$status] ?? $status;

                        $class = in_array(
                            $status,
                            ['pending','completed','failed'],
                            true
                        ) ? $status : 'pending';
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    #<?= (int)$payment['payment_id'] ?>
                                </strong>
                            </td>

                            <td>
                                <?= h($payment['order_code'] ?? 'Không có') ?>
                            </td>

                            <td>
                                <?= h(formatDate($payment['payment_date'])) ?>
                            </td>

                            <td class="price">
                                <?= money($payment['amount']) ?>
                            </td>

                            <td>
                                <span class="status <?= h($class) ?>">
                                    <?= h($label) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            Chưa có giao dịch thanh toán nào.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

    <div class="footer">
        SHOWROOM MANAGEMENT
        — Dữ liệu thống kê được lấy trực tiếp từ MySQL
    </div>

</div>

</body>
</html>