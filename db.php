<?php

$conn = mysqli_connect("localhost", "root", "", "ai");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

?>