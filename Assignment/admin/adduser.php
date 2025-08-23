<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email = req('email');

    // Validate email
    if (!$email) {
        $_err['email'] = 'Required';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_unique($email, 'staff', 'email')) {
        $_err['email'] = 'Duplicated';
    }
 if (!$_err){
 //Generate Token ID
$id = bin2hex(random_bytes(16));

// DELETE old token for this email
$stm = $_db->prepare(' DELETE FROM staffregistertoken WHERE email = ?');
$stm->execute([$email]);

// INSERT new token
$stm = $_db->prepare('
    INSERT INTO staffregistertoken (id, expire, email)
    VALUES (?, ADDTIME(NOW(), "00:5"), ?)
');
$stm->execute([$id, $email]);

//Generate token url
$url = base("admin/adminregister.php?id=$id");

        // TODO: (5) Send email
        $m = get_mail();
        $m -> addAddress($email);
        $m ->isHTML(true);
        $m -> Subject = 'Staff Registration';
        $m->Body = "
            <p>Dear $email,<p>
            <h1 style='color: red'>Staff Account Registration</h1>
            <p>
                Please click <a href='$url'>here</a>
                to create your staff account.
            </p>
            <p>From, Admin</p>
        ";
        $m ->send();
        temp('info', 'Email sent');

}
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body class="bodylr">
    <div class="login-container">
        <div class="login-header">
            <h1>Email</h1>
            <p>Please enter the email you want to add as staff</p>
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


            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Submit
                </button>
                <button type="reset" class="btn btn-secondary">
                    Clear
                </button>
            </div>
        </form>
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