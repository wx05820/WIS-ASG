document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.form-input');
    
    // Create multiple error sounds for variety
    const errorSounds = [
        new Audio('images/errorAudio.mp3')
    ];
    
    // Set volume for all sounds
    errorSounds.forEach(sound => sound.volume = 0.6);
    
    // Audio context for Web Audio API fallback
    let audioContext = null;
    let isAudioInitialized = false;

    // Initialize audio context on first interaction
    function initializeAudio() {
        if (!isAudioInitialized) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                isAudioInitialized = true;
                console.log('Audio context initialized');
            } catch (e) {
                console.warn('Web Audio API not supported');
            }
        }
    }

    // Initialize audio on any user interaction
    document.addEventListener('click', initializeAudio, { once: true });
    document.addEventListener('keydown', initializeAudio, { once: true });
    
    // Clear errors when user starts typing
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

    // Password strength meter
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

    // Enhanced error sound function with multiple fallbacks
    function playErrorSound() {
        // Initialize audio context if needed
        if (!isAudioInitialized) {
            initializeAudio();
        }

        // Try to play HTML5 audio first
        const sound = errorSounds[0];
        sound.currentTime = 0;
        
        const playPromise = sound.play();
        
        if (playPromise !== undefined) {
            playPromise.then(() => {
                console.log('Error sound played successfully');
            }).catch(error => {
                console.warn('HTML5 audio failed, trying Web Audio API fallback:', error);
                playWebAudioErrorSound();
            });
        } else {
            // Fallback for older browsers
            playWebAudioErrorSound();
        }
    }

    // Web Audio API fallback for better browser support
    function playWebAudioErrorSound() {
        if (!audioContext) {
            console.warn('No audio context available');
            return;
        }

        try {
            // Resume audio context if suspended
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            // Create error beep sound
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            // Create a double beep error sound
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.15);
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.3);
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0, audioContext.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
            
            console.log('Web Audio API error sound played');
        } catch (fallbackError) {
            console.warn('Web Audio API also failed:', fallbackError);
        }
    }

    // Enhanced function to show error and play sound immediately
    function showErrorWithSound(element, message) {
        // Add error class with animation
        element.classList.add('error');
        
        // Create or update error message
        let errorMsg = element.parentNode.querySelector('.error-message');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'error-message';
            element.parentNode.appendChild(errorMsg);
        }
        
        errorMsg.textContent = message;
        errorMsg.style.display = 'block';
        
        // Play error sound immediately
        playErrorSound();
        
        // Add visual shake effect
        element.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }

    // Comprehensive form validation with immediate sound feedback
    function validateFormWithSound(formElement) {
        let hasErrors = false;
        let errorCount = 0;
        
        // Clear all existing errors first
        const existingErrors = formElement.querySelectorAll('.error-message');
        existingErrors.forEach(error => error.style.display = 'none');
        
        const existingErrorInputs = formElement.querySelectorAll('.form-input.error');
        existingErrorInputs.forEach(input => input.classList.remove('error'));
        
        // Validate required fields
        const requiredInputs = formElement.querySelectorAll('[required]');
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                showErrorWithSound(input, 'This field is required');
                hasErrors = true;
                errorCount++;
            }
        });
        
        // Validate email fields
        const emailInputs = formElement.querySelectorAll('input[type="email"]');
        emailInputs.forEach(input => {
            if (input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                showErrorWithSound(input, 'Please enter a valid email address');
                hasErrors = true;
                errorCount++;
            }
        });
        
        // Validate password fields (registration form)
        const passwordField = formElement.querySelector('#password');
        if (passwordField && passwordField.value) {
            if (passwordField.value.length < 8) {
                showErrorWithSound(passwordField, 'Password must be at least 8 characters');
                hasErrors = true;
                errorCount++;
            }
        }
        
        // Validate password confirmation (registration form)
        const confirmField = formElement.querySelector('#confirm');
        if (confirmField && passwordField) {
            if (confirmField.value !== passwordField.value) {
                showErrorWithSound(confirmField, 'Passwords do not match');
                hasErrors = true;
                errorCount++;
            }
        }
        
        // Validate name field (registration form)
        const nameField = formElement.querySelector('#name');
        if (nameField && nameField.value) {
            if (nameField.value.length > 100) {
                showErrorWithSound(nameField, 'Name cannot exceed 100 characters');
                hasErrors = true;
                errorCount++;
            }
        }
        
        console.log(`Form validation completed: ${errorCount} errors found`);
        return !hasErrors;
    }

    // Add event listeners to all form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Handle form submission
        form.addEventListener('submit', function(event) {
            console.log('Form submit event triggered');
            
            // Initialize audio if not already done
            if (!isAudioInitialized) {
                initializeAudio();
            }
            
            // Validate form and play sound if errors exist
            const isValid = validateFormWithSound(this);
            
            if (!isValid) {
                console.log('Form validation failed, preventing submission');
                event.preventDefault(); // Prevent form submission
                
                // Scroll to first error
                const firstError = this.querySelector('.form-input.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                
                return false;
            }
            
            console.log('Form validation passed, allowing submission');
        });
        
        // Handle button clicks specifically
        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                console.log('Submit button clicked');
                
                // Initialize audio if not already done
                if (!isAudioInitialized) {
                    initializeAudio();
                }
                
                // Small delay to ensure audio context is ready
                setTimeout(() => {
                    const isValid = validateFormWithSound(form);
                    if (!isValid) {
                        console.log('Button click validation failed');
                        event.preventDefault();
                        
                        // Focus on first error field
                        const firstError = form.querySelector('.form-input.error');
                        if (firstError) {
                            firstError.focus();
                        }
                    }
                }, 10);
            });
        });
    });

    // Check for server-side errors and play sound
    function checkServerErrors() {
        const serverErrorInputs = document.querySelectorAll('.form-input.error');
        const serverErrorMessages = document.querySelectorAll('.error-message');
        const alertErrors = document.querySelectorAll('.alert-error');
        
        if (serverErrorInputs.length > 0 || serverErrorMessages.length > 0 || alertErrors.length > 0) {
            console.log('Server errors detected, will play sound on user interaction');
            
            // Play sound on first user interaction
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

    // Check for server errors on page load
    checkServerErrors();

    // Observer for dynamically added errors (if needed for AJAX)
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

    // Start observing for dynamic changes
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
    });

    // Expose validation function globally
    window.validateFormWithSound = validateFormWithSound;
    window.playErrorSound = playErrorSound;
    
    console.log('Enhanced login/register JavaScript loaded');
});

        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleText = document.getElementById('toggle-text');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleText.textContent = 'Hide';
            } else {
                passwordField.type = 'password';
                toggleText.textContent = 'Show';
            }
        }

        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('.btn-primary');
            const normalContent = submitBtn.querySelector('span');
            const loadingContent = submitBtn.querySelector('.btn-loading');
            
            normalContent.style.display = 'none';
            loadingContent.style.display = 'flex';
            submitBtn.disabled = true;
        });

        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (!emailField.value) {
                emailField.focus();
            } else if (!passwordField.value) {
                passwordField.focus();
            }
        });

        document.querySelectorAll('.otp-input').forEach((input, index, inputs) => {
    // Handle input
    input.addEventListener('input', (e) => {
        if (e.target.value.length === 1) {
            if (index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        }
    });
    
    // Handle backspace
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && e.target.value === '') {
            if (index > 0) {
                inputs[index - 1].focus();
            }
        }
    });
    
    // Handle paste
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
