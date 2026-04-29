<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$username = $_SESSION['user']['username'];

$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");


if (isset($_POST['add_sale'])) {

    $product_id = intval($_POST['product_id']);
    $qty = intval($_POST['quantity']);

    $prod = $conn->query("SELECT * FROM products WHERE id=$product_id")->fetch_assoc();

    if ($prod) {
        $product_name = $prod['name'];
        $price = $prod['price'];
        $stock = $prod['stock'];
        $image = $prod['image'];

        if ($qty > $stock) {
            echo "<script>alert('Not enough stock!');</script>";
        } else {

            $total = $qty * $price;

            $conn->query("INSERT INTO sales (product_name, product_image, quantity, total, status)
                          VALUES ('$product_name', '$image', $qty, $total, 'Completed')");

            $newStock = $stock - $qty;
            $conn->query("UPDATE products SET stock=$newStock WHERE id=$product_id");
        }
    }
}


if (isset($_POST['void_sale'])) {
    $id = intval($_POST['id']);
    $reason = $_POST['reason'];

    $conn->query("UPDATE sales SET status='VOID' WHERE id=$id");
    $conn->query("INSERT INTO sales_void (sale_id, reason) VALUES ($id, '$reason')");
}

if (isset($_POST['cancel_sale'])) {
    $id = intval($_POST['id']);

    
    $sale = $conn->query("SELECT * FROM sales WHERE id=$id AND status='Completed'")->fetch_assoc();

    if ($sale) {
        $conn->query("UPDATE sales SET status='Cancelled' WHERE id=$id");

        
        $conn->query("
            UPDATE products
            SET stock = stock + {$sale['quantity']}
            WHERE name = '{$sale['product_name']}'
        ");
    }
}


if (isset($_POST['reset_sales'])) {
    $conn->query("DELETE FROM sales");
    $conn->query("DELETE FROM sales_void");
}


$search = $_GET['search'] ?? '';
$date = $_GET['date'] ?? '';

$where = "1=1";

if (!empty($search)) {
    $where .= " AND s.product_name LIKE '%$search%'";
}

if (!empty($date)) {
    $where .= " AND DATE(s.created_at)='$date'";
}


$sales = $conn->query("
    SELECT s.*, p.image AS product_image, p.category AS product_category
    FROM sales s
    LEFT JOIN products p ON p.name = s.product_name
    WHERE $where
    ORDER BY s.created_at DESC
");


$totalSales = $conn->query("
    SELECT SUM(total) as total FROM sales WHERE status='Completed'
")->fetch_assoc()['total'] ?? 0;

$totalTransactions = $conn->query("
    SELECT COUNT(*) as c FROM sales WHERE status='Completed'
")->fetch_assoc()['c'] ?? 0;


$products = $conn->query("SELECT * FROM products");
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Sales & Inventory System</title>

<style>
body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f4; }
.title-bar { background: #222; color: white; padding: 15px; }
.container { display: flex; }
.sidebar { width: 220px; background: #111; color: white; min-height: 100vh; padding: 15px; }
.sidebar a { color: white; text-decoration: none; display: block; margin: 10px 0; }
.main { flex: 1; padding: 20px; }

.card { background: white; padding: 15px; display: inline-block; margin: 10px; }


table { width: 100%; border-collapse: collapse; background: white; }
th { background: #222; color: white; padding: 10px; }
td { padding: 10px; text-align: center; border-bottom: 1px solid #ddd; vertical-align: middle; }


.product-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
}

.product-thumb {
    width: 46px;
    height: 46px;
    border-radius: 8px;
    object-fit: cover;
    background: #eee;
    flex-shrink: 0;
}

.product-thumb-placeholder {
    width: 46px;
    height: 46px;
    border-radius: 8px;
    background: #d0d4e8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.product-name { font-weight: 600; font-size: 14px; color: #1e2235; line-height: 1.3; }
.product-category { font-size: 12px; color: #888; margin-top: 2px; }


.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.badge-completed {
    background: #e6f7ef;
    color: #1a7f4b;
}
.badge-completed::before { background: #1a7f4b; }

.badge-void, .badge-cancelled {
    background: #fff0ef;
    color: #c0392b;
}
.badge-void::before, .badge-cancelled::before { background: #c0392b; }

.badge-pending {
    background: #fff8e6;
    color: #9a6c00;
}
.badge-pending::before { background: #e5a000; }

input, select, button { padding: 6px; margin: 5px; }
</style>

<script>
function updatePrice() {
    var select = document.getElementById("product");
    var price = select.options[select.selectedIndex].getAttribute("data-price");
    document.getElementById("price").value = price;
}
</script>

</head>

<body>

<div class="title-bar">
    Welcome <?= htmlspecialchars($username) ?>
</div>

<div class="container">

<div class="sidebar">
  <h2>MENU</h2>
    <a href="/login/home_page.php">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="inventory.php">Inventory</a>
    <a href="sales.php">Sales</a>
    <a href="reports_analysis.php">Reports</a>
    <a href="admin.php">Admin</a>
    <a href="/login/logout.php"
       style="color:red;"
       onclick="return confirm('Are you sure you want to log out?')">
       Logout
    </a>
</div>

<div class="main">

<h1>Sales Management</h1>

<h2>Sale Entry</h2>

<form method="POST">
    <select name="product_id" id="product" onchange="updatePrice()" required>
        <option value="">Select Product</option>
        <?php while($p = $products->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>">
                <?= $p['name'] ?> (Stock: <?= $p['stock'] ?>)
            </option>
        <?php endwhile; ?>
    </select>

    <input type="number" name="quantity" placeholder="Quantity" required>
    <input type="number" id="price" readonly placeholder="Price">
    <button name="add_sale">Add Sale</button>
</form>

<h2>Summary</h2>

<div class="card">
    Total Sales: ₱<?= number_format($totalSales, 2) ?>
</div>

<div class="card">
    Transactions: <?= $totalTransactions ?>
</div>

<form method="POST" onsubmit="return confirm('Reset all sales?');">
    <button name="reset_sales" style="background:red;color:white;">
        Reset Sales History
    </button>
</form>

<h2>Sales List</h2>

<table>
<thead>
<tr>
    <th>ID</th>
    <th style="text-align:left;">Product</th>
    <th>Price</th>
    <th>Quantity</th>
    <th>Total</th>
    <th>Date</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php while($row = $sales->fetch_assoc()):
    $status = strtolower($row['status']);
    $badgeClass = match($status) {
        'completed'                    => 'badge-completed',
        'void'                         => 'badge-void',
        'cancelled', 'canceled'        => 'badge-cancelled',
        default                        => 'badge-pending',
    };
    $imageFile = $row['product_image'] ?? '';
?>
<tr>
    <td><?= $row['id'] ?></td>

    <td>
        <div class="product-cell">
            <?php if (!empty($imageFile)): ?>
    <img src="uploads/<?= htmlspecialchars($imageFile) ?>"
                     alt="<?= htmlspecialchars($row['product_name']) ?>"
                     class="product-thumb">
            <?php else: ?>
                <div class="product-thumb-placeholder">☕</div>
            <?php endif; ?>
            <div>
                <div class="product-name"><?= htmlspecialchars($row['product_name']) ?></div>
                <?php if (!empty($row['product_category'])): ?>
                    <div class="product-category"><?= htmlspecialchars($row['product_category']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </td>

    <td>₱<?= number_format($row['total'] / $row['quantity'], 2) ?></td>
    <td><?= $row['quantity'] ?></td>
    <td>₱<?= number_format($row['total'], 2) ?></td>
    <td><?= $row['created_at'] ?></td>
    <td>
        <span class="badge <?= $badgeClass ?>">
            <?= htmlspecialchars($row['status']) ?>
        </span>
    </td>
    <td>
        <?php if ($row['status'] === 'Completed'): ?>
        <form method="POST" style="display:inline;"
              onsubmit="return confirm('Cancel this order? Stock will be restored.')">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button name="cancel_sale"
                    style="background:#e74c3c;color:white;border:none;padding:5px 12px;border-radius:4px;cursor:pointer;">
                Cancel
            </button>
        </form>
        <?php else: ?>
            <span style="color:#aaa;font-size:12px;">—</span>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>
</div>

</body>
</html>