<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["team_id"])) {
    header("Location: login.php");
    exit();
}

$team_id = (int) $_SESSION["team_id"];

/* =====================================================
   GET ROUND NUMBER
===================================================== */

$round = isset($_GET["round"]) ? (int) $_GET["round"] : 1;

if ($round < 1) {
    $round = 1;
}

/* =====================================================
   GET TEAM DETAILS
===================================================== */

$sql = "SELECT team_name, coins
        FROM teams
        WHERE id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$team = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/* =====================================================
   TEAM NOT FOUND
===================================================== */

if (!$team) {
    session_destroy();

    header("Location: login.php");
    exit();
}

$team_name = htmlspecialchars($team["team_name"]);
$coins = (int) $team["coins"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>No Coins Remaining</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #151515, #30200d);
            color: white;
        }

        .box {
            width: 100%;
            max-width: 520px;
            padding: 40px 25px;
            text-align: center;
            border-radius: 20px;
            background: #211b13;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }

        .icon {
            font-size: 70px;
            margin-bottom: 15px;
        }

        h1 {
            margin: 0 0 15px;
            color: #ffb000;
            font-size: 30px;
        }

        p {
            color: #ddd;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .warning {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            background: #4a1f1f;
            border: 1px solid #ff5555;
            color: #ffb3b3;
            font-weight: bold;
        }

        .coins {
            margin: 25px 0;
            padding: 15px;
            border-radius: 10px;
            background: #342710;
            color: #ffc44d;
            font-size: 22px;
            font-weight: bold;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            padding: 13px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-primary {
            background: #ffb000;
            color: #201500;
        }

        .btn-primary:hover {
            background: #ffc44d;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #444;
            color: white;
        }

        .btn-secondary:hover {
            background: #666;
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 25px;
            }

            .btn {
                width: 100%;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="box">

    <div class="icon">🪙</div>

    <h1>No Coins Remaining</h1>

    <p>
        Hello <strong><?= $team_name ?></strong>.
        Your team has used all available coins.
        You cannot continue investing in this game.
    </p>

    <div class="warning">
        ⚠️ Your team has lost all available coins.
        Please view the round result.
    </div>

    <div class="coins">
        Available Coins: <?= $coins ?>
    </div>

    <div class="buttons">

        <!-- View the completed round result -->
        <a
            href="round_result.php?round=<?= $round ?>&reason=no_coins"
            class="btn btn-primary"
        >
            📊 Round Result
        </a>

        <!-- Return to dashboard -->
        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            🏠 Dashboard
        </a>

    </div>

</div>

</body>
</html>
