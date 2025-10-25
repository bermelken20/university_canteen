<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>University Canteen System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .menu {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .menu a {
            display: block;
            padding: 15px 30px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            transition: background 0.3s ease;
        }
        .menu a:hover {
            background: #0056b3;
        }
        .menu a.admin {
            background: #28a745;
        }
        .menu a.admin:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to University Canteen</h1>
        <div class="menu">
            <a href="user_app/signin.php">User Login</a>
            <a href="user_app/signup.php">User Sign Up</a>
            <a href="admin_app/adminsignin.php" class="admin">Admin Login</a>
        </div>
    </div>
</body>
</html>