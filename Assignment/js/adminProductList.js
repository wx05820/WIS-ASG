document.addEventListener('DOMContentLoaded', function() {
    initializeProductList();
});

function toggleOrder() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentOrder = urlParams.get('order') || 'ASC';
    const newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
    urlParams.set('order', newOrder);
    urlParams.set('page', '1'); // Reset to first page
    window.location.href = '?' + urlParams.toString();
}

function initializeProductList() {
    // Initialize quick view modal if it exists
    const modal = document.getElementById('quickViewModal');
    if (modal) {
        const closeBtn = modal.querySelector('.close');
        if (closeBtn) {
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }
}

function updateSort(sortValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    urlParams.set('page', '1'); // Reset to first page
    window.location.href = '?' + urlParams.toString();
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    // Add to page
    document.body.appendChild(notification);
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function handleImageChange(event, prodID) {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('prodID', prodID);
    formData.append('image1', file);
    fetch('updateproductimage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.newImage) {
            // Update image src
            const img = document.querySelector('img[onclick*="' + prodID + '"]');
            if (img) img.src = data.newImage;
        }
        alert(data.message || (data.success ? 'Image updated!' : 'Failed to update image.'));
    })
    .catch(() => alert('Error uploading image.'));
}

function toggleProductSelect(item, event) {
    // Don't toggle if clicking on links or form elements
    if (event.target.tagName === 'A' || event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON') {
        return;
    }
            
    const checkbox = item.querySelector('input[type=checkbox]');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateBulkOperations();
        }
}
        
function updateBulkOperations() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    const count = checkboxes.length;
    const bulkPanel = document.getElementById('bulk-operations');
    const countSpan = document.getElementById('selected-count');
            
    if (count > 0) {
        bulkPanel.style.display = 'block';
        countSpan.textContent = count;
    } else {
        bulkPanel.style.display = 'none';
    }
}
        
function clearSelection() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
    updateBulkOperations();
}
        
function toggleNewCategoryInput() {
    const select = document.getElementById('category-select');
    const input = document.getElementById('new-category-input');
            
    if (select.value === 'new_category') {
        input.style.display = 'block';
        input.focus();
        select.name = 'existing_category';            
        input.name = 'new_category';
    } else {
        input.style.display = 'none';
        input.value = '';
        select.name = 'new_category';
        input.name = 'new_category_name';
    }
}
        
// Add event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', updateBulkOperations);
    });
});

function toggleProductSelect(item, event) {
    // Don't toggle if clicking on links or form elements
    if (event.target.tagName === 'A' || event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON') {
        return;
    }
            
    const checkbox = item.querySelector('input[type=checkbox]');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateBulkOperations();
        }
}
        
function updateBulkOperations() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    const count = checkboxes.length;
    const bulkPanel = document.getElementById('bulk-operations');
    const countSpan = document.getElementById('selected-count');
            
    if (count > 0) {
        bulkPanel.style.display = 'block';
        countSpan.textContent = count;
    } else {
        bulkPanel.style.display = 'none';
    }
}
        
function clearSelection() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
    updateBulkOperations();
}
        
function toggleNewCategoryInput() {
    const select = document.getElementById('category-select');
    const input = document.getElementById('new-category-input');
            
    if (select.value === 'new_category') {
        input.style.display = 'block';
        input.focus();
        // Keep the names as they are when creating new category
        select.name = 'new_category';
        input.name = 'new_category_name';
    } else {
        input.style.display = 'none';
        input.value = '';
        // Keep the names as they are for existing category
        select.name = 'existing_category';
        input.name = 'existing_category_name';
    }
}
        
// Add event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', updateBulkOperations);
    });
});

// Auto-hide success message after 2 seconds
setTimeout(function() {
    const messageDiv = document.getElementById('success-message');
    if (messageDiv) {
        messageDiv.style.opacity = '0';
        setTimeout(function() {
            messageDiv.style.display = 'none';
        }, 500); // Wait for fade out transition to complete
    }
}, 2000); // 2 seconds

/* Bulk operations and admin product list behavior moved from product/list.php */
// Small state to track whether bulk panel is open
let bulkPanelOpen = false;

function openBulkPanel() {
    const bulkPanel = document.getElementById('bulk-operations');
    const toggleBtn = document.getElementById('bulk-toggle-btn');
    if (!bulkPanel) return;
    bulkPanel.style.display = 'block';
    requestAnimationFrame(() => {
        bulkPanel.style.transform = 'translateY(0)';
        bulkPanel.style.opacity = '1';
    });
    bulkPanelOpen = true;
    if (toggleBtn) toggleBtn.innerHTML = '<span style="font-size:18px;">&times;</span>';
    if (toggleBtn) toggleBtn.title = 'Close bulk operations';
}

function closeBulkPanel() {
    const bulkPanel = document.getElementById('bulk-operations');
    const toggleBtn = document.getElementById('bulk-toggle-btn');
    if (!bulkPanel) return;
    bulkPanel.style.transform = 'translateY(-20px)';
    bulkPanel.style.opacity = '0';
    setTimeout(() => {
        if (!bulkPanelOpen) {
            bulkPanel.style.display = 'none';
        }
    }, 320);
    bulkPanelOpen = false;
    if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-sliders-h"></i>';
    if (toggleBtn) toggleBtn.title = 'Open bulk operations';
}

function toggleProductSelect(item, event) {
    if (event.target.tagName === 'A' || event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON') {
        return;
    }
    const checkbox = item.querySelector('input[type=checkbox]');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        updateBulkOperations();
    }
}

function updateBulkOperations() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    const count = checkboxes.length;
    const bulkPanel = document.getElementById('bulk-operations');
    const countSpan = document.getElementById('selected-count');
    const toggleBtn = document.getElementById('bulk-toggle-btn');

    if (count > 0) {
        if (toggleBtn) toggleBtn.style.display = 'block';
        countSpan.textContent = count;
        if (!bulkPanelOpen) {
            openBulkPanel();
        }
    } else {
        if (toggleBtn) toggleBtn.style.display = 'none';
        closeBulkPanel();
        countSpan.textContent = 0;
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
    updateBulkOperations();
}

function toggleNewCategoryInput() {
    const select = document.getElementById('category-select');
    const input = document.getElementById('new-category-input');

    if (select && input) {
        if (select.value === 'new_category') {
            input.style.display = 'block';
            input.focus();
        } else {
            input.style.display = 'none';
            input.value = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', updateBulkOperations);
    });

    const toggleBtn = document.getElementById('bulk-toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (bulkPanelOpen) {
                bulkPanelOpen = false;
                closeBulkPanel();
            } else {
                bulkPanelOpen = true;
                openBulkPanel();
            }
        });
    }
});

// Auto-hide success message after 2 seconds (idempotent)
setTimeout(function() {
    const messageDiv = document.getElementById('success-message');
    if (messageDiv) {
        messageDiv.style.opacity = '0';
        setTimeout(function() {
            messageDiv.style.display = 'none';
        }, 500);
    }
}, 2000);