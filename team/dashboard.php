<?php

session_start();

/* =========================================================
   NO CACHE
========================================================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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
   GET LATEST TEAM DATA
========================================================= */

$stmt = $conn->prepare("
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
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $team_id);
$stmt->execute();

$result = $stmt->get_result();
$team = $result->fetch_assoc();

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
   UPDATE SESSION DATA
========================================================= */

$_SESSION["team_id"] = (int) $team["id"];
$_SESSION["team_code"] = $team["team_code"];
$_SESSION["team_name"] = $team["team_name"];
$_SESSION["coins"] = (int) $team["coins"];
$_SESSION["current_round"] = (int) $team["current_round"];
$_SESSION["current_question"] = (int) $team["current_question"];

/* =========================================================
   TEAM GAME DATA
========================================================= */

$current_round = (int) $team["current_round"];
$current_question = (int) $team["current_question"];
$coins = (int) $team["coins"];

/*
    current_round = 1 to 5  => round ready/active
    current_round >= 6      => game completed
*/

$game_completed = ($current_round >= 6);

/* =========================================================
   ANSWER STATISTICS
========================================================= */

$total_answers = 0;
$correct_answers = 0;
$wrong_answers = 0;

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_answers,
        COALESCE(SUM(is_correct), 0) AS correct_answers
    FROM answers
    WHERE team_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $team_id);
    $stmt->execute();

    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();

    $stmt->close();

    if ($stats) {
        $total_answers = (int) $stats["total_answers"];
        $correct_answers = (int) $stats["correct_answers"];
        $wrong_answers = $total_answers - $correct_answers;
    }
}

/* =========================================================
   GAME PROGRESS
========================================================= */

if ($game_completed) {
    $completed_questions = 25;
} else {
    $completed_questions =
        (($current_round - 1) * 5)
        + ($current_question - 1);
}

/* =========================================================
   PROTECT PROGRESS VALUE
========================================================= */

if ($completed_questions < 0) {
    $completed_questions = 0;
}

if ($completed_questions > 25) {
    $completed_questions = 25;
}

/* =========================================================
   PROGRESS PERCENTAGE
========================================================= */

$progress = ($completed_questions / 25) * 100;

/* =========================================================
   GAME MESSAGE
========================================================= */

if ($game_completed) {

    $game_message = "Game completed successfully!";

} elseif ($current_round >= 1 && $current_round <= 5) {

    $game_message =
        "Round " . $current_round .
        " is ready. Select the round to continue.";

} else {

    $game_message = "Please select a round to continue.";

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

    <title>Team Dashboard | AI Prediction Market</title>

    <link
        rel="stylesheet"
        href="../assets/team_dashboard.css"
    >

</head>

<body>

<header class="header">

    <div class="header-inner">

        <div class="brand">

            <div class="brand-icon">
                🤖
            </div>

            <div>
                <h2>AI Prediction Market</h2>
                <p>Team Portal</p>
            </div>

        </div>

        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</header>

<main class="container">

    <!-- =================================================
         WELCOME
    ================================================== -->

    <section class="welcome">

        <h1>
            Welcome,
            <?php
            echo htmlspecialchars(
                $team["team_name"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
            👋
        </h1>

        <p>
            Make your predictions wisely and manage your coins.
        </p>

    </section>

    <!-- =================================================
         STAT CARDS
    ================================================== -->

    <section class="cards">

        <div class="card">

            <div class="card-label">
                💰 Available Coins
            </div>

            <div class="card-value">
                <?php echo number_format($coins); ?>
            </div>

        </div>

        <div class="card">

            <div class="card-label">
                🎮 Current Round
            </div>

            <div class="card-value">

                <?php if ($game_completed): ?>

                    Completed

                <?php elseif ($current_round >= 1 && $current_round <= 5): ?>

                    <?php echo $current_round; ?> / 5

                <?php else: ?>

                    Not Selected

                <?php endif; ?>

            </div>

        </div>

        <div class="card">

            <div class="card-label">
                🎯 Correct Answers
            </div>

            <div class="card-value">
                <?php echo $correct_answers; ?>
            </div>

        </div>

        <div class="card">

            <div class="card-label">
                📝 Questions Answered
            </div>

            <div class="card-value">
                <?php echo $total_answers; ?> / 25
            </div>

        </div>

    </section>

    <!-- =================================================
         INSTRUCTIONS
    ================================================== -->

    <section class="instruction-panel">

        <div>

            <h2>📖 Game Instructions</h2>

            <p>
                Read the complete game instructions before starting a round.
            </p>

        </div>

        <a
            href="instructions.php"
            class="instruction-button"
        >
            📖 Read Instructions
        </a>

    </section>

    <!-- =================================================
         ROUND PANEL
    ================================================== -->

    <section class="game-panel">

        <h2>🎮 Round Selection</h2>

        <p class="game-message">
            <?php
            echo htmlspecialchars(
                $game_message,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </p>

        <?php if ($game_completed): ?>

            <!-- =========================================
                 GAME COMPLETED
            ========================================== -->

            <div class="no-round">

                <div class="no-round-icon">
                    🏆
                </div>

                <h3>
                    Game Completed
                </h3>

                <p>
                    You have completed all 5 rounds.
                </p>

                <a
                    href="result.php"
                    class="game-button result-button"
                >
                    🏆 View Final Result
                </a>

            </div>

        <?php elseif ($current_round >= 1 && $current_round <= 5): ?>

            <!-- =========================================
                 ROUND READY
            ========================================== -->

            <div class="active-round">

                <div class="active-icon">
                    🎯
                </div>

                <div>

                    <h3>
                        Round
                        <?php echo $current_round; ?>
                        Ready
                    </h3>

                    <p>
                        Select the round to continue.
                    </p>

                </div>

            </div>

            <a
                href="select_round.php"
                class="game-button continue-button"
            >
                🎮 Select Round
                <?php echo $current_round; ?>
            </a>

        <?php else: ?>

            <!-- =========================================
                 NO ROUND SELECTED
            ========================================== -->

            <div class="no-round">

                <div class="no-round-icon">
                    🎯
                </div>

                <h3>
                    No Round Selected
                </h3>

                <p>
                    Select a round to continue.
                </p>

                <a
                    href="select_round.php"
                    class="game-button"
                >
                    🎮 Select Round
                </a>

            </div>

        <?php endif; ?>

    </section>

    <!-- =================================================
         GAME PROGRESS
    ================================================== -->

    <section class="game-panel progress-panel">

        <h2>📊 Game Progress</h2>

        <p class="game-message">

            <?php echo $completed_questions; ?>
            of 25 questions completed
            (
            <?php echo number_format($progress, 0); ?>%
            )

        </p>

        <div class="progress-bar">

            <div
                class="progress-fill"
                style="width: <?php echo $progress; ?>%;"
            ></div>

        </div>

    </section>

    <!-- =================================================
         INFORMATION
    ================================================== -->

    <section class="info-grid">

        <div class="info-box">

            <h3>👤 Team Information</h3>

            <div class="info-row">

                <span class="info-label">
                    Team Name
                </span>

                <span class="info-value">
                    <?php
                    echo htmlspecialchars(
                        $team["team_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Team Code
                </span>

                <span class="info-value">
                    <?php
                    echo htmlspecialchars(
                        $team["team_code"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Status
                </span>

                <span class="info-value">
                    <?php
                    echo htmlspecialchars(
                        $team["status"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>
                </span>

            </div>

        </div>

        <div class="info-box">

            <h3>📊 Game Information</h3>

            <div class="info-row">

                <span class="info-label">
                    Total Rounds
                </span>

                <span class="info-value">
                    5
                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Questions / Round
                </span>

                <span class="info-value">
                    5
                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Time / Question
                </span>

                <span class="info-value">
                    60 Seconds
                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Maximum Round Time
                </span>

                <span class="info-value">
                    5 Minutes
                </span>

            </div>

            <div class="info-row">

                <span class="info-label">
                    Total Questions
                </span>

                <span class="info-value">
                    25
                </span>

            </div>

        </div>

    </section>

    <!-- =================================================
         GAME HISTORY
    ================================================== -->

    <section class="history-section">

        <a
            href="game_history.php"
            class="history-button"
        >
            📜 Game History
        </a>

    </section>

</main>

</body>
</html>