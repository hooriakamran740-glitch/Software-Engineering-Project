<?php
require "db.php";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name  = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $pass  = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?, 'student')");
    $stmt->bind_param("sss", $name, $email, $pass);
    $msg = $stmt->execute() ? "Registered! Ab login karein." : "Email pehle se maujood hai.";
}
?>
<h2>Student Register</h2>
<p><?= $msg ?></p>
<form method="post">
  <input name="name" placeholder="Name" required><br>
  <input type="email" name="email" placeholder="Email" required><br>
  <input type="password" name="password" placeholder="Password" required><br>
  <button>Register</button>
</form>
<a href="index.php">Login</a>
