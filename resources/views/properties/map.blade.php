<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Browse properties on an interactive map - TrueHold Premium Property Listings">
    <meta name="theme-color" content="#1e3a5f">
    <title>Map View - TrueHold</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/truehold-logo.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/truehold-logo.jpg') }}">
    
    <link href="{{ asset('css/font-awesome.min.css') }}" rel="stylesheet">
    
    <style>
/* ==========================================
   TRUEHOLD - Map View
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
    overflow: hidden;
}

.map-page {
    overflow: hidden;
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
    z-index: 1001;
    border-bottom: 1px solid rgba(30, 58, 95, 0.06);
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
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
   MAP HEADER
   ========================================== */

.map-header {
    background: linear-gradient(135deg, var(--primary-navy) 0%, var(--navy-light) 100%);
    color: var(--white);
    padding: 24px 0;
    box-shadow: 0 4px 16px rgba(30, 58, 95, 0.15);
    position: relative;
    z-index: 10;
}

.map-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.map-title-section {
    display: flex;
    align-items: center;
    gap: 24px;
}

.map-title {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
}

.properties-loaded {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50px;
    font-size: 15px;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.properties-loaded svg {
    color: var(--gold);
}

.map-header-actions {
    display: flex;
    gap: 12px;
}

.btn-list-view {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: var(--transition);
}

.btn-list-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(212, 175, 55, 0.4);
}

/* ==========================================
   FILTERS SECTION — Premium (navy + gold)
   ========================================== */

.filters-section {
    padding: 28px 0 32px;
    background: linear-gradient(180deg, #fafbfc 0%, var(--white) 40%, var(--off-white) 100%);
    border-bottom: 1px solid rgba(30, 58, 95, 0.08);
    position: relative;
    z-index: 10;
}

.filters-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--gold), var(--gold-light), var(--gold), transparent);
    opacity: 0.85;
}

.filters-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 16px;
    flex-wrap: wrap;
}

.filters-toggle {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 14px 26px;
    background: var(--white);
    border: 1px solid rgba(30, 58, 95, 0.12);
    border-radius: 12px;
    color: var(--primary-navy);
    font-weight: 600;
    font-size: 15px;
    letter-spacing: 0.02em;
    transition: var(--transition);
    width: fit-content;
    cursor: pointer;
    box-shadow: 0 2px 12px rgba(30, 58, 95, 0.06), 0 1px 0 rgba(255, 255, 255, 0.8) inset;
}

.filters-toggle:hover {
    border-color: rgba(212, 175, 55, 0.45);
    box-shadow: 0 4px 20px rgba(30, 58, 95, 0.08), 0 0 0 1px rgba(212, 175, 55, 0.15);
}

.filters-toggle-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(30, 58, 95, 0.08), rgba(30, 58, 95, 0.04));
    color: var(--gold);
    flex-shrink: 0;
}

.filters-toggle-icon svg {
    width: 20px;
    height: 20px;
}

.filters-toggle .chevron {
    margin-left: 4px;
    color: var(--primary-navy);
    opacity: 0.55;
    transition: transform 0.3s ease;
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
}

.filters-active-pill__text {
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
    border-radius: 4px;
}

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

.thf-card__scroll {
    flex: 1;
    overflow: visible;
    padding: 24px 24px 20px;
}

.thf-card__header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}

.thf-card__header-main h3 {
    margin: 0 0 4px 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #f8fafc;
}

.thf-card__header-main p {
    margin: 0;
    font-size: 14px;
    color: rgba(248, 250, 252, 0.78);
    line-height: 1.45;
}

.thf-filter-count {
    font-size: 13px;
    font-weight: 600;
    color: #f8fafc;
    background: rgba(212, 175, 55, 0.2);
    border: 1px solid rgba(212, 175, 55, 0.35);
    padding: 6px 12px;
    border-radius: 999px;
}

.thf-section { margin-bottom: 22px; }
.thf-section__head { margin-bottom: 12px; }
.thf-section__title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(232, 197, 120, 0.95);
}
.thf-section__desc { margin: 4px 0 0 0; font-size: 13px; color: rgba(248, 250, 252, 0.65); }
.thf-section__body { display: flex; flex-direction: column; gap: 14px; }

.thf-fields-grid { display: grid; gap: 14px 16px; grid-template-columns: 1fr; }
@media (min-width: 640px) {
    .thf-fields-grid--2 { grid-template-columns: repeat(2, 1fr); }
    .thf-fields-grid--3 { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .thf-fields-grid--2 { grid-template-columns: repeat(3, 1fr); }
    .thf-fields-grid--3 { grid-template-columns: repeat(3, 1fr); }
}

.thf-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.thf-label { font-size: 12px; font-weight: 600; color: rgba(248, 250, 252, 0.86); }

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
}
.thf-input::placeholder { color: rgba(248, 250, 252, 0.5); }
.thf-input:focus, .thf-select:focus { outline: none; border-color: rgba(212, 175, 55, 0.65); box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); }
.thf-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 6L11 1' stroke='%23e8c55c' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 40px;
}
.thf-select option { color: #f8fafc; background: #152a45; }

.thf-price-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; align-items: end; }
.thf-price-row__sep { display: none; }

.thf-segment { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px; background: rgba(0, 0, 0, 0.18); border-radius: 12px; border: 1px solid rgba(255,255,255,.12); }
.thf-segment__opt { flex: 1 1 0; min-width: 72px; text-align: center; cursor: pointer; margin: 0; }
.thf-segment__opt input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
.thf-segment__opt span { display: block; padding: 10px 12px; font-size: 14px; font-weight: 600; color: rgba(248,250,252,.82); border-radius: 8px; }
.thf-segment__opt input:checked + span { background: rgba(255,255,255,.2); color: #fff; }

.thf-pill-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.thf-pill { position: relative; cursor: pointer; margin: 0; }
.thf-pill input { position: absolute; opacity: 0; width: 0; height: 0; }
.thf-pill__ui { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; font-size: 14px; font-weight: 600; color: rgba(248,250,252,.9); border: 1px solid rgba(255,255,255,.2); border-radius: 999px; background: rgba(0,0,0,.2); }
.thf-pill input:checked + .thf-pill__ui { background: linear-gradient(135deg, rgba(212,175,55,.15), rgba(212,175,55,.08)); border-color: rgba(212,175,55,.5); color: #fff; }

.thf-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 14px; border: 1px solid rgba(255,255,255,.18); border-radius: 12px; background: rgba(0,0,0,.18); }
.thf-switch-row__text { font-size: 14px; font-weight: 600; color: #f8fafc; }
.thf-switch { position: relative; display: inline-block; width: 48px; height: 28px; }
.thf-switch__input { opacity: 0; width: 0; height: 0; position: absolute; }
.thf-switch__slider { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.18); border-radius: 999px; }
.thf-switch__slider::before { content:''; position:absolute; height:22px; width:22px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:transform .2s ease; }
.thf-switch__input:checked + .thf-switch__slider { background: linear-gradient(135deg, var(--gold), var(--gold-light)); }
.thf-switch__input:checked + .thf-switch__slider::before { transform: translateX(20px); }

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
}
.thf-card__footer-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.thf-card__footer-right { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-left: auto; }
.thf-link-reset { background:none; border:none; padding:8px 6px; font-size:13px; font-weight:600; color:rgba(248,250,252,.75); text-decoration:underline; text-underline-offset:3px; cursor:pointer; }

.filters-content {
    display: none;
    margin: 24px auto 0;
    width: 100%;
    max-width: 1320px;
    height: auto;
    display: flex;
    flex-direction: column;
    padding: 0;
    background: linear-gradient(150deg, rgba(30, 58, 95, 0.78), rgba(21, 42, 69, 0.8));
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(16px) saturate(120%);
    -webkit-backdrop-filter: blur(16px) saturate(120%);
    box-shadow:
        0 10px 28px rgba(15, 23, 42, 0.45),
        0 1px 0 rgba(255, 255, 255, 0.12) inset;
    overflow: visible;
    position: relative;
    z-index: 100;
}

.filters-content.active {
    display: block;
}

.filters-panel-inner {
    padding: 28px 32px 8px;
    overflow: visible;
    flex: 1;
}

.filters-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 28px;
    padding-bottom: 22px;
    border-bottom: 1px solid rgba(212, 175, 55, 0.18);
}

.filters-header-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(212, 175, 55, 0.12);
    border: 1px solid rgba(212, 175, 55, 0.25);
    color: var(--gold);
}

.filters-header-icon svg {
    width: 24px;
    height: 24px;
}

.filters-header-text h3 {
    color: var(--white);
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 6px 0;
    letter-spacing: -0.02em;
}

.filters-header-text p {
    margin: 0;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.55);
    font-weight: 500;
    line-height: 1.45;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px 24px;
    margin-bottom: 8px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.filter-label {
    color: rgba(232, 197, 120, 0.95);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.filter-input {
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    color: var(--white);
    background: rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(8px);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.filter-input::placeholder {
    color: rgba(255, 255, 255, 0.38);
}

.filter-input:focus {
    outline: none;
    border-color: rgba(212, 175, 55, 0.55);
    background: rgba(0, 0, 0, 0.28);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.12);
}

select.filter-input {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 6L11 1' stroke='%23d4af37' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-color: rgba(0, 0, 0, 0.2);
    background-position: right 14px center;
    padding-right: 40px;
}

select.filter-input:focus {
    background-color: rgba(0, 0, 0, 0.28);
}

select.filter-input option {
    background-color: #152a45;
    color: var(--white);
}

.filter-check-stack {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-check-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease;
    font-size: 14px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.92);
}

.filter-check-row:hover {
    border-color: rgba(212, 175, 55, 0.3);
    background: rgba(0, 0, 0, 0.22);
}

.filter-check-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    accent-color: var(--gold);
    cursor: pointer;
    border-radius: 4px;
}

/* Paying agents — icon toggle */
.paying-filter-wrap {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.paying-filter-checkbox {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    min-height: 52px;
    padding: 12px 16px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.2);
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.paying-filter-checkbox:hover {
    border-color: rgba(212, 175, 55, 0.4);
    background: rgba(0, 0, 0, 0.28);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
}

.paying-filter-checkbox input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.paying-filter-checkbox .checkbox-icon {
    color: rgba(255, 255, 255, 0.35);
    transition: var(--transition);
}

.paying-filter-checkbox:hover .checkbox-icon {
    color: rgba(255, 255, 255, 0.55);
}

.paying-filter-checkbox input[type="checkbox"]:checked ~ .checkbox-icon {
    color: var(--gold);
    filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.55));
}

.filter-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
    padding: 24px 32px 28px;
    margin: 8px -32px -32px;
    background: rgba(0, 0, 0, 0.18);
    border-top: 1px solid rgba(212, 175, 55, 0.12);
}

.filter-btn-apply {
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--primary-navy);
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 12px rgba(212, 175, 55, 0.25);
}

.filter-btn-apply:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(212, 175, 55, 0.35);
}

.filter-btn-apply svg {
    stroke: currentColor;
}

.filter-btn-clear {
    padding: 14px 22px;
    background: transparent;
    color: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-btn-clear:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.35);
}

.filter-btn-clear svg {
    stroke: currentColor;
}

/* ==========================================
   MAP WRAPPER (mobile: in-flow; desktop: contains fixed map)
   ========================================== */

.map-wrapper {
    position: relative;
}

/* ==========================================
   MAP CONTROLS
   ========================================== */

.map-controls {
    position: fixed;
    top: 285px;
    left: 24px;
    z-index: 1000;
    display: flex;
    gap: 8px;
    background: var(--white);
    padding: 6px;
    border-radius: 10px;
    box-shadow: var(--shadow-md);
}

.map-control-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background-color: var(--white);
    border: 2px solid transparent;
    border-radius: 8px;
    color: var(--text-dark);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
}

.map-control-btn:hover {
    background-color: var(--off-white);
}

.map-control-btn.active {
    background-color: var(--primary-navy);
    color: var(--white);
    border-color: var(--primary-navy);
}

.map-control-btn.active svg {
    stroke: var(--gold);
}


/* ==========================================
   MAP CONTAINER
   ========================================== */

.map-container {
    position: fixed;
    top: 270px;
            left: 0;
            right: 0;
            bottom: 0;
    background-color: var(--light-gray);
            z-index: 1;
        }
        
#map {
    width: 100%;
    height: 100%;
}

.map-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    background: linear-gradient(135deg, #e6f2ff 0%, #f0f7ff 100%);
    color: var(--primary-navy);
}

.map-placeholder svg {
    stroke: var(--primary-navy);
    opacity: 0.3;
}

.map-placeholder p {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
}

.map-placeholder small {
    font-size: 14px;
    color: var(--gray);
    max-width: 300px;
    text-align: center;
}

/* Loading Screen (inside map-wrapper so it covers map area) */
.loading-screen {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid var(--light-gray);
    border-top-color: var(--gold);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    margin-top: 20px;
    font-size: 18px;
            font-weight: 600;
    color: var(--primary-navy);
}

/* Info Window Styling */
.gm-style .gm-style-iw-c {
    border-radius: 10px;
    padding: 0 !important;
    max-width: 240px !important;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
    overflow: hidden !important;
}

.gm-style .gm-style-iw-d {
    overflow: hidden !important;
    padding: 0 !important;
    max-width: 240px !important;
}

.gm-style .gm-style-iw-t::after {
    background: linear-gradient(45deg, var(--white) 50%, transparent 51%, transparent) !important;
    box-shadow: -2px 2px 2px 0 rgba(0, 0, 0, 0.1) !important;
}

.gm-style-iw-tc::after {
    background: linear-gradient(45deg, var(--white) 50%, transparent 51%, transparent) !important;
}

/* Remove the default InfoWindow "tail" so the card sits close to the marker */
.gm-style .gm-style-iw-tc {
    display: none !important;
}

/* Custom overlay card (pixel-perfect anchored to marker, stable on zoom) */
.property-card-overlay {
    position: absolute;
    z-index: 2000;
    transform: translate(-50%, calc(-100% - 2px)); /* 2px gap above marker */
    pointer-events: auto;
}

.property-card-overlay .info-window-card {
    max-width: 187px; /* 30% wider vs 144px */
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}

.property-card-overlay .info-window-image {
    height: 96px; /* 20% shorter vs 120px */
}

.property-card-overlay .info-window-content {
    padding: 10px;
}

.property-card-overlay .info-window-header {
    margin-bottom: 6px;
    padding-bottom: 6px;
}

.property-card-overlay .info-window-title {
    font-size: 11px;
    margin-bottom: 3px;
    -webkit-line-clamp: 2;
}

.property-card-overlay .info-window-price {
    font-size: 13px;
}

.property-card-overlay .info-window-details {
    gap: 4px;
    margin-bottom: 6px;
}

.property-card-overlay .info-window-detail-item {
    font-size: 9px;
    gap: 6px;
}

.property-card-overlay .info-window-footer {
    padding-top: 6px;
}

.property-card-overlay .info-window-btn {
    padding: 6px 10px;
    font-size: 9px;
    border-radius: 6px;
}

.property-card-overlay .info-window-btn svg {
    width: 10px;
    height: 10px;
}

.property-card-overlay .overlay-close {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 30px;
    height: 30px;
    border-radius: 999px;
    border: 1px solid rgba(30, 58, 95, 0.12);
    background: rgba(255,255,255,0.96);
    color: var(--primary-navy);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.property-card-overlay .overlay-close:hover {
    background: var(--primary-navy);
    color: var(--white);
}

.info-window-card {
    background: var(--white);
    overflow: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-window-image {
            width: 100%;
    height: 120px;
    object-fit: cover;
    background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
    display: block;
}

.info-window-content {
    padding: 12px;
}

.info-window-header {
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
}

.info-window-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--primary-navy);
    margin-bottom: 4px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
            overflow: hidden;
}

.info-window-price {
    font-size: 15px;
    font-weight: 700;
    color: var(--gold);
    display: flex;
    align-items: center;
    gap: 2px;
}

.info-window-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 8px;
}

.info-window-detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 9px;
    color: var(--gray);
}

.info-window-detail-item svg {
    width: 11px;
    height: 11px;
    color: var(--gold);
    flex-shrink: 0;
}

.info-window-detail-item strong {
    color: var(--text-dark);
    font-weight: 600;
}

.info-window-footer {
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
}

.info-window-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    width: 100%;
    padding: 7px 14px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--white);
    border-radius: 6px;
    font-weight: 600;
    font-size: 10px;
    text-decoration: none;
    transition: var(--transition);
    box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
}

.info-window-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 16px rgba(212, 175, 55, 0.4);
}

.info-window-btn svg {
    width: 11px;
    height: 11px;
}

/* Close button styling */
.gm-style-iw-c button.gm-ui-hover-effect,
.gm-style-iw button.gm-ui-hover-effect {
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background: var(--white) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
    opacity: 1 !important;
    top: 6px !important;
    right: 6px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.2s ease !important;
    padding: 0 !important;
    border: 1px solid rgba(30, 58, 95, 0.1) !important;
}

.gm-style-iw-c button.gm-ui-hover-effect:hover,
.gm-style-iw button.gm-ui-hover-effect:hover {
    background: var(--primary-navy) !important;
    box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3) !important;
    transform: scale(1.08) !important;
    border-color: var(--primary-navy) !important;
}

/* Style the close button icon span (mask-image) */
.gm-style-iw-c button.gm-ui-hover-effect span,
.gm-style-iw button.gm-ui-hover-effect span {
    background-color: var(--primary-navy) !important;
    width: 20px !important;
    height: 20px !important;
    margin: 6px !important;
    transition: background-color 0.2s ease !important;
}

.gm-style-iw-c button.gm-ui-hover-effect:hover span,
.gm-style-iw button.gm-ui-hover-effect:hover span {
    background-color: var(--white) !important;
}

/* Ensure button is properly positioned */
        .gm-style-iw-c {
    position: relative !important;
}

.gm-style-iw-c button[aria-label="Close"],
.gm-style-iw button[aria-label="Close"] {
    position: absolute !important;
    top: 6px !important;
    right: 6px !important;
    z-index: 1000 !important;
}

/* (tail removed above) */

/* ==========================================
   RESPONSIVE DESIGN
   ========================================== */

@media (max-width: 768px) {
    body,
    .map-page {
        overflow-x: hidden;
        overflow-y: auto;
    }

    .map-header {
        padding: 16px 0;
    }

    .map-header-content {
        flex-wrap: wrap;
    }

    .map-title {
        font-size: 24px;
    }

    .properties-loaded {
        font-size: 14px;
    }

    /* Mobile: map area in document flow so no overlap/gap with header/filters */
    .map-wrapper {
        position: relative;
        width: 100%;
        height: min(65vh, 520px);
        min-height: 320px;
        flex-shrink: 0;
    }

    .map-controls {
        position: absolute;
        top: 12px;
        left: 12px;
        right: auto;
        z-index: 1000;
    }

    .map-control-btn {
        padding: 9px 14px;
        font-size: 13px;
    }

    .map-container {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
    }

    .nav-links {
        gap: 4px;
    }

    .nav-link {
        padding: 10px 12px;
        font-size: 13px;
    }

    /* Filters */
    .filters-section {
        padding: 16px 0 20px;
    }

    .filters-toggle {
        width: 100%;
        justify-content: center;
        padding: 14px 20px;
        font-size: 15px;
    }

    .filters-content {
        margin-top: 12px;
        max-width: 100%;
        border-radius: 16px;
    }

    .filters-panel-inner {
        padding: 20px 16px 16px;
    }

    .filters-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 18px;
    }

    .filters-header-icon {
        width: 44px;
        height: 44px;
    }

    .filters-header-text h3 {
        font-size: 18px;
    }

    .filters-header-text p {
        font-size: 13px;
    }

    .filter-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .filter-input {
        padding: 12px 14px;
        font-size: 14px;
    }

    .paying-filter-checkbox {
        min-height: 48px;
        padding: 12px 14px;
    }

    .paying-filter-checkbox .checkbox-icon {
        width: 22px;
        height: 22px;
    }

    .filter-actions {
        flex-direction: column;
        margin: 12px -16px -20px;
        padding: 20px 16px 22px;
    }

    .filter-btn-apply,
    .filter-btn-clear {
        width: 100%;
        justify-content: center;
    }

    /* Info window responsive adjustments */
    .gm-style .gm-style-iw-c {
        max-width: 210px !important;
    }

    .info-window-image {
        height: 100px;
    }

    .info-window-content {
        padding: 10px;
    }

    .info-window-title {
        font-size: 11px;
    }

    .info-window-price {
        font-size: 13px;
    }

    .info-window-detail-item {
        font-size: 9px;
        gap: 5px;
    }

    .info-window-btn {
        padding: 6px 12px;
        font-size: 9px;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 16px;
    }
    
    .map-title-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .logo-text {
        font-size: 16px;
    }
        }
    </style>
</head>
<body class="map-page">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="{{ route('properties.index') }}" class="logo js-truehold-to-listing">
                    <div class="logo-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-width="2"/>
                            <path d="M9 22V12h6v10" stroke-width="2"/>
                        </svg>
                        </div>
                    <span class="logo-text">TRUEHOLD</span>
                </a>
                <ul class="nav-links">
                    <li><a href="{{ route('properties.index') }}" class="nav-link js-truehold-to-listing">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-width="2"/>
                        </svg>
                        Properties
                    </a></li>
                    <li><a href="{{ route('properties.map') }}" class="nav-link active">
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

    <!-- Map Header -->
    <div class="map-header">
        <div class="container">
            <div class="map-header-content">
                <div class="map-title-section">
                    <h1 class="map-title">Property Map</h1>
                    <div class="properties-loaded">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                        </svg>
                        <span id="propertyCount">{{ $properties->count() }} properties loaded</span>
            </div>
        </div>
                <div class="map-header-actions">
                    <a href="{{ route('properties.index') }}" class="btn-list-view js-truehold-to-listing">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        List View
                    </a>
                    </div>
                </div>
            </div>
    </div>

    @php
        $thfFilterKeys = ['location', 'property_type', 'min_price', 'max_price', 'couples_allowed', 'ensuite', 'agent_name', 'paying_only', 'room_count'];
        $thfActiveCount = collect($thfFilterKeys)->filter(fn ($k) => request()->filled($k))->count();
        $thfCouples = request('couples_allowed');
    @endphp

    <!-- Filters -->
    <section class="filters-section" aria-label="Property search filters">
        <div class="container">
            <div class="filters-toolbar">
                <button type="button" id="filtersToggleBtn" class="filters-toggle" onclick="toggleMapFilters()" aria-expanded="false" aria-controls="filtersCollapse">
                    <span class="filters-toggle-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                        </svg>
                    </span>
                    <span id="filterToggleText">Refine search</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="chevron" id="filterChevron">
                        <path d="M6 9l6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                @if($thfActiveCount > 0)
                    <div class="filters-active-pill">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                        </svg>
                        <span class="filters-active-pill__text">{{ $thfActiveCount }} {{ $thfActiveCount === 1 ? 'filter' : 'filters' }} applied</span>
                        <button type="button" onclick="clearMapFilters()">Clear all</button>
                    </div>
                @endif
            </div>

            <div id="filtersCollapse" class="filters-collapse" aria-hidden="true">
                <div class="filters-collapse__inner">
                    <form class="filters-content thf-card" id="filtersContent" novalidate onsubmit="event.preventDefault(); applyMapFilters();">
                        <div class="thf-card__scroll">
                            <header class="thf-card__header">
                                <div class="thf-card__header-main">
                                    <h3>Search criteria</h3>
                                    <p>Narrow down listings — map pins update when you apply or change filters.</p>
                                </div>
                                <span class="thf-filter-count" id="thfFilterCountBadge" data-count="{{ $thfActiveCount }}">{{ $thfActiveCount }} applied</span>
                            </header>

                            @auth
                                <div class="thf-switch-row">
                                    <span class="thf-switch-row__text">Paying agents only <span aria-hidden="true">⚡</span></span>
                                    <label class="thf-switch">
                                        <input type="checkbox" id="filterPayingOnly" name="paying_only" value="1" class="thf-switch__input" {{ request('paying_only') ? 'checked' : '' }} aria-label="Show only paying agents">
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
                                               inputmode="search">
                                        <datalist id="thf-location-list">
                                            @foreach($locations ?? [] as $location)
                                                <option value="{{ $location }}"></option>
                                            @endforeach
                                        </datalist>
                                    </x-filters.filter-field>

                                    <x-filters.filter-field label="Property type" for="thf_property_type">
                                        <select name="property_type" id="thf_property_type" class="thf-select">
                                            <option value="">All types</option>
                                            @foreach($propertyTypes ?? [] as $type)
                                                @if($type && $type !== 'N/A')
                                                    <option value="{{ $type }}" @selected(request('property_type') == $type)>{{ $type }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </x-filters.filter-field>

                                    @auth
                                        <x-filters.filter-field label="Agent" for="thf_agent_name">
                                            <select name="agent_name" id="thf_agent_name" class="thf-select">
                                                <option value="">All agents</option>
                                                @foreach($agentNames ?? [] as $agentName)
                                                    @if($agentName && $agentName !== 'N/A')
                                                        <option value="{{ $agentName }}" @selected(request('agent_name') == $agentName)>
                                                            {{ $agentName }}
                                                            @if(isset($agentsWithPaying) && (is_array($agentsWithPaying) ? in_array($agentName, $agentsWithPaying) : ($agentsWithPaying->has($agentName) ? $agentsWithPaying->get($agentName) : $agentsWithPaying->contains($agentName))))
                                                                ⚡
                                                            @endif
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </x-filters.filter-field>
                                    @endauth
                                </div>
                            </x-filters.filter-section>

                            <x-filters.filter-section title="Budget" description="Monthly rent range (£)">
                                <div class="thf-price-row">
                                    <x-filters.filter-field label="Min" for="thf_min_price">
                                        <input type="number" id="thf_min_price" name="min_price" class="thf-input" value="{{ request('min_price') }}" placeholder="No min" min="0" step="1">
                                    </x-filters.filter-field>
                                    <span class="thf-price-row__sep" aria-hidden="true">—</span>
                                    <x-filters.filter-field label="Max" for="thf_max_price">
                                        <input type="number" id="thf_max_price" name="max_price" class="thf-input" value="{{ request('max_price') }}" placeholder="No max" min="0" step="1">
                                    </x-filters.filter-field>
                                </div>
                            </x-filters.filter-section>

                            <x-filters.filter-section title="Tenant preferences">
                                <x-filters.filter-toggle-group label="Household">
                                    <label class="thf-segment__opt">
                                        <input type="radio" name="couples_allowed" id="filterCouplesAny" value="" @checked(!in_array($thfCouples, ['yes', 'no'], true))>
                                        <span>Any</span>
                                    </label>
                                    <label class="thf-segment__opt">
                                        <input type="radio" name="couples_allowed" id="filterCouplesYes" value="yes" @checked($thfCouples === 'yes')>
                                        <span>Couples allowed</span>
                                    </label>
                                    <label class="thf-segment__opt">
                                        <input type="radio" name="couples_allowed" id="filterCouplesNo" value="no" @checked($thfCouples === 'no')>
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
                                                <input type="checkbox" id="filterEnsuite" name="ensuite" value="yes" @checked(request('ensuite') == 'yes')>
                                                <span class="thf-pill__ui">Ensuite only</span>
                                            </label>
                                        </div>
                                    </div>
                                    <x-filters.filter-field label="Bedrooms" for="thf_room_count">
                                        <select id="thf_room_count" name="room_count" class="thf-select">
                                            <option value="">Any</option>
                                            @foreach($roomCounts ?? [] as $count)
                                                @if($count !== null && $count !== '')
                                                    <option value="{{ $count }}" @selected(request('room_count') == (string)$count)>{{ $count }} {{ $count == 1 ? 'room' : 'rooms' }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </x-filters.filter-field>
                                </div>
                            </x-filters.filter-section>
                        </div>

                        <footer class="thf-card__footer">
                            <div class="thf-card__footer-left">
                                <button type="button" class="thf-link-reset" onclick="clearMapFilters()">Reset all</button>
                            </div>
                            <div class="thf-card__footer-right">
                                <button type="button" onclick="shareMapFilters()" class="filter-btn-share" title="Copy link with current filters">
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

    <!-- Map wrapper: on mobile keeps map + controls in flow; on desktop they stay fixed -->
    <div class="map-wrapper">
        <!-- Map Controls -->
        <div class="map-controls">
            <button class="map-control-btn active" id="mapViewBtn" onclick="toggleMapView('roadmap')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4-7 4z" stroke-width="2"/>
                </svg>
                Map
            </button>
            <button class="map-control-btn" id="satelliteViewBtn" onclick="toggleMapView('hybrid')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <path d="M12 2v20M2 12h20" stroke-width="2"/>
                </svg>
                Satellite
            </button>
        </div>

        <!-- Loading Screen -->
        <div class="loading-screen" id="loadingScreen">
            <div class="loading-spinner"></div>
            <div class="loading-text">Loading properties...</div>
        </div>

        <!-- Map Container -->
        <div class="map-container">
            <div id="map"></div>
        </div>
    </div>

    <!-- Properties Data (hidden) -->
    <div id="properties-data" style="display: none;">{!! json_encode($propertiesForJson ?? $properties->map(fn($p) => $p->toArray())) !!}</div>

    <!-- Google Maps API -->
    @if(config('services.google.maps_api_key') && config('services.google.maps_api_key') !== 'YOUR_GOOGLE_MAPS_API_KEY')
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initMap&v=weekly&loading=async"></script>
    @else
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('loadingScreen').innerHTML = '<div style="text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i><div class="loading-text" style="color: #ef4444;">Google Maps API key not configured</div><p style="color: #6b7280; margin-top: 8px;">Please add GOOGLE_MAPS_API_KEY to your .env file</p></div>';
            });
        </script>
    @endif

    <script src="{{ asset('js/property-filters-sync.js') }}"></script>
    <script>
        let map;
        let markers = [];
        let properties = [];
        let projectionHelper;
        let propertyOverlay;

        // Debug tools:
        // - Append `?debug_iw=1` to the map URL to enable logging
        // - In DevTools console you can tweak: window.__iwOffsetY = 24; then click a marker again
        const IW_DEBUG = new URLSearchParams(window.location.search).get('debug_iw') === '1';

        function createPropertyCardOverlay(mapInstance) {
            class PropertyCardOverlay extends google.maps.OverlayView {
                constructor() {
                    super();
                    this.map = mapInstance;
                    this.position = null;
                    this.container = null;
                    this.setMap(mapInstance);
                }

                onAdd() {
                    this.container = document.createElement('div');
                    this.container.className = 'property-card-overlay';
                    this.container.style.display = 'none';

                    // Prevent map click/drag from triggering when interacting with the card
                    this.container.addEventListener('click', (e) => e.stopPropagation());
                    this.container.addEventListener('mousedown', (e) => e.stopPropagation());
                    this.container.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });

                    this.getPanes().floatPane.appendChild(this.container);
                }

                onRemove() {
                    if (this.container) {
                        this.container.remove();
                        this.container = null;
                    }
                }

                draw() {
                    if (!this.container || !this.position) return;
                    const proj = this.getProjection();
                    if (!proj) return;
                    const point = proj.fromLatLngToDivPixel(this.position);
                    if (!point) return;
                    this.container.style.left = `${point.x}px`;
                    this.container.style.top = `${point.y}px`;
                }

                setPosition(latLng) {
                    this.position = latLng;
                    this.draw();
                }

                setContent(html) {
                    if (!this.container) return;
                    this.container.innerHTML = html;

                    const closeBtn = this.container.querySelector('[data-overlay-close="1"]');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.hide();
                        });
                    }
                }

                show() {
                    if (this.container) this.container.style.display = 'block';
                    this.draw();
                }

                hide() {
                    if (this.container) this.container.style.display = 'none';
                    this.position = null;
                }
            }

            return new PropertyCardOverlay();
        }

        // Generate a consistent color for each landlord/agent
        function getColorForAgent(agentName) {
            if (!agentName || agentName === 'N/A') {
                return '#6b7280'; // Gray for unknown
            }
            
            // Generate hash from agent name
            let hash = 0;
            for (let i = 0; i < agentName.length; i++) {
                hash = agentName.charCodeAt(i) + ((hash << 5) - hash);
            }
            
            // Define a palette of distinct, vibrant colors
            const colorPalette = [
                { main: '#3b82f6', hover: '#60a5fa' },  // Blue
                { main: '#10b981', hover: '#34d399' },  // Green
                { main: '#8b5cf6', hover: '#a78bfa' },  // Purple
                { main: '#f59e0b', hover: '#fbbf24' },  // Orange
                { main: '#ef4444', hover: '#f87171' },  // Red
                { main: '#06b6d4', hover: '#22d3ee' },  // Cyan
                { main: '#ec4899', hover: '#f472b6' },  // Pink
                { main: '#14b8a6', hover: '#2dd4bf' },  // Teal
                { main: '#f97316', hover: '#fb923c' },  // Deep Orange
                { main: '#6366f1', hover: '#818cf8' },  // Indigo
                { main: '#84cc16', hover: '#a3e635' },  // Lime
                { main: '#a855f7', hover: '#c084fc' },  // Violet
                { main: '#22c55e', hover: '#4ade80' },  // Emerald
                { main: '#eab308', hover: '#facc15' },  // Yellow
                { main: '#dc2626', hover: '#ef4444' }   // Crimson
            ];
            
            // Use hash to select color from palette
            const index = Math.abs(hash) % colorPalette.length;
            return colorPalette[index];
        }

        function initMap() {
            try {
                console.log('🗺️ Initializing map...');
                
                // Create map
                map = new google.maps.Map(document.getElementById('map'), {
                    center: { lat: 51.5074, lng: -0.1278 }, // London center
                    zoom: 12,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    mapTypeControl: false,
                    fullscreenControl: true,
                    streetViewControl: true,
                    zoomControl: true
                });
                
                // Projection helper for debugging pixel offsets
                projectionHelper = new google.maps.OverlayView();
                projectionHelper.onAdd = function () {};
                projectionHelper.draw = function () {};
                projectionHelper.onRemove = function () {};
                projectionHelper.setMap(map);

                // Custom overlay card anchored to marker (no "floating away" on zoom)
                propertyOverlay = createPropertyCardOverlay(map);
                map.addListener('click', () => propertyOverlay?.hide());

                // Load properties
                loadProperties();
                
                // Hide loading screen
                document.getElementById('loadingScreen').style.display = 'none';
                
                console.log('✅ Map initialized successfully');
                
                } catch (error) {
                console.error('❌ Map initialization failed:', error);
                document.getElementById('loadingScreen').innerHTML = '<div style="text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i><div class="loading-text" style="color: #ef4444;">Map initialization failed</div><p style="color: #6b7280; margin-top: 8px;">' + error.message + '</p></div>';
            }
        }

        function loadProperties() {
            try {
                const propertiesData = document.getElementById('properties-data');
                if (!propertiesData) {
                    throw new Error('Properties data not found');
                }

                const propertiesJson = propertiesData.textContent;
                if (!propertiesJson || propertiesJson.trim() === '') {
                    throw new Error('No properties data available');
                }

                properties = JSON.parse(propertiesJson);
                console.log(`📊 Loaded ${properties.length} properties`);

                // Filter properties with valid coordinates
                const validProperties = properties.filter(property => {
                    const lat = parseFloat(property.latitude);
                    const lng = parseFloat(property.longitude);
                    return !isNaN(lat) && !isNaN(lng) && 
                           lat >= -90 && lat <= 90 && 
                           lng >= -180 && lng <= 180;
                });

                console.log(`📍 Found ${validProperties.length} properties with valid coordinates`);

                if (validProperties.length === 0) {
                    alert('No properties with valid coordinates found');
                    return;
                }
                
                // Store all properties globally for filtering
                window.allProperties = validProperties;
                
                // Update property count
                document.getElementById('propertyCount').textContent = 
                    `${validProperties.length} properties loaded`;

                // Create markers
                createMarkers(validProperties);

                // Fit map to bounds
                fitMapToProperties(validProperties);

                if (typeof TrueholdPropertyFilters !== 'undefined') {
                    if (!TrueholdPropertyFilters.hasFilterParamsInSearch()) {
                        const stored = TrueholdPropertyFilters.getStoredQueryString();
                        if (stored) {
                            TrueholdPropertyFilters.applyQueryStringToMapControls(stored);
                            history.replaceState({}, '', '{{ route("properties.map") }}' + '?' + stored);
                        }
                    } else {
                        TrueholdPropertyFilters.saveFromLocationSearch();
                    }
                    applyMapFilters();
                }

                    } catch (error) {
                console.error('❌ Error loading properties:', error);
                alert('Failed to load properties: ' + error.message);
            }
        }

        function createMarkers(properties) {
                // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            window.markers = markers;

            properties.forEach(property => {
                    const lat = parseFloat(property.latitude);
                    const lng = parseFloat(property.longitude);
                
                // Check if property is premium
                const isPremium = property.flag && property.flag.toLowerCase() === 'premium';
                
                // Determine marker color based on agent/landlord and premium status
                let markerColor, hoverColor, markerShape, markerScale;
                
                if (isPremium) {
                    // Premium properties - Gold star
                    markerShape = google.maps.SymbolPath.CIRCLE;
                    markerScale = 10;
                    markerColor = '#FFD700'; // Bright gold
                    hoverColor = '#FFC107';
                } else {
                    // Different colors for different landlords/agents
                    const agentName = property.agent_name || property.landlord || 'N/A';
                    const agentColors = getColorForAgent(agentName);
                    
                    markerColor = agentColors.main;
                    hoverColor = agentColors.hover;
                    markerShape = google.maps.SymbolPath.CIRCLE;
                    markerScale = 7;
                }

                        const marker = new google.maps.Marker({
                        position: { lat, lng },
                        map: map,
                    title: property.title,
                    animation: google.maps.Animation.DROP,
                    // Critical: prevent Google from applying the default "pin" anchor offset
                    // (which makes InfoWindows float far above small circle markers)
                    anchorPoint: new google.maps.Point(0, 0),
                            icon: {
                        path: markerShape,
                        scale: markerScale,
                        fillColor: markerColor,
                                fillOpacity: 1,
                        strokeColor: isPremium ? '#b8941f' : '#1e3a5f',
                        strokeWeight: isPremium ? 3 : 2
                    },
                    zIndex: isPremium ? 1000 : 100 // Premium markers on top
                });
                
                // Store original marker properties for hover
                marker.originalIcon = {
                    path: markerShape,
                    scale: markerScale,
                    fillColor: markerColor,
                    fillOpacity: 1,
                    strokeColor: isPremium ? '#b8941f' : '#1e3a5f',
                    strokeWeight: isPremium ? 3 : 2
                };
                
                marker.hoverIcon = {
                    path: markerShape,
                    scale: markerScale + 2,
                    fillColor: hoverColor,
                    fillOpacity: 1,
                    strokeColor: isPremium ? '#b8941f' : '#1e3a5f',
                    strokeWeight: isPremium ? 4 : 3
                };
                
                // Add hover effect
                marker.addListener('mouseover', function() {
                    this.setIcon(this.hoverIcon);
                });
                
                marker.addListener('mouseout', function() {
                    this.setIcon(this.originalIcon);
                });

                marker.addListener('click', () => {
                    // Get the first image or use a placeholder
                    const imageUrl = property.first_photo_url || 
                                   (property.high_quality_photos_array && property.high_quality_photos_array[0]) || 
                                   'https://via.placeholder.com/380x200/1e3a5f/d4af37?text=No+Image';
                    
                    const content = `
                        <div class="info-window-card" style="position: relative;">
                            <button type="button" class="overlay-close" aria-label="Close" data-overlay-close="1">×</button>
                            <img src="${imageUrl}" alt="${property.title || 'Property'}" class="info-window-image" onerror="this.src='https://via.placeholder.com/380x200/1e3a5f/d4af37?text=No+Image'">
                            <div class="info-window-content">
                                <div class="info-window-header">
                                    <h3 class="info-window-title">${property.title || 'Property Details'}</h3>
                                    <div class="info-window-price">
                                        <span style="font-weight: 700; font-size: 16px;">£</span>
                                        ${(property.formatted_price || property.price || 'Price not available').toString().replace('£', '').trim()}
                    </div>
                </div>
                                <div class="info-window-details">
                                    ${property.location ? `
                                        <div class="info-window-detail-item">
                                            <svg viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                            </svg>
                                            <span><strong>Location:</strong> ${property.location}</span>
                        </div>
                    ` : ''}
                                    ${property.couples_allowed ? `
                                        <div class="info-window-detail-item">
                                            <svg viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                            </svg>
                                            <span><strong>Couples:</strong> ${property.couples_allowed === 'Yes' ? '✓ Allowed' : '✗ Not Allowed'}</span>
                        </div>
                    ` : ''}
                </div>
                                <div class="info-window-footer">
                                    <a href="/properties/${property.id}" class="info-window-btn" target="_blank" rel="noopener noreferrer" onclick="sessionStorage.setItem('propertyListingUrl', window.location.href);">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        View Full Details
                                    </a>
                        </div>
                    </div>
                </div>
            `;
                    propertyOverlay.setContent(content);
                    propertyOverlay.setPosition(marker.getPosition());
                    propertyOverlay.show();

                    // Debug: log marker pixel vs overlay position
                    if (IW_DEBUG) {
                        try {
                            const proj = projectionHelper?.getProjection?.();
                            const pos = marker.getPosition();
                            const markerPx = proj && pos ? proj.fromLatLngToDivPixel(pos) : null;
                            console.groupCollapsed('🪲 Overlay debug');
                            console.log('zoom:', map.getZoom());
                            console.log('marker latlng:', pos?.toJSON?.() ?? pos);
                            console.log('marker divPixel:', markerPx);
                            console.groupEnd();
                        } catch (e) {
                            console.warn('Overlay debug failed:', e);
                        }
                    }
                });

                markers.push(marker);
            });
            
            // Update global markers reference
            window.markers = markers;
        }

        function fitMapToProperties(properties) {
            if (properties.length === 0) return;

            const bounds = new google.maps.LatLngBounds();
            properties.forEach(property => {
                const lat = parseFloat(property.latitude);
                const lng = parseFloat(property.longitude);
                bounds.extend({ lat, lng });
            });

                map.fitBounds(bounds);
            
            // Don't zoom in too much for single property
            const listener = google.maps.event.addListener(map, "idle", function() {
                if (map.getZoom() > 16) map.setZoom(16);
                google.maps.event.removeListener(listener);
            });
        }

        function toggleMapView(mapType) {
            map.setMapTypeId(mapType);
            
            // Update button states
            const mapBtn = document.getElementById('mapViewBtn');
            const satBtn = document.getElementById('satelliteViewBtn');
            
            if (mapType === 'roadmap') {
                mapBtn.classList.add('active');
                satBtn.classList.remove('active');
            } else {
                satBtn.classList.add('active');
                mapBtn.classList.remove('active');
            }
        }

        function toggleMapFilters() {
            const text = document.getElementById('filterToggleText');
            const chevron = document.getElementById('filterChevron');
            const filtersContent = document.getElementById('filtersCollapse');
            const toggleBtn = document.getElementById('filtersToggleBtn');
            const open = filtersContent.classList.toggle('active');
            filtersContent.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            text.textContent = open ? 'Hide search' : 'Refine search';
            chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        }
        
        function applyMapFilters() {
            const locationFilter = (document.getElementById('thf_location')?.value || '').trim().toLowerCase();
            const propertyType = (document.getElementById('thf_property_type')?.value || '').toLowerCase();
            const minPrice = parseFloat(document.getElementById('thf_min_price')?.value) || 0;
            const maxPrice = parseFloat(document.getElementById('thf_max_price')?.value) || Infinity;
            const couplesAllowed = (document.querySelector('input[name="couples_allowed"]:checked')?.value || '').toLowerCase();
            const ensuite = document.getElementById('filterEnsuite')?.checked ? 'yes' : '';
            const roomCountFilter = (document.getElementById('thf_room_count')?.value || '').trim();
            @auth
            const agentName = document.getElementById('thf_agent_name')?.value.toLowerCase() || '';
            const payingOnly = document.getElementById('filterPayingOnly')?.checked || false;
            @endauth
            
            let visibleCount = 0;
            
            // Filter markers
            if (window.markers) {
                window.markers.forEach((marker, index) => {
                    const property = window.allProperties[index];
                    if (!property) return;
                    
                    let visible = true;
                    
                    // Property type filter
                    if (propertyType && property.property_type?.toLowerCase() !== propertyType) {
                        visible = false;
                    }

                    // Location filter
                    if (locationFilter) {
                        const propertyLocation = (property.location || '').toLowerCase();
                        if (!propertyLocation.includes(locationFilter)) {
                            visible = false;
                        }
                    }
                    
                    // Room count filter
                    if (roomCountFilter) {
                        const propRooms = String(property.total_rooms ?? property.room_count ?? '').trim();
                        if (propRooms !== roomCountFilter) {
                            visible = false;
                        }
                    }
                    
                    // Price filter
                const price = parseFloat(property.price) || 0;
                if (price < minPrice || price > maxPrice) {
                        visible = false;
                }

                // Couples allowed filter (matches listing view backend logic)
                    if (couplesAllowed) {
                        const propertyCouplesOk = (property.couples_ok || '').toLowerCase();
                        const propertyCouplesAllowed = (property.couples_allowed || '').toLowerCase();
                        
                        if (couplesAllowed === 'yes') {
                            // Must allow couples - check for yes, couples, allowed, or welcome
                            const allowsCouples = propertyCouplesOk.includes('yes') || 
                                                propertyCouplesOk.includes('couples') ||
                                                propertyCouplesOk.includes('allowed') ||
                                                propertyCouplesOk.includes('welcome') ||
                                                propertyCouplesAllowed.includes('yes') ||
                                                propertyCouplesAllowed.includes('couples') ||
                                                propertyCouplesAllowed.includes('allowed') ||
                                                propertyCouplesAllowed.includes('welcome');
                            if (!allowsCouples) {
                                visible = false;
                            }
                        } else if (couplesAllowed === 'no') {
                            // Must NOT allow couples (singles only) - check for no, not, single, or individual
                            const disallowsCouples = propertyCouplesOk.includes('no') || 
                                                   propertyCouplesOk.includes('not') ||
                                                   propertyCouplesOk.includes('single') ||
                                                   propertyCouplesOk.includes('individual') ||
                                                   propertyCouplesAllowed.includes('no') ||
                                                   propertyCouplesAllowed.includes('not') ||
                                                   propertyCouplesAllowed.includes('single') ||
                                                   propertyCouplesAllowed.includes('individual');
                            // If it doesn't explicitly disallow couples, hide it
                            if (!disallowsCouples) {
                                visible = false;
                            }
                        }
                    }
                    
                    // Ensuite filter - must have "en-suite" or "ensuite" in description and NOT have "studio"
                    // Also exclude cases where en-suites are mentioned but not available
                    if (ensuite === 'yes') {
                        const description = (property.description || '').toLowerCase();
                        const hasEnsuite = description.includes('en-suite') || description.includes('ensuite');
                        
                        if (!hasEnsuite) {
                            visible = false;
                        } else {
                            // Must NOT have "studio" in description
                            const hasStudio = description.includes('studio');
                            if (hasStudio) {
                                visible = false;
                            } else {
                                // Must NOT indicate that en-suites are not available
                                // Check for direct patterns first
                                const directNegativePatterns = [
                                    'en-suite not available',
                                    'ensuite not available',
                                    'en-suite unavailable',
                                    'ensuite unavailable',
                                    'en-suite are not available',
                                    'ensuite are not available',
                                    'en-suite not included',
                                    'ensuite not included',
                                    'en-suite not for',
                                    'ensuite not for',
                                ];
                                
                                const hasDirectNegativePattern = directNegativePatterns.some(pattern => 
                                    description.includes(pattern)
                                );
                                
                                if (hasDirectNegativePattern) {
                                    visible = false;
                                } else {
                                    // Check for patterns with words in between (e.g., "en-suite rooms (ENSUITES ARE NOT AVAILABLE")
                                    const hasNotAvailable = description.includes('not available') || description.includes('unavailable');
                                    
                                    if (hasEnsuite && hasNotAvailable) {
                                        // Find positions to ensure they're reasonably close (within 200 characters)
                                        const ensuitePos = Math.max(
                                            description.indexOf('en-suite') !== -1 ? description.indexOf('en-suite') : -1,
                                            description.indexOf('ensuite') !== -1 ? description.indexOf('ensuite') : -1
                                        );
                                        const notAvailablePos = Math.max(
                                            description.indexOf('not available') !== -1 ? description.indexOf('not available') : -1,
                                            description.indexOf('unavailable') !== -1 ? description.indexOf('unavailable') : -1
                                        );
                                        
                                        if (ensuitePos !== -1 && notAvailablePos !== -1 && Math.abs(ensuitePos - notAvailablePos) < 200) {
                                            visible = false;
                                        } else {
                                            // Check for pattern indicating available rooms have shared bathrooms
                                            if (description.includes('available') && 
                                                (description.includes('shared bathroom') || 
                                                 (description.includes('available room') && description.includes('shared')))) {
                                                visible = false;
                                            }
                                        }
                                    } else {
                                        // Check for pattern indicating available rooms have shared bathrooms
                                        if (description.includes('available') && 
                                            (description.includes('shared bathroom') || 
                                             (description.includes('available room') && description.includes('shared')))) {
                                            visible = false;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    @auth
                    // Agent name filter (only for authenticated users)
                    if (agentName && property.agent_name?.toLowerCase() !== agentName) {
                        visible = false;
                    }
                    
                    // Paying agents only filter (only for authenticated users)
                    if (payingOnly) {
                        const isPaying = property.paying === 'Yes' || 
                                       property.paying === 'yes' || 
                                       property.paying === '1' || 
                                       property.paying === 1 || 
                                       property.paying === true;
                        if (!isPaying) {
                            visible = false;
                        }
                    }
                    @endauth
                    
                    marker.setVisible(visible);
                    if (visible) visibleCount++;
                });
            }

            // Update property count
            const countElement = document.getElementById('propertyCount');
            if (countElement) {
                countElement.textContent = `${visibleCount} properties visible`;
            }

            if (typeof TrueholdPropertyFilters !== 'undefined') {
                const qs = TrueholdPropertyFilters.buildMapControlsQueryString();
                TrueholdPropertyFilters.saveFromQueryString(qs);
                const mapBase = '{{ route("properties.map") }}';
                history.replaceState({}, '', qs ? (mapBase + '?' + qs) : mapBase);
            }
        }
        
        function clearMapFilters() {
            // Reset all filter inputs
            const locationInput = document.getElementById('thf_location');
            if (locationInput) locationInput.value = '';
            const propertyTypeInput = document.getElementById('thf_property_type');
            if (propertyTypeInput) propertyTypeInput.value = '';
            const minPriceInput = document.getElementById('thf_min_price');
            if (minPriceInput) minPriceInput.value = '';
            const maxPriceInput = document.getElementById('thf_max_price');
            if (maxPriceInput) maxPriceInput.value = '';
            const couplesAny = document.getElementById('filterCouplesAny');
            if (couplesAny) couplesAny.checked = true;
            const ensuiteFilter = document.getElementById('filterEnsuite');
            if (ensuiteFilter) ensuiteFilter.checked = false;
            const roomCountFilter = document.getElementById('thf_room_count');
            if (roomCountFilter) roomCountFilter.value = '';
            @auth
            const agentFilter = document.getElementById('thf_agent_name');
            if (agentFilter) agentFilter.value = '';
            const payingFilter = document.getElementById('filterPayingOnly');
            if (payingFilter) payingFilter.checked = false;
            @endauth
            
            // Show all markers
            if (window.markers) {
                window.markers.forEach(marker => marker.setVisible(true));
            }
            
            // Update property count
            const countElement = document.getElementById('propertyCount');
            if (countElement && window.allProperties) {
                countElement.textContent = `${window.allProperties.length} properties loaded`;
            }

            if (typeof TrueholdPropertyFilters !== 'undefined') {
                TrueholdPropertyFilters.clearStored();
                history.replaceState({}, '', '{{ route("properties.map") }}');
            }
        }

        // Auto-apply map filters on change (no need to click Apply)
        (function initFilterAutoApply() {
            let priceDebounce;
            const runApply = () => applyMapFilters();
            const selIds = ['thf_property_type', 'thf_room_count'];
            const checkboxIds = ['filterEnsuite'];
            const radioIds = ['filterCouplesAny', 'filterCouplesYes', 'filterCouplesNo'];
            @auth
            selIds.push('thf_agent_name');
            @endauth
            selIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', runApply);
            });
            checkboxIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', runApply);
            });
            radioIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', runApply);
            });
            ['thf_min_price', 'thf_max_price'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', () => {
                        clearTimeout(priceDebounce);
                        priceDebounce = setTimeout(runApply, 400);
                    });
                    el.addEventListener('change', runApply);
                }
            });
            const locationEl = document.getElementById('thf_location');
            if (locationEl) {
                let locationDebounce;
                locationEl.addEventListener('input', () => {
                    clearTimeout(locationDebounce);
                    locationDebounce = setTimeout(runApply, 350);
                });
                locationEl.addEventListener('change', runApply);
            }
            const payingEl = document.getElementById('filterPayingOnly');
            if (payingEl) payingEl.addEventListener('change', runApply);
        })();

        function shareMapFilters() {
            const qs = (typeof TrueholdPropertyFilters !== 'undefined')
                ? TrueholdPropertyFilters.buildMapControlsQueryString()
                : window.location.search.replace(/^\?/, '');
            const params = new URLSearchParams(qs);
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
                body: JSON.stringify({ filters, view: 'map' }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('Failed to create share token')))
            .then(data => navigator.clipboard.writeText(data.share_url || ('{{ route("properties.map") }}' + (qs ? ('?' + qs) : ''))))
            .then(() => {
                const shareButton = document.querySelector('.filter-btn-share');
                const shareButtonText = document.getElementById('shareButtonText');
                if (!shareButton || !shareButtonText) return;
                const originalText = shareButtonText.textContent;
                shareButton.classList.add('copied');
                shareButtonText.textContent = 'Link Copied!';
                setTimeout(() => {
                    shareButton.classList.remove('copied');
                    shareButtonText.textContent = originalText;
                }, 1800);
            }).catch(() => {
                window.prompt('Unable to create tokenized link. Copy this URL instead:', window.location.href);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof TrueholdPropertyFilters === 'undefined') return;
            document.querySelectorAll('a.js-truehold-to-listing').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    const qs = TrueholdPropertyFilters.buildMapControlsQueryString() || TrueholdPropertyFilters.getStoredQueryString();
                    TrueholdPropertyFilters.saveFromQueryString(qs);
                    const base = a.getAttribute('href').split('?')[0];
                    window.location.href = base + (qs ? '?' + qs : '');
                });
            });

            // Dismiss filters when clicking outside the filter panel.
            document.addEventListener('mousedown', function (e) {
                const filtersPanel = document.getElementById('filtersCollapse');
                const toggleBtn = document.getElementById('filtersToggleBtn');
                if (!filtersPanel || !filtersPanel.classList.contains('active')) return;
                const formPanel = document.getElementById('filtersContent');
                const clickedInsidePanel = formPanel ? formPanel.contains(e.target) : filtersPanel.contains(e.target);
                const clickedToggle = toggleBtn && toggleBtn.contains(e.target);
                if (!clickedInsidePanel && !clickedToggle) {
                    toggleMapFilters();
                }
            });
        });
    </script>
</body>
</html>
