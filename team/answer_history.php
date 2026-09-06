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
   SETTINGS
===================================================== */

$TOTAL_ROUNDS = 5;
$QUESTIONS_PER_ROUND = 5;

/* =====================================================
   ESCAPE FUNCTION
===================================================== */

function clean($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =====================================================
   GET TEAM DETAILS
===================================================== */

$team_sql = "
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

$team_stmt = mysqli_prepare($conn, $team_sql);

if (!$team_stmt) {
    die("Team SQL Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $team_stmt,
    "i",
    $team_id
);

if (!mysqli_stmt_execute($team_stmt)) {
    die("Team Execute Error: " . mysqli_stmt_error($team_stmt));
}

$team_result = mysqli_stmt_get_result($team_stmt);
$team = mysqli_fetch_assoc($team_result);

mysqli_stmt_close($team_stmt);

if (!$team) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$current_coins = (int) $team["coins"];

/* =====================================================
   SELECTED ROUND
===================================================== */

$round = isset($_GET["round"])
    ? (int) $_GET["round"]
    : 1;

if ($round < 1 || $round > $TOTAL_ROUNDS) {
    $round = 1;
}

/* =====================================================
   GET LATEST FIVE ANSWERS FROM team_answers
===================================================== */

$sql = "
    SELECT
        latest_answers.id,
        latest_answers.team_id,
        latest_answers.round_number,
        latest_answers.question_id,
        latest_answers.is_correct,
        latest_answers.time_taken,
        latest_answers.created_at,

        q.question_number,
        q.question_text,
        q.correct_answer

    FROM
    (
        SELECT
            ta.id,
            ta.team_id,
            ta.round_number,
            ta.question_id,
            ta.is_correct,
            ta.time_taken,
            ta.created_at

        FROM team_answers ta

        WHERE ta.team_id = ?
          AND ta.round_number = ?

        ORDER BY
            ta.created_at DESC,
            ta.id DESC

        LIMIT 5

    ) AS latest_answers

    LEFT JOIN questions q
        ON latest_answers.question_id = q.id

    ORDER BY
        q.question_number ASC,
        latest_answers.id ASC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Answer History SQL Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $team_id,
    $round
);

if (!mysqli_stmt_execute($stmt)) {
    die("Answer History Execute Error: " . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);

/* =====================================================
   SUMMARY
===================================================== */

$answers = [];

$total_answers = 0;
$correct_answers = 0;
$wrong_answers = 0;
$total_time_taken = 0;

while ($row = mysqli_fetch_assoc($result)) {

    $answers[] = $row;

    $total_answers++;

    if ((int) $row["is_correct"] === 1) {
        $correct_answers++;
    } else {
        $wrong_answers++;
    }

    $total_time_taken += (int) $row["time_taken"];
}

mysqli_stmt_close($stmt);

/* =====================================================
   CALCULATE ACCURACY
===================================================== */

$accuracy = 0;

if ($total_answers > 0) {
    $accuracy = round(
        ($correct_answers / $total_answers) * 100,
        2
    );
}

/* =====================================================
   FORMAT TOTAL TIME
===================================================== */

$minutes = floor($total_time_taken / 60);
$seconds = $total_time_taken % 60;

$formatted_total_time = sprintf(
    "%02d:%02d",
    $minutes,
    $seconds
);

$page_title = "Round " . $round . " Answer History";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= clean($page_title) ?></title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #475569;
            --purple: #7c3aed;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #ca8a04;
            --dark: #0f172a;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --background: #f1f5f9;
            --white: #ffffff;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(37, 99, 235, 0.12),
                    transparent 32%
                ),
                linear-gradient(
                    135deg,
                    #f8fafc,
                    #eef2ff
                );
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.5;
        }

        .container {
            width: 94%;
            max-width: 1280px;
            margin: 35px auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .page-heading::before {
            content: "";
            display: block;
            width: 55px;
            height: 5px;
            border-radius: 10px;
            background: linear-gradient(
                90deg,
                var(--primary),
                var(--purple)
            );
            margin-bottom: 14px;
        }

        h1 {
            color: var(--dark);
            font-size: clamp(25px, 4vw, 34px);
            font-weight: 800;
            letter-spacing: -0.7px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
        }

        .team-info {
            min-width: 230px;
            padding: 18px 22px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--border);
            border-radius: 16px;
            text-align: right;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }

        .team-name {
            color: var(--dark);
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .team-code {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 8px;
        }

        .coins {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--warning);
            font-size: 14px;
            font-weight: 800;
        }

        .navigation {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 43px;
            padding: 11px 17px;
            border-radius: 10px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.13);
        }

        .btn-primary {
            background: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
        }

        .btn-secondary:hover {
            background: #334155;
        }

        .btn-result {
            background: var(--purple);
        }

        .btn-result:hover {
            background: #6d28d9;
        }

        .btn-active {
            background: var(--success);
            box-shadow: 0 5px 15px rgba(22, 163, 74, 0.2);
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .summary-card {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.05);
            transition: 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 13px 30px rgba(15, 23, 42, 0.09);
        }

        .summary-card h3 {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .summary-card p {
            color: var(--dark);
            font-size: 28px;
            font-weight: 800;
        }

        .correct-number {
            color: var(--success) !important;
        }

        .wrong-number {
            color: var(--danger) !important;
        }

        .accuracy-number {
            color: var(--primary) !important;
        }

        .time-number {
            color: var(--purple) !important;
        }

        .table-card {
            overflow: hidden;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.06);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 24px 25px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .table-header h2 {
            color: var(--dark);
            font-size: 21px;
            font-weight: 800;
        }

        .table-header p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 5px;
        }

        .round-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #4338ca;
            padding: 9px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 16px 14px;
            border-bottom: 1px solid var(--border);
            text-align: center;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }

        td {
            color: #334155;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .question-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 82px;
            padding: 7px 10px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            color: #4338ca;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .question-text {
            min-width: 280px;
            max-width: 430px;
            text-align: left;
            color: #334155;
            line-height: 1.65;
            font-weight: 500;
        }

        .correct-answer {
            display: inline-block;
            min-width: 65px;
            padding: 7px 11px;
            border-radius: 8px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .correct,
        .wrong {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 7px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .correct {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .correct::before {
            content: "✓";
            font-size: 13px;
        }

        .wrong {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .wrong::before {
            content: "×";
            font-size: 14px;
        }

        .time {
            color: #475569;
            font-weight: 600;
            white-space: nowrap;
        }

        .date {
            color: var(--muted);
            font-size: 12px;
            white-space: nowrap;
        }

        .empty {
            padding: 70px 25px;
            text-align: center;
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            font-size: 30px;
        }

        .empty h3 {
            color: #334155;
            font-size: 19px;
            margin-bottom: 8px;
        }

        .empty p {
            color: var(--muted);
            font-size: 14px;
        }

        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .footer-note {
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 1100px) {
            .summary {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 700px) {

            .container {
                width: 94%;
                margin: 22px auto;
            }

            .top-bar {
                align-items: flex-start;
                flex-direction: column;
            }

            .team-info {
                width: 100%;
                text-align: left;
            }

            .summary {
                grid-template-columns: repeat(2, 1fr);
                gap: 11px;
            }

            .summary-card {
                padding: 17px;
            }

            .summary-card p {
                font-size: 23px;
            }

            .navigation {
                gap: 8px;
            }

            .navigation .btn {
                flex: 1;
                min-width: 125px;
            }

            .table-header {
                padding: 20px;
            }

            .bottom-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .bottom-actions .btn {
                width: 100%;
            }

            .footer-note {
                text-align: center;
            }
        }

        @media (max-width: 430px) {

            .summary {
                grid-template-columns: 1fr 1fr;
            }

            .summary-card {
                padding: 14px;
            }

            .summary-card p {
                font-size: 21px;
            }

            .btn {
                font-size: 12px;
                padding: 10px 12px;
            }

            h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="top-bar">

        <div class="page-heading">

            <h1>
                <?= clean($page_title) ?>
            </h1>

            <p class="subtitle">
                Review the latest five submitted answers from
                Round <?= clean($round) ?>.
            </p>

        </div>

        <div class="team-info">

            <div class="team-name">
                <?= clean($team["team_name"]) ?>
            </div>

            <div class="team-code">
                Team Code:
                <?= clean($team["team_code"]) ?>
            </div>

            <div class="coins">
                🪙
                <span>
                    <?= number_format($current_coins) ?> Coins
                </span>
            </div>

        </div>

    </div>

    <!-- NAVIGATION -->

    <div class="navigation">

        <a
            href="dashboard.php"
            class="btn btn-primary"
        >
            ← Dashboard
        </a>

        <a
            href="round_result.php?round=<?= $round ?>"
            class="btn btn-result"
        >
            ← Round Result
        </a>

        <?php for ($r = 1; $r <= $TOTAL_ROUNDS; $r++): ?>

            <a
                href="answer_history.php?round=<?= $r ?>"
                class="btn <?= $round === $r
                    ? "btn-active"
                    : "btn-secondary" ?>"
            >
                Round <?= $r ?>
            </a>

        <?php endfor; ?>

    </div>

    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-card">

            <h3>Total Answers</h3>

            <p>
                <?= $total_answers ?>/<?= $QUESTIONS_PER_ROUND ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Correct Answers</h3>

            <p class="correct-number">
                <?= $correct_answers ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Wrong / Timeout</h3>

            <p class="wrong-number">
                <?= $wrong_answers ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Accuracy</h3>

            <p class="accuracy-number">
                <?= $accuracy ?>%
            </p>

        </div>

        <div class="summary-card">

            <h3>Total Time</h3>

            <p class="time-number">
                <?= $formatted_total_time ?>
            </p>

        </div>

    </div>

    <!-- ANSWER TABLE -->

    <div class="table-card">

        <div class="table-header">

            <div>

                <h2>
                    Round <?= clean($round) ?> Answers
                </h2>

                <p>
                    Detailed performance for the latest five questions
                </p>

            </div>

            <div class="round-badge">
                <?= $total_answers ?>/<?= $QUESTIONS_PER_ROUND ?>
                Answers
            </div>

        </div>

        <?php if (count($answers) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Question No.</th>
                            <th>Question</th>
                            <th>Correct Answer</th>
                            <th>Result</th>
                            <th>Time Taken</th>
                            <th>Submitted At</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($answers as $row): ?>

                            <?php

                            $is_correct =
                                (int) $row["is_correct"] === 1;

                            $question_number =
                                $row["question_number"] ?? "N/A";

                            $question_text =
                                $row["question_text"] ??
                                "Question unavailable";

                            $correct_answer =
                                $row["correct_answer"] ??
                                "Not available";

                            $time_taken =
                                (int) $row["time_taken"];

                            $submitted_at =
                                $row["created_at"] ?? "N/A";

                            if (
                                $submitted_at !== "N/A" &&
                                !empty($submitted_at)
                            ) {
                                $submitted_at = date(
                                    "d M Y, h:i A",
                                    strtotime($submitted_at)
                                );
                            }

                            ?>

                            <tr>

                                <td>

                                    <span class="question-number">
                                        Question
                                        <?= clean($question_number) ?>
                                    </span>

                                </td>

                                <td class="question-text">
                                    <?= clean($question_text) ?>
                                </td>

                                <td>

                                    <span class="correct-answer">
                                        <?= clean($correct_answer) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ($is_correct): ?>

                                        <span class="correct">
                                            Correct
                                        </span>

                                    <?php else: ?>

                                        <span class="wrong">
                                            Wrong / Timeout
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="time">
                                    <?= $time_taken ?> seconds
                                </td>

                                <td class="date">
                                    <?= clean($submitted_at) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    📋
                </div>

                <h3>
                    No Answers Found
                </h3>

                <p>
                    You have not submitted any answers for Round
                    <?= clean($round) ?> yet.
                </p>

            </div>

        <?php endif; ?>

    </div>

    <!-- BOTTOM ACTIONS -->

    <div class="bottom-actions">

        <span class="footer-note">
            Your submitted answers are securely saved.
        </span>

        <div class="navigation">

            <a
                href="dashboard.php"
                class="btn btn-primary"
            >
                Back to Dashboard
            </a>

            <a
                href="round_result.php?round=<?= $round ?>"
                class="btn btn-result"
            >
                Back to Round Result
            </a>

        </div>

    </div>

</div>

</body>

</html>
