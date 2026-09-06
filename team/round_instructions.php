<?php
session_start();

require_once "../db.php";

/* ---------------------------------------
   LOGIN CHECK
--------------------------------------- */

if (!isset($_SESSION["team_id"])) {
    header("Location: login.php");
    exit();
}

$team_id = (int) $_SESSION["team_id"];

/* ---------------------------------------
   GET SELECTED ROUND
--------------------------------------- */

$round = (int) ($_SESSION["selected_round"] ?? 0);

if ($round < 1 || $round > 5) {
    header("Location: select_round.php");
    exit();
}

/* ---------------------------------------
   GET TEAM DETAILS
--------------------------------------- */

$sql = "SELECT id, team_name, coins, status
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

if (!$team) {
    session_destroy();
    header("Location: login.php");
    exit();
}

if (strtolower(trim($team["status"])) !== "active") {
    session_destroy();
    header("Location: login.php");
    exit();
}

$coins = (int) $team["coins"];

if ($coins <= 0) {
    header("Location: no_coins.php");
    exit();
}

/* ---------------------------------------
   START ROUND AFTER AGREEMENT
--------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "start_round") {

        /*
         * Important:
         * question = 0 means first question.
         * Timers start only after clicking Start Round.
         */

        $_SESSION["game"] = [
            "round" => $round,
            "question" => 0,
            "correct" => 0,
            "wrong" => 0,
            "timeout" => 0,
            "round_start" => time(),
            "question_start" => time()
        ];

        $_SESSION["selected_round"] = $round;
        $_SESSION["current_round"] = $round;
        $_SESSION["current_question"] = 0;

        /*
         * Do not redirect to round_result.php here.
         */
        header("Location: game.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Round Instructions | AI Prediction Market</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #172033;
            min-height: 100vh;
        }

        .header {
            height: 72px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 7%;
        }

        .logo {
            font-size: 21px;
            font-weight: 800;
            color: #172033;
        }

        .logo span {
            color: #2563eb;
        }

        .logout {
            text-decoration: none;
            color: #475569;
            border: 1px solid #dbe1e8;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
        }

        .logout:hover {
            background: #f8fafc;
            color: #2563eb;
        }

        .container {
            width: 92%;
            max-width: 850px;
            margin: 50px auto;
        }

        .heading {
            text-align: center;
            margin-bottom: 30px;
        }

        .heading h1 {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .heading p {
            color: #64748b;
            font-size: 15px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 35px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        }

        .round-title {
            text-align: center;
            color: #2563eb;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 25px;
        }

        .rules {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .rule {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px 10px;
            text-align: center;
        }

        .rule-icon {
            font-size: 25px;
            margin-bottom: 10px;
        }

        .rule strong {
            display: block;
            font-size: 16px;
            margin-bottom: 6px;
        }

        .rule span {
            color: #64748b;
            font-size: 13px;
        }

        .instructions {
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .instructions h3 {
            margin-bottom: 12px;
            font-size: 17px;
        }

        .instructions ul {
            padding-left: 20px;
            color: #475569;
            line-height: 2;
            font-size: 14px;
        }

        .warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .agree-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            color: #475569;
            font-size: 14px;
        }

        .agree-row input {
            width: 17px;
            height: 17px;
            accent-color: #2563eb;
        }

        .start-btn {
            width: 100%;
            border: none;
            background: #2563eb;
            color: #ffffff;
            padding: 16px;
            border-radius: 9px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
        }

        .start-btn:hover {
            background: #1d4ed8;
        }

        .back-btn {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            color: #2563eb;
        }

        @media (max-width: 650px) {
            .container {
                margin: 25px auto;
            }

            .card {
                padding: 22px;
            }

            .rules {
                grid-template-columns: 1fr;
            }

            .heading h1 {
                font-size: 25px;
            }

            .header {
                padding: 0 5%;
            }

            .logo {
                font-size: 17px;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <div class="logo">
        AI <span>PREDICTION MARKET</span>
    </div>

    <a href="logout.php" class="logout">
        Logout
    </a>
</header>

<main class="container">

    <div class="heading">
        <h1>Round Instructions</h1>
        <p>Read the rules carefully before starting the game.</p>
    </div>

    <div class="card">

        <div class="round-title">
            Round <?php echo $round; ?>
        </div>

        <div class="rules">

            <div class="rule">
                <div class="rule-icon">📝</div>
                <strong>5 Questions</strong>
                <span>Answer all questions</span>
            </div>

            <div class="rule">
                <div class="rule-icon">⏱️</div>
                <strong>60 Seconds</strong>
                <span>Each question timer</span>
            </div>

            <div class="rule">
                <div class="rule-icon">⌛</div>
                <strong>5 Minutes</strong>
                <span>Complete round timer</span>
            </div>

        </div>

        <div class="instructions">
            <h3>Game Rules</h3>

            <ul>
                <li>Each question has a maximum time of 60 seconds.</li>
                <li>The complete round must be finished within 5 minutes.</li>
                <li>The AI prediction percentage will be displayed below the question.</li>
                <li>Select either Correct or Incorrect as your answer.</li>
                <li>Enter the number of coins you want to invest.</li>
                <li>Correct answers add your invested coins.</li>
                <li>Wrong answers subtract your invested coins.</li>
                <li>If the question timer expires, 10 coins will be deducted automatically.</li>
                <li>After submitting, the next question will open automatically.</li>
            </ul>
        </div>

        <div class="warning">
            ⚠️ Once the round starts, the timer cannot be paused.
            Please do not refresh or close the game page.
        </div>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="start_round"
            >

            <label class="agree-row">
                <input
                    type="checkbox"
                    id="agree"
                    required
                >

                I have read and agree to the game rules.
            </label>

            <button
                type="submit"
                class="start-btn"
            >
                I Agree & Start Round →
            </button>

        </form>

        <a href="select_round.php" class="back-btn">
            ← Back to Select Round
        </a>

    </div>

</main>

</body>
</html>