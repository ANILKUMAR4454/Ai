<?php

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once "../db.php";

/* =========================================================
   TEAM SESSION CHECK
========================================================= */

if (!isset($_SESSION["team_id"])) {
    header("Location: login.php");
    exit();
}

$team_id = (int) $_SESSION["team_id"];

/* =========================================================
   GET TEAM DETAILS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        team_code,
        team_name,
        coins,
        current_round,
        current_question,
        status
    FROM teams
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $team_id);
$stmt->execute();

$team_result = $stmt->get_result();
$team = $team_result->fetch_assoc();

$stmt->close();

/* =========================================================
   TEAM NOT FOUND
========================================================= */

if (!$team) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/* =========================================================
   TEAM STATUS CHECK
========================================================= */

if ($team["status"] !== "Active") {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/* =========================================================
   CHECK GAME COMPLETION
========================================================= */

/*
    current_round = 6 means
    all 5 rounds are completed.
*/

if ((int)$team["current_round"] < 6) {
    header("Location: game.php");
    exit();
}

/* =========================================================
   GET ALL ANSWERS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        a.id,
        a.question_id,
        a.round_number,
        a.question_number,
        a.prediction,
        a.correct_answer,
        a.is_correct,
        a.time_taken,
        a.coins_change,
        a.bet_amount,
        a.submitted_at,
        q.question_text,
        q.difficulty,
        q.ai_prediction_percentage
    FROM answers a
    INNER JOIN questions q
        ON a.question_id = q.id
    WHERE a.team_id = ?
    ORDER BY a.round_number ASC, a.question_number ASC
");

$stmt->bind_param("i", $team_id);
$stmt->execute();

$answers_result = $stmt->get_result();

$answers = [];

while ($row = $answers_result->fetch_assoc()) {
    $answers[] = $row;
}

$stmt->close();

/* =========================================================
   CALCULATE STATISTICS
========================================================= */

$total_questions = count($answers);

$correct_count = 0;
$wrong_count = 0;
$timeout_count = 0;

$total_time = 0;
$total_coins_change = 0;
$total_bet = 0;

foreach ($answers as $answer) {

    $time = (int)$answer["time_taken"];
    $coins_change = (int)$answer["coins_change"];
    $bet = (int)$answer["bet_amount"];

    $total_time += $time;
    $total_coins_change += $coins_change;
    $total_bet += $bet;

    if ($answer["prediction"] === "TIMEOUT") {
        $timeout_count++;
    } elseif ((int)$answer["is_correct"] === 1) {
        $correct_count++;
    } else {
        $wrong_count++;
    }
}

/* =========================================================
   AVERAGE TIME
========================================================= */

$average_time = 0;

if ($total_questions > 0) {
    $average_time = $total_time / $total_questions;
}

/* =========================================================
   ACCURACY
========================================================= */

$accuracy = 0;

if ($total_questions > 0) {
    $accuracy = ($correct_count / $total_questions) * 100;
}

/* =========================================================
   FINAL COINS
========================================================= */

$final_coins = (int)$team["coins"];

/* =========================================================
   RANKING
========================================================= */

/*
    Ranking is based on:

    1. Final coins DESC
    2. Correct answers DESC
    3. Total time ASC
*/

$ranking_query = "
    SELECT
        t.id,
        t.team_name,
        t.team_code,
        t.coins,

        COALESCE(SUM(
            CASE
                WHEN a.is_correct = 1 THEN 1
                ELSE 0
            END
        ), 0) AS correct_answers,

        COALESCE(SUM(a.time_taken), 0) AS total_time

    FROM teams t

    LEFT JOIN answers a
        ON t.id = a.team_id

    WHERE t.status = 'Active'

    GROUP BY
        t.id,
        t.team_name,
        t.team_code,
        t.coins

    HAVING COUNT(a.id) >= 25

    ORDER BY
        t.coins DESC,
        correct_answers DESC,
        total_time ASC
";

$ranking_result = $conn->query($ranking_query);

$rank = 0;
$position = 0;

if ($ranking_result) {

    while ($rank_team = $ranking_result->fetch_assoc()) {

        $position++;

        if ((int)$rank_team["id"] === $team_id) {
            $rank = $position;
            break;
        }
    }
}

/* =========================================================
   DISPLAY RANK FALLBACK
========================================================= */

if ($rank === 0) {
    $rank = "-";
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

    <title>Final Result</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6fb;
            color: #1f2937;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            background: #ffffff;
            border-radius: 18px;
            padding: 28px;
            margin-bottom: 22px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07);
        }

        .header h1 {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .header p {
            color: #6b7280;
            font-size: 15px;
        }

        .team-info {
            margin-top: 15px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .team-badge {
            background: #eef2ff;
            color: #4338ca;
            padding: 9px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        /* =========================
           STATS
        ========================= */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }

        .stat-title {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
        }

        /* =========================
           RESULT SUMMARY
        ========================= */

        .summary {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07);
        }

        .summary h2 {
            margin-bottom: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .summary-item {
            background: #f8fafc;
            padding: 18px;
            border-radius: 12px;
        }

        .summary-item span {
            display: block;
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .summary-item strong {
            font-size: 20px;
        }

        /* =========================
           TABLE
        ========================= */

        .questions-section {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07);
            overflow-x: auto;
        }

        .questions-section h2 {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            background: #f8fafc;
            text-align: left;
            padding: 14px;
            font-size: 13px;
            color: #475569;
        }

        td {
            padding: 14px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .question-text {
            max-width: 300px;
            line-height: 1.5;
        }

        /* =========================
           BADGES
        ========================= */

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .correct {
            background: #dcfce7;
            color: #166534;
        }

        .wrong {
            background: #fee2e2;
            color: #991b1b;
        }

        .timeout {
            background: #fef3c7;
            color: #92400e;
        }

        .difficulty {
            background: #eef2ff;
            color: #4338ca;
        }

        /* =========================
           COINS
        ========================= */

        .positive {
            color: #15803d;
            font-weight: bold;
        }

        .negative {
            color: #dc2626;
            font-weight: bold;
        }

        .neutral {
            color: #6b7280;
            font-weight: bold;
        }

        /* =========================
           LOGOUT
        ========================= */

        .actions {
            text-align: center;
            margin-top: 25px;
        }

        .logout-btn {
            display: inline-block;
            background: #111827;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: bold;
        }

        .logout-btn:hover {
            background: #000000;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 850px) {

            body {
                padding: 15px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 500px) {

            .stats {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================
         HEADER
    ========================= -->

    <div class="header">

        <h1>🏆 Final Game Result</h1>

        <p>
            Congratulations! You have completed all
            5 rounds and 25 questions.
        </p>

        <div class="team-info">

            <div class="team-badge">
                Team: <?= htmlspecialchars($team["team_name"]) ?>
            </div>

            <div class="team-badge">
                Code: <?= htmlspecialchars($team["team_code"]) ?>
            </div>

        </div>

    </div>


    <!-- =========================
         MAIN STATS
    ========================= -->

    <div class="stats">

        <div class="stat-card">

            <div class="stat-title">
                💰 Final Coins
            </div>

            <div class="stat-value">
                <?= number_format($final_coins) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                🎯 Correct
            </div>

            <div class="stat-value">
                <?= $correct_count ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                ❌ Wrong
            </div>

            <div class="stat-value">
                <?= $wrong_count ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                🏅 Rank
            </div>

            <div class="stat-value">
                #<?= htmlspecialchars((string)$rank) ?>
            </div>

        </div>

    </div>


    <!-- =========================
         SUMMARY
    ========================= -->

    <div class="summary">

        <h2>📊 Performance Summary</h2>

        <div class="summary-grid">

            <div class="summary-item">

                <span>Total Questions</span>

                <strong>
                    <?= $total_questions ?>
                </strong>

            </div>


            <div class="summary-item">

                <span>Accuracy</span>

                <strong>
                    <?= number_format($accuracy, 1) ?>%
                </strong>

            </div>


            <div class="summary-item">

                <span>Timeouts</span>

                <strong>
                    <?= $timeout_count ?>
                </strong>

            </div>


            <div class="summary-item">

                <span>Total Time</span>

                <strong>
                    <?= $total_time ?> sec
                </strong>

            </div>


            <div class="summary-item">

                <span>Average Time</span>

                <strong>
                    <?= number_format($average_time, 1) ?> sec
                </strong>

            </div>


            <div class="summary-item">

                <span>Coins Change</span>

                <strong class="<?= $total_coins_change >= 0 ? 'positive' : 'negative' ?>">

                    <?= $total_coins_change >= 0 ? '+' : '' ?>
                    <?= number_format($total_coins_change) ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =========================
         QUESTION RESULTS
    ========================= -->

    <div class="questions-section">

        <h2>📝 Question-by-Question Results</h2>

        <?php if (count($answers) > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>Round</th>

                        <th>Question</th>

                        <th>Question</th>

                        <th>Difficulty</th>

                        <th>AI</th>

                        <th>Your Prediction</th>

                        <th>Correct Answer</th>

                        <th>Time</th>

                        <th>Bet</th>

                        <th>Coins</th>

                        <th>Result</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($answers as $answer): ?>

                    <?php

                    $prediction = $answer["prediction"];

                    $correct_answer = $answer["correct_answer"];

                    $is_timeout = ($prediction === "TIMEOUT");

                    $is_correct = ((int)$answer["is_correct"] === 1);

                    $coins_change = (int)$answer["coins_change"];

                    ?>

                    <tr>

                        <td>
                            R<?= (int)$answer["round_number"] ?>
                        </td>

                        <td>
                            Q<?= (int)$answer["question_number"] ?>
                        </td>

                        <td class="question-text">
                            <?= htmlspecialchars($answer["question_text"]) ?>
                        </td>

                        <td>

                            <span class="badge difficulty">

                                <?= htmlspecialchars($answer["difficulty"]) ?>

                            </span>

                        </td>

                        <td>

                            <strong>
                                <?= number_format((float)$answer["ai_prediction_percentage"], 0) ?>%
                            </strong>

                        </td>

                        <td>

                            <?php if ($is_timeout): ?>

                                <span class="badge timeout">
                                    TIMEOUT
                                </span>

                            <?php else: ?>

                                <?= htmlspecialchars($prediction) ?>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($correct_answer) ?>

                        </td>

                        <td>

                            <?= (int)$answer["time_taken"] ?> sec

                        </td>

                        <td>

                            <?= (int)$answer["bet_amount"] ?>

                        </td>

                        <td>

                            <?php if ($coins_change > 0): ?>

                                <span class="positive">
                                    +<?= $coins_change ?>
                                </span>

                            <?php elseif ($coins_change < 0): ?>

                                <span class="negative">
                                    <?= $coins_change ?>
                                </span>

                            <?php else: ?>

                                <span class="neutral">
                                    0
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if ($is_timeout): ?>

                                <span class="badge timeout">
                                    Timeout
                                </span>

                            <?php elseif ($is_correct): ?>

                                <span class="badge correct">
                                    Correct
                                </span>

                            <?php else: ?>

                                <span class="badge wrong">
                                    Wrong
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <p>
                No answer records were found.
            </p>

        <?php endif; ?>

    </div>


    <!-- =========================
         LOGOUT
    ========================= -->

    <div class="actions">

        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</div>

</body>

</html>