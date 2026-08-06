<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Find your dream home with TrueHold. Browse {{ $properties->total() }}+ premium properties in prime London locations.">
    <meta name="theme-color" content="#1e3a5f">
    <title>TrueHold - Premium Property Listings</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/truehold-logo.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/truehold-logo.jpg') }}">
    
    <link href="{{ asset('css/font-awesome.min.css') }}" rel="stylesheet">
    
    <style>
/* ==========================================
   TRUEHOLD - Premium Property Listings
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
   NAVIGATION - MODERN DESIGN
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

.nav-link.active {
    color: var(--primary-navy);
    background-color: rgba(30, 58, 95, 0.08);
    font-weight: 600;
}

.nav-link.active svg {
    stroke: var(--gold);
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
   HERO HEADER - WITH BACKGROUND IMAGE
   ========================================== */

.hero-header {
    position: relative;
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
            overflow: hidden;
        }
        
.hero-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
    z-index: 0;
}

.hero-bg-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        135deg,
        rgba(30, 58, 95, 0.85) 0%,
        rgba(21, 42, 69, 0.75) 50%,
        rgba(30, 58, 95, 0.85) 100%
    );
    backdrop-filter: blur(2px);
}

.hero-content {
            position: relative;
            z-index: 2;
    text-align: center;
    color: var(--white);
    padding: 60px 0;
    width: 100%;
}

.hero-title {
    font-size: 56px;
    font-weight: 700;
    margin-bottom: 20px;
    line-height: 1.2;
    letter-spacing: -1px;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.hero-subtitle {
    font-size: 20px;
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 40px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

.hero-stats {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 48px;
    max-width: 900px;
    margin: 0 auto;
    padding: 32px 48px;
    background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 42px;
    font-weight: 700;
    color: var(--gold);
    margin-bottom: 8px;
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

.stat-divider {
    width: 1px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
}

/* ==========================================
   FILTERS — Light card UI (listing)
   ========================================== */

.filters-section {
    padding: 24px 0 28px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 50%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    position: relative;
}

.filters-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.6), transparent);
}

.filters-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.filters-toggle {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 12px;
    color: var(--primary-navy);
    font-weight: 600;
    font-size: 15px;
    transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.filters-toggle:hover {
    border-color: rgba(30, 58, 95, 0.2);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
}

.filters-toggle:active {
    transform: scale(0.99);
}

.filters-toggle-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(30, 58, 95, 0.06);
    color: var(--gold);
    flex-shrink: 0;
}

.filters-toggle-icon svg {
    width: 20px;
    height: 20px;
}

.filters-toggle .chevron {
    margin-left: 2px;
    color: var(--primary-navy);
    opacity: 0.45;
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.filters-active-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: #fff;
    border: 1px solid rgba(212, 175, 55, 0.35);
    border-radius: 999px;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
}

.filters-active-pill svg {
    color: var(--gold);
    flex-shrink: 0;
}

.filters-active-pill .filters-active-pill__text {
    color: var(--primary-navy);
    font-weight: 600;
    font-size: 13px;
}

.filters-active-pill button {
    background: none;
    border: none;
    color: var(--primary-navy);
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    text-decoration: underline;
    text-underline-offset: 2px;
    padding: 2px 4px;
    margin-left: 4px;
    border-radius: 4px;
}

.filters-active-pill button:hover {
    background: rgba(212, 175, 55, 0.12);
}

/* Animated expand */
.filters-collapse {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    margin-top: 16px;
}

.filters-collapse.active {
    grid-template-rows: 1fr;
}

.filters-collapse__inner {
    min-height: 0;
    overflow: hidden;
}

.filters-collapse.active .filters-collapse__inner {
    overflow: visible;
}

.filters-content.thf-card {
    margin: 0 auto;
    width: 100%;
    max-width: 1320px;
    display: flex;
    flex-direction: column;
    height: auto;
    background: linear-gradient(145deg, rgba(30, 58, 95, 0.76), rgba(21, 42, 69, 0.78));
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 16px;
    backdrop-filter: blur(16px) saturate(120%);
    -webkit-backdrop-filter: blur(16px) saturate(120%);
    box-shadow:
        0 12px 28px -8px rgba(15, 23, 42, 0.4),
        0 1px 0 rgba(255, 255, 255, 0.12) inset;
    overflow: visible;
}

.thf-card__scroll {
    flex: 1;
    overflow: visible;
    padding: 24px 24px 20px;
}

@media (min-width: 769px) {
    .thf-card__scroll {
        padding: 24px 28px 12px;
    }
}

.thf-card__header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.thf-card__header-main h3 {
    margin: 0 0 4px 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #f8fafc;
    letter-spacing: -0.02em;
}

.thf-card__header-main p {
    margin: 0;
    font-size: 14px;
    color: rgba(248, 250, 252, 0.78);
    line-height: 1.45;
    max-width: 36rem;
}

.thf-filter-count {
    font-size: 13px;
    font-weight: 600;
    color: #f8fafc;
    background: rgba(212, 175, 55, 0.2);
    border: 1px solid rgba(212, 175, 55, 0.35);
    padding: 6px 12px;
    border-radius: 999px;
    white-space: nowrap;
}

/* Sections (components) */
.thf-section {
    margin-bottom: 22px;
}

.thf-section__head {
    margin-bottom: 12px;
}

.thf-section__title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(232, 197, 120, 0.95);
}

.thf-section__desc {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: rgba(248, 250, 252, 0.65);
    line-height: 1.4;
}

.thf-section__body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* Responsive field grid inside sections */
.thf-fields-grid {
    display: grid;
    gap: 14px 16px;
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .thf-fields-grid--2 {
        grid-template-columns: repeat(2, 1fr);
    }
    .thf-fields-grid--3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .thf-fields-grid--2 {
        grid-template-columns: repeat(3, 1fr);
    }
    .thf-fields-grid--3 {
        grid-template-columns: repeat(3, 1fr);
    }
}

.thf-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.thf-label {
    font-size: 12px;
    font-weight: 600;
    color: rgba(248, 250, 252, 0.86);
}

.thf-label__optional {
    font-weight: 500;
    color: rgba(248, 250, 252, 0.65);
}

.thf-input,
.thf-select {
    width: 100%;
    min-height: 46px;
    padding: 0 14px;
    font-size: 15px;
    font-weight: 500;
    color: #f8fafc;
    background: rgba(0, 0, 0, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 10px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.thf-input::placeholder {
    color: rgba(248, 250, 252, 0.5);
}

.thf-input:hover,
.thf-select:hover {
    border-color: rgba(255, 255, 255, 0.3);
}

.thf-input:focus,
.thf-select:focus {
    outline: none;
    border-color: rgba(212, 175, 55, 0.65);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

.thf-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 6L11 1' stroke='%23e8c55c' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 40px;
}

.thf-select option {
    color: #f8fafc;
    background: #152a45;
}

.thf-price-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
    align-items: end;
}

.thf-price-row__sep {
    display: none;
}

@media (max-width: 480px) {
    .thf-price-row {
        grid-template-columns: 1fr;
    }
    .thf-price-row__sep {
        display: none;
    }
}

/* Segmented radios */
.thf-field--radios {
    border: 0;
    margin: 0;
    padding: 0;
    min-width: 0;
}

.thf-field--radios .thf-label {
    margin-bottom: 2px;
    padding: 0;
}

.thf-segment {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 4px;
    background: rgba(0, 0, 0, 0.18);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.thf-segment__opt {
    flex: 1 1 0;
    min-width: 72px;
    text-align: center;
    cursor: pointer;
    margin: 0;
}

.thf-segment__opt input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.thf-segment__opt span {
    display: block;
    padding: 10px 12px;
    font-size: 14px;
    font-weight: 600;
    color: rgba(248, 250, 252, 0.82);
    border-radius: 8px;
    transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.thf-segment__opt:hover span {
    color: #fff;
    background: rgba(255, 255, 255, 0.14);
}

.thf-segment__opt input:focus-visible + span {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
}

.thf-segment__opt input:checked + span {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    box-shadow: 0 1px 6px rgba(15, 23, 42, 0.25);
}

/* Toggle pills */
.thf-pill-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.thf-pill {
    position: relative;
    cursor: pointer;
    margin: 0;
}

.thf-pill input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.thf-pill__ui {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 600;
    color: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.2);
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.thf-pill:hover .thf-pill__ui {
    border-color: rgba(212, 175, 55, 0.45);
}

.thf-pill input:focus-visible + .thf-pill__ui {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
}

.thf-pill input:checked + .thf-pill__ui {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.15), rgba(212, 175, 55, 0.08));
    border-color: rgba(212, 175, 55, 0.5);
    color: var(--primary-navy);
}

/* Paying switch */
.thf-switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 14px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.18);
}

.thf-switch-row__text {
    font-size: 14px;
    font-weight: 600;
    color: #f8fafc;
}

.thf-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 28px;
    flex-shrink: 0;
}

.thf-switch__input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.thf-switch__slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: rgba(15, 23, 42, 0.18);
    border-radius: 999px;
    transition: background 0.2s ease;
}

.thf-switch__slider::before {
    content: '';
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    transition: transform 0.2s ease;
}

.thf-switch__input:checked + .thf-switch__slider {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
}

.thf-switch__input:checked + .thf-switch__slider::before {
    transform: translateX(20px);
}

.thf-switch__input:focus-visible + .thf-switch__slider {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
}

.filter-notice {
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(0, 0, 0, 0.2);
}

.filter-notice p {
    color: rgba(248, 250, 252, 0.9);
    font-size: 13px;
    margin: 0 0 10px 0;
    line-height: 1.5;
}

.filter-notice a {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    background: var(--primary-navy);
    color: #fff;
    transition: opacity 0.15s ease;
}

.filter-notice a:hover {
    opacity: 0.92;
}

/* Sticky footer */
.thf-card__footer {
    position: static;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px 16px;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.18) 0%, rgba(0, 0, 0, 0.28) 40%);
    border-top: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.2);
}

@media (min-width: 769px) {
    .thf-card__footer {
        padding: 16px 24px 18px;
    }
}

.thf-card__footer-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.thf-card__footer-right {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-left: auto;
}

.thf-link-reset {
    background: none;
    border: none;
    padding: 8px 6px;
    font-size: 13px;
    font-weight: 600;
    color: rgba(248, 250, 252, 0.75);
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
    border-radius: 4px;
}

.thf-link-reset:hover {
    color: #fff;
}

.filter-btn-apply {
    padding: 12px 24px;
    min-height: 48px;
    background: linear-gradient(135deg, var(--primary-navy), var(--navy-light));
    color: #fff;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 15px;
    letter-spacing: 0.01em;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 14px rgba(30, 58, 95, 0.25);
}

.filter-btn-apply:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(30, 58, 95, 0.3);
}

.filter-btn-apply:active {
    transform: translateY(0);
}

.filter-btn-apply i {
    color: var(--gold-light);
}

.filter-btn-clear {
    padding: 10px 14px;
    min-height: 44px;
    background: transparent;
    color: var(--gray);
    border: 1px solid transparent;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-btn-clear:hover {
    background: rgba(15, 23, 42, 0.05);
    color: var(--primary-navy);
}

.filter-btn-share {
    padding: 10px 16px;
    min-height: 44px;
    background: rgba(255, 255, 255, 0.12);
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.24);
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-btn-share:hover {
    border-color: rgba(212, 175, 55, 0.45);
    background: rgba(212, 175, 55, 0.06);
}

.filter-btn-share.copied {
    border-color: rgba(16, 185, 129, 0.45);
    color: #047857;
    background: rgba(16, 185, 129, 0.08);
}

.filter-btn-share svg {
    stroke: currentColor;
}

.thf-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* ==========================================
   PROPERTIES GRID
   ========================================== */

.properties-section {
    padding: 48px 0;
}

.properties-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 32px;
    margin-bottom: 48px;
}

.property-card {
    background-color: var(--white);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    position: relative;
}

.property-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

/* Property Flag Ribbons */
.property-flag {
    position: absolute;
    top: 16px;
    right: -8px;
    z-index: 10;
    padding: 8px 20px 8px 16px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--white);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    clip-path: polygon(0 0, 100% 0, 100% 100%, 8px 100%, 0 calc(100% - 8px));
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
}

.card-image {
    position: relative;
    overflow: hidden;
    background-color: var(--light-gray);
}

.card-img {
    width: 100%;
    height: 240px;
    object-fit: cover;
    display: block;
    transition: var(--transition);
}

.property-card:hover .card-img {
    transform: scale(1.05);
}

.photo-count {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.95);
    color: var(--primary-navy);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    z-index: 2;
}

.card-content {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex-grow: 1;
}

.property-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--primary-navy);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.property-location {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--gray);
    font-size: 14px;
}

.property-location svg {
    color: var(--gold);
    flex-shrink: 0;
}

.property-description {
    color: var(--gray);
    font-size: 14px;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-footer {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid var(--light-gray);
}

.property-price {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary-navy);
    margin-bottom: 12px;
}

.property-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.badge {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-room {
    background-color: rgba(30, 58, 95, 0.1);
    color: var(--primary-navy);
}

.badge-available {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(232, 197, 92, 0.2));
    color: var(--gold-dark);
}

.badge-date {
    background-color: var(--light-gray);
    color: var(--gray);
}

/* ==========================================
   PAGINATION
   ========================================== */

.pagination {
    display: flex;
    justify-content: space-between;
            align-items: center;
    padding: 32px 0;
    border-top: 1px solid var(--light-gray);
}

.pagination-info {
    color: var(--gray);
    font-size: 15px;
}

.pagination-controls {
    display: flex;
    gap: 8px;
    align-items: center;
}

.page-btn {
    padding: 10px 16px;
    background-color: var(--white);
    border: 2px solid var(--light-gray);
    color: var(--text-dark);
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: var(--transition);
}

.page-btn:hover:not(:disabled) {
    border-color: var(--primary-navy);
    color: var(--primary-navy);
}

.page-btn.active {
    background: linear-gradient(135deg, var(--primary-navy), var(--navy-light));
    color: var(--white);
    border-color: var(--primary-navy);
}

.page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Success message */
.success-message {
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
   RESPONSIVE DESIGN
   ========================================== */

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
        padding: 8px 12px;
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
    
    /* Hero Section */
    .hero-header {
        padding: 48px 0 32px 0;
        min-height: auto;
    }
    
    .hero-title {
        font-size: 32px;
        line-height: 1.2;
        margin-bottom: 12px;
    }
    
    .hero-subtitle {
        font-size: 15px;
        margin-bottom: 24px;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 16px;
        padding: 20px;
    }
    
    .stat-divider {
        display: none;
    }
    
    .stat-number {
        font-size: 28px;
    }
    
    .stat-label {
        font-size: 13px;
    }
    
    /* Filters */
    .filters-section {
        padding: 16px 0;
    }
    
    .filters-toggle {
        width: 100%;
        justify-content: center;
        padding: 14px 18px;
        font-size: 15px;
    }

    .filters-collapse {
        margin-top: 12px;
    }

    .filters-content.thf-card {
        border-radius: 14px;
        max-width: 100%;
    }

    .thf-card__scroll {
        padding: 16px 14px 16px;
    }

    .thf-card__footer {
        flex-direction: column;
        align-items: stretch;
    }

    .thf-card__footer-right {
        margin-left: 0;
        flex-direction: column;
    }

    .filter-btn-apply,
    .filter-btn-share {
        width: 100%;
        justify-content: center;
    }

    .thf-card__footer-left {
        justify-content: space-between;
    }
    
    /* Properties Grid */
    .properties-section {
        padding: 32px 0;
    }
    
    .properties-grid {
        grid-template-columns: 1fr;
        gap: 20px;
            }
            
            .property-card {
        max-width: 100%;
    }
    
    .card-img {
        height: 220px;
    }
    
    .property-flag {
        font-size: 11px;
        padding: 6px 16px 6px 12px;
    }
    
    .card-content {
        padding: 16px;
    }
    
    .card-title {
        font-size: 17px;
        margin-bottom: 8px;
    }
    
    .card-location {
        font-size: 13px;
        margin-bottom: 12px;
    }
    
    .card-price {
        font-size: 22px;
    }
    
    .card-price-label {
        font-size: 13px;
    }
    
    .property-badges {
        gap: 6px;
    }
    
    .badge {
        padding: 5px 10px;
        font-size: 11px;
    }
    
    /* Pagination */
    .pagination {
                flex-direction: column;
        align-items: stretch;
        gap: 12px;
        padding: 20px 16px;
    }
    
    .page-info {
        text-align: center;
        font-size: 13px;
    }
    
    .page-links {
                justify-content: center;
        flex-wrap: wrap;
    }
    
    .page-link {
        padding: 8px 12px;
        font-size: 13px;
        min-width: 36px;
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
    
    .logo-text {
        font-size: 16px;
    }
    
    .footer-text {
        font-size: 13px;
    }
    
    .footer-copyright {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 28px;
    }
    
    .hero-subtitle {
        font-size: 14px;
    }
    
    .stat-number {
        font-size: 24px;
    }
    
    .card-title {
        font-size: 16px;
    }
    
    .card-price {
        font-size: 20px;
    }
    
    .nav-links {
        justify-content: center;
    }
    
    .properties-grid {
        gap: 16px;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    /* thf-fields-grid--2 handles tablet columns */
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
                <ul class="nav-links">
                    <li><a href="{{ route('properties.index') }}" class="nav-link active">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-width="2"/>
                        </svg>
                        Properties
                    </a></li>
                    <li><a href="{{ route('properties.map') }}" class="nav-link" id="trueholdNavMapLink">
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

    <!-- Hero Header -->
    <header class="hero-header">
        <div class="hero-background">
            <img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1920&q=80" alt="Luxury Property" class="hero-bg-image">
            <div class="hero-overlay"></div>
            </div>
        <div class="hero-content">
            <div class="container">
                <h1 class="hero-title">Find Your Dream Home</h1>
                <p class="hero-subtitle">Discover exceptional properties in prime locations across London</p>
                
                <!-- Stats -->
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number">{{ $properties->total() }}</div>
                        <div class="stat-label">Properties Available</div>
        </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-number">{{ $locations->count() }}+</div>
                        <div class="stat-label">Prime Locations</div>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <div class="stat-number">1000+</div>
                        <div class="stat-label">Happy Clients</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

        <!-- Success Messages -->
        @if(session('success'))
        <div class="container" style="padding-top: 24px;">
            <div class="success-message">
                <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

    @php
        $thfFilterKeys = ['location', 'property_type', 'min_price', 'max_price', 'couples_allowed', 'ensuite', 'agent_name', 'paying_only', 'room_count'];
        $thfActiveCount = collect($thfFilterKeys)->filter(fn ($k) => request()->filled($k))->count();
        $thfCouples = request('couples_allowed');
    @endphp

    <!-- Filters -->
    <section class="filters-section" aria-label="Property search filters">
        <div class="container">
            <div class="filters-toolbar">
                <button type="button" class="filters-toggle" id="filtersToggleBtn" onclick="toggleFilters()" aria-expanded="false" aria-controls="filtersCollapse">
                    <span class="filters-toggle-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                        </svg>
                    </span>
                    <span id="filterToggleText">Refine search</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="chevron" id="filterChevron" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                @if($thfActiveCount > 0)
                    <div class="filters-active-pill">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                        </svg>
                        <span class="filters-active-pill__text">{{ $thfActiveCount }} {{ $thfActiveCount === 1 ? 'filter' : 'filters' }} applied</span>
                        <button type="button" onclick="clearFilters()">Clear all</button>
                    </div>
                @endif
            </div>

            <div id="filtersCollapse" class="filters-collapse" aria-hidden="true">
                <div class="filters-collapse__inner">
                    <form method="GET" action="{{ route('properties.index') }}" class="filters-content thf-card" id="filtersContent" novalidate>
                        <div class="thf-card__scroll">
                            <header class="thf-card__header">
                                <div class="thf-card__header-main">
                                    <h3>Search criteria</h3>
                                    <p>Narrow down listings — results update when you apply or change filters.</p>
                                </div>
                                <span class="thf-filter-count" id="thfFilterCountBadge" data-count="{{ $thfActiveCount }}">{{ $thfActiveCount }} applied</span>
                            </header>

                            @auth
                                <div class="thf-switch-row">
                                    <span class="thf-switch-row__text">Paying agents only <span aria-hidden="true">⚡</span></span>
                                    <label class="thf-switch">
                                        <input type="checkbox" name="paying_only" value="1" class="thf-switch__input" {{ request('paying_only') ? 'checked' : '' }} aria-label="Show only paying agents">
                                        <span class="thf-switch__slider" aria-hidden="true"></span>
                                    </label>
                                </div>
                            @endauth

                            <x-filters.filter-section title="Where" description="Area and home style">
                                <div class="thf-fields-grid thf-fields-grid--2 thf-fields-grid--3">
                                    <x-filters.filter-field label="Location" for="thf_location">
                                        <input type="search"
                                               name="location"
                                               id="thf_location"
                                               class="thf-input"
                                               value="{{ request('location') }}"
                                               placeholder="Type to search areas…"
                                               list="thf-location-list"
                                               autocomplete="off"
                                               inputmode="search"
                                               aria-describedby="thf_location_hint">
                                        <datalist id="thf-location-list">
                                            @foreach($locations as $location)
                                                <option value="{{ $location }}"></option>
                                            @endforeach
                                        </datalist>
                                        <span id="thf_location_hint" class="thf-sr-only">Suggestions appear as you type. You can pick a suggestion or enter your own.</span>
                                    </x-filters.filter-field>

                                    <x-filters.filter-field label="Property type" for="thf_property_type">
                                        <select name="property_type" id="thf_property_type" class="thf-select">
                                            <option value="">All types</option>
                                            @foreach($propertyTypes as $type)
                                                <option value="{{ $type }}" @selected(request('property_type') == $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
                                    </x-filters.filter-field>

                                    @auth
                                        <x-filters.filter-field label="Agent" for="thf_agent_name">
                                            <select name="agent_name" id="thf_agent_name" class="thf-select">
                                                <option value="">All agents</option>
                                                @foreach($agentNames as $agent)
                                                    <option value="{{ $agent }}" @selected(request('agent_name') == $agent)>
                                                        {{ $agent }}@if(isset($agentsWithPaying) && (is_array($agentsWithPaying) ? in_array($agent, $agentsWithPaying) : ($agentsWithPaying->has($agent) ? $agentsWithPaying->get($agent) : $agentsWithPaying->contains($agent)))) ⚡@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </x-filters.filter-field>
                                    @else
                                        @if(request('agent_name'))
                                            <div class="thf-field">
                                                <span class="thf-label">Agent <i class="fas fa-lock" style="opacity:0.6;font-size:11px;" aria-hidden="true"></i></span>
                                                <div class="filter-notice">
                                                    <p><i class="fas fa-user-lock" style="color: var(--gold); margin-right: 6px;" aria-hidden="true"></i>Agent filters are for signed-in users.</p>
                                                    <a href="{{ route('login', ['redirect' => url()->current()]) }}">Sign in to unlock</a>
                                                </div>
                                            </div>
                                        @endif
                                    @endauth
                                </div>
                            </x-filters.filter-section>

                            <x-filters.filter-section title="Budget" description="Monthly rent range (£)">
                                <div class="thf-price-row">
                                    <x-filters.filter-field label="Min" for="thf_min_price">
                                        <input type="number" name="min_price" id="thf_min_price" class="thf-input" value="{{ request('min_price') }}" placeholder="No min" min="0" step="1">
                                    </x-filters.filter-field>
                                    <span class="thf-price-row__sep" aria-hidden="true">—</span>
                                    <x-filters.filter-field label="Max" for="thf_max_price">
                                        <input type="number" name="max_price" id="thf_max_price" class="thf-input" value="{{ request('max_price') }}" placeholder="No max" min="0" step="1">
                                    </x-filters.filter-field>
                                </div>
                            </x-filters.filter-section>

                            <x-filters.filter-section title="Tenant preferences">
                                <x-filters.filter-toggle-group label="Household">
                                    <label class="thf-segment__opt">
                                        <input type="radio" name="couples_allowed" value="" @checked(!in_array($thfCouples, ['yes', 'no'], true))>
                                        <span>Any</span>
                                    </label>
                                    <label class="thf-segment__opt">
                                        <input type="radio" name="couples_allowed" value="yes" @checked($thfCouples === 'yes')>
                                        <span>Couples allowed</span>
                                    </label>
                                    <label class="thf-segment__opt">
                                        <input type="radio" name="couples_allowed" value="no" @checked($thfCouples === 'no')>
                                        <span>Singles only</span>
                                    </label>
                                </x-filters.filter-toggle-group>
                            </x-filters.filter-section>

                            <x-filters.filter-section title="Features">
                                <div class="thf-fields-grid thf-fields-grid--2">
                                    <div class="thf-field">
                                        <span class="thf-label">Room amenities</span>
                                        <div class="thf-pill-row">
                                            <label class="thf-pill">
                                                <input type="checkbox" name="ensuite" value="yes" @checked(request('ensuite') == 'yes')>
                                                <span class="thf-pill__ui">Ensuite only</span>
                                            </label>
                                        </div>
                                    </div>
                                    <x-filters.filter-field label="Bedrooms" for="thf_room_count">
                                        <select name="room_count" id="thf_room_count" class="thf-select">
                                            <option value="">Any</option>
                                            @foreach($roomCounts ?? [] as $count)
                                                @if($count !== null && $count !== '')
                                                    <option value="{{ $count }}" @selected(request('room_count') == $count)>{{ $count }} {{ $count == 1 ? 'room' : 'rooms' }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </x-filters.filter-field>
                                </div>
                            </x-filters.filter-section>
                        </div>

                        <footer class="thf-card__footer">
                            <div class="thf-card__footer-left">
                                <button type="button" class="thf-link-reset" onclick="clearFilters()">Reset all</button>
                            </div>
                            <div class="thf-card__footer-right">
                                <button type="button" onclick="shareFilters()" class="filter-btn-share" title="Copy link with current filters">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M18 8h-3m0 0a2 2 0 11-4 0 2 2 0 014 0zM3 21h18M3 10h18M13 13l5-5m0 0v4m0-4h-4" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span id="shareButtonText">Share</span>
                                </button>
                                <button type="submit" class="filter-btn-apply">
                                    <i class="fas fa-search" aria-hidden="true"></i>
                                    Apply filters
                                </button>
                            </div>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Properties Grid (only this container updates when filters change) -->
    <section class="properties-section">
        <div class="container" id="propertiesResultsContainer">
            @if($properties->count() > 0)
                <div class="properties-grid">
                    @foreach($properties as $property)
                        <a href="{{ route('properties.show', $property->id) }}" class="property-card">
                            @if($property->flag)
                                <span class="property-flag" style="{{ $property->flag_color ? 'background: ' . $property->flag_color : '' }}">
                                    {{ $property->flag }}
                            </span>
            @endif

                            <div class="card-image">
                                    @if($property->high_quality_photos_array && count($property->high_quality_photos_array) > 0)
                                    <img src="{{ $property->high_quality_photos_array[0] }}" alt="{{ $property->title }}" class="card-img">
                                    @elseif($property->first_photo_url && $property->first_photo_url !== 'N/A')
                                    <img src="{{ $property->first_photo_url }}" alt="{{ $property->title }}" class="card-img">
                                    @else
                                    <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800&q=80" alt="{{ $property->title }}" class="card-img">
                                    @endif
                                    
                                    @if(($property->high_quality_photos_array && count($property->high_quality_photos_array) > 0) || $property->photo_count > 0)
                                    <span class="photo-count">
                                            @if($property->high_quality_photos_array && count($property->high_quality_photos_array) > 0)
                                            {{ count($property->high_quality_photos_array) }} photos
                                            @else
                                            {{ $property->photo_count }} photos
                                            @endif
                                            </span>
                                        @endif
                                    </div>
                            <div class="card-content">
                                <h3 class="property-title">{{ Str::limit($property->title, 60) }}</h3>
                                <div class="property-location">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    {{ $property->location ?: 'Location not specified' }}
                                </div>
                                    @if($property->description && $property->description !== 'N/A')
                                    <p class="property-description">{{ Str::limit($property->description, 100) }}</p>
                                    @endif
                                <div class="card-footer">
                                    <div class="property-price">{{ $property->formatted_price }}</div>
                                    <div class="property-badges">
                                        @if($property->property_type)
                                            <span class="badge badge-room">{{ $property->property_type }}</span>
                                        @endif
                                        @if($property->available_date && $property->available_date !== 'N/A')
                                            <span class="badge badge-date">{{ $property->available_date }}</span>
                                        @else
                                            <span class="badge badge-available">Available now</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
                
                <!-- Pagination -->
                    @if($properties->hasPages())
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing {{ $properties->firstItem() ?? 0 }}-{{ $properties->lastItem() ?? 0 }} of {{ $properties->total() }}
                            </div>
                        <div class="pagination-controls">
                                @if($properties->onFirstPage())
                                <button class="page-btn" disabled>Previous</button>
                                @else
                                <a href="{{ $properties->previousPageUrl() }}&{{ http_build_query(request()->except('page')) }}" class="page-btn">Previous</a>
                                @endif
                                
                                @foreach($properties->getUrlRange(max(1, $properties->currentPage() - 2), min($properties->lastPage(), $properties->currentPage() + 2)) as $page => $url)
                                    @if($page == $properties->currentPage())
                                    <button class="page-btn active">{{ $page }}</button>
                                    @else
                                    <a href="{{ $url }}&{{ http_build_query(request()->except('page')) }}" class="page-btn">{{ $page }}</a>
                                    @endif
                                @endforeach
                                
                                @if($properties->hasMorePages())
                                <a href="{{ $properties->nextPageUrl() }}&{{ http_build_query(request()->except('page')) }}" class="page-btn">Next</a>
                                @else
                                <button class="page-btn" disabled>Next</button>
                                @endif
                            </div>
                        </div>
                    @endif
            @else
                <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: 16px; box-shadow: var(--shadow-md);">
                    <i class="fas fa-home" style="font-size: 64px; color: var(--gray); margin-bottom: 24px;"></i>
                    <h3 style="font-size: 24px; font-weight: 700; color: var(--primary-navy); margin-bottom: 12px;">No Properties Found</h3>
                    <p style="color: var(--gray); margin-bottom: 24px;">Try adjusting your filters to see more results.</p>
                    <a href="{{ route('properties.index') }}" class="filter-btn-apply">View All Properties</a>
                </div>
            @endif
    </div>
    </section>

    <script src="{{ asset('js/property-filters-sync.js') }}"></script>
    <script>
        function toggleFilters() {
            const wrap = document.getElementById('filtersCollapse');
            const btn = document.getElementById('filtersToggleBtn');
            const text = document.getElementById('filterToggleText');
            const chevron = document.getElementById('filterChevron');
            if (!wrap) return;
            const open = wrap.classList.toggle('active');
            wrap.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (text) text.textContent = open ? 'Hide search' : 'Refine search';
            if (chevron) chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        }
        
        function clearFilters() {
            if (typeof TrueholdPropertyFilters !== 'undefined') {
                TrueholdPropertyFilters.clearStored();
            }
            window.location.href = '{{ route("properties.index") }}';
        }

        function shareFilters() {
            const form = document.getElementById('filtersContent');
            let params = new URLSearchParams(window.location.search);
            if (form && typeof TrueholdPropertyFilters !== 'undefined') {
                params = new URLSearchParams(TrueholdPropertyFilters.listingFormQueryString(form));
            }

            const filters = {};
            params.forEach((value, key) => {
                if (value != null && String(value).trim() !== '') filters[key] = value;
            });

            fetch('{{ route("properties.share-token") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ filters, view: 'listing' }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('Failed to create share token')))
            .then(data => navigator.clipboard.writeText(data.share_url || window.location.href))
            .then(() => {
                // Visual feedback
                const shareButton = document.querySelector('.filter-btn-share');
                const shareButtonText = document.getElementById('shareButtonText');
                const originalText = shareButtonText.textContent;
                
                shareButton.classList.add('copied');
                shareButtonText.innerHTML = '<i class="fas fa-check"></i> Link Copied!';
                
                // Reset after 2 seconds
                setTimeout(() => {
                    shareButton.classList.remove('copied');
                    shareButtonText.textContent = originalText;
                }, 2000);
            })
            .catch(err => {
                console.error('Failed to create tokenized share link:', err);
                alert('Unable to create share link right now. Please try again.');
            });
        }
        
        // Auto-apply filters on change: update only the results container (no full page reload)
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('filtersContent');
            const container = document.getElementById('propertiesResultsContainer');
            if (!form || !container) return;

            function getFilterQueryString() {
                if (typeof TrueholdPropertyFilters !== 'undefined') {
                    return TrueholdPropertyFilters.listingFormQueryString(form);
                }
                const formData = new FormData(form);
                const params = new URLSearchParams();
                formData.forEach((value, key) => {
                    if (value != null && value !== '') params.set(key, value);
                });
                return params.toString();
            }

            function updateThfFilterCountBadge() {
                const badge = document.getElementById('thfFilterCountBadge');
                if (!badge || !form) return;
                const fd = new FormData(form);
                const keys = ['location', 'property_type', 'min_price', 'max_price', 'couples_allowed', 'ensuite', 'agent_name', 'paying_only', 'room_count'];
                let n = 0;
                keys.forEach((k) => {
                    const v = fd.get(k);
                    if (v != null && String(v).trim() !== '') n++;
                });
                badge.textContent = n + ' applied';
                badge.setAttribute('data-count', String(n));
                badge.style.display = n ? '' : 'none';
            }

            function loadResults() {
                const qs = getFilterQueryString();
                const url = '{{ route("properties.index") }}' + (qs ? '?' + qs : '');
                container.style.opacity = '0.6';
                container.style.pointerEvents = 'none';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('propertiesResultsContainer');
                        if (newContainer) {
                            container.innerHTML = newContainer.innerHTML;
                        }
                        history.pushState({}, '', url);
                        if (typeof TrueholdPropertyFilters !== 'undefined') {
                            TrueholdPropertyFilters.saveFromLocationSearch();
                        }
                        updateThfFilterCountBadge();
                    })
                    .catch(() => { window.location.href = url; })
                    .finally(() => {
                        container.style.opacity = '';
                        container.style.pointerEvents = '';
                    });
            }

            updateThfFilterCountBadge();

            if (typeof TrueholdPropertyFilters !== 'undefined') {
                if (!TrueholdPropertyFilters.hasFilterParamsInSearch()) {
                    const stored = TrueholdPropertyFilters.getStoredQueryString();
                    if (stored) {
                        TrueholdPropertyFilters.applyQueryStringToListingForm(form, stored);
                        const u = '{{ route("properties.index") }}' + '?' + stored;
                        history.replaceState({}, '', u);
                        loadResults();
                    }
                } else {
                    TrueholdPropertyFilters.saveFromLocationSearch();
                }
            }

            const mapNav = document.getElementById('trueholdNavMapLink');
            if (mapNav && typeof TrueholdPropertyFilters !== 'undefined') {
                mapNav.addEventListener('click', function (e) {
                    e.preventDefault();
                    const qs = TrueholdPropertyFilters.listingFormQueryString(form) || TrueholdPropertyFilters.getStoredQueryString();
                    TrueholdPropertyFilters.saveFromQueryString(qs);
                    window.location.href = '{{ route("properties.map") }}' + (qs ? '?' + qs : '');
                });
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                loadResults();
            });
            let priceDebounce;
            let locationDebounce;
            form.querySelectorAll('.thf-select').forEach(sel => {
                sel.addEventListener('change', () => { updateThfFilterCountBadge(); loadResults(); });
            });
            form.querySelectorAll('.thf-input[type="number"]').forEach(inp => {
                inp.addEventListener('input', () => {
                    clearTimeout(priceDebounce);
                    priceDebounce = setTimeout(() => { updateThfFilterCountBadge(); loadResults(); }, 400);
                });
                inp.addEventListener('change', () => { updateThfFilterCountBadge(); loadResults(); });
            });
            const locInput = document.getElementById('thf_location');
            if (locInput) {
                locInput.addEventListener('input', () => {
                    clearTimeout(locationDebounce);
                    locationDebounce = setTimeout(() => { updateThfFilterCountBadge(); loadResults(); }, 450);
                });
                locInput.addEventListener('change', () => { updateThfFilterCountBadge(); loadResults(); });
            }
            form.querySelectorAll('input[name="couples_allowed"]').forEach(r => {
                r.addEventListener('change', () => { updateThfFilterCountBadge(); loadResults(); });
            });
            form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', () => { updateThfFilterCountBadge(); loadResults(); });
            });

            // Dismiss filters when clicking outside the container.
            document.addEventListener('mousedown', (e) => {
                const wrap = document.getElementById('filtersCollapse');
                const toggleBtn = document.getElementById('filtersToggleBtn');
                if (!wrap || !wrap.classList.contains('active')) return;
                const card = document.getElementById('filtersContent');
                if (!card) return;
                const clickedInsidePanel = card.contains(e.target);
                const clickedToggle = toggleBtn && toggleBtn.contains(e.target);
                if (!clickedInsidePanel && !clickedToggle) {
                    toggleFilters();
                }
            });

            // Store current URL when clicking on property cards (delegation so AJAX-loaded cards work)
            document.addEventListener('click', (e) => {
                if (e.target.closest('.property-card')) {
                    sessionStorage.setItem('propertyListingUrl', window.location.href);
                }
            });
        });
    </script>
</body>
</html>
