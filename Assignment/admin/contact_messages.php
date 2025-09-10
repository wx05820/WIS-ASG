<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../_base.php';
require_once '../lib/priority_detector.php';

// Check if user is admin/staff
if (!isset($_SESSION['staff_id']) || !isLoggedInStaff()) {
    redirect('loginstaff.php');
    exit;
}

if (is_post()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $message_id = (int)$_POST['message_id'];
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
        
        $stmt = $_db->prepare("
            UPDATE contact_messages 
            SET status = ?, priority = ?, assigned_to = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$status, $priority, $assigned_to, $message_id]);
        
        $_SESSION['admin_success'] = 'Message status updated successfully.';
    }
    
    if ($action === 'reply') {
        $message_id = (int)$_POST['message_id'];
        $reply_message = trim($_POST['reply_message']);
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        
        if (!empty($reply_message)) {
            $stmt = $_db->prepare("
                INSERT INTO contact_replies (message_id, reply_by, reply_message, is_internal_note) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$message_id, $_SESSION['staff_id'], $reply_message, $is_internal]);
            
            $new_status = $is_internal ? 'in_progress' : 'replied';
            $stmt = $_db->prepare("
                UPDATE contact_messages 
                SET status = ?, replied_at = NOW(), reply_by = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $_SESSION['staff_id'], $message_id]);
            
            if (!$is_internal) {
                sendContactReplyEmail($message_id, $reply_message);
            }
            
            $_SESSION['admin_success'] = 'Reply sent successfully.';
        }
    }
    
    redirect('contact_messages.php');
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "cm.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "cm.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(cm.name LIKE ? OR cm.email LIKE ? OR cm.subject LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get contact messages
$stmt = $_db->prepare("
    SELECT 
        cm.*,
        u1.username as assigned_to_name,
        u2.username as reply_by_name,
        (SELECT COUNT(*) FROM contact_replies cr WHERE cr.message_id = cm.id) as reply_count
    FROM contact_messages cm
    LEFT JOIN user u1 ON cm.assigned_to = u1.userID
    LEFT JOIN user u2 ON cm.reply_by = u2.userID
    $where_clause
    ORDER BY cm.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Debug: Check if messages are being fetched
if (empty($messages)) {
    error_log("No messages found in contact_messages table");
} else {
    error_log("Found " . count($messages) . " messages");
}

// Get staff users for assignment
$stmt = $_db->prepare("SELECT userID, username FROM user WHERE role = 'Admin' OR role = 'Supervisor' OR role = 'SuperAdmin' ORDER BY username");
$stmt->execute();
$staff_users = $stmt->fetchAll();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/userlist.css">
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="../css/contact_messages.css">
    <style>
        /* Fallback styles in case CSS doesn't load */
        .admin-container {
            font-family: Arial, sans-serif;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .message-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #8B4513;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
</style>
</head>
<body class="product-list-main" style="margin-top:0; padding-top:0;">
    <?php include 'adminheader.php'; ?>

<div class="container">
<div class="admin-container">
    <div class="admin-header">
        <h1><i class="fas fa-envelope"></i> Contact Messages</h1>
        <div class="admin-actions">
            <a href="adminpage.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="admin-filters">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Status:</label>
                <select name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="replied" <?= $status_filter === 'replied' ? 'selected' : '' ?>>Replied</option>
                    <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Priority:</label>
                <select name="priority">
                    <option value="all" <?= $priority_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= $priority_filter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Search:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email, or subject">
            </div>
            
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>

    <!-- Messages List -->
    <div class="admin-content">
        <?php if (empty($messages)): ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <p>No contact messages found.</p>
            </div>
        <?php else: ?>
            <div class="messages-grid">
                <?php foreach ($messages as $message): ?>
                    <div class="message-card" data-status="<?= $message->status ?>">
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
                                <span class="priority-badge priority-<?= $message->priority ?>" 
                                      title="<?= PriorityDetector::getPriorityDescription($message->priority) ?>">
                                    <i class="<?= PriorityDetector::getPriorityIcon($message->priority) ?>"></i>
                                    <?= ucfirst($message->priority) ?>
                                </span>
                                <?php 
                                $age_hours = (time() - strtotime($message->created_at)) / 3600;
                                $response_target = PriorityDetector::getResponseTimeTarget($message->priority);
                                $is_overdue = false;
                                
                                // Check if overdue based on priority
                                if ($message->priority === 'urgent' && $age_hours > 1) $is_overdue = true;
                                elseif ($message->priority === 'high' && $age_hours > 4) $is_overdue = true;
                                elseif ($message->priority === 'medium' && $age_hours > 24) $is_overdue = true;
                                elseif ($message->priority === 'low' && $age_hours > 48) $is_overdue = true;
                                
                                if ($is_overdue): ?>
                                    <span class="overdue-badge" title="Overdue - Target: <?= $response_target ?>">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Overdue
                                    </span>
                                <?php endif; ?>
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
                        
                        <div class="message-actions">
                            <button class="btn btn-sm btn-primary" onclick="openReplyModal(<?= $message->id ?>)">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="openStatusModal(<?= $message->id ?>, '<?= $message->status ?>', '<?= $message->priority ?>', <?= $message->assigned_to ?? 'null' ?>)">
                                <i class="fas fa-edit"></i> Update Status
                            </button>
                            <?php if ($message->reply_count > 0): ?>
                                <button class="btn btn-sm btn-info" onclick="viewReplies(<?= $message->id ?>)">
                                    <i class="fas fa-comments"></i> View Replies (<?= $message->reply_count ?>)
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reply to Message</h3>
            <span class="close" onclick="closeReplyModal()">&times;</span>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="message_id" id="reply_message_id">
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_internal" id="is_internal">
                    Internal Note (not sent to customer)
                </label>
            </div>
            
            <div class="form-group">
                <label for="reply_message">Reply Message:</label>
                <textarea name="reply_message" id="reply_message" rows="6" required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeReplyModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Reply</button>
            </div>
        </form>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Message Status</h3>
            <span class="close" onclick="closeStatusModal()">&times;</span>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="message_id" id="status_message_id">
            
            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" required>
                    <option value="new">New</option>
                    <option value="in_progress">In Progress</option>
                    <option value="replied">Replied</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="priority">Priority:</label>
                <select name="priority" id="priority" required>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="assigned_to">Assign to:</label>
                <select name="assigned_to" id="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($staff_users as $user): ?>
                        <option value="<?= $user->userID ?>"><?= htmlspecialchars($user->username) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>
</div>

    <script src="../js/contact.js"></script>

    <?php require_once '../footer.php'; ?>

</body>
</html>






