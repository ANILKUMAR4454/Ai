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
   GET TEAM
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        team_code,
        team_name,
        coins,
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
   TEAM STATUS
========================================================= */

if ($team["status"] !== "Active") {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
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

    <title>
        Game Instructions | AI Prediction Market
    </title>

    <link
        rel="stylesheet"
        href="../assets/instructions.css"
    >

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="header">

    <div class="header-inner">


        <!-- BRAND -->

        <div class="brand">

            <div class="brand-icon">
                🤖
            </div>

            <div>

                <h2>
                    AI Prediction Market
                </h2>

                <p>
                    Team Portal
                </p>

            </div>

        </div>


        <!-- BACK -->

        <a
            href="dashboard.php"
            class="back-button"
        >
            ← Dashboard
        </a>


    </div>

</header>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="container">


    <!-- =================================================
         PAGE TITLE
    ================================================= -->

    <section class="page-title">

        <div class="title-icon">
            📖
        </div>

        <div>

            <h1>
                Game Instructions
            </h1>

            <p>
                Please read all instructions carefully before starting the game.
            </p>

        </div>

    </section>



    <!-- =================================================
         WELCOME
    ================================================= -->

    <section class="welcome-card">

        <h2>
            👋 Welcome,
            <?php
            echo htmlspecialchars(
                $team["team_name"],
                ENT_QUOTES,
                "UTF-8"
            );
            ?>
        </h2>

        <p>
            Welcome to the AI Prediction Market game.
            Read the rules below carefully and make your predictions wisely.
        </p>

    </section>



    <!-- =================================================
         GAME OVERVIEW
    ================================================= -->

    <section class="instruction-card">

        <div class="section-heading">

            <span class="section-icon">
                🎮
            </span>

            <div>

                <h2>
                    Game Overview
                </h2>

                <p>
                    Basic information about the competition
                </p>

            </div>

        </div>


        <div class="rules-grid">


            <div class="rule-box">

                <span class="rule-icon">
                    🏆
                </span>

                <div>

                    <strong>
                        Total Rounds
                    </strong>

                    <p>
                        5 Rounds
                    </p>

                </div>

            </div>


            <div class="rule-box">

                <span class="rule-icon">
                    ❓
                </span>

                <div>

                    <strong>
                        Questions
                    </strong>

                    <p>
                        5 Questions / Round
                    </p>

                </div>

            </div>


            <div class="rule-box">

                <span class="rule-icon">
                    ⏱️
                </span>

                <div>

                    <strong>
                        Time
                    </strong>

                    <p>
                        60 Seconds / Question
                    </p>

                </div>

            </div>


            <div class="rule-box">

                <span class="rule-icon">
                    ⌛
                </span>

                <div>

                    <strong>
                        Round Limit
                    </strong>

                    <p>
                        Maximum 5 Minutes
                    </p>

                </div>

            </div>


            <div class="rule-box">

                <span class="rule-icon">
                    📊
                </span>

                <div>

                    <strong>
                        Total Questions
                    </strong>

                    <p>
                        25 Questions
                    </p>

                </div>

            </div>


            <div class="rule-box">

                <span class="rule-icon">
                    💰
                </span>

                <div>

                    <strong>
                        Currency
                    </strong>

                    <p>
                        Game Coins
                    </p>

                </div>

            </div>


        </div>

    </section>



    <!-- =================================================
         HOW TO PLAY
    ================================================= -->

    <section class="instruction-card">

        <div class="section-heading">

            <span class="section-icon">
                🎯
            </span>

            <div>

                <h2>
                    How to Play
                </h2>

                <p>
                    Follow these steps during the game
                </p>

            </div>

        </div>


        <div class="steps">


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <div class="step-content">

                    <h3>
                        Select a Round
                    </h3>

                    <p>
                        Select the round you want to play from the
                        available rounds.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <div class="step-content">

                    <h3>
                        Read the Instructions
                    </h3>

                    <p>
                        Read the round instructions carefully before
                        starting the round.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <div class="step-content">

                    <h3>
                        Start the Round
                    </h3>

                    <p>
                        Click the Start Round button after confirming
                        that you have read the instructions.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    4
                </div>

                <div class="step-content">

                    <h3>
                        Read the Question
                    </h3>

                    <p>
                        Each question will be displayed one at a time.
                        Read it carefully.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    5
                </div>

                <div class="step-content">

                    <h3>
                        Make Your Prediction
                    </h3>

                    <p>
                        Choose whether you think the statement is
                        Correct or Incorrect.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    6
                </div>

                <div class="step-content">

                    <h3>
                        Select Your Bet
                    </h3>

                    <p>
                        Select the number of coins you want to bet.
                        You cannot bet more coins than you have.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    7
                </div>

                <div class="step-content">

                    <h3>
                        Submit Your Answer
                    </h3>

                    <p>
                        Submit your prediction before the 60-second
                        question timer expires.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    8
                </div>

                <div class="step-content">

                    <h3>
                        Continue
                    </h3>

                    <p>
                        After submission, the next question will be
                        displayed automatically.
                    </p>

                </div>

            </div>


        </div>

    </section>



    <!-- =================================================
         COIN RULES
    ================================================= -->

    <section class="instruction-card">

        <div class="section-heading">

            <span class="section-icon">
                💰
            </span>

            <div>

                <h2>
                    Coin & Betting Rules
                </h2>

                <p>
                    Understand how your coins are calculated
                </p>

            </div>

        </div>


        <div class="coin-rules">


            <div class="coin-rule correct">

                <div class="coin-rule-icon">
                    ✅
                </div>

                <div>

                    <h3>
                        Correct Prediction
                    </h3>

                    <p>
                        If your prediction is correct, you receive
                        the same number of coins as your bet.
                    </p>

                    <strong>
                        Example: Bet 20 → +20 Coins
                    </strong>

                </div>

            </div>


            <div class="coin-rule wrong">

                <div class="coin-rule-icon">
                    ❌
                </div>

                <div>

                    <h3>
                        Wrong Prediction
                    </h3>

                    <p>
                        If your prediction is wrong, the amount you
                        bet will be deducted.
                    </p>

                    <strong>
                        Example: Bet 20 → -20 Coins
                    </strong>

                </div>

            </div>


            <div class="coin-rule timeout">

                <div class="coin-rule-icon">
                    ⏰
                </div>

                <div>

                    <h3>
                        Time Out
                    </h3>

                    <p>
                        If you do not submit your answer within
                        60 seconds, you will lose 10 coins.
                    </p>

                    <strong>
                        Too late, sorry! → -10 Coins
                    </strong>

                </div>

            </div>


        </div>

    </section>



    <!-- =================================================
         AI PREDICTION
    ================================================= -->

    <section class="instruction-card">

        <div class="section-heading">

            <span class="section-icon">
                🤖
            </span>

            <div>

                <h2>
                    AI Prediction
                </h2>

                <p>
                    Use AI information as a guide
                </p>

            </div>

        </div>


        <div class="ai-box">

            <div class="ai-icon">
                🤖
            </div>

            <div>

                <h3>
                    AI Prediction is Advisory
                </h3>

                <p>
                    The AI prediction percentage is displayed to help
                    you make your decision. It does not directly
                    determine your score.
                </p>

                <p>
                    Your score depends on your prediction,
                    the actual answer, and your selected bet.
                </p>

            </div>

        </div>

    </section>



    <!-- =================================================
         IMPORTANT RULES
    ================================================= -->

    <section class="instruction-card important-card">

        <div class="section-heading">

            <span class="section-icon">
                ⚠️
            </span>

            <div>

                <h2>
                    Important Rules
                </h2>

                <p>
                    Please remember these rules
                </p>

            </div>

        </div>


        <ul class="important-list">

            <li>
                <span>✓</span>
                Each question has a maximum time of 60 seconds.
            </li>

            <li>
                <span>✓</span>
                You can submit your answer before the timer expires.
            </li>

            <li>
                <span>✓</span>
                You cannot bet more coins than your available balance.
            </li>

            <li>
                <span>✓</span>
                Once an answer is submitted, it cannot be changed.
            </li>

            <li>
                <span>✓</span>
                Do not refresh or close the browser while answering.
            </li>

            <li>
                <span>✓</span>
                Do not use browser navigation to go back during an active question.
            </li>

            <li>
                <span>✓</span>
                Your final ranking depends on your game performance.
            </li>

            <li>
                <span>✓</span>
                The system automatically records your answers and results.
            </li>

        </ul>

    </section>



    <!-- =================================================
         BEFORE START
    ================================================= -->

    <section class="ready-card">

        <div class="ready-icon">
            🚀
        </div>

        <div class="ready-content">

            <h2>
                Ready to Play?
            </h2>

            <p>
                Make sure you understand all the rules before
                selecting and starting a round.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="start-button"
        >
            ← Back to Dashboard
        </a>

    </section>


</main>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

    <p>
        AI Prediction Market • Team Portal
    </p>

</footer>


</body>

</html>
