<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| ADMIN SESSION CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/
$MAX_VIOLATIONS = 3;
$PENALTY_PER_VIOLATION = 5;

/*
|--------------------------------------------------------------------------
| CREATE TABLE IF NOT EXISTS
|--------------------------------------------------------------------------
*/
$create_table = "
CREATE TABLE IF NOT EXISTS game_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    violation_type VARCHAR(100) NOT NULL,
    violation_count INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (team_id)
) ENGINE=InnoDB
";

mysqli_query($conn, $create_table);

/*
|--------------------------------------------------------------------------
| DELETE ALL VIOLATIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "clear_all") {

        mysqli_query($conn, "TRUNCATE TABLE game_violations");

        $_SESSION["success"] = "All violations cleared successfully.";

        header("Location: violations.php");
        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ONE VIOLATION
    |--------------------------------------------------------------------------
    */
    if ($action === "delete") {

        $violation_id = (int) ($_POST["violation_id"] ?? 0);

        if ($violation_id > 0) {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM game_violations WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $violation_id
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $_SESSION["success"] = "Violation deleted successfully.";
        }

        header("Location: violations.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

/*
|--------------------------------------------------------------------------
| GET TOTAL VIOLATIONS
|--------------------------------------------------------------------------
*/
$total_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM game_violations"
);

$total_data = mysqli_fetch_assoc($total_result);
$total_violations = (int) $total_data["total"];

/*
|--------------------------------------------------------------------------
| GET TOTAL TEAMS WITH VIOLATIONS
|--------------------------------------------------------------------------
*/
$teams_result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT team_id) AS total
     FROM game_violations"
);

$teams_data = mysqli_fetch_assoc($teams_result);
$total_teams = (int) $teams_data["total"];

/*
|--------------------------------------------------------------------------
| GET DISQUALIFIED TEAMS
|--------------------------------------------------------------------------
*/
$disqualified_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM teams
     WHERE LOWER(status) = 'disqualified'"
);

$disqualified_data = mysqli_fetch_assoc($disqualified_result);
$total_disqualified = (int) $disqualified_data["total"];

/*
|--------------------------------------------------------------------------
| GET TOTAL PENALTY
|--------------------------------------------------------------------------
*/
$total_penalty = $total_violations * $PENALTY_PER_VIOLATION;

/*
|--------------------------------------------------------------------------
| GET VIOLATIONS
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        gv.id,
        gv.team_id,
        gv.violation_type,
        gv.violation_count,
        gv.created_at,
        t.team_code,
        t.coins,
        t.status,
        (
            SELECT COALESCE(SUM(gv2.violation_count), 0)
            FROM game_violations gv2
            WHERE gv2.team_id = gv.team_id
        ) AS team_total_violations
    FROM game_violations gv
    LEFT JOIN teams t
        ON t.id = gv.team_id
    ORDER BY gv.created_at DESC
";

$result = mysqli_query($conn, $sql);

/*
|--------------------------------------------------------------------------
| FORMAT VIOLATION NAME
|--------------------------------------------------------------------------
*/
function formatViolation($type)
{
    return ucwords(
        str_replace("_", " ", $type)
    );
}

/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Team Violations</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #1f2937;
        }

        .page {
            padding: 30px;
            max-width: 1400px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
        }

        .header p {
            margin-top: 8px;
            color: #6b7280;
        }

        .back-btn {
            text-decoration: none;
            background: #374151;
            color: white;
            padding: 11px 18px;
            border-radius: 8px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .card h3 {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .card .number {
            margin-top: 12px;
            font-size: 28px;
            font-weight: bold;
        }

        .red {
            color: #dc2626;
        }

        .orange {
            color: #ea580c;
        }

        .blue {
            color: #2563eb;
        }

        .green {
            color: #16a34a;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .table-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .table-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .clear-btn {
            border: none;
            background: #dc2626;
            color: white;
            padding: 10px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th {
            background: #f3f4f6;
            color: #374151;
            text-align: left;
            padding: 14px;
            font-size: 13px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        tr:hover {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge-warning {
            background: #ffedd5;
            color: #c2410c;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-gray {
            background: #e5e7eb;
            color: #374151;
        }

        .delete-btn {
            border: none;
            background: #ef4444;
            color: white;
            padding: 7px 11px;
            border-radius: 6px;
            cursor: pointer;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #6b7280;
        }

        @media (max-width: 900px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .page {
                padding: 15px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 500px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page">

    <div class="header">
        <div>
            <h1>Team Violations</h1>
            <p>Monitor fullscreen, tab switching, copying, pasting and other violations.</p>
        </div>

        <a href="dashboard.php" class="back-btn">
            Back to Dashboard
        </a>
    </div>

    <?php if ($success !== ""): ?>
        <div class="message">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div class="cards">

        <div class="card">
            <h3>Total Violations</h3>
            <div class="number red">
                <?= $total_violations ?>
            </div>
        </div>

        <div class="card">
            <h3>Teams With Violations</h3>
            <div class="number blue">
                <?= $total_teams ?>
            </div>
        </div>

        <div class="card">
            <h3>Disqualified Teams</h3>
            <div class="number orange">
                <?= $total_disqualified ?>
            </div>
        </div>

        <div class="card">
            <h3>Total Penalty Coins</h3>
            <div class="number green">
                <?= $total_penalty ?>
            </div>
        </div>

    </div>

    <div class="table-box">

        <div class="table-header">
            <h2>Violation History</h2>

            <form method="POST"
                  onsubmit="return confirm('Delete all violation records?');">

                <input type="hidden"
                       name="action"
                       value="clear_all">

                <button type="submit" class="clear-btn">
                    Clear All
                </button>

            </form>
        </div>

        <?php if ($result && mysqli_num_rows($result) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Team</th>
                        <th>Violation</th>
                        <th>Team Total</th>
                        <th>Penalty</th>
                        <th>Team Coins</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <?php
                    $team_status = strtolower(
                        trim($row["status"] ?? "")
                    );

                    $team_total = (int) $row["team_total_violations"];

                    if ($team_status === "disqualified") {
                        $status_class = "badge-danger";
                    } elseif ($team_total >= $MAX_VIOLATIONS) {
                        $status_class = "badge-danger";
                    } elseif ($team_total > 0) {
                        $status_class = "badge-warning";
                    } else {
                        $status_class = "badge-success";
                    }
                    ?>

                    <tr>

                        <td>
                            <?= (int) $row["id"] ?>
                        </td>

                        <td>
                            <strong>
                                <?= e($row["team_code"] ?? "Unknown") ?>
                            </strong>
                            <br>
                            <small>
                                Team ID:
                                <?= (int) $row["team_id"] ?>
                            </small>
                        </td>

                        <td>
                            <span class="badge badge-danger">
                                <?= e(formatViolation($row["violation_type"])) ?>
                            </span>
                        </td>

                        <td>
                            <?= $team_total ?> / <?= $MAX_VIOLATIONS ?>
                        </td>

                        <td>
                            <?= $PENALTY_PER_VIOLATION ?> coins
                        </td>

                        <td>
                            <?= (int) ($row["coins"] ?? 0) ?>
                        </td>

                        <td>
                            <span class="badge <?= $status_class ?>">
                                <?= e($row["status"] ?? "Unknown") ?>
                            </span>
                        </td>

                        <td>
                            <?= e($row["created_at"]) ?>
                        </td>

                        <td>
                            <form method="POST"
                                  onsubmit="return confirm('Delete this violation?');">

                                <input type="hidden"
                                       name="action"
                                       value="delete">

                                <input type="hidden"
                                       name="violation_id"
                                       value="<?= (int) $row["id"] ?>">

                                <button type="submit"
                                        class="delete-btn">
                                    Delete
                                </button>

                            </form>
                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                No violations recorded yet.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>