// Address Management JavaScript

class AddressManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupFormValidation();
        this.setupPhoneFormatting();
        this.setupAnimations();
    }

    setupFormValidation() {
        const form = document.getElementById('addAddressForm');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
            }
        });
    }

    validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
        });

        const phoneField = form.querySelector('input[name="phoneNo"]');
        if (phoneField && !this.validatePhone(phoneField.value)) {
            this.showFieldError(phoneField, 'Please enter a valid Malaysian phone number');
            isValid = false;
        }

        const postcodeField = form.querySelector('input[name="postcode"]');
        if (postcodeField && !this.validatePostcode(postcodeField.value)) {
            this.showFieldError(postcodeField, 'Postcode must be 5 digits');
            isValid = false;
        }

        return isValid;
    }

    validatePhone(phone) {
        const digits = phone.replace(/\D/g, '');
        return /^\d{9,12}$/.test(digits);
    }

    validatePostcode(postcode) {
        return /^\d{5}$/.test(postcode);
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: var(--error-color);
            font-size: 12px;
            margin-top: 4px;
            font-weight: 500;
        `;
        
        field.style.borderColor = 'var(--error-color)';
        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        field.style.borderColor = '';
    }

    setupPhoneFormatting() {
        const phoneField = document.querySelector('input[name="phoneNo"]');
        if (!phoneField) return;

        phoneField.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 0 && !value.startsWith('60')) {
                if (value.startsWith('0')) {
                    value = '60' + value.substring(1);
                } else if (!value.startsWith('60')) {
                    value = '60' + value;
                }
            }
            
            if (value.length > 2) {
                value = '+' + value.substring(0, 2) + ' ' + value.substring(2);
            }
            
            e.target.value = value;
        });
    }

    setupAnimations() {
        const cards = document.querySelectorAll('.address-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new AddressManager();
});

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
