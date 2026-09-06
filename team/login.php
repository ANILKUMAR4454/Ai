<?php

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* =========================================================
   ALREADY LOGGED IN
========================================================= */

if (isset($_SESSION["team_id"])) {
    header("Location: dashboard.php");
    exit();
}

/* =========================================================
   VARIABLES
========================================================= */

$error = "";

$team_code = "";

/* =========================================================
   LOGIN
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $team_code = trim($_POST["team_code"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($team_code === "" || $password === "") {

        $error = "Please enter team code and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                team_code,
                team_name,
                password,
                coins,
                current_round,
                current_question,
                current_at,
                status
            FROM teams
            WHERE team_code = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $team_code);
        $stmt->execute();

        $result = $stmt->get_result();
        $team = $result->fetch_assoc();

        $stmt->close();

        /* =================================================
           CHECK LOGIN
        ================================================= */

        if (!$team) {

            $error = "Invalid team code or password.";

        } elseif (!password_verify($password, $team["password"])) {

            $error = "Invalid team code or password.";

        } elseif ($team["status"] !== "Active") {

            $error = "This team account is inactive.";

        } else {

            /* =============================================
               LOGIN SUCCESS
            ============================================= */

            session_regenerate_id(true);

            $_SESSION["team_id"] = (int)$team["id"];
            $_SESSION["team_code"] = $team["team_code"];
            $_SESSION["team_name"] = $team["team_name"];

            $_SESSION["coins"] = (int)$team["coins"];
            $_SESSION["current_round"] = (int)$team["current_round"];
            $_SESSION["current_question"] = (int)$team["current_question"];

            header("Location: dashboard.php");
            exit();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Team Login</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6fb;
        }

        .login-box {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
        }

        .logo {
            width: 65px;
            height: 65px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: #eef2ff;
            font-size: 30px;
        }

        h1 {
            text-align: center;
            font-size: 28px;
            margin-bottom: 8px;
            color: #111827;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
            color: #374151;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #6366f1;
        }

        button {
            width: 100%;
            border: none;
            padding: 14px;
            border-radius: 10px;
            background: #4f46e5;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #4338ca;
        }

        .info {
            margin-top: 20px;
            padding: 13px;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
            font-size: 12px;
        }

        @media (max-width: 480px) {

            .login-box {
                padding: 25px 20px;
            }

            h1 {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>

<div class="login-box">

    <div class="logo">
        🤖
    </div>

    <h1>Team Login</h1>

    <p class="subtitle">
        AI Prediction Market
    </p>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <div class="form-group">

            <label for="team_code">
                Team Code
            </label>

            <input
                type="text"
                id="team_code"
                name="team_code"
                value="<?= htmlspecialchars($team_code) ?>"
                placeholder="Enter team code"
                required
                autofocus
            >

        </div>


        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter password"
                required
            >

        </div>


        <button type="submit">
            Login
        </button>

    </form>


    <div class="info">
        Enter your team code and password to start the prediction game.
    </div>

</div>

</body>

</html>