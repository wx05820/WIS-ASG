<?php
require_once '../_base.php';

// Check if user is logged in as staff
if (!isLoggedInStaff()) {
    redirect('loginstaff.php');
}

// Check if user has permission (Admin, Supervisor, or SuperAdmin)
if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
    redirect('loginstaff.php');
}

$message = '';
$error = '';

// Handle form submission
if (is_post()) {
    try {
        $code = req('code');
        $description = req('description');
        $discount_type = req('discount_type');
        $value = req('value');
        $minOrderAmount = req('minOrderAmount');
        $maxDiscountAmount = req('maxDiscountAmount');
        $start_date = req('start_date');
        $end_date = req('end_date');
        $usage_limit = req('usage_limit');
        $is_active = req('is_active') ? 1 : 0;
        
        // Validation
        if (empty($code)) {
            throw new Exception('Voucher code is required');
        }
        
        if (empty($description)) {
            throw new Exception('Description is required');
        }
        
        if (empty($discount_type)) {
            throw new Exception('Discount type is required');
        }
        
        if (empty($value) || $value <= 0) {
            throw new Exception('Value must be greater than 0');
        }
        
        if (empty($minOrderAmount) || $minOrderAmount < 0) {
            throw new Exception('Minimum order amount must be 0 or greater');
        }
        
        if (empty($start_date)) {
            throw new Exception('Start date is required');
        }
        
        if (empty($end_date)) {
            throw new Exception('End date is required');
        }
        
        if ($start_date >= $end_date) {
            throw new Exception('End date must be after start date');
        }
        
        // Check if voucher code already exists
        $check_stmt = $_db->prepare('SELECT voucher_id FROM voucher WHERE code = ?');
        $check_stmt->execute([$code]);
        if ($check_stmt->fetch()) {
            throw new Exception('Voucher code already exists');
        }
        
        // Map discount type to our new structure
        $voucher_type = ($discount_type === 'Percentage') ? 'percentage' : 'fixed';
        
        // Insert voucher
        $stmt = $_db->prepare("
            INSERT INTO voucher (code, discount_type, value, start_date, end_date, is_active, usage_limit, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $code,
            $voucher_type,
            $value,
            $start_date,
            $end_date,
            $is_active,
            $usage_limit ?: 100,
            $description
        ]);
        
        $message = 'Voucher created successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Voucher - AiKUN Furniture</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/addvoucher.css">
    <?php include 'adminheader.php'; ?>
</head>
<body class="add-list-main">
    
    
        <div class="voucher-form">
            <h1><i class="fas fa-ticket-alt"></i> Add New Voucher</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="code">Voucher Code *</label>
                        <input type="text" id="code" name="code" class="form-control" 
                               value="<?php echo htmlspecialchars(req('code')); ?>" required>
                        <div class="help-text">e.g., SAVE10, WELCOME20</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="discount_type">Discount Type *</label>
                        <select id="discount_type" name="discount_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Percentage" <?php echo req('discount_type') === 'Percentage' ? 'selected' : ''; ?>>Percentage</option>
                            <option value="Fixed" <?php echo req('discount_type') === 'Fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                            <option value="Free Shipping" <?php echo req('discount_type') === 'Free Shipping' ? 'selected' : ''; ?>>Free Shipping</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="3" required><?php echo htmlspecialchars(req('description')); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="value">Value *</label>
                        <input type="number" id="value" name="value" class="form-control" 
                               step="0.01" min="0" value="<?php echo htmlspecialchars(req('value')); ?>" required>
                        <div class="help-text">Percentage (1-100) or Amount (RM)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="minOrderAmount">Minimum Order Amount (RM)</label>
                        <input type="number" id="minOrderAmount" name="minOrderAmount" class="form-control" 
                               step="0.01" min="0" value="<?php echo htmlspecialchars(req('minOrderAmount') ?: '0'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="maxDiscountAmount">Max Discount Amount (RM)</label>
                        <input type="number" id="maxDiscountAmount" name="maxDiscountAmount" class="form-control" 
                               step="0.01" min="0" value="<?php echo htmlspecialchars(req('maxDiscountAmount')); ?>">
                        <div class="help-text">Leave empty for no limit</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="usage_limit">Usage Limit</label>
                        <input type="number" id="usage_limit" name="usage_limit" class="form-control" 
                               min="1" value="<?php echo htmlspecialchars(req('usage_limit')); ?>">
                        <div class="help-text">Leave empty for unlimited</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars(req('start_date')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date *</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars(req('end_date')); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" 
                               <?php echo req('is_active') ? 'checked' : ''; ?>>
                        <label for="is_active">Active Voucher</label>
                    </div>
                    <div class="help-text">Uncheck to create an inactive voucher</div>
                </div>
                
                <div class="form-actions">
                    <a href="voucher_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Voucher List
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Voucher
                    </button>
                </div>
            </form>
        </div>
    
    
    <script>
        // Auto-generate voucher code suggestion
        document.getElementById('code').addEventListener('input', function() {
            let code = this.value.toUpperCase();
            // Remove spaces and special characters
            code = code.replace(/[^A-Z0-9]/g, '');
            this.value = code;
        });
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').setAttribute('min', today);
        document.getElementById('end_date').setAttribute('min', today);
        
        // Update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            if (startDate) {
                const nextDay = new Date(startDate);
                nextDay.setDate(nextDay.getDate() + 1);
                document.getElementById('end_date').setAttribute('min', nextDay.toISOString().split('T')[0]);
            }
        });
        
        // Validate discount type and value
        document.getElementById('discount_type').addEventListener('change', function() {
            const valueInput = document.getElementById('value');
            const helpText = valueInput.nextElementSibling;
            
            if (this.value === 'Percentage') {
                valueInput.setAttribute('max', '100');
                helpText.textContent = 'Percentage (1-100)';
            } else if (this.value === 'Fixed' || this.value === 'Free Shipping') {
                valueInput.removeAttribute('max');
                helpText.textContent = 'Amount (RM)';
            }
        });
    </script>
        <?php include '../footer.php'; ?>
</body>
</html>
