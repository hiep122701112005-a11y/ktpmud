<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Kiểm tra mã khách hàng
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    exit("Mã khách hàng không hợp lệ.");
}

// Lấy thông tin khách hàng hiện tại
$stmt = $pdo->prepare(
    "SELECT * FROM customers WHERE customer_id = :id"
);
$stmt->execute([':id' => $id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    exit("Không tìm thấy khách hàng.");
}

$error = "";

// Xử lý khi nhấn nút cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($full_name === '' || $phone === '') {
        $error = "Vui lòng nhập đầy đủ họ tên và số điện thoại.";
    } elseif (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = "Địa chỉ email không hợp lệ.";
    } else {

        try {
            $sql = "UPDATE customers
                    SET full_name = :full_name,
                        phone = :phone,
                        email = :email,
                        address = :address
                    WHERE customer_id = :id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':full_name' => $full_name,
                ':phone' => $phone,
                ':email' => $email !== '' ? $email : null,
                ':address' => $address !== '' ? $address : null,
                ':id' => $id
            ]);

            header("Location: index.php?updated=1");
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Không thể cập nhật thông tin khách hàng.";
        }
    }

    // Giữ lại dữ liệu đã nhập nếu có lỗi
    $customer['full_name'] = $full_name;
    $customer['phone'] = $phone;
    $customer['email'] = $email;
    $customer['address'] = $address;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sửa khách hàng | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .container {
            max-width: 850px;
            margin: auto;
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 3px;
        }

        h1 {
            font-size: 38px;
            margin: 18px 0;
        }

        .subtitle {
            color: #64748b;
            font-size: 18px;
            margin-bottom: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 13px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-white {
            background: white;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .panel {
            background: white;
            padding: 35px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .required {
            color: red;
        }

        input, textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 11px;
            font-size: 16px;
            font-family: Arial, sans-serif;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        @media(max-width: 600px) {
            body {
                padding: 20px;
            }

            .panel {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <div class="brand">SHOWROOM MANAGEMENT</div>

            <h1>Chỉnh sửa khách hàng</h1>

            <p class="subtitle">
                Cập nhật thông tin khách hàng trong hệ thống.
            </p>
        </div>

        <a href="index.php" class="btn btn-white">
            ← Danh sách khách hàng
        </a>
    </div>

    <div class="panel">

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="edit.php?id=<?= (int)$id ?>">

            <div class="form-group">
                <label>
                    Họ và tên khách hàng
                    <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="full_name"
                    value="<?= h($customer['full_name']) ?>"
                    placeholder="Nhập họ và tên"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label>
                    Số điện thoại
                    <span class="required">*</span>
                </label>

                <input
                    type="tel"
                    name="phone"
                    value="<?= h($customer['phone']) ?>"
                    placeholder="Ví dụ: 0912345678"
                    maxlength="20"
                    required
                >
            </div>

            <div class="form-group">
                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    value="<?= h($customer['email']) ?>"
                    placeholder="Ví dụ: khachhang@gmail.com"
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label>Địa chỉ</label>

                <textarea
                    name="address"
                    placeholder="Nhập địa chỉ khách hàng"
                ><?= h($customer['address']) ?></textarea>
            </div>

            <div class="actions">

                <button type="submit" class="btn btn-primary">
                    Lưu thay đổi
                </button>

                <a href="index.php" class="btn btn-white">
                    Hủy
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>