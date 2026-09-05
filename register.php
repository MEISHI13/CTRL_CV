<?php
session_start();
require_once 'auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple validation
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (registerUser($username, $email, $password)) {
        $success = "Account created! Please <a href='login.php' style='color:#6fcf97;'>login</a>.";
    } else {
        $error = "Username or email already exists!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWaste · Register</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Inter', Arial, sans-serif; background: #f0f7f2; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .auth-screen { max-width: 400px; width: 100%; margin: 0 auto; padding: 40px 30px; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 30, 15, 0.08); text-align: center; }
        .logo { font-size: 2rem; font-weight: 800; color: #0f3d26; }
        .logo i { color: #1f8a4a; }
        .subtitle { color: #5f7f6b; font-size: 0.85rem; margin-bottom: 30px; }
        .auth-form { display: flex; flex-direction: column; gap: 14px; }
        .auth-form input { padding: 14px 18px; border: 2px solid #eaf3ec; border-radius: 12px; font-family: inherit; font-size: 0.9rem; background: #f8fbf9; width: 100%; box-sizing: border-box; }
        .auth-form input:focus { outline: none; border-color: #1f8a4a; background: #ffffff; }
        .btn-auth { background: #0f3d26; color: #ffffff; border: none; padding: 14px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; width: 100%; font-family: inherit; }
        .btn-auth:hover { background: #1a5a3a; }
        .btn-auth i { margin-right: 8px; }
        .toggle-auth { font-size: 0.8rem; color: #5f7f6b; margin-top: 4px; }
        .toggle-auth a { color: #1f8a4a; text-decoration: none; font-weight: 600; }
        .toggle-auth a:hover { text-decoration: underline; }
        .error-msg { background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 10px; font-size: 0.85rem; border: 1px solid #f5c6cb; text-align: left; }
        .error-msg i { margin-right: 6px; }
        .success-msg { background: #d4edda; color: #155724; padding: 10px 14px; border-radius: 10px; font-size: 0.85rem; border: 1px solid #c3e6cb; text-align: left; }
        .success-msg.show { display: block; }
        .success-msg i { margin-right: 6px; }
    </style>
</head>
<body>

    <div class="auth-screen">
        <div class="logo">
            <i class="fas fa-recycle"></i> SmartWaste
        </div>
        <div class="subtitle">Create your account</div>

        <form class="auth-form" action="register.php" method="POST">
            
            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-msg show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="Username" required />
            <input type="email" name="email" placeholder="Email" required />
            <input type="password" name="password" placeholder="Password (min 6 chars)" required />
            
            <button class="btn-auth" type="submit">
                <i class="fas fa-user-plus"></i> Register
            </button>
            
            <div class="toggle-auth">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </form>
    </div>

</body>
</html>