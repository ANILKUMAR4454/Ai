<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* =====================================================
   ADMIN LOGIN CHECK
===================================================== */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/* =====================================================
   FORMAT TIME
===================================================== */

function formatTime($seconds)
{
    $seconds = (int) $seconds;

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $remaining_seconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf(
            "%02d:%02d:%02d",
            $hours,
            $minutes,
            $remaining_seconds
        );
    }

    return sprintf(
        "%02d:%02d",
        $minutes,
        $remaining_seconds
    );
}

/* =====================================================
   GET ALL TEAMS
===================================================== */

$sql = "
    SELECT
        t.id,
        t.team_code,
        t.team_name,
        t.coins,
        t.status,

        COUNT(ta.id) AS total_answers,

        COALESCE(
            SUM(
                CASE
                    WHEN ta.is_correct = 1 THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS correct_answers,

        COALESCE(
            SUM(
                CASE
                    WHEN ta.is_correct = 0 THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS wrong_answers,

        COALESCE(SUM(ta.time_taken), 0) AS total_time

    FROM teams t

    LEFT JOIN team_answers ta
        ON t.id = ta.team_id

    GROUP BY
        t.id,
        t.team_code,
        t.team_name,
        t.coins,
        t.status

    ORDER BY
        t.coins DESC,
        correct_answers DESC,
        total_time ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Leaderboard query failed: " . mysqli_error($conn));
}

$teams = [];

while ($row = mysqli_fetch_assoc($result)) {

    $total_answers = (int) $row["total_answers"];
    $correct_answers = (int) $row["correct_answers"];

    $accuracy = 0;

    if ($total_answers > 0) {
        $accuracy = round(
            ($correct_answers / $total_answers) * 100,
            1
        );
    }

    $row["coins"] = (int) $row["coins"];
    $row["total_time"] = (int) $row["total_time"];
    $row["accuracy"] = $accuracy;

    $teams[] = $row;
}

/* =====================================================
   STATISTICS
===================================================== */

$total_teams = count($teams);

$highest_coins = 0;
$completed_teams = 0;
$game_over_teams = 0;

foreach ($teams as $team) {

    if ($team["coins"] > $highest_coins) {
        $highest_coins = $team["coins"];
    }

    $status = strtolower(trim($team["status"] ?? ""));

    if ($status === "completed") {
        $completed_teams++;
    }

    if (
        $status === "game over" ||
        $status === "game_over"
    ) {
        $game_over_teams++;
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

    <title>Admin Leaderboard | AI Prediction Market</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(
                    circle at top,
                    #26345d,
                    #111827 48%,
                    #080b14
                );
            color: #ffffff;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            margin: auto;
            padding: 25px;
        }

        /* HEADER */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .brand h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 800;
        }

        .brand p {
            margin: 8px 0 0;
            color: #9ca3af;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 10px;
            border: 1px solid #374151;
            background: #1f2937;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: 0.3s;
        }

        .btn:hover {
            background: #374151;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #6366f1;
            border-color: #6366f1;
        }

        .btn-primary:hover {
            background: #818cf8;
        }

        /* STAT CARDS */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            padding: 22px;
            border: 1px solid #293548;
            border-radius: 16px;
            background: rgba(17, 24, 39, 0.9);
        }

        .stat-label {
            margin-bottom: 10px;
            color: #9ca3af;
            font-size: 13px;
        }

        .stat-value {
            font-size: 27px;
            font-weight: 800;
        }

        /* TABLE */

        .table-card {
            overflow: hidden;
            border: 1px solid #293548;
            border-radius: 18px;
            background: rgba(17, 24, 39, 0.95);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 22px 25px;
            border-bottom: 1px solid #293548;
        }

        .table-header h2 {
            margin: 0;
            font-size: 21px;
        }

        .table-header p {
            margin: 7px 0 0;
            color: #9ca3af;
            font-size: 13px;
        }

        .live {
            padding: 7px 12px;
            border-radius: 20px;
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            font-size: 12px;
            font-weight: 700;
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

        th {
            padding: 16px 20px;
            text-align: left;
            background: #1f2937;
            color: #9ca3af;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            white-space: nowrap;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid #293548;
            font-size: 14px;
            white-space: nowrap;
        }

        tbody tr {
            transition: 0.25s;
        }

        tbody tr:hover {
            background: rgba(99, 102, 241, 0.08);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .rank {
            font-size: 18px;
            font-weight: 800;
        }

        .team-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .team-name {
            color: #ffffff;
            font-weight: 700;
        }

        .team-code {
            color: #9ca3af;
            font-size: 12px;
        }

        .accuracy {
            font-weight: 700;
        }

        .progress {
            width: 100px;
            height: 6px;
            margin-top: 7px;
            overflow: hidden;
            border-radius: 10px;
            background: #374151;
        }

        .progress-bar {
            height: 100%;
            border-radius: 10px;
            background: #6366f1;
        }

        .coins {
            color: #facc15;
            font-size: 15px;
            font-weight: 800;
        }

        .status {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .active {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
        }

        .completed {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }

        .game-over {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .unknown {
            background: rgba(156, 163, 175, 0.15);
            color: #d1d5db;
        }

        .empty {
            padding: 60px 20px;
            text-align: center;
            color: #9ca3af;
        }

        .footer {
            padding: 25px 0 10px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 500px) {
            .container {
                padding: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .brand h1 {
                font-size: 24px;
            }

            .table-header {
                padding: 18px;
            }

            .actions {
                width: 100%;
            }

            .actions .btn {
                flex: 1;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div class="brand">
            <h1>🏆 Admin Leaderboard</h1>
            <p>AI Prediction Market • All Team Rankings</p>
        </div>

        <div class="actions">

            <a href="dashboard.php" class="btn">
                🏠 Admin Dashboard
            </a>

            <a href="leaderboard.php" class="btn btn-primary">
                🔄 Refresh
            </a>

        </div>

    </div>

    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat-card">
            <div class="stat-label">Total Teams</div>
            <div class="stat-value">
                <?= $total_teams ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Highest Coins</div>
            <div class="stat-value">
                🪙 <?= number_format($highest_coins) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Completed Teams</div>
            <div class="stat-value">
                <?= $completed_teams ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Game Over Teams</div>
            <div class="stat-value">
                <?= $game_over_teams ?>
            </div>
        </div>

    </div>

    <!-- LEADERBOARD -->

    <div class="table-card">

        <div class="table-header">

            <div>
                <h2>All Team Rankings</h2>
                <p>
                    Sorted by coins, accuracy, and total time.
                </p>
            </div>

            <div class="live">
                ● LIVE
            </div>

        </div>

        <?php if (count($teams) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Team Code</th>
                            <th>Team Name</th>
                            <th>Accuracy</th>
                            <th>Coins</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($teams as $index => $team): ?>

                        <?php
                        $rank = $index + 1;

                        if ($rank === 1) {
                            $rank_display = "🥇";
                        } elseif ($rank === 2) {
                            $rank_display = "🥈";
                        } elseif ($rank === 3) {
                            $rank_display = "🥉";
                        } else {
                            $rank_display = $rank;
                        }

                        $status = strtolower(
                            trim((string) ($team["status"] ?? "active"))
                        );

                        if ($status === "game over" || $status === "game_over") {
                            $status_class = "game-over";
                            $status_text = "Game Over";
                        } elseif ($status === "completed") {
                            $status_class = "completed";
                            $status_text = "Completed";
                        } elseif ($status === "active") {
                            $status_class = "active";
                            $status_text = "Active";
                        } else {
                            $status_class = "unknown";
                            $status_text = ucfirst($status);
                        }
                        ?>

                        <tr>

                            <td>
                                <span class="rank">
                                    <?= $rank_display ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($team["team_code"]) ?>
                            </td>

                            <td>
                                <div class="team-info">

                                    <span class="team-name">
                                        <?= htmlspecialchars($team["team_name"]) ?>
                                    </span>

                                    <span class="team-code">
                                        Team ID:
                                        <?= (int) $team["id"] ?>
                                    </span>

                                </div>
                            </td>

                            <td>

                                <div class="accuracy">
                                    <?= $team["accuracy"] ?>%
                                </div>

                                <div class="progress">
                                    <div
                                        class="progress-bar"
                                        style="width: <?= min(100, max(0, $team["accuracy"])) ?>%;"
                                    ></div>
                                </div>

                            </td>

                            <td>
                                <span class="coins">
                                    🪙 <?= number_format($team["coins"]) ?>
                                </span>
                            </td>

                            <td>
                                ⏱️ <?= formatTime($team["total_time"]) ?>
                            </td>

                            <td>
                                <span class="status <?= $status_class ?>">
                                    ● <?= htmlspecialchars($status_text) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                <h3>No teams found</h3>
                <p>Team rankings will appear after teams are created.</p>
            </div>

        <?php endif; ?>

    </div>

    <div class="footer">
        AI Prediction Market Admin Panel © <?= date("Y") ?>
    </div>

</div>

</body>
</html>
