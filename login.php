<?php
session_start();
require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "SELECT id, full_name, username, password,
                is_admin, role, status
         FROM users
         WHERE username = ?"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    if (!$user) {

        $message = "Invalid username or password.";

    }

    /* ADMIN */

    elseif (
        $user["is_admin"] == 1 &&
        $username === "admin" &&
        $password === "Admin123"
    ) {

        $_SESSION["admin_id"] = $user["id"];
        $_SESSION["admin_username"] = $user["username"];

        $log = $conn->prepare(
            "INSERT INTO activity_logs
            (user_id, username, role, action)
            VALUES (?, ?, 'admin', 'Login')"
        );

        $log->bind_param(
            "is",
            $user["id"],
            $user["username"]
        );

        $log->execute();
        $log->close();

        header("Location: admin_dashboard.php");
        exit;
    }

    /* WRONG ADMIN PASSWORD */

    elseif ($user["is_admin"] == 1) {

        $message = "Invalid username or password.";
    }

    /* PENDING ACCOUNT */

    elseif ($user["status"] !== "approved") {

        $message = "Your account is waiting for admin approval.";
    }

    /* TEACHER / STUDENT */

    elseif (password_verify($password, $user["password"])) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        /* SAVE LOGIN HISTORY */

        $log = $conn->prepare(
            "INSERT INTO activity_logs
            (user_id, username, role, action)
            VALUES (?, ?, ?, 'Login')"
        );

        $log->bind_param(
            "iss",
            $user["id"],
            $user["username"],
            $user["role"]
        );

        $log->execute();
        $log->close();

        if ($user["role"] === "teacher") {

            header("Location: teacher_dashboard.php");
            exit;

        }

        if ($user["role"] === "student") {

            header("Location: student_dashboard.php");
            exit;
        }

    }

    else {

        $message = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login Page</title>

    <link rel="stylesheet" href="login.css">

</head>

<body>

<div class="login-container">

    <form class="login-box" method="POST">

        <h2>*♡Welcomeʚ˚⋆🦢</h2>

        <?php if ($message): ?>

            <p class="error-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

        <?php endif; ?>

        <div class="input-box">

            <label>Username</label>

            <input
                type="text"
                name="username"
                placeholder="Enter username"
                autocomplete="off"
                required
            >

        </div>

        <div class="input-box">

            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Enter password"
                required
            >

        </div>

        <button type="submit">
            Login pls
        </button>

        <p class="signup-link">

            No account yet?

            <a href="signup.php">
                Sign up
            </a>

        </p>

        <p> Username:admin
            <br> password:Admin123
        </p>

        <p> username: Jills
            <br> password: Ganz123
        </p>

        <p> usernams: Jillieyy
            <br> password: Jillian05
        </p>
        
    </form>

</div>

</body>

</html>