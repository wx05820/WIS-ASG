document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.form-input');
    
    const errorSounds = [
        new Audio('/images/errorAudio.mp3')
    ];
    
    errorSounds.forEach(sound => sound.volume = 0.6);
    
    let audioContext = null;
    let isAudioInitialized = false;

    function initializeAudio() {
        if (!isAudioInitialized) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                isAudioInitialized = true;
            } catch (e) {
            }
        }
    }

    document.addEventListener('click', initializeAudio, { once: true });
    document.addEventListener('keydown', initializeAudio, { once: true });
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                this.classList.remove('error');
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
            }
        });
    });

    const rememberMeCheckbox = document.getElementById('remember_me');
    if (rememberMeCheckbox) {
        rememberMeCheckbox.addEventListener('change', function() {
            const container = this.closest('.checkbox-container');
            if (this.checked) {
                container.style.backgroundColor = 'var(--gray-100)';
                container.style.border = '1px solid var(--wood-medium)';
                container.style.boxShadow = '0 0 0 2px rgba(139, 69, 19, 0.1)';
            } else {
                container.style.backgroundColor = '';
                container.style.border = '';
                container.style.boxShadow = '';
            }
        });
    }

    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('password-strength-bar');
    
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            let width = 0;
            let color = '#ef4444';
            
            if (strength <= 1) {
                width = 25;
                color = '#ef4444';
            } else if (strength <= 3) {
                width = 50;
                color = '#f59e0b';
            } else if (strength <= 4) {
                width = 75;
                color = '#10b981';
            } else {
                width = 100;
                color = '#10b981';
            }
            
            strengthBar.style.width = width + '%';
            strengthBar.style.background = color;
        });
    }

    function playErrorSound() {
        if (!isAudioInitialized) {
            initializeAudio();
        }

        const sound = errorSounds[0];
        sound.currentTime = 0;
        
        const playPromise = sound.play();
        
        if (playPromise !== undefined) {
            playPromise.then(() => {
            }).catch(error => {
                playWebAudioErrorSound();
            });
        } else {
            playWebAudioErrorSound();
        }
    }

    function playWebAudioErrorSound() {
        if (!audioContext) {
            return;
        }

        try {
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.15);
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.3);
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0, audioContext.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
            
        } catch (fallbackError) {
        }
    }

    function showErrorWithSound(element, message) {
        element.classList.add('error');
        
        let errorMsg = element.parentNode.querySelector('.error-message');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'error-message';
            element.parentNode.appendChild(errorMsg);
        }
        
        errorMsg.textContent = message;
        errorMsg.style.display = 'block';
        playErrorSound();
        
        element.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }

    function validateFormWithSound(formElement) {
        let hasErrors = false;
        let errorCount = 0;
        
        const existingErrors = formElement.querySelectorAll('.error-message');
        existingErrors.forEach(error => error.style.display = 'none');
        
        const existingErrorInputs = formElement.querySelectorAll('.form-input.error');
        existingErrorInputs.forEach(input => input.classList.remove('error'));
        
        const requiredInputs = formElement.querySelectorAll('[required]');
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                showErrorWithSound(input, 'This field is required');
                hasErrors = true;
                errorCount++;
            }
        });
        
        const emailInputs = formElement.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            if (input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                showErrorWithSound(input, 'Please enter a valid email address');
                hasErrors = true;
                errorCount++;
            }
        });
        
        const passwordField = formElement.querySelector('#password');
        // Password length validation removed for login - users can login with any length password
        
        const confirmField = formElement.querySelector('#confirm');
        if (confirmField && passwordField) {
            if (confirmField.value !== passwordField.value) {
                showErrorWithSound(confirmField, 'Passwords do not match');
                hasErrors = true;
                errorCount++;
            }
        }
        
        const nameField = formElement.querySelector('#name');
        if (nameField && nameField.value) {
            if (nameField.value.length > 100) {
                showErrorWithSound(nameField, 'Name cannot exceed 100 characters');
                hasErrors = true;
                errorCount++;
            }
        }
        
        return !hasErrors;
    }

    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            
            if (!isAudioInitialized) {
                initializeAudio();
            }
            
            const isValid = validateFormWithSound(this);
            
            if (!isValid) {
                event.preventDefault();
                
                const firstError = this.querySelector('.form-input.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                
                return false;
            }
            
        });
        
        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                
                if (!isAudioInitialized) {
                    initializeAudio();
                }
                
                setTimeout(() => {
                    const isValid = validateFormWithSound(form);
                    if (!isValid) {
                        event.preventDefault();
                        
                        const firstError = form.querySelector('.form-input.error');
                        if (firstError) {
                            firstError.focus();
                        }
                    }
                }, 10);
            });
        });
    });

    function checkServerErrors() {
        const serverErrorInputs = document.querySelectorAll('.form-input.error');
        const serverErrorMessages = document.querySelectorAll('.error-message');
        const alertErrors = document.querySelectorAll('.alert-error');
        
        if (serverErrorInputs.length > 0 || serverErrorMessages.length > 0 || alertErrors.length > 0) {
            
            const playServerErrorSound = () => {
                playErrorSound();
                document.removeEventListener('click', playServerErrorSound);
                document.removeEventListener('keydown', playServerErrorSound);
                document.removeEventListener('touchstart', playServerErrorSound);
            };

            document.addEventListener('click', playServerErrorSound);
            document.addEventListener('keydown', playServerErrorSound);
            document.addEventListener('touchstart', playServerErrorSound);
        }
    }
    checkServerErrors();

    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList && node.classList.contains('error-message')) {
                            playErrorSound();
                        }
                        const errorMessages = node.querySelectorAll && node.querySelectorAll('.error-message');
                        if (errorMessages && errorMessages.length > 0) {
                            playErrorSound();
                        }
                    }
                });
            } else if (mutation.type === 'attributes') {
                if (mutation.attributeName === 'class' && 
                    mutation.target.classList.contains('error') &&
                    mutation.target.classList.contains('form-input')) {
                    playErrorSound();
                }
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
    });

    window.validateFormWithSound = validateFormWithSound;
    window.playErrorSound = playErrorSound;
    
});

        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = passwordField.parentNode.querySelector('.password-toggle');
            const eyeIcon = toggleButton.querySelector('.eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('show', 'fas', 'fa-eye');
                eyeIcon.classList.add('hide', 'fas', 'fa-eye-slash');
                eyeIcon.classList.add('state-change');
                setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('hide', 'fas', 'fa-eye-slash');
                eyeIcon.classList.add('show', 'fas', 'fa-eye');
                eyeIcon.classList.add('state-change');
                setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
            }
        }

        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('.btn-primary');
            const normalContent = submitBtn.querySelector('span');
            const loadingContent = submitBtn.querySelector('.btn-loading');
            const formContainer = document.querySelector('.login-container');
            
            // Show loading state immediately
            normalContent.style.display = 'none';
            loadingContent.style.display = 'flex';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            formContainer.classList.add('loading');
            
            // Set a timeout to prevent indefinite loading (5 seconds)
            const loadingTimeout = setTimeout(() => {
                // Reset button state if still loading
                if (submitBtn.disabled) {
                    normalContent.style.display = 'inline';
                    loadingContent.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    formContainer.classList.remove('loading');
                    
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-error';
                    errorDiv.innerHTML = '⏱️ Login is taking longer than expected. Please check your connection and try again.';
                    formContainer.insertBefore(errorDiv, document.querySelector('form'));
                    
                    // Add retry button
                    const retryBtn = document.createElement('button');
                    retryBtn.type = 'button';
                    retryBtn.className = 'btn btn-secondary';
                    retryBtn.innerHTML = '🔄 Retry Login';
                    retryBtn.style.marginTop = '10px';
                    retryBtn.onclick = () => {
                        errorDiv.remove();
                        retryBtn.remove();
                        formContainer.classList.remove('loading');
                    };
                    errorDiv.appendChild(retryBtn);
                    
                    // Remove error after 8 seconds
                    setTimeout(() => {
                        if (errorDiv.parentNode) {
                            errorDiv.parentNode.removeChild(errorDiv);
                        }
                    }, 8000);
                }
            }, 5000); // 5 second timeout
            
            // Clear timeout if page unloads or form resets
            window.addEventListener('beforeunload', () => clearTimeout(loadingTimeout));
            
            // Store timeout ID for potential clearing
            submitBtn.dataset.loadingTimeout = loadingTimeout;
        });

        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('login_input');
            const passwordField = document.getElementById('password');
            
            // Auto-focus on empty field
            if (!emailField.value) {
                emailField.focus();
            } else if (!passwordField.value) {
                passwordField.focus();
            }
            
            // Add real-time validation feedback
            if (emailField) {
                emailField.addEventListener('blur', function() {
                    const value = this.value.trim();
                    if (value && !isValidLoginInput(value)) {
                        this.classList.add('error');
                        showErrorWithSound(this, 'Please enter a valid email or username');
                    }
                });
            }
            
            // Add visual feedback for form submission
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Add a subtle form shake if there are validation errors
                    const hasErrors = this.querySelector('.form-input.error');
                    if (hasErrors) {
                        this.style.animation = 'shake 0.5s ease-in-out';
                        setTimeout(() => {
                            this.style.animation = '';
                        }, 500);
                    }
                });
            }
        });
        
        // Helper function to validate login input
        function isValidLoginInput(input) {
            // Check if it's an email
            if (input.includes('@')) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
            }
            // Check if it's a valid username (alphanumeric, underscore, dash, 3-20 chars)
            return /^[a-zA-Z0-9_-]{3,20}$/.test(input);
        }

        document.querySelectorAll('.otp-input').forEach((input, index, inputs) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                }
            });
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && e.target.value === '') {
            if (index > 0) {
                inputs[index - 1].focus();
            }
        }
    });
    
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasteData = e.clipboardData.getData('text/plain');
        if (/^\d{6}$/.test(pasteData)) {
            pasteData.split('').forEach((char, i) => {
                if (inputs[i]) {
                    inputs[i].value = char;
                }
            });
            inputs[inputs.length - 1].focus();
        }
    });
});

if (document.getElementById('timer')) {
    const expiryTime = new Date(
        document.getElementById('timer').dataset.expiry
    ).getTime();

    const countdown = setInterval(function () {
        const now = new Date().getTime();
        const distance = expiryTime - now;

        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById('timer').innerHTML = minutes + "m " + seconds + "s ";

        if (distance < 0) {
            clearInterval(countdown);
            document.getElementById('timer').innerHTML = "EXPIRED";
            const btn = document.querySelector('.btn-primary');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = "Code Expired";
            }
        }
    }, 1000);
}

if (document.getElementById('password') && document.getElementById('confirm_password')) {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');

    const requirements = {
        length: document.getElementById('length'),
        uppercase: document.getElementById('uppercase'),
        lowercase: document.getElementById('lowercase'),
        number: document.getElementById('number'),
        special: document.getElementById('special'),
        match: document.getElementById('match'),
    };

    function checkPasswordRequirements() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;

        const lengthValid = password.length >= 8 && password.length <= 128;
        const uppercaseValid = /[A-Z]/.test(password);
        const lowercaseValid = /[a-z]/.test(password);
        const numberValid = /[0-9]/.test(password);
        const specialValid = /[^A-Za-z0-9]/.test(password);
        const matchValid = password === confirm && password !== '';

        updateRequirement(requirements.length, lengthValid);
        updateRequirement(requirements.uppercase, uppercaseValid);
        updateRequirement(requirements.lowercase, lowercaseValid);
        updateRequirement(requirements.number, numberValid);
        updateRequirement(requirements.special, specialValid);
        updateRequirement(requirements.match, matchValid);

        let strength = 0;
        if (lengthValid) strength++;
        if (uppercaseValid) strength++;
        if (lowercaseValid) strength++;
        if (numberValid) strength++;
        if (specialValid) strength++;

        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        if (strengthBar && strengthText) {
            const colors = ["red", "orange", "yellow", "lightgreen", "green", "darkgreen"];
            const texts = ["Very Weak", "Weak", "Fair", "Good", "Strong", "Very Strong"];

            strengthBar.style.width = (strength * 16.67) + "%";
            strengthBar.style.background = colors[strength];
            strengthText.textContent = texts[strength];
        }
    }

    function updateRequirement(element, isValid) {
        if (!element) return;
        if (isValid) {
            element.classList.remove('invalid');
            element.classList.add('valid');
            element.textContent = '✅ ' + element.textContent.replace(/✅ |❌ /, '');
        } else {
            element.classList.remove('valid');
            element.classList.add('invalid');
            element.textContent = '❌ ' + element.textContent.replace(/✅ |❌ /, '');
        }
    }

    window.togglePassword = function (fieldId) {
        const passwordField = document.getElementById(fieldId);
        if (!passwordField) return;

        const toggleButton = passwordField.parentNode.querySelector('.password-toggle');
        const eyeIcon = toggleButton.querySelector('.eye-icon');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            eyeIcon.classList.remove('show', 'fas', 'fa-eye');
            eyeIcon.classList.add('hide', 'fas', 'fa-eye-slash');
            eyeIcon.classList.add('state-change');
            setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
        } else {
            passwordField.type = 'password';
            eyeIcon.classList.remove('hide', 'fas', 'fa-eye-slash');
            eyeIcon.classList.add('show', 'fas', 'fa-eye');
            eyeIcon.classList.add('state-change');
            setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
        }
    }

    passwordInput.addEventListener('input', checkPasswordRequirements);
    confirmInput.addEventListener('input', checkPasswordRequirements);
}

function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = passwordField.parentNode.querySelector('.password-toggle');
            const eyeIcon = toggleButton.querySelector('.eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('show', 'fas', 'fa-eye');
                eyeIcon.classList.add('hide', 'fas', 'fa-eye-slash');
                eyeIcon.classList.add('state-change');
                setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('hide', 'fas', 'fa-eye-slash');
                eyeIcon.classList.add('show', 'fas', 'fa-eye');
                eyeIcon.classList.add('state-change');
                setTimeout(() => eyeIcon.classList.remove('state-change'), 300);
            }
        }

        document.getElementById('login_input').addEventListener('input', function() {
            const input = this.value.trim();
            const isEmail = input.includes('@');
            const placeholder = isEmail ? 'Enter your email' : 'Enter your username';
        });
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.classList.contains('error')) {
                        this.classList.remove('error');
                        const errorMsg = this.parentNode.querySelector('.error-message');
                        if (errorMsg) errorMsg.style.display = 'none';
                    }
                });
            });
        });