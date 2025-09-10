<?php
require_once "_base.php";

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$messageType = '';

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_welcome'])) {
        if (sendWelcomeEmail()) {
            $message = "Welcome email sent successfully to " . $_SESSION['user']['email'];
            $messageType = "success";
        } else {
            $message = "Failed to send welcome email";
            $messageType = "error";
        }
    } elseif (isset($_POST['send_custom'])) {
        $subject = $_POST['email_subject'] ?? 'Custom Message';
        $content = $_POST['email_content'] ?? 'Hello!';
        
        if (sendEmailToLoggedInUser($subject, $content)) {
            $message = "Custom email sent successfully to " . $_SESSION['user']['email'];
            $messageType = "success";
        } else {
            $message = "Failed to send custom email";
            $messageType = "error";
        }
    }
}

include "header.php";
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4>Send Email to Logged-in User</h4>
                    <p class="mb-0">Current user: <strong><?= htmlspecialchars($_SESSION['user']['username']) ?></strong> 
                    (<?= htmlspecialchars($_SESSION['user']['email']) ?>)</p>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Send Welcome Email -->
                    <div class="mb-4">
                        <h5>Send Welcome Email</h5>
                        <p>Send a welcome email to the currently logged-in user.</p>
                        <form method="POST">
                            <button type="submit" name="send_welcome" class="btn btn-primary">
                                <i class="fas fa-envelope"></i> Send Welcome Email
                            </button>
                        </form>
                    </div>

                    <hr>

                    <!-- Send Custom Email -->
                    <div class="mb-4">
                        <h5>Send Custom Email</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email_subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="email_subject" name="email_subject" 
                                       value="Custom Message from AiKUN Furniture" required>
                            </div>
                            <div class="mb-3">
                                <label for="email_content" class="form-label">Message Content</label>
                                <textarea class="form-control" id="email_content" name="email_content" rows="6" required>
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
    <h2 style='color: #2c3e50;'>Hello <?= htmlspecialchars($_SESSION['user']['username']) ?>!</h2>
    <p>This is a custom message sent to your email address.</p>
    <p>You can customize this content as needed.</p>
    <br>
    <p>Best regards,<br>The AiKUN Furniture Team</p>
</div></textarea>
                            </div>
                            <button type="submit" name="send_custom" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Send Custom Email
                            </button>
                        </form>
                    </div>

                    <hr>

                    <!-- Email Functions Available -->
                    <div class="mb-4">
                        <h5>Available Email Functions</h5>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <strong>sendEmailToLoggedInUser($subject, $message)</strong> - Send any email to current user
                            </li>
                            <li class="list-group-item">
                                <strong>sendWelcomeEmail()</strong> - Send welcome email to current user
                            </li>
                            <li class="list-group-item">
                                <strong>sendOrderConfirmationEmail($orderId)</strong> - Send order confirmation to current user
                            </li>
                            <li class="list-group-item">
                                <strong>sendLowStockAlert()</strong> - Send stock alerts to current user
                            </li>
                        </ul>
                    </div>

                    <!-- PHP Code Examples -->
                    <div class="mb-4">
                        <h5>PHP Code Examples</h5>
                        <div class="alert alert-info">
                            <strong>Example 1: Send Welcome Email</strong>
                            <pre><code><?= htmlspecialchars('<?php
if (sendWelcomeEmail()) {
    echo "Welcome email sent!";
}
?>') ?></code></pre>
                        </div>
                        <div class="alert alert-info">
                            <strong>Example 2: Send Custom Email</strong>
                            <pre><code><?= htmlspecialchars('<?php
$subject = "Your Custom Subject";
$message = "<h2>Hello!</h2><p>Your message here</p>";
if (sendEmailToLoggedInUser($subject, $message)) {
    echo "Email sent successfully!";
}
?>') ?></code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
