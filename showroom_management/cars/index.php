<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Hàm hiển thị dữ liệu an toàn.
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Kiểm tra đường dẫn ảnh.
function safeImage($value): string {
    $url = trim((string)$value);

    if ($url === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }

    if (preg_match('~^(?!//)(?:\./|\.\./|/)?[a-zA-Z0-9_./-]+$~', $url)) {
        return $url;
    }

    return '';
}

// Tạo mã bảo vệ chức năng xóa.
if (empty($_SESSION['cars_csrf'])) {
    $_SESSION['cars_csrf'] = bin2hex(random_bytes(32));
}

// Nhận từ khóa tìm kiếm.
$search = trim(
    is_string($_GET['q'] ?? null) ? $_GET['q'] : ''
);

$cars = [];
$error = '';

try {
    // Lấy thông tin xe và tên hãng.
    $sql = 'SELECT
                c.car_id,
                c.brand_id,
                c.car_name,
                c.model_year,
                c.color,
                c.price,
                c.stock_quantity,
                c.description,
                c.image_url,
                b.brand_name
            FROM cars AS c
            INNER JOIN brands AS b
                ON c.brand_id = b.brand_id';

    // Thêm điều kiện tìm kiếm nếu có.
    if ($search !== '') {
        $sql .= ' WHERE c.car_name LIKE ?
                  OR b.brand_name LIKE ?
                  OR c.color LIKE ?';
    }

    $sql .= ' ORDER BY c.car_id DESC';

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {
        $term = '%' . $search . '%';

        $stmt->execute([
            $term,
            $term,
            $term
        ]);
    } else {
        $stmt->execute();
    }

    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log($ex->getMessage());

    $error = 'Không thể tải danh sách ô tô. Hãy kiểm tra MySQL.';
}

// Hiển thị thông báo khi thêm, sửa, xóa thành công.
$notice = '';

if (isset($_GET['added'])) {
    $notice = 'Thêm ô tô thành công!';
} elseif (isset($_GET['updated'])) {
    $notice = 'Cập nhật ô tô thành công!';
} elseif (isset($_GET['deleted'])) {
    $notice = 'Xóa ô tô thành công!';
} elseif (($_GET['error'] ?? '') === 'linked') {
    $error = 'Không thể xóa vì xe đã có trong đơn hàng.';
} elseif (($_GET['error'] ?? '') === 'notfound') {
    $error = 'Không tìm thấy xe cần xóa.';
} elseif (($_GET['error'] ?? '') === 'delete') {
    $error = 'Xóa ô tô thất bại.';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Quản lý ô tô | Showroom</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<main class="container">

    <!-- Tiêu đề trang -->
    <div class="topbar">

        <div>
            <div class="eyebrow">
                SHOWROOM MANAGEMENT
            </div>

            <h1>Quản lý ô tô</h1>

            <p class="muted">
                Theo dõi các mẫu xe, giá bán và số lượng tồn kho.
            </p>
        </div>

        <div class="actions">

            <a class="button button-light"
               href="../brands/index.php">
                Quản lý hãng
            </a>

            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a class="button button-primary"
               href="add.php">
                + Thêm ô tô
                
            </a>
            <?php endif; ?>

        </div>
    </div>

    <!-- Thông báo thành công -->
    <?php if ($notice !== ''): ?>

        <div class="notice success">
            <?= e($notice) ?>
        </div>

    <?php endif; ?>

    <!-- Thông báo lỗi -->
    <?php if ($error !== ''): ?>

        <div class="notice danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <section class="panel">

        <div class="panel-head">

            <div>
                <h2>Danh sách xe</h2>

                <p class="muted">
                    <?= count($cars) ?> kết quả
                </p>
            </div>

            <!-- Tìm kiếm ô tô -->
            <form method="GET"
                  action="index.php"
                  class="search">

                <input
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Tìm tên xe, hãng, màu sắc..."
                >

                <button
                    class="button button-primary"
                    type="submit">
                    Tìm kiếm
                </button>

                <?php if ($search !== ''): ?>

                    <a class="button button-light"
                       href="index.php">
                        Xóa lọc
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- Bảng danh sách ô tô -->
        <div class="table-scroll">

            <table>

                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Mã xe</th>
                        <th>Tên xe / Hãng</th>
                        <th>Năm / Màu</th>
                        <th>Giá bán</th>
                        <th>Tồn kho</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$cars): ?>

                    <tr>
                        <td colspan="7" class="empty">
                            Chưa có ô tô phù hợp.

                            <a href="add.php">
                                Thêm xe mới
                            </a>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($cars as $car): ?>

                        <tr>

                            <!-- Ảnh ô tô -->
                            <td>

                                <?php
                                $img = safeImage($car['image_url']);
                                ?>

                                <?php if ($img !== ''): ?>

                                    <img
                                        class="car-image"
                                        src="<?= e($img) ?>"
                                        alt="Ảnh <?= e($car['car_name']) ?>"
                                    >

                                <?php else: ?>

                                    <div class="image-placeholder">
                                        Chưa có ảnh
                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- Mã xe -->
                            <td>
                                #<?= (int)$car['car_id'] ?>
                            </td>


                            <!-- Tên xe và hãng -->
                            <td>

                                <strong>
                                    <?= e($car['car_name']) ?>
                                </strong>

                                <div class="muted small">
                                    <?= e($car['brand_name']) ?>
                                </div>

                            </td>


                            <!-- Năm sản xuất và màu -->
                            <td>

                                <?= (int)$car['model_year'] ?>

                                <div class="muted small">
                                    <?= e($car['color'] ?: 'Chưa nhập màu') ?>
                                </div>

                            </td>


                            <!-- Giá bán -->
                            <td class="price">

                                <?= number_format(
                                    (float)$car['price'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ

                            </td>


                            <!-- Số lượng tồn kho -->
                            <td>

                                <span class="stock">
                                    <?= (int)$car['stock_quantity'] ?>
                                </span>

                            </td>


                            <!-- Nút sửa và xóa -->
                            <td>

                                <div class="row-actions">
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <a
                                        class="button button-light button-small"
                                        href="edit.php?id=<?= (int)$car['car_id'] ?>"
                                    >
                                        Sửa
                                        
                                    </a>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        onsubmit="return confirm('Bạn chắc chắn muốn xóa xe này?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="car_id"
                                            value="<?= (int)$car['car_id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['cars_csrf']) ?>"
                                        >

                                        <button
                                            class="button button-danger button-small"
                                            type="submit"
                                        >
                                            Xóa
                                        </button>

                                    </form>
                                    <?php endif; ?>
                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <p class="footer-note">
        Dữ liệu được lấy từ MySQL:
        showroom_db → cars
    </p>
