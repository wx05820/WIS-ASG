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
