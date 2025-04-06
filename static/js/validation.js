// validation.js - Client-side validation functions

document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    const joinGameForm = document.getElementById('joinGameForm');
    const submitScoreForm = document.getElementById('submitScoreForm');
    
    // Email validation function
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Password validation (at least 8 characters with at least one number and one letter)
    function validatePassword(password) {
        return password.length >= 8 && /[A-Za-z]/.test(password) && /[0-9]/.test(password);
    }
    
    // Username validation (3-20 characters, alphanumeric only)
    function validateUsername(username) {
        return /^[a-zA-Z0-9]{3,20}$/.test(username);
    }
    
    // Session ID validation (alphanumeric only)
    function validateSessionId(sessionId) {
        return /^[a-zA-Z0-9]{8,64}$/.test(sessionId);
    }
    
    // Initials validation (1-3 characters)
    function validateInitials(initials) {
        return /^[A-Za-z0-9]{1,3}$/.test(initials);
    }
    
    // Register form validation
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            let isValid = true;
            let errorMessage = '';
            
            // Username validation
            if (!validateUsername(username)) {
                isValid = false;
                errorMessage += 'Username must be 3-20 characters and contain only letters and numbers.\n';
            }
            
            // Email validation (if provided)
            if (email && !validateEmail(email)) {
                isValid = false;
                errorMessage += 'Please enter a valid email address.\n';
            }
            
            // Password validation
            if (!validatePassword(password)) {
                isValid = false;
                errorMessage += 'Password must be at least 8 characters long and contain at least one letter and one number.\n';
            }
            
            // Confirm password validation
            if (password !== confirmPassword) {
                isValid = false;
                errorMessage += 'Passwords do not match.\n';
            }
            
            if (!isValid) {
                event.preventDefault();
                alert(errorMessage);
            }
        });
    }
    
    // Login form validation
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            let isValid = true;
            let errorMessage = '';
            
            // Simple required field validation
            if (!username) {
                isValid = false;
                errorMessage += 'Username is required.\n';
            }
            
            if (!password) {
                isValid = false;
                errorMessage += 'Password is required.\n';
            }
            
            if (!isValid) {
                event.preventDefault();
                alert(errorMessage);
            }
        });
    }
    
    // Join game form validation
    if (joinGameForm) {
        joinGameForm.addEventListener('submit', function(event) {
            const sessionId = document.getElementById('session_id').value;
            
            if (!validateSessionId(sessionId)) {
                event.preventDefault();
                alert('Please enter a valid session ID (8-64 alphanumeric characters).');
            }
        });
    }
    
    // Submit score form validation
    if (submitScoreForm) {
        submitScoreForm.addEventListener('submit', function(event) {
            const initials = document.getElementById('initials').value;
            const email = document.getElementById('email').value;
            
            let isValid = true;
            let errorMessage = '';
            
            // Initials validation
            if (!validateInitials(initials)) {
                isValid = false;
                errorMessage += 'Initials must be 1-3 characters.\n';
            }
            
            // Email validation (if provided)
            if (email && !validateEmail(email)) {
                isValid = false;
                errorMessage += 'Please enter a valid email address.\n';
            }
            
            if (!isValid) {
                event.preventDefault();
                alert(errorMessage);
            }
        });
    }
});