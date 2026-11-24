<?php
session_start();
require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
require_once __DIR__ . "/../config/database.php";

$message = "";

// Nhận dữ liệu khi nhấn nút Thêm hãng xe
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireAdmin();
    $name = trim($_POST["brand_name"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($name === "") {
        $message = "Vui lòng nhập tên hãng xe.";
    } else {
        try {
            // Thêm dữ liệu vào bảng brands
            $sql = "INSERT INTO brands (brand_name, description)
                    VALUES (:name, :description)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                "name" => $name,
                "description" => $description
            ]);

            header("Location: index.php?added=1");
            exit;
        } catch (PDOException $e) {
            $message = $e->getCode() === "23000"
                ? "Hãng xe đã tồn tại."
                : "Không thể thêm hãng xe.";
        }
    }
}

// Đọc danh sách hãng xe
$sql = "SELECT brand_id, brand_name, description
        FROM brands
        ORDER BY brand_id ASC";

$stmt = $pdo->query($sql);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý hãng xe</title>
</head>
<body>

    <h1>QUẢN LÝ HÃNG XE</h1>

    <h2>Thêm hãng xe mới</h2>

    <?php if (isset($_GET["added"])): ?>
        <p>Thêm hãng xe thành công!</p>
    <?php endif; ?>

    <?php if ($message !== ""): ?>
        <p><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <form method="POST">
        <label>Tên hãng xe:</label>
        <input type="text" name="brand_name"
               maxlength="100" required>

        <br><br>

        <label>Mô tả:</label>
        <input type="text" name="description">

        <br><br>

        <button type="submit">Thêm hãng xe</button>
    </form>
<?php endif; ?>
    <hr>

    <h2>Danh sách hãng xe</h2>

    <?php foreach ($brands as $brand): ?>
        <p>
            <?= (int)$brand["brand_id"] ?>
            -
            <?= htmlspecialchars($brand["brand_name"], ENT_QUOTES, "UTF-8") ?>
            -
            <?= htmlspecialchars($brand["description"] ?? "", ENT_QUOTES, "UTF-8") ?>
        </p>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="edit.php?id=<?= (int)$brand["brand_id"] ?>">
    Sửa
</a>
<form method="POST" action="delete.php"
      onsubmit="return confirm('Bạn có chắc muốn xóa hãng xe này?');">

    <input type="hidden" name="brand_id"
           value="<?= (int)$brand["brand_id"] ?>">

    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">

    <button type="submit">Xóa</button>
</form>
<?php endif; ?>
    <?php endforeach; ?>

</body>
</html>