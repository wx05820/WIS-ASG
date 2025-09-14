<?php

require_once __DIR__ . '/../_base.php';

/**
 * Auto-check stock levels after product operations
 * Call this function after updating product quantities
 */
function autoCheckStockLevels($updatedProductIds = [], $threshold = 5) {
    // Only run for admin users
    if (!isStaffAdmin() && !isStaffSupervisor() && !isStaffSuperAdmin()) {
        return false;
    }
    
    // If specific products were updated, check if any are now low stock
    if (!empty($updatedProductIds)) {
        global $_db;
        
        try {
            $placeholders = implode(',', array_fill(0, count($updatedProductIds), '?'));
            $stmt = $_db->prepare("
                SELECT COUNT(*) as low_stock_count 
                FROM product 
                WHERE prodID IN ($placeholders) AND qty <= ? AND qty >= 0 AND status != 'removed'
            ");
            $params = array_merge($updatedProductIds, [$threshold]);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If any updated products are now low stock, run full stock check
            if ($result['low_stock_count'] > 0) {
                // Run stock monitoring in background (don't send email automatically)
                runStockMonitoring($threshold, false);
                return true;
            }
        } catch (Exception $e) {
            error_log("Auto stock check error: " . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Get stock status for a specific product
 */
function getProductStockStatus($productId, $threshold = 5) {
    global $_db;
    
    try {
        $stmt = $_db->prepare("SELECT qty FROM product WHERE prodID = ? AND status != 'removed'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return 'not_found';
        }
        
        $qty = (int)$product['qty'];
        
        if ($qty === 0) {
            return 'out_of_stock';
        } elseif ($qty <= $threshold) {
            return 'low_stock';
        } else {
            return 'adequate';
        }
    } catch (Exception $e) {
        error_log("Product stock status check error: " . $e->getMessage());
        return 'error';
    }
}

/**
 * Get stock warning for display in admin interface
 */
function getStockWarningHtml($productId, $threshold = 5) {
    $status = getProductStockStatus($productId, $threshold);
    
    switch ($status) {
        case 'out_of_stock':
            return '<span class="stock out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>';
        case 'low_stock':
            return '<span class="stock low-stock"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>';
        case 'adequate':
            return '<span class="stock in-stock"><i class="fas fa-check-circle"></i> Adequate Stock</span>';
        default:
            return '';
    }
}
?>
