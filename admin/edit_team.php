<?php

session_start();

require_once "../db.php";

/* =========================================================
   ADMIN SESSION CHECK
========================================================= */

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/* =========================================================
   GET TEAM ID
========================================================= */

$team_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($team_id <= 0) {
    header("Location: teams.php");
    exit();
}

/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$team_code = "";
$team_name = "";
$coins = 100;
$current_round = 1;
$current_question = 1;
$status = "Active";

/* =========================================================
   GET EXISTING TEAM
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        team_code,
        team_name,
        coins,
        current_round,
        current_question,
        status
     FROM teams
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($stmt);

    header("Location: teams.php");
    exit();
}

$team = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/* =========================================================
   LOAD DATA
========================================================= */

$team_code = $team["team_code"];
$team_name = $team["team_name"];
$coins = (int)$team["coins"];
$current_round = (int)$team["current_round"];
$current_question = (int)$team["current_question"];
$status = $team["status"];

/* =========================================================
   UPDATE TEAM
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $team_code = trim($_POST["team_code"] ?? "");
    $team_name = trim($_POST["team_name"] ?? "");

    $new_password = $_POST["password"] ?? "";

    $coins = isset($_POST["coins"])
        ? (int)$_POST["coins"]
        : 100;

    $current_round = isset($_POST["current_round"])
        ? (int)$_POST["current_round"]
        : 1;

    $current_question = isset($_POST["current_question"])
        ? (int)$_POST["current_question"]
        : 1;

    $status = $_POST["status"] ?? "Active";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($team_code === "" || $team_name === "") {

        $message = "Team code and team name are required.";
        $message_type = "error";

    } elseif (strlen($team_code) < 3) {

        $message = "Team code must contain at least 3 characters.";
        $message_type = "error";

    } elseif ($coins < 0) {

        $message = "Coins cannot be negative.";
        $message_type = "error";

    } elseif ($current_round < 1) {

        $message = "Round must be at least 1.";
        $message_type = "error";

    } elseif ($current_question < 1) {

        $message = "Question must be at least 1.";
        $message_type = "error";

    } elseif (!in_array($status, ["Active", "Inactive"], true)) {

        $message = "Invalid team status.";
        $message_type = "error";

    } else {


        /* =================================================
           CHECK DUPLICATE TEAM CODE
           EXCLUDE CURRENT TEAM
        ================================================= */

        $check = mysqli_prepare(
            $conn,
            "SELECT id
             FROM teams
             WHERE team_code = ?
             AND id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check,
            "si",
            $team_code,
            $team_id
        );

        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {

            $message = "This team code already exists.";
            $message_type = "error";

        } else {


            /* =================================================
               PASSWORD UPDATE
               
               IMPORTANT:
               If password field is empty:
               KEEP OLD PASSWORD.
               
               If new password is entered:
               HASH IT USING password_hash().
            ================================================= */

            if ($new_password !== "") {

                if (strlen($new_password) < 6) {

                    $message = "New password must contain at least 6 characters.";
                    $message_type = "error";

                } else {

                    /*
                     * Create secure unique password hash
                     */
                    $hashed_password = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    /*
                     * Update EVERYTHING including password
                     */
                    $update = mysqli_prepare(
                        $conn,
                        "UPDATE teams
                         SET
                            team_code = ?,
                            team_name = ?,
                            password = ?,
                            coins = ?,
                            current_round = ?,
                            current_question = ?,
                            status = ?
                         WHERE id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $update,
                        "sssiiisi",
                        $team_code,
                        $team_name,
                        $hashed_password,
                        $coins,
                        $current_round,
                        $current_question,
                        $status,
                        $team_id
                    );

                    if (mysqli_stmt_execute($update)) {

                        mysqli_stmt_close($update);
                        mysqli_stmt_close($check);

                        header(
                            "Location: teams.php?success=team_updated"
                        );
                        exit();

                    } else {

                        $message = "Unable to update team.";
                        $message_type = "error";
                    }

                    mysqli_stmt_close($update);
                }

            } else {

                /*
                 * Password is empty.
                 *
                 * Therefore old password remains unchanged.
                 */
                $update = mysqli_prepare(
                    $conn,
                    "UPDATE teams
                     SET
                        team_code = ?,
                        team_name = ?,
                        coins = ?,
                        current_round = ?,
                        current_question = ?,
                        status = ?
                     WHERE id = ?"
                );

                mysqli_stmt_bind_param(
                    $update,
                    "ssiiisi",
                    $team_code,
                    $team_name,
                    $coins,
                    $current_round,
                    $current_question,
                    $status,
                    $team_id
                );

                if (mysqli_stmt_execute($update)) {

                    mysqli_stmt_close($update);
                    mysqli_stmt_close($check);

                    header(
                        "Location: teams.php?success=team_updated"
                    );
                    exit();

                } else {

                    $message = "Unable to update team.";
                    $message_type = "error";
                }

                mysqli_stmt_close($update);
            }
        }

        mysqli_stmt_close($check);
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
        Edit Team | AI Prediction Market
    </title>

    <link
        rel="stylesheet"
        href="../assets/add_team.css"
    >

</head>


<body>

<div class="add-team-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="form-header">

        <div>

            <div class="back-link">

                <a href="teams.php">
                    ← Back to Teams
                </a>

            </div>

            <h1>
                Edit Team
            </h1>

            <p>
                Update team information, game progress and password.
            </p>

        </div>

    </div>


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

    <div class="form-card">

        <div class="form-card-header">

            <div class="form-icon">
                👥
            </div>

            <div>

                <h2>
                    Team Information
                </h2>

                <p>
                    Update the registered team details.
                </p>

            </div>

        </div>


        <form
            method="POST"
            action="edit_team.php?id=<?php echo $team_id; ?>"
            autocomplete="off"
        >


            <!-- =================================================
                 TEAM CODE
            ================================================== -->

            <div class="form-group">

                <label for="team_code">
                    Team Code
                </label>

                <input
                    type="text"
                    id="team_code"
                    name="team_code"
                    value="<?php echo htmlspecialchars($team_code); ?>"
                    maxlength="50"
                    required
                >

                <small>
                    Unique code used for team login.
                </small>

            </div>


            <!-- =================================================
                 TEAM NAME
            ================================================== -->

            <div class="form-group">

                <label for="team_name">
                    Team Name
                </label>

                <input
                    type="text"
                    id="team_name"
                    name="team_name"
                    value="<?php echo htmlspecialchars($team_name); ?>"
                    maxlength="100"
                    required
                >

                <small>
                    Name displayed during the game.
                </small>

            </div>


            <!-- =================================================
                 PASSWORD
            ================================================== -->

            <div class="form-group">

                <label for="password">
                    New Team Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter new password"
                    minlength="6"
                >

                <small>
                    Leave empty to keep the current password.
                    Enter a new password to replace the old password.
                </small>

            </div>


            <!-- =================================================
                 COINS
            ================================================== -->

            <div class="form-group">

                <label for="coins">
                    Coins
                </label>

                <input
                    type="number"
                    id="coins"
                    name="coins"
                    value="<?php echo $coins; ?>"
                    min="0"
                    required
                >

            </div>


            <!-- =================================================
                 CURRENT ROUND
            ================================================== -->

            <div class="form-group">

                <label for="current_round">
                    Current Round
                </label>

                <input
                    type="number"
                    id="current_round"
                    name="current_round"
                    value="<?php echo $current_round; ?>"
                    min="1"
                    required
                >

            </div>


            <!-- =================================================
                 CURRENT QUESTION
            ================================================== -->

            <div class="form-group">

                <label for="current_question">
                    Current Question
                </label>

                <input
                    type="number"
                    id="current_question"
                    name="current_question"
                    value="<?php echo $current_question; ?>"
                    min="1"
                    required
                >

            </div>


            <!-- =================================================
                 STATUS
            ================================================== -->

            <div class="form-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option
                        value="Active"
                        <?php echo ($status === "Active") ? "selected" : ""; ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?php echo ($status === "Inactive") ? "selected" : ""; ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <!-- =================================================
                 PASSWORD INFORMATION
            ================================================== -->

            <div class="password-info">

                <strong>
                    🔐 Password Security
                </strong>

                <p>
                    New passwords are securely hashed using PHP
                    <code>password_hash()</code> before being stored
                    in the database.
                </p>

            </div>


            <!-- =================================================
                 BUTTONS
            ================================================== -->

            <div class="form-actions">

                <a
                    href="teams.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="create-team-btn"
                >
                    Update Team
                </button>

            </div>

        </form>

    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="dashboard-footer">

        <span>
            © <?php echo date("Y"); ?> AI Prediction Market
        </span>

        <span>
            Admin Panel
        </span>

    </footer>

</div>

</body>

</html>