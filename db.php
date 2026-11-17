<?php
// db.php
$servername = "localhost";
$username = "root"; // XAMPP ka default username
$password = "";     // XAMPP mein password khali hota hai
$database = "buitems_complaints"; // Aapke database ka asal naam

// Connection banana
$conn = mysqli_connect($servername, $username, $password, $database);

// Check karna ke connection theek se ban gaya ya nahi
if (!$conn) {
    die("Database se connect nahi ho paya: " . mysqli_connect_error());
}
?>