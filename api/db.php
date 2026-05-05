<?php
$conn = new mysqli("localhost", "root", "", "auto_service", 3308);

if ($conn->connect_error) {
    die("DB error: " . $conn->connect_error);
}
?>