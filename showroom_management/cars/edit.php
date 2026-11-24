<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

// Kết nối MySQL
require_once __DIR__ . '/../config/database.php';

// Hàm hiển thị dữ liệu an toàn
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Hàm đếm độ dài văn bản
function textLength(string $value): int {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

// Tạo mã bảo vệ biểu mẫu
if (empty($_SESSION['cars_csrf'])) {
    $_SESSION['cars_csrf'] = bin2hex(random_bytes(32));
}

// Nhận mã xe từ đường dẫn
// Ví dụ: edit.php?id=2
$car_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Kiểm tra mã xe
if ($car_id === false || $car_id === null) {
    header('Location: index.php?error=notfound');
    exit;
}

$error = '';
$brands = [];
$car = null;

// Lấy danh sách hãng xe và thông tin xe cần sửa
try {

    // Lấy danh sách hãng
    $stmt = $pdo->query(
        'SELECT brand_id, brand_name
         FROM brands
         ORDER BY brand_name'
    );

    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tìm xe theo mã car_id
    $stmt = $pdo->prepare(
        'SELECT *
         FROM cars
         WHERE car_id = ?
         LIMIT 1'
    );

    $stmt->execute([$car_id]);

    $car = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {

    error_log($ex->getMessage());

    $error = 'Không thể tải dữ liệu từ MySQL.';
}

// Nếu không tìm thấy xe
if ($car === false) {
    header('Location: index.php?error=notfound');
    exit;
}

// Chuẩn bị dữ liệu hiển thị lên biểu mẫu
$data = [
    'brand_id' => (string)($car['brand_id'] ?? ''),
    'car_name' => (string)($car['car_name'] ?? ''),
    'model_year' => (string)($car['model_year'] ?? ''),
    'color' => (string)($car['color'] ?? ''),
    'price' => (string)($car['price'] ?? ''),
    'stock_quantity' => (string)($car['stock_quantity'] ?? '0'),
    'description' => (string)($car['description'] ?? ''),
    'image_url' => (string)($car['image_url'] ?? '')
];

// Xử lý khi người dùng nhấn nút Lưu thay đổi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $car !== null) {

    // Nhận dữ liệu từ biểu mẫu
    foreach ($data as $key => $oldValue) {

        $input = $_POST[$key] ?? null;

        $data[$key] = is_string($input)
            ? trim($input)
            : '';
    }

    // Nhận mã bảo vệ
    $token = $_POST['csrf_token'] ?? null;

    // Kiểm tra hãng xe
    $brand_id = filter_var(
        $data['brand_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    // Kiểm tra năm sản xuất
    $model_year = filter_var(
        $data['model_year'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1886,
                'max_range' => 2100
            ]
        ]
    );

    // Kiểm tra số lượng tồn kho
    $stock_quantity = filter_var(
        $data['stock_quantity'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 0,
                'max_range' => 2147483647
            ]
        ]
    );

    // Danh sách mã hãng xe hợp lệ
    $validBrandIds = array_map(
        'intval',
        array_column($brands, 'brand_id')
    );

    // Kiểm tra dữ liệu nhập vào
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['cars_csrf'], $token)
    ) {

        $error = 'Biểu mẫu không hợp lệ. Hãy tải lại trang.';

    } elseif (
        $brand_id === false ||
        !in_array($brand_id, $validBrandIds, true)
    ) {

        $error = 'Vui lòng chọn hãng xe hợp lệ.';

    } elseif (
        $data['car_name'] === '' ||
        textLength($data['car_name']) > 150
    ) {

        $error = 'Tên ô tô phải có từ 1 đến 150 ký tự.';

    } elseif ($model_year === false) {

        $error = 'Năm sản xuất không hợp lệ.';

    } elseif (textLength($data['color']) > 50) {

        $error = 'Màu sắc không được quá 50 ký tự.';

    } elseif (
        !preg_match(
            '/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/',
            $data['price']
        ) ||
        (float)$data['price'] <= 0
    ) {

        $error = 'Giá bán phải lớn hơn 0 và có tối đa 2 chữ số thập phân.';

    } elseif ($stock_quantity === false) {

        $error = 'Số lượng tồn kho phải là số nguyên từ 0 trở lên.';

    } elseif (textLength($data['image_url']) > 255) {

        $error = 'Đường dẫn ảnh không được quá 255 ký tự.';

    } else {

        try {

            // Cập nhật dữ liệu của ô tô
            $sql = 'UPDATE cars
                    SET
                        brand_id = ?,
                        car_name = ?,
                        model_year = ?,
                        color = ?,
                        price = ?,
                        stock_quantity = ?,
                        description = ?,
                        image_url = ?
                    WHERE car_id = ?';

            // Chuẩn bị câu lệnh SQL
            $stmt = $pdo->prepare($sql);

            // Thực hiện cập nhật
            $stmt->execute([
                $brand_id,
                $data['car_name'],
                $model_year,
                $data['color'] === '' ? null : $data['color'],
                $data['price'],
                $stock_quantity,
                $data['description'] === '' ? null : $data['description'],
                $data['image_url'] === '' ? null : $data['image_url'],
                $car_id
            ]);

            // Quay về trang danh sách
            header('Location: index.php?updated=1');
            exit;

        } catch (PDOException $ex) {

            error_log($ex->getMessage());

            $error = 'Không thể cập nhật ô tô vào MySQL.';
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

    <title>Sửa thông tin ô tô | Showroom</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<main class="container narrow">

    <!-- Tiêu đề trang -->
    <div class="topbar">

        <div>

            <div class="eyebrow">
                SHOWROOM MANAGEMENT
            </div>

            <h1>Sửa thông tin ô tô</h1>

            <p class="muted">
                Đang chỉnh sửa xe có mã:
                #<?= (int)$car_id ?>
            </p>

        </div>

        <a
            class="button button-light"
            href="index.php"
        >
            ← Danh sách ô tô
        </a>

    </div>

    <!-- Khung biểu mẫu -->
    <section class="panel form-panel">

        <?php if ($error !== ''): ?>

            <div class="notice danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($car === null): ?>

            <p>Không thể tải thông tin ô tô.</p>

            <a href="index.php" class="button button-light">
                Quay lại
            </a>

        <?php else: ?>

        <!-- Biểu mẫu sửa xe -->
        <form
            method="POST"
            action="edit.php?id=<?= (int)$car_id ?>"
            class="car-form"
        >

            <!-- Mã bảo vệ -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['cars_csrf']) ?>"
            >

            <!-- Chọn hãng -->
            <label for="brand">
                Hãng xe <span class="required">*</span>
            </label>

            <select
                id="brand"
                name="brand_id"
                required
            >

                <option value="">
                    -- Chọn hãng xe --
                </option>

                <?php foreach ($brands as $brand): ?>

                    <option
                        value="<?= (int)$brand['brand_id'] ?>"
                        <?= (string)$brand['brand_id'] === $data['brand_id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($brand['brand_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <!-- Tên ô tô -->
            <label for="car_name">
                Tên ô tô <span class="required">*</span>
            </label>

            <input
                id="car_name"
                type="text"
                name="car_name"
                maxlength="150"
                value="<?= e($data['car_name']) ?>"
                required
            >


            <div class="form-grid">

                <!-- Năm sản xuất -->
                <div>

                    <label for="model_year">
                        Năm sản xuất
                        <span class="required">*</span>
                    </label>

                    <input
                        id="model_year"
                        type="number"
                        name="model_year"
                        min="1886"
                        max="2100"
                        value="<?= e($data['model_year']) ?>"
                        required
                    >

                </div>


                <!-- Màu sắc -->
                <div>

                    <label for="color">
                        Màu sắc
                    </label>

                    <input
                        id="color"
                        type="text"
                        name="color"
                        maxlength="50"
                        value="<?= e($data['color']) ?>"
                    >

                </div>


                <!-- Giá bán -->
                <div>

                    <label for="price">
                        Giá bán (VNĐ)
                        <span class="required">*</span>
                    </label>

                    <input
                        id="price"
                        type="number"
                        name="price"
                        min="0.01"
                        max="9999999999999.99"
                        step="0.01"
                        value="<?= e($data['price']) ?>"
                        required
                    >

                </div>


                <!-- Tồn kho -->
                <div>

                    <label for="stock_quantity">
                        Số lượng tồn kho
                        <span class="required">*</span>
                    </label>

                    <input
                        id="stock_quantity"
                        type="number"
                        name="stock_quantity"
                        min="0"
                        max="2147483647"
                        step="1"
                        value="<?= e($data['stock_quantity']) ?>"
                        required
                    >

                </div>

            </div>


            <!-- Mô tả -->
            <label for="description">
                Mô tả
            </label>

            <textarea
                id="description"
                name="description"
                rows="4"
            ><?= e($data['description']) ?></textarea>


            <!-- Đường dẫn ảnh -->
            <label for="image_url">
                Đường dẫn ảnh
            </label>

            <input
                id="image_url"
                type="text"
                name="image_url"
                maxlength="255"
                value="<?= e($data['image_url']) ?>"
                placeholder="https://... hoặc đường dẫn ảnh"
            >


            <!-- Nút thao tác -->
            <div class="actions form-actions">

                <button
                    type="submit"
                    class="button button-primary"
                >
                    Lưu thay đổi
                </button>

                <a
                    class="button button-light"
                    href="index.php"
                >
                    Hủy
                </a>

            </div>

        </form>

        <?php endif; ?>

    </section>

</main>

</body>
</html>