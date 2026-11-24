<?php
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Tạo mã bảo vệ biểu mẫu
if (empty($_SESSION['staff_csrf'])) {
    $_SESSION['staff_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$fullName = '';
$username = '';
$phone = '';

// Xử lý thêm nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    $nameLength = function_exists('mb_strlen')
        ? mb_strlen($fullName, 'UTF-8')
        : strlen($fullName);

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['staff_csrf'], $token)
    ) {
        $error = 'Yêu cầu không hợp lệ.';

    } elseif ($fullName === '' || $nameLength > 150) {

        $error = 'Họ và tên không hợp lệ.';

    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {

        $error = 'Tên đăng nhập phải có 3–50 ký tự chữ, số hoặc dấu gạch dưới.';

    } elseif (strlen($phone) > 20) {

        $error = 'Số điện thoại quá dài.';

    } elseif (strlen($password) < 10) {

        $error = 'Mật khẩu phải có ít nhất 10 ký tự.';

    } elseif ($password !== $confirmPassword) {

        $error = 'Hai mật khẩu không giống nhau.';

    } else {

        try {

            // Kiểm tra tên đăng nhập đã tồn tại chưa
            $stmt = $pdo->prepare(
                "SELECT user_id
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);

            if ($stmt->fetch()) {

                $error = 'Tên đăng nhập đã tồn tại.';

            } else {

                // Mã hóa mật khẩu
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Tạo tài khoản với quyền staff
                $sql = "INSERT INTO users
                        (full_name, username, password_hash, role, phone)
                        VALUES (?, ?, ?, 'staff', ?)";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    $fullName,
                    $username,
                    $passwordHash,
                    $phone !== '' ? $phone : null
                ]);

                header('Location: index.php?created=1', true, 303);
                exit;
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $error = 'Không thể tạo tài khoản nhân viên.';
        }
    }
}

// Lấy danh sách tài khoản
$stmt = $pdo->query(
    "SELECT
        user_id,
        full_name,
        username,
        phone,
        role,
        created_at
     FROM users
     ORDER BY user_id DESC"
);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý tài khoản | Showroom</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #172033;
            font-family: Arial, sans-serif;
            padding: 35px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .brand {
            color: #2563eb;
            letter-spacing: 3px;
            font-weight: bold;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 35px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #64748b;
        }

        .card {
            background: white;
            padding: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            margin-bottom: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 10px;
        }

        input {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px 22px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: white;
            color: #172033;
            border: 1px solid #cbd5e1;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .badge {
            padding: 7px 12px;
            border-radius: 15px;
            display: inline-block;
            font-size: 13px;
            font-weight: bold;
        }

        .admin {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .staff {
            background: #dcfce7;
            color: #166534;
        }

        @media (max-width: 650px) {
            body {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="brand">SHOWROOM MANAGEMENT</div>

    <div class="header">
        <div>
            <h1>Quản lý tài khoản</h1>
            <p class="subtitle">
                Tạo tài khoản nhân viên và xem danh sách người dùng.
            </p>
        </div>

        <a href="../index.php" class="btn btn-secondary">
            ← Trang chủ
        </a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert success">
            Tạo tài khoản nhân viên thành công!
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>Thêm tài khoản nhân viên</h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['staff_csrf']) ?>"
            >

            <div class="form-grid">

                <div>
                    <label>Họ và tên *</label>

                    <input
                        type="text"
                        name="full_name"
                        maxlength="150"
                        value="<?= h($fullName) ?>"
                        required
                    >
                </div>

                <div>
                    <label>Tên đăng nhập *</label>

                    <input
                        type="text"
                        name="username"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]{3,50}"
                        value="<?= h($username) ?>"
                        required
                    >
                </div>

                <div>
                    <label>Số điện thoại</label>

                    <input
                        type="text"
                        name="phone"
                        maxlength="20"
                        value="<?= h($phone) ?>"
                    >
                </div>

                <div>
                    <label>Vai trò</label>

                    <input
                        type="text"
                        value="Nhân viên (Staff)"
                        disabled
                    >
                </div>

                <div>
                    <label>Mật khẩu *</label>

                    <input
                        type="password"
                        name="password"
                        minlength="10"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div>
                    <label>Nhập lại mật khẩu *</label>

                    <input
                        type="password"
                        name="confirm_password"
                        minlength="10"
                        autocomplete="new-password"
                        required
                    >
                </div>

            </div>

            <p class="subtitle">
                Mật khẩu cần ít nhất 10 ký tự.
                Tài khoản mới sẽ tự động có quyền Nhân viên.
            </p>

            <button class="btn" type="submit">
                + Tạo tài khoản nhân viên
            </button>

        </form>
    </div>

    <div class="card">

        <h2>Danh sách tài khoản</h2>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Họ và tên</th>
                        <th>Tên đăng nhập</th>
                        <th>Số điện thoại</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($users as $user): ?>

                    <tr>
                        <td>
                            #<?= (int)$user['user_id'] ?>
                        </td>

                        <td>
                            <?= h($user['full_name']) ?>
                        </td>

                        <td>
                            <?= h($user['username']) ?>
                        </td>

                        <td>
                            <?= h($user['phone'] ?: '—') ?>
                        </td>

                        <td>
                            <?php if ($user['role'] === 'admin'): ?>

                                <span class="badge admin">
                                    Admin
                                </span>

                            <?php else: ?>

                                <span class="badge staff">
                                    Nhân viên
                                </span>

                            <?php endif; ?>
                        </td>

                        <td>
                            <?= h($user['created_at'] ?? '—') ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6">
                            Chưa có tài khoản nào.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>