<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1e3a5f">
    <title>Agent Login - TrueHold</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/truehold-logo.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/truehold-logo.jpg') }}">
    
    <link href="{{ asset('css/font-awesome.min.css') }}" rel="stylesheet">
    
    <style>
/* ==========================================
   TRUEHOLD - Login Page
   Color Palette: White, Navy Blue, Gold
   ========================================== */

/* CSS Variables */
:root {
    --primary-navy: #1e3a5f;
    --navy-dark: #152a45;
    --navy-light: #2d5280;
    --gold: #d4af37;
    --gold-light: #e8c55c;
    --gold-dark: #b8941f;
    --white: #ffffff;
    --off-white: #f8f9fa;
    --light-gray: #e9ecef;
    --gray: #6c757d;
    --text-dark: #212529;
    --shadow-sm: 0 2px 4px rgba(30, 58, 95, 0.08);
    --shadow-md: 0 4px 12px rgba(30, 58, 95, 0.12);
    --shadow-lg: 0 8px 24px rgba(30, 58, 95, 0.15);
    --transition: all 0.3s ease;
}

/* Reset & Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: var(--text-dark);
    background-color: var(--off-white);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    min-height: 100vh;
}

a {
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
}

button {
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: var(--transition);
}

/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}

/* ==========================================
   NAVIGATION
   ========================================== */

.navbar {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    box-shadow: 0 4px 30px rgba(30, 58, 95, 0.08);
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(30, 58, 95, 0.06);
}

.nav-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
}

.logo {
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 700;
    font-size: 22px;
    color: var(--primary-navy);
    cursor: pointer;
    transition: var(--transition);
}

.logo:hover {
    transform: translateX(2px);
}

.logo-icon {
    width: 46px;
    height: 46px;
    background: linear-gradient(135deg, var(--primary-navy), var(--navy-light));
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(30, 58, 95, 0.2);
    transition: var(--transition);
}

.logo-icon svg {
    stroke: var(--gold);
    stroke-width: 2.5;
}

.logo:hover .logo-icon {
    transform: rotate(-5deg) scale(1.05);
    box-shadow: 0 6px 20px rgba(30, 58, 95, 0.3);
}

.logo-text {
    letter-spacing: 1px;
}

.back-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--gray);
    font-weight: 500;
    font-size: 15px;
    padding: 10px 20px;
    border-radius: 10px;
    transition: var(--transition);
}

.back-link:hover {
    color: var(--primary-navy);
    background-color: rgba(30, 58, 95, 0.05);
}

/* ==========================================
   LOGIN LAYOUT
   ========================================== */

.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: calc(100vh - 86px);
    background-color: var(--white);
}

/* Left Side - Image */
.login-image-side {
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, var(--primary-navy), var(--navy-light));
}

.login-bg-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.3;
}

.login-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(30, 58, 95, 0.9), rgba(45, 82, 128, 0.9));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px;
    text-align: center;
}

.login-image-content h2 {
    font-size: 42px;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 20px;
    line-height: 1.2;
}

.login-image-content p {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 32px;
    max-width: 400px;
}

.feature-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    align-items: flex-start;
    margin: 0 auto;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--white);
    font-size: 16px;
    font-weight: 500;
}

.feature-icon {
    width: 40px;
    height: 40px;
    background: rgba(212, 175, 55, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    flex-shrink: 0;
}

/* Right Side - Form */
.login-form-side {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px;
    background-color: var(--off-white);
}

.login-form-container {
    width: 100%;
    max-width: 480px;
}

.form-header {
    text-align: center;
    margin-bottom: 40px;
}

.form-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-navy), var(--navy-light));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    box-shadow: 0 8px 24px rgba(30, 58, 95, 0.2);
}

.form-icon svg {
    color: var(--gold);
    font-size: 36px;
}

.form-header h2 {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-navy);
    margin-bottom: 12px;
}

.form-header p {
    font-size: 16px;
    color: var(--gray);
}

/* Error Alert */
.error-alert {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border: 2px solid #ef4444;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    color: #991b1b;
}

.error-alert-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    margin-bottom: 8px;
}

.error-list {
    list-style: none;
    padding-left: 30px;
}

.error-list li {
    position: relative;
    margin-bottom: 4px;
}

.error-list li:before {
    content: "•";
    position: absolute;
    left: -12px;
    color: #ef4444;
}

/* Form Styles */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary-navy);
}

.form-label svg {
    color: var(--gold);
    font-size: 16px;
}

.form-input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--light-gray);
    border-radius: 10px;
    font-size: 15px;
    font-weight: 500;
    color: var(--text-dark);
    background-color: var(--white);
    transition: var(--transition);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-navy);
    box-shadow: 0 0 0 4px rgba(30, 58, 95, 0.1);
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.remember-me {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--gray);
    cursor: pointer;
}

.remember-me input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border: 2px solid var(--light-gray);
    border-radius: 4px;
    cursor: pointer;
}

.forgot-link {
    font-size: 14px;
    color: var(--primary-navy);
    font-weight: 600;
    transition: var(--transition);
}

.forgot-link:hover {
    color: var(--gold);
}

.submit-button {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--white);
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 16px rgba(212, 175, 55, 0.3);
    transition: var(--transition);
}

.submit-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(212, 175, 55, 0.4);
}

.submit-button svg {
    font-size: 18px;
}

.form-footer {
    text-align: center;
    padding-top: 24px;
    border-top: 1px solid var(--light-gray);
}

.footer-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-navy);
    font-weight: 600;
    font-size: 14px;
    padding: 8px 16px;
    border-radius: 8px;
    transition: var(--transition);
}

.footer-link:hover {
    background-color: rgba(30, 58, 95, 0.05);
    color: var(--gold);
}

.footer-note {
    margin-top: 16px;
    font-size: 13px;
    color: var(--gray);
    line-height: 1.5;
}

/* ==========================================
   RESPONSIVE DESIGN
   ========================================== */

@media (max-width: 1024px) {
    .login-container {
        grid-template-columns: 1fr;
    }
    
    .login-image-side {
        display: none;
    }
    
    .login-form-side {
        min-height: calc(100vh - 86px);
    }
}

@media (max-width: 768px) {
    .nav-content {
        padding: 16px 0;
    }
    
    .logo {
        font-size: 18px;
    }
    
    .logo-icon {
        width: 40px;
        height: 40px;
    }
    
    .login-form-side {
        padding: 32px 24px;
    }
    
    .form-header h2 {
        font-size: 28px;
    }
    
    .form-icon {
        width: 64px;
        height: 64px;
    }
    
    .form-icon svg {
        font-size: 28px;
    }
    
    .form-options {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 16px;
    }
    
    .login-form-side {
        padding: 24px 16px;
    }
    
    .form-header h2 {
        font-size: 24px;
    }
    
    .logo-text {
        font-size: 16px;
    }
}
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="{{ route('properties.index') }}" class="logo">
                    <div class="logo-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-width="2"/>
                            <path d="M9 22V12h6v10" stroke-width="2"/>
                        </svg>
                    </div>
                    <span class="logo-text">TRUEHOLD</span>
                </a>
                <a href="{{ route('properties.index') }}" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Properties</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Login Container -->
    <div class="login-container">
        <!-- Left Side - Image & Info -->
        <div class="login-image-side">
            <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=1200&q=80" alt="Property Management" class="login-bg-image">
            <div class="login-image-overlay">
                <div class="login-image-content">
                    <h2>Welcome to TrueHold</h2>
                    <p>Access your property management dashboard and manage your listings with ease</p>
                    
                    <div class="feature-list">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <span>Manage Properties</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span>Track Clients</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <span>View Analytics</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <span>Generate Reports</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-form-side">
            <div class="login-form-container">
                <!-- Form Header -->
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h2>Agent Sign In</h2>
                    <p>Enter your credentials to access your dashboard</p>
                </div>

                <!-- Error Messages -->
                @if($errors->any())
                    <div class="error-alert">
                        <div class="error-alert-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Login Failed</span>
                        </div>
                        <ul class="error-list">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login.post') }}" class="login-form">
                    @csrf
                    
                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            <span>Email or Username</span>
                        </label>
                        <input 
                            type="text" 
                            id="email" 
                            name="email" 
                            value="{{ old('email') }}" 
                            class="form-input" 
                            placeholder="Email or username"
                            autocomplete="username"
                            required 
                            autofocus
                        >
                    </div>
                    
                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            <span>Password</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>
                    
                    <!-- Options -->
                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="forgot-link">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="submit-button">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In to Dashboard</span>
                    </button>
                </form>
                
                <!-- Form Footer -->
                <div class="form-footer">
                    <a href="{{ route('properties.index') }}" class="footer-link">
                        <i class="fas fa-home"></i>
                        <span>View Property Listings</span>
                    </a>
                    <p class="footer-note">
                        Don't have an account? Contact your administrator to create an agent account.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
