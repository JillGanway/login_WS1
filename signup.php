<?php
require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"]);
    $username = trim($_POST["username"]);
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (
        $full_name === "" ||
        $username === "" ||
        $role === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {

        $message = "Please complete every field.";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";

    } elseif ($role !== "teacher" && $role !== "student") {

        $message = "Please choose Teacher or Student.";

    } else {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ?"
        );

        $check->bind_param("s", $username);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "Username already exists.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users
                (full_name, username, password, is_admin, role, status)
                VALUES (?, ?, ?, 0, ?, 'pending')"
            );

            $stmt->bind_param(
                "ssss",
                $full_name,
                $username,
                $hashed_password,
                $role
            );

            if ($stmt->execute()) {


                $title = "New Account Request";

                $notification_message =
                    $full_name . " (" . $role .
                    ") created a new account and is waiting for approval.";

                $type = "account";

                $notify = $conn->prepare(
                    "INSERT INTO notifications
                    (title, message, type)
                    VALUES (?, ?, ?)"
                );

                $notify->bind_param(
                    "sss",
                    $title,
                    $notification_message,
                    $type
                );

                $notify->execute();
                $notify->close();

                header("Location: login.php");
                exit;

            } else {

                $message = "Unable to create account.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up ⋆.˚</title>

    <link rel="stylesheet" href="signup.css">

</head>

<body>

<div class="signup-container">

    <form class="signup-box" method="POST">

        <h2>⋆｡⋆୨୧˚Create Account𐙚˙⋆˚ᡣ𐭩</h2>

        <?php if ($message): ?>

            <p class="error-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

        <?php endif; ?>

        <p class="subtitle">
            Welcome! Please create your account⋆.˚
        </p>

        <div class="input-box">

            <label>⋆ ˚｡⋆୨୧˚Full Name˚୨୧⋆｡</label>

            <input
                type="text"
                name="full_name"
                placeholder="Enter your full name"
                required
            >

        </div>

        <div class="input-box">

            <label>⋆ ˚｡⋆୨୧˚Username˚୨୧⋆｡</label>

            <input
                type="text"
                name="username"
                placeholder="Create a username"
                required
            >

        </div>

        <div class="input-box">

            <label>⋆ ˚｡⋆୨୧˚User˚୨୧⋆｡</label>

            <div class="user">

                <div>
                    <label>Teacher</label>
                    <input
                        type="radio"
                        name="role"
                        value="teacher"
                        required
                    >
                </div>

                <div>
                    <label>Student</label>
                    <input
                        type="radio"
                        name="role"
                        value="student"
                    >
                </div>

            </div>

        </div>

        <div class="input-box">

            <label>⋆ ˚｡⋆୨୧˚Password˚୨୧⋆｡</label>

            <input
                type="password"
                name="password"
                placeholder="Create a password"
                required
            >

        </div>

        <div class="input-box">

            <label>⋆ ˚｡⋆୨୧˚Confirm Password˚୨୧⋆｡</label>

            <input
                type="password"
                name="confirm_password"
                placeholder="Confirm your password"
                required
            >

        </div>

        <button type="submit">
            Sign Up, yehey!♡
        </button>

        <p class="login-link">

            Already have an account? ⋆.˚

            <a href="login.php">Login</a>

        </p>

    </form>

</div>

</body>

</html>
