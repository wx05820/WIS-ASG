<?php
include '../_base.php';

$_db->query('DELETE FROM staffregistertoken WHERE expire < NOW()');

$id = $_GET['id'];

if (!is_exists($id, 'staffregistertoken', 'id')) {
    temp('info', 'Invalid or expired token. Try again.');
    redirect('adminpage.php');
}

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $name     = req('name');

    if (!$email) {
        $_err['email'] = 'Required';
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
    else if (strlen($password) < 5 || strlen($password) > 100) {
        $_err['password'] = 'Between 5-100 characters';
    }

    // Validate: confirm
    if (!$confirm) {
        $_err['confirm'] = 'Required';
    }
    else if (strlen($confirm) < 5 || strlen($confirm) > 100) {
        $_err['confirm'] = 'Between 5-100 characters';
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
        // (2) Insert user (member)
        // Insert new staff
        $stm = $_db->prepare('
        INSERT INTO staff (email, password, name, role, createdtime)
        VALUES (?, SHA1(?), ?, "Crew",NOW())
        ');
        $stm->execute([$email, $password, $name]);

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
    <script src="../script.js"></script>
    <title>AiKUN Furniture - Premium Malaysian Furniture Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .register-header p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input.error {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 6px;
            display: block;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e1e5e9;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            margin: 0 10px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 5px;
            height: 5px;
            background: #e1e5e9;
            border-radius: 5px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: #ef4444;
            transition: all 0.3s ease;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create Your Account</h1>
            <p>Staff Account Registration</p>
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
                    maxlength="100"
                    placeholder="Confirm your password"
                    required
                >
                <?php if (isset($_err['confirm'])): ?>
                    <div class="error-message"><?php echo htmlspecialchars($_err['confirm']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Register
                </button>
                <button type="reset" class="btn btn-secondary">
                    Clear
                </button>
            </div>
        </form>

        <div class="links">
            Already have an account? <a href="adminlogin.php">Sign In</a>
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

            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('password-strength-bar');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 8) strength += 1;
                if (password.length >= 12) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                // Update strength bar
                let width = 0;
                let color = '#ef4444'; // red
                
                if (strength <= 1) {
                    width = 25;
                    color = '#ef4444'; // red
                } else if (strength <= 3) {
                    width = 50;
                    color = '#f59e0b'; // yellow
                } else if (strength <= 4) {
                    width = 75;
                    color = '#10b981'; // green
                } else {
                    width = 100;
                    color = '#10b981'; // green
                }
                
                strengthBar.style.width = width + '%';
                strengthBar.style.background = color;
            });
        });
    </script>
</body>
</html>