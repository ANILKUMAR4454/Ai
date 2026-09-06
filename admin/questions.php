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

if (!isset($_SESSION["admin_id"]) || empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   VARIABLES
========================================================= */

$success = "";
$error = "";


/* =========================================================
   SUCCESS MESSAGES
========================================================= */

if (isset($_GET["success"])) {

    switch ($_GET["success"]) {

        case "question_added":
            $success = "Question added successfully.";
            break;

        case "question_updated":
            $success = "Question updated successfully.";
            break;

        case "question_deleted":
            $success = "Question deleted successfully.";
            break;
    }
}


/* =========================================================
   ERROR MESSAGES
========================================================= */

if (isset($_GET["error"])) {

    switch ($_GET["error"]) {

        case "invalid_id":
            $error = "Invalid question ID.";
            break;

        case "delete_failed":
            $error = "Failed to delete question.";
            break;

        case "question_not_found":
            $error = "Question not found.";
            break;

        default:
            $error = "Something went wrong.";
            break;
    }
}


/* =========================================================
   GET ALL QUESTIONS
========================================================= */

$questions = [];

$sql = "
    SELECT
        id,
        round_number,
        question_number,
        question_text,
        difficulty,
        correct_answer,
        ai_prediction_percentage
    FROM questions
    ORDER BY
        round_number ASC,
        question_number ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }

    mysqli_free_result($result);
}


/* =========================================================
   TOTAL QUESTIONS
========================================================= */

$total_questions = count($questions);


/* =========================================================
   ROUND COUNTS
========================================================= */

$round_counts = [];

for ($i = 1; $i <= 5; $i++) {
    $round_counts[$i] = 0;
}

foreach ($questions as $question) {

    $round = (int)$question["round_number"];

    if ($round >= 1 && $round <= 5) {
        $round_counts[$round]++;
    }
}


/* =========================================================
   HELPER - DIFFICULTY CLASS
========================================================= */

function difficultyClass($difficulty)
{
    $difficulty = strtolower(trim($difficulty));

    if (
        $difficulty === "easy" ||
        $difficulty === "medium" ||
        $difficulty === "hard"
    ) {
        return $difficulty;
    }

    return "default";
}


/* =========================================================
   HELPER - ANSWER CLASS
========================================================= */

function answerClass($answer)
{
    if (strtolower(trim($answer)) === "correct") {
        return "answer-correct";
    }

    return "answer-incorrect";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Questions | AI Prediction Market</title>

    <style>
        /* ================================
           RESET
        ================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            font-family: inherit;
        }

        /* ================================
           MAIN PAGE
        ================================= */
        .questions-page {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* ================================
           HEADER
        ================================= */
        .questions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;

            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;

            padding: 25px 28px;
            margin-bottom: 25px;

            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
        }

        .header-left {
            min-width: 0;
        }

        .header-right {
            flex-shrink: 0;
        }

        /* ================================
           BACK LINK
        ================================= */
        .back-link {
            margin-bottom: 8px;
        }

        .back-link a {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            color: #4f46e5;
            font-size: 14px;
            font-weight: 700;

            transition: 0.2s ease;
        }

        .back-link a:hover {
            color: #3730a3;
            transform: translateX(-3px);
        }

        /* ================================
           TITLE
        ================================= */
        .questions-header h1 {
            color: #111827;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
        }

        .questions-header p {
            color: #64748b;
            font-size: 14px;
        }

        /* ================================
           ADD QUESTION BUTTON
        ================================= */
        .add-question-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            min-height: 45px;
            padding: 0 20px;

            background: #4f46e5;
            color: #ffffff;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 700;

            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.20);

            transition: 0.2s ease;
        }

        .add-question-btn:hover {
            background: #4338ca;
            transform: translateY(-2px);

            box-shadow:
                0 9px 20px rgba(79, 70, 229, 0.25);
        }

        /* ================================
           SUCCESS / ERROR
        ================================= */
        .success-message,
        .error-message {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 14px 17px;
            margin-bottom: 20px;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 600;
        }

        .success-message {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .message-icon {
            font-size: 17px;
            font-weight: 800;
        }

        /* ================================
           SUMMARY
        ================================= */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 28px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 12px;

            min-width: 0;

            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;

            padding: 17px;

            box-shadow:
                0 6px 20px rgba(15, 23, 42, 0.05);

            transition: 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-3px);

            box-shadow:
                0 10px 25px rgba(15, 23, 42, 0.09);
        }

        .summary-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 45px;
            height: 45px;

            flex-shrink: 0;

            background: #eef2ff;
            color: #4f46e5;

            border-radius: 11px;
            font-size: 20px;
        }

        .summary-content {
            min-width: 0;
        }

        .summary-content span {
            display: block;

            color: #64748b;
            font-size: 11px;
            font-weight: 600;

            margin-bottom: 3px;
        }

        .summary-content strong {
            display: block;

            color: #111827;
            font-size: 20px;
            font-weight: 800;
        }

        /* ================================
           ROUND SECTION
        ================================= */
        .round-section {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;

            margin-bottom: 25px;
            overflow: hidden;

            box-shadow:
                0 8px 25px rgba(15, 23, 42, 0.055);
        }

        .round-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            padding: 22px 25px;

            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .round-label {
            display: block;

            color: #4f46e5;
            font-size: 11px;
            font-weight: 800;

            text-transform: uppercase;
            letter-spacing: 1px;

            margin-bottom: 3px;
        }

        .round-header h2 {
            color: #111827;
            font-size: 22px;
            font-weight: 800;
        }

        .round-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 35px;
            padding: 0 13px;

            background: #ffffff;
            border: 1px solid #dbe1ea;
            border-radius: 9px;

            color: #475569;
            font-size: 12px;
            font-weight: 700;

            white-space: nowrap;
        }

        /* ================================
           QUESTIONS CONTAINER
        ================================= */
        .questions-container {
            padding: 20px;
        }

        /* ================================
           QUESTION CARD
        ================================= */
        .question-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;

            margin-bottom: 16px;
            overflow: hidden;

            transition: 0.2s ease;
        }

        .question-card:last-child {
            margin-bottom: 0;
        }

        .question-card:hover {
            border-color: #cbd5e1;

            box-shadow:
                0 8px 22px rgba(15, 23, 42, 0.07);

            transform: translateY(-1px);
        }

        /* ================================
           QUESTION TOP
        ================================= */
        .question-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            padding: 15px 18px;

            background: #fafbfc;
            border-bottom: 1px solid #edf0f4;
        }

        .question-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-width: 48px;
            height: 33px;
            padding: 0 11px;

            background: #4f46e5;
            color: #ffffff;

            border-radius: 8px;

            font-size: 12px;
            font-weight: 800;
        }

        .question-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;

            gap: 7px;
            flex-wrap: wrap;
        }

        /* ================================
           DIFFICULTY
        ================================= */
        .difficulty {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 11px;
            font-weight: 800;

            text-transform: capitalize;
        }

        .difficulty-easy {
            background: #dcfce7;
            color: #166534;
        }

        .difficulty-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .difficulty-hard {
            background: #fee2e2;
            color: #991b1b;
        }

        .difficulty-default {
            background: #f1f5f9;
            color: #475569;
        }

        /* ================================
           QUESTION ID
        ================================= */
        .question-id {
            display: inline-flex;
            align-items: center;

            padding: 5px 9px;

            background: #f1f5f9;
            border-radius: 7px;

            color: #64748b;

            font-size: 10px;
            font-weight: 700;
        }

        /* ================================
           QUESTION CONTENT
        ================================= */
        .question-content {
            padding: 19px 18px 17px;
        }

        .question-content h3 {
            color: #64748b;

            font-size: 10px;
            font-weight: 800;

            text-transform: uppercase;
            letter-spacing: 0.8px;

            margin-bottom: 7px;
        }

        .question-content p {
            color: #1e293b;

            font-size: 15px;
            font-weight: 600;

            line-height: 1.65;

            word-break: break-word;
        }

        /* ================================
           ANSWER GRID
        ================================= */
        .answer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);

            gap: 11px;

            padding: 0 18px 18px;
        }

        .answer-box {
            min-width: 0;

            padding: 13px;

            background: #f8fafc;
            border: 1px solid #e8edf3;

            border-radius: 10px;
        }

        .answer-box span {
            display: block;

            color: #64748b;

            font-size: 10px;
            font-weight: 700;

            margin-bottom: 5px;
        }

        .answer-box strong {
            display: block;

            color: #1e293b;

            font-size: 13px;
            font-weight: 800;

            word-break: break-word;
        }

        /* ================================
           ANSWER COLORS
        ================================= */
        .answer-correct {
            color: #15803d !important;
        }

        .answer-incorrect {
            color: #dc2626 !important;
        }

        .ai-answer {
            color: #7c3aed !important;
        }

        /* ================================
           TIMING
        ================================= */
        .timing-box {
            display: grid;
            grid-template-columns: repeat(2, 1fr);

            gap: 10px;

            margin: 0 18px 18px;
            padding: 13px 15px;

            background: #f8fafc;
            border: 1px dashed #d8dee8;

            border-radius: 10px;
        }

        .timing-box > div {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;
        }

        .timing-box span {
            color: #64748b;

            font-size: 11px;
            font-weight: 600;
        }

        .timing-box strong {
            color: #334155;

            font-size: 12px;
            font-weight: 800;

            white-space: nowrap;
        }

        /* ================================
           ACTIONS
        ================================= */
        .question-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;

            gap: 8px;

            padding: 12px 18px;

            background: #fafbfc;
            border-top: 1px solid #edf0f4;
        }

        .edit-btn,
        .delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 36px;
            padding: 0 13px;

            border-radius: 8px;

            font-size: 11px;
            font-weight: 800;

            transition: 0.2s ease;
        }

        .edit-btn {
            background: #eef2ff;
            color: #4338ca;
        }

        .edit-btn:hover {
            background: #e0e7ff;
            transform: translateY(-1px);
        }

        .delete-btn {
            background: #fef2f2;
            color: #dc2626;
        }

        .delete-btn:hover {
            background: #fee2e2;
            transform: translateY(-1px);
        }

        /* ================================
           EMPTY ROUND
        ================================= */
        .empty-round {
            text-align: center;

            padding: 50px 20px;

            background: #fafbfc;
            border: 1px dashed #d5dce7;

            border-radius: 13px;
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 60px;
            height: 60px;

            margin: 0 auto 13px;

            background: #f1f5f9;

            border-radius: 50%;

            font-size: 26px;
        }

        .empty-round h3 {
            color: #334155;

            font-size: 16px;
            font-weight: 800;

            margin-bottom: 5px;
        }

        .empty-round p {
            color: #64748b;

            font-size: 13px;

            margin-bottom: 16px;
        }

        .empty-add-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 39px;
            padding: 0 15px;

            background: #4f46e5;
            color: #ffffff;

            border-radius: 8px;

            font-size: 12px;
            font-weight: 800;

            transition: 0.2s ease;
        }

        .empty-add-btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        /* ================================
           FOOTER
        ================================= */
        .dashboard-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            padding: 20px 3px 5px;

            color: #94a3b8;

            font-size: 12px;
        }

        /* ================================
           TABLET
        ================================= */
        @media (max-width: 1200px) {

            .summary-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .answer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* ================================
           SMALL TABLET
        ================================= */
        @media (max-width: 850px) {

            .questions-page {
                padding: 20px;
            }

            .questions-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
            }

            .add-question-btn {
                width: 100%;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .round-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .round-count {
                width: 100%;
            }

            .timing-box {
                grid-template-columns: 1fr;
            }
        }

        /* ================================
           MOBILE
        ================================= */
        @media (max-width: 600px) {

            .questions-page {
                padding: 12px;
            }

            .questions-header {
                padding: 19px;
                border-radius: 15px;
            }

            .questions-header h1 {
                font-size: 23px;
            }

            .questions-header p {
                font-size: 13px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
            }

            .round-section {
                border-radius: 15px;
            }

            .round-header {
                padding: 17px;
            }

            .round-header h2 {
                font-size: 20px;
            }

            .questions-container {
                padding: 12px;
            }

            .question-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .question-meta {
                justify-content: flex-start;
                width: 100%;
            }

            .question-content {
                padding: 16px 15px;
            }

            .question-content p {
                font-size: 14px;
            }

            .answer-grid {
                grid-template-columns: 1fr;
                padding: 0 15px 15px;
            }

            .timing-box {
                margin: 0 15px 15px;
            }

            .timing-box > div {
                flex-direction: column;
                align-items: flex-start;
                gap: 3px;
            }

            .question-actions {
                padding: 12px 15px;
            }

            .dashboard-footer {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* ================================
           VERY SMALL MOBILE
        ================================= */
        @media (max-width: 380px) {

            .questions-page {
                padding: 8px;
            }

            .questions-header {
                padding: 16px;
            }

            .questions-header h1 {
                font-size: 21px;
            }

            .summary-card {
                padding: 13px;
            }

            .summary-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .round-header {
                padding: 15px;
            }

            .questions-container {
                padding: 9px;
            }

            .question-actions {
                flex-direction: column;
            }

            .edit-btn,
            .delete-btn {
                width: 100%;
            }
        }

        /* ================================
           ACCESSIBILITY
        ================================= */
        a:focus-visible {
            outline: 3px solid rgba(79, 70, 229, 0.25);
            outline-offset: 3px;
        }

        /* ================================
           REDUCED MOTION
        ================================= */
        @media (prefers-reduced-motion: reduce) {

            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>

</head>

<body>

<div class="questions-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="questions-header">

        <div class="header-left">

            <div class="back-link">

                <a href="dashboard.php">
                    ← Admin Dashboard
                </a>

            </div>


            <h1>
                Question Management
            </h1>


            <p>
                Manage questions for all prediction game rounds.
            </p>

        </div>


        <div class="header-right">

            <a
                href="add_question.php"
                class="add-question-btn"
            >
                + Add Question
            </a>

        </div>

    </header>


    <!-- =====================================================
         SUCCESS MESSAGE
    ====================================================== -->

    <?php if ($success !== "") { ?>

        <div class="success-message">

            <span class="message-icon">
                ✓
            </span>

            <span>
                <?php echo htmlspecialchars($success); ?>
            </span>

        </div>

    <?php } ?>


    <!-- =====================================================
         ERROR MESSAGE
    ====================================================== -->

    <?php if ($error !== "") { ?>

        <div class="error-message">

            <span class="message-icon">
                ✕
            </span>

            <span>
                <?php echo htmlspecialchars($error); ?>
            </span>

        </div>

    <?php } ?>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <section class="summary-grid">


        <!-- TOTAL QUESTIONS -->

        <div class="summary-card">

            <div class="summary-icon">
                ❓
            </div>

            <div class="summary-content">

                <span>
                    Total Questions
                </span>

                <strong>
                    <?php echo $total_questions; ?>/25
                </strong>

            </div>

        </div>


        <!-- ROUND 1 -->

        <div class="summary-card">

            <div class="summary-icon">
                🎯
            </div>

            <div class="summary-content">

                <span>
                    Round 1
                </span>

                <strong>
                    <?php echo $round_counts[1]; ?>/5
                </strong>

            </div>

        </div>


        <!-- ROUND 2 -->

        <div class="summary-card">

            <div class="summary-icon">
                🎯
            </div>

            <div class="summary-content">

                <span>
                    Round 2
                </span>

                <strong>
                    <?php echo $round_counts[2]; ?>/5
                </strong>

            </div>

        </div>


        <!-- ROUND 3 -->

        <div class="summary-card">

            <div class="summary-icon">
                🎯
            </div>

            <div class="summary-content">

                <span>
                    Round 3
                </span>

                <strong>
                    <?php echo $round_counts[3]; ?>/5
                </strong>

            </div>

        </div>


        <!-- ROUND 4 -->

        <div class="summary-card">

            <div class="summary-icon">
                🎯
            </div>

            <div class="summary-content">

                <span>
                    Round 4
                </span>

                <strong>
                    <?php echo $round_counts[4]; ?>/5
                </strong>

            </div>

        </div>


        <!-- ROUND 5 -->

        <div class="summary-card">

            <div class="summary-icon">
                🏆
            </div>

            <div class="summary-content">

                <span>
                    Round 5
                </span>

                <strong>
                    <?php echo $round_counts[5]; ?>/5
                </strong>

            </div>

        </div>

    </section>


    <!-- =====================================================
         ROUND SECTIONS
    ====================================================== -->

    <?php for ($round = 1; $round <= 5; $round++) { ?>

        <section class="round-section">


            <!-- ROUND HEADER -->

            <div class="round-header">

                <div>

                    <span class="round-label">
                        PREDICTION ROUND
                    </span>

                    <h2>
                        Round <?php echo $round; ?>
                    </h2>

                </div>


                <div class="round-count">

                    <?php echo $round_counts[$round]; ?>
                    / 5 Questions

                </div>

            </div>


            <!-- QUESTIONS -->

            <div class="questions-container">

                <?php

                $round_has_questions = false;

                foreach ($questions as $question) {

                    if (
                        (int)$question["round_number"] !== $round
                    ) {
                        continue;
                    }

                    $round_has_questions = true;

                ?>


                    <!-- QUESTION CARD -->

                    <article class="question-card">


                        <!-- QUESTION TOP -->

                        <div class="question-top">

                            <div class="question-number">

                                Q<?php
                                echo (int)$question["question_number"];
                                ?>

                            </div>


                            <div class="question-meta">


                                <!-- DIFFICULTY -->

                                <span
                                    class="difficulty difficulty-<?php
                                    echo htmlspecialchars(
                                        difficultyClass(
                                            $question["difficulty"]
                                        )
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $question["difficulty"]
                                    );
                                    ?>

                                </span>


                                <!-- QUESTION ID -->

                                <span class="question-id">

                                    ID:
                                    <?php
                                    echo (int)$question["id"];
                                    ?>

                                </span>

                            </div>

                        </div>


                        <!-- QUESTION TEXT -->

                        <div class="question-content">

                            <h3>
                                Question
                            </h3>

                            <p>

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $question["question_text"]
                                    )
                                );

                                ?>

                            </p>

                        </div>


                        <!-- ANSWER INFORMATION -->

                        <div class="answer-grid">


                            <!-- ACTUAL ANSWER -->

                            <div class="answer-box">

                                <span>
                                    Actual Answer
                                </span>

                                <strong
                                    class="<?php
                                    echo answerClass(
                                        $question["correct_answer"]
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $question["correct_answer"]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <!-- AI PREDICTION -->

                            <div class="answer-box">

                                <span>
                                    AI Prediction
                                </span>

                                <strong class="ai-answer">

                                    <?php

                                    echo number_format(
                                        (float)$question[
                                            "ai_prediction_percentage"
                                        ],
                                        0
                                    );

                                    ?>%

                                </strong>

                            </div>


                            <!-- BETTING TIME -->

                            <div class="answer-box">

                                <span>
                                    Betting Time
                                </span>

                                <strong>
                                    60 Seconds
                                </strong>

                            </div>


                            <!-- QUESTION NUMBER -->

                            <div class="answer-box">

                                <span>
                                    Question
                                </span>

                                <strong>

                                    Q<?php
                                    echo (int)$question[
                                        "question_number"
                                    ];
                                    ?>

                                </strong>

                            </div>

                        </div>


                        <!-- GAME TIMING -->

                        <div class="timing-box">


                            <div>

                                <span>
                                    ⏱ Betting Time
                                </span>

                                <strong>
                                    60 Seconds
                                </strong>

                            </div>


                            <div>

                                <span>
                                    ⏭ Next Question
                                </span>

                                <strong>
                                    Automatic
                                </strong>

                            </div>

                        </div>


                        <!-- ACTIONS -->

                        <div class="question-actions">


                            <!-- EDIT -->

                            <a
                                href="edit_question.php?id=<?php
                                echo (int)$question["id"];
                                ?>"
                                class="edit-btn"
                            >
                                ✏ Edit
                            </a>


                            <!-- DELETE -->

                            <a
                                href="delete_question.php?id=<?php
                                echo (int)$question["id"];
                                ?>"
                                class="delete-btn"
                            >
                                🗑 Delete
                            </a>

                        </div>

                    </article>


                <?php } ?>


                <!-- NO QUESTIONS -->

                <?php if (!$round_has_questions) { ?>

                    <div class="empty-round">

                        <div class="empty-icon">
                            📭
                        </div>

                        <h3>
                            No questions added
                        </h3>

                        <p>

                            Add questions to Round
                            <?php echo $round; ?>.

                        </p>

                        <a
                            href="add_question.php"
                            class="empty-add-btn"
                        >
                            + Add Question
                        </a>

                    </div>

                <?php } ?>

            </div>

        </section>

    <?php } ?>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="dashboard-footer">

        <span>

            ©
            <?php echo date("Y"); ?>
            AI Prediction Market

        </span>

        <span>
            Admin Panel
        </span>

    </footer>


</div>


<!-- =========================================================
     DELETE CONFIRMATION
========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const deleteButtons =
        document.querySelectorAll(".delete-btn");

    deleteButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            const confirmed = confirm(
                "Are you sure you want to delete this question?\n\n" +
                "This action cannot be undone."
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    });

});

</script>


</body>

</html>
