<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
// Nhận từ khóa tìm kiếm
$keyword = trim($_GET['q'] ?? '');

// Lấy danh sách khách hàng
if ($keyword !== '') {
    $sql = "SELECT * FROM customers
            WHERE full_name LIKE :name
            OR phone LIKE :phone
            OR email LIKE :email
            ORDER BY customer_id DESC";

    $stmt = $pdo->prepare($sql);

    $search = "%{$keyword}%";

    $stmt->execute([
        ':name' => $search,
        ':phone' => $search,
        ':email' => $search
    ]);
} else {
    $stmt = $pdo->query(
        "SELECT * FROM customers ORDER BY customer_id DESC"
    );
}

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($customers);

function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý khách hàng | Showroom</title>

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
            max-width: 1250px;
            margin: auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            gap: 20px;
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
        }

        .btn {
            display: inline-block;
            padding: 13px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-size: 15px;
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

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-edit {
            background: white;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .panel {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .panel-head {
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        h2 {
            margin: 0 0 12px;
        }

        .count {
            color: #64748b;
        }

        .search {
            display: flex;
            gap: 12px;
        }

        .search input {
            width: 330px;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 11px;
            font-size: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
        }

        th, td {
            padding: 20px 15px;
            border-top: 1px solid #e2e8f0;
        }

        tr:hover {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .empty {
            text-align: center;
            padding: 45px;
            color: #64748b;
        }

        .footer {
            margin-top: 25px;
            color: #64748b;
        }

        @media(max-width: 850px) {
            body {
                padding: 15px;
            }

            .panel {
                overflow-x: auto;
            }

            table {
                min-width: 850px;
            }

            .search input {
                width: 100%;
            }

            .topbar {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="topbar">
        <div>
            <div class="brand">SHOWROOM MANAGEMENT</div>

            <h1>Quản lý khách hàng</h1>

            <p class="subtitle">
                Theo dõi thông tin và danh sách khách hàng.
            </p>
        </div>

        <div>
            <a href="../index.php" class="btn btn-white">
                Trang chủ
            </a>

            <a href="add.php" class="btn btn-primary">
                + Thêm khách hàng
            </a>
        </div>
    </div>

    <div class="panel">

        <div class="panel-head">

            <div>
                <h2>Danh sách khách hàng</h2>

                <div class="count">
                    <?= $total ?> kết quả
                </div>
            </div>

            <form method="GET" class="search">

                <input
                    type="text"
                    name="q"
                    placeholder="Tìm tên, số điện thoại, email..."
                    value="<?= h($keyword) ?>"
                >

                <button class="btn btn-primary" type="submit">
                    Tìm kiếm
                </button>

            </form>

        </div>

        <table>

            <thead>
                <tr>
                    <th>Mã KH</th>
                    <th>Họ và tên</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Địa chỉ</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($total > 0): ?>

                <?php foreach ($customers as $customer): ?>

                    <tr>

                        <td>
                            #<?= (int)$customer['customer_id'] ?>
                        </td>

                        <td>
                            <strong>
                                <?= h($customer['full_name']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= h($customer['phone']) ?>
                        </td>

                        <td>
                            <?= h($customer['email']) ?>
                        </td>

                        <td>
                            <?= h($customer['address']) ?>
                        </td>

                        <td>
                            <div class="actions">

                                <a
                                    class="btn btn-edit"
                                    href="edit.php?id=<?= (int)$customer['customer_id'] ?>"
                                >
                                    Sửa
                                </a>

                                <a
                                    class="btn btn-danger"
                                    href="delete.php?id=<?= (int)$customer['customer_id'] ?>"
                                    onclick="return confirm('Bạn có chắc chắn muốn xóa khách hàng này không?')"
                                >
                                    Xóa
                                </a>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        Chưa có khách hàng nào trong hệ thống.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <div class="footer">
        Dữ liệu được lấy từ MySQL:
        showroom_db → customers
    </div>

</div>

</body>
</html>