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
   ONLY POST REQUEST
===================================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: game.php");
    exit();
}

/* =====================================================
   GAME SETTINGS
===================================================== */

$TOTAL_ROUNDS = 5;
$QUESTIONS_PER_ROUND = 5;

/* =====================================================
   GET FORM VALUES
===================================================== */

$question_id = (int) ($_POST["question_id"] ?? 0);

$posted_round = (int) ($_POST["round_number"] ?? 0);

$posted_question = (int) ($_POST["question_number"] ?? 0);

$prediction = strtoupper(
    trim($_POST["prediction"] ?? "")
);

$auto_timeout = (int) ($_POST["auto_timeout"] ?? 0);

$bet_amount = (int) ($_POST["bet_amount"] ?? 0);

/* =====================================================
   BASIC VALIDATION
===================================================== */

if ($question_id <= 0) {
    die("Invalid question.");
}

if ($posted_round < 1 || $posted_question < 1) {
    die("Invalid round or question.");
}

/* =====================================================
   GET CURRENT TEAM
===================================================== */

$sql = "
    SELECT
        id,
        coins,
        current_round,
        current_question,
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
    die("Team not found.");
}

/* =====================================================
   TEAM STATUS CHECK
===================================================== */

$team_status = strtolower(
    trim($team["status"] ?? "")
);

if ($team_status !== "active") {
    die("Your team is not active.");
}

/* =====================================================
   DATABASE VALUES ARE AUTHORITY
===================================================== */

$db_round = (int) $team["current_round"];

$db_question = (int) $team["current_question"];

$current_coins = (int) $team["coins"];

/* =====================================================
   PREVENT OLD PAGE SUBMISSION
===================================================== */

if (
    $posted_round !== $db_round ||
    $posted_question !== $db_question
) {
    header(
        "Location: game.php?round=" .
        $db_round .
        "&error=old_question"
    );
    exit();
}

/* =====================================================
   PREVENT DUPLICATE SUBMISSION
===================================================== */

$sql = "
    SELECT id
    FROM team_answers
    WHERE team_id = ?
      AND round_number = ?
      AND question_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Duplicate check error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "iii",
    $team_id,
    $db_round,
    $question_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$existing_answer = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if ($existing_answer) {
    header(
        "Location: game.php?round=" .
        $db_round .
        "&error=already_answered"
    );
    exit();
}

/* =====================================================
   GET CURRENT QUESTION
===================================================== */

$sql = "
    SELECT
        id,
        round_number,
        question_number,
        correct_answer
    FROM questions
    WHERE id = ?
      AND round_number = ?
      AND question_number = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Question query error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "iii",
    $question_id,
    $db_round,
    $db_question
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$question = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$question) {
    die("Question not found.");
}

$correct_answer = strtoupper(
    trim($question["correct_answer"] ?? "")
);

/* =====================================================
   VALIDATE ANSWER AND INVESTMENT
===================================================== */

$is_timeout = (
    $auto_timeout === 1 ||
    $prediction === "TIMEOUT"
);

if ($is_timeout) {

    $prediction = "TIMEOUT";

    /*
       Timeout always deducts 10 coins.
    */
    $bet_amount = 0;

} else {

    if (
        $prediction !== "CORRECT" &&
        $prediction !== "INCORRECT"
    ) {
        die("Please select Correct or Incorrect.");
    }

    if ($bet_amount <= 0) {
        die("Investment must be greater than zero.");
    }

    if ($bet_amount > $current_coins) {
        die("You do not have enough coins.");
    }
}

/* =====================================================
   CALCULATE ANSWER RESULT
===================================================== */

$is_correct = 0;
$coins_change = 0;

if ($is_timeout) {

    /*
       Timeout: deduct 10 coins.
    */

    $is_correct = 0;
    $coins_change = -10;

} elseif ($prediction === $correct_answer) {

    /*
       Correct answer: add investment.
    */

    $is_correct = 1;
    $coins_change = $bet_amount;

} else {

    /*
       Wrong answer: deduct investment.
    */

    $is_correct = 0;
    $coins_change = -$bet_amount;
}

/* =====================================================
   CALCULATE NEW COINS
===================================================== */

$new_coins = $current_coins + $coins_change;

if ($new_coins < 0) {
    $new_coins = 0;
}

/* =====================================================
   CALCULATE TIME TAKEN
===================================================== */

$time_taken = 0;

if (isset($_SESSION["game"]["question_start"])) {

    $time_taken =
        time() -
        (int) $_SESSION["game"]["question_start"];
}

if ($time_taken < 0) {
    $time_taken = 0;
}

/* =====================================================
   CALCULATE NEXT QUESTION
===================================================== */

$next_round = $db_round;

$next_question = $db_question + 1;

$round_completed = false;

$game_completed = false;

/*
   Last question of current round.
*/

if ($next_question > $QUESTIONS_PER_ROUND) {

    $round_completed = true;

    $next_round = $db_round + 1;

    $next_question = 1;
}

/*
   Last question of last round.
*/

if ($next_round > $TOTAL_ROUNDS) {

    $game_completed = true;

    $next_round = $TOTAL_ROUNDS;

    $next_question = $QUESTIONS_PER_ROUND;
}

/* =====================================================
   START DATABASE TRANSACTION
===================================================== */

mysqli_begin_transaction($conn);

try {

    /* -------------------------------------------------
       SAVE ANSWER
    ------------------------------------------------- */

    $sql = "
        INSERT INTO team_answers
        (
            team_id,
            round_number,
            question_id,
            is_correct,
            time_taken,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, NOW())
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(
            "Answer insert error: " . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiiii",
        $team_id,
        $db_round,
        $question_id,
        $is_correct,
        $time_taken
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "Answer insert failed: " .
            mysqli_stmt_error($stmt)
        );
    }

    mysqli_stmt_close($stmt);

    /* -------------------------------------------------
       UPDATE TEAM COINS
    ------------------------------------------------- */

    $sql = "
        UPDATE teams
        SET coins = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(
            "Coins update error: " . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $new_coins,
        $team_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "Coins update failed: " .
            mysqli_stmt_error($stmt)
        );
    }

    mysqli_stmt_close($stmt);

    /* -------------------------------------------------
       UPDATE TEAM PROGRESS
    ------------------------------------------------- */

    /*
       If coins become zero, keep the current position.
       The user cannot continue to another question.
    */

    if ($new_coins <= 0) {

        $progress_round = $db_round;
        $progress_question = $db_question;

    } else {

        $progress_round = $next_round;
        $progress_question = $next_question;
    }

    /*
       This query does not use current_at.
       Therefore, it works even if current_at
       does not exist in your teams table.
    */

    $sql = "
        UPDATE teams
        SET
            current_round = ?,
            current_question = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(
            "Progress update error: " . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iii",
        $progress_round,
        $progress_question,
        $team_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "Progress update failed: " .
            mysqli_stmt_error($stmt)
        );
    }

    mysqli_stmt_close($stmt);

    /* -------------------------------------------------
       COMMIT DATABASE
    ------------------------------------------------- */

    mysqli_commit($conn);

} catch (Exception $e) {

    mysqli_rollback($conn);

    die("Submission failed: " . $e->getMessage());
}

/* =====================================================
   UPDATE SESSION COUNTERS
===================================================== */

if (!isset($_SESSION["game"])) {
    $_SESSION["game"] = [];
}

if (!isset($_SESSION["game"]["correct"])) {
    $_SESSION["game"]["correct"] = 0;
}

if (!isset($_SESSION["game"]["wrong"])) {
    $_SESSION["game"]["wrong"] = 0;
}

if (!isset($_SESSION["game"]["late"])) {
    $_SESSION["game"]["late"] = 0;
}

if ($is_timeout) {

    $_SESSION["game"]["late"]++;

} elseif ($is_correct === 1) {

    $_SESSION["game"]["correct"]++;

} else {

    $_SESSION["game"]["wrong"]++;
}

$_SESSION["coins"] = $new_coins;

unset($_SESSION["game"]["question_start"]);

/* =====================================================
   ZERO COINS CHECK
   MUST COME BEFORE ROUND CHECK
===================================================== */

if ($new_coins <= 0) {

    $_SESSION["game"]["game_over"] = true;

    $_SESSION["game"]["game_over_reason"] = "no_coins";

    /*
       Show the no-coins error box first.
       The button on no_coins.php opens
       round_result.php.
    */

    header(
        "Location: no_coins.php?round=" . $db_round
    );

    exit();
}

/* =====================================================
   LAST ROUND CHECK
===================================================== */

if ($game_completed) {

    $_SESSION["game"]["game_over"] = false;

    header("Location: final_result.php");

    exit();
}

/* =====================================================
   LAST QUESTION OF CURRENT ROUND
===================================================== */

if ($round_completed) {

    $_SESSION["game"]["game_over"] = false;

    header(
        "Location: round_result.php?round=" . $db_round
    );

    exit();
}

/* =====================================================
   MOVE TO NEXT QUESTION
===================================================== */

$_SESSION["game"]["round"] = $next_round;

$_SESSION["game"]["question"] = $next_question - 1;

$_SESSION["game"]["question_start"] = time();

header(
    "Location: game.php?round=" . $next_round
);

exit();
?>