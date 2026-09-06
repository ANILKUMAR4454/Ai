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
   DEFAULT TEAM PASSWORD
   Same password for every team
========================================================= */

$default_password = "Ai@123";


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$team_code = "";
$team_name = "";


/* =========================================================
   FORM SUBMISSION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $team_code = trim($_POST["team_code"] ?? "");
    $team_name = trim($_POST["team_name"] ?? "");

    /*
     * Password is NOT generated.
     * Every team uses Ai@123.
     */
    $password = $default_password;


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($team_code === "" || $team_name === "") {

        $message = "Team code and team name are required.";
        $message_type = "error";

    } elseif (strlen($team_code) < 3) {

        $message = "Team code must contain at least 3 characters.";
        $message_type = "error";

    } elseif (strlen($team_name) < 2) {

        $message = "Team name must contain at least 2 characters.";
        $message_type = "error";

    } else {


        /* =================================================
           CHECK DUPLICATE TEAM CODE
        ================================================= */

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM teams WHERE team_code = ? LIMIT 1"
        );

        if (!$check) {

            $message = "Database error: " . mysqli_error($conn);
            $message_type = "error";

        } else {

            mysqli_stmt_bind_param(
                $check,
                "s",
                $team_code
            );

            mysqli_stmt_execute($check);

            mysqli_stmt_store_result($check);


            if (mysqli_stmt_num_rows($check) > 0) {

                $message = "This team code already exists.";
                $message_type = "error";

            } else {


                /* =============================================
                   HASH PASSWORD
                   
                   Database stores ONLY the hash.
                   It does NOT store Ai@123 directly.
                ============================================= */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /* =============================================
                   INSERT TEAM
                   
                   Default values:
                   
                   coins            = 100
                   current_round    = 1
                   current_question = 1
                   status           = Active
                ============================================= */

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO teams
                    (
                        team_code,
                        team_name,
                        password,
                        coins,
                        current_round,
                        current_question,
                        status
                    )
                    VALUES
                    (?, ?, ?, 50, 1, 1, 'Active')"
                );


                if (!$stmt) {

                    $message = "Database error: " . mysqli_error($conn);
                    $message_type = "error";

                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sss",
                        $team_code,
                        $team_name,
                        $hashed_password
                    );


                    /* =========================================
                       EXECUTE INSERT
                    ========================================= */

                    if (mysqli_stmt_execute($stmt)) {

                        /*
                         * Team successfully created.
                         *
                         * Login:
                         * Team Code = entered team code
                         * Password  = Ai@123
                         */

                        mysqli_stmt_close($stmt);
                        mysqli_stmt_close($check);

                        header("Location: teams.php?success=team_added");
                        exit();

                    } else {

                        $message = "Unable to create team: " .
                                   mysqli_stmt_error($stmt);

                        $message_type = "error";
                    }

                    mysqli_stmt_close($stmt);
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
        Add Team | AI Prediction Market
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
                Add New Team
            </h1>

            <p>
                Create a new prediction team and configure its login details.
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


        <!-- FORM HEADER -->

        <div class="form-card-header">

            <div class="form-icon">
                👥
            </div>

            <div>

                <h2>
                    Team Information
                </h2>

                <p>
                    Enter the details required for team login.
                </p>

            </div>

        </div>



        <!-- =================================================
             FORM
        ================================================== -->

        <form
            method="POST"
            action=""
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
                    placeholder="Example: TEAM001"
                    maxlength="50"
                    required
                >

                <small>
                    A unique code used by the team to log in.
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
                    placeholder="Example: AI Warriors"
                    maxlength="100"
                    required
                >

                <small>
                    Enter the name that will be displayed on the game.
                </small>

            </div>



            <!-- =================================================
                 TEAM PASSWORD
            ================================================== -->

            <div class="form-group">

                <label for="password">
                    Team Password
                </label>

                <input
                    type="text"
                    id="password"
                    value="Ai@123"
                    readonly
                    class="fixed-password"
                >

                <small>
                    Default password for every team:
                    <strong>Ai@123</strong>
                </small>

            </div>



            <!-- =================================================
                 PASSWORD INFORMATION
            ================================================== -->

            <div class="password-info">

                <div class="password-info-icon">
                    🔐
                </div>

                <div>

                    <strong>
                        Team Login Password
                    </strong>

                    <p>
                        All teams use the password
                        <strong>Ai@123</strong>.
                        The password is securely hashed before it is
                        stored in the database.
                    </p>

                </div>

            </div>



            <!-- =================================================
                 DEFAULT GAME SETTINGS
            ================================================== -->

            <div class="default-settings">

                <h3>
                    Default Game Settings
                </h3>


                <div class="settings-grid">


                    <!-- COINS -->

                    <div class="setting-item">

                        <span>
                            Starting Coins
                        </span>

                        <strong>
                            🪙 50
                        </strong>

                    </div>



                    <!-- ROUND -->

                    <div class="setting-item">

                        <span>
                            Starting Round
                        </span>

                        <strong>
                            Round 1
                        </strong>

                    </div>



                    <!-- QUESTION -->

                    <div class="setting-item">

                        <span>
                            Starting Question
                        </span>

                        <strong>
                            Question 1
                        </strong>

                    </div>



                    <!-- STATUS -->

                    <div class="setting-item">

                        <span>
                            Status
                        </span>

                        <strong class="active-text">
                            ● Active
                        </strong>

                    </div>

                </div>

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
                    Create Team
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