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
   GAME SETTINGS
===================================================== */

$TOTAL_ROUNDS = 5;
$QUESTIONS_PER_ROUND = 5;

$ROUND_SECONDS = 5 * 60;
$QUESTION_SECONDS = 60;


/* =====================================================
   GET TEAM DETAILS
===================================================== */

$sql = "
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
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Team query error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$team = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$team) {
    session_destroy();
    header("Location: login.php");
    exit();
}


/* =====================================================
   TEAM DETAILS
===================================================== */

$team_name = $team["team_name"] ?? "Team";
$team_code = $team["team_code"] ?? "";

$coins = (int) ($team["coins"] ?? 0);

$team_status = strtolower(
    trim($team["status"] ?? "waiting")
);

if ($team_status !== "active") {
    die("
        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>
            <h2>Your team is not active.</h2>
            <p>Please contact the administrator.</p>
        </div>
    ");
}


/* =====================================================
   GET CURRENT ROUND AND QUESTION FROM DATABASE
===================================================== */

$round = (int) ($team["current_round"] ?? 1);
$question_number = (int) ($team["current_question"] ?? 1);


/* =====================================================
   VALIDATE ROUND AND QUESTION
===================================================== */

if ($round < 1 || $round > $TOTAL_ROUNDS) {
    $round = 1;
}

if (
    $question_number < 1 ||
    $question_number > $QUESTIONS_PER_ROUND
) {
    $question_number = 1;
}


/* =====================================================
   RESET OLD SESSION WHEN DATABASE PROGRESS CHANGES
===================================================== */

/*
   After admin reset:

   current_round = 1
   current_question = 1

   The old team session may still contain
   Round 3 or Question 4.

   This code replaces the old session.
*/

$session_round = (int) (
    $_SESSION["game"]["round"] ?? 0
);

$session_question = (int) (
    $_SESSION["game"]["question"] ?? -1
);


/*
   Database question number is 1-based.
   Session question index is 0-based.
*/

$expected_session_question = $question_number - 1;

if (
    !isset($_SESSION["game"]) ||
    $session_round !== $round ||
    $session_question !== $expected_session_question
) {

    $_SESSION["game"] = [
        "round" => $round,
        "question" => $expected_session_question,
        "correct" => 0,
        "wrong" => 0,
        "late" => 0,
        "attempts" => [],
        "round_start" => time(),
        "question_start" => time()
    ];
}


/* =====================================================
   GET CURRENT SESSION VALUES
===================================================== */

$current_question_index = (int) (
    $_SESSION["game"]["question"] ?? 0
);

$question_number = $current_question_index + 1;


/* =====================================================
   UPDATE DATABASE CURRENT PROGRESS
===================================================== */

$stmt = mysqli_prepare(
    $conn,
    "UPDATE teams
     SET current_round = ?, current_question = ?
     WHERE id = ?"
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "iii",
        $round,
        $question_number,
        $team_id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}


/* =====================================================
   COUNT QUESTIONS
===================================================== */

$sql = "
    SELECT COUNT(*) AS total_questions
    FROM questions
    WHERE round_number = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Question count error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $round);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$count_data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$total_questions = (int) (
    $count_data["total_questions"] ?? 0
);

if ($total_questions <= 0) {
    die("No questions available for Round " . $round);
}


/* =====================================================
   CHECK ROUND COMPLETION
===================================================== */

if ($question_number > $total_questions) {

    header(
        "Location: round_result.php?round=" . $round
    );

    exit();
}


/* =====================================================
   TIMER CALCULATION
===================================================== */

$round_start = (int) (
    $_SESSION["game"]["round_start"] ?? time()
);

$question_start = (int) (
    $_SESSION["game"]["question_start"] ?? time()
);

$round_elapsed = time() - $round_start;
$question_elapsed = time() - $question_start;

$round_remaining = max(
    0,
    $ROUND_SECONDS - $round_elapsed
);

$question_remaining = max(
    0,
    $QUESTION_SECONDS - $question_elapsed
);


/* =====================================================
   LOAD CURRENT QUESTION
===================================================== */

$sql = "
    SELECT
        id,
        round_number,
        question_number,
        question_text,
        difficulty,
        correct_answer,
        ai_prediction,
        ai_prediction_percentage
    FROM questions
    WHERE round_number = ?
      AND question_number = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Question query error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $round,
    $question_number
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$question = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$question) {
    die(
        "Question not found for Round " .
        $round .
        " Question " .
        $question_number
    );
}


/* =====================================================
   SAFE DISPLAY VALUES
===================================================== */

$question_id = (int) $question["id"];

$question_text = htmlspecialchars(
    $question["question_text"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

$difficulty = htmlspecialchars(
    $question["difficulty"] ?? "Normal",
    ENT_QUOTES,
    "UTF-8"
);

$ai_prediction = htmlspecialchars(
    $question["ai_prediction"] ?? "Not available",
    ENT_QUOTES,
    "UTF-8"
);

$ai_percentage = (float) (
    $question["ai_prediction_percentage"] ?? 0
);

$progress = (
    $question_number / $total_questions
) * 100;

$round_time_display = gmdate(
    "i:s",
    $round_remaining
);

$question_time_display = gmdate(
    "i:s",
    $question_remaining
);

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
        AI Prediction Market | Round <?= $round ?>
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #172033;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 20px 7%;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }

        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
        }

        .logo span {
            color: #2563eb;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .coin-badge {
            padding: 11px 16px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 9px;
            font-weight: bold;
        }

        .logout {
            text-decoration: none;
            color: #475569;
            border: 1px solid #d1d5db;
            padding: 11px 16px;
            border-radius: 9px;
            font-size: 14px;
        }

        .logout:hover {
            background: #f8fafc;
        }

        .container {
            width: 90%;
            max-width: 1050px;
            margin: 35px auto;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin-bottom: 8px;
            font-size: 30px;
            color: #111827;
        }

        .page-heading p {
            color: #64748b;
            font-size: 14px;
        }

        .round-tag {
            padding: 12px 17px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            color: #2563eb;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .info-card,
        .question-card,
        .answer-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        }

        .card-label {
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .card-value {
            font-size: 28px;
            font-weight: bold;
            color: #111827;
        }

        .blue {
            color: #2563eb;
        }

        .muted {
            color: #94a3b8;
            font-size: 13px;
            margin-top: 8px;
        }

        .progress-track {
            width: 100%;
            height: 9px;
            background: #e5e7eb;
            border-radius: 20px;
            margin-top: 16px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: <?= $progress ?>%;
            background: #2563eb;
            border-radius: 20px;
            transition: width 0.3s ease;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }

        .timer {
            color: #2563eb;
            font-size: 32px;
            font-weight: bold;
        }

        .timer.danger {
            color: #dc2626;
        }

        .question-card {
            margin-bottom: 20px;
        }

        .question-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 18px;
            margin-bottom: 25px;
        }

        .question-number {
            color: #2563eb;
            font-size: 14px;
            font-weight: bold;
        }

        .question-text {
            font-size: 25px;
            line-height: 1.5;
            font-weight: bold;
            color: #111827;
        }

        .difficulty {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 7px;
            color: #475569;
            font-size: 13px;
        }

        .ai-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .ai-title {
            color: #1e40af;
            font-weight: bold;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .ai-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .ai-percentage {
            font-size: 45px;
            font-weight: bold;
            color: #2563eb;
        }

        .ai-description {
            text-align: right;
            color: #64748b;
            font-size: 14px;
        }

        .ai-description strong {
            display: block;
            color: #1d4ed8;
            font-size: 19px;
            margin-top: 6px;
        }

        .section-title {
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .answer-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 22px;
        }

        .answer-btn {
            padding: 17px;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            color: #334155;
            transition: 0.2s ease;
        }

        .answer-btn:hover {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
        }

        .answer-btn.selected {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .investment-label {
            display: block;
            color: #475569;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .investment-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-size: 16px;
            outline: none;
        }

        .investment-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px #dbeafe;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            margin-top: 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .submit-btn:hover {
            background: #1d4ed8;
        }

        .submit-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .warning {
            display: none;
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
        }

        @media (max-width: 700px) {

            .header,
            .page-heading {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
            }

            .dashboard-grid,
            .answer-options {
                grid-template-columns: 1fr;
            }

            .question-text {
                font-size: 21px;
            }

            .ai-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .ai-description {
                text-align: left;
            }

            .round-tag {
                white-space: normal;
            }

        }

        @media (max-width: 450px) {

            .container {
                width: 94%;
                margin: 22px auto;
            }

            .header {
                padding: 18px 4%;
            }

            .info-card,
            .question-card,
            .answer-card,
            .ai-card {
                padding: 20px;
            }

            .page-heading h1 {
                font-size: 25px;
            }

            .question-top {
                align-items: flex-start;
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<header class="header">

    <div class="logo">
        AI <span>PREDICTION MARKET</span>
    </div>

    <div class="header-right">

        <div class="coin-badge">
            🪙 <span id="headerCoins"><?= $coins ?></span> Coins
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

    <!-- PAGE HEADING -->

    <div class="page-heading">

        <div>

            <h1>
                Round <?= $round ?> Challenge
            </h1>

            <p>
                Analyze the AI prediction and invest your coins wisely.
            </p>

        </div>

        <div class="round-tag">
            ROUND <?= $round ?> OF <?= $TOTAL_ROUNDS ?>
        </div>

    </div>


    <!-- QUESTION PROGRESS -->

    <div class="info-card" style="margin-bottom:20px;">

        <div class="card-label">
            Round Progress
        </div>

        <div class="card-value">

            Question
            <span class="blue">
                <?= $question_number ?>
            </span>

            <span style="font-size:17px;color:#94a3b8;">
                / <?= $total_questions ?>
            </span>

        </div>

        <div class="progress-track">

            <div class="progress-bar"></div>

        </div>

    </div>


    <!-- COINS AND QUESTION TIMER -->

    <div class="dashboard-grid">

        <div class="info-card">

            <div class="card-label">
                Available Coins
            </div>

            <div
                class="card-value blue"
                id="coinDisplay"
            >
                <?= $coins ?> 🪙
            </div>

            <div class="muted">
                Your current investment balance
            </div>

        </div>


        <div class="info-card">

            <div class="card-label">
                Question Time Remaining
            </div>

            <div
                class="timer"
                id="questionTimer"
            >
                <?= $question_time_display ?>
            </div>

            <div class="muted">
                You have 60 seconds to answer
            </div>

        </div>

    </div>


    <!-- ROUND TIMER -->

    <div
        class="info-card"
        style="margin-bottom:20px;"
    >

        <div class="card-label">
            Round Time Remaining
        </div>

        <div
            class="timer"
            id="roundTimer"
        >
            <?= $round_time_display ?>
        </div>

        <div class="muted">
            Five minutes for the complete round
        </div>

    </div>


    <!-- QUESTION CARD -->

    <div class="question-card">

        <div class="question-top">

            <div class="question-number">
                Question <?= $question_number ?>
            </div>

            <div>
                ⏱
                <strong id="questionTimerSmall">
                    <?= $question_time_display ?>
                </strong>
            </div>

        </div>

        <div class="question-text">
            <?= $question_text ?>
        </div>

        <div class="difficulty">
            ◈ Difficulty: <?= $difficulty ?>
        </div>

    </div>


    <!-- AI PREDICTION -->

    <div class="ai-card">

        <div class="ai-title">
            ✦ AI Market Prediction
        </div>

        <div class="ai-content">

            <div class="ai-percentage">
                <?= $ai_percentage ?>%
            </div>

            <div class="ai-description">

                The AI model predicts the answer is

                <strong>
                    <?= $ai_prediction ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- ANSWER FORM -->

    <div class="answer-card">

        <div class="section-title">
            Select Your Prediction
        </div>

        <form
            method="POST"
            action="submit_answer.php"
            id="answerForm"
        >

            <input
                type="hidden"
                name="question_id"
                value="<?= $question_id ?>"
            >

            <input
                type="hidden"
                name="round_number"
                value="<?= $round ?>"
            >

            <input
                type="hidden"
                name="question_number"
                value="<?= $question_number ?>"
            >

            <input
                type="hidden"
                name="prediction"
                id="prediction"
                value=""
            >

            <input
                type="hidden"
                name="auto_timeout"
                id="auto_timeout"
                value="0"
            >

            <div class="answer-options">

                <button
                    type="button"
                    class="answer-btn"
                    onclick="selectAnswer('Correct', this)"
                >
                    Correct
                </button>

                <button
                    type="button"
                    class="answer-btn"
                    onclick="selectAnswer('Incorrect', this)"
                >
                    Incorrect
                </button>

            </div>


            <label
                class="investment-label"
                for="bet_amount"
            >
                Investment Coins
            </label>

            <input
                type="number"
                name="bet_amount"
                id="bet_amount"
                class="investment-input"
                min="1"
                max="<?= $coins ?>"
                placeholder="Enter investment amount"
                required
            >


            <div
                class="warning"
                id="warningMessage"
            ></div>


            <button
                type="submit"
                class="submit-btn"
                id="submitBtn"
            >
                Submit Answer
            </button>

        </form>

    </div>

</main>


<script>

/* =====================================================
   TIMER VALUES
===================================================== */

let roundTime = <?= $round_remaining ?>;
let questionTime = <?= $question_remaining ?>;

let timeoutSubmitted = false;


/* =====================================================
   FORMAT TIME
===================================================== */

function formatTime(seconds) {

    seconds = Math.max(0, seconds);

    const minutes = Math.floor(seconds / 60);

    const secs = seconds % 60;

    return String(minutes).padStart(2, "0")
        + ":"
        + String(secs).padStart(2, "0");
}


/* =====================================================
   UPDATE TIMERS
===================================================== */

function updateTimers() {

    const roundTimer =
        document.getElementById("roundTimer");

    const questionTimer =
        document.getElementById("questionTimer");

    const questionTimerSmall =
        document.getElementById("questionTimerSmall");


    roundTimer.textContent =
        formatTime(roundTime);

    questionTimer.textContent =
        formatTime(questionTime);

    questionTimerSmall.textContent =
        formatTime(questionTime);


    if (roundTime <= 30) {
        roundTimer.classList.add("danger");
    }

    if (questionTime <= 10) {
        questionTimer.classList.add("danger");
        questionTimerSmall.classList.add("danger");
    }


    if (
        roundTime <= 0 ||
        questionTime <= 0
    ) {

        if (!timeoutSubmitted) {

            timeoutSubmitted = true;

            submitTimeout();

        }

        return;
    }


    roundTime--;
    questionTime--;

}


/* =====================================================
   START TIMER
===================================================== */

updateTimers();

setInterval(updateTimers, 1000);


/* =====================================================
   SELECT ANSWER
===================================================== */

function selectAnswer(answer, button) {

    document.getElementById("prediction").value = answer;

    document.querySelectorAll(".answer-btn").forEach(
        function(btn) {
            btn.classList.remove("selected");
        }
    );

    button.classList.add("selected");

}


/* =====================================================
   SHOW WARNING
===================================================== */

function showWarning(message) {

    const warning =
        document.getElementById("warningMessage");

    warning.textContent = message;

    warning.style.display = "block";

}


/* =====================================================
   SUBMIT TIMEOUT
===================================================== */

function submitTimeout() {

    document.getElementById("prediction").value =
        "TIMEOUT";

    document.getElementById("auto_timeout").value =
        "1";

    document.getElementById("bet_amount").value =
        "0";

    document.getElementById("answerForm").submit();

}


/* =====================================================
   FORM VALIDATION
===================================================== */

document.getElementById("answerForm").addEventListener(
    "submit",
    function(event) {

        const prediction =
            document.getElementById("prediction").value;

        const investment =
            parseInt(
                document.getElementById("bet_amount").value
            ) || 0;

        const availableCoins =
            <?= $coins ?>;

        const isTimeout =
            document.getElementById("auto_timeout").value === "1";


        if (!isTimeout && prediction === "") {

            event.preventDefault();

            showWarning(
                "Please select Correct or Incorrect."
            );

            return;
        }


        if (!isTimeout && investment <= 0) {

            event.preventDefault();

            showWarning(
                "Please enter a valid investment amount."
            );

            return;
        }


        if (!isTimeout && investment > availableCoins) {

            event.preventDefault();

            showWarning(
                "You do not have enough coins."
            );

            return;
        }


        document.getElementById("submitBtn").disabled =
            true;

        document.getElementById("submitBtn").textContent =
            "Submitting...";

    }
);

</script>

</body>

</html>
