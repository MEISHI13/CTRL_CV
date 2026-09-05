<?php
session_start();
require_once 'auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (loginUser($user, $pass)) {
        header('Location: index.php');
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWaste · Login</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Emergency fallback styles in case CSS doesn't load */
        body { font-family: 'Inter', Arial, sans-serif; background: #f0f7f2; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .auth-screen { max-width: 400px; width: 100%; margin: 0 auto; padding: 40px 30px; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 30, 15, 0.08); text-align: center; }
        .logo { font-size: 2.2rem; font-weight: 800; color: #0f3d26; }
        .logo i { color: #1f8a4a; }
        .subtitle { color: #5f7f6b; font-size: 0.85rem; margin-top: 4px; margin-bottom: 30px; }
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
    </style>
</head>
<body>

    <div class="auth-screen">
        <div class="logo">
            <i class="fas fa-recycle"></i> SmartWaste
        </div>
        <div class="subtitle">Turn Trash to Cash · Sustainable ASEAN</div>

        <form class="auth-form" action="login.php" method="POST">
            
            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="Username or Email" required />
            <input type="password" name="password" placeholder="Password" required />
            
            <button class="btn-auth" type="submit">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
            
            <div class="toggle-auth">
                Don't have an account? <a href="register.php">Register</a>
            </div>
        </form>
    </div>

</body>
</html>