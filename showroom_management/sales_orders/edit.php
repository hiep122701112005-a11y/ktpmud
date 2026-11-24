<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
}

// Lay ID don hang
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId && isset($_POST['order_id'])) {
    $orderId = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
}

if (!$orderId) {
    die('Ma don hang khong hop le!');
}

// Kiem tra don hang
$stmt = $pdo->prepare(
    "SELECT * FROM sales_orders WHERE order_id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Don hang khong ton tai!');
}

$error = '';
$success = '';

// Xu ly cap nhat don hang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $customerId = filter_var(
            $_POST['customer_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $carIds = $_POST['car_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if (!$customerId || !is_array($carIds) || !is_array($quantities)) {
            throw new Exception('Vui long nhap day du thong tin!');
        }

        if (count($carIds) === 0) {
            throw new Exception('Don hang phai co it nhat mot o to!');
        }

        $pdo->beginTransaction();

        // Khoa don hang khi cap nhat
        $stmt = $pdo->prepare(
            "SELECT order_id FROM sales_orders
             WHERE order_id = ? FOR UPDATE"
        );
        $stmt->execute([$orderId]);

        if (!$stmt->fetch()) {
            throw new Exception('Don hang khong ton tai!');
        }

        // Lay chi tiet don hang cu
        $stmt = $pdo->prepare(
            "SELECT * FROM order_details
             WHERE order_id = ? FOR UPDATE"
        );
        $stmt->execute([$orderId]);
        $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Luu gia ban cu va hoan lai ton kho cu
        $oldPrices = [];

        foreach ($oldItems as $item) {

            $carId = (int)$item['car_id'];
            $quantity = (int)$item['quantity'];

            $oldPrices[$carId] = (float)$item['unit_price'];

            $restore = $pdo->prepare(
                "UPDATE cars
                 SET stock_quantity = stock_quantity + ?
                 WHERE car_id = ?"
            );

            $restore->execute([$quantity, $carId]);
        }

        // Kiem tra khach hang
        $stmt = $pdo->prepare(
            "SELECT customer_id FROM customers
             WHERE customer_id = ?"
        );
        $stmt->execute([$customerId]);

        if (!$stmt->fetch()) {
            throw new Exception('Khach hang khong ton tai!');
        }

        $newItems = [];
        $totalAmount = 0;

        // Kiem tra tung o to
        foreach ($carIds as $index => $rawCarId) {

            $rawQuantity = $quantities[$index] ?? '';

            if ($rawCarId === '' || $rawCarId === null) {
                throw new Exception('Vui long chon day du o to!');
            }

            $carId = filter_var($rawCarId, FILTER_VALIDATE_INT);
            $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);

            if (!$carId || !$quantity || $quantity < 1) {
                throw new Exception('Ma o to hoac so luong khong hop le!');
            }

            // Khoa thong tin xe va kiem tra ton kho
            $stmt = $pdo->prepare(
                "SELECT car_id, car_name, price, stock_quantity
                 FROM cars
                 WHERE car_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$carId]);
            $car = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$car) {
                throw new Exception('O to khong ton tai!');
            }

            $stock = (int)$car['stock_quantity'];

            if ($stock < $quantity) {
                throw new Exception(
                    'Xe ' . $car['car_name'] .
                    ' khong du ton kho! Hien con ' . $stock . ' xe.'
                );
            }

            // Giu gia ban cu neu xe da co trong don hang
            // Neu them xe moi thi lay gia hien tai
            $unitPrice = isset($oldPrices[$carId])
                ? $oldPrices[$carId]
                : (float)$car['price'];

            $subtotal = $quantity * $unitPrice;

            $totalAmount += $subtotal;

            $newItems[] = [
                'car_id' => $carId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice
            ];

            // Tru ton kho theo so luong moi
            $updateStock = $pdo->prepare(
                "UPDATE cars
                 SET stock_quantity = stock_quantity - ?
                 WHERE car_id = ? AND stock_quantity >= ?"
            );

            $updateStock->execute([
                $quantity,
                $carId,
                $quantity
            ]);

            if ($updateStock->rowCount() !== 1) {
                throw new Exception('Khong the cap nhat ton kho!');
            }
        }

        // Cap nhat thong tin don hang
        // Giu nguyen ma don, nhan vien, ngay tao va trang thai
        $stmt = $pdo->prepare(
            "UPDATE sales_orders
             SET customer_id = ?,
                 total_amount = ?
             WHERE order_id = ?"
        );

        $stmt->execute([
            $customerId,
            $totalAmount,
            $orderId
        ]);

        // Xoa chi tiet cu
        $stmt = $pdo->prepare(
            "DELETE FROM order_details WHERE order_id = ?"
        );

        $stmt->execute([$orderId]);

        // Them chi tiet moi
        $stmt = $pdo->prepare(
            "INSERT INTO order_details
             (order_id, car_id, quantity, unit_price)
             VALUES (?, ?, ?, ?)"
        );

        foreach ($newItems as $item) {

            $stmt->execute([
                $orderId,
                $item['car_id'],
                $item['quantity'],
                $item['unit_price']
            ]);
        }

        // Hoan tat giao dich
        $pdo->commit();

        header('Location: detail.php?id=' . $orderId . '&updated=1');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log($e->getMessage());
            $error = 'Co loi khi cap nhat du lieu. Vui long kiem tra lai!';
        } else {
            $error = $e->getMessage();
        }
    }
}

// Lay thong tin khach hang
$customers = $pdo->query(
    "SELECT customer_id, full_name, phone
     FROM customers
     ORDER BY full_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Lay danh sach o to
$cars = $pdo->query(
    "SELECT car_id, car_name, model_year, color,
            price, stock_quantity
     FROM cars
     ORDER BY car_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Lay chi tiet don hang hien tai
$stmt = $pdo->prepare(
    "SELECT * FROM order_details
     WHERE order_id = ?
     ORDER BY detail_id"
);
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tinh so luong xe cu de phuc hoi ton kho khi sua
$oldPrices = [];
$oldQuantities = [];

foreach ($orderItems as $item) {

    $carId = (int)$item['car_id'];

    $oldPrices[$carId] = (float)$item['unit_price'];

    if (!isset($oldQuantities[$carId])) {
        $oldQuantities[$carId] = 0;
    }

    $oldQuantities[$carId] += (int)$item['quantity'];
}

// Neu co loi POST thi giu lai du lieu nguoi dung da nhap
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['car_id'])
    && is_array($_POST['car_id'])) {

    $formItems = [];

    foreach ($_POST['car_id'] as $index => $carId) {

        $formItems[] = [
            'car_id' => $carId,
            'quantity' => $_POST['quantity'][$index] ?? 1
        ];
    }

} else {

    $formItems = $orderItems;
}

// Dam bao luon co mot dong chon xe
if (empty($formItems)) {
    $formItems = [
        ['car_id' => '', 'quantity' => 1]
    ];
}

$selectedCustomer = $_POST['customer_id'] ?? $order['customer_id'];

$statusLabels = [
    'pending' => 'Cho xu ly',
    'confirmed' => 'Da xac nhan',
    'completed' => 'Hoan thanh',
    'cancelled' => 'Da huy'
];

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sua don hang | Showroom</title>

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
    max-width: 1200px;
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
    flex-wrap: wrap;
    gap: 20px;
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
    padding: 35px;
}

h2 {
    font-size: 23px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 25px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 12px;
}

input, select {
    width: 100%;
    padding: 16px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    font-size: 16px;
    background: white;
}

input:focus, select:focus {
    outline: 2px solid #2563eb;
    border-color: #2563eb;
}

.car-row {
    display: grid;
    grid-template-columns: 1fr 170px 90px;
    gap: 15px;
    align-items: end;
    background: #f8fafc;
    padding: 22px;
    border-radius: 15px;
    margin-bottom: 15px;
}

.car-row small {
    display: block;
    color: #64748b;
    margin-top: 8px;
}

.stock-warning {
    color: #dc2626 !important;
}

.btn {
    display: inline-block;
    padding: 15px 22px;
    border-radius: 12px;
    border: none;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
}

.btn-blue {
    background: #2563eb;
    color: white;
}

.btn-white {
    background: white;
    border: 1px solid #e2e8f0;
    color: #172033;
}

.btn-red {
    background: #fee2e2;
    color: #dc2626;
}

.btn-add {
    margin-top: 10px;
}

.total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #eff6ff;
    padding: 25px;
    border-radius: 15px;
    margin-top: 25px;
}

.total strong:last-child {
    color: #2563eb;
    font-size: 27px;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 30px;
}

.alert {
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.alert-error {
    background: #fee2e2;
    color: #b91c1c;
}

.info {
    color: #64748b;
    margin-top: 12px;
}

@media(max-width: 750px) {
    body {
        padding: 25px 4%;
    }

    .car-row {
        grid-template-columns: 1fr;
    }

    h1 {
        font-size: 28px;
    }

    .card {
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="brand">SHOWROOM MANAGEMENT</div>

    <div class="header">
        <div>
            <h1>Sua don hang</h1>
            <p class="subtitle">
                Cap nhat thong tin va danh sach o to trong don hang.
            </p>
        </div>

        <a href="index.php" class="btn btn-white">
            ← Danh sach don hang
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Thong tin don hang</h2>

        <div class="form-group">
            <label>Ma don hang</label>
            <input
                type="text"
                value="<?= h($order['order_code']) ?>"
                readonly
            >
        </div>

        <div class="form-group">
            <label>Trang thai hien tai</label>
            <input
                type="text"
                value="<?= h($statusLabels[$order['status']] ?? $order['status']) ?>"
                readonly
            >
        </div>

        <form method="POST" action="edit.php?id=<?= (int)$orderId ?>"
              id="editForm">

            <input type="hidden" name="order_id"
                   value="<?= (int)$orderId ?>">

            <div class="form-group">

                <label>Khach hang *</label>

                <select name="customer_id" required>

                    <option value="">-- Chon khach hang --</option>

                    <?php foreach ($customers as $customer): ?>

                        <option
                            value="<?= (int)$customer['customer_id'] ?>"
                            <?= ((int)$selectedCustomer === (int)$customer['customer_id']) ? 'selected' : '' ?>
                        >
                            <?= h($customer['full_name']) ?>
                            - <?= h($customer['phone']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Danh sach o to *</label>

                <div id="carRows">

                    <?php foreach ($formItems as $row): ?>

                        <div class="car-row">

                            <div>

                                <label>Chon o to</label>

                                <select name="car_id[]"
                                        class="car-select"
                                        required
                                        onchange="updateTotal()">

                                    <option value="">-- Chon o to --</option>

                                    <?php foreach ($cars as $car): ?>

                                        <?php
                                        $carId = (int)$car['car_id'];

                                        $available = (int)$car['stock_quantity']
                                            + (int)($oldQuantities[$carId] ?? 0);

                                        $price = isset($oldPrices[$carId])
                                            ? $oldPrices[$carId]
                                            : (float)$car['price'];
                                        ?>

                                        <option
                                            value="<?= $carId ?>"
                                            data-price="<?= $price ?>"
                                            data-stock="<?= $available ?>"
                                            <?= ((int)$row['car_id'] === $carId) ? 'selected' : '' ?>
                                        >
                                            <?= h($car['car_name']) ?>
                                            (<?= h($car['model_year']) ?> -
                                            <?= h($car['color']) ?>)
                                            - <?= money($price) ?>
                                            (Ton: <?= $available ?>)
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <small class="stock-warning"></small>

                            </div>

                            <div>

                                <label>So luong</label>

                                <input
                                    type="number"
                                    name="quantity[]"
                                    min="1"
                                    required
                                    value="<?= (int)$row['quantity'] ?>"
                                    oninput="updateTotal()"
                                >

                            </div>

                            <div>
                                <button
                                    type="button"
                                    class="btn btn-red"
                                    onclick="removeCar(this)">
                                    Xoa
                                </button>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <button
                    type="button"
                    class="btn btn-white btn-add"
                    onclick="addCar()">
                    + Them o to
                </button>

            </div>

            <div class="total">

                <strong>Tong tien don hang</strong>

                <strong id="totalAmount">0 đ</strong>

            </div>

            <div class="actions">

                <a href="index.php" class="btn btn-white">
                    Huy
                </a>

                <button type="submit" class="btn btn-blue">
                    Luu thay doi
                </button>

            </div>

        </form>

    </div>

</div>

<!-- Mau dong chon xe moi -->
<template id="carTemplate">

    <div class="car-row">

        <div>

            <label>Chon o to</label>

            <select name="car_id[]" class="car-select"
                    required onchange="updateTotal()">

                <option value="">-- Chon o to --</option>

                <?php foreach ($cars as $car): ?>

                    <?php
                    $carId = (int)$car['car_id'];

                    $available = (int)$car['stock_quantity']
                        + (int)($oldQuantities[$carId] ?? 0);

                    $price = isset($oldPrices[$carId])
                        ? $oldPrices[$carId]
                        : (float)$car['price'];
                    ?>

                    <option
                        value="<?= $carId ?>"
                        data-price="<?= $price ?>"
                        data-stock="<?= $available ?>"
                    >
                        <?= h($car['car_name']) ?>
                        (<?= h($car['model_year']) ?> -
                        <?= h($car['color']) ?>)
                        - <?= money($price) ?>
                        (Ton: <?= $available ?>)
                    </option>

                <?php endforeach; ?>

            </select>

            <small class="stock-warning"></small>

        </div>

        <div>

            <label>So luong</label>

            <input type="number" name="quantity[]"
                   min="1" value="1" required
                   oninput="updateTotal()">

        </div>

        <div>

            <button type="button" class="btn btn-red"
                    onclick="removeCar(this)">
                Xoa
            </button>

        </div>

    </div>

</template>

<script>

function addCar() {

    const template = document.getElementById('carTemplate');

    const clone = template.content.cloneNode(true);

    document.getElementById('carRows').appendChild(clone);

    updateTotal();
}

function removeCar(button) {

    const rows = document.querySelectorAll('.car-row');

    if (rows.length <= 1) {
        alert('Don hang phai co it nhat mot o to!');
        return;
    }

    button.closest('.car-row').remove();

    updateTotal();
}

function updateTotal() {

    let total = 0;

    let quantitiesByCar = {};
    let stockByCar = {};

    const rows = document.querySelectorAll('.car-row');

    rows.forEach(row => {

        const select = row.querySelector('.car-select');
        const quantityInput = row.querySelector('[name="quantity[]"]');

        const option = select.options[select.selectedIndex];

        if (!option || !select.value) {
            row.querySelector('.stock-warning').textContent = '';
            return;
        }

        const price = Number(option.dataset.price || 0);
        const stock = Number(option.dataset.stock || 0);
        const quantity = Number(quantityInput.value || 0);

        total += price * quantity;

        const carId = select.value;

        quantitiesByCar[carId] =
            (quantitiesByCar[carId] || 0) + quantity;

        stockByCar[carId] = stock;

    });

    // Kiem tra tong so luong cua tung loai xe
    rows.forEach(row => {

        const select = row.querySelector('.car-select');
        const warning = row.querySelector('.stock-warning');

        if (!select.value) {
            warning.textContent = '';
            return;
        }

        const carId = select.value;

        if (quantitiesByCar[carId] > stockByCar[carId]) {

            warning.textContent =
                'Vuot qua ton kho! Chi co ' +
                stockByCar[carId] + ' xe co the dat.';

        } else {

            warning.textContent =
                'Ton kho co the dat: ' +
                stockByCar[carId] + ' xe.';

        }

    });

    document.getElementById('totalAmount').textContent =
        total.toLocaleString('vi-VN') + ' đ';
}

// Tinh tong tien ngay khi mo trang
updateTotal();

</script>

</body>
</html>