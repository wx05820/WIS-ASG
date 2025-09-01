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
$emailFromToken = $tokenRow ? $tokenRow->email : '';
$roleFromToken  = $tokenRow ? $tokenRow->roles : '';

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
    do {
            $username = generateRandomUsername();
            $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE username = ?');
            $stm->execute([$username]);
    } while ($stm->fetchColumn() > 0);
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $defaultProfilePhoto = '../profilePhoto/default-profile.jpg';
        
        // Insert staff
        $stm = $_db->prepare('
        INSERT INTO user (username, email, password, photo, name, role, created_at, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(),?)
        ');
        $stm->execute([$username, $email, $password_hash, $defaultProfilePhoto, $name,$roleFromToken, "Active"]);


        // Delete used token*/
        $stm = $_db->prepare('DELETE FROM staffregistertoken WHERE id = ?');
        $stm->execute([$id]);

        temp('info', 'Record inserted');
        redirect('adminpage.php');
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
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
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
            <div class="security-notice">
                    💡 Password must be 8–128 chars with uppercase, lowercase, number, and special symbol.
            </div>

                         <div class="form-group">
                 <label for="password">Password</label>
                 <div class="password-input-container">
                     <input 
                         type="password" 
                         id="password" 
                         name="password" 
                         class="form-input <?php echo isset($_err['password']) ? 'error' : ''; ?>" 
                         maxlength="128"
                         placeholder="Enter your password"
                         required
                     >
                     <button type="button" class="password-toggle" onclick="togglePassword('password')">
                         <i class="eye-icon show fas fa-eye"></i>
                     </button>
                 </div>

                 <?php if (isset($_err['password'])): ?>
                     <div class="error-message"><?php echo htmlspecialchars($_err['password']); ?></div>
                 <?php endif; ?>
             </div>

                         <div class="form-group">
                 <label for="confirm">Confirm Password</label>
                 <div class="password-input-container">
                     <input 
                         type="password" 
                         id="confirm" 
                         name="confirm" 
                         class="form-input <?php echo isset($_err['confirm']) ? 'error' : ''; ?>" 
                         maxlength="128"
                         placeholder="Confirm your password"
                         required
                     >
                     <button type="button" class="password-toggle" onclick="togglePassword('confirm')">
                         <i class="eye-icon show fas fa-eye"></i>
                     </button>
                 </div>
                 <?php if (isset($_err['confirm'])): ?>
                     <div class="error-message"><?php echo htmlspecialchars($_err['confirm']); ?></div>
                 <?php endif; ?>
             </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Register</button>
                <button type="reset" class="btn btn-secondary">Clear</button>
            </div>
        </form>
    </div>

    <script src="../js/loginRegister.js"></script>
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
         });

         // Password toggle function
         function togglePassword(fieldId) {
             const passwordField = document.getElementById(fieldId);
             if (!passwordField) return;

             const toggleButton = passwordField.parentNode.querySelector('.password-toggle');
             const eyeIcon = toggleButton.querySelector('.eye-icon');

             if (passwordField.type === 'password') {
                 passwordField.type = 'text';
                 eyeIcon.classList.remove('show', 'fas', 'fa-eye');
                 eyeIcon.classList.add('hide', 'fas', 'fa-eye-slash');
                 eyeIcon.classList.add('state-change');
                 setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
             } else {
                 passwordField.type = 'password';
                 eyeIcon.classList.remove('hide', 'fas', 'fa-eye-slash');
                 eyeIcon.classList.add('show', 'fas', 'fa-eye');
                 eyeIcon.classList.add('state-change');
                 setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
             }
         }
     </script>
</body>
</html>
