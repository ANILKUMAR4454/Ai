<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* ===============================
   HELPER FUNCTION
================================ */
function clean($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* ===============================
   CHECK TEAM LOGIN
================================ */
if (!isset($_SESSION["team_id"])) {
    header("Location: login.php");
    exit();
}

$team_id = (int)$_SESSION["team_id"];

/* ===============================
   GET TEAM DETAILS
================================ */
$stmt = $conn->prepare("
    SELECT id, team_code, team_name, coins, status
    FROM teams
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $team_id);
$stmt->execute();

$result = $stmt->get_result();
$team = $result->fetch_assoc();

$stmt->close();

if (!$team || $team["status"] !== "Active") {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* ===============================
   GET GAME HISTORY
   DATA FROM team_answers TABLE
================================ */
$stmt = $conn->prepare("
    SELECT
        ta.id,
        ta.team_id,
        ta.round_number,
        ta.question_id,
        ta.is_correct,
        ta.time_taken,
        ta.created_at,

        q.question_number,
        q.question_text,
        q.difficulty,
        q.correct_answer,
        q.ai_prediction,
        q.ai_prediction_percentage

    FROM team_answers ta

    LEFT JOIN questions q
        ON ta.question_id = q.id

    WHERE ta.team_id = ?

    ORDER BY
        ta.round_number ASC,
        q.question_number ASC,
        ta.id ASC
");

$stmt->bind_param("i", $team_id);
$stmt->execute();

$history_result = $stmt->get_result();

$history = [];

while ($row = $history_result->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();

/* ===============================
   STATISTICS
================================ */
$total_questions = count($history);
$total_correct = 0;
$total_wrong = 0;
$total_time = 0;

foreach ($history as $row) {
    if ((int)$row["is_correct"] === 1) {
        $total_correct++;
    } else {
        $total_wrong++;
    }

    $total_time += (int)$row["time_taken"];
}

$accuracy = $total_questions > 0
    ? round(($total_correct / $total_questions) * 100, 2)
    : 0;

$minutes = floor($total_time / 60);
$seconds = $total_time % 60;

$formatted_total_time = sprintf(
    "%02d:%02d",
    $minutes,
    $seconds
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Game History - AI Prediction Market</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
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
                    rgba(37, 99, 235, 0.13),
                    transparent 32%
                ),
                linear-gradient(135deg, #f8fafc, #eef2ff);
            color: var(--text);
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
        }

        .history-header {
            background: linear-gradient(
                135deg,
                #0f172a,
                #1e293b
            );
            color: white;
            padding: 20px 30px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.15);
        }

        .history-header-inner {
            max-width: 1400px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--purple)
            );
            border-radius: 14px;
            font-size: 23px;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.3);
        }

        .brand h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
        }

        .brand p {
            margin: 4px 0 0;
            color: #cbd5e1;
            font-size: 13px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            color: white;
            background: #334155;
            padding: 11px 17px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s;
        }

        .back-button:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .history-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 35px 20px 50px;
        }

        .page-title {
            margin-bottom: 27px;
        }

        .page-title::before {
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

        .page-title h2 {
            margin: 0;
            color: var(--dark);
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .page-title p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .team-info {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 22px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 27px;
            box-shadow: 0 9px 28px rgba(15, 23, 42, 0.06);
        }

        .team-details h3 {
            margin: 0 0 7px;
            color: var(--dark);
            font-size: 20px;
            font-weight: 800;
        }

        .team-code {
            color: var(--muted);
            font-size: 13px;
        }

        .team-code strong {
            color: var(--text);
        }

        .coin-display {
            text-align: right;
        }

        .coin-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 4px;
        }

        .coin-value {
            color: var(--warning);
            font-size: 27px;
            font-weight: 800;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.05);
            transition: 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 13px 30px rgba(15, 23, 42, 0.09);
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 10px;
        }

        .stat-value {
            color: var(--dark);
            font-size: 29px;
            font-weight: 800;
        }

        .positive {
            color: var(--success) !important;
        }

        .negative {
            color: var(--danger) !important;
        }

        .history-card {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.06);
        }

        .history-card-header {
            padding: 23px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .history-card-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: 21px;
            font-weight: 800;
        }

        .history-card-header span {
            color: var(--muted);
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 8px 13px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            padding: 16px 13px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 17px 13px;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover td {
            background: #f8fbff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .round-badge,
        .question-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .round-badge {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #4338ca;
        }

        .question-badge {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .question-text {
            min-width: 260px;
            max-width: 380px;
            color: #334155;
            line-height: 1.6;
            font-weight: 500;
        }

        .difficulty {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #f3e8ff;
            border: 1px solid #e9d5ff;
            color: #7e22ce;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .ai-prediction {
            color: var(--purple);
            font-weight: 800;
            white-space: nowrap;
        }

        .ai-percentage {
            color: var(--primary);
            font-weight: 800;
            white-space: nowrap;
        }

        .correct-answer {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 8px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .result-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .result-correct {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .result-correct::before {
            content: "✓";
            font-size: 13px;
        }

        .result-wrong {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .result-wrong::before {
            content: "×";
            font-size: 14px;
        }

        .time {
            color: #475569;
            font-weight: 700;
            white-space: nowrap;
        }

        .date {
            color: var(--muted);
            font-size: 12px;
            white-space: nowrap;
        }

        .empty-history {
            padding: 75px 20px;
            text-align: center;
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 75px;
            height: 75px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #f1f5f9;
            font-size: 34px;
        }

        .empty-history h3 {
            margin: 0 0 8px;
            color: var(--dark);
            font-size: 20px;
        }

        .empty-history p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .history-footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 30px;
        }

        @media (max-width: 950px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .history-header-inner {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 650px) {
            .history-header {
                padding: 17px 18px;
            }

            .history-container {
                padding: 25px 12px 40px;
            }

            .page-title h2 {
                font-size: 26px;
            }

            .team-info {
                align-items: flex-start;
                flex-direction: column;
            }

            .coin-display {
                text-align: left;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-value {
                font-size: 23px;
            }

            .history-card-header {
                padding: 20px;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .back-button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="history-header">

        <div class="history-header-inner">

            <div class="brand">

                <div class="brand-icon">
                    🤖
                </div>

                <div>
                    <h1>AI Prediction Market</h1>
                    <p>Team Game History</p>
                </div>

            </div>

            <a href="dashboard.php" class="back-button">
                ← Back to Dashboard
            </a>

        </div>

    </header>

    <!-- MAIN CONTENT -->
    <main class="history-container">

        <!-- PAGE TITLE -->
        <div class="page-title">

            <h2>Game History</h2>

            <p>
                Review your submitted answers, results and performance.
            </p>

        </div>

        <!-- TEAM INFORMATION -->
        <section class="team-info">

            <div class="team-details">

                <h3>
                    <?php echo clean($team["team_name"]); ?>
                </h3>

                <div class="team-code">
                    Team Code:
                    <strong>
                        <?php echo clean($team["team_code"]); ?>
                    </strong>
                </div>

            </div>

            <div class="coin-display">

                <div class="coin-label">
                    Current Coins
                </div>

                <div class="coin-value">
                    🪙 <?php echo number_format((int)$team["coins"]); ?>
                </div>

            </div>

        </section>

        <!-- STATISTICS -->
        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-label">
                    Total Questions
                </div>

                <div class="stat-value">
                    <?php echo $total_questions; ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-label">
                    Correct Answers
                </div>

                <div class="stat-value positive">
                    <?php echo $total_correct; ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-label">
                    Wrong / Timeout
                </div>

                <div class="stat-value negative">
                    <?php echo $total_wrong; ?>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-label">
                    Accuracy
                </div>

                <div class="stat-value">
                    <?php echo $accuracy; ?>%
                </div>

            </div>

        </section>

        <!-- HISTORY TABLE -->
        <section class="history-card">

            <div class="history-card-header">

                <h3>
                    Prediction History
                </h3>

                <span>
                    <?php echo $total_questions; ?> record(s)
                </span>

            </div>

            <?php if (empty($history)): ?>

                <div class="empty-history">

                    <div class="empty-icon">
                        📊
                    </div>

                    <h3>
                        No Game History Yet
                    </h3>

                    <p>
                        Your submitted answers will appear here after you play.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Round</th>
                                <th>Question</th>
                                <th>Question Text</th>
                                <th>Difficulty</th>
                                <th>AI Prediction</th>
                                <th>AI Percentage</th>
                                <th>Correct Answer</th>
                                <th>Time</th>
                                <th>Result</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($history as $row): ?>

                                <?php
                                $is_correct = (int)$row["is_correct"] === 1;

                                $question_number = $row["question_number"]
                                    ?? "N/A";

                                $question_text = $row["question_text"]
                                    ?? "Question unavailable";

                                $difficulty = $row["difficulty"]
                                    ?? "Normal";

                                $ai_prediction = $row["ai_prediction"]
                                    ?? "Not available";

                                $ai_percentage = $row["ai_prediction_percentage"]
                                    ?? 0;

                                $correct_answer = $row["correct_answer"]
                                    ?? "Not available";

                                $time_taken = (int)$row["time_taken"];

                                $created_at = $row["created_at"]
                                    ?? "";

                                if ($is_correct) {
                                    $result_class = "result-correct";
                                    $result_text = "Correct";
                                } else {
                                    $result_class = "result-wrong";
                                    $result_text = "Wrong / Timeout";
                                }
                                ?>

                                <tr>

                                    <!-- ROUND -->
                                    <td>
                                        <span class="round-badge">
                                            Round <?php echo (int)$row["round_number"]; ?>
                                        </span>
                                    </td>

                                    <!-- QUESTION NUMBER -->
                                    <td>
                                        <span class="question-badge">
                                            Q<?php echo clean($question_number); ?>
                                        </span>
                                    </td>

                                    <!-- QUESTION TEXT -->
                                    <td>
                                        <div class="question-text">
                                            <?php echo nl2br(clean($question_text)); ?>
                                        </div>
                                    </td>

                                    <!-- DIFFICULTY -->
                                    <td>
                                        <span class="difficulty">
                                            <?php echo clean($difficulty); ?>
                                        </span>
                                    </td>

                                    <!-- AI PREDICTION -->
                                    <td>
                                        <span class="ai-prediction">
                                            <?php echo clean($ai_prediction); ?>
                                        </span>
                                    </td>

                                    <!-- AI PERCENTAGE -->
                                    <td>
                                        <span class="ai-percentage">
                                            <?php echo number_format(
                                                (float)$ai_percentage,
                                                2
                                            ); ?>%
                                        </span>
                                    </td>

                                    <!-- CORRECT ANSWER -->
                                    <td>
                                        <span class="correct-answer">
                                            <?php echo clean($correct_answer); ?>
                                        </span>
                                    </td>

                                    <!-- TIME TAKEN -->
                                    <td>
                                        <span class="time">
                                            <?php echo $time_taken; ?> sec
                                        </span>
                                    </td>

                                    <!-- RESULT -->
                                    <td>
                                        <span class="result-badge <?php echo $result_class; ?>">
                                            <?php echo $result_text; ?>
                                        </span>
                                    </td>

                                    <!-- SUBMITTED AT -->
                                    <td>
                                        <span class="date">
                                            <?php
                                            echo $created_at
                                                ? date(
                                                    "d M Y, h:i A",
                                                    strtotime($created_at)
                                                )
                                                : "Not available";
                                            ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

        <div class="history-footer">
            AI Prediction Market © <?php echo date("Y"); ?>
        </div>

    </main>

</body>

</html>
