<?php

session_start();

require_once "../db.php";

/* =========================================================
   NO CACHE
========================================================= */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/* =========================================================
   ADMIN SESSION CHECK
========================================================= */
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/* =========================================================
   GET QUESTION ID
========================================================= */
$question_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($question_id <= 0) {
    header("Location: questions.php");
    exit();
}

/* =========================================================
   VARIABLES
========================================================= */
$message = "";
$message_type = "";

$round_number = "";
$question_number = "";
$question_text = "";
$difficulty = "Easy";
$correct_answer = "Correct";
$ai_prediction_percentage = 50;

/* =========================================================
   GET EXISTING QUESTION
========================================================= */
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        round_number,
        question_number,
        question_text,
        difficulty,
        correct_answer,
        ai_prediction_percentage
     FROM questions
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $question_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($stmt);

    header("Location: questions.php");
    exit();
}

$question = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/* =========================================================
   LOAD EXISTING DATA
========================================================= */
$round_number = (int)$question["round_number"];
$question_number = (int)$question["question_number"];
$question_text = $question["question_text"];
$difficulty = $question["difficulty"];
$correct_answer = $question["correct_answer"];
$ai_prediction_percentage = (float)$question["ai_prediction_percentage"];

/* =========================================================
   FORM SUBMISSION
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $round_number = (int)($_POST["round_number"] ?? 0);

    $question_number = (int)($_POST["question_number"] ?? 0);

    $question_text = trim($_POST["question_text"] ?? "");

    $difficulty = $_POST["difficulty"] ?? "Easy";

    $correct_answer = $_POST["correct_answer"] ?? "Correct";

    $ai_prediction_percentage =
        (float)($_POST["ai_prediction_percentage"] ?? 50);

    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($round_number < 1 || $round_number > 5) {

        $message = "Round number must be between 1 and 5.";
        $message_type = "error";

    } elseif ($question_number < 1 || $question_number > 5) {

        $message = "Question number must be between 1 and 5.";
        $message_type = "error";

    } elseif ($question_text === "") {

        $message = "Question text is required.";
        $message_type = "error";

    } elseif (
        !in_array(
            $difficulty,
            ["Easy", "Medium", "Hard", "Critical"],
            true
        )
    ) {

        $message = "Invalid difficulty.";
        $message_type = "error";

    } elseif (
        !in_array(
            $correct_answer,
            ["Correct", "Incorrect"],
            true
        )
    ) {

        $message = "Invalid correct answer.";
        $message_type = "error";

    } elseif (
        $ai_prediction_percentage < 0 ||
        $ai_prediction_percentage > 100
    ) {

        $message = "AI prediction percentage must be between 0 and 100.";
        $message_type = "error";

    } else {

        /* =================================================
           CHECK DUPLICATE QUESTION
           Ignore current question ID
        ================================================= */

        $check = mysqli_prepare(
            $conn,
            "SELECT id
             FROM questions
             WHERE round_number = ?
             AND question_number = ?
             AND id != ?
             LIMIT 1"
        );

        if (!$check) {

            $message = "Database error.";
            $message_type = "error";

        } else {

            mysqli_stmt_bind_param(
                $check,
                "iii",
                $round_number,
                $question_number,
                $question_id
            );

            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {

                $message =
                    "Round $round_number Question $question_number already exists.";

                $message_type = "error";

            } else {

                /* =========================================
                   UPDATE QUESTION
                ========================================= */

                $update = mysqli_prepare(
                    $conn,
                    "UPDATE questions
                     SET
                        round_number = ?,
                        question_number = ?,
                        question_text = ?,
                        difficulty = ?,
                        correct_answer = ?,
                        ai_prediction_percentage = ?
                     WHERE id = ?
                     LIMIT 1"
                );

                if (!$update) {

                    $message =
                        "Unable to prepare update query.";

                    $message_type = "error";

                } else {

                    mysqli_stmt_bind_param(
                        $update,
                        "iisssdi",
                        $round_number,
                        $question_number,
                        $question_text,
                        $difficulty,
                        $correct_answer,
                        $ai_prediction_percentage,
                        $question_id
                    );

                    if (mysqli_stmt_execute($update)) {

                        mysqli_stmt_close($update);
                        mysqli_stmt_close($check);

                        header(
                            "Location: questions.php?success=question_updated"
                        );

                        exit();

                    } else {

                        $message =
                            "Unable to update question. Please try again.";

                        $message_type = "error";
                    }

                    mysqli_stmt_close($update);
                }
            }

            mysqli_stmt_close($check);
        }
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

    <title>
        Edit Question | AI Prediction Market
    </title>

    <link
        rel="stylesheet"
        href="../assets/edit_question.css"
    >

</head>

<body>

<div class="add-question-page">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="form-header">

        <div>

            <div class="back-link">

                <a href="questions.php">
                    ← Back to Questions
                </a>

            </div>

            <h1>
                Edit Question
            </h1>

            <p>
                Update the question and AI prediction settings.
            </p>

        </div>

    </header>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== "") { ?>

        <div class="form-message <?php echo htmlspecialchars($message_type); ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php } ?>


    <!-- =====================================================
         FORM CARD
    ====================================================== -->

    <section class="form-card">

        <div class="form-card-header">

            <div class="form-icon">
                ✏️
            </div>

            <div>

                <h2>
                    Question Information
                </h2>

                <p>
                    Modify the selected question.
                </p>

            </div>

        </div>


        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <!-- =================================================
                 ROUND & QUESTION
            ================================================== -->

            <div class="form-row">

                <div class="form-group">

                    <label for="round_number">
                        Round Number
                    </label>

                    <select
                        id="round_number"
                        name="round_number"
                        required
                    >

                        <option value="">
                            Select Round
                        </option>

                        <?php for ($i = 1; $i <= 5; $i++) { ?>

                            <option
                                value="<?php echo $i; ?>"
                                <?php
                                echo ($round_number === $i)
                                    ? "selected"
                                    : "";
                                ?>
                            >

                                Round <?php echo $i; ?>

                            </option>

                        <?php } ?>

                    </select>

                    <small>
                        Each round contains 5 questions.
                    </small>

                </div>


                <div class="form-group">

                    <label for="question_number">
                        Question Number
                    </label>

                    <select
                        id="question_number"
                        name="question_number"
                        required
                    >

                        <option value="">
                            Select Question
                        </option>

                        <?php for ($i = 1; $i <= 5; $i++) { ?>

                            <option
                                value="<?php echo $i; ?>"
                                <?php
                                echo ($question_number === $i)
                                    ? "selected"
                                    : "";
                                ?>
                            >

                                Question <?php echo $i; ?>

                            </option>

                        <?php } ?>

                    </select>

                    <small>
                        Choose Q1 to Q5.
                    </small>

                </div>

            </div>


            <!-- =================================================
                 QUESTION
            ================================================== -->

            <div class="form-group">

                <label for="question_text">
                    Question
                </label>

                <textarea
                    id="question_text"
                    name="question_text"
                    rows="5"
                    maxlength="2000"
                    required
                ><?php echo htmlspecialchars($question_text); ?></textarea>

                <small>
                    Enter a clear Correct/Incorrect type statement.
                </small>

            </div>


            <!-- =================================================
                 DIFFICULTY
            ================================================== -->

            <div class="form-group">

                <label for="difficulty">
                    Difficulty
                </label>

                <select
                    id="difficulty"
                    name="difficulty"
                    required
                >

                    <option
                        value="Easy"
                        <?php
                        echo $difficulty === "Easy"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Easy
                    </option>

                    <option
                        value="Medium"
                        <?php
                        echo $difficulty === "Medium"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Medium
                    </option>

                    <option
                        value="Hard"
                        <?php
                        echo $difficulty === "Hard"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Hard
                    </option>

                    <option
                        value="Critical"
                        <?php
                        echo $difficulty === "Critical"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Critical
                    </option>

                </select>

                <small>
                    Easy → Medium → Hard → Critical
                </small>

            </div>


            <!-- =================================================
                 ACTUAL ANSWER
            ================================================== -->

            <div class="form-group">

                <label for="correct_answer">
                    Actual Correct Answer
                </label>

                <select
                    id="correct_answer"
                    name="correct_answer"
                    required
                >

                    <option
                        value="Correct"
                        <?php
                        echo $correct_answer === "Correct"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Correct
                    </option>

                    <option
                        value="Incorrect"
                        <?php
                        echo $correct_answer === "Incorrect"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Incorrect
                    </option>

                </select>

                <small>
                    The actual answer used to calculate the result.
                </small>

            </div>


            <!-- =================================================
                 AI PREDICTION PERCENTAGE
            ================================================== -->

            <div class="form-group">

                <label for="ai_prediction_percentage">
                    AI Prediction Confidence %
                </label>

                <input
                    type="number"
                    id="ai_prediction_percentage"
                    name="ai_prediction_percentage"
                    min="0"
                    max="100"
                    step="0.01"
                    value="<?php echo htmlspecialchars((string)$ai_prediction_percentage); ?>"
                    required
                >

                <small>
                    Example: 85 means the AI has 85% confidence.
                </small>

            </div>


            <!-- =================================================
                 GAME PREVIEW
            ================================================== -->

            <div class="preview-box">

                <h3>
                    Game Preview
                </h3>

                <div class="preview-grid">

                    <div>

                        <span>
                            Betting Time
                        </span>

                        <strong>
                            ⏱ 30 Seconds
                        </strong>

                    </div>


                    <div>

                        <span>
                            Round
                        </span>

                        <strong>
                            <?php echo (int)$round_number; ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Question
                        </span>

                        <strong>
                            Q<?php echo (int)$question_number; ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            AI Confidence
                        </span>

                        <strong>
                            <?php echo htmlspecialchars(
                                rtrim(
                                    rtrim(
                                        number_format(
                                            $ai_prediction_percentage,
                                            2,
                                            ".",
                                            ""
                                        ),
                                        "0"
                                    ),
                                    "."
                                )
                            ); ?>%
                        </strong>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 BUTTONS
            ================================================== -->

            <div class="form-actions">

                <a
                    href="questions.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="create-question-btn"
                >
                    Update Question
                </button>

            </div>

        </form>

    </section>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="dashboard-footer">

        <span>

            © <?php echo date("Y"); ?>

            AI Prediction Market

        </span>

        <span>
            Admin Panel
        </span>

    </footer>

</div>

</body>

</html>