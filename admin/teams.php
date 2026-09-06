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
   GET TEAM COUNTS
========================================================= */

$total_teams = 0;
$active_teams = 0;
$inactive_teams = 0;


/* =========================================================
   TOTAL TEAMS
========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM teams"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $total_teams = (int)$row["total"];
}


/* =========================================================
   ACTIVE TEAMS
========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM teams
     WHERE status = 'Active'"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $active_teams = (int)$row["total"];
}


/* =========================================================
   INACTIVE TEAMS
========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM teams
     WHERE status = 'Inactive'"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $inactive_teams = (int)$row["total"];
}


/* =========================================================
   GET ALL TEAMS
========================================================= */

$teams = mysqli_query(
    $conn,
    "SELECT
        id,
        team_code,
        team_name,
        password,
        coins,
        current_round,
        current_question,
        current_at,
        status,
        created_at
     FROM teams
     ORDER BY id DESC"
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
        Team Management | AI Prediction Market
    </title>


    <!-- TEAM PAGE CSS -->

    <link
        rel="stylesheet"
        href="../assets/team.css"
    >

</head>


<body>

<div class="team-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <header class="page-header">


        <!-- LEFT SIDE -->

        <div class="header-left">

            <div class="page-icon">
                👥
            </div>


            <div>

                <h1>
                    Team Management
                </h1>

                <p>
                    Create, manage and monitor prediction teams.
                </p>

            </div>

        </div>



        <!-- RIGHT SIDE BUTTONS -->

        <div class="header-actions">


            <!-- BACK TO DASHBOARD -->

            <a
                href="dashboard.php"
                class="dashboard-btn"
            >

                <span>
                    ←
                </span>

                Dashboard

            </a>



            <!-- ADD TEAM -->

            <a
                href="add_team.php"
                class="add-team-btn"
            >

                <span>
                    +
                </span>

                Add Team

            </a>

        </div>

    </header>



    <!-- =====================================================
         TEAM STATISTICS
    ====================================================== -->

    <section class="team-stats">


        <!-- TOTAL -->

        <div class="team-stat total-card">

            <div class="stat-icon">
                👥
            </div>


            <div class="stat-content">
                

                <span>
                    Total Teams
                </span>

                <strong>
                    <?php echo $total_teams; ?>
                </strong>

            </div>

        </div>



        <!-- ACTIVE -->

        <div class="team-stat active-card">

            <div class="stat-icon">
                ✓
            </div>


            <div class="stat-content">

                <span>
                    Active Teams
                </span>

                <strong class="active-number">
                    <?php echo $active_teams; ?>
                </strong>

            </div>

        </div>



        <!-- INACTIVE -->

        <div class="team-stat inactive-card">

            <div class="stat-icon">
                !
            </div>


            <div class="stat-content">

                <span>
                    Inactive Teams
                </span>

                <strong class="inactive-number">
                    <?php echo $inactive_teams; ?>
                </strong>

            </div>

        </div>

    </section>



    <!-- =====================================================
         TEAM TABLE
    ====================================================== -->

    <section class="teams-panel">


        <!-- PANEL HEADER -->

        <div class="teams-panel-header">

            <div>

                <h2>
                    All Teams
                </h2>

                <p>
                    Manage registered teams and their current game progress.
                </p>

            </div>


            <div class="team-count">

                <?php echo $total_teams; ?>

                <?php
                echo ($total_teams == 1)
                    ? "Team"
                    : "Teams";
                ?>

            </div>

        </div>



        <!-- =================================================
             TABLE
        ================================================== -->

        <div class="table-wrapper">

            <table class="teams-table">


                <!-- TABLE HEADER -->

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Team Code
                        </th>

                        <th>
                            Team Name
                        </th>

                        <th>
                            Coins
                        </th>

                        <th>
                            Round
                        </th>

                        <th>
                            Question
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Current At
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>



                <!-- TABLE BODY -->

                <tbody>


                <?php if ($teams && mysqli_num_rows($teams) > 0) { ?>


                    <?php while ($team = mysqli_fetch_assoc($teams)) { ?>


                        <tr>


                            <!-- ID -->

                            <td class="id-cell">

                                #

                                <?php
                                echo (int)$team["id"];
                                ?>

                            </td>



                            <!-- TEAM CODE -->

                            <td>

                                <span class="team-code">

                                    <?php

                                    echo htmlspecialchars(
                                        $team["team_code"]
                                    );

                                    ?>

                                </span>

                            </td>



                            <!-- TEAM NAME -->

                            <td>

                                <div class="team-name-box">


                                    <div class="team-avatar">

                                        <?php

                                        echo strtoupper(
                                            substr(
                                                $team["team_name"],
                                                0,
                                                1
                                            )
                                        );

                                        ?>

                                    </div>


                                    <span class="team-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $team["team_name"]
                                        );

                                        ?>

                                    </span>


                                </div>

                            </td>



                            <!-- COINS -->

                            <td>

                                <span class="coins">

                                    🪙

                                    <?php

                                    echo number_format(
                                        (int)$team["coins"]
                                    );

                                    ?>

                                </span>

                            </td>



                            <!-- ROUND -->

                            <td>

                                <span class="progress-badge">

                                    Round

                                    <?php

                                    echo (int)$team["current_round"];

                                    ?>

                                </span>

                            </td>



                            <!-- QUESTION -->

                            <td>

                                <span class="question-badge">

                                    Q<?php

                                    echo (int)$team["current_question"];

                                    ?>

                                </span>

                            </td>



                            <!-- STATUS -->

                            <td>

                                <?php if ($team["status"] === "Active") { ?>


                                    <span class="status status-active">

                                        <span class="status-dot"></span>

                                        Active

                                    </span>


                                <?php } else { ?>


                                    <span class="status status-inactive">

                                        <span class="status-dot"></span>

                                        Inactive

                                    </span>


                                <?php } ?>

                            </td>



                            <!-- CURRENT AT -->

                            <td>

                                <span class="created-date">

                                    <?php

                                    echo !empty($team["current_at"])
                                        ? htmlspecialchars(
                                            $team["current_at"]
                                        )
                                        : "—";

                                    ?>

                                </span>

                            </td>



                            <!-- CREATED -->

                            <td>

                                <span class="created-date">

                                    <?php

                                    echo htmlspecialchars(
                                        $team["created_at"]
                                    );

                                    ?>

                                </span>

                            </td>



                            <!-- ACTION -->

                            <td>

                                <div class="action-buttons">


                                    <!-- EDIT -->

                                    <a
                                        href="edit_team.php?id=<?php echo (int)$team["id"]; ?>"
                                        class="action-btn edit-btn"
                                    >
                                        Edit
                                    </a>



                                    <!-- DELETE -->

                                    <a
                                        href="delete_team.php?id=<?php echo (int)$team["id"]; ?>"
                                        class="action-btn delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this team?');"
                                    >
                                        Delete
                                    </a>


                                </div>

                            </td>


                        </tr>


                    <?php } ?>


                <?php } else { ?>


                    <!-- =================================================
                         EMPTY STATE
                    ================================================== -->

                    <tr>

                        <td colspan="10">


                            <div class="empty-state">


                                <div class="empty-icon">
                                    👥
                                </div>


                                <h3>
                                    No Teams Found
                                </h3>


                                <p>
                                    Create your first team to start the
                                    prediction game.
                                </p>


                                <a
                                    href="add_team.php"
                                    class="empty-add-btn"
                                >
                                    + Create First Team
                                </a>


                            </div>


                        </td>

                    </tr>


                <?php } ?>


                </tbody>

            </table>

        </div>

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

            Team Management

        </span>

    </footer>


</div>

</body>

</html>