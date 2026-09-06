<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* Admin security check */
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/* Game settings */
$total_rounds = 5;
$questions_per_round = 5;
$question_time = 60;
$round_time = 5;

/* Count total questions */
$total_questions = 0;

$count_query = $conn->query(
    "SELECT COUNT(*) AS total FROM questions"
);

if ($count_query) {
    $count_row = $count_query->fetch_assoc();
    $total_questions = (int) $count_row["total"];
}

/* Team statistics */
$total_teams = 0;
$active_teams = 0;
$completed_teams = 0;

$team_query = $conn->query(
    "SELECT
        COUNT(*) AS total_teams,
        COALESCE(SUM(status = 'Active'), 0) AS active_teams,
        COALESCE(SUM(current_round >= 6), 0) AS completed_teams
     FROM teams"
);

if ($team_query) {
    $team_row = $team_query->fetch_assoc();

    $total_teams = (int) ($team_row["total_teams"] ?? 0);
    $active_teams = (int) ($team_row["active_teams"] ?? 0);
    $completed_teams = (int) ($team_row["completed_teams"] ?? 0);
}

/* Get question count for each round */
$rounds = [];

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM questions
     WHERE round_number = ?"
);

if (!$stmt) {
    die("Database query preparation failed.");
}

for ($round = 1; $round <= $total_rounds; $round++) {

    $question_count = 0;

    $stmt->bind_param("i", $round);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        $question_count = (int) ($row["total"] ?? 0);
    }

    $is_ready = ($question_count >= $questions_per_round);

    $rounds[] = [
        "number" => $round,
        "title" => "Round " . $round,
        "description" => "Prediction Round " . $round,
        "question_count" => $question_count,
        "is_ready" => $is_ready
    ];
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Round Management | AI Prediction Market</title>

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        background: #f5f7fb;
        color: #1f2937;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px 40px;
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ede9fe;
        border-radius: 12px;
        font-size: 25px;
    }

    .page-header h1 {
        font-size: 26px;
        margin-bottom: 5px;
    }

    .page-header p {
        color: #6b7280;
    }

    .back-button {
        text-decoration: none;
        background: #4f46e5;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: bold;
    }

    .back-button:hover {
        background: #4338ca;
    }

    .container {
        max-width: 1250px;
        margin: 35px auto;
        padding: 0 20px;
    }

    .page-intro {
        margin-bottom: 30px;
    }

    .page-label {
        color: #4f46e5;
        font-size: 13px;
        font-weight: bold;
        letter-spacing: 1px;
    }

    .page-intro h2 {
        margin: 10px 0;
        font-size: 30px;
    }

    .page-intro p {
        color: #6b7280;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }

    .summary-card {
        display: flex;
        align-items: center;
        gap: 15px;
        background: white;
        padding: 22px;
        border-radius: 14px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .summary-icon {
        width: 55px;
        height: 55px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 25px;
    }

    .summary-icon.purple {
        background: #ede9fe;
    }

    .summary-icon.blue {
        background: #dbeafe;
    }

    .summary-icon.green {
        background: #dcfce7;
    }

    .summary-icon.orange {
        background: #ffedd5;
    }

    .summary-content span,
    .summary-content small {
        display: block;
        color: #6b7280;
    }

    .summary-content strong {
        display: block;
        font-size: 28px;
        margin: 5px 0;
    }

    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .section-heading h2 {
        margin-bottom: 8px;
    }

    .section-heading p {
        color: #6b7280;
    }

    .game-info {
        display: flex;
        gap: 20px;
        color: #6b7280;
        font-size: 14px;
    }

    .rounds-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 25px;
    }

    .round-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: 0.3s;
    }

    .round-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .round-card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .round-number span {
        display: block;
        color: #6b7280;
        font-size: 12px;
        letter-spacing: 1px;
    }

    .round-number strong {
        font-size: 30px;
        color: #4f46e5;
    }

    .status-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 7px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }

    .status-badge.ready {
        background: #dcfce7;
        color: #15803d;
    }

    .status-badge.not-ready {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
    }

    .round-card-title h3 {
        font-size: 20px;
        margin-bottom: 8px;
    }

    .round-card-title p {
        color: #6b7280;
        margin-bottom: 25px;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .progress-track {
        height: 9px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 10px;
    }

    .progress-fill.ready {
        background: #22c55e;
    }

    .progress-fill.not-ready {
        background: #f59e0b;
    }

    .progress-text {
        margin-top: 10px;
        color: #6b7280;
        font-size: 13px;
    }

    .round-details {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .detail-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .detail-icon {
        font-size: 20px;
    }

    .detail-label {
        display: block;
        color: #6b7280;
        font-size: 12px;
        margin-bottom: 4px;
    }

    .detail-row strong {
        font-size: 14px;
    }

    .round-card-footer {
        margin-top: 20px;
    }

    .view-button {
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-decoration: none;
        background: #4f46e5;
        color: white;
        padding: 13px 16px;
        border-radius: 8px;
        font-weight: bold;
    }

    .view-button:hover {
        background: #4338ca;
    }

    .arrow {
        font-size: 20px;
    }

    .rules-card {
        display: flex;
        gap: 20px;
        margin-top: 40px;
        padding: 25px;
        background: #eef2ff;
        border-radius: 15px;
    }

    .rules-icon {
        font-size: 30px;
    }

    .rules-content h3 {
        margin-bottom: 15px;
    }

    .rules-content li {
        margin-bottom: 8px;
        color: #4b5563;
    }

    .page-footer {
        text-align: center;
        padding: 25px;
        color: #6b7280;
    }

    @media (max-width: 900px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .rounds-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .page-header,
        .section-heading {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }

        .summary-grid,
        .rounds-grid {
            grid-template-columns: 1fr;
        }

        .game-info {
            flex-direction: column;
            gap: 8px;
        }
    }
</style>


</head>

<body>

<header class="page-header">

    <div class="header-left">

        <div class="header-icon">🔄</div>

        <div>
            <h1>Round Management</h1>
            <p>Manage and monitor all game rounds</p>
        </div>

    </div>

    <a href="dashboard.php" class="back-button">
        ← Back to Dashboard
    </a>

</header>


<main class="container">

    <!-- Page heading -->
    <section class="page-intro">

        <div>
            <span class="page-label">GAME CONFIGURATION</span>

            <h2>Round Management</h2>

            <p>
                Check question availability and round readiness
                before teams start playing.
            </p>
        </div>

    </section>


    <!-- Summary cards -->
    <section class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon purple">🔄</div>

            <div class="summary-content">
                <span>Total Rounds</span>
                <strong><?= $total_rounds ?></strong>
                <small>Game rounds</small>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon blue">📝</div>

            <div class="summary-content">
                <span>Total Questions</span>
                <strong><?= $total_questions ?></strong>
                <small>Questions added</small>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon green">✓</div>

            <div class="summary-content">
                <span>Active Teams</span>
                <strong><?= $active_teams ?></strong>
                <small>Currently active</small>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon orange">🏆</div>

            <div class="summary-content">
                <span>Completed Teams</span>
                <strong><?= $completed_teams ?></strong>
                <small>Game completed</small>
            </div>

        </div>

    </section>


    <!-- Rounds heading -->
    <section class="section-heading">

        <div>
            <h2>All Game Rounds</h2>

            <p>
                Each round requires 5 questions to become ready.
            </p>
        </div>

        <div class="game-info">

            <span>
                ⏱ <?= $question_time ?> seconds/question
            </span>

            <span>
                🕒 <?= $round_time ?> minutes/round
            </span>

        </div>

    </section>


    <!-- Round cards -->
    <section class="rounds-grid">

        <?php foreach ($rounds as $round): ?>

            <?php
                $question_count = $round["question_count"];

                $progress = min(
                    100,
                    ($question_count / $questions_per_round) * 100
                );

                $status_class = $round["is_ready"]
                    ? "ready"
                    : "not-ready";

                $status_text = $round["is_ready"]
                    ? "Ready"
                    : "Not Ready";
            ?>

            <article class="round-card">

                <!-- Card top -->
                <div class="round-card-top">

                    <div class="round-number">

                        <span>ROUND</span>

                        <strong>
                            <?= $round["number"] ?>
                        </strong>

                    </div>


                    <span class="status-badge <?= $status_class ?>">

                        <span class="status-dot"></span>

                        <?= $status_text ?>

                    </span>

                </div>


                <!-- Card title -->
                <div class="round-card-title">

                    <h3>
                        <?= htmlspecialchars($round["title"]) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars($round["description"]) ?>
                    </p>

                </div>


                <!-- Question progress -->
                <div class="question-progress">

                    <div class="progress-header">

                        <span>Questions</span>

                        <strong>
                            <?= $question_count ?> /
                            <?= $questions_per_round ?>
                        </strong>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-fill <?= $status_class ?>"
                            style="width: <?= $progress ?>%;"
                        ></div>

                    </div>


                    <p class="progress-text">

                        <?php if ($round["is_ready"]): ?>

                            All questions are ready

                        <?php else: ?>

                            <?= max(
                                0,
                                $questions_per_round - $question_count
                            ) ?>

                            more question(s) required

                        <?php endif; ?>

                    </p>

                </div>


                <!-- Round information -->
                <div class="round-details">

                    <div class="detail-row">

                        <span class="detail-icon">⏱</span>

                        <div>

                            <span class="detail-label">
                                Question Time
                            </span>

                            <strong>
                                <?= $question_time ?> Seconds
                            </strong>

                        </div>

                    </div>


                    <div class="detail-row">

                        <span class="detail-icon">🕒</span>

                        <div>

                            <span class="detail-label">
                                Round Time
                            </span>

                            <strong>
                                <?= $round_time ?> Minutes
                            </strong>

                        </div>

                    </div>


                    <div class="detail-row">

                        <span class="detail-icon">📝</span>

                        <div>

                            <span class="detail-label">
                                Required Questions
                            </span>

                            <strong>
                                <?= $questions_per_round ?> Questions
                            </strong>

                        </div>

                    </div>

                </div>


                <!-- Action -->
                <div class="round-card-footer">

                    <a
                        href="questions.php?round=<?= $round["number"] ?>"
                        class="view-button"
                    >

                        <span>View Questions</span>

                        <span class="arrow">→</span>

                    </a>

                </div>

            </article>

        <?php endforeach; ?>

    </section>


    <!-- Game rules -->
    <section class="rules-card">

        <div class="rules-icon">💡</div>

        <div class="rules-content">

            <h3>Game Round Rules</h3>

            <ul>

                <li>
                    There are 5 rounds in the game.
                </li>

                <li>
                    Each round contains 5 questions.
                </li>

                <li>
                    Each question has 60 seconds of betting time.
                </li>

                <li>
                    Each round has a maximum duration of 5 minutes.
                </li>

                <li>
                    A round becomes ready when all 5 questions are added.
                </li>

                <li>
                    Teams must complete the current round before continuing.
                </li>

            </ul>

        </div>

    </section>

</main>


<footer class="page-footer">

    <p>
        AI Prediction Market &copy; <?= date("Y") ?>
    </p>

</footer>

</body>

</html>