<?php
require_once __DIR__ . "/config/database.php";

// Lấy danh sách hãng xe từ MySQL
$sql ="SELECT brand_id, brand_name FROM brands ORDER BY brand_id ASC";  

$stmt = $pdo->query($sql);

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý showroom ô tô</title>
</head>
<body>

    <h1>HỆ THỐNG QUẢN LÝ SHOWROOM Ô TÔ</h1>

    <h2>Danh sách hãng xe</h2>

    <?php if (count($brands) == 0): ?>

        <p>Chưa có hãng xe nào.</p>

    <?php else: ?>

        <?php foreach ($brands as $brand): ?>

            <p>
                <?php echo (int)$brand["brand_id"]; ?>
                -
                <?php echo htmlspecialchars(
                    $brand["brand_name"],
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

</body>
</html>