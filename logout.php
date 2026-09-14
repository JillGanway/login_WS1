<?php

session_start();
require_once "db.php";


if (isset($_SESSION["admin_id"])) {

    $user_id = $_SESSION["admin_id"];
    $username = $_SESSION["admin_username"];

    $log = $conn->prepare(
        "INSERT INTO activity_logs
        (user_id, username, role, action)
        VALUES (?, ?, 'admin', 'Logout')"
    );

    $log->bind_param(
        "is",
        $user_id,
        $username
    );

    $log->execute();
    $log->close();
}

elseif (isset($_SESSION["user_id"])) {

    $user_id = $_SESSION["user_id"];
    $username = $_SESSION["username"];
    $role = $_SESSION["role"];

    $log = $conn->prepare(
        "INSERT INTO activity_logs
        (user_id, username, role, action)
        VALUES (?, ?, ?, 'Logout')"
    );

    $log->bind_param(
        "iss",
        $user_id,
        $username,
        $role
    );

    $log->execute();
    $log->close();
}

session_destroy();

header("Location: login.php");
exit;

?>