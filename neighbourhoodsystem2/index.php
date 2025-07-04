<?php
// index.php - Login and Registration page

require_once 'config.php';

// Check if user is already logged in
$user = verifyUserSession();
if ($user) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neighbourhood Connect - Login & Register</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            display: flex;
        }

        .auth-sidebar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }

        .auth-sidebar h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .auth-sidebar p {
            font-size: 1.1em;
            line-height: 1.6;
            opacity: 0.9;
        }

        .auth-forms {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-header h2 {
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
            font-size: 1em;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #4facfe;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 172, 254, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .form-switch {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }

        .form-switch a {
            color: #4facfe;
            text-decoration: none;
            font-weight: 600;
        }

        .form-switch a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #363;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                max-width: 500px;
            }

            .auth-sidebar {
                padding: 40px 20px;
            }

            .auth-forms {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-sidebar">
            <h1>🏘️ Neighbourhood Connect</h1>
            <p>Join your local community and stay connected with your neighbours. Share events, build relationships, and create a stronger neighbourhood together.</p>
        </div>

        <div class="auth-forms">
            <!-- Login Form -->
            <div class="form-container active" id="loginForm">
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to your account</p>
                </div>

                <div class="error-message" id="loginError"></div>
                <div class="success-message" id="loginSuccess"></div>

                <form id="loginFormElement">
                    <div class="form-group">
                        <label for="loginUsername">Username or Email</label>
                        <input type="text" id="loginUsername" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" required>
                    </div>

                    <button type="submit" class="btn">Sign In</button>
                </form>

                <div class="form-switch">
                    Don't have an account? <a href="#" onclick="showRegisterForm()">Sign up here</a>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="form-container" id="registerForm">
                <div class="form-header">
                    <h2>Join Our Community</h2>
                    <p>Create your account</p>
                </div>

                <div class="error-message" id="registerError"></div>
                <div class="success-message" id="registerSuccess"></div>

                <form id="registerFormElement">
                    <div class="form-group">
                        <label for="registerUsername">Username</label>
                        <input type="text" id="registerUsername" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="registerEmail">Email</label>
                        <input type="email" id="registerEmail" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="registerFullName">Full Name</label>
                        <input type="text" id="registerFullName" name="full_name" required>
                    </div>

                    <div class="form-group">
                        <label for="registerPassword">Password</label>
                        <input type="password" id="registerPassword" name="password" required>
                    </div>

                    <div class="form-group">
                        <label for="registerConfirmPassword">Confirm Password</label>
                        <input type="password" id="registerConfirmPassword" name="confirm_password" required>
                    </div>

                    <div class="form-group">
                        <label for="registerAddress">Address (Optional)</label>
                        <textarea id="registerAddress" name="address" placeholder="Your neighbourhood address"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="registerPhone">Phone Number (Optional)</label>
                        <input type="tel" id="registerPhone" name="phone">
                    </div>

                    <button type="submit" class="btn">Create Account</button>
                </form>

                <div class="form-switch">
                    Already have an account? <a href="#" onclick="showLoginForm()">Sign in here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form switching
        function showLoginForm() {
            document.getElementById('loginForm').classList.add('active');
            document.getElementById('registerForm').classList.remove('active');
            clearMessages();
        }

        function showRegisterForm() {
            document.getElementById('registerForm').classList.add('active');
            document.getElementById('loginForm').classList.remove('active');
            clearMessages();
        }

        function clearMessages() {
            document.querySelectorAll('.error-message, .success-message').forEach(el => {
                el.style.display = 'none';
            });
        }

        function showMessage(elementId, message, isSuccess = false) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.style.display = 'block';
            
            // Auto-hide success messages after 3 seconds
            if (isSuccess) {
                setTimeout(() => {
                    element.style.display = 'none';
                }, 3000);
            }
        }

        // Handle login form submission
        document.getElementById('loginFormElement').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'login');
            
            const container = document.querySelector('.auth-forms');
            container.classList.add('loading');
            clearMessages();
            
            try {
                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('loginSuccess', data.message, true);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showMessage('loginError', data.error || 'Login failed');
                }
            } catch (error) {
                showMessage('loginError', 'Network error. Please try again.');
            } finally {
                container.classList.remove('loading');
            }
        });

        // Handle registration form submission
        document.getElementById('registerFormElement').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'register');
            
            const container = document.querySelector('.auth-forms');
            container.classList.add('loading');
            clearMessages();
            
            try {
                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('registerSuccess', data.message, true);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showMessage('registerError', data.error || 'Registration failed');
                }
            } catch (error) {
                showMessage('registerError', 'Network error. Please try again.');
            } finally {
                container.classList.remove('loading');
            }
        });

        // Client-side password validation
        document.getElementById('registerPassword').addEventListener('input', function() {
            const password = this.value;
            const confirmPassword = document.getElementById('registerConfirmPassword').value;
            
            if (confirmPassword && password !== confirmPassword) {
                document.getElementById('registerConfirmPassword').setCustomValidity('Passwords do not match');
            } else {
                document.getElementById('registerConfirmPassword').setCustomValidity('');
            }
        });

        document.getElementById('registerConfirmPassword').addEventListener('input', function() {
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>