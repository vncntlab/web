<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error) die("DB Error");

if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $price = floatval($_POST['price']);
    $category = $_POST['category'];
    $status = $_POST['status'];

    $stock = intval($_POST['stock']);
    $low = intval($_POST['low_stock_threshold']);

    $image = "";
    if (!empty($_FILES['image']['name'])) {
        $image = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
    }

    $conn->query("INSERT INTO products 
        (name, price, category, status, image, stock, low_stock_threshold)
        VALUES 
        ('$name', $price, '$category', '$status', '$image', $stock, $low)");

    header("Location: products.php");
    exit();
}

if (isset($_POST['delete'])) {
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM products WHERE id=$id");

    header("Location: products.php");
    exit();
}

$editProduct = null;

if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $res = $conn->query("SELECT * FROM products WHERE id=$id");
    $editProduct = $res->fetch_assoc();
}

if (isset($_POST['save_edit'])) {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $price = floatval($_POST['price']);
    $category = $_POST['category'];
    $status = $_POST['status'];

    $stock = intval($_POST['stock']);
    $low = intval($_POST['low_stock_threshold']);

    $image_sql = "";

    if (!empty($_FILES['image']['name'])) {
        $image = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
        $image_sql = ", image='$image'";
    }

    $conn->query("UPDATE products 
        SET name='$name',
            price=$price,
            category='$category',
            status='$status',
            stock=$stock,
            low_stock_threshold=$low
            $image_sql
        WHERE id=$id");

    header("Location: products.php");
    exit();
}

$search           = $_GET['search']   ?? '';
$selectedCategory = $_GET['category'] ?? '';
$selectedStatus   = $_GET['status']   ?? '';

$where = "1=1";

if (!empty($search)) {
    $where .= " AND name LIKE '%$search%'";
}

if (!empty($selectedCategory)) {
    $where .= " AND category='$selectedCategory'";
}

if (!empty($selectedStatus)) {
    $where .= " AND status='$selectedStatus'";
}

$products = $conn->query("SELECT * FROM products WHERE $where");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product Management</title>
<link rel="stylesheet" href="../resources/main_css.css">

<style>
img { width:60px; height:60px; object-fit:cover; }
</style>

</head>

<body>

<div class="title-bar">
  Al Coffee's Sales and Inventory Management System
</div>

<div class="container">

<div class="sidebar">
  <h2>MENU</h2>
  <ul>
    <li><a href="/login/home_page.php">Dashboard</a></li>
    <li><a href="products.php">Products</a></li>
    <li><a href="inventory.php">Inventory</a></li>
    <li><a href="sales.php">Sales</a></li>
    <li><a href="reports_analysis.php">Reports</a></li>
    <li><a href="admin.php">Admin</a></li>

    <li>
      <a href="/login/logout.php" 
         style="color:red;" 
         onclick="return confirm('Are you sure you want to log out?')">
         Logout
      </a>
    </li>
  </ul>
</div>

<div class="main">

<h1>PRODUCT REGISTRY</h1>

<h2>Add Product</h2>
<form method="POST" enctype="multipart/form-data">

    <input type="text" name="name" placeholder="Product Name" required>
    <input type="number" step="0.01" name="price" placeholder="Price" required>

    <input type="number" name="stock" placeholder="Stock" required>
    <input type="number" name="low_stock_threshold" placeholder="Low Stock Threshold" required>

    <select name="category">
        <option>Hot Coffee</option>
        <option>Iced Coffee</option>
        <option>Matcha Series</option>
        <option>Non-Coffee</option>
        <option>Snacks</option>
        <option>Add Ons</option>
    </select>

    <select name="status">
        <option>Available</option>
        <option>Unavailable</option>
    </select>

    <input type="file" name="image">
    <button name="add_product">Add Product</button>
</form>

<h2>Search & Filter</h2>
<form method="GET">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search...">

    <select name="category">
        <option value="">Select Category</option>
        <option value="Hot Coffee"     <?= $selectedCategory === 'Hot Coffee'     ? 'selected' : '' ?>>Hot Coffee</option>
        <option value="Iced Coffee"    <?= $selectedCategory === 'Iced Coffee'    ? 'selected' : '' ?>>Iced Coffee</option>
        <option value="Matcha Series"  <?= $selectedCategory === 'Matcha Series'  ? 'selected' : '' ?>>Matcha Series</option>
        <option value="Non-Coffee"     <?= $selectedCategory === 'Non-Coffee'     ? 'selected' : '' ?>>Non-Coffee</option>
        <option value="Snacks"         <?= $selectedCategory === 'Snacks'         ? 'selected' : '' ?>>Snacks</option>
        <option value="Add Ons"        <?= $selectedCategory === 'Add Ons'        ? 'selected' : '' ?>>Add Ons</option>
    </select>

    <select name="status">
        <option value="">Select Status</option>
        <option value="Available"   <?= $selectedStatus === 'Available'   ? 'selected' : '' ?>>Available</option>
        <option value="Unavailable" <?= $selectedStatus === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
    </select>

    <button>Apply</button>
    <a href="products.php">Clear</a>
</form>

<?php if ($editProduct): ?>
<h2>Edit Product</h2>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="id" value="<?= $editProduct['id'] ?>">

<input type="text" name="name" value="<?= $editProduct['name'] ?>">
<input type="number" step="0.01" name="price" value="<?= $editProduct['price'] ?>">

<input type="number" name="stock" value="<?= $editProduct['stock'] ?>">
<input type="number" name="low_stock_threshold" value="<?= $editProduct['low_stock_threshold'] ?>">

<select name="category">
    <option <?= $editProduct['category']=="Hot Coffee"?'selected':'' ?>>Hot Coffee</option>
    <option <?= $editProduct['category']=="Iced Coffee"?'selected':'' ?>>Iced Coffee</option>
    <option <?= $editProduct['category']=="Matcha Series"?'selected':'' ?>>Matcha Series</option>
    <option <?= $editProduct['category']=="Non-Coffee"?'selected':'' ?>>Non-Coffee</option>
    <option <?= $editProduct['category']=="Snacks"?'selected':'' ?>>Snacks</option>
    <option <?= $editProduct['category']=="Add Ons"?'selected':'' ?>>Add Ons</option>
</select>

<select name="status">
    <option <?= $editProduct['status']=="Available"?'selected':'' ?>>Available</option>
    <option <?= $editProduct['status']=="Unavailable"?'selected':'' ?>>Unavailable</option>
</select>

<input type="file" name="image">

<button name="save_edit">Save Changes</button>

</form>
<?php endif; ?>

<h2>Product List</h2>

<table border="1" width="100%">
<tr>
<th>Image</th>
<th>Name</th>
<th>Price</th>
<th>Category</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php while($row = $products->fetch_assoc()): ?>
<tr>

<td>
<?php if ($row['image']): ?>
<img src="uploads/<?= $row['image'] ?>">
<?php endif; ?>
</td>

<td><?= htmlspecialchars($row['name']) ?></td>
<td>₱<?= number_format($row['price'],2) ?></td>
<td><?= $row['category'] ?></td>

<td>
<?= $row['status']=="Available"
? '<span style="color:green;font-weight:bold;">Available</span>'
: '<span style="color:red;font-weight:bold;">Unavailable</span>' ?>
</td>

<td>
<form method="POST" style="display:inline;">
    <input type="hidden" name="id" value="<?= $row['id'] ?>">
    <button name="edit">Edit</button>
</form>

<form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
    <input type="hidden" name="id" value="<?= $row['id'] ?>">
    <button name="delete">Delete</button>
</form>
</td>

</tr>
<?php endwhile; ?>
</table>

</div>
</div>

</body>
</html>