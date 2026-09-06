<?php

function hasCoins($conn, $team_id)
{
    $sql = "SELECT coins FROM teams WHERE id = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $team_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $team = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$team) {
        return false;
    }

    return (int)$team['coins'] > 0;
}
?>