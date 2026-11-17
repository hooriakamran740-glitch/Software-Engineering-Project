<?php
session_start();

// Database Connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "buitems_complaints";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$message = "";

// Form Submit hone par logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // ---------------- LOGIN LOGIC ----------------
    if ($action == 'login') {
        $role = strtolower(trim($_POST['role'] ?? ''));
        $email = trim($_POST['email_or_roll'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND LOWER(role) = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $user_role = strtolower(trim($row['role']));
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['role'] = $user_role;
                    $_SESSION['full_name'] = $row['full_name'];

                    if ($user_role === 'admin') {
                        header("Location: admin_dashboard.php");
                    } elseif ($user_role === 'staff') {
                        header("Location: staff_dashboard.php");
                    } else {
                        header("Location: student_dashboard.php");
                    }
                    exit();
                } else {
                    $message = "<div class='error-msg'>Ghalat Password darj kiya gaya hai!</div>";
                }
            } else {
                $message = "<div class='error-msg'>Invalid Email, Password, or Role!</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='error-msg'>Database Query Error: " . $conn->error . "</div>";
        }
    } 
    // ---------------- REGISTER LOGIC ----------------
    elseif ($action == 'register') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email_or_roll'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? ''));

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($check) {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $message = "<div class='error-msg'>User with this Email already exists!</div>";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);
                    if ($stmt->execute()) {
                        $message = "<div class='success-msg'>Registration Successful! Ab aap login kar sakte hain.</div>";
                    } else {
                        $message = "<div class='error-msg'>Error during registration.</div>";
                    }
                    $stmt->close();
                }
            }
            $check->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUITEMS | Student Complaint Portal Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #1e1e1e; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        
        .card { background-color: white; width: 100%; max-width: 420px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .card-header { background-color: #0a295c; color: white; padding: 18px; display: flex; align-items: center; font-weight: bold; font-size: 16px; justify-content: center; gap: 10px; }
        .card-header i { font-size: 20px; color: #4b9ced; }
        
        .card-body { padding: 35px 30px; }
        .title { color: #0a295c; font-size: 22px; font-weight: bold; text-align: center; margin-bottom: 8px; }
        .subtitle { color: #666; font-size: 13px; text-align: center; margin-bottom: 25px; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; color: #333; }
        
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i.left-icon { position: absolute; left: 15px; color: #b3b3b3; }
        .input-wrapper i.right-icon { position: absolute; right: 15px; color: #666; cursor: pointer; }
        
        select, input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; appearance: none; background: #fff; }
        .input-wrapper input { padding-left: 42px; }
        .input-wrapper input[type="password"] { padding-right: 42px; }
        select:focus, input:focus { border-color: #0a295c; }
        
        .options-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 13px; }
        .options-row label { color: #666; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .options-row a { color: #0a295c; text-decoration: none; }
        
        .btn { width: 100%; padding: 12px; background-color: #0a295c; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn:hover { background-color: #071d42; }
        
        .toggle-link { text-align: center; margin-top: 15px; font-size: 13px; color: #666; }
        .toggle-link a { color: #0a295c; font-weight: bold; cursor: pointer; text-decoration: none; }
        
        .footer { text-align: center; margin-top: 25px; font-size: 11px; color: #aaa; }
        
        .error-msg { background: #ffe6e6; color: #d8000c; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; font-size: 13px; }
        .success-msg { background: #dff0d8; color: #3c763d; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; font-size: 13px; }
        
        #registerForm { display: none; }
    </style>
</head>
<body>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-shield-alt"></i> BUITEMS | Student Complaint Portal Login
        </div>
        
        <div class="card-body">
            <?php echo $message; ?>

            <!-- ================= LOGIN FORM ================= -->
            <form id="loginForm" method="POST" action="">
                <input type="hidden" name="action" value="login">
                
                <div class="title">Login to your account</div>
                <div class="subtitle">Please select your role and enter your credentials to continue</div>

                <div class="form-group">
                    <label>Select Your Role</label>
                    <select name="role" required>
                        <option value="" disabled selected>Select Your Role</option>
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope left-icon" style="color: #d8b4e2;"></i>
                        <input type="email" name="email_or_roll" placeholder="Enter your email address" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock left-icon" style="color: #ffb74d;"></i>
                        <input type="password" name="password" id="loginPassword" placeholder="Enter your password" required>
                        <i class="fas fa-eye right-icon" onclick="togglePassword('loginPassword')"></i>
                    </div>
                </div>

                <div class="options-row">
                    <label><input type="checkbox"> Remember me</label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit" class="btn">Login</button>
                
                <div class="toggle-link">
                    Don't have an account? <a onclick="toggleForms()">Register here</a>
                </div>
            </form>

            <!-- ================= REGISTER FORM ================= -->
            <form id="registerForm" method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="title">Create an account</div>
                <div class="subtitle">Enter your details to register in the portal</div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label>Select Your Role</label>
                    <select name="role" required>
                        <option value="" disabled selected>Select Your Role</option>
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope left-icon" style="color: #d8b4e2;"></i>
                        <input type="email" name="email_or_roll" placeholder="Enter your email address" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock left-icon" style="color: #ffb74d;"></i>
                        <input type="password" name="password" id="registerPassword" placeholder="Create a password" required>
                        <i class="fas fa-eye right-icon" onclick="togglePassword('registerPassword')"></i>
                    </div>
                </div>

                <button type="submit" class="btn">Register</button>
                
                <div class="toggle-link">
                    Already have an account? <a onclick="toggleForms()">Login here</a>
                </div>
            </form>

            <div class="footer">
                Need help? Contact IT Support • v2.1.0
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function toggleForms() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            if (loginForm.style.display !== "none") {
                loginForm.style.display = "none";
                registerForm.style.display = "block";
            } else {
                loginForm.style.display = "block";
                registerForm.style.display = "none";
            }
        }
    </script>
</body>
</html>