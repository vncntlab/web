<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");

$username = $_SESSION['user']['username'];


$daily = $conn->query("
    SELECT SUM(total) as total
    FROM sales
    WHERE created_at >= CURDATE()
    AND created_at < CURDATE() + INTERVAL 1 DAY
")->fetch_assoc()['total'] ?? 0;

$weekly = $conn->query("
    SELECT SUM(total) as total
    FROM sales
    WHERE created_at >= CURDATE() - INTERVAL 6 DAY
    AND created_at < CURDATE() + INTERVAL 1 DAY
")->fetch_assoc()['total'] ?? 0;

$monthly = $conn->query("
    SELECT SUM(total) as total
    FROM sales
    WHERE MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
")->fetch_assoc()['total'] ?? 0;

$today_orders = $conn->query("
    SELECT COUNT(*) as c
    FROM sales
    WHERE created_at >= CURDATE()
    AND created_at < CURDATE() + INTERVAL 1 DAY
")->fetch_assoc()['c'] ?? 0;

$forecast = $monthly * 1.1;


$inStock  = $conn->query("SELECT COUNT(*) as c FROM products WHERE stock > 0")->fetch_assoc()['c'] ?? 0;
$lowStock = $conn->query("SELECT COUNT(*) as c FROM products WHERE stock <= 5")->fetch_assoc()['c'] ?? 0;


$ingredients = $conn->query("SELECT * FROM ingredients ORDER BY stock ASC");


$best_daily = $conn->query("
    SELECT s.product_name, SUM(s.quantity) as qty, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s
    LEFT JOIN products p ON p.name = s.product_name
    WHERE s.created_at >= CURDATE()
      AND s.created_at < CURDATE() + INTERVAL 1 DAY
    GROUP BY s.product_name, p.image, p.price, p.category
    ORDER BY qty DESC
    LIMIT 10
");


$best_weekly = $conn->query("
    SELECT s.product_name, SUM(s.quantity) as qty, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s
    LEFT JOIN products p ON p.name = s.product_name
    WHERE s.created_at >= CURDATE() - INTERVAL 6 DAY
      AND s.created_at < CURDATE() + INTERVAL 1 DAY
    GROUP BY s.product_name, p.image, p.price, p.category
    ORDER BY qty DESC
    LIMIT 10
");


$best_monthly = $conn->query("
    SELECT s.product_name, SUM(s.quantity) as qty, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s
    LEFT JOIN products p ON p.name = s.product_name
    WHERE MONTH(s.created_at) = MONTH(CURDATE())
      AND YEAR(s.created_at)  = YEAR(CURDATE())
    GROUP BY s.product_name, p.image, p.price, p.category
    ORDER BY qty DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard & Inventory</title>

<style>
body { margin:0; font-family:Arial; background:#f4f4f4; }

.title-bar {
    background:#222;
    color:white;
    padding:15px;
}

.container { display:flex; }

.sidebar {
    width:220px;
    background:#111;
    color:white;
    min-height:100vh;
    padding:15px;
}

.sidebar a {
    display:block;
    color:white;
    margin:10px 0;
    text-decoration:none;
}

.main { flex:1; padding:20px; }

.card {
    background:white;
    padding:15px;
    margin:10px;
    border-radius:8px;
    display:inline-block;
    width:220px;
    vertical-align:top;
}

table {
    width:100%;
    background:white;
    border-collapse:collapse;
}

th {
    background:#222;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:center;
}

.good { color:green; }
.mid  { color:orange; }
.bad  { color:red; }

.best-item {
    display:flex;
    align-items:center;
    gap:10px;
    margin:10px 0;
}
</style>

<style>
.hero {
    text-align:center;
    padding:20px 0 10px;
    border-bottom:2px solid #ddd;
    margin-bottom:16px;
}
.hero-label {
    font-size:12px;
    color:#888;
    letter-spacing:2px;
    text-transform:uppercase;
}
.hero-amount {
    font-size:46px;
    font-weight:bold;
    color:#111;
    margin:4px 0 0;
}

.stat-strip {
    display:flex;
    border-top:2px solid #222;
    border-bottom:2px solid #222;
    background:white;
    margin-bottom:24px;
}
.stat-strip .stat-item {
    flex:1;
    padding:14px 18px;
    border-right:1px solid #ddd;
}
.stat-strip .stat-item:last-child { border-right:none; }
.stat-item .s-label {
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:1px;
    color:#888;
}
.stat-item .s-value {
    font-size:22px;
    font-weight:bold;
    color:#111;
    margin-top:2px;
}

.section-title {
    font-size:15px;
    font-weight:bold;
    border-left:4px solid #222;
    padding-left:8px;
    margin:20px 0 10px;
}
.section-title a {
    float:right;
    font-size:12px;
    font-weight:normal;
    color:#888;
    text-decoration:none;
}

.stock-bad  { color:#e53935; }
.stock-mid  { color:#fb8c00; }
.stock-good { color:#43a047; }

.prod-img-thumb {
    width:50px;
    height:50px;
    object-fit:cover;
    vertical-align:middle;
}
.prod-img-blank {
    width:50px;
    height:50px;
    background:#ddd;
    display:inline-block;
    vertical-align:middle;
}
.period-heading {
    font-size:11px;
    font-weight:bold;
    letter-spacing:2px;
    text-transform:uppercase;
    color:#555;
    background:#eee;
    padding:6px 10px;
    margin-top:16px;
}
</style>

</head>
<body>

<div class="title-bar">
    Al Coffee System Dashboard & Inventory —
    Welcome <?= htmlspecialchars($username) ?>
</div>

<div class="container">

<div class="sidebar">
    <h2>MENU</h2>
    <a href="home_page.php">Dashboard</a>
    <a href="admin/products.php">Products</a>
    <a href="admin/inventory.php">Inventory</a>
    <a href="admin/sales.php">Sales</a>
    <a href="admin/reports_analysis.php">Reports</a>
    <a href="admin/admin.php">Admin</a>
    <a href="/login/logout.php" style="color:red;"
       onclick="return confirm('Are you sure you want to log out?')">
       Logout
    </a>
</div>

<div class="main">

    
    <div class="hero">
        <div class="hero-label">Today's Sales</div>
        <div class="hero-amount">₱<?= number_format($daily, 0) ?></div>
    </div>

    
    <div class="stat-strip">
        <div class="stat-item">
            <div class="s-label">This Week's Sale</div>
            <div class="s-value">₱<?= number_format($weekly, 0) ?></div>
        </div>
        <div class="stat-item">
            <div class="s-label">This Month's Sales</div>
            <div class="s-value">₱<?= number_format($monthly, 0) ?></div>
        </div>
        <div class="stat-item">
            <div class="s-label">Today's Orders</div>
            <div class="s-value"><?= $today_orders ?></div>
        </div>
        <div class="stat-item">
            <div class="s-label">Monthly Estimate</div>
            <div class="s-value">₱<?= number_format($forecast, 0) ?></div>
        </div>
    </div>

    
    <div class="section-title">
        Inventory Status
        <a href="admin/inventory.php">Manage All</a>
    </div>

    <table>
        <tr>
            <th>Ingredient</th>
            <th>Unit</th>
            <th>Stock</th>
            <th>Limit</th>
            <th>Status</th>
        </tr>
        <?php
        $ingredients->data_seek(0);
        while ($i = $ingredients->fetch_assoc()):
            $thr = $i['low_stock_threshold'] ?? 5;
            if ($i['stock'] <= $thr)         { $sc="stock-bad";  $icon="⚠️"; $label="Low"; }
            elseif ($i['stock'] <= $thr * 3) { $sc="stock-mid";  $icon="⚠️"; $label="Mid"; }
            else                             { $sc="stock-good"; $icon="✅"; $label="Good"; }
        ?>
        <tr>
            <td style="text-align:left;">
                <?php if (!empty($i['image'])): ?>
                    <img src="admin/uploads/<?= htmlspecialchars($i['image']) ?>"
                         style="width:36px;height:36px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px;">
                <?php endif; ?>
                <?= htmlspecialchars($i['ingredient_name']) ?>
            </td>
            <td><?= htmlspecialchars($i['unit'] ?? '') ?></td>
            <td><?= $i['stock'] ?></td>
            <td><?= $thr ?></td>
            <td><span class="<?= $sc ?>"><?= $icon ?> <?= $label ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>

    
    <div class="section-title" style="margin-top:28px;">Top Selling Products</div>

    
    <div class="period-heading">Daily</div>
    <table>
        <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Sold</th>
            <th>Revenue</th>
        </tr>
        <?php while ($b = $best_daily->fetch_assoc()): ?>
        <tr>
            <td style="text-align:left;">
                <?php if (!empty($b['image'])): ?>
                    <img src="admin/uploads/<?= htmlspecialchars($b['image']) ?>"
                         class="prod-img-thumb">
                <?php else: ?>
                    <span class="prod-img-blank"></span>
                <?php endif; ?>
                &nbsp;<?= htmlspecialchars($b['product_name']) ?>
            </td>
            <td><?= htmlspecialchars($b['category'] ?? '—') ?></td>
            <td>₱<?= number_format($b['price'] ?? 0, 0) ?></td>
            <td><?= $b['qty'] ?></td>
            <td>₱<?= number_format($b['revenue'], 0) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    
    <div class="period-heading">Weekly</div>
    <table>
        <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Sold</th>
            <th>Revenue</th>
        </tr>
        <?php while ($b = $best_weekly->fetch_assoc()): ?>
        <tr>
            <td style="text-align:left;">
                <?php if (!empty($b['image'])): ?>
                    <img src="admin/uploads/<?= htmlspecialchars($b['image']) ?>"
                         class="prod-img-thumb">
                <?php else: ?>
                    <span class="prod-img-blank"></span>
                <?php endif; ?>
                &nbsp;<?= htmlspecialchars($b['product_name']) ?>
            </td>
            <td><?= htmlspecialchars($b['category'] ?? '—') ?></td>
            <td>₱<?= number_format($b['price'] ?? 0, 0) ?></td>
            <td><?= $b['qty'] ?></td>
            <td>₱<?= number_format($b['revenue'], 0) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    
    <div class="period-heading">Monthly</div>
    <table>
        <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Sold</th>
            <th>Revenue</th>
        </tr>
        <?php while ($b = $best_monthly->fetch_assoc()): ?>
        <tr>
            <td style="text-align:left;">
                <?php if (!empty($b['image'])): ?>
                    <img src="admin/uploads/<?= htmlspecialchars($b['image']) ?>"
                         class="prod-img-thumb">
                <?php else: ?>
                    <span class="prod-img-blank"></span>
                <?php endif; ?>
                &nbsp;<?= htmlspecialchars($b['product_name']) ?>
            </td>
            <td><?= htmlspecialchars($b['category'] ?? '—') ?></td>
            <td>₱<?= number_format($b['price'] ?? 0, 0) ?></td>
            <td><?= $b['qty'] ?></td>
            <td>₱<?= number_format($b['revenue'], 0) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

</div>
</div>

</body>
</html>