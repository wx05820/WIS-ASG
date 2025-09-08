// Main JavaScript for AiKUN Furniture Website

document.addEventListener('DOMContentLoaded', function() {
    console.log('AiKUN Furniture website loaded');
    
    // Initialize all interactive elements
    initializeShippingDropdown();
    initializeAIChat();
    initializeMobileMenu();
});

// Initialize shipping dropdown functionality
function initializeShippingDropdown() {
    console.log('=== INITIALIZING SHIPPING DROPDOWN ===');
    
    const shippingIcon = document.querySelector('.shipping-icon');
    const shippingDropdown = document.querySelector('.shipping-dropdown');
    const dropdownContent = document.querySelector('.shipping-dropdown .dropdown-content');
    
    console.log('Shipping icon found:', !!shippingIcon);
    console.log('Shipping dropdown found:', !!shippingDropdown);
    console.log('Dropdown content found:', !!dropdownContent);
    
    if (shippingIcon && shippingDropdown) {
        console.log('Setting up shipping dropdown...');
        
        // Add click event listener
        shippingIcon.addEventListener('click', handleShippingClick);
        
        console.log('Shipping dropdown initialized successfully');
    } else {
        console.log('Shipping dropdown elements not found');
    }
}

// Handle shipping dropdown click
function handleShippingClick(e) {
    e.preventDefault();
    e.stopPropagation();
    
    console.log('=== SHIPPING DROPDOWN CLICKED ===');
    
    const dropdown = e.target.closest('.shipping-dropdown');
    if (!dropdown) {
        console.log('Dropdown container not found');
        return;
    }
    
    const content = dropdown.querySelector('.dropdown-content');
    if (!content) {
        console.log('Dropdown content not found');
        return;
    }
    
    const isActive = dropdown.classList.contains('active');
    
    // Close all other dropdowns first
    document.querySelectorAll('.shipping-dropdown').forEach(d => {
        d.classList.remove('active');
        const c = d.querySelector('.dropdown-content');
        if (c) {
            c.style.display = 'none';
            c.style.opacity = '0';
            c.style.visibility = 'hidden';
        }
    });
    
    if (!isActive) {
        // Show this dropdown
        dropdown.classList.add('active');
        content.style.display = 'block';
        content.style.opacity = '1';
        content.style.visibility = 'visible';
        content.style.position = 'absolute';
        content.style.top = '100%';
        content.style.right = '0';
        content.style.zIndex = '9999';
        content.style.background = 'white';
        content.style.border = '1px solid #ccc';
        content.style.borderRadius = '8px';
        content.style.padding = '10px';
        content.style.minWidth = '200px';
        content.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        content.style.marginTop = '5px';
        
        console.log('Shipping dropdown opened');
    } else {
        console.log('Shipping dropdown closed');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.shipping-dropdown')) {
        document.querySelectorAll('.shipping-dropdown').forEach(dropdown => {
            dropdown.classList.remove('active');
            const content = dropdown.querySelector('.dropdown-content');
            if (content) {
                content.style.display = 'none';
                content.style.opacity = '0';
                content.style.visibility = 'hidden';
            }
        });
    }
});

// Keep dropdown open when clicking inside
document.addEventListener('click', function(e) {
    if (e.target.closest('#shipping-dropdown-content')) {
        e.stopPropagation();
    }
});

// Initialize AI Chat functionality
function initializeAIChat() {
    const aiChatIcon = document.querySelector('.ai-chat-icon');
    if (aiChatIcon) {
        aiChatIcon.addEventListener('click', function() {
            console.log('AI Chat clicked');
            // Add AI chat functionality here
        });
    }
}

// Initialize mobile menu functionality
function initializeMobileMenu() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
        });
    }
}

// Toggle order sorting
function toggleOrder() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentOrder = urlParams.get('order') || 'ASC';
    const newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
    
    urlParams.set('order', newOrder);
    window.location.search = urlParams.toString();
}

// Test function for shipping dropdown (for debugging)
function testShippingDropdown() {
    console.log('=== TESTING SHIPPING DROPDOWN ===');
    
    const shippingIcon = document.querySelector('.shipping-icon');
    const shippingDropdown = document.querySelector('.shipping-dropdown');
    const dropdownContent = document.querySelector('.shipping-dropdown .dropdown-content');
    
    console.log('Elements found:');
    console.log('- Shipping icon:', !!shippingIcon);
    console.log('- Shipping dropdown:', !!shippingDropdown);
    console.log('- Dropdown content:', !!dropdownContent);
    
    if (shippingDropdown && dropdownContent) {
        // Force show the dropdown
        shippingDropdown.classList.add('active');
        dropdownContent.style.display = 'block';
        dropdownContent.style.opacity = '1';
        dropdownContent.style.visibility = 'visible';
        dropdownContent.style.position = 'absolute';
        dropdownContent.style.top = '100%';
        dropdownContent.style.right = '0';
        dropdownContent.style.zIndex = '9999';
        dropdownContent.style.background = 'white';
        dropdownContent.style.border = '3px solid red';
        dropdownContent.style.padding = '10px';
        dropdownContent.style.minWidth = '200px';
        dropdownContent.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        dropdownContent.style.marginTop = '5px';
        
        console.log('Dropdown forced to show');
    } else {
        console.log('Cannot force show dropdown - elements not found');
    }
}

// Make test function available globally
window.testShippingDropdown = testShippingDropdown;