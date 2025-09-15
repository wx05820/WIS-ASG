<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../_base.php';
require_once '../lib/priority_detector.php';
require_once '../lib/SimplePager.php';

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
    
    if ($action === 'process_refund') {
        $orderID = $_POST['orderID'];
        $refund_action = $_POST['refund_action']; // 'approve' or 'reject'
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        
        try {
            if ($refund_action === 'approve') {
                // Update order status to Refunded
                $stmt = $_db->prepare("UPDATE `order` SET status = 'Refunded' WHERE orderID = ?");
                $stmt->execute([$orderID]);
                
                // Update refund request
                $stmt = $_db->prepare("
                    UPDATE refund_requests 
                    SET status = 'approved', admin_notes = ?, processed_date = NOW(), processed_by = ?, priority = ?, updated_at = NOW()
                    WHERE orderID = ?
                ");
                $stmt->execute([$admin_notes, $_SESSION['staff_id'], $priority, $orderID]);
                
                $_SESSION['admin_success'] = "Refund approved for Order #$orderID";
                
            } elseif ($refund_action === 'reject') {
                // Update order status back to Delivered
                $stmt = $_db->prepare("UPDATE `order` SET status = 'Delivered' WHERE orderID = ?");
                $stmt->execute([$orderID]);
                
                // Update refund request
                $stmt = $_db->prepare("
                    UPDATE refund_requests 
                    SET status = 'rejected', admin_notes = ?, processed_date = NOW(), processed_by = ?, priority = ?, updated_at = NOW()
                    WHERE orderID = ?
                ");
                $stmt->execute([$admin_notes, $_SESSION['staff_id'], $priority, $orderID]);
                
                $_SESSION['admin_success'] = "Refund rejected for Order #$orderID";
            }
        } catch (Exception $e) {
            error_log("Refund processing error: " . $e->getMessage());
            $_SESSION['admin_error'] = 'Failed to process refund request.';
        }
    }
    
    redirect('contact_messages.php');
}

// Get filter parameters - separate for each tab
$contact_status_filter = $_GET['contact_status'] ?? 'all';
$contact_priority_filter = $_GET['contact_priority'] ?? 'all';
$contact_search = $_GET['contact_search'] ?? '';

$refund_status_filter = $_GET['refund_status'] ?? 'all';
$refund_priority_filter = $_GET['refund_priority'] ?? 'all';
$refund_search = $_GET['refund_search'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$active_tab = $_GET['tab'] ?? 'contact-messages';

// Build contact messages query
$contact_where_conditions = [];
$contact_params = [];

if ($contact_status_filter !== 'all') {
    $contact_where_conditions[] = "cm.status = ?";
    $contact_params[] = $contact_status_filter;
}

if ($contact_priority_filter !== 'all') {
    $contact_where_conditions[] = "cm.priority = ?";
    $contact_params[] = $contact_priority_filter;
}

if (!empty($contact_search)) {
    $contact_where_conditions[] = "(cm.name LIKE ? OR cm.email LIKE ? OR cm.subject LIKE ?)";
    $search_term = "%$contact_search%";
    $contact_params[] = $search_term;
    $contact_params[] = $search_term;
    $contact_params[] = $search_term;
}

$contact_where_clause = !empty($contact_where_conditions) ? 'WHERE ' . implode(' AND ', $contact_where_conditions) : '';

// Get contact messages with pagination
$contact_query = "
    SELECT 
        cm.*,
        u1.username as assigned_to_name,
        u2.username as reply_by_name,
        (SELECT COUNT(*) FROM contact_replies cr WHERE cr.message_id = cm.id) as reply_count
    FROM contact_messages cm
    LEFT JOIN user u1 ON cm.assigned_to = u1.userID
    LEFT JOIN user u2 ON cm.reply_by = u2.userID
    $contact_where_clause
    ORDER BY cm.created_at DESC
";

$contact_pager = new SimplePager($contact_query, $contact_params, 10, $page);
$messages = $contact_pager->result;

// Build refund requests query
$refund_where_conditions = [];
$refund_params = [];

if ($refund_status_filter !== 'all') {
    if ($refund_status_filter === 'pending') {
        $refund_where_conditions[] = "rr.status = 'pending'";
    } elseif ($refund_status_filter === 'approved') {
        $refund_where_conditions[] = "rr.status = 'approved'";
    } elseif ($refund_status_filter === 'rejected') {
        $refund_where_conditions[] = "rr.status = 'rejected'";
    }
}

if ($refund_priority_filter !== 'all') {
    $refund_where_conditions[] = "rr.priority = ?";
    $refund_params[] = $refund_priority_filter;
}

if (!empty($refund_search)) {
    $refund_where_conditions[] = "(o.orderID LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$refund_search%";
    $refund_params[] = $search_term;
    $refund_params[] = $search_term;
    $refund_params[] = $search_term;
}

$refund_where_clause = !empty($refund_where_conditions) ? 'WHERE ' . implode(' AND ', $refund_where_conditions) : '';

// Get refund requests with pagination - only show actual refund requests
$refund_query = "
    SELECT 
        o.orderID,
        o.orderDate,
        o.status,
        o.total,
        o.recipient_name,
        o.phoneNo,
        u.username,
        u.email,
        u.name as customer_name,
        rr.request_date,
        rr.reason,
        rr.admin_notes,
        rr.processed_date,
        rr.processed_by,
        rr.priority,
        rr.created_at,
        rr.updated_at
    FROM `order` o
    INNER JOIN refund_requests rr ON o.orderID = rr.orderID
    LEFT JOIN user u ON o.userID = u.userID
    $refund_where_clause
    ORDER BY 
        CASE rr.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 
        END,
        rr.request_date DESC
";

$refund_pager = new SimplePager($refund_query, $refund_params, 10, $page);
$refund_requests = $refund_pager->result;

// Debug: Check if messages are being fetched
if (empty($messages)) {
    error_log("No messages found in contact_messages table");
} else {
    error_log("Found " . count($messages) . " messages");
}

// Get staff users for assignment
$stmt = $_db->prepare("SELECT userID, username FROM user WHERE role = 'Admin' OR role = 'Supervisor' OR role = 'SuperAdmin' ORDER BY username");
$stmt->execute();
$staff_users = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
        .pagination-container {
            margin-top: 30px;
            text-align: center;
        }
        .pager {
            display: inline-flex;
            gap: 5px;
            align-items: center;
        }
        .pager a {
            padding: 8px 12px;
            background: #8B4513;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .pager a:hover {
            background: #A0522D;
        }
        .pager a.active {
            background: #654321;
            font-weight: bold;
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

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-btn <?= $active_tab === 'contact-messages' ? 'active' : '' ?>" onclick="showTab('contact-messages')" id="tab-contact-messages">
            <i class="fas fa-envelope"></i> Contact Messages (<?= $contact_pager->item_count ?>)
        </button>
        <button class="tab-btn <?= $active_tab === 'refund-requests' ? 'active' : '' ?>" onclick="showTab('refund-requests')" id="tab-refund-requests">
            <i class="fas fa-undo"></i> Refund Requests (<?= $refund_pager->item_count ?>)
        </button>
    </div>

    <!-- Filters for Contact Messages -->
    <div class="admin-filters" id="contact-filters">
        <form method="GET" class="filter-form">
            <input type="hidden" name="tab" value="contact-messages">
            <div class="filter-group">
                <label>Status:</label>
                <select name="contact_status">
                    <option value="all" <?= $contact_status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="new" <?= $contact_status_filter === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="in_progress" <?= $contact_status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="replied" <?= $contact_status_filter === 'replied' ? 'selected' : '' ?>>Replied</option>
                    <option value="closed" <?= $contact_status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Priority:</label>
                <select name="contact_priority">
                    <option value="all" <?= $contact_priority_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="low" <?= $contact_priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $contact_priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $contact_priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= $contact_priority_filter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Search:</label>
                <input type="text" name="contact_search" value="<?= htmlspecialchars($contact_search) ?>" placeholder="Name, email, or subject">
            </div>
            
            <button type="submit" class="btn btn-primary">Filter Messages</button>
        </form>
    </div>

    <!-- Filters for Refund Requests -->
    <div class="admin-filters" id="refund-filters" style="display: <?= $active_tab === 'refund-requests' ? 'block' : 'none' ?>;">
        <form method="GET" class="filter-form">
            <input type="hidden" name="tab" value="refund-requests">
            <div class="filter-group">
                <label>Status:</label>
                <select name="refund_status">
                    <option value="all" <?= $refund_status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $refund_status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $refund_status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $refund_status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Priority:</label>
                <select name="refund_priority">
                    <option value="all" <?= $refund_priority_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="low" <?= $refund_priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $refund_priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $refund_priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= $refund_priority_filter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Search:</label>
                <input type="text" name="refund_search" value="<?= htmlspecialchars($refund_search) ?>" placeholder="Order ID, username, or email">
            </div>
            
            <button type="submit" class="btn btn-primary">Filter Refunds</button>
        </form>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Contact Messages Tab -->
        <div id="contact-messages" class="tab-pane <?= $active_tab === 'contact-messages' ? 'active' : '' ?>">
            <div class="admin-content">
                <?php if (empty($messages)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <p>No contact messages found.</p>
                    </div>
                <?php else: ?>
                    <div class="messages-grid">
                        <?php foreach ($messages as $message): ?>
                            <div class="message-card" data-status="<?= $message['status'] ?>">
                                <div class="message-header">
                                    <div class="message-meta">
                                        <h3><?= htmlspecialchars($message['subject']) ?></h3>
                                        <p class="message-from">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($message['name']) ?> 
                                            <span class="email">(<?= htmlspecialchars($message['email']) ?>)</span>
                                        </p>
                                        <p class="message-time">
                                            <i class="fas fa-clock"></i>
                                            <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="message-status">
                                        <span class="status-badge status-<?= $message['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $message['status'])) ?>
                                        </span>
                                        <?php if ($message['status'] !== 'replied'): ?>
                                        <span class="priority-badge priority-<?= $message['priority'] ?>" 
                                              title="<?= PriorityDetector::getPriorityDescription($message['priority']) ?>">
                                            <i class="<?= PriorityDetector::getPriorityIcon($message['priority']) ?>"></i>
                                            <?= ucfirst($message['priority']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php 
                                        // Only show overdue badge for non-replied messages
                                        if ($message['status'] !== 'replied') {
                                            $age_hours = (time() - strtotime($message['created_at'])) / 3600;
                                            $response_target = PriorityDetector::getResponseTimeTarget($message['priority']);
                                            $is_overdue = false;
                                            
                                            // Check if overdue based on priority
                                            if ($message['priority'] === 'urgent' && $age_hours > 1) $is_overdue = true;
                                            elseif ($message['priority'] === 'high' && $age_hours > 4) $is_overdue = true;
                                            elseif ($message['priority'] === 'medium' && $age_hours > 24) $is_overdue = true;
                                            elseif ($message['priority'] === 'low' && $age_hours > 48) $is_overdue = true;
                                            
                                            if ($is_overdue): ?>
                                                <span class="overdue-badge" title="Overdue - Target: <?= $response_target ?>">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Overdue
                                                </span>
                                            <?php endif; 
                                        } ?>
                                    </div>
                                </div>
                                
                                <div class="message-content">
                                    <p><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                </div>
                                
                                <?php if ($message['phone']): ?>
                                    <div class="message-phone">
                                        <i class="fas fa-phone"></i>
                                        <?= htmlspecialchars($message['phone']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message-actions">
                                    <button class="btn btn-sm btn-primary" onclick="openReplyModal(<?= $message['id'] ?>)">
                                        <i class="fas fa-reply"></i> Reply
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="openStatusModal(<?= $message['id'] ?>, '<?= $message['status'] ?>', '<?= $message['priority'] ?>', <?= $message['assigned_to'] ?? 'null' ?>)">
                                        <i class="fas fa-edit"></i> Update Status
                                    </button>
                                    <?php if ($message['reply_count'] > 0): ?>
                                        <button class="btn btn-sm btn-info" onclick="viewReplies(<?= $message['id'] ?>)">
                                            <i class="fas fa-comments"></i> View Replies (<?= $message['reply_count'] ?>)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination for Contact Messages -->
                <div class="pagination-container">
                    <?php 
                    $pager_params = http_build_query([
                        'contact_status' => $contact_status_filter,
                        'contact_priority' => $contact_priority_filter,
                        'contact_search' => $contact_search,
                        'tab' => 'contact-messages'
                    ]);
                    $contact_pager->html($pager_params);
                    ?>
                </div>
            </div>
        </div>

        <!-- Refund Requests Tab -->
        <div id="refund-requests" class="tab-pane <?= $active_tab === 'refund-requests' ? 'active' : '' ?>">
            <div class="admin-content">
                <?php if (empty($refund_requests)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <p>No refund requests found.</p>
                    </div>
                <?php else: ?>
                    <div class="refund-requests-grid">
                        <?php foreach ($refund_requests as $refund): ?>
                            <div class="refund-card">
                                <div class="refund-header">
                                    <div class="refund-info">
                                        <h3>Order #<?= htmlspecialchars($refund['orderID']) ?></h3>
                                        <p class="refund-meta">
                                            <strong><?= htmlspecialchars($refund['customer_name'] ?: $refund['username']) ?></strong> 
                                            (<?= htmlspecialchars($refund['email']) ?>) 
                                            - <?= date('M j, Y', strtotime($refund['orderDate'])) ?>
                                        </p>
                                    </div>
                                    <div class="refund-status">
                                        <span class="status-badge status-<?= strtolower($refund['status']) ?>">
                                            <?php 
                                            if ($refund['status'] === 'Processing') {
                                                echo 'Pending';
                                            } elseif ($refund['status'] === 'cancelled') {
                                                echo 'Cancelled';
                                            } else {
                                                echo ucfirst($refund['status']);
                                            }
                                            ?>
                                        </span>
                                        <span class="priority-badge priority-<?= $refund['priority'] ?>" 
                                              title="<?= PriorityDetector::getPriorityDescription($refund['priority']) ?>">
                                            <i class="<?= PriorityDetector::getPriorityIcon($refund['priority']) ?>"></i>
                                            <?= ucfirst($refund['priority']) ?>
                                        </span>
                                        <span class="amount-badge">
                                            RM <?= number_format($refund['total'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="refund-content">
                                    <div class="refund-details">
                                        <p><strong>Order Date:</strong> <?= date('M j, Y H:i', strtotime($refund['orderDate'])) ?></p>
                                        <p><strong>Total Amount:</strong> RM <?= number_format($refund['total'], 2) ?></p>
                                        <p><strong>Customer:</strong> <?= htmlspecialchars($refund['customer_name'] ?: $refund['username']) ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($refund['email']) ?></p>
                                        <p><strong>Phone:</strong> <?= htmlspecialchars($refund['phoneNo']) ?></p>
                                        <?php if ($refund['reason']): ?>
                                            <p><strong>Reason:</strong> <?= htmlspecialchars($refund['reason']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($refund['admin_notes']): ?>
                                            <p><strong>Admin Notes:</strong> <?= htmlspecialchars($refund['admin_notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($refund['status'] === 'Processing'): ?>
                                    <div class="refund-actions">
                                        <form method="POST" class="refund-form">
                                            <input type="hidden" name="action" value="process_refund">
                                            <input type="hidden" name="orderID" value="<?= htmlspecialchars($refund['orderID']) ?>">
                                            
                                            <div class="form-group">
                                                <label>Priority:</label>
                                                <select name="priority" required>
                                                    <option value="low" <?= ($refund['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Low</option>
                                                    <option value="medium" <?= ($refund['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                                                    <option value="high" <?= ($refund['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>High</option>
                                                    <option value="urgent" <?= ($refund['priority'] ?? 'medium') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Admin Notes:</label>
                                                <textarea name="admin_notes" rows="3" placeholder="Add notes about this refund decision..."></textarea>
                                            </div>
                                            
                                            <div class="form-actions">
                                                <button type="submit" name="refund_action" value="approve" class="btn btn-success"
                                                        onclick="return confirm('Approve refund for Order #<?= htmlspecialchars($refund['orderID']) ?>?')">
                                                    <i class="fas fa-check"></i> Approve Refund
                                                </button>
                                                <button type="submit" name="refund_action" value="reject" class="btn btn-danger"
                                                        onclick="return confirm('Reject refund for Order #<?= htmlspecialchars($refund['orderID']) ?>?')">
                                                    <i class="fas fa-times"></i> Reject Refund
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php elseif ($refund['status'] === 'cancelled'): ?>
                                    <div class="refund-cancelled">
                                        <p><strong>Status:</strong> <span class="text-warning">Cancelled by Customer</span></p>
                                        <p><strong>Order Status:</strong> Delivered</p>
                                        <?php if ($refund['updated_at']): ?>
                                            <p><strong>Cancelled:</strong> <?= date('M j, Y H:i', strtotime($refund['updated_at'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="refund-processed">
                                        <p><strong>Status:</strong> <?= ucfirst($refund['status']) ?></p>
                                        <?php if ($refund['processed_date']): ?>
                                            <p><strong>Processed:</strong> <?= date('M j, Y H:i', strtotime($refund['processed_date'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination for Refund Requests -->
                <div class="pagination-container">
                    <?php 
                    $pager_params = http_build_query([
                        'refund_status' => $refund_status_filter,
                        'refund_priority' => $refund_priority_filter,
                        'refund_search' => $refund_search,
                        'tab' => 'refund-requests'
                    ]);
                    $refund_pager->html($pager_params);
                    ?>
                </div>
            </div>
        </div>
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
                        <option value="<?= $user['userID'] ?>"><?= htmlspecialchars($user['username']) ?></option>
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
    
    <script>
    // Tab functionality
    function showTab(tabName) {
        // Hide all tab panes
        const tabPanes = document.querySelectorAll('.tab-pane');
        tabPanes.forEach(pane => {
            pane.classList.remove('active');
        });
        
        // Remove active class from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show/hide appropriate filter forms
        const contactFilters = document.getElementById('contact-filters');
        const refundFilters = document.getElementById('refund-filters');
        
        if (tabName === 'contact-messages') {
            contactFilters.style.display = 'block';
            refundFilters.style.display = 'none';
        } else if (tabName === 'refund-requests') {
            contactFilters.style.display = 'none';
            refundFilters.style.display = 'block';
        }
        
        // Show selected tab pane
        const selectedPane = document.getElementById(tabName);
        if (selectedPane) {
            selectedPane.classList.add('active');
        }
        
        // Add active class to clicked button
        const selectedButton = document.getElementById('tab-' + tabName);
        if (selectedButton) {
            selectedButton.classList.add('active');
        }
    }
    
    // Initialize tabs on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Set active tab based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'contact-messages';
        showTab(activeTab);
    });
    </script>

    <?php require_once '../footer.php'; ?>

</body>
</html>






