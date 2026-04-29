<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Reset Password</title>
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
    <h2>Forgot Password</h2>
    <form action="db.php" method="POST">
        <input type="hidden" name="action" value="forgot_password" />
        <label for="fp_username">Enter your username</label>
        <input type="text" id="fp_username" name="username" required />
        <br>
        <button type="submit">Reset Password</button>
    </form>
</div>
</body>
</html>