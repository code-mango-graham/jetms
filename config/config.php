<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "db_jetmsis";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set character encoding
$conn->set_charset("utf8");
?>