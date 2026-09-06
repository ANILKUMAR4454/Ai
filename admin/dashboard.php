<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/* =====================================================
   ADMIN AUTHENTICATION
===================================================== */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$admin_username = $_SESSION["admin_username"] ?? "Admin";


/* =====================================================
   SAFE COUNT FUNCTION
===================================================== */

function getCount($conn, $table)
{
    $allowed_tables = [
        "teams",
        "questions",
        "rounds",
        "predictions",
        "game_results"
    ];

    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    $check = mysqli_query(
        $conn,
        "SHOW TABLES LIKE '$table'"
    );

    if (!$check || mysqli_num_rows($check) === 0) {
        return 0;
    }

    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return (int) ($row["total"] ?? 0);
    }

    return 0;
}


/* =====================================================
   DASHBOARD COUNTS
===================================================== */

$total_teams = getCount($conn, "teams");
$total_questions = getCount($conn, "questions");
$total_rounds = getCount($conn, "rounds");
$total_predictions = getCount($conn, "predictions");
$total_results = getCount($conn, "game_results");


/* =====================================================
   DEFAULT GAME SETTINGS
===================================================== */

$game_status = "STOPPED";

$total_game_rounds = 5;
$questions_per_round = 5;
$round_minutes = 5;

$current_round = 1;
$current_question = 1;


/* =====================================================
   READ GAME CONTROL TABLE
===================================================== */

$control_check = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'game_control'"
);

if ($control_check && mysqli_num_rows($control_check) > 0) {

    $control_result = mysqli_query(
        $conn,
        "SELECT *
         FROM game_control
         WHERE id = 1
         LIMIT 1"
    );

    if ($control_result && mysqli_num_rows($control_result) > 0) {

        $control = mysqli_fetch_assoc($control_result);

        $game_status = strtoupper(
            $control["game_status"] ?? "STOPPED"
        );

        $current_round = (int) (
            $control["current_round"] ?? 1
        );

        $current_question = (int) (
            $control["current_question"] ?? 1
        );
    }
}


/* =====================================================
   GAME STATUS CLASS
===================================================== */

$status_class = "ready";

if ($game_status === "RUNNING") {
    $status_class = "running";
} elseif ($game_status === "STOPPED") {
    $status_class = "stopped";
} elseif ($game_status === "PAUSED") {
    $status_class = "paused";
}


/* =====================================================
   GAME PROGRESS
===================================================== */

$progress = 0;

if ($total_game_rounds > 0) {
    $progress = (
        $current_round / $total_game_rounds
    ) * 100;
}

$progress = min(100, max(0, $progress));

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard | AI Prediction Market</title>

    <link
        rel="stylesheet"
        href="../assets/admin.css"
    >
</head>

<body>

<div class="admin-layout">

    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">
                AI
            </div>

            <div>
                <h2>Prediction</h2>
                <span>Market</span>
            </div>

        </div>


        <nav class="sidebar-menu">

            <p class="menu-title">
                MAIN MENU
            </p>


            <a
                href="dashboard.php"
                class="menu-item active"
            >
                <span>▣</span>
                Dashboard
            </a>


            <a
                href="teams.php"
                class="menu-item"
            >
                <span>👥</span>
                Teams
            </a>


            <a
                href="questions.php"
                class="menu-item"
            >
                <span>❓</span>
                Questions
            </a>


            <a
                href="rounds.php"
                class="menu-item"
            >
                <span>🔄</span>
                Rounds
            </a>


            <p class="menu-title">
                GAME
            </p>


            <a
                href="game_control.php"
                class="menu-item"
            >
                <span>🎮</span>
                Game Control
            </a>


            <a
                href="results.php"
                class="menu-item"
            >
                <span>🏆</span>
                Results
            </a>


            <a
                href="leaderboard.php"
                class="menu-item"
            >
                <span>📊</span>
                Leaderboard
            </a>

            <a
    href="violations.php"
    class="menu-item"
>
    <span>⚠️</span>
    Team Violations
</a>


            <p class="menu-title">
                ACCOUNT
            </p>


            <a
                href="logout.php"
                class="menu-item logout-link"
            >
                <span>↪</span>
                Logout
            </a>

        </nav>


        <div class="sidebar-footer">

            <div class="admin-avatar">
                <?= strtoupper(
                    substr($admin_username, 0, 1)
                ) ?>
            </div>

            <div>
                <strong>
                    <?= htmlspecialchars($admin_username) ?>
                </strong>

                <small>
                    Administrator
                </small>
            </div>

        </div>

    </aside>


    <!-- =================================================
         MAIN CONTENT
    ================================================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <h1>
                    Dashboard
                </h1>

                <p>
                    Welcome back,
                    <?= htmlspecialchars($admin_username) ?>.
                </p>

            </div>


            <div class="topbar-right">

                <div class="game-status <?= $status_class ?>">

                    <span class="status-dot"></span>

                    <?= htmlspecialchars($game_status) ?>

                </div>


                <div class="profile">

                    <div class="profile-avatar">
                        <?= strtoupper(
                            substr($admin_username, 0, 1)
                        ) ?>
                    </div>

                    <div>

                        <strong>
                            <?= htmlspecialchars($admin_username) ?>
                        </strong>

                        <small>
                            Admin
                        </small>

                    </div>

                </div>

            </div>

        </header>


        <!-- =================================================
             STAT CARDS
        ================================================== -->

        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon teams-icon">
                    👥
                </div>

                <div class="stat-info">

                    <span>
                        Total Teams
                    </span>

                    <strong>
                        <?= $total_teams ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon questions-icon">
                    ❓
                </div>

                <div class="stat-info">

                    <span>
                        Questions
                    </span>

                    <strong>
                        <?= $total_questions ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon rounds-icon">
                    🔄
                </div>

                <div class="stat-info">

                    <span>
                        Total Rounds
                    </span>

                    <strong>
                        <?= $total_game_rounds ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon prediction-icon">
                    🎯
                </div>

                <div class="stat-info">

                    <span>
                        Predictions
                    </span>

                    <strong>
                        <?= $total_predictions ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- =================================================
             GAME OVERVIEW
        ================================================== -->

        <section class="dashboard-grid">


            <!-- CURRENT GAME -->

            <div class="panel current-game">

                <div class="panel-header">

                    <div>

                        <h2>
                            Current Game
                        </h2>

                        <p>
                            Live game information
                        </p>

                    </div>

                    <span class="live-label">
                        ● LIVE
                    </span>

                </div>


                <div class="game-info-grid">


                    <div class="game-info">

                        <span>
                            Current Round
                        </span>

                        <strong>
                            <?= $current_round ?>
                            /
                            <?= $total_game_rounds ?>
                        </strong>

                    </div>


                    <div class="game-info">

                        <span>
                            Question
                        </span>

                        <strong>
                            <?= $current_question ?>
                            /
                            <?= $questions_per_round ?>
                        </strong>

                    </div>


                    <div class="game-info">

                        <span>
                            Round Duration
                        </span>

                        <strong>
                            <?= $round_minutes ?> min
                        </strong>

                    </div>


                    <div class="game-info">

                        <span>
                            Results
                        </span>

                        <strong>
                            <?= $total_results ?>
                        </strong>

                    </div>

                </div>


                <div class="game-progress">

                    <div class="progress-header">

                        <span>
                            Game Progress
                        </span>

                        <strong>
                            <?= round($progress) ?>%
                        </strong>

                    </div>


                    <div class="progress-bar">

                        <div
                            class="progress-fill"
                            style="width: <?= $progress ?>%;"
                        ></div>

                    </div>

                </div>


                <div class="game-actions">

                    <a
                        href="game_control.php"
                        class="btn btn-primary"
                    >
                        🎮 Manage Game
                    </a>


                    <a
                        href="rounds.php"
                        class="btn btn-secondary"
                    >
                        🔄 Manage Rounds
                    </a>

                </div>

            </div>


            <!-- QUICK ACTIONS -->

            <div class="panel quick-actions">

                <div class="panel-header">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Manage your game quickly
                        </p>

                    </div>

                </div>


                <div class="action-list">


                    <a
                        href="add_team.php"
                        class="action-item"
                    >

                        <div class="action-icon">
                            👥
                        </div>

                        <div>

                            <strong>
                                Add Team
                            </strong>

                            <span>
                                Create a new team
                            </span>

                        </div>

                        <b>›</b>

                    </a>


                    <a
                        href="add_question.php"
                        class="action-item"
                    >

                        <div class="action-icon">
                            ❓
                        </div>

                        <div>

                            <strong>
                                Add Question
                            </strong>

                            <span>
                                Create a prediction question
                            </span>

                        </div>

                        <b>›</b>

                    </a>


                    <a
                        href="leaderboard.php"
                        class="action-item"
                    >

                        <div class="action-icon">
                            🏆
                        </div>

                        <div>

                            <strong>
                                View Leaderboard
                            </strong>

                            <span>
                                Check team rankings
                            </span>

                        </div>

                        <b>›</b>

                    </a>


                    <a
                        href="results.php"
                        class="action-item"
                    >

                        <div class="action-icon">
                            📊
                        </div>

                        <div>

                            <strong>
                                View Results
                            </strong>

                            <span>
                                Review game results
                            </span>

                        </div>

                        <b>›</b>

                    </a>

                </div>

            </div>

        </section>


        <!-- =================================================
             GAME CONFIGURATION
        ================================================== -->

        <section class="panel requirements-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Game Configuration
                    </h2>

                    <p>
                        Current prediction market settings
                    </p>

                </div>


                <a
                    href="game_control.php"
                    class="text-link"
                >
                    Manage Game →
                </a>

            </div>


            <div class="requirements-grid">


                <div class="requirement">

                    <span>
                        Total Rounds
                    </span>

                    <strong>
                        <?= $total_game_rounds ?>
                    </strong>

                </div>


                <div class="requirement">

                    <span>
                        Questions / Round
                    </span>

                    <strong>
                        <?= $questions_per_round ?>
                    </strong>

                </div>


                <div class="requirement">

                    <span>
                        Round Time
                    </span>

                    <strong>
                        <?= $round_minutes ?> Minutes
                    </strong>

                </div>


                <div class="requirement">

                    <span>
                        Starting Coins
                    </span>

                    <strong>
                        100
                    </strong>

                </div>


                <div class="requirement">

                    <span>
                        Minimum Bet
                    </span>

                    <strong>
                        10 Coins
                    </strong>

                </div>


                <div class="requirement">

                    <span>
                        Game Status
                    </span>

                    <strong class="<?= $status_class ?>">
                        <?= htmlspecialchars($game_status) ?>
                    </strong>

                </div>

            </div>

        </section>
     
        <!-- FOOTER -->

        <footer class="dashboard-footer">

            <span>
                AI Prediction Market
            </span>

            <span>
                Offline Web-Based Prediction Game
            </span>

        </footer>


    </main>

</div>

</body>
</html>