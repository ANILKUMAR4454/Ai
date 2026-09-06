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

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;


/* =========================================================
   VALIDATE ID
========================================================= */

if ($id <= 0) {
    header("Location: teams.php?error=invalid_id");
    exit();
}


/* =========================================================
   CHECK TEAM EXISTS
========================================================= */

$check = mysqli_prepare(
    $conn,
    "SELECT id FROM teams WHERE id = ? LIMIT 1"
);

mysqli_stmt_bind_param(
    $check,
    "i",
    $id
);

mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) === 0) {

    mysqli_stmt_close($check);

    header("Location: teams.php?error=team_not_found");
    exit();
}

mysqli_stmt_close($check);


/* =========================================================
   DELETE TEAM
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM teams WHERE id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);


if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);


    /* =====================================================
       CHECK IF TEAMS TABLE IS EMPTY
    ===================================================== */

    $count_result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM teams"
    );

    $count_row = mysqli_fetch_assoc($count_result);

    $total_teams = (int)$count_row["total"];


    /* =====================================================
       RESET AUTO INCREMENT ONLY WHEN TABLE IS EMPTY
       
       Example:
       Delete ID 1
       Delete ID 2
       Delete ID 3

       Table becomes empty.

       Next team ID = 1
    ===================================================== */

    if ($total_teams === 0) {

        mysqli_query(
            $conn,
            "ALTER TABLE teams AUTO_INCREMENT = 1"
        );
    }


    /* =====================================================
       SUCCESS
    ===================================================== */

    header("Location: teams.php?success=team_deleted");
    exit();

} else {

    mysqli_stmt_close($stmt);

    header("Location: teams.php?error=delete_failed");
    exit();
}

?>