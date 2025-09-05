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
            // Ignore if clicking interactive elements
            if (e.target.closest('a') || e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
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

// Selection behavior: clicking row selects (sets true)
function toggleProductSelect(item, event) {
    if (event && (event.target.closest('a') || event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON')) return;
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
