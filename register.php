<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register</title>
<link rel="stylesheet" href="resources/style.css">
</head>
<body>

<div class="flash-message-container">
<?php 
if (!empty($_SESSION['flash'])) {
    $class = ($_SESSION['flash']['type'] === 'err') ? 'msg-err' : 'msg-ok';
    echo "<div class='message {$class}'>" . htmlspecialchars($_SESSION['flash']['text']) . "</div>";
    unset($_SESSION['flash']);
}
?>
</div>

<div class="container">
    <h2>Create Account</h2>

    <form action="db.php" method="POST">
        <input type="hidden" name="action" value="register">

        <!-- ✅ NEW FIELD -->
        <label>Full Name</label>
        <input type="text" name="fullname" required>

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Register</button>
    </form>

    <br>
    <a href="login.php">Back to Login</a>
</div>

</body>
</html>