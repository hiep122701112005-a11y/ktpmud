<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}

$error = '';

/* =========================
   XỬ LÝ TẠO ĐƠN HÀNG
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customer_id = (int)($_POST['customer_id'] ?? 0);

        $car_ids = $_POST['car_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if ($customer_id <= 0) {
            throw new Exception('Vui lòng chọn khách hàng.');
        }

        if (!is_array($car_ids) || !is_array($quantities)) {
            throw new Exception('Dữ liệu ô tô không hợp lệ.');
        }

        // Gộp các dòng nếu khách chọn trùng một mẫu xe
        $items = [];

        foreach ($car_ids as $index => $id) {
            $car_id = (int)$id;
            $quantity = (int)($quantities[$index] ?? 0);

            if ($car_id <= 0 && $quantity <= 0) {
                continue;
            }

            if ($car_id <= 0 || $quantity <= 0) {
                throw new Exception('Vui lòng chọn ô tô và nhập số lượng hợp lệ.');
            }

            $items[$car_id] = ($items[$car_id] ?? 0) + $quantity;
        }

        if (empty($items)) {
            throw new Exception('Vui lòng chọn ít nhất một ô tô.');
        }

        $pdo->beginTransaction();

        // Kiểm tra khách hàng
        $stmt = $pdo->prepare(
            "SELECT customer_id FROM customers WHERE customer_id = ?"
        );
        $stmt->execute([$customer_id]);

        if (!$stmt->fetch()) {
            throw new Exception('Khách hàng không tồn tại.');
        }

        $total_amount = 0;
        $order_items = [];

        // Kiểm tra tồn kho và lấy giá xe
        foreach ($items as $car_id => $quantity) {

            $stmt = $pdo->prepare(
                "SELECT car_id, car_name, price, stock_quantity
                 FROM cars
                 WHERE car_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$car_id]);
            $car = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$car) {
                throw new Exception('Không tìm thấy ô tô có mã ' . $car_id);
            }

            if ($quantity > (int)$car['stock_quantity']) {
                throw new Exception(
                    'Xe ' . $car['car_name'] .
                    ' chỉ còn ' . $car['stock_quantity'] . ' chiếc.'
                );
            }

            $unit_price = (float)$car['price'];

            $total_amount += $unit_price * $quantity;

            $order_items[] = [
                'car_id' => $car_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price
            ];
        }

        // Tạo mã đơn hàng
        $order_code = 'DH' . date('YmdHis') . random_int(100, 999);

        // Lưu đơn hàng
        // user_id = NULL vì không còn nhân viên phụ trách
        $stmt = $pdo->prepare(
            "INSERT INTO sales_orders
            (order_code, customer_id, user_id, total_amount, status)
            VALUES (?, ?, NULL, ?, 'pending')"
        );

        $stmt->execute([
            $order_code,
            $customer_id,
            $total_amount
        ]);

        $order_id = $pdo->lastInsertId();

        // Lưu chi tiết đơn hàng và trừ tồn kho
        $detail_stmt = $pdo->prepare(
            "INSERT INTO order_details
            (order_id, car_id, quantity, unit_price)
            VALUES (?, ?, ?, ?)"
        );

        $stock_stmt = $pdo->prepare(
            "UPDATE cars
             SET stock_quantity = stock_quantity - ?
             WHERE car_id = ? AND stock_quantity >= ?"
        );

        foreach ($order_items as $item) {

            $detail_stmt->execute([
                $order_id,
                $item['car_id'],
                $item['quantity'],
                $item['unit_price']
            ]);

            $stock_stmt->execute([
                $item['quantity'],
                $item['car_id'],
                $item['quantity']
            ]);

            if ($stock_stmt->rowCount() !== 1) {
                throw new Exception('Không thể cập nhật tồn kho ô tô.');
            }
        }

        $pdo->commit();

        header(
            'Location: index.php?created=1&code=' .
            urlencode($order_code)
        );
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        $error = $e instanceof Exception
            ? $e->getMessage()
            : 'Không thể tạo đơn hàng. Vui lòng kiểm tra lại dữ liệu.';
    }
}

/* =========================
   LẤY DỮ LIỆU KHÁCH HÀNG
========================= */

$customers = $pdo->query(
    "SELECT customer_id, full_name, phone
     FROM customers
     ORDER BY full_name"
)->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   LẤY DANH SÁCH Ô TÔ
========================= */

$cars = $pdo->query(
    "SELECT car_id, car_name, model_year, color,
            price, stock_quantity
     FROM cars
     WHERE stock_quantity > 0
     ORDER BY car_name"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tạo đơn hàng | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .container {
            max-width: 1240px;
            margin: 55px auto;
            padding: 0 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 38px;
        }

        .brand {
            color: #2563eb;
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 4px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 40px;
            margin: 0 0 14px;
        }

        .subtitle {
            color: #64748b;
            font-size: 19px;
        }

        .btn {
            display: inline-block;
            padding: 15px 23px;
            border-radius: 13px;
            border: none;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-white {
            background: white;
            color: #1e293b;
            border: 1px solid #dbe3ee;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 18px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 38px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.04);
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

        input, select {
            width: 100%;
            padding: 16px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 16px;
            background: white;
            outline: none;
        }

        input:focus, select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .car-list {
            background: #f8fafc;
            padding: 20px;
            border-radius: 18px;
            margin-bottom: 18px;
        }

        .car-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
            padding: 5px;
        }

        .stock-info {
            display: block;
            color: #64748b;
            font-size: 14px;
            margin-top: 10px;
        }

        .total-box {
            background: #eff6ff;
            border-radius: 15px;
            padding: 24px;
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .total-label {
            font-size: 18px;
            font-weight: bold;
        }

        .total-price {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .alert {
            padding: 17px 20px;
            border-radius: 12px;
            margin-bottom: 22px;
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty {
            color: #dc2626;
            margin-bottom: 20px;
        }

        @media (max-width: 700px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            h1 {
                font-size: 30px;
            }

            .card {
                padding: 20px;
            }

            .car-row {
                grid-template-columns: 1fr;
            }

            .total-box {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">

        <div>
            <div class="brand">SHOWROOM MANAGEMENT</div>

            <h1>Tạo đơn hàng mới</h1>

            <div class="subtitle">
                Nhập thông tin để tạo đơn hàng.
            </div>
        </div>

        <a href="index.php" class="btn btn-white">
            ← Danh sách đơn hàng
        </a>

    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($customers)): ?>
        <div class="alert alert-error">
            Chưa có khách hàng. Vui lòng thêm khách hàng trước.
        </div>
    <?php endif; ?>

    <?php if (empty($cars)): ?>
        <div class="alert alert-error">
            Hiện không có ô tô còn hàng trong kho.
        </div>
    <?php endif; ?>

    <div class="card">

        <form method="POST" id="orderForm">

            <!-- KHÁCH HÀNG -->

            <div class="form-group">

                <label>Khách hàng *</label>

                <select name="customer_id" required>

                    <option value="">-- Chọn khách hàng --</option>

                    <?php foreach ($customers as $customer): ?>

                        <option value="<?= (int)$customer['customer_id'] ?>">

                            <?= h($customer['full_name']) ?>
                            -
                            <?= h($customer['phone']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- DANH SÁCH Ô TÔ -->

            <div class="form-group">

                <label>Danh sách ô tô *</label>

                <div id="carList">

                    <div class="car-list">

                        <div class="car-row">

                            <div>

                                <label>Chọn ô tô</label>

                                <select name="car_id[]" class="car-select"
                                        required onchange="updateRow(this)">

                                    <option value="">-- Chọn ô tô --</option>

                                    <?php foreach ($cars as $car): ?>

                                        <option
                                            value="<?= (int)$car['car_id'] ?>"
                                            data-price="<?= (float)$car['price'] ?>"
                                            data-stock="<?= (int)$car['stock_quantity'] ?>"
                                        >

                                            <?= h($car['car_name']) ?>
                                            (<?= h($car['model_year']) ?> -
                                            <?= h($car['color']) ?>)
                                            - <?= money($car['price']) ?>
                                            (Tồn: <?= (int)$car['stock_quantity'] ?>)

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <small class="stock-info">
                                    Chọn ô tô để xem tồn kho.
                                </small>

                            </div>

                            <div>

                                <label>Số lượng</label>

                                <input
                                    type="number"
                                    name="quantity[]"
                                    class="qty-input"
                                    min="1"
                                    value="1"
                                    required
                                    oninput="updateTotal()"
                                >

                            </div>

                            <button
                                type="button"
                                class="btn btn-danger remove-btn"
                            >
                                Xóa
                            </button>

                        </div>

                    </div>

                </div>

                <button type="button" class="btn btn-white"
                        onclick="addCar()">

                    + Thêm ô tô

                </button>

            </div>

            <!-- TỔNG TIỀN -->

            <div class="total-box">

                <div class="total-label">
                    Tổng tiền đơn hàng
                </div>

                <div class="total-price" id="totalPrice">
                    0 đ
                </div>

            </div>

            <div class="form-actions">

                <a href="index.php" class="btn btn-white">
                    Hủy
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                    <?= (empty($customers) || empty($cars)) ? 'disabled' : '' ?>
                >
                    Tạo đơn hàng
                </button>

            </div>

        </form>

    </div>

</div>

<!-- MẪU THÊM Ô TÔ -->

<template id="carTemplate">

    <div class="car-list">

        <div class="car-row">

            <div>

                <label>Chọn ô tô</label>

                <select name="car_id[]" class="car-select"
                        required onchange="updateRow(this)">

                    <option value="">-- Chọn ô tô --</option>

                    <?php foreach ($cars as $car): ?>

                        <option
                            value="<?= (int)$car['car_id'] ?>"
                            data-price="<?= (float)$car['price'] ?>"
                            data-stock="<?= (int)$car['stock_quantity'] ?>"
                        >

                            <?= h($car['car_name']) ?>
                            (<?= h($car['model_year']) ?> -
                            <?= h($car['color']) ?>)
                            - <?= money($car['price']) ?>
                            (Tồn: <?= (int)$car['stock_quantity'] ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

                <small class="stock-info">
                    Chọn ô tô để xem tồn kho.
                </small>

            </div>

            <div>

                <label>Số lượng</label>

                <input
                    type="number"
                    name="quantity[]"
                    class="qty-input"
                    min="1"
                    value="1"
                    required
                    oninput="updateTotal()"
                >

            </div>

            <button type="button"
                    class="btn btn-danger remove-btn">
                Xóa
            </button>

        </div>

    </div>

</template>

<script>

    // Cập nhật thông tin tồn kho
    function updateRow(select) {

        const row = select.closest('.car-row');

        const option = select.selectedOptions[0];

        const stockInfo = row.querySelector('.stock-info');

        const quantity = row.querySelector('.qty-input');

        if (select.value && option) {

            const stock = parseInt(option.dataset.stock);

            stockInfo.textContent = 'Tồn kho hiện tại: ' + stock + ' xe';

            quantity.max = stock;

            if (parseInt(quantity.value) > stock) {
                quantity.value = stock;
            }

        } else {

            stockInfo.textContent = 'Chọn ô tô để xem tồn kho.';

            quantity.removeAttribute('max');
        }

        updateTotal();
    }

    // Tính tổng tiền dự kiến
    function updateTotal() {

        let total = 0;

        document.querySelectorAll('.car-row').forEach(row => {

            const select = row.querySelector('.car-select');

            const quantity = row.querySelector('.qty-input');

            const option = select.selectedOptions[0];

            if (select.value && option) {

                const price = parseFloat(option.dataset.price);

                const qty = parseInt(quantity.value) || 0;

                total += price * qty;
            }

        });

        document.getElementById('totalPrice').textContent =
            new Intl.NumberFormat('vi-VN').format(total) + ' đ';
    }

    // Thêm ô tô
    function addCar() {

        const template = document.getElementById('carTemplate');

        const clone = template.content.cloneNode(true);

        document.getElementById('carList').appendChild(clone);

        updateTotal();
    }

    // Xóa dòng ô tô
    document.addEventListener('click', function(event) {

        if (event.target.classList.contains('remove-btn')) {

            const row = event.target.closest('.car-list');

            row.remove();

            updateTotal();
        }

    });

    // Kiểm tra số lượng trước khi gửi
    document.getElementById('orderForm').addEventListener(
        'submit',
        function(event) {

            let valid = true;

            document.querySelectorAll('.car-row').forEach(row => {

                const select = row.querySelector('.car-select');

                const quantity = row.querySelector('.qty-input');

                const option = select.selectedOptions[0];

                if (!select.value || !option) {
                    valid = false;
                    return;
                }

                const stock = parseInt(option.dataset.stock);

                const qty = parseInt(quantity.value);

                if (qty <= 0 || qty > stock) {
                    valid = false;
                }

            });

            if (!valid) {

                event.preventDefault();

                alert('Vui lòng kiểm tra ô tô và số lượng tồn kho.');

            }

        }
    );

</script>

</body>
</html>