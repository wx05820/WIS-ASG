<?php
include '../../_base.php'; // loads config.php internally via _base

// Check if user is admin
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('../loginstaff.php');
}

// Retrieve one-time flash messages
$success_msg = get_temp('success');
$error_msg   = get_temp('error');

// Handle form submission
if (is_post()) {
    $email = req('email');
    $roles = req('roles');

    // Validation
    if (!$email) {
        $_err['email'] = 'Required';
    } elseif (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    } elseif (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    } else {
        // Enhanced email existence check
        $stm = $_db->prepare("SELECT COUNT(*) FROM user WHERE email = ?");
        $stm->execute([$email]);
        $emailExists = $stm->fetchColumn() > 0;
        
        if ($emailExists) {
            $_err['email'] = 'Email already exists in database';
            $email_exists_error = true; // Flag for dialog display
        } elseif (!is_unique($email, 'user', 'email')) {
            $_err['email'] = 'Duplicated';
        }
    }
    
    if (!$roles) {
     $_err['roles'] = 'Roles is required';
    } elseif (!in_array($roles, ['Admin', 'Supervisor'])) {
    $_err['roles'] = 'Invalid roles selected';
     }

    // On success, send token email
    if (empty($_err)) {
        $id = bin2hex(random_bytes(16));

        // Delete existing tokens for this email
        $stm = $_db->prepare('DELETE FROM staffregistertoken WHERE email = ?');
        $stm->execute([$email]);

        // Insert new token with 5-minute expiration
        $stm = $_db->prepare('
            INSERT INTO staffregistertoken (id, expire, email, roles)
            VALUES (?, ADDTIME(NOW(), "00:30:00"), ?, ?)
        ');

        $stm->execute([$id, $email, $roles]);

        $url = base("adminregister.php?id=$id");

        $m = get_mail();
        $m->addAddress($email);
        $m->isHTML(true);
        $m->Subject = 'Staff Registration';

        // Embed the logo image and get the CID
        $logoCid = 'aikunlogo';
        $m->addEmbeddedImage(__DIR__ . '/../../images/logo.png', $logoCid, 'AiKUN Furniture Logo');

        // Build the HTML body with variables interpolated
        $m->Body = "
            <div style='text-align: center; font-family: Arial, sans-serif;'>
                <img src=\"cid:$logoCid\" alt=\"AiKUN Furniture Logo\" style=\"width: 120px; margin-bottom: 20px;\">
                <h1 style='color: #2d7a2d; margin-bottom: 10px;'>Welcome to AiKUN Furniture!</h1>
                <p style='font-size: 1.1em; color: #333;'>Dear <b>" . htmlspecialchars($email) . "</b>,</p>
                <p style='font-size: 1.1em; color: #333;'>
                    We are excited to invite you to join our team as <b>" . htmlspecialchars($roles) . "</b>!
                </p>
                <p style='font-size: 1.1em; color: #333;'>
                    To complete your staff registration, please click the button below within <b>5 minutes</b>:
                </p>
                <a href='" . htmlspecialchars($url) . "' style='display: inline-block; background: #2d7a2d; color: #fff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-size: 1.1em; margin: 20px 0;'>Register Now</a>
                <p style='color: #888; font-size: 0.95em; margin-top: 20px;'>
                    If you did not expect this invitation, you may safely ignore this email.<br>
                    <br>
                    Best regards,<br>
                    <b>AiKUN Furniture Admin Team</b>
                </p>
            </div>
        ";
        $m->send();

        temp('success', 'Email sent successfully!');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AiKUN Furniture - Add Staff Email</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/css/index.css">
<link rel="stylesheet" href="../../css/loginRegister.css">
</head>
<body class="bodylr">
    <div class="login-container">
        <div class="login-header">
            <button class="back-btn" onclick="window.location.href='../adminpage.php'">
            <i class="fas fa-arrow-left"></i>
            </button>
            <h1>Add Staff Email</h1>
            <p>Please enter the email you want to register</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_err['general'])): ?>
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
                    placeholder="Enter staff email"
                    value="<?php echo htmlspecialchars(req('email')); ?>"
                    required
                >
                <?php if (isset($_err['email'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['email']); ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
            <label for="roles">Select Role</label>
            <select 
            name="roles" 
            id="roles" 
            class="form-input <?php echo isset($_err['roles']) ? 'error' : ''; ?>" 
            required
                >
            <option value="">-- Select Roles --</option>
            <option value="Admin" <?php echo req('roles') === 'Admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="Supervisor" <?php echo req('roles') === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
            </select>
            <?php if (isset($_err['roles'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_err['roles']); ?></div>
            <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit</button>
                <button type="reset" class="btn btn-secondary">Clear</button>
            </div>
        </form>
    </div>

    <!-- Email Exists Dialog -->
    <?php if (isset($email_exists_error) && $email_exists_error): ?>
    <div id="emailExistsDialog" class="dialog">
        <div class="dialog-content">
            <span class="close-btn" onclick="closeEmailDialog()">&times;</span>
            <h2>⚠️ Email Already Exists</h2>
            <p>The email address <strong><?php echo htmlspecialchars(req('email')); ?></strong> is already registered in our database.</p>
            <p>Please use a different email address or contact the administrator if you need to reset the existing account.</p>
            <button class="btn" onclick="closeEmailDialog()">OK</button>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const dialog = document.getElementById("emailExistsDialog");
            dialog.style.display = "flex";
        });
        function closeEmailDialog() {
            const d = document.getElementById("emailExistsDialog");
            if (d) d.style.display = "none";
        }
    </script>
    <?php endif; ?>

    <!-- Success Dialog -->
    <?php if ($success_msg): ?>
    <div id="successDialog" class="dialog">
        <div class="dialog-content">
            <span class="close-btn" onclick="closeDialog()">&times;</span>
            <h2>✅ Success</h2>
            <p><?php echo htmlspecialchars($success_msg); ?></p>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const dialog = document.getElementById("successDialog");
            dialog.style.display = "flex";
            setTimeout(closeDialog, 3000);
        });
        function closeDialog() {
            const d = document.getElementById("successDialog");
            if (d) d.style.display = "none";
        }
    </script>
    <?php endif; ?>

    <script>
        // Clear error highlighting on input
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.form-input').forEach(input => {
                input.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        this.classList.remove('error');
                        const msg = this.parentNode.querySelector('.error-message');
                        if (msg) msg.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
