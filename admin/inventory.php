<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("Connection failed");

if (isset($_POST['add_ingredient'])) {

    $name  = $_POST['ingredient_name'];
    $stock = intval($_POST['stock']);
    $low   = intval($_POST['low_stock_threshold']);
    $unit  = $_POST['unit'];

    $image = "";
    if (!empty($_FILES['image']['name'])) {
        $image = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
    }

    $stmt = $conn->prepare("INSERT INTO ingredients 
        (ingredient_name, stock, low_stock_threshold, unit, image)
        VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siiss", $name, $stock, $low, $unit, $image);
    $stmt->execute();

    $_SESSION['notif'] = ['type' => 'added', 'name' => $name];
    header("Location: inventory.php");
    exit();
}

if (isset($_POST['delete_ing'])) {
    $delId = intval($_POST['id']);

    $getNameStmt = $conn->prepare("SELECT ingredient_name FROM ingredients WHERE id=?");
    $getNameStmt->bind_param("i", $delId);
    $getNameStmt->execute();
    $getNameResult = $getNameStmt->get_result()->fetch_assoc();
    $delName = $getNameResult ? $getNameResult['ingredient_name'] : 'Ingredient';

    $stmt = $conn->prepare("DELETE FROM ingredients WHERE id=?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();

    $_SESSION['notif'] = ['type' => 'deleted', 'name' => $delName];
    header("Location: inventory.php");
    exit();
}

$editIng = null;

if (isset($_POST['edit_ing'])) {
    $stmt = $conn->prepare("SELECT * FROM ingredients WHERE id=?");
    $stmt->bind_param("i", $_POST['id']);
    $stmt->execute();
    $editIng = $stmt->get_result()->fetch_assoc();
}

if (isset($_POST['update_ing'])) {

    $id    = $_POST['id'];
    $name  = $_POST['ingredient_name'];
    $stock = intval($_POST['stock']);
    $low   = intval($_POST['low_stock_threshold']);
    $unit  = $_POST['unit'];

    $image_sql = "";

    if (!empty($_FILES['image']['name'])) {
        $image = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
        $image_sql = ", image='$image'";
    }

    $stmt = $conn->prepare("UPDATE ingredients 
        SET ingredient_name=?, stock=?, low_stock_threshold=?, unit=? $image_sql
        WHERE id=?");
    $stmt->bind_param("siisi", $name, $stock, $low, $unit, $id);
    $stmt->execute();

    $_SESSION['notif'] = ['type' => 'updated', 'name' => $name];
    header("Location: inventory.php");
    exit();
}

$search     = $_GET['search']      ?? '';
$stockLevel = $_GET['stock_level'] ?? '';
$category   = $_GET['category']    ?? '';

$ingWhere  = "1=1";
$ingParams = [];
$ingTypes  = "";

if (!empty($search)) {
    $ingWhere   .= " AND ingredient_name LIKE ?";
    $ingParams[] = "%$search%";
    $ingTypes   .= "s";
}

if (!empty($category)) {
    $ingWhere   .= " AND unit = ?";
    $ingParams[] = $category;
    $ingTypes   .= "s";
}

if ($stockLevel === 'low') {
    $ingWhere .= " AND stock <= low_stock_threshold";
} elseif ($stockLevel === 'mid') {
    $ingWhere .= " AND stock > low_stock_threshold AND stock <= low_stock_threshold * 3";
} elseif ($stockLevel === 'high') {
    $ingWhere .= " AND stock > low_stock_threshold * 3";
}

$ingStmt = $conn->prepare("SELECT * FROM ingredients WHERE $ingWhere");
if (!empty($ingParams)) {
    $ingStmt->bind_param($ingTypes, ...$ingParams);
}
$ingStmt->execute();
$ingredients = $ingStmt->get_result();

$lowStockAlerts = [];

$lowIngStmt = $conn->prepare("SELECT ingredient_name AS label, stock, low_stock_threshold FROM ingredients WHERE stock <= low_stock_threshold ORDER BY ingredient_name");
$lowIngStmt->execute();
$lowIngResult = $lowIngStmt->get_result();
while ($r = $lowIngResult->fetch_assoc()) {
    $lowStockAlerts[] = $r;
}

$notif = null;
if (!empty($_SESSION['notif'])) {
    $notif = $_SESSION['notif'];
    unset($_SESSION['notif']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory</title>

<link rel="stylesheet" href="../resources/main_css.css">
<link rel="stylesheet" href="../resources/homepages.css">

<style>
input, select { padding: 5px; margin: 5px; }
button { padding: 5px 10px; margin: 3px; }
.sidebar-logout { color: red; }
#low-stock-alert {
    position: relative;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-left: 5px solid #e65100;
    border-radius: 6px;
    padding: 14px 40px 14px 16px;
    margin-bottom: 20px;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
#low-stock-alert .alert-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 15px;
    color: #7a3a00;
    margin-bottom: 8px;
}
#low-stock-alert .alert-header .bell-icon {
    font-size: 18px;
    animation: ring 1s ease 0.5s 2;
    display: inline-block;
}
@keyframes ring {
    0%, 100% { transform: rotate(0); }
    20% { transform: rotate(-20deg); }
    40% { transform: rotate(20deg); }
    60% { transform: rotate(-10deg); }
    80% { transform: rotate(10deg); }
}
#low-stock-alert .alert-items {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
}
#low-stock-alert .alert-tag {
    background: #b71c1c;
    color: #fff;
    border-radius: 12px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
#low-stock-alert .alert-count {
    font-size: 13px;
    color: #7a3a00;
    margin-top: 6px;
}
#low-stock-alert .alert-count a {
    color: #7a3a00;
    font-weight: 600;
}
.alert-nav-badge {
    display: inline-block;
    background: #e53935;
    color: #fff;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 700;
    width: 18px;
    height: 18px;
    line-height: 18px;
    text-align: center;
    margin-left: 4px;
    vertical-align: middle;
}
.filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}
.filter-bar input[type="text"] {
    padding: 7px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    min-width: 180px;
}
.filter-bar select {
    padding: 7px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}
.filter-search-btn {
    padding: 7px 16px;
    background: #333;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}
.filter-clear-btn {
    padding: 7px 14px;
    background: #eee;
    color: #333;
    border: 1px solid #ccc;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
}
.ingredient-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
}
.ingredient-card {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 16px;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.ingredient-card img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #eee;
    flex-shrink: 0;
}
.img-placeholder {
    width: 60px;
    height: 60px;
    background: #f0f0f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.card-info { flex: 1; min-width: 0; }
.card-name {
    font-weight: 700;
    font-size: 15px;
    margin-bottom: 4px;
    color: #1a1a1a;
}
.card-stock-label {
    font-size: 12px;
    color: #888;
    margin-bottom: 4px;
}
.stock-bar-wrap {
    background: #eee;
    border-radius: 6px;
    height: 6px;
    width: 100%;
    margin-bottom: 5px;
    overflow: hidden;
}
.stock-bar-fill {
    height: 6px;
    border-radius: 6px;
}
.bar-low  { background: #e53935; }
.bar-mid  { background: #fb8c00; }
.bar-high { background: #43a047; }
.card-stock-count {
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
}
.card-stock-count.low  { color: #e53935; }
.card-stock-count.mid  { color: #fb8c00; }
.card-stock-count.high { color: #43a047; }
.card-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}
.card-limit { font-size: 12px; color: #888; }
.card-unit {
    font-size: 11px;
    color: #aaa;
    background: #f4f4f4;
    border-radius: 8px;
    padding: 2px 8px;
}
.card-actions { display: flex; gap: 5px; }
.btn-edit {
    padding: 4px 12px;
    background: #1565c0;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
}
.btn-delete {
    padding: 4px 12px;
    background: #c62828;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
}
.form-inline { display: inline; }
#notif-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 340px;
    pointer-events: none;
}
.notif-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 40px 14px 16px;
    border-radius: 10px;
    border: 1.5px solid transparent;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    position: relative;
    font-size: 14px;
    pointer-events: all;
    animation: notifSlideIn 0.4s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes notifSlideIn {
    from { opacity: 0; transform: translateX(80px); }
    to   { opacity: 1; transform: translateX(0); }
}
.notif-deleted {
    background: #fff0f0;
    border-color: #f5c2c2;
}
.notif-deleted .notif-icon  { color: #e53935; }
.notif-deleted .notif-title { color: #c62828; }
.notif-added,
.notif-updated {
    background: #f0fff4;
    border-color: #a8e6b8;
}
.notif-added .notif-icon,
.notif-updated .notif-icon  { color: #2e7d32; }
.notif-added .notif-title,
.notif-updated .notif-title { color: #1b5e20; }
.notif-lowstock {
    background: #fffbea;
    border-color: #ffe08a;
}
.notif-lowstock .notif-icon  { color: #f59e0b; }
.notif-lowstock .notif-title { color: #b45309; }
.notif-icon {
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 1px;
}
.notif-body { flex: 1; }
.notif-title {
    font-weight: 700;
    font-size: 13.5px;
    margin-bottom: 3px;
}
.notif-msg {
    color: #444;
    font-size: 13px;
    line-height: 1.4;
}
.notif-close-link {
    position: absolute;
    top: 10px;
    right: 12px;
    font-size: 16px;
    line-height: 1;
    color: #999;
    text-decoration: none;
    font-weight: 700;
}
.notif-close-link:hover { color: #333; }
.notif-card:nth-child(1) { animation-delay: 0.00s; }
.notif-card:nth-child(2) { animation-delay: 0.10s; }
.notif-card:nth-child(3) { animation-delay: 0.20s; }
.notif-card:nth-child(4) { animation-delay: 0.30s; }
.notif-card:nth-child(5) { animation-delay: 0.40s; }
</style>

</head>
<body>

<div class="title-bar">
  Al Coffee's Sales and Inventory Management System
</div>

<div class="container">

<div class="sidebar">
  <h2>MENU</h2>
  <a href="/login/home_page.php">Dashboard</a>
  <a href="products.php">Products</a>
  <a href="inventory.php">
    Inventory
    <?php if (count($lowStockAlerts) > 0): ?>
      <span class="alert-nav-badge"><?= count($lowStockAlerts) ?></span>
    <?php endif; ?>
  </a>
  <a href="sales.php">Sales</a>
  <a href="reports_analysis.php">Reports</a>
  <a href="admin.php">Admin</a>
  <a href="/login/logout.php" class="sidebar-logout"
     onclick="return confirm('Are you sure you want to log out?')">Logout</a>
</div>

<div class="main">

<h1>INVENTORY</h1>

<?php if (!empty($lowStockAlerts)): ?>
<div id="low-stock-alert">
    <div class="alert-header">
        <span class="bell-icon">🔔</span>
        Low Stock Alert — <?= count($lowStockAlerts) ?> ingredient<?= count($lowStockAlerts) > 1 ? 's' : '' ?> need<?= count($lowStockAlerts) === 1 ? 's' : '' ?> restocking
    </div>
    <div class="alert-items">
        <?php foreach ($lowStockAlerts as $alert): ?>
            <span class="alert-tag"
                  title="Stock: <?= $alert['stock'] ?> / Threshold: <?= $alert['low_stock_threshold'] ?>">
                🧪 <?= htmlspecialchars($alert['label']) ?> (<?= $alert['stock'] ?>)
            </span>
        <?php endforeach; ?>
    </div>
    <div class="alert-count">
        <a href="inventory.php?stock_level=low">View all low stock →</a>
    </div>
</div>
<?php endif; ?>

<form method="GET" class="filter-bar">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Search ingredient...">

    <select name="category">
        <option value="">Select Category</option>
        <option value="Can-based"     <?= $category === 'Can-based'     ? 'selected' : '' ?>>Can-based</option>
        <option value="Bottle-based"  <?= $category === 'Bottle-based'  ? 'selected' : '' ?>>Bottle-based</option>
        <option value="Box-based"     <?= $category === 'Box-based'     ? 'selected' : '' ?>>Box-based</option>
        <option value="Plastic-based" <?= $category === 'Plastic-based' ? 'selected' : '' ?>>Plastic-based</option>
        <option value="Pack-based"    <?= $category === 'Pack-based'    ? 'selected' : '' ?>>Pack-based</option>
    </select>

    <select name="stock_level">
        <option value="">Select Stock Level</option>
        <option value="low"  <?= $stockLevel === 'low'  ? 'selected' : '' ?>>🔴 Low</option>
        <option value="mid"  <?= $stockLevel === 'mid'  ? 'selected' : '' ?>>🟠 Mid</option>
        <option value="high" <?= $stockLevel === 'high' ? 'selected' : '' ?>>🟢 High</option>
    </select>

    <button type="submit" class="filter-search-btn">Search</button>
    <a href="inventory.php" class="filter-clear-btn">Clear</a>
</form>

<h2>Ingredients Inventory</h2>

<form method="POST" enctype="multipart/form-data">
    <input type="text"   name="ingredient_name"    placeholder="Name"          required>
    <input type="number" name="stock"               placeholder="Stock"         required>
    <input type="number" name="low_stock_threshold" placeholder="Low Threshold" required>
    <select name="unit">
        <option>Can-based</option>
        <option>Bottle-based</option>
        <option>Box-based</option>
        <option>Plastic-based</option>
        <option>Pack-based</option>
    </select>
    <input type="file" name="image">
    <button name="add_ingredient">Add</button>
</form>

<?php if ($editIng): ?>
<h3>Edit Ingredient</h3>
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="id"                  value="<?= $editIng['id'] ?>">
    <input type="text"   name="ingredient_name"     value="<?= htmlspecialchars($editIng['ingredient_name']) ?>">
    <input type="number" name="stock"               value="<?= $editIng['stock'] ?>">
    <input type="number" name="low_stock_threshold" value="<?= $editIng['low_stock_threshold'] ?>">
    <select name="unit">
        <option <?= $editIng['unit']=="Can-based"     ? 'selected' : '' ?>>Can-based</option>
        <option <?= $editIng['unit']=="Bottle-based"  ? 'selected' : '' ?>>Bottle-based</option>
        <option <?= $editIng['unit']=="Box-based"     ? 'selected' : '' ?>>Box-based</option>
        <option <?= $editIng['unit']=="Plastic-based" ? 'selected' : '' ?>>Plastic-based</option>
        <option <?= $editIng['unit']=="Pack-based"    ? 'selected' : '' ?>>Pack-based</option>
    </select>
    <input type="file" name="image">
    <button name="update_ing">Save</button>
    <a href="inventory.php"><button type="button">Cancel</button></a>
</form>
<?php endif; ?>

<div class="ingredient-grid">
<?php while ($row = $ingredients->fetch_assoc()):

    if ($row['stock'] <= $row['low_stock_threshold']) {
        $cls = 'low'; $barClass = 'bar-low'; $icon = '⚠️';
    } elseif ($row['stock'] <= $row['low_stock_threshold'] * 3) {
        $cls = 'mid'; $barClass = 'bar-mid'; $icon = '⚠️';
    } else {
        $cls = 'high'; $barClass = 'bar-high'; $icon = '✅';
    }

    $maxRef = max($row['low_stock_threshold'] * 3, 1);
    $pct    = min(100, round(($row['stock'] / $maxRef) * 100));
?>

<div class="ingredient-card">

    <?php if (!empty($row['image'])): ?>
        <img src="uploads/<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['ingredient_name']) ?>">
    <?php else: ?>
        <div class="img-placeholder">📦</div>
    <?php endif; ?>

    <div class="card-info">
        <div class="card-name"><?= htmlspecialchars($row['ingredient_name']) ?></div>
        <div class="card-stock-label">Stock Level:</div>
        <div class="stock-bar-wrap">
            <div class="stock-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="card-stock-count <?= $cls ?>">
            <?= $icon ?> <?= $row['stock'] ?> LEFT
        </div>
    </div>

    <div class="card-right">
        <div class="card-limit">Limit: <?= $row['low_stock_threshold'] ?></div>
        <div class="card-unit"><?= htmlspecialchars($row['unit']) ?></div>
        <div class="card-actions">
            <form method="POST" class="form-inline">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <button name="edit_ing" class="btn-edit">Edit</button>
            </form>
            <form method="POST" class="form-inline" onsubmit="return confirm('Delete ingredient?')">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <button name="delete_ing" class="btn-delete">Delete</button>
            </form>
        </div>
    </div>

</div>
<?php endwhile; ?>
</div>

</div>
</div>

<?php
$hasNotifs = ($notif !== null) || !empty($lowStockAlerts);
if ($hasNotifs):
?>
<div id="notif-container">

    <?php if ($notif !== null): ?>
        <?php if ($notif['type'] === 'deleted'): ?>
        <div class="notif-card notif-deleted">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">Deleted Successfully</div>
                <div class="notif-msg">
                    <?= htmlspecialchars($notif['name']) ?> has been successfully removed.
                </div>
            </div>
            <a href="inventory.php" class="notif-close-link">✕</a>
        </div>

        <?php elseif ($notif['type'] === 'added'): ?>
        <div class="notif-card notif-added">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">Added Successfully</div>
                <div class="notif-msg">
                    <?= htmlspecialchars($notif['name']) ?> has been successfully added.
                </div>
            </div>
            <a href="inventory.php" class="notif-close-link">✕</a>
        </div>

        <?php elseif ($notif['type'] === 'updated'): ?>
        <div class="notif-card notif-updated">
            <span class="notif-icon">ℹ️</span>
            <div class="notif-body">
                <div class="notif-title">Updated Successfully</div>
                <div class="notif-msg">
                    <?= htmlspecialchars($notif['name']) ?> has been successfully updated.
                </div>
            </div>
            <a href="inventory.php" class="notif-close-link">✕</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php foreach ($lowStockAlerts as $alert): ?>
    <div class="notif-card notif-lowstock">
        <span class="notif-icon">ℹ️</span>
        <div class="notif-body">
            <div class="notif-title">Low Stock Alert!</div>
            <div class="notif-msg">
                <?= htmlspecialchars($alert['label']) ?> is running low. Only
                <strong><?= $alert['stock'] ?> units remaining.</strong>
            </div>
        </div>
        <a href="inventory.php?stock_level=low" class="notif-close-link">✕</a>
    </div>
    <?php endforeach; ?>

</div>
<?php endif; ?>

</body>
</html>