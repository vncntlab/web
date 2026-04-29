<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$username = $_SESSION['user']['username'];

$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");

$filter = $_GET['filter'] ?? 'daily';

if ($filter == "daily") {
    $dateCondition = "DATE(created_at) = CURDATE()";
} elseif ($filter == "weekly") {
    $dateCondition = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter == "monthly") {
    $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
} else {
    $dateCondition = "1=1";
}



$salesData = $conn->query("
    SELECT SUM(total) as total_sales, COUNT(*) as total_transactions
    FROM sales WHERE status='Completed' AND $dateCondition
")->fetch_assoc();
$totalSales        = $salesData['total_sales']        ?? 0;
$totalTransactions = $salesData['total_transactions'] ?? 0;


$salesRows = [];
$res = $conn->query("
    SELECT product_name, quantity, total, created_at, status
    FROM sales WHERE status='Completed' AND $dateCondition
    ORDER BY created_at DESC
");
while ($row = $res->fetch_assoc()) $salesRows[] = $row;


$topDaily = [];
$res = $conn->query("
    SELECT s.product_name, SUM(s.quantity) as qty_sold, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s LEFT JOIN products p ON p.name = s.product_name
    WHERE s.status='Completed' AND DATE(s.created_at) = CURDATE()
    GROUP BY s.product_name, p.image, p.price, p.category
    ORDER BY qty_sold DESC LIMIT 10
");
while ($row = $res->fetch_assoc()) $topDaily[] = $row;


$topWeekly = [];
$res = $conn->query("
    SELECT s.product_name, SUM(s.quantity) as qty_sold, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s LEFT JOIN products p ON p.name = s.product_name
    WHERE s.status='Completed'
      AND s.created_at >= CURDATE() - INTERVAL 6 DAY
      AND s.created_at < CURDATE() + INTERVAL 1 DAY
    GROUP BY s.product_name, p.image, p.price, p.category
    ORDER BY qty_sold DESC LIMIT 10
");
while ($row = $res->fetch_assoc()) $topWeekly[] = $row;


$topMonthly = [];
$res = $conn->query("
    SELECT s.product_name, SUM(s.quantity) as qty_sold, SUM(s.total) as revenue,
           p.image, p.price, p.category
    FROM sales s LEFT JOIN products p ON p.name = s.product_name
    WHERE s.status='Completed'
      AND MONTH(s.created_at) = MONTH(CURDATE())
      AND YEAR(s.created_at)  = YEAR(CURDATE())
    GROUP BY s.product_name, p.image, p.price, p.category
    ORDER BY qty_sold DESC LIMIT 10
");
while ($row = $res->fetch_assoc()) $topMonthly[] = $row;


$categoryRows = [];
$res = $conn->query("
    SELECT p.category, SUM(s.quantity) as total_quantity, SUM(s.total) as total_sales
    FROM sales s JOIN products p ON s.product_name = p.name
    WHERE s.status='Completed'
    GROUP BY p.category ORDER BY total_sales DESC
");
while ($row = $res->fetch_assoc()) $categoryRows[] = $row;


$allIngredients = [];
$res = $conn->query("SELECT * FROM ingredients ORDER BY stock ASC");
while ($row = $res->fetch_assoc()) $allIngredients[] = $row;


$allUnitCats = [];
foreach ($allIngredients as $i) {
    $unit = ucfirst($i['unit'] ?? 'Other') . '-based';
    if (!in_array($unit, $allUnitCats)) $allUnitCats[] = $unit;
}


$invStatus   = $_GET['inv_status']   ?? '';
$invCategory = $_GET['inv_category'] ?? '';


$filteredIngredients = [];
foreach ($allIngredients as $i) {
    $thr = $i['low_stock_threshold'] ?? 5;
    if ($i['stock'] <= $thr)         $cls = 'badge-bad';
    elseif ($i['stock'] <= $thr * 3) $cls = 'badge-mid';
    else                             $cls = 'badge-good';

    $unitLabel = ucfirst($i['unit'] ?? 'Other') . '-based';

    if ($invStatus   !== '' && $cls       !== $invStatus)   continue;
    if ($invCategory !== '' && $unitLabel !== $invCategory) continue;

    $i['_cls']       = $cls;
    $i['_unitLabel'] = $unitLabel;
    $filteredIngredients[] = $i;
}


if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filterLabel   = ucfirst($filter);
    $dateGenerated = date('Y-m-d H:i:s');

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Report_' . $filterLabel . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
               xmlns:x="urn:schemas-microsoft-com:office:excel"
               xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="UTF-8"></head><body>';
    echo '<table border="1" style="border-collapse:collapse;font-family:Arial;font-size:13px;">';
    echo '<tr><td colspan="5" style="background:#222;color:white;font-size:15px;font-weight:bold;padding:10px;">Al Coffee\'s Sales and Inventory Management System</td></tr>';
    echo '<tr><td colspan="5" style="padding:6px;">Report Period: <b>' . $filterLabel . '</b> &nbsp; Generated: ' . $dateGenerated . '</td></tr>';
    echo '<tr><td colspan="5"></td></tr>';

    echo '<tr><td colspan="5" style="background:#444;color:white;font-weight:bold;padding:8px;">SALES SUMMARY</td></tr>';
    echo '<tr><td style="background:#ddd;font-weight:bold;">Total Sales</td><td style="background:#ddd;font-weight:bold;">Total Transactions</td><td colspan="3"></td></tr>';
    echo '<tr><td>&#8369;' . number_format($totalSales, 2) . '</td><td>' . $totalTransactions . '</td><td colspan="3"></td></tr>';
    echo '<tr><td colspan="5"></td></tr>';

    echo '<tr><td colspan="5" style="background:#444;color:white;font-weight:bold;padding:8px;">SALES TRANSACTIONS (' . $filterLabel . ')</td></tr>';
    echo '<tr><td style="background:#ddd;font-weight:bold;">Product</td><td style="background:#ddd;font-weight:bold;">Qty</td><td style="background:#ddd;font-weight:bold;">Total</td><td style="background:#ddd;font-weight:bold;">Date</td><td style="background:#ddd;font-weight:bold;">Status</td></tr>';
    if (empty($salesRows)) {
        echo '<tr><td colspan="5" style="color:gray;">No transactions for this period.</td></tr>';
    } else {
        foreach ($salesRows as $row) {
            echo '<tr><td>' . htmlspecialchars($row['product_name']) . '</td><td>' . $row['quantity'] . '</td><td>&#8369;' . number_format($row['total'], 2) . '</td><td>' . $row['created_at'] . '</td><td>' . $row['status'] . '</td></tr>';
        }
    }
    echo '</table></body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports & Analysis — Al Coffee</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f5f5f5;
    color: #111;
}


.layout { display: flex; min-height: 100vh; }


.sidebar {
    width: 220px;
    background: #fff;
    border-right: 1px solid #e5e5e5;
    display: flex;
    flex-direction: column;
    padding: 0;
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    z-index: 100;
}
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 18px;
    border-bottom: 1px solid #eee;
    font-weight: 700;
    font-size: 15px;
}
.sidebar-logo span.logo-icon {
    width: 32px; height: 32px;
    background: #111;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 13px; font-weight: 800;
}
.sidebar nav { padding: 12px 0; flex: 1; }
.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 18px;
    text-decoration: none;
    color: #555;
    font-size: 14px;
    border-radius: 0;
    transition: background .15s, color .15s;
}
.sidebar nav a svg { width: 18px; height: 18px; flex-shrink: 0; }
.sidebar nav a:hover { background: #f5f5f5; color: #111; }
.sidebar nav a.active {
    background: #111;
    color: #fff;
}
.sidebar nav a.active svg { stroke: #fff; }
.sidebar nav a.logout { color: #e53935; margin-top: auto; }
.sidebar nav a.logout:hover { background: #fef2f2; }


.main {
    margin-left: 220px;
    flex: 1;
    display: flex;
    flex-direction: column;
}


.topbar {
    background: #fff;
    border-bottom: 1px solid #e5e5e5;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 90;
}
.topbar-title { font-size: 14px; color: #888; font-weight: 500; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-right a {
    font-size: 13px;
    text-decoration: none;
    color: #555;
    border: 1px solid #ddd;
    padding: 6px 12px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all .15s;
}
.topbar-right a:hover { border-color: #111; color: #111; }
.topbar-right a.active-filter {
    background: #111;
    color: #fff;
    border-color: #111;
}
.btn-excel {
    background: #1d6f42 !important;
    color: white !important;
    border-color: #1d6f42 !important;
    font-weight: 500;
}
.btn-excel:hover { background: #155233 !important; }


.content { padding: 28px; }


.hero {
    text-align: center;
    padding: 28px 0 20px;
    border-bottom: 1px solid #e5e5e5;
    margin-bottom: 20px;
}
.hero-label {
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #888;
    font-weight: 600;
}
.hero-amount {
    font-size: 52px;
    font-weight: 800;
    color: #111;
    letter-spacing: -2px;
    margin-top: 4px;
}


.stat-strip {
    display: flex;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 28px;
}
.stat-item {
    flex: 1;
    padding: 18px 20px;
    border-right: 1px solid #e5e5e5;
}
.stat-item:last-child { border-right: none; }
.s-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #888;
    font-weight: 600;
}
.s-value {
    font-size: 24px;
    font-weight: 800;
    color: #111;
    margin-top: 4px;
    letter-spacing: -0.5px;
}


.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 28px 0 14px;
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-title svg { width: 18px; height: 18px; }
.section-link {
    font-size: 12px;
    color: #888;
    text-decoration: none;
}
.section-link:hover { color: #111; }


.period-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #555;
    background: #eee;
    padding: 5px 10px;
    border-radius: 4px;
    display: inline-block;
    margin: 18px 0 10px;
}


.product-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
    scrollbar-width: thin;
}
.product-scroll::-webkit-scrollbar { height: 4px; }
.product-scroll::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }

.prod-card {
    flex-shrink: 0;
    width: 145px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 12px;
    position: relative;
    transition: box-shadow .15s;
}
.prod-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.prod-rank {
    position: absolute;
    top: 8px; left: 8px;
    background: #111;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    width: 20px; height: 20px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.prod-img {
    width: 100%; height: 100px;
    object-fit: cover;
    border-radius: 7px;
    display: block;
    margin-bottom: 8px;
}
.prod-no-img {
    width: 100%; height: 100px;
    background: #f0f0f0;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; color: #aaa;
    margin-bottom: 8px;
}
.prod-name {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.prod-cat {
    font-size: 11px;
    color: #888;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.prod-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.prod-price { font-size: 12px; font-weight: 700; color: #111; }
.prod-sold { font-size: 11px; color: #888; }


.inv-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.inv-filter-select-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.inv-filter-select-wrap select {
    appearance: none;
    -webkit-appearance: none;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 7px 32px 7px 12px;
    font-size: 13px;
    color: #555;
    cursor: pointer;
    outline: none;
    transition: border-color .15s;
}
.inv-filter-select-wrap select:hover,
.inv-filter-select-wrap select:focus { border-color: #111; color: #111; }
.inv-select-arrow {
    position: absolute;
    right: 8px;
    width: 14px; height: 14px;
    pointer-events: none;
    stroke: #888;
}
.inv-submit-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #111;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s;
}
.inv-submit-btn:hover { background: #333; }
.inv-clear-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    color: #888;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 7px 14px;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
}
.inv-clear-btn:hover { border-color: #111; color: #111; }
.filter-active-tag {
    font-size: 12px;
    background: #111;
    color: #fff;
    border-radius: 20px;
    padding: 3px 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.filter-active-tag a {
    color: #aaa;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
}
.filter-active-tag a:hover { color: #fff; }


.inv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}
.inv-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.inv-thumb {
    width: 44px; height: 44px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
}
.inv-thumb-blank {
    width: 44px; height: 44px;
    background: #f0f0f0;
    border-radius: 8px;
    flex-shrink: 0;
}
.inv-info { flex: 1; min-width: 0; }
.inv-name {
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.inv-unit { font-size: 11px; color: #888; margin-bottom: 2px; }
.inv-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
}
.inv-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
}
.badge-bad  { background: #fef2f2; color: #e53935; }
.badge-mid  { background: #fff8f0; color: #fb8c00; }
.badge-good { background: #f1f8f1; color: #43a047; }
.inv-limit  { font-size: 11px; color: #aaa; }


.data-table {
    width: 100%;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
}
.data-table th {
    background: #111;
    color: #fff;
    padding: 11px 16px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    letter-spacing: .5px;
}
.data-table td {
    padding: 11px 16px;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table td.right { text-align: right; font-weight: 600; }
</style>
</head>
<body>

<div class="layout">

  
  <aside class="sidebar">
    <div class="sidebar-logo">
      <span class="logo-icon">al</span>
      <span>al coffee</span>
    </div>
    <nav>
      <a href="/login/home_page.php">
        Dashboard
      </a>
      <a href="products.php">
        Products
      </a>
      <a href="inventory.php">
        Inventory
      </a>
      <a href="sales.php">
        Sales
      </a>
      <a href="reports_analysis.php" class="active">
        Reports
      </a>
      <a href="admin.php">
        Admin
      </a>
      <a href="/login/logout.php" class="logout" onclick="return confirm('Are you sure you want to log out?')">
        Logout
      </a>
    </nav>
  </aside>

 
  <div class="main">

 
    <div class="topbar">
      <span class="topbar-title">Reports &amp; Analytics</span>
      <div class="topbar-right">
        <a href="?filter=daily"   class="<?= $filter=='daily'   ? 'active-filter' : '' ?>">Daily</a>
        <a href="?filter=weekly"  class="<?= $filter=='weekly'  ? 'active-filter' : '' ?>">Weekly</a>
        <a href="?filter=monthly" class="<?= $filter=='monthly' ? 'active-filter' : '' ?>">Monthly</a>
        <a href="reports_analysis.php" class="<?= !in_array($filter,['daily','weekly','monthly']) ? 'active-filter' : '' ?>">All</a>
        <a href="?filter=<?= $filter ?>&export=excel" class="btn-excel">⬇ Excel</a>
      </div>
    </div>

    <div class="content">

      
      <div class="hero">
        <div class="hero-label">
          <?= ucfirst($filter) ?>'s Sales
        </div>
        <div class="hero-amount">₱<?= number_format($totalSales, 0) ?></div>
      </div>

      
      <div class="stat-strip">
        <div class="stat-item">
          <div class="s-label">Total Transactions</div>
          <div class="s-value"><?= $totalTransactions ?></div>
        </div>
        <?php foreach ($categoryRows as $cat): ?>
        <div class="stat-item">
          <div class="s-label"><?= htmlspecialchars($cat['category']) ?></div>
          <div class="s-value">₱<?= number_format($cat['total_sales'], 0) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      
      <div class="section-header">
        <div class="section-title">
        Top Selling Products
        </div>
      </div>

      
      <div class="period-label">Daily</div>
      <div class="product-scroll">
        <?php if (empty($topDaily)): ?>
          <p style="color:#aaa;font-size:13px;">No data for today.</p>
        <?php else: $rank = 1; foreach ($topDaily as $p): ?>
        <div class="prod-card">
          <div class="prod-rank"><?= $rank ?></div>
          <?php if (!empty($p['image'])): ?>
            <img class="prod-img" src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
          <?php else: ?>
            <div class="prod-no-img">No Image</div>
          <?php endif; ?>
          <div class="prod-name"><?= htmlspecialchars($p['product_name']) ?></div>
          <div class="prod-cat"><?= htmlspecialchars($p['category'] ?? '—') ?></div>
          <div class="prod-meta">
            <span class="prod-price">₱<?= number_format($p['price'] ?? 0, 0) ?></span>
            <span class="prod-sold"><?= $p['qty_sold'] ?> sold</span>
          </div>
        </div>
        <?php $rank++; endforeach; endif; ?>
      </div>

   
      <div class="period-label">Weekly</div>
      <div class="product-scroll">
        <?php if (empty($topWeekly)): ?>
          <p style="color:#aaa;font-size:13px;">No data this week.</p>
        <?php else: $rank = 1; foreach ($topWeekly as $p): ?>
        <div class="prod-card">
          <div class="prod-rank"><?= $rank ?></div>
          <?php if (!empty($p['image'])): ?>
            <img class="prod-img" src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
          <?php else: ?>
            <div class="prod-no-img">No Image</div>
          <?php endif; ?>
          <div class="prod-name"><?= htmlspecialchars($p['product_name']) ?></div>
          <div class="prod-cat"><?= htmlspecialchars($p['category'] ?? '—') ?></div>
          <div class="prod-meta">
            <span class="prod-price">₱<?= number_format($p['price'] ?? 0, 0) ?></span>
            <span class="prod-sold"><?= $p['qty_sold'] ?> sold</span>
          </div>
        </div>
        <?php $rank++; endforeach; endif; ?>
      </div>

      
      <div class="period-label">Monthly</div>
      <div class="product-scroll">
        <?php if (empty($topMonthly)): ?>
          <p style="color:#aaa;font-size:13px;">No data this month.</p>
        <?php else: $rank = 1; foreach ($topMonthly as $p): ?>
        <div class="prod-card">
          <div class="prod-rank"><?= $rank ?></div>
          <?php if (!empty($p['image'])): ?>
            <img class="prod-img" src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
          <?php else: ?>
            <div class="prod-no-img">No Image</div>
          <?php endif; ?>
          <div class="prod-name"><?= htmlspecialchars($p['product_name']) ?></div>
          <div class="prod-cat"><?= htmlspecialchars($p['category'] ?? '—') ?></div>
          <div class="prod-meta">
            <span class="prod-price">₱<?= number_format($p['price'] ?? 0, 0) ?></span>
            <span class="prod-sold"><?= $p['qty_sold'] ?> sold</span>
          </div>
        </div>
        <?php $rank++; endforeach; endif; ?>
      </div>

      
      <div class="section-header">
        <div class="section-title">
          Inventory Status
        </div>
        <a class="section-link" href="inventory.php">Manage All</a>
      </div>

      
      <form method="GET" action="reports_analysis.php" style="margin:0;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="inv-filters">

          
          <div class="inv-filter-select-wrap">
            <select name="inv_status">
              <option value="">Filter Status</option>
              <option value="badge-bad"  <?= $invStatus === 'badge-bad'  ? 'selected' : '' ?>>⚠ Low</option>
              <option value="badge-mid"  <?= $invStatus === 'badge-mid'  ? 'selected' : '' ?>>⚠ Medium</option>
              <option value="badge-good" <?= $invStatus === 'badge-good' ? 'selected' : '' ?>>✓ Good</option>
            </select>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="inv-select-arrow"><polyline points="6 9 12 15 18 9"/></svg>
          </div>

         
          <div class="inv-filter-select-wrap">
            <select name="inv_category">
              <option value="">All Categories</option>
              <?php foreach ($allUnitCats as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>" <?= $invCategory === $cat ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="inv-select-arrow"><polyline points="6 9 12 15 18 9"/></svg>
          </div>

         
          <button type="submit" class="inv-submit-btn">Apply Filter</button>

         
          <?php if ($invStatus !== '' || $invCategory !== ''): ?>
          <a href="?filter=<?= htmlspecialchars($filter) ?>" class="inv-clear-btn">✕ Clear</a>
          <?php endif; ?>

        </div>

     
        <?php if ($invStatus !== '' || $invCategory !== ''): ?>
        <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php if ($invStatus !== ''):
            $statusLabel = ['badge-bad' => '⚠ Low', 'badge-mid' => '⚠ Medium', 'badge-good' => '✓ Good'][$invStatus] ?? $invStatus;
          ?>
          <span class="filter-active-tag">
            Status: <?= htmlspecialchars($statusLabel) ?>
            <a href="?filter=<?= htmlspecialchars($filter) ?>&inv_category=<?= urlencode($invCategory) ?>">×</a>
          </span>
          <?php endif; ?>
          <?php if ($invCategory !== ''): ?>
          <span class="filter-active-tag">
            Category: <?= htmlspecialchars($invCategory) ?>
            <a href="?filter=<?= htmlspecialchars($filter) ?>&inv_status=<?= urlencode($invStatus) ?>">×</a>
          </span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </form>

      <div class="inv-grid">
        <?php if (empty($filteredIngredients)): ?>
          <p style="color:#aaa;font-size:13px;grid-column:1/-1;">No ingredients match the selected filters.</p>
        <?php else: foreach ($filteredIngredients as $i):
            $cls       = $i['_cls'];
            $unitLabel = $i['_unitLabel'];
            $thr       = $i['low_stock_threshold'] ?? 5;
            if ($cls === 'badge-bad')  $label = '⚠ ' . $i['stock'] . ' LEFT';
            elseif ($cls === 'badge-mid') $label = '⚠ ' . $i['stock'] . ' LEFT';
            else                      $label = '✓ ' . $i['stock'];
        ?>
        <div class="inv-card">
          <?php if (!empty($i['image'])): ?>
            <img class="inv-thumb" src="uploads/<?= htmlspecialchars($i['image']) ?>" alt="">
          <?php else: ?>
            <div class="inv-thumb-blank"></div>
          <?php endif; ?>
          <div class="inv-info">
            <div class="inv-name"><?= htmlspecialchars($i['ingredient_name']) ?></div>
            <div class="inv-unit"><?= htmlspecialchars($unitLabel) ?></div>
            <div class="inv-status">
              <span class="inv-badge <?= $cls ?>"><?= $label ?></span>
              <span class="inv-limit">Limit: <?= $thr ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      
      <div class="section-header">
        <div class="section-title">
          Best Selling Category
        </div>
      </div>
      <table class="data-table">
        <tr>
          <th style="width:60px;text-align:center;">Rank</th>
          <th>Category</th>
          <th style="text-align:right;">Quantity</th>
          <th style="text-align:right;">Total Sales</th>
        </tr>
        <?php $rank = 1; foreach ($categoryRows as $row): ?>
        <tr>
          <td style="text-align:center;font-weight:700;color:#888;">#<?= $rank ?></td>
          <td><?= htmlspecialchars($row['category']) ?></td>
          <td style="text-align:right;"><?= number_format($row['total_quantity']) ?></td>
          <td class="right">₱<?= number_format($row['total_sales'], 2) ?></td>
        </tr>
        <?php $rank++; endforeach; ?>
      </table>

    </div>
  </div>
</div>

</body>
</html>