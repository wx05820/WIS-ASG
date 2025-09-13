/**
 * Enhanced User Dropdown JavaScript
 * Provides better accessibility, mobile support, and user experience
 */

class UserDropdown {
    constructor() {
        this.dropdowns = document.querySelectorAll('.user-dropdown');
        this.isOpen = false;
        this.currentDropdown = null;
        this.init();
    }

    init() {
        this.dropdowns.forEach(dropdown => {
            this.setupDropdown(dropdown);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-dropdown')) {
                this.closeAllDropdowns();
            }
        });

        // Close dropdown on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllDropdowns();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.closeAllDropdowns();
        });
    }

    setupDropdown(dropdown) {
        const button = dropdown.querySelector('.user-profile-btn');
        const content = dropdown.querySelector('.dropdown-content');
        const arrow = dropdown.querySelector('.dropdown-arrow');

        if (!button || !content) return;

        // Click handler
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleDropdown(dropdown);
        });

        // Keyboard navigation
        button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.toggleDropdown(dropdown);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.openDropdown(dropdown);
                this.focusFirstItem(content);
            }
        });

        // Focus management
        content.addEventListener('keydown', (e) => {
            this.handleDropdownKeydown(e, content);
        });

        // Prevent dropdown from closing when clicking inside
        content.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    toggleDropdown(dropdown) {
        if (this.currentDropdown === dropdown) {
            this.closeDropdown(dropdown);
        } else {
            this.closeAllDropdowns();
            this.openDropdown(dropdown);
        }
    }

    openDropdown(dropdown) {
        const button = dropdown.querySelector('.user-profile-btn');
        const content = dropdown.querySelector('.dropdown-content');
        const arrow = dropdown.querySelector('.dropdown-arrow');

        if (!content) return;

        // Close any other open dropdowns
        this.closeAllDropdowns();

        // Show dropdown
        content.style.display = 'block';
        content.setAttribute('aria-hidden', 'false');
        button.setAttribute('aria-expanded', 'true');

        // Add animation classes
        content.classList.add('dropdown-open');
        if (arrow) {
            arrow.style.transform = 'rotate(180deg)';
        }

        // Position dropdown
        this.positionDropdown(dropdown);

        // Update state
        this.isOpen = true;
        this.currentDropdown = dropdown;

        // Focus first item after animation
        setTimeout(() => {
            this.focusFirstItem(content);
        }, 100);
    }

    closeDropdown(dropdown) {
        const button = dropdown.querySelector('.user-profile-btn');
        const content = dropdown.querySelector('.dropdown-content');
        const arrow = dropdown.querySelector('.dropdown-arrow');

        if (!content) return;

        // Hide dropdown
        content.style.display = 'none';
        content.setAttribute('aria-hidden', 'true');
        button.setAttribute('aria-expanded', 'false');

        // Remove animation classes
        content.classList.remove('dropdown-open');
        if (arrow) {
            arrow.style.transform = 'rotate(0deg)';
        }

        // Update state
        this.isOpen = false;
        this.currentDropdown = null;
    }

    closeAllDropdowns() {
        this.dropdowns.forEach(dropdown => {
            this.closeDropdown(dropdown);
        });
    }

    positionDropdown(dropdown) {
        const content = dropdown.querySelector('.dropdown-content');
        const rect = dropdown.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const contentHeight = content.offsetHeight;

        // Reset positioning
        content.style.top = '';
        content.style.bottom = '';
        content.style.right = '0';
        content.style.left = 'auto';

        // Check if dropdown would go off screen
        const spaceBelow = viewportHeight - rect.bottom;
        const spaceAbove = rect.top;

        if (spaceBelow < contentHeight && spaceAbove > contentHeight) {
            // Position above
            content.style.bottom = '100%';
            content.style.top = 'auto';
            content.style.marginBottom = '8px';
            content.style.marginTop = '0';
        } else {
            // Position below (default)
            content.style.top = '100%';
            content.style.bottom = 'auto';
            content.style.marginTop = '8px';
            content.style.marginBottom = '0';
        }

        // Handle horizontal positioning on small screens
        if (rect.right > window.innerWidth - 20) {
            content.style.right = '0';
            content.style.left = 'auto';
        }
    }

    focusFirstItem(content) {
        const firstItem = content.querySelector('.dropdown-item');
        if (firstItem) {
            firstItem.focus();
        }
    }

    handleDropdownKeydown(e, content) {
        const items = Array.from(content.querySelectorAll('.dropdown-item'));
        const currentIndex = items.indexOf(document.activeElement);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                const nextIndex = (currentIndex + 1) % items.length;
                items[nextIndex].focus();
                break;

            case 'ArrowUp':
                e.preventDefault();
                const prevIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
                items[prevIndex].focus();
                break;

            case 'Home':
                e.preventDefault();
                items[0].focus();
                break;

            case 'End':
                e.preventDefault();
                items[items.length - 1].focus();
                break;

            case 'Escape':
                e.preventDefault();
                this.closeAllDropdowns();
                // Return focus to button
                const button = content.closest('.user-dropdown').querySelector('.user-profile-btn');
                if (button) button.focus();
                break;
        }
    }
}

// Enhanced dropdown item interactions
class DropdownItemEnhancer {
    constructor() {
        this.init();
    }

    init() {
        // Add click animations
        document.addEventListener('click', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (item) {
                this.animateClick(item);
            }
        });

        // Add hover effects for better feedback
        document.addEventListener('mouseenter', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (item) {
                this.addHoverEffect(item);
            }
        }, true);

        document.addEventListener('mouseleave', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (item) {
                this.removeHoverEffect(item);
            }
        }, true);
    }

    animateClick(item) {
        // Add ripple effect
        const ripple = document.createElement('span');
        ripple.classList.add('ripple-effect');
        
        const rect = item.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        
        item.style.position = 'relative';
        item.style.overflow = 'hidden';
        item.appendChild(ripple);
        
        // Remove ripple after animation
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }

    addHoverEffect(item) {
        item.style.transform = 'translateX(4px)';
        item.style.transition = 'transform 0.2s ease';
    }

    removeHoverEffect(item) {
        item.style.transform = 'translateX(0)';
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new UserDropdown();
    new DropdownItemEnhancer();
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    .dropdown-content {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform-origin: top right;
    }

    .dropdown-content.dropdown-open {
        animation: dropdownSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes dropdownSlideIn {
        from {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .dropdown-item {
        transition: all 0.2s ease;
        position: relative;
    }

    .dropdown-item:focus {
        outline: 2px solid #007bff;
        outline-offset: -2px;
        background-color: #f8f9fa;
    }

    .ripple-effect {
        position: absolute;
        border-radius: 50%;
        background: rgba(0, 123, 255, 0.3);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }

    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }

    /* Mobile improvements */
    @media (max-width: 768px) {
        .user-dropdown .dropdown-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 300px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .user-dropdown .dropdown-content.dropdown-open {
            animation: mobileDropdownFadeIn 0.3s ease;
        }

        @keyframes mobileDropdownFadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
    }

    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .dropdown-item:focus {
            outline: 3px solid;
            outline-offset: -3px;
        }
    }

    /* Reduced motion support */
    @media (prefers-reduced-motion: reduce) {
        .dropdown-content,
        .dropdown-item,
        .ripple-effect {
            transition: none;
            animation: none;
        }
    }
`;
document.head.appendChild(style);



