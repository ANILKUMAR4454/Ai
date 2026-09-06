<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| TEAM AUTHENTICATION
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["team_id"])) {
    header("Location: login.php");
    exit();
}

$team_id = (int) $_SESSION["team_id"];

/*
|--------------------------------------------------------------------------
| GAME SETTINGS
|--------------------------------------------------------------------------
*/
$TOTAL_ROUNDS = 5;
$QUESTIONS_PER_ROUND = 5;
$QUESTION_TIME = 60;
$ROUND_TIME = 300;

/*
|--------------------------------------------------------------------------
| GET TEAM DETAILS
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        id,
        team_code,
        team_name,
        coins,
        current_round,
        current_question,
        current_at,
        status
    FROM teams
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$team = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| TEAM VALIDATION
|--------------------------------------------------------------------------
*/
if (!$team) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

if (strtolower(trim($team["status"])) !== "active") {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| COINS CHECK
|--------------------------------------------------------------------------
*/
$coins = (int) $team["coins"];

if ($coins <= 0) {
    header("Location: no_coins.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| CURRENT ROUND
|--------------------------------------------------------------------------
|
| 0 = New team, Round 1 available
| 1 = Round 1 available
| 2 = Round 2 available
| 3 = Round 3 available
| 4 = Round 4 available
| 5 = Round 5 available
| 6 = All rounds completed
|
|--------------------------------------------------------------------------
*/
$current_round = (int) $team["current_round"];

if ($current_round <= 0) {
    $allowed_round = 1;
} elseif ($current_round >= 6) {
    $allowed_round = 6;
} else {
    $allowed_round = $current_round;
}

/*
|--------------------------------------------------------------------------
| START SELECTED ROUND
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $round = (int) ($_POST["round"] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | VALID ROUND CHECK
    |--------------------------------------------------------------------------
    */
    if ($round < 1 || $round > $TOTAL_ROUNDS) {
        header("Location: select_round.php?error=invalid_round");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK LATEST TEAM COINS
    |--------------------------------------------------------------------------
    */
    $coin_sql = "
        SELECT coins, status
        FROM teams
        WHERE id = ?
        LIMIT 1
    ";

    $coin_stmt = mysqli_prepare($conn, $coin_sql);

    if (!$coin_stmt) {
        die("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($coin_stmt, "i", $team_id);
    mysqli_stmt_execute($coin_stmt);

    $coin_result = mysqli_stmt_get_result($coin_stmt);
    $latest_team = mysqli_fetch_assoc($coin_result);

    mysqli_stmt_close($coin_stmt);

    $latest_coins = (int) ($latest_team["coins"] ?? 0);
    $latest_status = strtolower(
        trim($latest_team["status"] ?? "")
    );

    if ($latest_status !== "active") {
        session_unset();
        session_destroy();

        header("Location: login.php");
        exit();
    }

    if ($latest_coins <= 0) {
        header("Location: no_coins.php");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | ONLY CURRENT ROUND CAN BE SELECTED
    |--------------------------------------------------------------------------
    */
    if ($round !== $allowed_round) {
        header("Location: select_round.php?error=locked");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE TEAM ROUND
    |--------------------------------------------------------------------------
    */
    $update_sql = "
        UPDATE teams
        SET
            current_round = ?,
            current_question = 1,
            current_at = NULL
        WHERE id = ?
    ";

    $update_stmt = mysqli_prepare($conn, $update_sql);

    if (!$update_stmt) {
        die("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $update_stmt,
        "ii",
        $round,
        $team_id
    );

    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    /*
    |--------------------------------------------------------------------------
    | SAVE ROUND IN SESSION
    |--------------------------------------------------------------------------
    */
    $_SESSION["selected_round"] = $round;
    $_SESSION["current_round"] = $round;
    $_SESSION["current_question"] = 1;

    unset($_SESSION["round_started_at"]);

    /*
    |--------------------------------------------------------------------------
    | GO TO ROUND INSTRUCTIONS
    |--------------------------------------------------------------------------
    */
    header("Location: round_instructions.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| ERROR MESSAGE
|--------------------------------------------------------------------------
*/
$error = "";

if (isset($_GET["error"])) {

    if ($_GET["error"] === "invalid_round") {
        $error = "Invalid round selected.";
    }

    if ($_GET["error"] === "locked") {
        $error = "Please complete the current round first.";
    }
}

/*
|--------------------------------------------------------------------------
| GAME COMPLETION
|--------------------------------------------------------------------------
*/
$game_completed = ($current_round >= 6);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Select Round - AI Prediction Market</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .header {
            background: #111827;
            color: white;
            padding: 18px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 23px;
            font-weight: bold;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .team-name {
            font-weight: bold;
        }

        .coins {
            background: #f59e0b;
            color: #111827;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
        }

        .logout {
            background: #dc2626;
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 7px;
        }

        .logout:hover {
            background: #b91c1c;
        }

        .container {
            width: 92%;
            max-width: 1100px;
            margin: 40px auto;
        }

        .title {
            text-align: center;
            margin-bottom: 10px;
        }

        .title h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .title p {
            color: #6b7280;
        }

        .error {
            max-width: 600px;
            margin: 25px auto 0;
            padding: 14px;
            border-radius: 8px;
            text-align: center;
            background: #fee2e2;
            color: #991b1b;
            font-weight: bold;
        }

        .round-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-top: 35px;
        }

        .round-card {
            background: white;
            border-radius: 15px;
            padding: 25px 18px;
            text-align: center;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
            transition: 0.2s;
        }

        .round-card:hover {
            transform: translateY(-5px);
        }

        .round-card.locked {
            opacity: 0.55;
        }

        .round-number {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: bold;
        }

        .round-card.locked .round-number {
            background: #9ca3af;
        }

        .round-card h2 {
            margin-bottom: 15px;
        }

        .round-info {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .select-btn {
            width: 100%;
            border: none;
            background: #2563eb;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .select-btn:hover {
            background: #1d4ed8;
        }

        .select-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .completed-message {
            max-width: 600px;
            margin: 35px auto;
            background: white;
            padding: 30px;
            text-align: center;
            border-radius: 15px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
        }

        .completed-message h2 {
            margin-bottom: 12px;
        }

        .result-btn,
        .back-btn {
            display: block;
            width: 220px;
            margin: 20px auto 0;
            text-align: center;
            padding: 12px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .result-btn {
            background: #16a34a;
        }

        .back-btn {
            background: #6b7280;
        }

        @media (max-width: 900px) {
            .round-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 550px) {
            .header {
                padding: 15px;
            }

            .team-name {
                display: none;
            }

            .round-grid {
                grid-template-columns: 1fr;
            }

            .container {
                width: 94%;
            }
        }
    </style>
</head>

<body>

<header class="header">

    <div class="logo">
        AI Prediction Market
    </div>

    <div class="header-right">

        <span class="team-name">
            <?= htmlspecialchars(
                $team["team_name"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </span>

        <span class="coins">
            🪙 <?= $coins ?>
        </span>

        <a href="logout.php" class="logout">
            Logout
        </a>

    </div>

</header>

<div class="container">

    <div class="title">
        <h1>Select Round</h1>
        <p>Complete each round in order</p>
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </div>

    <?php endif; ?>

    <?php if ($game_completed): ?>

        <div class="completed-message">

            <h2>🏆 Game Completed</h2>

            <p>
                You have completed all 5 rounds.
            </p>

            <a href="result.php" class="result-btn">
                View Final Result
            </a>

        </div>

    <?php else: ?>

        <div class="round-grid">

            <?php for (
                $round = 1;
                $round <= $TOTAL_ROUNDS;
                $round++
            ): ?>

                <?php
                $is_allowed = ($round === $allowed_round);
                $is_completed = ($round < $allowed_round);
                $is_locked = ($round > $allowed_round);
                ?>

                <div class="round-card <?= $is_locked ? "locked" : "" ?>">

                    <div class="round-number">
                        <?= $round ?>
                    </div>

                    <h2>
                        Round <?= $round ?>
                    </h2>

                    <div class="round-info">
                        📝 <?= $QUESTIONS_PER_ROUND ?> Questions<br>
                        ⏱️ <?= $QUESTION_TIME ?> Seconds / Question<br>
                        ⏳ Maximum <?= $ROUND_TIME / 60 ?> Minutes
                    </div>

                    <?php if ($is_completed): ?>

                        <button class="select-btn" disabled>
                            ✓ Completed
                        </button>

                    <?php elseif ($is_allowed): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="round"
                                value="<?= $round ?>"
                            >

                            <button
                                type="submit"
                                class="select-btn"
                            >
                                Select Round <?= $round ?>
                            </button>

                        </form>

                    <?php else: ?>

                        <button class="select-btn" disabled>
                            🔒 Locked
                        </button>

                    <?php endif; ?>

                </div>

            <?php endfor; ?>

        </div>

    <?php endif; ?>

    <a href="dashboard.php" class="back-btn">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>