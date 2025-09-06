<?php
require_once '../_base.php';

// Check if user is admin/staff
if (!isset($_SESSION['staff_id']) || !isLoggedInStaff()) {
    redirect('loginstaff.php');
    exit;
}

// Get message ID from URL
$message_id = (int)($_GET['id'] ?? 0);

if (!$message_id) {
    redirect('contact_messages.php');
    exit;
}

// Get the original message
$stmt = $_db->prepare("
    SELECT cm.*, u.name as customer_name, u.email as customer_email
    FROM contact_messages cm
    LEFT JOIN user u ON cm.email = u.email
    WHERE cm.id = ?
");
$stmt->execute([$message_id]);
$message = $stmt->fetch();

if (!$message) {
    redirect('contact_messages.php');
    exit;
}

// Get all replies for this message
$stmt = $_db->prepare("
    SELECT cr.*, u.username as reply_by_name, u.name as reply_by_full_name
    FROM contact_replies cr
    LEFT JOIN user u ON cr.reply_by = u.userID
    WHERE cr.message_id = ?
    ORDER BY cr.created_at ASC
");
$stmt->execute([$message_id]);
$replies = $stmt->fetchAll();

// Handle new reply submission
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'add_reply') {
    $reply_message = trim($_POST['reply_message'] ?? '');
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    
    if (!empty($reply_message)) {
        try {
            // Insert reply
            $stmt = $_db->prepare("
                INSERT INTO contact_replies (message_id, reply_by, reply_message, is_internal_note) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$message_id, $_SESSION['staff_id'], $reply_message, $is_internal]);
            
            // Update main message status
            $new_status = $is_internal ? 'in_progress' : 'replied';
            $stmt = $_db->prepare("
                UPDATE contact_messages 
                SET status = ?, replied_at = NOW(), reply_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $_SESSION['staff_id'], $message_id]);
            
            // Send email reply to customer (if not internal note)
            if (!$is_internal) {
                sendContactReplyEmail($message_id, $reply_message);
            }
            
            $_SESSION['admin_success'] = 'Reply added successfully.';
            redirect("contact_replies.php?id=$message_id");
            
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error adding reply. Please try again.';
        }
    }
}

require_once 'adminheader.php';
?>

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="../css/contact_messages.css">

<div class="admin-container">
    <div class="admin-header">
        <h1><i class="fas fa-comments"></i> Message Replies</h1>
        <div class="admin-actions">
            <a href="adminpage.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="contact_messages.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Messages
            </a>
        </div>
    </div>

    <!-- Original Message -->
    <div class="message-card" style="margin-bottom: 30px;">
        <div class="message-header">
            <div class="message-meta">
                <h3><?= htmlspecialchars($message->subject) ?></h3>
                <p class="message-from">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($message->name) ?> 
                    <span class="email">(<?= htmlspecialchars($message->email) ?>)</span>
                </p>
                <p class="message-time">
                    <i class="fas fa-clock"></i>
                    <?= date('M j, Y g:i A', strtotime($message->created_at)) ?>
                </p>
            </div>
            <div class="message-status">
                <span class="status-badge status-<?= $message->status ?>">
                    <?= ucfirst(str_replace('_', ' ', $message->status)) ?>
                </span>
                <span class="priority-badge priority-<?= $message->priority ?>">
                    <?= ucfirst($message->priority) ?>
                </span>
            </div>
        </div>
        
        <div class="message-content">
            <p><?= nl2br(htmlspecialchars($message->message)) ?></p>
        </div>
        
        <?php if ($message->phone): ?>
            <div class="message-phone">
                <i class="fas fa-phone"></i>
                <?= htmlspecialchars($message->phone) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['admin_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['admin_success']); unset($_SESSION['admin_success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?>
        </div>
    <?php endif; ?>

    <!-- Replies Section -->
    <div class="replies-section">
        <h2><i class="fas fa-comments"></i> Replies (<?= count($replies) ?>)</h2>
        
        <?php if (empty($replies)): ?>
            <div class="no-data">
                <i class="fas fa-comment-slash"></i>
                <p>No replies yet. Be the first to reply!</p>
            </div>
        <?php else: ?>
            <div class="replies-list">
                <?php foreach ($replies as $reply): ?>
                    <div class="reply-card <?= $reply->is_internal_note ? 'internal-reply' : 'customer-reply' ?>">
                        <div class="reply-header">
                            <div class="reply-meta">
                                <h4>
                                    <?php if ($reply->is_internal_note): ?>
                                        <i class="fas fa-eye-slash"></i> Internal Note
                                    <?php else: ?>
                                        <i class="fas fa-reply"></i> Reply to Customer
                                    <?php endif; ?>
                                </h4>
                                <p class="reply-author">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($reply->reply_by_full_name ?: $reply->reply_by_name) ?>
                                </p>
                                <p class="reply-time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('M j, Y g:i A', strtotime($reply->created_at)) ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="reply-content">
                            <p><?= nl2br(htmlspecialchars($reply->reply_message)) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Reply Form -->
    <div class="add-reply-section">
        <h3><i class="fas fa-plus"></i> Add Reply</h3>
        <form method="POST" class="reply-form">
            <input type="hidden" name="action" value="add_reply">
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_internal" id="is_internal">
                    <span class="checkmark"></span>
                    Internal Note (not sent to customer)
                </label>
            </div>
            
            <div class="form-group">
                <label for="reply_message">Reply Message:</label>
                <textarea name="reply_message" id="reply_message" rows="4" required placeholder="Type your reply here..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.replies-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.replies-section h2 {
    color: var(--wood-dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.replies-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.reply-card {
    background: var(--surface-muted);
    border-radius: 8px;
    padding: 20px;
    border-left: 4px solid var(--wood-light);
    transition: all 0.3s ease;
}

.reply-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 69, 19, 0.1);
}

.customer-reply {
    border-left-color: var(--wood-primary);
}

.internal-reply {
    border-left-color: #6c757d;
    background: #f8f9fa;
}

.reply-header {
    margin-bottom: 15px;
}

.reply-meta h4 {
    margin: 0 0 8px 0;
    color: var(--wood-dark);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-author, .reply-time {
    margin: 5px 0;
    color: #666;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-author i, .reply-time i {
    color: var(--wood-primary);
    width: 14px;
}

.reply-content {
    line-height: 1.6;
    color: #333;
}

.add-reply-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 25px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.add-reply-section h3 {
    color: var(--wood-dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.reply-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: var(--wood-dark);
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--wood-light);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    resize: vertical;
    min-height: 100px;
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--wood-primary);
    box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--wood-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--wood-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3);
}

.btn-secondary {
    background: var(--wood-light);
    color: var(--wood-dark);
    border: 2px solid var(--wood-primary);
}

.btn-secondary:hover {
    background: var(--wood-primary);
    color: white;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert i {
    font-size: 1.2rem;
}
</style>

    <script src="../js/contact.js"></script>

<?php require_once '../footer.php'; ?>


