<?php

include '../_base.php';

if (is_post()) {
    $email    = req('email');
    $password = req('password');

    // Validate: email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    // Validate: password
    if ($password == '') {
        $_err['password'] = 'Required';
    }

    // Login user
    if (!$_err) {
        // TODO
        $stm = $_db->prepare('
            SELECT * FROM user
            WHERE email = ? AND password = SHA1(?)
        ');
        $stm->execute([$email,$password]);
        $u = $stm->fetch();

        if ($u) {
            temp('info', 'Login successfully');
            login($u,'adminpage.php');
        }
        else {
            $_err['password'] = 'Not matched';
        }
    }
}

$page_title = 'Staff Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Your App</title>
    <link rel="stylesheet" href=    "../css/loginRegister.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Staff Login</h1>
            <p>Please  login to your account</p>
        </div>

        <?php if ($success_msg = get_temp('success')): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg = get_temp('error')): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_err['general'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_err['general']); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input <?php echo isset($_err['email']) ? 'error' : ''; ?>" 
                    maxlength="100"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars(req('email')); ?>"
                    required
                >
                <?php if (isset($_err['email'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['email']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input <?php echo isset($_err['password']) ? 'error' : ''; ?>" 
                    maxlength="100"
                    placeholder="Enter your password"
                    required
                >
                <?php if (isset($_err['password'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['password']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Sign In
                </button>
                <button type="reset" class="btn btn-secondary">
                    Clear
                </button>
            </div>
        </form>

        <div class="links">
            <a href="forgot-password.php">Forgot Password?</a>
        </div>
    </div>

    <script>
        // Clear error styling when user starts typing
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        this.classList.remove('error');
                        const errorMsg = this.parentNode.querySelector('.error-message');
                        if (errorMsg) {
                            errorMsg.style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>