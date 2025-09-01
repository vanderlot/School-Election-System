<?php
// Database credentials
$servername = "localhost";
$username = "root";
$password = ""; // Your password, if you have one
$dbname = "election_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  // If connection fails, stop the script and output an error message
  // die() is a function that terminates the script
  die("Connection failed: " . $conn->connect_error);
}
?>
