<?php
require_once '../_base.php';
require_once '../lib/priority_detector.php';

if (!is_post()) {
    redirect('../headerInfo/contact.php');
    exit;
}

$errors = [];

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$newsletter = isset($_POST['newsletter']) ? 1 : 0;
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validateCSRFToken($csrf_token)) {
    $errors[] = 'Invalid security token. Please try again.';
}

if (empty($name)) {
    $errors[] = 'Name is required.';
} elseif (strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters.';
} elseif (strlen($name) > 100) {
    $errors[] = 'Name must be less than 100 characters.';
}

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!is_email($email)) {
    $errors[] = 'Only Gmail, Outlook, Yahoo, and Hotmail email addresses are allowed.';
} elseif (strlen($email) > 255) {
    $errors[] = 'Email address is too long.';
}

if (empty($subject)) {
    $errors[] = 'Subject is required.';
} elseif (strlen($subject) < 5) {
    $errors[] = 'Subject must be at least 5 characters.';
} elseif (strlen($subject) > 255) {
    $errors[] = 'Subject must be less than 255 characters.';
}

if (empty($message)) {
    $errors[] = 'Message is required.';
} elseif (strlen($message) < 5) {
    $errors[] = 'Message must be at least 5 characters.';
} elseif (strlen($message) > 2000) {
    $errors[] = 'Message must be less than 2000 characters.';
}


if (!empty($phone)) {
    $phone_digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
        $errors[] = 'Phone number must be between 10-15 digits.';
    }
}

// Check for spam patterns
$spam_patterns = [
    '/\b(viagra|cialis|casino|poker|lottery|winner|free money|click here)\b/i',
    '/\b(bit\.ly|tinyurl|short\.link)\b/i',
    '/\b(urgent|asap|immediately|act now)\b/i'
];

foreach ($spam_patterns as $pattern) {
    if (preg_match($pattern, $message) || preg_match($pattern, $subject)) {
        $errors[] = 'Your message contains content that appears to be spam.';
        break;
    }
}

// Rate limiting - check if same email has submitted recently
if (empty($errors)) {
    try {
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $stmt = $_db->prepare("
            SELECT COUNT(*) 
            FROM contact_messages 
            WHERE email = ? AND created_at > ?
        ");
        $stmt->execute([$email, $one_hour_ago]);
        $recent_submissions = $stmt->fetchColumn();
        
        if ($recent_submissions >= 3) {
            $errors[] = 'You have submitted too many messages recently. Please wait before submitting again.';
        }
    } catch (Exception $e) {
    }
}

if (!empty($errors)) {
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_form_data'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'subject' => $subject,
        'message' => $message,
        'newsletter' => $newsletter
    ];
    redirect('../headerInfo/contact.php');
    exit;
}

try {
    $detected_priority = PriorityDetector::detectPriority($subject, $message, $email);
    
    $stmt = $_db->prepare("
        INSERT INTO contact_messages 
        (name, email, phone, subject, message, newsletter_subscribed, status, priority, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'new', ?, NOW())
    ");
    
    $result = $stmt->execute([
        $name,
        $email,
        $phone ?: null,
        $subject,
        $message,
        $newsletter,
        $detected_priority
    ]);
    
    if ($result) {
        $message_id = $_db->lastInsertId();
        
        try {
            $mail = get_mail();
            $mail->addAddress('info@aikunfurniture.com', 'AiKUN Furniture Admin');
            $mail->Subject = 'New Contact Message: ' . $subject;
            
            $mail->Body = "
                <h2>New Contact Message Received</h2>
                <p><strong>From:</strong> " . htmlspecialchars($name) . " (" . htmlspecialchars($email) . ")</p>
                <p><strong>Phone:</strong> " . htmlspecialchars($phone ?: 'Not provided') . "</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p><strong>Message:</strong></p>
                <div style='background: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #8B4513;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                <p><strong>Newsletter Subscription:</strong> " . ($newsletter ? 'Yes' : 'No') . "</p>
                <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
                <hr>
                <p><a href='" . base('contact_messages.php') . "'>View in Admin Panel</a></p>
            ";
            
            $mail->isHTML(true);
            $mail->send();
            
        } catch (Exception $e) {
        }
        
        $_SESSION['contact_success'] = 'Thank you for your message! We will get back to you soon.';
        unset($_SESSION['contact_form_data']);
        
    } else {
        throw new Exception('Failed to insert contact message');
    }
    
} catch (Exception $e) {
    $_SESSION['contact_error'] = 'Sorry, there was an error submitting your message. Please try again.';
}
redirect('../headerInfo/contact.php');
exit;
?>


