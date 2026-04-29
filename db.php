<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "", "login");
if ($conn->connect_error){
    die("Database connection failed: " . $conn->connect_error);
}


function flash($type, $text){
    $_SESSION['flash'] = [
        'type' => $type,
        'text' => $text
    ];
}


function back(){
    header("Location: login.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    exit("Invalid access");
}

$action = $_POST['action'] ?? '';


if ($action === 'login') {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === '' || $password === '') {
        flash('err', 'All fields are required.');
        back();
    }


    $stmt = $conn->prepare("SELECT id, username, password, role, fullname FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'role' => $row['role'],
                'fullname' => $row['fullname'] ?? ''
            ];

            flash('ok', 'Login successful!');
            header("Location: home_page.php");
            exit;

        } else {
            flash('err', 'Invalid username or password.');
        }
    } else {
        flash('err', 'Invalid username or password.');
    }

    $stmt->close();
    back();
}



if ($action === 'register') {

    $fullname = trim($_POST['fullname']); 
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($fullname === '' || $username === '' || $password === '' || $confirm === '') {
        flash('err', 'All fields are required.');
        back();
    }

    if ($password !== $confirm) {
        flash('err', 'Passwords do not match.');
        back();
    }


    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        flash('err', 'Username already exists.');
        back();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

   
    $stmt = $conn->prepare("
        INSERT INTO users (fullname, username, password, role, status) 
        VALUES (?, ?, ?, 'user', 'active')
    ");
    $stmt->bind_param("sss", $fullname, $username, $hashed);

    if ($stmt->execute()) {
        flash('ok', 'Account created! You can now login.');
    } else {
        flash('err', 'Registration failed.');
    }

    $stmt->close();
    back();
}



if ($action === 'forgot_password') {

    $username = trim($_POST['username']);

    if ($username === '') {
        flash('err', 'Username required.');
        back();
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: create_new_password.php?user=" . urlencode($username));
        exit;
    } else {
        flash('err', 'User not found.');
        back();
    }
}



if ($action === 'create_new_password') {

    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        flash('err', 'Passwords do not match.');
        header("Location: create_new_password.php?user=" . urlencode($username));
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password=? WHERE username=?");
    $stmt->bind_param("ss", $hashed, $username);

    if ($stmt->execute()) {
        flash('ok', 'Password updated.');
        header("Location: login.php");
        exit;
    } else {
        flash('err', 'Update failed.');
        back();
    }
}
?>