<?php
// Bắt đầu phiên làm việc
session_start();

// Xóa tất cả thông tin đăng nhập trong SESSION
$_SESSION = [];

// Xóa cookie phiên đăng nhập
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hủy phiên đăng nhập trên máy chủ
session_destroy();

// Quay về trang đăng nhập
header("Location: login.php?logout=1");
exit;
?>