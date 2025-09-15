// Category delete confirmation - Global function
function confirmDelete(categoryName, isUsed, productCount) {
    if (isUsed) {
        alert('Cannot delete category "' + categoryName + '" because it is being used by ' + productCount + ' product(s).\n\nTo delete this category, first move or remove all products using this category.');
        return false;
    }
    return confirm('Are you sure you want to delete the category "' + categoryName + '"?\n\nThis category is not currently being used by any products.');
}

// Category edit functions - Global functions
function startEdit(catID) {
    const nameDiv = document.getElementById('category-name-' + catID);
    const editDiv = document.getElementById('category-edit-' + catID);
    if (nameDiv && editDiv) {
        nameDiv.style.display = 'none';
        editDiv.style.display = 'block';
    }
}

function cancelEdit(catID) {
    const nameDiv = document.getElementById('category-name-' + catID);
    const editDiv = document.getElementById('category-edit-' + catID);
    if (nameDiv && editDiv) {
        nameDiv.style.display = 'block';
        editDiv.style.display = 'none';
    }
}

// Function to show and auto-hide success message - Global function
function showSuccessMessage() {
    setTimeout(function() {
        var messageDiv = document.getElementById('success-message');
        if (messageDiv) {
            messageDiv.style.opacity = '0';
            setTimeout(function() {
                messageDiv.style.display = 'none';
            }, 500); // Wait for fade out animation
        }
    }, 2000);
}

// Centralized admin product list behavior
document.addEventListener('DOMContentLoaded', function() {
    initializeProductList();

    // Wire checkbox change handlers
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const item = checkbox.closest('.product-list-item');
            if (item) {
                if (checkbox.checked) item.classList.add('selected'); else item.classList.remove('selected');
            }
            updateBulkOperations();
        });
    });

    // Click on row selects the product
    const productItems = document.querySelectorAll('.product-list-item');
    productItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            // Allow product name links and edit links to work normally
            if (e.target.closest('.product-name a') || e.target.closest('.product-actions a')) {
                return; // Let the link work normally
            }
            
            // Ignore other interactive elements but not product name links
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
            
            // animate
            item.classList.remove('product-clicked');
            void item.offsetWidth;
            item.classList.add('product-clicked');
            // select
            toggleProductSelect(item, e);
        });
    });

    // Bulk toggle button
    const toggleBtn = document.getElementById('bulk-toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (bulkPanelOpen) { bulkPanelOpen = false; closeBulkPanel(); } else { bulkPanelOpen = true; openBulkPanel(); }
        });
    }

    // Ensure product name links work properly
    const productNameLinks = document.querySelectorAll('.product-name a');
    productNameLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row selection when clicking product name
        });
    });

    // Ensure edit action links work properly  
    const editLinks = document.querySelectorAll('.product-actions a');
    editLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row selection when clicking edit
        });
    });
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
    // Quick view modal
    const modal = document.getElementById('quickViewModal');
    if (modal) {
        const closeBtn = modal.querySelector('.close');
        if (closeBtn) closeBtn.onclick = function() { modal.style.display = 'none'; };
        window.onclick = function(event) { if (event.target == modal) modal.style.display = 'none'; };
    }

    // Wire clear button for product list search
    try {
        const prodSearchInput = document.getElementById('search-query-input');
        const prodClearBtn = document.getElementById('clear-search-btn');
        if (prodSearchInput && prodClearBtn) {
            prodClearBtn.style.display = prodSearchInput.value.trim() ? 'inline-block' : 'none';
            prodSearchInput.addEventListener('input', function() { prodClearBtn.style.display = prodSearchInput.value.trim() ? 'inline-block' : 'none'; });
            prodClearBtn.addEventListener('click', function() { prodSearchInput.value = ''; prodSearchInput.focus(); prodClearBtn.style.display = 'none'; });
        }
    } catch (e) { /* silent */ }
}

function updateSort(sortValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    urlParams.set('page', '1');
    window.location.href = '?' + urlParams.toString();
}

function onSortChange(selectEl) {
    var p = new URLSearchParams(window.location.search);
    if (selectEl.name && selectEl.value) p.set(selectEl.name, selectEl.value);
    var existingOrder = p.get('order');
    if (existingOrder) p.set('order', existingOrder.toUpperCase() === 'ASC' ? 'ASC' : 'DESC');
    p.set('page', '1');
    window.location.href = '?' + p.toString();
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => { notification.classList.remove('show'); setTimeout(() => notification.remove(), 300); }, 3000);
}

function handleImageChange(event, prodID) {
    const file = event.target.files[0]; if (!file) return;
    const formData = new FormData(); formData.append('prodID', prodID); formData.append('image1', file);
    fetch('updateproductimage.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => { if (data.success && data.newImage) { const img = document.querySelector('img[onclick*="' + prodID + '"]'); if (img) img.src = data.newImage; } alert(data.message || (data.success ? 'Image updated!' : 'Failed to update image.')); })
        .catch(() => alert('Error uploading image.'));
}

// Selection behavior: clicking row toggles selection
function toggleProductSelect(item, event) {
    // Allow product name links and edit links to work normally
    if (event && (event.target.closest('.product-name a') || event.target.closest('.product-actions a'))) {
        return; // Let the link work normally
    }
    
    // Ignore other interactive elements
    if (event && (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON')) return;
    
    const checkbox = item.querySelector('input[type=checkbox]');
    if (!checkbox) return;
    // Toggle selection: unselect if already selected, select if not
    if (checkbox.checked) {
        checkbox.checked = false;
        item.classList.remove('selected');
    } else {
        checkbox.checked = true;
        item.classList.add('selected');
    }
    updateBulkOperations();
}

function updateBulkOperations() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    const count = checkboxes.length;
    const bulkPanel = document.getElementById('bulk-operations');
    const countSpan = document.getElementById('selected-count');
    const toggleBtn = document.getElementById('bulk-toggle-btn');
    if (count > 0) {
        // Show toggle button and update count, but do not auto-open the bulk panel.
        if (toggleBtn) toggleBtn.style.display = 'block';
        countSpan.textContent = count;
    } else {
        // No selections: hide toggle and ensure panel is closed
        if (toggleBtn) toggleBtn.style.display = 'none';
        closeBulkPanel();
        countSpan.textContent = 0;
    }
}

function clearSelection() { document.querySelectorAll('input[name="selected_products[]"]').forEach(cb => cb.checked = false); document.querySelectorAll('.product-list-item.selected').forEach(i => i.classList.remove('selected')); updateBulkOperations(); }

function toggleNewCategoryInput() {
    const select = document.getElementById('category-select');
    const input = document.getElementById('new-category-input');
    if (!select || !input) return;
    // Always keep the select named 'new_category' so the server receives the chosen catID
    select.name = 'new_category';
    input.name = 'new_category_name';
    if (select.value === 'new_category') {
        input.style.display = 'block';
        input.focus();
    } else {
        input.style.display = 'none';
        input.value = '';
    }
}

// Bulk panel helpers
let bulkPanelOpen = false;
function openBulkPanel() { const bulkPanel = document.getElementById('bulk-operations'); const toggleBtn = document.getElementById('bulk-toggle-btn'); if (!bulkPanel) return; bulkPanel.style.display = 'block'; requestAnimationFrame(() => { bulkPanel.style.transform = 'translateY(0)'; bulkPanel.style.opacity = '1'; }); bulkPanelOpen = true; if (toggleBtn) { toggleBtn.innerHTML = '<span style="font-size:18px;">&times;</span>'; toggleBtn.title = 'Close bulk operations'; } }
function closeBulkPanel() { const bulkPanel = document.getElementById('bulk-operations'); const toggleBtn = document.getElementById('bulk-toggle-btn'); if (!bulkPanel) return; bulkPanel.style.transform = 'translateY(-20px)'; bulkPanel.style.opacity = '0'; setTimeout(() => { if (!bulkPanelOpen) bulkPanel.style.display = 'none'; }, 320); bulkPanelOpen = false; if (toggleBtn) { toggleBtn.innerHTML = '<i class="fas fa-sliders-h"></i>'; toggleBtn.title = 'Open bulk operations'; } }

// Auto-hide success message
setTimeout(function() { const messageDiv = document.getElementById('success-message'); if (messageDiv) { messageDiv.style.opacity = '0'; setTimeout(() => { messageDiv.style.display = 'none'; }, 500); } }, 2000);

// Auto-hide form messages
function hideFormMessage() {
    setTimeout(function() {
        var msg = document.getElementById('form-message');
        if (msg) { msg.style.display = 'none'; }
    }, 2000);
}

// Add product form category handling
function initializeAddProductForm() {
    const catSelect = document.getElementById('catID');
    const newCatDiv = document.getElementById('new-category-div');
    const newCatInput = document.getElementById('newCategory');
    const addProductForm = document.querySelector('.addproduct-form');

    if (catSelect && newCatDiv && newCatInput && addProductForm) {
        catSelect.addEventListener('change', function() {
            if (this.value === 'new') {
                newCatDiv.style.display = 'block';
                newCatInput.setAttribute('required', 'required');
            } else {
                newCatDiv.style.display = 'none';
                newCatInput.removeAttribute('required');
            }
        });

        addProductForm.addEventListener('submit', function(e) {
            if (catSelect.value === 'new' && newCatInput.value.trim() === '') {
                alert('Please enter a new category name.');
                newCatInput.focus();
                e.preventDefault();
            }
        });
    }
}

// Initialize form features when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAddProductForm();
    hideFormMessage();
    initializeCategoryModal();
});

// Category management modal functionality
function initializeCategoryModal() {
    const manageCategoriesBtn = document.getElementById('manageCategoriesBtn');
    const categoryModal = document.getElementById('categoryModal');
    const closeModal = document.getElementById('closeModal');

    if (manageCategoriesBtn && categoryModal && closeModal) {
        manageCategoriesBtn.onclick = function() {
            categoryModal.style.display = 'block';
        }

        closeModal.onclick = function() {
            categoryModal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == categoryModal) {
                categoryModal.style.display = 'none';
            }
        }
    }
}
