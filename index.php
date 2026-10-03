<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUITEMS Complaint Portal Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #e8f0fe;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-box {
            width: 420px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        /* Blue Header */
        .header {
            background-color: #0A2A5E;
            color: white;
            padding: 18px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 600;
        }

        .header-icon {
            font-size: 22px;
        }

        /* Content Area */
        .content {
            padding: 35px 30px;
        }

        .title {
            text-align: center;
            font-size: 24px;
            color: #0A2A5E;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: #333;
            margin-bottom: 7px;
            font-weight: 500;
        }

        /* Dropdown Style */
        .role-select {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background-color: white;
            cursor: pointer;
            outline: none;
        }

        .role-select:focus {
            border-color: #0A2A5E;
        }

        .note {
            font-size: 11px;
            color: #888;
            margin-top: 5px;
        }

        /* Input with Icon */
        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 13px 15px 13px 42px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #0A2A5E;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #888;
        }

        /* Eye Icon */
        .eye-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 16px;
            color: #888;
            user-select: none;
        }

        /* Remember + Forgot */
        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 13px;
        }

        .options label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #555;
            cursor: pointer;
        }

        .forgot {
            color: #0A2A5E;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot:hover {
            text-decoration: underline;
        }

        /* Login Button */
        .login-btn {
            width: 100%;
            padding: 14px;
            background-color: #0A2A5E;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .login-btn:hover {
            background-color: #08306b;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>

    <div class="login-box">
        <!-- Blue Header -->
        <div class="header">
    <span class="header-icon">🛡️</span>
    BUITEMS | Student Complaint Portal Login
</div>

        <!-- Content -->
        <div class="content">
            <div class="title">Login to your account</div>
            <div class="subtitle">Please select your role and enter your credentials to continue</div>

            <!-- Role Dropdown -->
            <div class="form-group">
                <label>Select Your Role</label>
                <select class="role-select" id="roleSelect">
                    <option value="" disabled selected>Select Your Role</option>
                    <option value="student">Student</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
                <div class="note">Role options (Student, Staff, Admin) are hidden until clicked</div>
            </div>

            <!-- User ID / Email -->
            <div class="form-group">
                <label>User ID / Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">✉️</span>
                    <input type="text" placeholder="Enter your User ID or Email">
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="passwordField" placeholder="Enter your password">
                    <span class="eye-icon" onclick="togglePassword()">👁️</span>
                </div>
            </div>

            <!-- Remember + Forgot -->
            <div class="options">
                <label>
                    <input type="checkbox"> Remember me
                </label>
                <a href="#" class="forgot">Forgot password?</a>
            </div>

            <!-- Login Button -->
            <button type="submit" class="login-btn">Login</button>

            <div class="footer">
                Need help? Contact IT Support • v2.1.0
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('passwordField');
            const eyeIcon = document.querySelector('.eye-icon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                eyeIcon.textContent = '👁️';
            }
        }
    </script>

</body>
</html>