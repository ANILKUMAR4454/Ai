<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* =====================================================
   TEAM LOGIN CHECK
===================================================== */

if (!isset($_SESSION["team_id"])) {
    header("Location: login.php");
    exit();
}

$team_id = (int) $_SESSION["team_id"];

/* =====================================================
   GET TEAM DETAILS
===================================================== */

$sql = "
    SELECT
        id,
        team_name,
        team_code,
        coins,
        current_round,
        current_question,
        status
    FROM teams
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Team query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$team = mysqli_fetch_assoc($result);

if (!$team) {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* =====================================================
   SELECT ROUND
===================================================== */

$round = isset($_GET["round"])
    ? (int) $_GET["round"]
    : (int) ($team["current_round"] ?? 1);

if ($round < 1) {
    $round = 1;
}

if ($round > 5) {
    $round = 5;
}

/* =====================================================
   ROUND STATISTICS
===================================================== */

$total_questions = 0;
$correct_answers = 0;
$wrong_answers = 0;
$total_time = 0;

$sql = "
    SELECT
        COUNT(*) AS total_questions,

        COALESCE(
            SUM(
                CASE
                    WHEN is_correct = 1 THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS correct_answers,

        COALESCE(
            SUM(
                CASE
                    WHEN is_correct = 0 THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS wrong_answers,

        COALESCE(SUM(time_taken), 0) AS total_time

    FROM team_answers

    WHERE team_id = ?
      AND round_number = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Statistics query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "ii", $team_id, $round);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);

if ($stats) {
    $total_questions = (int) $stats["total_questions"];
    $correct_answers = (int) $stats["correct_answers"];
    $wrong_answers = (int) $stats["wrong_answers"];
    $total_time = (int) $stats["total_time"];
}

/* =====================================================
   ACCURACY CALCULATION
===================================================== */

$accuracy = 0;

if ($total_questions > 0) {
    $accuracy = round(
        ($correct_answers / $total_questions) * 100,
        1
    );
}

/*
   Keep accuracy between 0 and 100.
*/
$accuracy = max(0, min(100, $accuracy));

/* =====================================================
   NEXT ROUND
===================================================== */

$TOTAL_ROUNDS = 5;
$next_round = $round + 1;

$has_next_round = $next_round <= $TOTAL_ROUNDS;

/* =====================================================
   NO COINS CHECK
===================================================== */

$current_coins = (int) ($team["coins"] ?? 0);

$reason = $_GET["reason"] ?? "";

$no_coins = (
    $reason === "no_coins" ||
    $current_coins <= 0
);

/* =====================================================
   FORMAT TIME
===================================================== */

$minutes = floor($total_time / 60);
$seconds = $total_time % 60;

$formatted_time = sprintf(
    "%02d:%02d",
    $minutes,
    $seconds
);

/* =====================================================
   SAFE DISPLAY VALUES
===================================================== */

$team_name = htmlspecialchars(
    $team["team_name"] ?? "Team",
    ENT_QUOTES,
    "UTF-8"
);

$team_code = htmlspecialchars(
    $team["team_code"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

$round_title = "Round " . $round;

/*
   Pie chart:
   Correct percentage = actual accuracy.
*/
$correct_percentage = $accuracy;
$wrong_percentage = 100 - $accuracy;

/*
   Display total questions as completed/expected.
   Your game currently uses 5 questions per round.
*/
$QUESTIONS_PER_ROUND = 5;

$question_display =
    $total_questions . "/" . $QUESTIONS_PER_ROUND;

if ($no_coins) {
    $notice_title = "Game stopped because coins reached zero.";
    $notice_message =
        "Your investment balance reached zero. " .
        "You cannot continue to the next round.";
    $notice_icon = "⚠️";
} elseif ($has_next_round) {
    $notice_title = "Round completed successfully.";
    $notice_message =
        "Your answers have been saved. " .
        "You can continue to the next round.";
    $notice_icon = "✅";
} else {
    $notice_title = "All rounds completed.";
    $notice_message =
        "Congratulations! You have completed all five rounds.";
    $notice_icon = "🏆";
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

    <title><?= htmlspecialchars($round_title) ?> Result</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #ca8a04;
            --dark: #0f172a;
            --text: #172033;
            --muted: #64748b;
            --border: #e2e8f0;
            --surface: #ffffff;
            --background: #f4f7fb;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    #dbeafe 0,
                    transparent 35%
                ),
                var(--background);
            color: var(--text);
            min-height: 100vh;
        }

        .topbar {
            background: rgba(15, 23, 42, 0.98);
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.15);
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(
                135deg,
                #2563eb,
                #7c3aed
            );
            font-size: 22px;
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.35);
        }

        .brand {
            font-size: 21px;
            font-weight: 800;
        }

        .brand-subtitle {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 4px;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            font-size: 14px;
        }

        .team-details {
            text-align: right;
            line-height: 1.7;
        }

        .team-label {
            color: #94a3b8;
            font-size: 12px;
        }

        .team-name {
            color: white;
            font-weight: bold;
        }

        .team-code {
            color: #cbd5e1;
            font-size: 12px;
        }

        .coins-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(250, 204, 21, 0.12);
            border: 1px solid rgba(250, 204, 21, 0.25);
            padding: 10px 15px;
            border-radius: 12px;
        }

        .coin-icon {
            font-size: 20px;
        }

        .coins {
            color: #facc15;
            font-size: 17px;
            font-weight: 800;
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 38px auto;
        }

        .heading {
            margin-bottom: 27px;
        }

        .heading-label {
            display: inline-block;
            color: var(--primary);
            background: #dbeafe;
            padding: 7px 13px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 13px;
        }

        .heading h1 {
            font-size: 34px;
            color: var(--dark);
            margin-bottom: 9px;
            font-weight: 800;
        }

        .heading p {
            color: var(--muted);
            font-size: 15px;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 22px;
            padding: 32px;
            box-shadow: 0 15px 45px rgba(15, 23, 42, 0.08);
            border: 1px solid var(--border);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 23px;
            margin-bottom: 30px;
        }

        .result-header h2 {
            font-size: 23px;
            color: #111827;
            margin-bottom: 7px;
        }

        .result-header p {
            color: var(--muted);
            font-size: 14px;
        }

        .round-badge {
            background: #eef2ff;
            color: #4338ca;
            padding: 11px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            border: 1px solid #c7d2fe;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 45px;
            align-items: center;
        }

        .chart-area {
            min-height: 285px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .pie-chart {
            width: 255px;
            height: 255px;
            border-radius: 50%;

            background:
                conic-gradient(
                    var(--success) 0 <?= $correct_percentage ?>%,
                    #ef4444 <?= $correct_percentage ?>% 100%
                );

            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;

            box-shadow:
                0 12px 35px rgba(15, 23, 42, 0.14),
                inset 0 0 0 1px rgba(255, 255, 255, 0.4);
        }

        .pie-chart::after {
            content: "";
            width: 155px;
            height: 155px;
            background: white;
            border-radius: 50%;
            position: absolute;
            box-shadow: inset 0 0 20px rgba(15, 23, 42, 0.04);
        }

        .chart-text {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .chart-text strong {
            display: block;
            font-size: 38px;
            color: #111827;
            font-weight: 800;
        }

        .chart-text span {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 28px;
            margin-top: 18px;
            font-size: 14px;
            color: #475569;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-box {
            width: 13px;
            height: 13px;
            border-radius: 4px;
        }

        .correct-box {
            background: var(--success);
        }

        .wrong-box {
            background: #ef4444;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stat-box {
            padding: 21px;
            border-radius: 15px;
            background: #f8fafc;
            border: 1px solid var(--border);
            transition: 0.2s;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .stat-box small {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 9px;
            font-weight: 600;
        }

        .stat-box strong {
            font-size: 27px;
            color: #111827;
            font-weight: 800;
        }

        .stat-box.green {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .stat-box.green strong {
            color: var(--success);
        }

        .stat-box.red {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .stat-box.red strong {
            color: var(--danger);
        }

        .stat-box.blue {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .stat-box.blue strong {
            color: var(--primary);
        }

        .stat-box.yellow {
            background: #fefce8;
            border-color: #fde68a;
        }

        .stat-box.yellow strong {
            color: var(--warning);
        }

        .progress-section {
            margin-top: 30px;
            padding: 22px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 15px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: bold;
            color: #334155;
        }

        .progress-track {
            height: 11px;
            background: #e2e8f0;
            border-radius: 30px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: <?= $accuracy ?>%;
            background: linear-gradient(
                90deg,
                #2563eb,
                #16a34a
            );
            border-radius: 30px;
            transition: width 0.5s ease;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            margin-top: 30px;
        }

        .btn {
            text-decoration: none;
            border: none;
            padding: 14px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-dark {
            background: #111827;
            color: white;
        }

        .btn-light {
            background: #e2e8f0;
            color: #1e293b;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 5px solid var(--primary);
            padding: 17px;
            border-radius: 10px;
            color: #1e40af;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 25px;
        }

        .notice.warning {
            background: #fff7ed;
            border-color: #fed7aa;
            border-left-color: #f97316;
            color: #9a3412;
        }

        .notice.success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            border-left-color: var(--success);
            color: #166534;
        }

        .notice-icon {
            font-size: 18px;
        }

        @media (max-width: 850px) {
            .main-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .chart-area {
                min-height: 250px;
            }

            .result-card {
                padding: 25px;
            }
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 17px 20px;
                align-items: flex-start;
                flex-direction: column;
            }

            .team-info {
                width: 100%;
                justify-content: space-between;
            }

            .team-details {
                text-align: left;
            }

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .heading h1 {
                font-size: 28px;
            }

            .result-card {
                padding: 18px;
                border-radius: 16px;
            }

            .result-header {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 23px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-box {
                padding: 16px;
            }

            .stat-box strong {
                font-size: 22px;
            }

            .pie-chart {
                width: 220px;
                height: 220px;
            }

            .pie-chart::after {
                width: 130px;
                height: 130px;
            }

            .chart-text strong {
                font-size: 32px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .notice {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

    <!-- =====================================================
         TOP BAR
    ====================================================== -->

    <div class="topbar">

        <div class="brand-area">

            <div class="brand-icon">
                🤖
            </div>

            <div>
                <div class="brand">
                    AI Prediction Market
                </div>

                <div class="brand-subtitle">
                    Intelligent Quiz Investment Game
                </div>
            </div>

        </div>

        <div class="team-info">

            <div class="team-details">

                <div class="team-label">
                    TEAM
                </div>

                <div class="team-name">
                    <?= $team_name ?>
                </div>

                <div class="team-code">
                    <?= $team_code ?>
                </div>

            </div>

            <div class="coins-box">

                <span class="coin-icon">
                    🪙
                </span>

                <span class="coins">
                    <?= number_format($current_coins) ?>
                </span>

            </div>

        </div>

    </div>

    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <div class="container">

        <div class="heading">

            <span class="heading-label">
                Performance Report
            </span>

            <h1>
                <?= htmlspecialchars($round_title) ?> Result
            </h1>

            <p>
                Review your actual performance and answer accuracy.
            </p>

        </div>

        <div class="result-card">

            <div class="result-header">

                <div>

                    <h2>
                        Performance Summary
                    </h2>

                    <p>
                        Your actual result for
                        <?= htmlspecialchars($round_title) ?>
                    </p>

                </div>

                <div class="round-badge">
                    ROUND <?= $round ?>
                </div>

            </div>

            <div class="main-grid">

                <!-- =================================================
                     PIE CHART
                ================================================== -->

                <div>

                    <div class="chart-area">

                        <div class="pie-chart">

                            <div class="chart-text">

                                <strong>
                                    <?= $accuracy ?>%
                                </strong>

                                <span>
                                    Accuracy
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="legend">

                        <div class="legend-item">

                            <span class="legend-box correct-box"></span>

                            Correct <?= $correct_answers ?>

                        </div>

                        <div class="legend-item">

                            <span class="legend-box wrong-box"></span>

                            Wrong <?= $wrong_answers ?>

                        </div>

                    </div>

                </div>

                <!-- =================================================
                     STATISTICS
                ================================================== -->

                <div class="stats-grid">

                    <div class="stat-box">

                        <small>
                            Total Questions
                        </small>

                        <strong>
                            <?= $question_display ?>
                        </strong>

                    </div>

                    <div class="stat-box green">

                        <small>
                            Correct Answers
                        </small>

                        <strong>
                            <?= $correct_answers ?>
                        </strong>

                    </div>

                    <div class="stat-box red">

                        <small>
                            Wrong / Timeout
                        </small>

                        <strong>
                            <?= $wrong_answers ?>
                        </strong>

                    </div>

                    <div class="stat-box blue">

                        <small>
                            Accuracy
                        </small>

                        <strong>
                            <?= $accuracy ?>%
                        </strong>

                    </div>

                    <div class="stat-box yellow">

                        <small>
                            Total Time
                        </small>

                        <strong>
                            <?= $formatted_time ?>
                        </strong>

                    </div>

                    <div class="stat-box">

                        <small>
                            Current Coins
                        </small>

                        <strong>
                            <?= number_format($current_coins) ?>
                        </strong>

                    </div>

                </div>

            </div>

            <!-- =====================================================
                 PROGRESS
            ====================================================== -->

            <div class="progress-section">

                <div class="progress-header">

                    <span>
                        Round Accuracy Progress
                    </span>

                    <span>
                        <?= $accuracy ?>%
                    </span>

                </div>

                <div class="progress-track">

                    <div class="progress-fill"></div>

                </div>

            </div>

            <!-- =====================================================
                 ACTION BUTTONS
            ====================================================== -->

            <div class="actions">

                <a
                    href="answer_history.php?round=<?= $round ?>"
                    class="btn btn-primary"
                >
                    📋 View Answers
                </a>

                <?php if ($no_coins): ?>

                    <a
                        href="dashboard.php"
                        class="btn btn-dark"
                    >
                        ← Dashboard
                    </a>

                <?php elseif ($has_next_round): ?>

                    <a
                        href="game.php?round=<?= $next_round ?>"
                        class="btn btn-success"
                    >
                        🚀 Next Round
                    </a>

                    <a
                        href="dashboard.php"
                        class="btn btn-dark"
                    >
                        ← Dashboard
                    </a>

                <?php else: ?>

                    <a
                        href="final_result.php"
                        class="btn btn-success"
                    >
                        🏆 Final Result
                    </a>

                    <a
                        href="dashboard.php"
                        class="btn btn-dark"
                    >
                        ← Dashboard
                    </a>

                <?php endif; ?>

            </div>

            <!-- =====================================================
                 NOTICE
            ====================================================== -->

            <div class="notice <?= $no_coins ? "warning" : "success" ?>">

                <span class="notice-icon">
                    <?= $notice_icon ?>
                </span>

                <div>

                    <strong>
                        <?= htmlspecialchars($notice_title) ?>
                    </strong>

                    <br>

                    <?= htmlspecialchars($notice_message) ?>

                </div>

            </div>

        </div>

    </div>

</body>

</html>
