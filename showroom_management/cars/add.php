<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

// Hàm hiển thị dữ liệu an toàn trên trang HTML.
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function textLength(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

// Mã bảo vệ biểu mẫu; dùng cùng tên với những trang quản lý ô tô khác.
if (empty($_SESSION['cars_csrf'])) {
    $_SESSION['cars_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$brands = [];
$data = [
    'brand_id' => '',
    'car_name' => '',
    'model_year' => '',
    'color' => '',
    'price' => '',
    'stock_quantity' => '0',
    'description' => '',
    'image_url' => ''
];

// Lấy danh sách hãng xe từ bảng brands.
try {
    $stmt = $pdo->query('SELECT brand_id, brand_name FROM brands ORDER BY brand_name');
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'Không thể tải danh sách hãng xe. Hãy kiểm tra MySQL.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Đọc dữ liệu, giữ lại trên biểu mẫu nếu nhập sai.
    foreach ($data as $key => $oldValue) {
        $input = $_POST[$key] ?? null;
        $data[$key] = is_string($input) ? trim($input) : '';
    }

    $token = $_POST['csrf_token'] ?? null;
    $brandId = filter_var(
        $data['brand_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $year = filter_var(
        $data['model_year'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1886, 'max_range' => 2100]]
    );
    $stock = filter_var(
        $data['stock_quantity'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    $validBrandIds = array_map('intval', array_column($brands, 'brand_id'));

    if (!is_string($token) || !hash_equals($_SESSION['cars_csrf'], $token)) {
        $error = 'Biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.';
    } elseif ($brandId === false || !in_array($brandId, $validBrandIds, true)) {
        $error = 'Vui lòng chọn hãng xe hợp lệ.';
    } elseif ($data['car_name'] === '' || textLength($data['car_name']) > 150) {
        $error = 'Tên ô tô phải có từ 1 đến 150 ký tự.';
    } elseif ($year === false) {
        $error = 'Năm sản xuất không hợp lệ.';
    } elseif (textLength($data['color']) > 50) {
        $error = 'Màu sắc không được quá 50 ký tự.';
    } elseif (
        !preg_match('/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/', $data['price'])
        || (float)$data['price'] <= 0
    ) {
        $error = 'Giá bán phải lớn hơn 0 và có tối đa 2 chữ số thập phân.';
    } elseif ($stock === false) {
        $error = 'Số lượng tồn kho phải là số nguyên từ 0 trở lên.';
    } elseif (textLength($data['image_url']) > 255) {
        $error = 'Đường dẫn ảnh không được quá 255 ký tự.';
    } elseif (!$brands) {
        $error = 'Chưa có hãng xe để lựa chọn.';
    } else {
        try {
            $sql = 'INSERT INTO cars
                    (brand_id, car_name, model_year, color, price,
                     stock_quantity, description, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $brandId,
                $data['car_name'],
                $year,
                $data['color'] === '' ? null : $data['color'],
                $data['price'],
                $stock,
                $data['description'] === '' ? null : $data['description'],
                $data['image_url'] === '' ? null : $data['image_url']
            ]);

            header('Location: index.php?added=1');
            exit;
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $error = 'Không thể lưu ô tô vào MySQL. Hãy kiểm tra lại thông tin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thêm ô tô mới | Showroom</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container narrow">
    <div class="topbar">
        <div>
            <div class="eyebrow">SHOWROOM MANAGEMENT</div>
            <h1>Thêm ô tô mới</h1>
            <p class="muted">Nhập thông tin và lưu xe vào hệ thống showroom.</p>
        </div>
        <a class="button button-light" href="index.php">← Danh sách ô tô</a>
    </div>

    <section class="panel form-panel">
        <?php if ($error !== ''): ?>
            <div class="notice danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="add.php" class="car-form">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['cars_csrf']) ?>">

            <label for="brand">Hãng xe <span class="required">*</span></label>
            <select id="brand" name="brand_id" required>
                <option value="">-- Chọn hãng xe --</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?= (int)$brand['brand_id'] ?>"
                        <?= (string)$brand['brand_id'] === $data['brand_id'] ? 'selected' : '' ?>>
                        <?= e($brand['brand_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="name">Tên ô tô <span class="required">*</span></label>
            <input id="name" type="text" name="car_name" maxlength="150"
                   value="<?= e($data['car_name']) ?>" required>

            <div class="form-grid">
                <div>
                    <label for="year">Năm sản xuất <span class="required">*</span></label>
                    <input id="year" type="number" name="model_year" min="1886" max="2100"
                           value="<?= e($data['model_year']) ?>" required>
                </div>
                <div>
                    <label for="color">Màu sắc</label>
                    <input id="color" type="text" name="color" maxlength="50"
                           value="<?= e($data['color']) ?>">
                </div>
                <div>
                    <label for="price">Giá bán (VNĐ) <span class="required">*</span></label>
                    <input id="price" type="number" name="price" min="0.01" step="0.01"
                           value="<?= e($data['price']) ?>" required>
                </div>
                <div>
                    <label for="stock">Số lượng tồn kho <span class="required">*</span></label>
                    <input id="stock" type="number" name="stock_quantity" min="0" step="1"
                           value="<?= e($data['stock_quantity']) ?>" required>
                </div>
            </div>

            <label for="description">Mô tả</label>
            <textarea id="description" name="description" rows="4"><?= e($data['description']) ?></textarea>

            <label for="image_url">Đường dẫn ảnh</label>
            <input id="image_url" type="text" name="image_url" maxlength="255"
                   value="<?= e($data['image_url']) ?>"
                   placeholder="https://... hoặc đường dẫn ảnh trong dự án">

            <div class="actions form-actions">
                <button class="button button-primary" type="submit">Thêm ô tô</button>
                <a class="button button-light" href="index.php">Hủy</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>