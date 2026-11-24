<?php
// Bắt đầu SESSION nếu chưa được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra người dùng đã đăng nhập chưa
if (
    empty($_SESSION['logged_in']) ||
    empty($_SESSION['user_id'])
) {
    // Chưa đăng nhập thì quay về trang login
    header('Location: /showroom_management/login.php');
    exit;
}

// Hàm kiểm tra quyền Admin
function requireAdmin() {

    if (($_SESSION['role'] ?? '') !== 'admin') {

        http_response_code(403);

        exit('Bạn không có quyền truy cập chức năng này.');
    }
}