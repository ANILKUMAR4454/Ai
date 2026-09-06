<?php

session_start();

/* =====================================================
   CACHE CONTROL
===================================================== */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/* =====================================================
   DATABASE
===================================================== */

require_once "../db.php";

/* =====================================================
   ADMIN AUTHENTICATION
===================================================== */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/* =====================================================
   GAME SETTINGS
===================================================== */

$TOTAL_ROUNDS = 5;

/* =====================================================
   HELPER FUNCTIONS
===================================================== */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatTime($seconds)
{
    $seconds = (int) $seconds;

    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;

    return sprintf(
        "%02d:%02d",
        $minutes,
        $remaining_seconds
    );
}

/* =====================================================
   GET ALL TEAM RESULTS
=====================================================

   IMPORTANT:
   This query does NOT use:
   - a.coin_change
   - a.answer_result

   It uses only:
   - teams.coins
   - team_answers.is_correct
   - team_answers.time_taken
===================================================== */

$sql = "
    SELECT
        t.id,
        t.team_code,
        t.team_name,
        t.coins,
        t.current_round,
        t.status,

        COUNT(a.id) AS total_answers,

        COALESCE(
            SUM(
                CASE
                    WHEN a.is_correct = 1
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS correct_answers,

        COALESCE(
            SUM(
                CASE
                    WHEN a.is_correct = 0
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS wrong_answers,

        COALESCE(
            SUM(a.time_taken),
            0
        ) AS total_time

    FROM teams t

    LEFT JOIN team_answers a
        ON t.id = a.team_id

    GROUP BY
        t.id,
        t.team_code,
        t.team_name,
        t.coins,
        t.current_round,
        t.status

    ORDER BY
        t.coins DESC,
        correct_answers DESC,
        total_time ASC,
        t.id ASC
";

/* =====================================================
   EXECUTE QUERY
===================================================== */

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Database error: " . mysqli_error($conn));
}

/* =====================================================
   FETCH TEAM RESULTS
===================================================== */

$teams = [];

while ($row = mysqli_fetch_assoc($result)) {

    $total_answers = (int) ($row["total_answers"] ?? 0);

    $correct_answers = (int) (
        $row["correct_answers"] ?? 0
    );

    $accuracy = 0;

    if ($total_answers > 0) {
        $accuracy = (
            $correct_answers /
            $total_answers
        ) * 100;
    }

    $row["accuracy"] = max(
        0,
        min(100, $accuracy)
    );

    $teams[] = $row;
}

/* =====================================================
   SUMMARY DATA
===================================================== */

$total_teams = count($teams);

$total_coins = 0;
$total_answers = 0;
$total_correct = 0;
$total_wrong = 0;
$total_time = 0;

foreach ($teams as $team) {

    $total_coins += (int) (
        $team["coins"] ?? 0
    );

    $total_answers += (int) (
        $team["total_answers"] ?? 0
    );

    $total_correct += (int) (
        $team["correct_answers"] ?? 0
    );

    $total_wrong += (int) (
        $team["wrong_answers"] ?? 0
    );

    $total_time += (int) (
        $team["total_time"] ?? 0
    );
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

    <title>All Team Results | AI Prediction Market</title>

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

        /* =================================================
           HEADER
        ================================================= */

        .header {
            background: #111827;
            color: white;
            padding: 18px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .logo {
            font-size: 23px;
            font-weight: bold;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .back-btn,
        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 10px 17px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .back-btn {
            background: #374151;
        }

        .back-btn:hover {
            background: #4b5563;
        }

        .logout-btn {
            background: #dc2626;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }

        /* =================================================
           MAIN CONTAINER
        ================================================= */

        .container {
            width: 94%;
            max-width: 1450px;
            margin: 40px auto;
        }

        .title {
            text-align: center;
            margin-bottom: 35px;
        }

        .title h1 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #111827;
        }

        .title p {
            color: #6b7280;
            font-size: 15px;
        }

        /* =================================================
           SUMMARY CARDS
        ================================================= */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.06);
            border-top: 4px solid #2563eb;
        }

        .stat-card.coins {
            border-top-color: #d97706;
        }

        .stat-card.correct {
            border-top-color: #16a34a;
        }

        .stat-card.wrong {
            border-top-color: #dc2626;
        }

        .stat-card .icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 28px;
            margin-bottom: 7px;
            color: #111827;
        }

        .stat-card p {
            color: #6b7280;
            font-size: 13px;
        }

        /* =================================================
           RESULTS CARD
        ================================================= */

        .results-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        .results-header {
            background: #2563eb;
            color: white;
            padding: 22px 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .results-header h2 {
            font-size: 21px;
        }

        .results-header p {
            margin-top: 5px;
            font-size: 13px;
            opacity: 0.9;
        }

        .search-box {
            width: 270px;
            padding: 11px 14px;
            border: none;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        /* =================================================
           TABLE
        ================================================= */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            color: #6b7280;
            padding: 17px 15px;
            text-align: center;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 17px 15px;
            border-top: 1px solid #f1f5f9;
            font-size: 14px;
            text-align: center;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        /* =================================================
           RANK
        ================================================= */

        .rank {
            font-size: 20px;
            font-weight: bold;
        }

        .rank-number {
            color: #6b7280;
            font-size: 15px;
        }

        /* =================================================
           TEAM DETAILS
        ================================================= */

        .team-info {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }

        .team-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #dbeafe;
            color: #1d4ed8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .team-details h4 {
            font-size: 15px;
            margin-bottom: 5px;
            color: #111827;
        }

        .team-details span {
            color: #9ca3af;
            font-size: 12px;
        }

        /* =================================================
           COINS
        ================================================= */

        .coin-value {
            color: #d97706;
            font-size: 17px;
            font-weight: bold;
            white-space: nowrap;
        }

        .coin-label {
            display: block;
            color: #9ca3af;
            font-size: 11px;
            margin-top: 4px;
        }

        /* =================================================
           ACCURACY
        ================================================= */

        .accuracy {
            color: #2563eb;
            font-size: 16px;
            font-weight: bold;
        }

        .accuracy-bar {
            width: 95px;
            height: 7px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin: 7px auto 0;
        }

        .accuracy-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 10px;
        }

        /* =================================================
           ANSWER COUNTS
        ================================================= */

        .correct-value {
            color: #16a34a;
            font-size: 16px;
            font-weight: bold;
        }

        .wrong-value {
            color: #dc2626;
            font-size: 16px;
            font-weight: bold;
        }

        .total-value {
            color: #374151;
            font-size: 16px;
            font-weight: bold;
        }

        /* =================================================
           TIME
        ================================================= */

        .time-value {
            color: #374151;
            font-size: 15px;
            font-weight: bold;
            white-space: nowrap;
        }

        .time-label {
            display: block;
            color: #9ca3af;
            font-size: 11px;
            margin-top: 4px;
        }

        /* =================================================
           ROUND PROGRESS
        ================================================= */

        .round-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }

        .progress-bar {
            width: 80px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 10px;
        }

        .round-text {
            color: #6b7280;
            font-size: 13px;
            white-space: nowrap;
        }

        /* =================================================
           STATUS
        ================================================= */

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-completed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* =================================================
           EMPTY STATE
        ================================================= */

        .empty-state {
            text-align: center;
            padding: 70px 20px;
        }

        .empty-state .icon {
            font-size: 50px;
            margin-bottom: 18px;
        }

        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =================================================
           FOOTER
        ================================================= */

        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: #9ca3af;
            font-size: 13px;
        }

        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 950px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: stretch;
            }

            .logo {
                font-size: 19px;
                text-align: center;
            }

            .header-actions {
                justify-content: center;
            }

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .title h1 {
                font-size: 28px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .results-header {
                padding: 18px;
                align-items: stretch;
            }

            .results-header h2 {
                font-size: 18px;
            }

            .search-box {
                width: 100%;
            }

        }

    </style>

</head>

<body>

    <!-- =================================================
         HEADER
    ================================================== -->

    <header class="header">

        <div class="logo">
            AI Prediction Market
        </div>

        <div class="header-actions">

            <a
                href="dashboard.php"
                class="back-btn"
            >
                ← Dashboard
            </a>

            <a
                href="logout.php"
                class="logout-btn"
            >
                Logout
            </a>

        </div>

    </header>

    <!-- =================================================
         MAIN CONTENT
    ================================================== -->

    <main class="container">

        <div class="title">

            <h1>📊 All Team Results</h1>

            <p>
                Complete performance report of all participating teams
            </p>

        </div>

        <!-- =================================================
             SUMMARY CARDS
        ================================================== -->

        <div class="stats">

            <div class="stat-card">

                <div class="icon">👥</div>

                <h3>
                    <?= $total_teams ?>
                </h3>

                <p>
                    Total Teams
                </p>

            </div>

            <div class="stat-card coins">

                <div class="icon">🪙</div>

                <h3>
                    <?= number_format($total_coins) ?>
                </h3>

                <p>
                    Combined Coins
                </p>

            </div>

            <div class="stat-card correct">

                <div class="icon">✅</div>

                <h3>
                    <?= number_format($total_correct) ?>
                </h3>

                <p>
                    Correct Answers
                </p>

            </div>

            <div class="stat-card wrong">

                <div class="icon">❌</div>

                <h3>
                    <?= number_format($total_wrong) ?>
                </h3>

                <p>
                    Wrong / Timeout Answers
                </p>

            </div>

        </div>

        <!-- =================================================
             RESULTS TABLE
        ================================================== -->

        <section class="results-card">

            <div class="results-header">

                <div>

                    <h2>
                        Team Performance Details
                    </h2>

                    <p>
                        All teams are ranked according to their current coin balance
                    </p>

                </div>

                <input
                    type="text"
                    id="searchInput"
                    class="search-box"
                    placeholder="Search team name or code..."
                    onkeyup="searchTeams()"
                >

            </div>

            <div class="table-wrapper">

                <?php if ($total_teams > 0): ?>

                    <table id="resultsTable">

                        <thead>

                            <tr>

                                <th>Rank</th>

                                <th>Team</th>

                                <th>Current Coins</th>

                                <th>Total Answers</th>

                                <th>Correct</th>

                                <th>Wrong / Timeout</th>

                                <th>Accuracy</th>

                                <th>Total Time</th>

                                <th>Rounds</th>

                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php

                            $rank = 1;

                            foreach ($teams as $row):

                                $team_name = $row["team_name"] ?? "Unknown Team";

                                $team_code = $row["team_code"] ?? "N/A";

                                $team_coins = (int) (
                                    $row["coins"] ?? 0
                                );

                                $completed_rounds = (int) (
                                    $row["current_round"] ?? 0
                                );

                                $total_answer_count = (int) (
                                    $row["total_answers"] ?? 0
                                );

                                $correct_answer_count = (int) (
                                    $row["correct_answers"] ?? 0
                                );

                                $wrong_answer_count = (int) (
                                    $row["wrong_answers"] ?? 0
                                );

                                $total_team_time = (int) (
                                    $row["total_time"] ?? 0
                                );

                                $accuracy = (float) (
                                    $row["accuracy"] ?? 0
                                );

                                $completed_rounds = max(
                                    0,
                                    min(
                                        $TOTAL_ROUNDS,
                                        $completed_rounds
                                    )
                                );

                                $progress = (
                                    $completed_rounds /
                                    $TOTAL_ROUNDS
                                ) * 100;

                                $formatted_time = formatTime(
                                    $total_team_time
                                );

                                $initial = strtoupper(
                                    substr($team_name, 0, 1)
                                );

                                $status = strtolower(
                                    trim($row["status"] ?? "active")
                                );

                                ?>

                                <tr>

                                    <!-- RANK -->

                                    <td class="rank">

                                        <?php if ($rank === 1): ?>

                                            🥇

                                        <?php elseif ($rank === 2): ?>

                                            🥈

                                        <?php elseif ($rank === 3): ?>

                                            🥉

                                        <?php else: ?>

                                            <span class="rank-number">
                                                #<?= $rank ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- TEAM -->

                                    <td>

                                        <div class="team-info">

                                            <div class="team-avatar">

                                                <?= e($initial) ?>

                                            </div>

                                            <div class="team-details">

                                                <h4>
                                                    <?= e($team_name) ?>
                                                </h4>

                                                <span>
                                                    Code:
                                                    <?= e($team_code) ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>

                                    <!-- CURRENT COINS -->

                                    <td>

                                        <span class="coin-value">

                                            🪙
                                            <?= number_format($team_coins) ?>

                                        </span>

                                        <span class="coin-label">
                                            Current Balance
                                        </span>

                                    </td>

                                    <!-- TOTAL ANSWERS -->

                                    <td>

                                        <span class="total-value">
                                            <?= $total_answer_count ?>
                                        </span>

                                    </td>

                                    <!-- CORRECT ANSWERS -->

                                    <td>

                                        <span class="correct-value">
                                            <?= $correct_answer_count ?>
                                        </span>

                                    </td>

                                    <!-- WRONG ANSWERS -->

                                    <td>

                                        <span class="wrong-value">
                                            <?= $wrong_answer_count ?>
                                        </span>

                                    </td>

                                    <!-- ACCURACY -->

                                    <td>

                                        <span class="accuracy">

                                            <?= number_format(
                                                $accuracy,
                                                1
                                            ) ?>%

                                        </span>

                                        <div class="accuracy-bar">

                                            <div
                                                class="accuracy-fill"
                                                style="width: <?= $accuracy ?>%;"
                                            ></div>

                                        </div>

                                    </td>

                                    <!-- TOTAL TIME -->

                                    <td>

                                        <span class="time-value">

                                            ⏱️
                                            <?= $formatted_time ?>

                                        </span>

                                        <span class="time-label">
                                            mm:ss
                                        </span>

                                    </td>

                                    <!-- ROUND PROGRESS -->

                                    <td>

                                        <div class="round-progress">

                                            <div class="progress-bar">

                                                <div
                                                    class="progress-fill"
                                                    style="width: <?= $progress ?>%;"
                                                ></div>

                                            </div>

                                            <span class="round-text">

                                                <?= $completed_rounds ?>
                                                /
                                                <?= $TOTAL_ROUNDS ?>

                                            </span>

                                        </div>

                                    </td>

                                    <!-- STATUS -->

                                    <td>

                                        <?php if (
                                            $completed_rounds >= $TOTAL_ROUNDS
                                        ): ?>

                                            <span class="status status-completed">
                                                🏆 Completed
                                            </span>

                                        <?php elseif ($status === "pending"): ?>

                                            <span class="status status-pending">
                                                ⏳ Pending
                                            </span>

                                        <?php else: ?>

                                            <span class="status status-active">
                                                ● Active
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                                <?php

                                $rank++;

                            endforeach;

                            ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="icon">
                            📊
                        </div>

                        <h3>
                            No Results Available
                        </h3>

                        <p>
                            Team results will appear after teams submit answers.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <div class="footer">

            AI Prediction Market © <?= date("Y") ?>

        </div>

    </main>

    <!-- =================================================
         SEARCH SCRIPT
    ================================================== -->

    <script>

        function searchTeams() {

            const input = document
                .getElementById("searchInput")
                .value
                .toLowerCase();

            const table = document.getElementById("resultsTable");

            if (!table) {
                return;
            }

            const rows = table
                .getElementsByTagName("tbody")[0]
                .getElementsByTagName("tr");

            for (let i = 0; i < rows.length; i++) {

                const rowText = rows[i]
                    .textContent
                    .toLowerCase();

                if (rowText.includes(input)) {

                    rows[i].style.display = "";

                } else {

                    rows[i].style.display = "none";

                }

            }

        }

    </script>

</body>

</html>
