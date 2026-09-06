<?php
session_start();

require_once "../db.php";

$error = "";

// Built-in admin credentials
$builtin_username = "admin";
$builtin_password = "admin@123";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    // =========================================
    // BUILT-IN ADMIN LOGIN
    // =========================================
    if ($username === $builtin_username && $password === $builtin_password) {

        $_SESSION["admin_id"] = 1;
        $_SESSION["admin_username"] = $builtin_username;

        header("Location: dashboard.php");
        exit();

    }

    // =========================================
    // DATABASE ADMIN LOGIN
    // =========================================
    else {

        $sql = "SELECT * FROM admins WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) == 1) {

                $admin = mysqli_fetch_assoc($result);

                // Supports both hashed and plain-text passwords
                $password_match = password_verify($password, $admin["password"])
                    || $password === $admin["password"];

                if ($password_match) {

                    $_SESSION["admin_id"] = $admin["id"];
                    $_SESSION["admin_username"] = $admin["username"];

                    header("Location: dashboard.php");
                    exit();

                } else {
                    $error = "Invalid password";
                }

            } else {
                $error = "Invalid username or password";
            }

            mysqli_stmt_close($stmt);

        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | AI Prediction Market</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0f172a, #1e293b, #312e81);
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.97);
            padding: 40px 35px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
        }

        .logo {
            width: 75px;
            height: 75px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #334155;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: 0.3s;
        }

        input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 480px) {

            .login-card {
                padding: 30px 22px;
            }

            h2 {
                font-size: 24px;
            }

        }

    </style>

</head>
<body>

    <div class="login-container">

        <div class="login-card">

            <div class="logo">AI</div>

            <h2>Admin Login</h2>

            <p class="subtitle">
                AI Prediction Market
            </p>

            <?php if ($error != "") { ?>

                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php } ?>

            <form method="POST">

                <div class="form-group">

                    <label for="username">Username</label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter admin username"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter admin password"
                        required
                    >

                </div>

                <button type="submit" class="login-btn">
                    Login to Dashboard
                </button>

            </form>

            <div class="footer">
                © 2026 AI Prediction Market
            </div>

        </div>

    </div>

</body>
</html>