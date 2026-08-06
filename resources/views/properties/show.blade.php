<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="View detailed information about {{ $property->title }} on TrueHold.">
    <meta name="theme-color" content="#1e3a5f">
    <title>{{ $property->title ?: 'Property Details' }} - TrueHold</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/truehold-logo.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/truehold-logo.jpg') }}">
    
    <link href="{{ asset('css/font-awesome.min.css') }}" rel="stylesheet">
    
    <style>
/* ==========================================
   TRUEHOLD - Property Details
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
}

a {
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    -webkit-tap-highlight-color: transparent;
}

button {
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: var(--transition);
    -webkit-tap-highlight-color: transparent;
}

input,
textarea {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

/* Smooth Scrolling */
html {
    scroll-behavior: smooth;
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

.nav-links {
    display: flex;
    list-style: none;
    gap: 8px;
    align-items: center;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--gray);
    font-weight: 500;
    font-size: 15px;
    padding: 12px 20px;
    border-radius: 10px;
    position: relative;
    transition: var(--transition);
}

.nav-link svg {
    stroke-width: 2;
    transition: var(--transition);
}

.nav-link:hover {
    color: var(--primary-navy);
    background-color: rgba(30, 58, 95, 0.05);
}

.nav-link:hover svg {
    transform: translateY(-2px);
}

.btn-agent {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--white);
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    box-shadow: 0 4px 16px rgba(212, 175, 55, 0.3);
    margin-left: 8px;
}

.btn-agent:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(212, 175, 55, 0.4);
}

/* ==========================================
   BACK BUTTON SECTION
   ========================================== */

.back-section {
    padding: 24px 0;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-navy);
    font-weight: 600;
    font-size: 15px;
    transition: var(--transition);
}

.back-link:hover {
    color: var(--navy-light);
}

.back-link svg {
    transition: var(--transition);
}

.back-link:hover svg {
    transform: translateX(-4px);
}

/* ==========================================
   PROPERTY DETAILS LAYOUT
   ========================================== */

.property-details-section {
    padding: 24px 0 48px;
}

.details-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 32px;
}

.details-main {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

/* ==========================================
   IMAGE GALLERY
   ========================================== */

.image-gallery {
    background-color: var(--white);
    border-radius: 16px;
            overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.gallery-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 2px solid var(--light-gray);
}

.gallery-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-navy);
}

.gallery-title svg {
    color: var(--gold);
}

.gallery-counter {
    background-color: var(--off-white);
    color: var(--text-dark);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.main-image {
    position: relative;
    background-color: var(--light-gray);
}

.gallery-image {
    width: 100%;
    height: auto;
    display: block;
    aspect-ratio: 16 / 9;
    object-fit: cover;
}

.gallery-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.95);
    color: var(--primary-navy);
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    box-shadow: var(--shadow-md);
    transition: var(--transition);
}

.gallery-prev {
    left: 16px;
}

.gallery-next {
    right: 16px;
}

.gallery-nav:hover {
    background: var(--primary-navy);
    color: var(--white);
}

.fullscreen-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.95);
    color: var(--primary-navy);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    transition: var(--transition);
}

.fullscreen-btn:hover {
    background: var(--primary-navy);
    color: var(--white);
}

.thumbnail-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    padding: 8px;
    background-color: var(--off-white);
}

.thumbnail {
    width: 100%;
    height: 80px;
    object-fit: cover;
    cursor: pointer;
    transition: var(--transition);
    border-radius: 8px;
    border: 3px solid transparent;
}

.thumbnail:hover {
    opacity: 0.8;
    border-color: var(--gold);
}

.thumbnail.active {
    border-color: var(--primary-navy);
}

/* ==========================================
   LIGHTBOX MODAL
   ========================================== */

.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.lightbox.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.lightbox-content {
    position: relative;
    max-width: 90%;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-image {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    animation: zoomIn 0.3s ease;
}

@keyframes zoomIn {
    from {
        transform: scale(0.8);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    color: var(--white);
    font-size: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    z-index: 10;
}

.lightbox-close:hover {
    background: var(--white);
    color: var(--primary-navy);
    border-color: var(--white);
    transform: rotate(90deg);
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    z-index: 10;
}

.lightbox-nav:hover {
    background: var(--gold);
    border-color: var(--gold);
    transform: translateY(-50%) scale(1.1);
}

.lightbox-nav svg {
    width: 28px;
    height: 28px;
}

.lightbox-prev {
    left: 40px;
}

.lightbox-next {
    right: 40px;
}

.lightbox-counter {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 12px 24px;
            border-radius: 50px;
    color: var(--white);
    font-size: 16px;
            font-weight: 600;
    z-index: 10;
}

.lightbox-zoom-hint {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(212, 175, 55, 0.9);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
            border-radius: 50px;
    color: var(--white);
    font-size: 13px;
            font-weight: 600;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Add cursor pointer to main image */
.gallery-image {
    cursor: zoom-in;
    position: relative;
}

/* Expand icon overlay on main image */
.main-image {
            position: relative;
        }
        
.main-image::after {
            content: '';
            position: absolute;
    bottom: 16px;
    right: 16px;
    width: 44px;
    height: 44px;
    background: rgba(212, 175, 55, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 50%;
    display: flex;
            align-items: center;
            justify-content: center;
    pointer-events: none;
    opacity: 0;
    transition: var(--transition);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3Cpath d='M11 8v6M8 11h6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 24px 24px;
}

.main-image:hover::after {
    opacity: 1;
}

/* ==========================================
   PROPERTY INFO
   ========================================== */

.property-info {
    background-color: var(--white);
    border-radius: 16px;
    padding: 32px;
    box-shadow: var(--shadow-sm);
}

.info-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 24px;
    padding-bottom: 24px;
    border-bottom: 2px solid var(--light-gray);
    margin-bottom: 24px;
}

.property-title-large {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-navy);
    line-height: 1.3;
    margin-bottom: 12px;
}

.property-location-large {
    display: flex;
            align-items: center;
    gap: 8px;
    color: var(--gray);
    font-size: 16px;
}

.property-location-large svg {
    color: var(--gold);
}

.rooms-available-text {
    font-size: 15px;
    font-weight: 500;
    color: var(--gray);
    margin: 4px 0 8px 0;
}

.price-large {
    font-size: 36px;
    font-weight: 700;
    color: var(--primary-navy);
    white-space: nowrap;
}

.price-large span {
    font-size: 16px;
    font-weight: 500;
    color: var(--gray);
}

.availability-status {
    display: flex;
    gap: 12px;
    margin-bottom: 32px;
    flex-wrap: wrap;
}

.status-badge {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
}

.status-available {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(232, 197, 92, 0.2));
    color: var(--gold-dark);
}

.property-type {
    padding: 10px 20px;
            border-radius: 8px;
    background-color: rgba(30, 58, 95, 0.1);
    color: var(--primary-navy);
    font-weight: 600;
    font-size: 14px;
}

/* Sections */
.section-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-navy);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title svg {
    color: var(--gold);
}

.description-section {
    margin-bottom: 32px;
}

.address-block {
    background-color: var(--off-white);
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 15px;
    color: var(--text-dark);
    border-left: 4px solid var(--gold);
}

.address-block strong {
    color: var(--primary-navy);
}

.description-text {
    color: var(--text-dark);
    font-size: 15px;
    line-height: 1.8;
    margin-bottom: 16px;
}

.description-text:last-child {
    margin-bottom: 0;
}

.offer-section {
    margin-top: 32px;
    padding: 24px;
    background-color: var(--off-white);
    border-radius: 12px;
}

.offer-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-navy);
    margin-bottom: 20px;
}

.offer-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.offer-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
    font-weight: 500;
    color: var(--text-dark);
}

.offer-item svg {
    color: #2ecc71;
    flex-shrink: 0;
    background-color: rgba(46, 204, 113, 0.1);
    padding: 2px;
    border-radius: 4px;
}

/* ==========================================
   SIDEBAR
   ========================================== */

.details-sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.manager-card,
.location-card,
.clients-card,
.actions-card {
    background-color: var(--white);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--shadow-sm);
    border: 2px solid var(--light-gray);
}

.card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-navy);
    margin-bottom: 20px;
}

.card-title svg {
    color: var(--gold);
}

.locked-content {
    text-align: center;
    padding: 32px 20px;
    background: linear-gradient(135deg, rgba(30, 58, 95, 0.05), rgba(30, 58, 95, 0.02));
    border-radius: 12px;
}

.locked-content svg {
    color: var(--gray);
    opacity: 0.4;
    margin-bottom: 16px;
}

.locked-text {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    line-height: 1.5;
}

.locked-subtext {
    font-size: 13px;
    color: var(--gray);
}

.client-count {
    font-size: 14px;
    color: var(--primary-navy);
    font-weight: 600;
    margin: 8px 0;
}

.map-placeholder {
    background: linear-gradient(135deg, rgba(30, 58, 95, 0.08), rgba(30, 58, 95, 0.03));
    border: 2px dashed var(--light-gray);
    border-radius: 12px;
    padding: 32px 20px;
    text-align: center;
    margin-bottom: 16px;
}

.map-placeholder svg {
    color: var(--primary-navy);
    margin-bottom: 12px;
}

.coordinates {
    margin-top: 12px;
}

.coordinates-label {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 4px;
}

.coordinates-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary-navy);
    font-family: 'Courier New', monospace;
}

.btn-maps {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px;
    background: linear-gradient(135deg, var(--primary-navy), var(--navy-light));
    color: var(--white);
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    transition: var(--transition);
}

.btn-maps:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-maps svg {
    flex-shrink: 0;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.btn-action,
.btn-action-secondary {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    transition: var(--transition);
}

.btn-action {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--white);
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-action svg {
    flex-shrink: 0;
}

.btn-action-secondary {
    background-color: var(--white);
    border: 2px solid var(--primary-navy);
    color: var(--primary-navy);
}

.btn-action-secondary:hover {
    background-color: var(--primary-navy);
    color: var(--white);
}

.btn-action-secondary svg {
    flex-shrink: 0;
}

/* Success Alert */
.success-alert {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    border: 2px solid #10b981;
    color: #065f46;
    padding: 16px 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}

/* ==========================================
   FOOTER
   ========================================== */

.footer {
    background: linear-gradient(135deg, var(--primary-navy) 0%, var(--navy-dark) 100%);
    color: var(--white);
    padding: 48px 0;
    margin-top: 48px;
}

.footer-content {
    text-align: center;
}

.footer-logo {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    font-size: 24px;
}

.footer-text {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 8px;
}

.footer-copyright {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
}

/* ==========================================
   RESPONSIVE DESIGN
   ========================================== */

@media (max-width: 1024px) {
    .details-layout {
        grid-template-columns: 1fr;
    }
    
    /* Main content (image & description) appears first on mobile */
    .details-main {
        order: 1;
    }
    
    /* Sidebar (buttons & actions) appears after on mobile */
    .details-sidebar {
        order: 2;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 16px;
    }
    
    /* Navigation */
    .navbar {
        padding: 12px 0;
    }
    
    .logo-text {
        font-size: 18px;
    }
    
    .logo-icon svg {
        width: 20px;
        height: 20px;
    }
    
    .nav-links {
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .nav-link {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .nav-link svg {
        width: 14px;
        height: 14px;
    }
    
    .btn-agent {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .btn-agent svg {
        width: 14px;
        height: 14px;
    }
    
    /* Back Button */
    .back-section {
        padding: 16px 0;
    }
    
    .back-link {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    /* Property Details Layout */
    .property-details-section {
        padding: 24px 0;
    }
    
    .details-layout {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    
    .details-main {
        width: 100%;
        order: 1;
    }
    
    .details-sidebar {
        width: 100%;
        order: 2;
    }
    
    /* Image Gallery */
    .gallery-header {
        padding: 12px 16px;
    }
    
    .gallery-title {
        font-size: 16px;
    }
    
    .gallery-title svg {
        width: 20px;
        height: 20px;
    }
    
    .gallery-counter {
        font-size: 12px;
    }
    
    .main-image {
        height: 280px;
    }
    
    .gallery-image {
        height: 280px;
    }
    
    .gallery-nav {
        width: 36px;
        height: 36px;
    }
    
    .gallery-nav svg {
        width: 18px;
        height: 18px;
        }
        
        .thumbnail-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        padding: 8px;
        }
        
        .thumbnail {
        height: 70px;
        border-width: 2px;
    }
    
    /* Property Info */
    .property-info {
        padding: 20px 16px;
    }
    
    .info-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    
    .property-title-large {
        font-size: 22px;
        line-height: 1.3;
    }
    
    .property-location-large {
        font-size: 14px;
    }
    
    .property-location-large svg {
        width: 16px;
        height: 16px;
    }
    
    .price-large {
        font-size: 26px;
        align-self: flex-start;
    }
    
    .price-large span {
        font-size: 14px;
    }
    
    .availability-status {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .status-badge {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .property-type {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .section-title {
        font-size: 16px;
        margin-bottom: 12px;
    }
    
    .section-title svg {
        width: 18px;
        height: 18px;
    }
    
    .description-text {
        font-size: 14px;
        line-height: 1.6;
    }
    
    .address-block {
        padding: 12px;
        font-size: 13px;
    }
    
    .offer-title {
        font-size: 14px;
    }
    
    .offer-list {
        gap: 10px;
    }
    
    .offer-item {
        font-size: 13px;
    }
    
    .offer-item svg {
        width: 16px;
        height: 16px;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .detail-item {
        padding: 12px;
    }
    
    .detail-label {
        font-size: 12px;
    }
    
    .detail-value {
        font-size: 14px;
    }
    
    .detail-value svg {
        width: 16px;
        height: 16px;
    }
    
    /* Sidebar Cards */
    .manager-card,
    .location-card,
    .clients-card,
    .actions-card {
        padding: 16px;
    }
    
    .card-title {
        font-size: 15px;
        margin-bottom: 12px;
    }
    
    .card-title svg {
        width: 18px;
        height: 18px;
    }
    
    .locked-content svg {
        width: 36px;
        height: 36px;
    }
    
    .locked-text {
        font-size: 13px;
    }
    
    .locked-subtext {
        font-size: 12px;
    }
    
    .map-placeholder svg {
        width: 36px;
        height: 36px;
    }
    
    .coordinates-label {
        font-size: 12px;
    }
    
    .coordinates-value {
        font-size: 13px;
    }
    
    .btn-maps {
        padding: 12px;
        font-size: 14px;
    }
    
    .btn-maps svg {
        width: 18px;
        height: 18px;
    }
    
    .client-item {
        padding: 12px;
    }
    
    .client-name {
        font-size: 14px;
    }
    
    .client-phone,
    .client-email {
        font-size: 12px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-action,
    .btn-action-secondary {
                width: 100%;
                justify-content: center;
        padding: 12px 20px;
        font-size: 14px;
    }
    
    .btn-action svg,
    .btn-action-secondary svg {
        width: 18px;
        height: 18px;
    }
    
    /* Footer */
    .footer {
        padding: 32px 0;
    }
    
    .footer-logo {
        margin-bottom: 12px;
    }
    
    .logo-icon {
        width: 32px;
        height: 32px;
        font-size: 20px;
    }
    
    .footer-text {
        font-size: 13px;
    }
    
    .footer-copyright {
        font-size: 12px;
    }
    
    /* Lightbox Mobile */
    .lightbox-nav {
        width: 48px;
        height: 48px;
    }
    
    .lightbox-nav svg {
        width: 24px;
        height: 24px;
    }
    
    .lightbox-prev {
        left: 8px;
    }
    
    .lightbox-next {
        right: 8px;
    }
    
    .lightbox-close {
        top: 8px;
        right: 8px;
        width: 42px;
        height: 42px;
        font-size: 24px;
    }
    
    .lightbox-counter {
        bottom: 16px;
        font-size: 13px;
        padding: 8px 16px;
    }
    
    .lightbox-zoom-hint {
        font-size: 11px;
        padding: 6px 12px;
        top: 8px;
    }
    
    .lightbox-zoom-hint svg {
        width: 14px;
        height: 14px;
    }
    
    .lightbox-content {
        max-width: 95%;
    }
    
    .lightbox-image {
        max-height: 80vh;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 12px;
    }
    
    /* Navigation - Extra Small */
    .logo-text {
        font-size: 16px;
    }
    
    .nav-links {
        gap: 2px;
    }
    
    .nav-link {
        padding: 6px 8px;
        font-size: 11px;
    }
    
    .btn-agent {
        padding: 6px 10px;
        font-size: 11px;
    }
    
    /* Image Gallery - Extra Small */
    .main-image {
        height: 240px;
    }
    
    .gallery-image {
        height: 240px;
    }
    
    .gallery-title {
        font-size: 14px;
    }
    
    .gallery-counter {
        font-size: 11px;
    }
    
    .gallery-nav {
        width: 32px;
        height: 32px;
    }
    
    .gallery-nav svg {
        width: 16px;
        height: 16px;
    }
    
    .thumbnail-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 4px;
        padding: 6px;
    }
    
    .thumbnail {
        height: 60px;
    }
    
    /* Property Info - Extra Small */
    .property-info {
        padding: 16px 12px;
    }
    
    .property-title-large {
        font-size: 20px;
    }
    
    .property-location-large {
        font-size: 13px;
    }
    
    .price-large {
        font-size: 24px;
    }
    
    .status-badge {
        padding: 5px 10px;
        font-size: 11px;
    }
    
    .section-title {
        font-size: 15px;
    }
    
    .description-text {
        font-size: 13px;
    }
    
    .details-grid {
        gap: 10px;
    }
    
    .detail-item {
        padding: 10px;
    }
    
    .detail-label {
        font-size: 11px;
    }
    
    .detail-value {
        font-size: 13px;
    }
    
    /* Sidebar Cards - Extra Small */
    .manager-card,
    .location-card,
    .clients-card,
    .actions-card {
        padding: 14px 12px;
    }
    
    .card-title {
        font-size: 14px;
    }
    
    .locked-content svg {
        width: 32px;
        height: 32px;
    }
    
    .locked-text {
        font-size: 12px;
    }
    
    .btn-action,
    .btn-action-secondary {
        padding: 10px 16px;
        font-size: 13px;
    }
    
    /* Lightbox - Extra Small */
    .lightbox-nav {
        width: 40px;
        height: 40px;
    }
    
    .lightbox-nav svg {
        width: 20px;
        height: 20px;
    }
    
    .lightbox-prev {
        left: 4px;
    }
    
    .lightbox-next {
        right: 4px;
    }
    
    .lightbox-close {
        top: 4px;
        right: 4px;
        width: 38px;
        height: 38px;
        font-size: 22px;
    }
    
    .lightbox-counter {
        bottom: 12px;
        font-size: 12px;
        padding: 6px 12px;
    }
    
    .lightbox-zoom-hint {
        font-size: 10px;
        padding: 4px 10px;
    }
    
    .lightbox-image {
        max-height: 75vh;
    }
        }
    </style>
    
    <script>
        // Image gallery functionality
        let currentImageIndex = 0;
        let images = [];
        
        function initGallery() {
            images = Array.from(document.querySelectorAll('.thumbnail'));
            const mainImage = document.querySelector('.gallery-image');
            const counter = document.querySelector('.gallery-counter');
            
            if (images.length === 0) return;
            
            images.forEach((thumb, index) => {
                thumb.addEventListener('click', () => {
                    currentImageIndex = index;
                    updateGallery();
                });
            });
            
            document.querySelector('.gallery-prev')?.addEventListener('click', () => {
                currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
                updateGallery();
            });
            
            document.querySelector('.gallery-next')?.addEventListener('click', () => {
                currentImageIndex = (currentImageIndex + 1) % images.length;
                updateGallery();
            });
            
            function updateGallery() {
                let imageSrc = images[currentImageIndex].src;
                // Convert to high quality - remove size parameters and use large version
                imageSrc = imageSrc.replace(/w=\d+/, 'w=1200');
                imageSrc = imageSrc.replace(/h=\d+/, 'h=900');
                // If it's a square thumbnail, convert to large
                if (imageSrc.includes('/square/')) {
                    imageSrc = imageSrc.replace('/square/', '/large/');
                }
                mainImage.src = imageSrc;
                images.forEach((thumb, index) => {
                    thumb.classList.toggle('active', index === currentImageIndex);
                });
                counter.textContent = `${currentImageIndex + 1} of ${images.length}`;
            }
        }
    </script>
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
                <ul class="nav-links">
                    <li><a href="{{ route('properties.index') }}" class="nav-link" id="navPropertiesLink">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-width="2"/>
                        </svg>
                        Properties
                    </a></li>
                    <li><a href="{{ route('properties.map') }}" class="nav-link" id="navMapLink">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4-7 4z" stroke-width="2"/>
                        </svg>
                        Map View
                    </a></li>
                        @auth
                    <li><a href="{{ route('admin.dashboard') }}" class="btn-agent">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Dashboard
                    </a></li>
                            @else
                    <li><a href="{{ route('login', ['redirect' => url()->current()]) }}" class="btn-agent">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Agent Login
                    </a></li>
                        @endauth
                </ul>
                    </div>
                            </div>
    </nav>

    <!-- Back Button -->
    <section class="back-section">
        <div class="container">
            <a href="{{ route('properties.index') }}" class="back-link" id="backButton">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M19 12H5M12 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Listings
            </a>
                            </div>
    </section>

    <!-- Success Message -->
    @if(session('success'))
        <div class="container">
            <div class="success-alert">
                <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                <span>{{ session('success') }}</span>
                    </div>
                </div>
                        @endif

    <!-- Property Details -->
    <section class="property-details-section">
        <div class="container">
            <div class="details-layout">
                
                <!-- Main Content -->
                <div class="details-main">
                    
                    <!-- Image Gallery -->
                    <div class="image-gallery">
                        <div class="gallery-header">
                            <h2 class="gallery-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                                </svg>
                                Property Gallery
                                </h2>
                            <span class="gallery-counter">
                                @if($property->high_quality_photos_array && count($property->high_quality_photos_array) > 0)
                                    1 of {{ count($property->high_quality_photos_array) }}
                                @elseif($property->photo_count > 0)
                                    1 of {{ $property->photo_count }}
                                @else
                                    1 of 1
                                @endif
                                    </span>
                                </div>
                        <div class="main-image">
                            @if($property->high_quality_photos_array && count($property->high_quality_photos_array) > 0)
                                <img src="{{ $property->high_quality_photos_array[0] }}" alt="{{ $property->title }}" class="gallery-image">
                            @elseif($property->first_photo_url && $property->first_photo_url !== 'N/A')
                                <img src="{{ $property->first_photo_url }}" alt="{{ $property->title }}" class="gallery-image">
                            @else
                                <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&q=80" alt="{{ $property->title }}" class="gallery-image">
                            @endif
                            
                            @if(($property->high_quality_photos_array && count($property->high_quality_photos_array) > 1) || $property->photo_count > 1)
                                <button class="gallery-nav gallery-prev">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M15 18l-6-6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <button class="gallery-nav gallery-next">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M9 18l6-6-6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            @endif
                            </div>
                            
                        @if($property->high_quality_photos_array && count($property->high_quality_photos_array) > 1)
                            <div class="thumbnail-grid">
                                @foreach($property->high_quality_photos_array as $index => $photo)
                                    <img src="{{ $photo }}" alt="Thumbnail {{ $index + 1 }}" class="thumbnail {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}">
                                @endforeach
                        </div>
                    @endif
                    </div>

                    <!-- Property Info -->
                    <div class="property-info">
                        <div class="info-header">
                            <div>
                                <h1 class="property-title-large">{{ $property->title }}</h1>
                                @php
                                    $roomsAvailable = isset($property->room_count) && is_numeric($property->room_count) ? (int) $property->room_count : (int) ($property->total_rooms ?? 1);
                                    $roomsAvailable = $roomsAvailable >= 1 ? $roomsAvailable : 1;
                                @endphp
                                <p class="rooms-available-text">{{ $roomsAvailable }} {{ $roomsAvailable === 1 ? 'room' : 'rooms' }} available</p>
                                <div class="property-location-large">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    {{ $property->location ?: 'Location not specified' }}
                                </div>
                                </div>
                            <div class="price-large">{{ $property->formatted_price }}<span>/month</span></div>
                            </div>

                        <div class="availability-status">
                            @if($property->available_date && $property->available_date !== 'N/A')
                                <span class="status-badge status-available">Available {{ $property->available_date }}</span>
                            @else
                                <span class="status-badge status-available">Available Now</span>
                                @endif
                                
                            @if($property->property_type)
                                <span class="property-type">{{ $property->property_type }}</span>
                                @endif
                            </div>
                            
                        <!-- Description -->
                        <div class="description-section">
                            <h2 class="section-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                                Description
                            </h2>
                            
                            @if($property->address && $property->address !== 'N/A')
                                <div class="address-block">
                                    <strong>ADDRESS:</strong> {{ $property->address }}
                                    </div>
                                @endif
                                
                            @if($property->description && $property->description !== 'N/A')
                                <p class="description-text">{{ $property->description }}</p>
                                @endif

                            @php
                                // Use room_count (rooms in ad) for deposit rows; fallback to total_rooms then 1. Cap at 6.
                                $roomCount = isset($property->room_count) && is_numeric($property->room_count) ? (int) $property->room_count : null;
                                $totalRooms = $roomCount !== null && $roomCount >= 1 ? min($roomCount, 6) : (int) ($property->total_rooms ?? 1);
                                $totalRooms = max(1, min(6, $totalRooms));
                                $hasExtraCost = ($property->deposit !== null && $property->deposit !== '' && (string)$property->deposit !== 'N/A')
                                    || ($property->bills_included !== null && $property->bills_included !== '' && (string)$property->bills_included !== 'N/A')
                                    || ($totalRooms > 1);
                                $depositFormatted = $property->deposit;
                                if ($property->deposit !== null && $property->deposit !== '' && (string)$property->deposit !== 'N/A' && is_numeric(preg_replace('/[^0-9.]/', '', $property->deposit))) {
                                    $depositFormatted = '£' . number_format((float) preg_replace('/[^0-9.]/', '', $property->deposit), 2);
                                }
                            @endphp
                            @if($hasExtraCost)
                                <div class="offer-section">
                                    <h3 class="offer-title">Extra cost</h3>
                                    <div class="offer-list">
                                        @if($property->deposit !== null && $property->deposit !== '' && (string)$property->deposit !== 'N/A' || $totalRooms > 1)
                                            @if($totalRooms > 1)
                                                @for($r = 1; $r <= $totalRooms; $r++)
                                                    @php
                                                        $rt = $property->{ 'room' . $r . '_type' } ?? null;
                                                        $rp = $property->{ 'room' . $r . '_price_pcm' } ?? null;
                                                        $rd = $property->{ 'room' . $r . '_deposit' } ?? null;
                                                        $hasRoomData = ($rt !== null && $rt !== '' && (string)$rt !== 'N/A')
                                                            || ($rp !== null && $rp !== '' && (string)$rp !== 'N/A')
                                                            || ($rd !== null && $rd !== '' && (string)$rd !== 'N/A');
                                                        $rentFormatted = $rp;
                                                        if ($rp !== null && $rp !== '' && (string)$rp !== 'N/A' && is_numeric(preg_replace('/[^0-9.]/', '', $rp))) {
                                                            $rentFormatted = '£' . number_format((float) preg_replace('/[^0-9.]/', '', $rp), 2) . '/month';
                                                        }
                                                        $depositRoomFormatted = $rd;
                                                        if ($rd !== null && $rd !== '' && (string)$rd !== 'N/A' && is_numeric(preg_replace('/[^0-9.]/', '', $rd))) {
                                                            $depositRoomFormatted = '£' . number_format((float) preg_replace('/[^0-9.]/', '', $rd), 2);
                                                        }
                                                    @endphp
                                                    @if($hasRoomData)
                                                        <div class="offer-item">
                                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                            </svg>
                                                            <span><strong>Room {{ $r }}</strong>
                                                                @if($rt) {{ $rt }}@endif
                                                                @if($rp) — {{ $rentFormatted }}@endif
                                                                @if($rd) — Deposit {{ $depositRoomFormatted }}@endif
                                                            </span>
                                                        </div>
                                                    @endif
                                                @endfor
                                            @else
                                                @if($property->deposit !== null && $property->deposit !== '' && (string)$property->deposit !== 'N/A')
                                                    <div class="offer-item">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                        </svg>
                                                        <span><strong>Deposit</strong> {{ $depositFormatted }}</span>
                                                    </div>
                                                @endif
                                            @endif
                                        @endif
                                        @if($property->bills_included !== null && $property->bills_included !== '' && (string)$property->bills_included !== 'N/A')
                                            <div class="offer-item">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                </svg>
                                                <span><strong>Bills included?</strong> {{ $property->bills_included }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                                
                            @php
                                $availabilityFields = [
                                    'available_date' => 'Available date',
                                    'min_term' => 'Min term',
                                    'max_term' => 'Max term',
                                ];
                                $amenityFields = [
                                    'furnishings' => 'Furnishings',
                                    'parking' => 'Parking',
                                    'garden' => 'Garden',
                                    'broadband' => 'Broadband',
                                    'housemates' => 'Housemates',
                                    'total_rooms' => 'Total rooms',
                                ];
                                $preferenceFields = [
                                    'smoker' => 'Smoker',
                                    'pets' => 'Pets',
                                    'occupation' => 'Occupation',
                                    'gender' => 'Gender',
                                    'couples_ok' => 'Couples OK',
                                    'smoking_ok' => 'Smoking OK',
                                    'pets_ok' => 'Pets OK',
                                    'pref_occupation' => 'Preferred occupation',
                                    'references' => 'References',
                                    'min_age' => 'Min age',
                                    'max_age' => 'Max age',
                                ];
                                $hasValue = function($val) {
                                    return $val !== null && $val !== '' && (string)$val !== 'N/A';
                                };
                            @endphp

                            @if(array_reduce(array_keys($availabilityFields), fn($carry, $k) => $carry || $hasValue($property->{$k} ?? null), false))
                                <div class="offer-section">
                                    <h3 class="offer-title">Availability</h3>
                                    <div class="offer-list">
                                        @foreach($availabilityFields as $key => $label)
                                            @if($hasValue($property->{$key} ?? null))
                                                <div class="offer-item">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                    </svg>
                                                    <span><strong>{{ $label }}:</strong> {{ $property->$key }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(array_reduce(array_keys($amenityFields), fn($carry, $k) => $carry || $hasValue($property->{$k} ?? null), false))
                                <div class="offer-section">
                                    <h3 class="offer-title">Amenities</h3>
                                    <div class="offer-list">
                                        @foreach($amenityFields as $key => $label)
                                            @if($hasValue($property->{$key} ?? null))
                                                <div class="offer-item">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                    </svg>
                                                    <span><strong>{{ $label }}:</strong> {{ $property->$key }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(array_reduce(array_keys($preferenceFields), fn($carry, $k) => $carry || $hasValue($property->{$k} ?? null), false))
                                <div class="offer-section">
                                    <h3 class="offer-title">New housemate preferences</h3>
                                    <div class="offer-list">
                                        @foreach($preferenceFields as $key => $label)
                                            @if($hasValue($property->{$key} ?? null))
                                                <div class="offer-item">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                    </svg>
                                                    <span><strong>{{ $label }}:</strong> {{ $property->$key }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                </div>

                                </div>
                            </div>

                <!-- Sidebar -->
                <div class="details-sidebar">
                    
                    <!-- Property Manager Card -->
                    <div class="manager-card">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            Property Manager
                        </h3>
                        @auth
                            @if($property->agent_name && $property->agent_name !== 'N/A')
                                <div style="padding: 16px; background: var(--off-white); border-radius: 10px;">
                                    <p style="font-weight: 600; color: var(--primary-navy); margin-bottom: 8px;">
                                    {{ $property->agent_name }}
                                    @php
                                        $payingValue = $property->paying ?? null;
                                        $isPayingAgent = false;
                                        if ($payingValue !== null && $payingValue !== '') {
                                            $normalizedPaying = is_string($payingValue) ? strtolower(trim($payingValue)) : $payingValue;
                                            $isPayingAgent = $normalizedPaying === 'yes' || $normalizedPaying === true || $normalizedPaying === 1 || $normalizedPaying === '1';
                                        }
                                    @endphp
                                    @if($isPayingAgent)
                                        <span title="Paying agent" style="color: var(--gold); margin-left: 6px;">⚡</span>
                                    @endif
                                    </p>
                                    @if($property->agent_phone && $property->agent_phone !== 'N/A')
                                        <p style="color: var(--gray); font-size: 14px;">
                                            <i class="fas fa-phone" style="margin-right: 8px; color: var(--gold);"></i>
                                            {{ $property->agent_phone }}
                                        </p>
                                    @endif
                            </div>
                            @else
                                <p style="color: var(--gray); font-size: 14px;">No agent assigned</p>
                            @endif
                        @else
                            <div class="locked-content">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                <p class="locked-text">Please contact your agent for more details</p>
                                <p class="locked-subtext">Property manager information available to registered users</p>
                        </div>
                        @endauth
                                </div>

                    <!-- Location Card -->
                    @if($property->latitude && $property->longitude && $property->latitude !== 'N/A' && $property->longitude !== 'N/A')
                        <div class="location-card">
                            <h3 class="card-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                Location
                            </h3>
                            <div class="map-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <div class="coordinates">
                                    <p class="coordinates-label">Coordinates:</p>
                                    <p class="coordinates-value">{{ $property->latitude }}, {{ $property->longitude }}</p>
                                </div>
                            </div>
                            <a href="https://www.google.com/maps?q={{ $property->latitude }},{{ $property->longitude }}" target="_blank" class="btn-maps">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
                                </svg>
                                Open in Google Maps
                            </a>
                        </div>
                    @endif

                    <!-- Quick Actions Card -->
                    <div class="actions-card">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                            </svg>
                            Quick Actions
                        </h3>
                        <div class="action-buttons">
                            <button class="btn-action" onclick="shareProperty()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Share Property
                            </button>
                            
                            @auth
                                @if($property->link && $property->link !== 'N/A')
                                    <a href="{{ $property->link }}" target="_blank" class="btn-action-secondary">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                View Original Listing
                            </a>
                            @endif
                            @endauth
                        </div>
                    </div>
                </div>

            </div>
    </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <span class="logo-icon">T</span>
                    <span class="logo-text">TRUEHOLD</span>
            </div>
                <p class="footer-text">Premium Property Solutions</p>
                <p class="footer-copyright">© 2025 TrueHold. All rights reserved.</p>
        </div>
    </div>
    </footer>

    <script>
        function shareProperty() {
            if (navigator.share) {
                navigator.share({
                    title: '{{ $property->title }}',
                    text: 'Check out this property on TrueHold',
                    url: window.location.href
                }).catch(() => {
                    copyToClipboard();
                });
                    } else {
                copyToClipboard();
            }
        }
        
        function copyToClipboard() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('Property link copied to clipboard!');
            });
        }
    </script>
    
    <!-- Lightbox Modal -->
    <div id="lightbox" class="lightbox">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        
        <div class="lightbox-zoom-hint">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
                <path d="M11 8v6M8 11h6"/>
            </svg>
            Click to view full size
        </div>
        
        <button class="lightbox-nav lightbox-prev" onclick="lightboxNavigate(-1)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M15 18l-6-6 6-6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="Enlarged view" class="lightbox-image">
        </div>
        
        <button class="lightbox-nav lightbox-next" onclick="lightboxNavigate(1)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M9 18l6-6-6-6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        
        <div class="lightbox-counter" id="lightboxCounter">1 of 1</div>
    </div>
    
    <script>
        // Lightbox functionality
        let lightboxIndex = 0;
        let allImages = [];
        
        function initLightbox() {
            // Collect all images from gallery - use all thumbnails (not limited to visible ones)
            const thumbnails = Array.from(document.querySelectorAll('.thumbnail'));
            const mainImage = document.querySelector('.gallery-image');
            
            if (thumbnails.length > 0) {
                // Get all thumbnail images (all images are now shown as thumbnails)
                allImages = thumbnails.map(thumb => {
                    let src = thumb.src;
                    // Convert to high quality if needed (remove size parameters, use original)
                    src = src.replace(/w=\d+/, 'w=1920');
                    src = src.replace(/h=\d+/, 'h=1080');
                    // If it's a square thumbnail, convert to large
                    if (src.includes('/square/')) {
                        src = src.replace('/square/', '/large/');
                    }
                    return src;
                });
            } else if (mainImage) {
                allImages = [mainImage.src.replace(/w=\d+/, 'w=1920').replace(/h=\d+/, 'h=1080')];
            }
            
            // Add click event to main image
            if (mainImage) {
                mainImage.addEventListener('click', () => {
                    openLightbox(currentImageIndex);
                });
            }
            
            // Add click event to thumbnails
            thumbnails.forEach((thumb, index) => {
                thumb.addEventListener('dblclick', () => {
                    openLightbox(index);
                });
            });
        }
        
        function openLightbox(index) {
            if (allImages.length === 0) return;
            
            lightboxIndex = index;
            const lightbox = document.getElementById('lightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            
            lightbox.classList.add('active');
            lightboxImage.src = allImages[lightboxIndex];
            updateLightboxCounter();
            
            // Disable body scroll
                document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.classList.remove('active');
            
            // Enable body scroll
            document.body.style.overflow = '';
        }
        
        function lightboxNavigate(direction) {
            if (allImages.length === 0) return;
            
            lightboxIndex = (lightboxIndex + direction + allImages.length) % allImages.length;
            const lightboxImage = document.getElementById('lightboxImage');
            lightboxImage.src = allImages[lightboxIndex];
            updateLightboxCounter();
        }
        
        function updateLightboxCounter() {
            const counter = document.getElementById('lightboxCounter');
            counter.textContent = `${lightboxIndex + 1} of ${allImages.length}`;
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            const lightbox = document.getElementById('lightbox');
            if (!lightbox.classList.contains('active')) return;
            
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                lightboxNavigate(-1);
            } else if (e.key === 'ArrowRight') {
                lightboxNavigate(1);
            }
        });
        
        // Close lightbox when clicking outside the image
        document.getElementById('lightbox')?.addEventListener('click', (e) => {
            if (e.target.id === 'lightbox') {
                closeLightbox();
            }
        });
        
        // Initialize lightbox after gallery is initialized
        document.addEventListener('DOMContentLoaded', () => {
            initGallery();
            initLightbox();
            initBackButton();
        });
        
        // Back button with filter preservation
        function initBackButton() {
            const backButton = document.getElementById('backButton');
            const navPropertiesLink = document.getElementById('navPropertiesLink');
            const navMapLink = document.getElementById('navMapLink');
            
            // Check if we have a stored return URL with filters
            const returnUrl = sessionStorage.getItem('propertyListingUrl');
            
            if (returnUrl) {
                // Update back button
                if (backButton) {
                    backButton.href = returnUrl;
                }
                
                // Update nav links to preserve filters
                if (returnUrl.includes('/properties/map')) {
                    if (navMapLink) navMapLink.href = returnUrl;
                } else if (returnUrl.includes('/properties')) {
                    if (navPropertiesLink) navPropertiesLink.href = returnUrl;
                }
            }
            
            // Alternative: use browser history for back button
            if (backButton) {
                backButton.addEventListener('click', (e) => {
                    if (window.history.length > 1 && document.referrer && 
                        (document.referrer.includes('{{ route('properties.index') }}') || 
                         document.referrer.includes('{{ route('properties.map') }}'))) {
                e.preventDefault();
                        window.history.back();
                    }
                });
            }
        }
    </script>
</body>
</html>

