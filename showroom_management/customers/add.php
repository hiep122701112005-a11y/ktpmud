<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
// Kết nối cơ sở dữ liệu
require_once __DIR__ . '/../config/database.php';

// Hàm hiển thị dữ liệu an toàn
function e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Hàm kiểm tra độ dài chuỗi
function textLength(string $value): int {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

// Tạo mã bảo vệ biểu mẫu
if (empty($_SESSION['customers_csrf'])) {
    $_SESSION['customers_csrf'] = bin2hex(random_bytes(32));
}

// Biến thông báo lỗi
$error = '';

// Dữ liệu khách hàng
$data = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'address' => ''
];

// Xử lý khi nhấn nút thêm khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lấy dữ liệu từ biểu mẫu
    foreach ($data as $key => $value) {

        $input = $_POST[$key] ?? null;

        $data[$key] = is_string($input)
            ? trim($input)
            : '';
    }

    // Gán dữ liệu vào biến
    $full_name = $data['full_name'];
    $phone = $data['phone'];
    $email = $data['email'];
    $address = $data['address'];

    $token = $_POST['csrf_token'] ?? null;

    // Kiểm tra dữ liệu
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['customers_csrf'], $token)
    ) {

        $error = 'Biểu mẫu không hợp lệ. Hãy tải lại trang.';

    } elseif (
        $full_name === '' ||
        textLength($full_name) > 150
    ) {

        $error = 'Vui lòng nhập họ tên hợp lệ (tối đa 150 ký tự).';

    } elseif (
        strlen($phone) > 20 ||
        !preg_match('/^[0-9+().\s-]+$/', $phone) ||
        strlen(preg_replace('/\D/', '', $phone)) < 8
    ) {

        $error = 'Số điện thoại không hợp lệ.';

    } elseif (
        $email !== '' &&
        (
            strlen($email) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        )
    ) {

        $error = 'Địa chỉ email không hợp lệ.';

    } else {

        try {

            // Câu lệnh thêm khách hàng vào MySQL
            $sql = "INSERT INTO customers
                    (full_name, phone, email, address)
                    VALUES
                    (:full_name, :phone, :email, :address)";

            // Chuẩn bị câu lệnh
            $stmt = $pdo->prepare($sql);

            // Thực hiện thêm khách hàng
            $stmt->execute([
                ':full_name' => $full_name,
                ':phone' => $phone,
                ':email' => $email !== '' ? $email : null,
                ':address' => $address !== '' ? $address : null
            ]);

            // Chuyển về danh sách nếu thành công
            header('Location: index.php?added=1');
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $error = 'Không thể thêm khách hàng vào MySQL.';
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

    <title>Thêm khách hàng | Showroom</title>

    <!-- Dùng lại CSS của phần ô tô -->
    <link rel="stylesheet" href="../cars/style.css">
</head>

<body>

<main class="container narrow">

    <!-- Tiêu đề -->
    <div class="topbar">

        <div>

            <div class="eyebrow">
                SHOWROOM MANAGEMENT
            </div>

            <h1>Thêm khách hàng mới</h1>

            <p class="muted">
                Nhập thông tin khách hàng vào hệ thống.
            </p>

        </div>

        <a
            href="index.php"
            class="button button-light"
        >
            ← Danh sách khách hàng
        </a>

    </div>

    <!-- Biểu mẫu -->
    <section class="panel form-panel">

        <!-- Hiển thị lỗi -->
        <?php if ($error !== ''): ?>

            <div class="notice danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="add.php"
            class="car-form"
        >

            <!-- Mã bảo vệ -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['customers_csrf']) ?>"
            >

            <!-- Họ tên -->
            <label for="full_name">
                Họ và tên khách hàng
                <span class="required">*</span>
            </label>

            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="150"
                value="<?= e($data['full_name']) ?>"
                placeholder="Nhập họ và tên"
                required
            >

            <!-- Số điện thoại -->
            <label for="phone">
                Số điện thoại
                <span class="required">*</span>
            </label>

            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="20"
                value="<?= e($data['phone']) ?>"
                placeholder="Ví dụ: 0912345678"
                required
            >

            <!-- Email -->
            <label for="email">
                Email
            </label>

            <input
                type="email"
                id="email"
                name="email"
                maxlength="100"
                value="<?= e($data['email']) ?>"
                placeholder="Ví dụ: khachhang@gmail.com"
            >

            <!-- Địa chỉ -->
            <label for="address">
                Địa chỉ
            </label>

            <textarea
                id="address"
                name="address"
                rows="4"
                placeholder="Nhập địa chỉ khách hàng"
            ><?= e($data['address']) ?></textarea>

            <!-- Các nút thao tác -->
            <div class="actions form-actions">

                <button
                    type="submit"
                    class="button button-primary"
                >
                    Thêm khách hàng
                </button>

                <a
                    href="index.php"
                    class="button button-light"
                >
                    Hủy
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>