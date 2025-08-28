document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.form-input');
    
    const errorSounds = [
        new Audio('images/errorAudio.mp3')
    ];
    
    errorSounds.forEach(sound => sound.volume = 0.6);
    
    let audioContext = null;
    let isAudioInitialized = false;

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
                console.log('Error sound played successfully');
            }).catch(error => {
                console.warn('HTML5 audio failed, trying Web Audio API fallback:', error);
                playWebAudioErrorSound();
            });
        } else {
            playWebAudioErrorSound();
        }
    }

    function playWebAudioErrorSound() {
        if (!audioContext) {
            console.warn('No audio context available');
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
            
            console.log('Web Audio API error sound played');
        } catch (fallbackError) {
            console.warn('Web Audio API also failed:', fallbackError);
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
        if (passwordField && passwordField.value) {
            if (passwordField.value.length < 8) {
                showErrorWithSound(passwordField, 'Password must be at least 8 characters');
                hasErrors = true;
                errorCount++;
            }
        }
        
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
        
        console.log(`Form validation completed: ${errorCount} errors found`);
        return !hasErrors;
    }

    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            console.log('Form submit event triggered');
            
            if (!isAudioInitialized) {
                initializeAudio();
            }
            
            const isValid = validateFormWithSound(this);
            
            if (!isValid) {
                console.log('Form validation failed, preventing submission');
                event.preventDefault();
                
                const firstError = this.querySelector('.form-input.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                
                return false;
            }
            
            console.log('Form validation passed, allowing submission');
        });
        
        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                console.log('Submit button clicked');
                
                if (!isAudioInitialized) {
                    initializeAudio();
                }
                
                setTimeout(() => {
                    const isValid = validateFormWithSound(form);
                    if (!isValid) {
                        console.log('Button click validation failed');
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
            console.log('Server errors detected, will play sound on user interaction');
            
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
    
    console.log('Enhanced login/register JavaScript loaded');
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

