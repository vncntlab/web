<?php
session_start();

if (!isset($_GET['user']) || empty($_GET['user'])) {
    $_SESSION['flash'] = ['type' => 'err', 'text' => 'Invalid link.'];
    header('Location: login.php');
    exit;
}

$username = $_GET['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Create New Password</title>
<link rel="stylesheet" href="resources/style.css" />
</head>
<body>
<?php 
if (!empty($_SESSION['flash'])) {
    $type = $_SESSION['flash']['type'];
    $text = $_SESSION['flash']['text'];
    echo "<div class='msg-" . htmlspecialchars($type) . "'>" . htmlspecialchars($text) . "</div>";
    unset($_SESSION['flash']);
}
?>
<div class="container">
    <h2>Create New Password</h2>
    <form action="db.php" method="POST">
        <input type="hidden" name="action" value="create_new_password" />
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>" />
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="password" required />
        <br>
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required />
        <br>
        <button type="submit">Change Password</button>
    </form>
</div>
</body>
</html>