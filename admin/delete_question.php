<?php
session_start();

/* ---------- NO CACHE ---------- */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/* ---------- ADMIN CHECK ---------- */
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

/* ---------- DATABASE ---------- */
require_once "../db.php";

/* ---------- GET QUESTION ID ---------- */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: questions.php?error=invalid_id");
    exit();
}

$id = (int) $_GET['id'];

if ($id <= 0) {
    header("Location: questions.php?error=invalid_id");
    exit();
}

/* ---------- CHECK QUESTION EXISTS ---------- */
$stmt = $conn->prepare("
    SELECT id, round_number, question_number
    FROM questions
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    header("Location: questions.php?error=question_not_found");
    exit();
}

$question = $result->fetch_assoc();
$stmt->close();

/* ---------- DELETE QUESTION ---------- */
$stmt = $conn->prepare("
    DELETE FROM questions
    WHERE id = ?
");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    $stmt->close();

    header("Location: questions.php?success=question_deleted");
    exit();

} else {

    $stmt->close();

    header("Location: questions.php?error=delete_failed");
    exit();
}
?>