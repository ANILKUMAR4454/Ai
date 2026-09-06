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

$admin_username = $_SESSION["admin_username"] ?? "Admin";

/* =====================================================
   GAME SETTINGS
===================================================== */

$STARTING_COINS = 50;
$TOTAL_ROUNDS = 5;
$QUESTIONS_PER_ROUND = 5;

$message = "";
$error = "";

/* =====================================================
   HELPER FUNCTION
===================================================== */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =====================================================
   CSRF TOKEN
===================================================== */

if (empty($_SESSION["game_control_token"])) {
    $_SESSION["game_control_token"] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION["game_control_token"];

/* =====================================================
   HANDLE TEAM ACTION
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $csrf_token,
            $posted_token
        )
    ) {
        $error = "Invalid security token. Please try again.";
    } else {

        $action = $_POST["action"] ?? "";

        $team_id = filter_input(
            INPUT_POST,
            "team_id",
            FILTER_VALIDATE_INT
        );

        if (!$team_id || $team_id < 1) {

            $error = "Please select a valid team.";

        } else {

            mysqli_begin_transaction($conn);

            try {

                /* =========================================
                   GET SELECTED TEAM
                ========================================= */

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT
                        id,
                        team_name,
                        team_code,
                        coins,
                        current_round,
                        current_question,
                        current_at,
                        status
                     FROM teams
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Could not prepare team query."
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $team_id
                );

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);

                    throw new Exception(
                        "Could not get selected team."
                    );
                }

                $result = mysqli_stmt_get_result($stmt);
                $team = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if (!$team) {
                    throw new Exception(
                        "Selected team not found."
                    );
                }

                $team_name = $team["team_name"] ?? "Selected Team";

                /* =========================================
                   COMPLETE RESET
                ========================================= */

                if ($action === "reset") {

                    /*
                       Delete only the selected team's
                       answer history from team_answers.
                    */

                    $stmt = mysqli_prepare(
                        $conn,
                        "DELETE FROM team_answers
                         WHERE team_id = ?"
                    );

                    if (!$stmt) {
                        throw new Exception(
                            "Could not prepare answer history reset."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "i",
                        $team_id
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        throw new Exception(
                            "Could not delete previous team answers."
                        );
                    }

                    mysqli_stmt_close($stmt);

                    /*
                       Reset the selected team.
                    */

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE teams
                         SET
                            coins = ?,
                            current_round = 1,
                            current_question = 1,
                            current_at = NULL,
                            status = 'active'
                         WHERE id = ?"
                    );

                    if (!$stmt) {
                        throw new Exception(
                            "Could not prepare team reset."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ii",
                        $STARTING_COINS,
                        $team_id
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        throw new Exception(
                            "Could not reset selected team."
                        );
                    }

                    mysqli_stmt_close($stmt);

                    /*
                       Commit both operations:
                       1. Delete team_answers
                       2. Reset teams table
                    */

                    mysqli_commit($conn);

                    /*
                       Clear session only if the admin is
                       also logged in as this team.
                    */

                    if (
                        isset($_SESSION["team_id"]) &&
                        (int) $_SESSION["team_id"] === $team_id
                    ) {
                        $_SESSION["coins"] = $STARTING_COINS;
                        $_SESSION["current_round"] = 1;
                        $_SESSION["current_question"] = 1;

                        unset($_SESSION["round_correct"]);
                        unset($_SESSION["round_wrong"]);
                        unset($_SESSION["round_late"]);
                        unset($_SESSION["round_coins_earned"]);
                        unset($_SESSION["round_coins_lost"]);
                        unset($_SESSION["round_started_at"]);
                        unset($_SESSION["selected_round"]);
                    }

                    header(
                        "Location: game_control.php?success=reset&team=" .
                        urlencode($team_name)
                    );

                    exit();
                }

                /* =========================================
                   PLAY AGAIN
                ========================================= */

                elseif ($action === "play_again") {

                    /*
                       Play Again does not delete history.
                       It only starts the team again from
                       Round 1 with 50 coins.
                    */

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE teams
                         SET
                            coins = ?,
                            current_round = 1,
                            current_question = 1,
                            current_at = NULL,
                            status = 'active'
                         WHERE id = ?"
                    );

                    if (!$stmt) {
                        throw new Exception(
                            "Could not prepare play-again update."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ii",
                        $STARTING_COINS,
                        $team_id
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        throw new Exception(
                            "Could not allow team to play again."
                        );
                    }

                    mysqli_stmt_close($stmt);

                    mysqli_commit($conn);

                    header(
                        "Location: game_control.php?success=play_again&team=" .
                        urlencode($team_name)
                    );

                    exit();
                }

                else {

                    throw new Exception(
                        "Invalid game control action."
                    );
                }

            } catch (Exception $e) {

                mysqli_rollback($conn);

                $error = $e->getMessage();
            }
        }
    }
}

/* =====================================================
   SUCCESS MESSAGE
===================================================== */

if (isset($_GET["success"])) {

    $success_type = $_GET["success"];

    $success_team = e(
        $_GET["team"] ?? "team"
    );

    if ($success_type === "reset") {
        $message = "Game reset successfully for {$success_team}.";
    }

    if ($success_type === "play_again") {
        $message = "{$success_team} can now play again from Round 1.";
    }
}

/* =====================================================
   FETCH ALL TEAMS
===================================================== */

$teams_result = mysqli_query(
    $conn,
    "SELECT
        id,
        team_name,
        team_code,
        coins,
        current_round,
        current_question,
        status
     FROM teams
     ORDER BY id DESC"
);

if (!$teams_result) {
    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}

$total_teams = mysqli_num_rows($teams_result);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Game Control | AI Prediction Market</title>

    <link
        rel="stylesheet"
        href="../assets/admin.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
        }

        .page {
            width: 100%;
            min-height: 100vh;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .heading h1 {
            margin: 0 0 8px;
            font-size: 30px;
            color: #111827;
        }

        .heading p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-button {
            display: inline-block;
            padding: 12px 18px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .back-button:hover {
            background: #1d4ed8;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            font-weight: bold;
        }

        .admin-info strong {
            display: block;
            font-size: 13px;
        }

        .admin-info small {
            color: #64748b;
            font-size: 11px;
        }

        .notice {
            margin-bottom: 22px;
            padding: 15px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .info-card {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.04);
        }

        .info-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eff6ff;
            font-size: 24px;
            flex-shrink: 0;
        }

        .info-card h3 {
            margin: 0 0 5px;
            font-size: 16px;
            color: #111827;
        }

        .info-card p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 24px 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h2 {
            margin: 0 0 6px;
            font-size: 20px;
            color: #111827;
        }

        .card-header p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .team-count {
            padding: 9px 15px;
            border-radius: 20px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 16px 22px;
            text-align: left;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 18px 22px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .team-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .team-avatar {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: #e8edff;
            color: #4f46e5;
            font-weight: 700;
            flex-shrink: 0;
        }

        .team-name {
            display: block;
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .team-id {
            color: #94a3b8;
            font-size: 12px;
        }

        .code {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .coins {
            color: #2563eb;
            font-weight: 700;
            font-size: 15px;
            white-space: nowrap;
        }

        .round {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 6px;
            background: #f3e8ff;
            color: #7e22ce;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status.active {
            background: #dcfce7;
            color: #15803d;
        }

        .status.completed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status.waiting {
            background: #fef3c7;
            color: #b45309;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .actions form {
            margin: 0;
        }

        .action-button {
            border: none;
            border-radius: 8px;
            padding: 10px 13px;
            color: white;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .play-button {
            background: #16a34a;
        }

        .play-button:hover {
            background: #15803d;
        }

        .reset-button {
            background: #dc2626;
        }

        .reset-button:hover {
            background: #b91c1c;
        }

        .empty {
            padding: 55px !important;
            text-align: center;
            color: #64748b;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 22px;
            color: #94a3b8;
            font-size: 11px;
        }

        @media (max-width: 800px) {

            .page {
                padding: 20px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .top-actions {
                width: 100%;
                justify-content: space-between;
            }

            .card-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 20px;
            }

            .info-card {
                align-items: flex-start;
            }

        }

        @media (max-width: 500px) {

            .page {
                padding: 15px;
            }

            .heading h1 {
                font-size: 24px;
            }

            .admin-info {
                display: none;
            }

            .top-actions {
                justify-content: flex-start;
            }

            .footer {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- TOP BAR -->

    <header class="topbar">

        <div class="heading">

            <h1>Game Control</h1>

            <p>
                Manage and restart individual team games
            </p>

        </div>

        <div class="top-actions">

            <a
                href="dashboard.php"
                class="back-button"
            >
                ← Back to Dashboard
            </a>

            <div class="admin-info">

                <div class="admin-avatar">
                    <?= e(strtoupper(substr($admin_username, 0, 1))) ?>
                </div>

                <div>

                    <strong>
                        <?= e($admin_username) ?>
                    </strong>

                    <small>Administrator</small>

                </div>

            </div>

        </div>

    </header>

    <!-- MESSAGES -->

    <?php if ($message !== ""): ?>

        <div class="notice success">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="notice danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- INFORMATION -->

    <div class="info-card">

        <div class="info-icon">
            🎮
        </div>

        <div>

            <h3>Individual Team Control</h3>

            <p>
                Select one team to restart its game.
                Reset deletes only that team's previous
                answers from the team_answers table,
                restores <?= $STARTING_COINS ?> coins,
                and starts the team from Round 1.
            </p>

        </div>

    </div>

    <!-- TEAMS CARD -->

    <section class="card">

        <div class="card-header">

            <div>

                <h2>Registered Teams</h2>

                <p>
                    Choose a team to allow it to play again
                    or completely reset its game.
                </p>

            </div>

            <span class="team-count">
                <?= $total_teams ?> Teams
            </span>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Team</th>
                        <th>Team Code</th>
                        <th>Coins</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($total_teams > 0): ?>

                    <?php $number = 1; ?>

                    <?php while ($team = mysqli_fetch_assoc($teams_result)): ?>

                        <?php

                        $team_status = strtolower(
                            trim($team["status"] ?? "waiting")
                        );

                        if (
                            $team_status !== "active" &&
                            $team_status !== "completed"
                        ) {
                            $team_status = "waiting";
                        }

                        $team_name = $team["team_name"] ?? "Unnamed Team";

                        $first_letter = strtoupper(
                            substr($team_name, 0, 1)
                        );

                        ?>

                        <tr>

                            <td>
                                <?= $number++ ?>
                            </td>

                            <td>

                                <div class="team-profile">

                                    <div class="team-avatar">
                                        <?= e($first_letter) ?>
                                    </div>

                                    <div>

                                        <span class="team-name">
                                            <?= e($team_name) ?>
                                        </span>

                                        <span class="team-id">
                                            Team ID:
                                            <?= (int) $team["id"] ?>
                                        </span>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <span class="code">
                                    <?= e($team["team_code"] ?? "-") ?>
                                </span>

                            </td>

                            <td>

                                <span class="coins">
                                    🪙
                                    <?= (int) ($team["coins"] ?? 0) ?>
                                </span>

                            </td>

                            <td>

                                <span class="round">
                                    Round
                                    <?= (int) ($team["current_round"] ?? 1) ?>

                                    /

                                    Question
                                    <?= (int) ($team["current_question"] ?? 1) ?>
                                </span>

                            </td>

                            <td>

                                <span class="status <?= e($team_status) ?>">
                                    <?= e(ucfirst($team_status)) ?>
                                </span>

                            </td>

                            <td>

                                <div class="actions">

                                    <!-- PLAY AGAIN -->

                                    <form
                                        method="POST"
                                        onsubmit="return confirm(
                                            'Allow this team to play again from Round 1?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrf_token) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="team_id"
                                            value="<?= (int) $team["id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="play_again"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button play-button"
                                        >
                                            ▶ Play Again
                                        </button>

                                    </form>

                                    <!-- COMPLETE RESET -->

                                    <form
                                        method="POST"
                                        onsubmit="return confirm(
                                            'WARNING: This will delete all previous answers for this team and restore 50 coins. Continue?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrf_token) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="team_id"
                                            value="<?= (int) $team["id"] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="reset"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button reset-button"
                                        >
                                            ↻ Reset Game
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No teams found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- FOOTER -->

    <footer class="footer">

        <span>
            AI Prediction Market
        </span>

        <span>
            Offline Web-Based Prediction Game
        </span>

    </footer>

</div>

</body>

</html>
