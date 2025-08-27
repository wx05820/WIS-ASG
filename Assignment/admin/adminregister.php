<?php
include '../_base.php';

$_db->query('DELETE FROM staffregistertoken WHERE expire < NOW()');

$id = $_GET['id'];

// Check if token exists
if (!is_exists($id, 'staffregistertoken', 'id')) {
    temp('info', 'Invalid or expired token. Try again.');
    redirect('/index.php');
}

// Retrieve the email from token table
$stm = $_db->prepare("SELECT email, roles FROM staffregistertoken WHERE id = ?");
$stm->execute([$id]);
$tokenRow = $stm->fetch();
$emailFromToken = $tokenRow ? $tokenRow['email'] : '';
$roleFromToken  = $tokenRow ? $tokenRow['roles']  : '';

if (is_post()) {
    // Email will be hidden field
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $name     = req('name');

    // Validate email (even though hidden, still important)
    if (!$email) {
        $_err['email'] = 'Email not found';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Duplicated';
    }

    // Validate: password
    if (!$password) {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 8) {
        $_err['password'] = 'Password must be at least 8 characters';
    }
    else if (strlen($password) > 128) {
        $_err['password'] = 'Password must be less than 128 characters';
    }
    else if (!preg_match('/[A-Z]/', $password)) {
        $_err['password'] = 'Password must contain at least one uppercase letter';
    }
    else if (!preg_match('/[a-z]/', $password)) {
        $_err['password'] = 'Password must contain at least one lowercase letter';
    }
    else if (!preg_match('/[0-9]/', $password)) {
        $_err['password'] = 'Password must contain at least one number';
    }
    else if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $_err['password'] = 'Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    }

    // Validate: confirm
    if (!$confirm) {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm != $password) {
        $_err['confirm'] = 'Not matched';
    }

    // Validate: name
    if (!$name) {
        $_err['name'] = 'Required';
    }
    else if (strlen($name) > 100) {
        $_err['name'] = 'Maximum 100 characters';
    }

    if (!$_err) {
        // Insert staff
        $stm = $_db->prepare('
        INSERT INTO user (email, password, name, role, created_at)
        VALUES (?, SHA1(?), ?, ?, NOW())
        ');
        $stm->execute([$email, $password, $name,$rolesFromToken,]);

        // Delete used token
        $stm = $_db->prepare('DELETE FROM staffregistertoken WHERE id = ?');
        $stm->execute([$id]);

        temp('info', 'Record inserted');
        redirect('adminlogin.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link rel="stylesheet" href="../css/loginRegister.css">

    <title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
    <style>
        .error { border: 1px solid red; }
        .error-message { color: red; font-size: 0.9em; }
        .password-strength { margin-top: 5px; height: 6px; background: #eee; border-radius: 3px; }
        .password-strength-bar { height: 6px; width: 0; border-radius: 3px; transition: 0.3s; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Staff Registration</h1>
        </div>

        <form method="post" class="form" novalidate>
            <!-- Hidden Email -->
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($emailFromToken); ?>">  
            <div class="form-group">
            <label for="email">Email</label>
            <input 
            type="email" 
             id="email" 
             name="email" 
            class="form-input readonly-email" 
            value="<?php echo htmlspecialchars($emailFromToken); ?>" 
            readonly>
</div>
            <div class="form-group">
            <label for="roles">Roles</label>
            <input 
            type="text" 
            id="roles" 
            name="roles" 
            class="form-input readonly-email" 
            value="<?php echo htmlspecialchars($roleFromToken); ?>" 
            readonly
    >
</div>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-input <?php echo isset($_err['name']) ? 'error' : ''; ?>" 
                    maxlength="100"
                    placeholder="Enter your full name"
                    value="<?php echo htmlspecialchars(req('name')); ?>"
                    required
                >
                <?php if (isset($_err['name'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input <?php echo isset($_err['password']) ? 'error' : ''; ?>" 
                    maxlength="128"
                    placeholder="Enter your password"
                    required
                >
                <div class="password-strength">
                    <div class="password-strength-bar" id="password-strength-bar"></div>
                </div>
                <?php if (isset($_err['password'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['password']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input 
                    type="password" 
                    id="confirm" 
                    name="confirm" 
                    class="form-input <?php echo isset($_err['confirm']) ? 'error' : ''; ?>" 
                    maxlength="128"
                    placeholder="Confirm your password"
                    required
                >
                <?php if (isset($_err['confirm'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['confirm']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Register</button>
                <button type="reset" class="btn btn-secondary">Clear</button>
            </div>
        </form>

        <div class="links">
            Already have an account? <a href="adminlogin.php">Sign In</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        this.classList.remove('error');
                        const errorMsg = this.parentNode.querySelector('.error-message');
                        if (errorMsg) errorMsg.style.display = 'none';
                    }
                });
            });

            // Password strength
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('password-strength-bar');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;

                let width = [0,20,40,60,80,100][strength];
                let colors = ['#ef4444','#ef4444','#f59e0b','#fbbf24','#10b981','#059669'];
                strengthBar.style.width = width + '%';
                strengthBar.style.background = colors[strength];
            });
        });
    </script>
</body>
</html>
