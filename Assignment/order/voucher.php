<?php
require_once '../_base.php';

$user_id = $_SESSION['user_id'] ?? null;
checkLogin();

// Get available vouchers for user
function getAvailableVouchers($user_id, $subtotal = 0) {
    global $_db;
    
    $current_date = date('Y-m-d H:i:s');
    
    try {
        // Get all active vouchers that this user can potentially use
        $stmt = $_db->prepare("
            SELECT 
                v.voucher_id,
                v.code,
                v.description,
                v.discount_type,
                v.value,
                v.minOrderAmount,
                v.maxDiscountAmount,
                v.start_date,
                v.end_date,
                v.usage_limit,
                v.current_usage,
                v.is_active,
                -- Check if user has already used this voucher
                CASE 
                    WHEN vu.id IS NOT NULL THEN 'used'
                    WHEN v.end_date < ? THEN 'expired'
                    WHEN v.start_date > ? THEN 'not_started'
                    WHEN COALESCE(v.usage_limit, 0) > 0 AND v.current_usage >= v.usage_limit THEN 'limit_reached'
                    WHEN v.is_active = 0 THEN 'inactive'
                    ELSE 'available'
                END as status,
                -- Calculate if user meets minimum order requirement
                CASE WHEN ? >= v.minOrderAmount THEN 1 ELSE 0 END as meets_min_order
            FROM voucher v
            LEFT JOIN voucher_user vu ON v.voucher_id = vu.voucher_id AND vu.user_id = ?
            WHERE v.is_active = 1
            ORDER BY 
                CASE 
                    WHEN v.end_date < ? THEN 3
                    WHEN vu.id IS NOT NULL THEN 2
                    WHEN COALESCE(v.usage_limit, 0) > 0 AND v.current_usage >= v.usage_limit THEN 2
                    WHEN ? < v.minOrderAmount THEN 1
                    ELSE 0
                END,
                v.end_date ASC,
                v.value DESC
        ");
        
        $stmt->execute([
            $current_date, // for expired check
            $current_date, // for not started check
            $subtotal,     // for minimum order check
            $user_id,      // for user usage check
            $current_date, // for ordering (expired)
            $subtotal      // for ordering (min order)
        ]);
        
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process vouchers to add additional information
        $processed_vouchers = [];
        foreach ($vouchers as $voucher) {
            // Convert numeric strings to proper types
            $voucher['value'] = floatval($voucher['value']);
            $voucher['minOrderAmount'] = floatval($voucher['minOrderAmount']);
            $voucher['maxDiscountAmount'] = floatval($voucher['maxDiscountAmount']);
            $voucher['usage_limit'] = intval($voucher['usage_limit']);
            $voucher['current_usage'] = intval($voucher['current_usage']);
            $voucher['meets_min_order'] = intval($voucher['meets_min_order']);
            
            // Calculate potential discount for this voucher
            if ($voucher['status'] === 'available' && $voucher['meets_min_order']) {
                if ($voucher['discount_type'] === 'percentage') {
                    $discount = ($subtotal * $voucher['value']) / 100;
                    $voucher['potential_discount'] = min($discount, $voucher['maxDiscountAmount']);
                } else {
                    $voucher['potential_discount'] = min($voucher['value'], $subtotal);
                }
            } else {
                $voucher['potential_discount'] = 0;
            }
            
            // Add reason why voucher is not available (for better UX)
            $voucher['unavailable_reason'] = '';
            if ($voucher['status'] !== 'available') {
                switch ($voucher['status']) {
                    case 'used':
                        $voucher['unavailable_reason'] = 'You have already used this voucher';
                        break;
                    case 'expired':
                        $voucher['unavailable_reason'] = 'This voucher has expired';
                        break;
                    case 'not_started':
                        $voucher['unavailable_reason'] = 'This voucher is not yet active';
                        break;
                    case 'limit_reached':
                        $voucher['unavailable_reason'] = 'This voucher has reached its usage limit';
                        break;
                    case 'inactive':
                        $voucher['unavailable_reason'] = 'This voucher is currently inactive';
                        break;
                }
            } elseif (!$voucher['meets_min_order']) {
                $voucher['unavailable_reason'] = "Minimum order amount of RM " . number_format($voucher['minOrderAmount'], 2) . " required";
            }
            
            $processed_vouchers[] = $voucher;
        }
        
        return $processed_vouchers;
        
    } catch (Exception $e) {
        error_log("Error getting vouchers: " . $e->getMessage());
        return [];
    }
}

// Validate voucher
function validateVoucherUser($user_id, $voucher_code, $subtotal) {
    global $_db;
    
    $current_date = date('Y-m-d H:i:s');
    
    try {
        if (empty($voucher_code) || $subtotal <= 0) {
            return [
                'valid' => false,
                'error' => 'Invalid voucher code or order amount'
            ];
        }

        // Get voucher details and validate
        $stmt = $_db->prepare("
            SELECT 
                v.voucher_id,
                v.code,
                v.description,
                v.discount_type,
                v.value,
                v.minOrderAmount,
                v.maxDiscountAmount,
                v.start_date,
                v.end_date,
                v.usage_limit,
                v.current_usage,
                v.is_active,
                vu.id as user_usage_id
            FROM voucher v
            LEFT JOIN voucher_user vu ON v.voucher_id = vu.voucher_id AND vu.user_id = ?
            WHERE v.code = ? AND v.is_active = 1
            LIMIT 1
        ");
        
        $stmt->execute([$user_id, $voucher_code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            return [
                'valid' => false,
                'error' => 'Invalid voucher code or voucher is not active'
            ];
        }

        // Convert to proper types
        $voucher['value'] = floatval($voucher['value']);
        $voucher['minOrderAmount'] = floatval($voucher['minOrderAmount']);
        $voucher['maxDiscountAmount'] = floatval($voucher['maxDiscountAmount']);
        $voucher['usage_limit'] = intval($voucher['usage_limit']);
        $voucher['current_usage'] = intval($voucher['current_usage']);

        // Validation checks
        $validation_errors = [];

        if ($voucher['user_usage_id']) {
            $validation_errors[] = 'You have already used this voucher';
        }

        if (strtotime($voucher['start_date']) > strtotime($current_date)) {
            $validation_errors[] = 'This voucher is not yet active';
        }

        if (strtotime($voucher['end_date']) < strtotime($current_date)) {
            $validation_errors[] = 'This voucher has expired';
        }

        if (intval($voucher['usage_limit']) > 0 && intval($voucher['current_usage']) >= intval($voucher['usage_limit'])) {
            $validation_errors[] = 'This voucher has reached its usage limit';
        }

        if ($subtotal < $voucher['minOrderAmount']) {
            $validation_errors[] = 'Minimum order amount of RM ' . number_format($voucher['minOrderAmount'], 2) . ' required';
        }

        if (!empty($validation_errors)) {
            return [
                'valid' => false,
                'error' => implode('. ', $validation_errors),
                'voucher' => [
                    'code' => $voucher['code'],
                    'description' => $voucher['description'],
                    'minOrderAmount' => $voucher['minOrderAmount'],
                    'end_date' => $voucher['end_date']
                ]
            ];
        }

        // Calculate discount
        $discount = 0;
        if ($voucher['discount_type'] === 'percentage') {
            $discount = ($subtotal * $voucher['value']) / 100;
            if ($voucher['maxDiscountAmount'] > 0) {
                $discount = min($discount, $voucher['maxDiscountAmount']);
            }
        } else {
            $discount = $voucher['value'];
        }
        
        $discount = min($discount, $subtotal);

        return [
            'valid' => true,
            'voucher' => [
                'voucher_id' => $voucher['voucher_id'],
                'code' => $voucher['code'],
                'description' => $voucher['description'],
                'discount_type' => $voucher['discount_type'],
                'value' => $voucher['value'],
                'minOrderAmount' => $voucher['minOrderAmount'],
                'maxDiscountAmount' => $voucher['maxDiscountAmount'],
                'end_date' => $voucher['end_date']
            ],
            'discount' => round($discount, 2),
            'new_total' => round($subtotal - $discount, 2)
        ];

    } catch (Exception $e) {
        error_log("Error validating voucher: " . $e->getMessage());
        return [
            'valid' => false,
            'error' => 'Failed to validate voucher'
        ];
    }
}

// Get user's voucher usage statistics
function getUserVoucherStats($user_id) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("
            SELECT 
                COUNT(*) as total_used,
                COALESCE(SUM(discount_applied), 0) as total_saved
            FROM voucher_user 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_used' => intval($stats['total_used']),
            'total_saved' => floatval($stats['total_saved'])
        ];
        
    } catch (Exception $e) {
        error_log("Error getting user voucher stats: " . $e->getMessage());
        return [
            'total_used' => 0,
            'total_saved' => 0
        ];
    }
}

// Record voucher usage
function recordVoucherUsage($voucher_id, $user_id, $order_id, $discount_applied) {
    global $_db;
    
    try {
        $_db->beginTransaction();
        
        // Insert voucher usage record
        $stmt = $_db->prepare("INSERT INTO voucher_user (voucher_id, user_id, order_id, discount_applied, used_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$voucher_id, $user_id, $order_id, $discount_applied]);
        
        // Update voucher current usage
        $stmt = $_db->prepare("UPDATE voucher SET current_usage = current_usage + 1 WHERE voucher_id = ?");
        $stmt->execute([$voucher_id]);
        
        $_db->commit();
        return true;
        
    } catch (Exception $e) {
        $_db->rollBack();
        error_log("Error recording voucher usage: " . $e->getMessage());
        return false;
    }
}

// Get voucher by ID
function getVoucherById($voucher_id) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("SELECT * FROM voucher WHERE voucher_id = ? AND is_active = 1");
        $stmt->execute([$voucher_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting voucher by ID: " . $e->getMessage());
        return false;
    }
}

// Get voucher by code
function getVoucherByCode($code) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("SELECT * FROM voucher WHERE code = ? AND is_active = 1");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting voucher by code: " . $e->getMessage());
        return false;
    }
}
?>