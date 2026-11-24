<?php
session_start();

// Kết nối cơ sở dữ liệu
require_once __DIR__ . '/config/database.php';

// Hàm hiển thị dữ liệu an toàn
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Nếu người dùng đã đăng nhập thì chuyển về Dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Tạo mã bảo vệ biểu mẫu đăng nhập
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$username = '';

// Xử lý khi người dùng nhấn Đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Kiểm tra mã bảo vệ
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['login_csrf'], $token)
    ) {
        $error = 'Phiên làm việc không hợp lệ. Hãy tải lại trang.';

    } elseif (
        !is_string($username) ||
        !is_string($password) ||
        $username === '' ||
        $password === ''
    ) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';

    } else {

        try {

            // Tìm tài khoản trong bảng users
            $sql = "
                SELECT
                    user_id,
                    full_name,
                    username,
                    password_hash,
                    role
                FROM users
                WHERE username = ?
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([$username]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra tài khoản, mật khẩu và vai trò
            if (
                $user &&
                password_verify(
                    $password,
                    $user['password_hash']
                ) &&
                in_array($user['role'], ['admin', 'staff'], true)
            ) {

                // Tạo phiên đăng nhập mới
                session_regenerate_id(true);

                // Lưu thông tin người dùng vào SESSION
                $_SESSION['user_id'] = (int)$user['user_id'];

                $_SESSION['username'] = $user['username'];

                $_SESSION['full_name'] = $user['full_name'];

                $_SESSION['role'] = $user['role'];

                $_SESSION['logged_in'] = true;

                // Xóa mã bảo vệ biểu mẫu đã dùng
                unset($_SESSION['login_csrf']);

                // Chuyển về trang chủ
                header('Location: index.php');
                exit;

            } else {

                $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';

            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $error = 'Không thể đăng nhập. Vui lòng thử lại.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Đăng nhập | Showroom Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            color: #0f172a;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 25px;
        }

        .login-card {
            width: 100%;
            max-width: 470px;
            background: white;

            border: 1px solid #e2e8f0;
            border-radius: 22px;

            padding: 42px;

            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.06);
        }

        .brand {
            color: #2563eb;
            font-weight: bold;
            letter-spacing: 3px;
            font-size: 15px;
            margin-bottom: 32px;
        }

        h1 {
            font-size: 32px;
            margin: 0 0 12px;
        }

        .subtitle {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        .form-group {
            margin-bottom: 23px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 11px;
        }

        input {
            width: 100%;
            padding: 15px;
            font-size: 16px;

            border: 1px solid #cbd5e1;
            border-radius: 12px;

            background: white;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px #dbeafe;
        }

        .btn-login {
            width: 100%;
            border: none;

            background: #2563eb;
            color: white;

            padding: 16px;
            border-radius: 12px;

            font-size: 17px;
            font-weight: bold;

            cursor: pointer;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: #1d4ed8;
        }

        .error {
            padding: 15px;
            background: #fee2e2;
            color: #991b1b;

            border-radius: 10px;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 520px) {
            .login-card {
                padding: 25px;
            }

            h1 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="brand">
        SHOWROOM MANAGEMENT
    </div>

    <h1>Đăng nhập hệ thống</h1>

    <p class="subtitle">
        Đăng nhập để quản lý hoạt động kinh doanh
        của showroom ô tô.
    </p>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= h($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="login.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= h($_SESSION['login_csrf']) ?>"
        >

        <div class="form-group">

            <label for="username">
                Tên đăng nhập
            </label>

            <input
                type="text"
                id="username"
                name="username"

                placeholder="Nhập tên đăng nhập"
                value="<?= h($username) ?>"

                autocomplete="username"
                required
            >

        </div>

        <div class="form-group">

            <label for="password">
                Mật khẩu
            </label>

            <input
                type="password"
                id="password"
                name="password"

                placeholder="Nhập mật khẩu"

                autocomplete="current-password"
                required
            >

        </div>

        <button type="submit" class="btn-login">
            Đăng nhập
        </button>

    </form>

    <div class="footer">
        Hệ thống quản lý showroom ô tô
    </div>

</div>

</body>
</html>