<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Handle login form submission
if ($_POST) {
    $auth = new Auth();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            $_SESSION['login_success'] = 'Login successful! Welcome ' . $_SESSION['first_name'];
            if ($_SESSION['role'] === 'admin') {
                header('Location: patients.php');
                exit;
            } else {
                header('Location: ' . $result['redirect']);
                exit;
            }
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - EasyMed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-form h2 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #00bcd4;
            box-shadow: 0 0 5px rgba(0,188,212,0.3);
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #00bcd4;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #0097a7;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        .default-creds {
            background: #e2e3e5;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        .default-creds h4 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }
        .nav-links {
            text-align: center;
            margin-top: 1rem;
        }
        .nav-links a {
            color: #00bcd4;
            text-decoration: none;
            margin: 0 10px;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h2>Admin Login</h2>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['login_success'])): ?>
                <div class="success"><?php echo $_SESSION['login_success']; unset($_SESSION['login_success']); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username or Email:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <div class="default-creds">
                <h4>Default Admin Credentials:</h4>
                <strong>Username:</strong> admin<br>
                <strong>Password:</strong> admin123
            </div>
            
            <div class="nav-links">
                <a href="auth_debug.php">Debug Page</a> |
                <a href="patients.php">Patient Management</a> |
                <a href="../../index.php">Home</a>
            </div>
        </div>
    </div>
</body>
</html>
