<?php
require_once __DIR__ . '/../config/auth.php';
requireAdmin();
require_once __DIR__ . "/../config/database.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Mã hãng xe không hợp lệ.");
}

$sql = "SELECT * FROM brands WHERE brand_id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute(["id" => $id]);

$brand = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$brand) {
    exit("Không tìm thấy hãng xe.");
}
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["brand_name"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($name === "") {
        $message = "Vui lòng nhập tên hãng xe.";
    } else {
        try {
            $sql = "UPDATE brands
                    SET brand_name = :name,
                        description = :description
                    WHERE brand_id = :id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                "name" => $name,
                "description" => $description,
                "id" => $id
            ]);

            header("Location: index.php?updated=1");
            exit;

        } catch (PDOException $e) {
            $message = $e->getCode() === "23000"
                ? "Tên hãng xe đã tồn tại."
                : "Không thể lưu thay đổi.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa hãng xe</title>
</head>
<body>

<h1>Sửa thông tin hãng xe</h1>

<form method="POST">
    <label>Tên hãng xe:</label>

    <input type="text" name="brand_name"
           value="<?= htmlspecialchars($brand["brand_name"], ENT_QUOTES, "UTF-8") ?>">

    <br><br>

    <label>Mô tả:</label>

    <input type="text" name="description"
           value="<?= htmlspecialchars($brand["description"] ?? "", ENT_QUOTES, "UTF-8") ?>">

    <br><br>

    <button type="submit">Lưu thay đổi</button>
</form>

</body>
</html>