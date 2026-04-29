<?php
function logAction($conn, $user, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $action, $details);
    $stmt->execute();
}
?>