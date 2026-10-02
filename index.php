<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUITEMS Complaint Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f0f4f8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background-color: white;
            width: 420px;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #0A2A5E;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .role-title {
            font-size: 16px;
            color: #0A2A5E;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .roles {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .role-card {
            flex: 1;
            padding: 12px 5px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 14px;
            color: #333;
        }

        .role-card:hover {
            border-color: #0A2A5E;
            background-color: #f0f7ff;
        }

        .role-card.active {
            border-color: #0A2A5E;
            background-color: #0A2A5E;
            color: white;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #0A2A5E;
        }

        .login-btn {
            width: 100%;
            padding: 13px;
            background-color: #0A2A5E;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }

        .login-btn:hover {
            background-color: #FFC72C;
            color: #0A2A5E;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="logo">BUITEMS Complaint Portal</div>
        <div class="subtitle">Student Complaint Management System</div>

        <div class="role-title">Select Your Role</div>
        
        <div class="roles">
            <div class="role-card" onclick="selectRole(this)">Student</div>
            <div class="role-card" onclick="selectRole(this)">Staff</div>
            <div class="role-card" onclick="selectRole(this)">Admin</div>
        </div>

        <form>
            <div class="form-group">
                <label>Email / Roll No</label>
                <input type="text" placeholder="Enter your Email or Roll No">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" placeholder="Enter your password">
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="footer">
            © 2026 BUITEMS | Complaint Management System
        </div>
    </div>

    <script>
        function selectRole(element) {
            // pehle sab se active hatao
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('active');
            });
            // jis pe click hua uspe active lagao
            element.classList.add('active');
        }
    </script>

</body>
</html>