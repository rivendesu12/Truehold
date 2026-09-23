<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'TRUEHOLD') }} - Admin Panel</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/truehold-logo.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/truehold-logo.jpg') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome - Multiple CDN sources for reliability -->
    <link href="{{ asset('css/font-awesome.min.css') }}" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';">
    <!-- Bootstrap CSS - Local first, then CDN fallback -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css';">
    
    <!-- Tailwind CSS - Local fallback for production -->
    <link href="{{ asset('css/tailwind.min.css') }}" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdn.tailwindcss.com';">
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Fallback CSS for when CDN libraries fail -->
    <link href="{{ asset('css/fallback.css') }}" rel="stylesheet">
    
    <style>
        /* Dark Mode Admin Panel */
        body {
            background-color: #111827 !important;
            color: #f9fafb !important;
        }
        
        .main-content {
            background-color: #111827 !important;
        }
        
        /* Dark mode overrides */
        .bg-white {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
        }
        
        .text-gray-900 {
            color: #d1d5db !important;
        }
        
        .text-gray-700 {
            color: #9ca3af !important;
        }
        
        .text-gray-500 {
            color: #6b7280 !important;
        }
        
        /* Lighter placeholders */
        input::placeholder, textarea::placeholder, select::placeholder {
            color: #9ca3af !important;
        }
        
        /* Form inputs dark mode */
        input, textarea, select {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #d1d5db !important;
        }
        
        input:focus, textarea:focus, select:focus {
            border-color: #fbbf24 !important;
            box-shadow: 0 0 0 1px #fbbf24 !important;
        }
        
        /* All labels lighter - comprehensive coverage */
        label, .form-label, .label, .field-label, .control-label, .input-label,
        .col-form-label, .custom-control-label, .form-check-label, .radio-label,
        .checkbox-label, .switch-label, .toggle-label, .btn-label,
        .nav-label, .menu-label, .sidebar-label, .card-label,
        .table-label, .list-label, .item-label, .section-label {
            color: #d1d5db !important;
        }
        
        /* Field labels and descriptions */
        .field-label, .field-description, .help-text {
            color: #d1d5db !important;
        }
        
        /* Required field indicators */
        .required, .asterisk {
            color: #fbbf24 !important;
        }
        
        /* Form groups and containers */
        .form-group, .form-control-group {
            margin-bottom: 1rem;
        }
        
        /* Input groups */
        .input-group-text {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #d1d5db !important;
        }
        
        /* Form validation */
        .is-invalid {
            border-color: #ef4444 !important;
        }
        
        .invalid-feedback {
            color: #ef4444 !important;
        }
        
        .is-valid {
            border-color: #10b981 !important;
        }
        
        /* Form sections and headers */
        .form-section, .card-header h3, .card-header h4, .card-header h5, .card-header h6 {
            color: #d1d5db !important;
        }
        
        /* Help text and descriptions */
        .form-text, .help-block, .field-help {
            color: #9ca3af !important;
        }
        
        /* Specific form elements */
        .form-control, .form-select, .form-check-input {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #d1d5db !important;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #374151 !important;
            border-color: #fbbf24 !important;
            box-shadow: 0 0 0 0.2rem rgba(251, 191, 36, 0.25) !important;
            color: #d1d5db !important;
        }
        
        /* Checkboxes and radio buttons */
        .form-check-label {
            color: #d1d5db !important;
        }
        
        .form-check-input:checked {
            background-color: #fbbf24 !important;
            border-color: #fbbf24 !important;
        }
        
        /* Select dropdowns */
        select option {
            background-color: #374151 !important;
            color: #d1d5db !important;
        }
        
        /* Text areas */
        textarea.form-control {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #d1d5db !important;
        }
        
        /* Admin panel specific labels */
        .admin-label, .panel-label, .dashboard-label, .content-label,
        .widget-label, .component-label, .module-label, .feature-label,
        .setting-label, .option-label, .config-label, .preference-label {
            color: #d1d5db !important;
        }
        
        /* Table and list labels */
        th, .table-header, .list-header, .group-header {
            color: #d1d5db !important;
        }
        
        /* Navigation labels */
        .nav-link, .menu-item, .sidebar-link {
            color: #d1d5db !important;
        }
        
        /* Card and widget labels */
        .card-title, .widget-title, .panel-title, .section-title {
            color: #d1d5db !important;
        }
        
        /* Button labels */
        .btn-text, .button-text, .link-text {
            color: #d1d5db !important;
        }
        
        /* Catch-all for any remaining labels */
        [class*="label"], [class*="title"], [class*="header"] {
            color: #d1d5db !important;
        }
        
        /* Override any white text that should be lighter */
        .text-white {
            color: #d1d5db !important;
        }
        
        /* Ensure all form-related text is lighter */
        .form-text, .form-description, .field-description {
            color: #9ca3af !important;
        }
        
        .border-gray-200 {
            border-color: #374151 !important;
        }
        
        .bg-gray-50 {
            background-color: #374151 !important;
        }
        
        .bg-gray-100 {
            background-color: #4b5563 !important;
        }
        
        /* Gold accent colors */
        .text-blue-600, .text-green-600, .text-purple-600, .text-orange-600, .text-red-600, .text-indigo-600, .text-teal-600, .text-yellow-600 {
            color: #fbbf24 !important;
        }
        
        .border-blue-200, .border-green-200, .border-purple-200, .border-orange-200, .border-red-200, .border-indigo-200, .border-teal-200, .border-yellow-200 {
            border-color: #fbbf24 !important;
        }
        
        /* Sidebar dark mode */
        .sidebar-item {
            color: #9ca3af !important;
            transition: all 0.3s ease;
        }
        
        .sidebar-item:hover {
            background-color: #374151 !important;
            color: #d1d5db !important;
        }
        
        .sidebar-item.active {
            background-color: #374151 !important;
            border-left: 2px solid #fbbf24 !important;
            color: #d1d5db !important;
        }
        
        .sidebar-icon {
            color: #fbbf24 !important;
        }
        
        .sidebar-text {
            color: #9ca3af !important;
        }
        
        /* Section headers */
        .sidebar-text.text-xs {
            color: #fbbf24 !important;
        }
        
        /* Tables dark mode */
        table {
            background-color: #1f2937 !important;
            color: #d1d5db !important;
        }
        
        th {
            background-color: #374151 !important;
            color: #d1d5db !important;
            border-color: #4b5563 !important;
        }
        
        td {
            background-color: #1f2937 !important;
            color: #d1d5db !important;
            border-color: #4b5563 !important;
        }
        
        tr:hover td {
            background-color: #374151 !important;
        }
        
        /* Cards dark mode */
        .card, .bg-white {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
        }
        
        /* Standardized button styles */
        .btn, button, input[type="submit"], input[type="button"], input[type="reset"] {
            background: linear-gradient(135deg, #374151, #4b5563) !important;
            border: 1px solid #6b7280 !important;
            color: #d1d5db !important;
            padding: 0.5rem 1rem !important;
            border-radius: 0.375rem !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
        }
        
        .btn:hover, button:hover, input[type="submit"]:hover, input[type="button"]:hover, input[type="reset"]:hover {
            background: linear-gradient(135deg, #4b5563, #6b7280) !important;
            border-color: #fbbf24 !important;
            color: #f9fafb !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3) !important;
        }
        
        .btn:active, button:active, input[type="submit"]:active, input[type="button"]:active, input[type="reset"]:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        }
        
        /* Primary buttons */
        .btn-primary, .btn-success, .btn-info {
            background: linear-gradient(135deg, #1f2937, #374151) !important;
            border: 1px solid #fbbf24 !important;
            color: #fbbf24 !important;
        }
        
        .btn-primary:hover, .btn-success:hover, .btn-info:hover {
            background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;
            color: #1f2937 !important;
            border-color: #f59e0b !important;
        }
        
        /* Danger buttons */
        .btn-danger, .btn-warning {
            background: linear-gradient(135deg, #7f1d1d, #991b1b) !important;
            border: 1px solid #ef4444 !important;
            color: #f9fafb !important;
        }
        
        .btn-danger:hover, .btn-warning:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            color: #f9fafb !important;
            border-color: #dc2626 !important;
        }
        
        /* Secondary buttons */
        .btn-secondary, .btn-outline {
            background: linear-gradient(135deg, #374151, #4b5563) !important;
            border: 1px solid #6b7280 !important;
            color: #d1d5db !important;
        }
        
        .btn-secondary:hover, .btn-outline:hover {
            background: linear-gradient(135deg, #4b5563, #6b7280) !important;
            border-color: #fbbf24 !important;
            color: #f9fafb !important;
        }
        
        /* Button sizes */
        .btn-sm, .btn-small {
            padding: 0.25rem 0.75rem !important;
            font-size: 0.875rem !important;
        }
        
        .btn-lg, .btn-large {
            padding: 0.75rem 1.5rem !important;
            font-size: 1.125rem !important;
        }
        
        .btn-xl, .btn-extra-large {
            padding: 1rem 2rem !important;
            font-size: 1.25rem !important;
        }
        
        /* Button states */
        .btn:disabled, button:disabled, input[type="submit"]:disabled, input[type="button"]:disabled, input[type="reset"]:disabled {
            background: linear-gradient(135deg, #1f2937, #374151) !important;
            border-color: #374151 !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            opacity: 0.6 !important;
        }
        
        .btn:focus, button:focus, input[type="submit"]:focus, input[type="button"]:focus, input[type="reset"]:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.3) !important;
        }
        
        /* Link buttons */
        .btn-link, a.btn {
            background: transparent !important;
            border: none !important;
            color: #fbbf24 !important;
            text-decoration: underline !important;
        }
        
        .btn-link:hover, a.btn:hover {
            background: transparent !important;
            color: #f59e0b !important;
            text-decoration: none !important;
        }
        
        /* Icon buttons */
        .btn-icon, .btn-icon-only {
            width: 2.5rem !important;
            height: 2.5rem !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* Ensure Tailwind gradients work */
        .bg-gradient-to-r {
            background-image: linear-gradient(to right, var(--tw-gradient-stops));
        }
        .from-blue-500 { --tw-gradient-from: #3b82f6; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(59, 130, 246, 0)); }
        .to-blue-600 { --tw-gradient-to: #2563eb; }
        .from-green-500 { --tw-gradient-from: #10b981; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(16, 185, 129, 0)); }
        .to-green-600 { --tw-gradient-to: #059669; }
        .from-purple-500 { --tw-gradient-from: #8b5cf6; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(139, 92, 246, 0)); }
        .to-purple-600 { --tw-gradient-to: #7c3aed; }
        .from-orange-500 { --tw-gradient-from: #f97316; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgba(249, 115, 22, 0)); }
        .to-orange-600 { --tw-gradient-to: #ea580c; }
        
        /* Additional Tailwind utilities */
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .md\\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lg\\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .gap-6 { gap: 1.5rem; }
        .mb-8 { margin-bottom: 2rem; }
        .rounded-xl { border-radius: 0.75rem; }
        .p-6 { padding: 1.5rem; }
        .text-white { color: #ffffff; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
        .font-bold { font-weight: 700; }
        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .font-medium { font-weight: 500; }
        .p-3 { padding: 0.75rem; }
        .rounded-lg { border-radius: 0.5rem; }
        .text-2xl { font-size: 1.5rem; line-height: 2rem; }
        .bg-opacity-30 { background-color: rgba(255, 255, 255, 0.3); }
        
        /* Responsive breakpoints */
        @media (min-width: 768px) {
            .md\\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 1024px) {
            .lg\\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        
        .sidebar {
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100vh; /* ensure full viewport height so inner nav can scroll */
            overflow: hidden; /* prevent the whole sidebar from scrolling */
        }
        
        .sidebar.collapsed {
            width: 4rem;
        }
        
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-icon {
            margin-right: 0;
        }
        
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-bottom: 1rem;
        }
        
        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .main-content {
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 4rem;
        }
        
        .sidebar-item {
            transition: all 0.2s ease;
        }
        
        .sidebar-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border-right: 3px solid #3b82f6;
        }
        
        .sidebar-item.active {
            background-color: rgba(59, 130, 246, 0.15);
            border-right: 3px solid #3b82f6;
            color: #3b82f6;
        }
        
        .stats-card {
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Mobile responsiveness */
        @media (max-width: 767.98px) {
            .sidebar {
                width: 16rem; /* keep full width on mobile when opened */
                transform: translateX(-100%);
                will-change: transform;
                z-index: 50;
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            /* Mobile toggle button styles */
            #sidebarToggle, #mobileMenuToggle {
                display: block !important;
                cursor: pointer;
            }
        }
        
        @media (min-width: 768px) {
            #sidebarToggle, #mobileMenuToggle {
                display: none !important;
            }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar shadow-lg w-64 min-h-screen fixed left-0 top-0 z-40" style="background-color: #1f2937; border-right: 1px solid #374151;">
            <!-- Sidebar Header -->
            <div class="p-4" style="border-bottom: 1px solid #374151;">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <img src="{{ asset('images/truehold-logo.jpg') }}" alt="TRUEHOLD GROUP LTD" class="h-8 w-auto mr-3">
                        <span class="sidebar-text text-xl font-bold" style="color: #d1d5db;">TRUEHOLD</span>
                    </div>
                    <!-- Mobile Toggle Button - Only visible on smaller screens -->
                    <button id="sidebarToggle" class="md:hidden hover:text-gray-300 p-2 rounded-lg transition-colors" style="color: #fbbf24; background-color: rgba(251, 191, 36, 0.1);">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="sidebar-nav mt-4">
                <div class="px-4 mb-4">
                    <span class="sidebar-text text-xs font-semibold uppercase tracking-wider" style="color: #fbbf24;">Main</span>
                </div>
                
                @auth
                @if(auth()->user()->hasAdminPermission('dashboard', 'view'))
                <a href="{{ route('admin.dashboard') }}" 
                   class="sidebar-item flex items-center px-4 py-3 hover:bg-gray-700 {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                   style="color: #d1d5db; {{ request()->routeIs('admin.dashboard') ? 'background-color: #374151; border-left: 3px solid #fbbf24;' : '' }}">
                    <i class="fas fa-tachometer-alt sidebar-icon mr-3 text-lg" style="color: #fbbf24;"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
                @endif
                @endauth

                @auth
                @if(auth()->user()->hasAdminPermission('properties', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold uppercase tracking-wider" style="color: #fbbf24;">Properties</span>
                </div>
                
                @php($apNewCount = 0)
                @auth
                    @php(
                        $apNewCount = \App\Models\ApProperty::where('created_at', '>', (auth()->user()->ap_properties_last_seen_at ?? \Carbon\Carbon::createFromTimestamp(0)))->count()
                    )
                @endauth
                <a href="{{ route('admin.ap-properties.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.ap-properties*') ? 'active' : '' }}">
                    <img src="{{ asset('images/apre-logo.png') }}" alt="AP" class="mr-3" style="width: 22px; height: 22px; object-fit: contain; border-radius: 3px;"/>
                    <span class="sidebar-text">AP Properties</span>
                    @if($apNewCount > 0)
                        <span class="ml-auto inline-flex items-center justify-center text-xs font-semibold rounded-full" style="background-color:#ef4444; color:#fff; min-width: 18px; height: 18px; padding: 0 6px;">
                            {{ $apNewCount > 9 ? '9+' : $apNewCount }}
                        </span>
                    @endif
                </a>

                {{-- Google Sheets Properties --}}
                <a href="{{ route('properties.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('properties.*') ? 'active' : '' }}">
                    <i class="fas fa-table sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Sheet Properties</span>
                </a>

                @if(auth()->user()->hasAdminPermission('properties', 'create'))
                <a href="{{ route('properties.create') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('properties.create') ? 'active' : '' }}">
                    <i class="fas fa-plus-circle sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Add Sheet Property</span>
                </a>
                @endif
                
                <a href="{{ route('admin.properties.flags') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.properties.flags') ? 'active' : '' }}">
                    <i class="fas fa-flag sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Property Flags</span>
                </a>
                @endif
                @endauth
                
                

                @auth
                @if(auth()->user()->hasAdminPermission('clients', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Clients</span>
                </div>
                
                <a href="{{ route('admin.clients') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.clients*') ? 'active' : '' }}">
                    <i class="fas fa-user-friends sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Manage Clients</span>
                </a>
                
                @if(auth()->user()->hasAdminPermission('clients', 'create'))
                <a href="{{ route('admin.clients.create') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.clients.create') ? 'active' : '' }}">
                    <i class="fas fa-user-plus sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Add Client</span>
                </a>
                @endif
                @endif
                @endauth

                @auth
                @if(auth()->user()->hasAdminPermission('rental_codes', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Reserved</span>
                </div>
                
                <a href="{{ route('rental-codes.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('rental-codes*') ? 'active' : '' }}">
                    <i class="fas fa-key sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Rental Codes</span>
                </a>

                <a href="{{ route('marketing-agents.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('marketing-agents*') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Marketing Agents</span>
                </a>

                <a href="{{ route('landlord-bonuses.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('landlord-bonuses*') ? 'active' : '' }}">
                    <i class="fas fa-gift sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Landlord Bonuses</span>
                </a>
                @endif
                @endauth

                @auth
                @if(auth()->user()->hasAdminPermission('invoices', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoicing</span>
                </div>
                
                <a href="{{ route('admin.invoices.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.invoices*') ? 'active' : '' }}">
                    <i class="fas fa-file-invoice sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Manage Invoices</span>
                </a>
                
                @if(auth()->user()->hasAdminPermission('invoices', 'create'))
                <a href="{{ route('admin.invoices.create') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.invoices.create') ? 'active' : '' }}">
                    <i class="fas fa-plus sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Create Invoice</span>
                </a>
                @endif
                @endif
                @endauth

                @auth
                @if(auth()->user()->hasAdminPermission('group_viewings', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Viewings</span>
                </div>
                
                <a href="{{ route('admin.group-viewings.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.group-viewings*') ? 'active' : '' }}">
                    <i class="fas fa-users sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Group Viewings</span>
                </a>
                @endif
                @endauth

                @auth
                @if(auth()->user()->hasAdminPermission('call_logs', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Call Logs</span>
                </div>
                
                <a href="{{ route('admin.call-logs.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.call-logs*') ? 'active' : '' }}">
                    <i class="fas fa-phone sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">All Call Logs</span>
                </a>
                
                @if(auth()->user()->hasAdminPermission('call_logs', 'create'))
                <a href="{{ route('admin.call-logs.create') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.call-logs.create') ? 'active' : '' }}">
                    <i class="fas fa-plus sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Log New Call</span>
                </a>
                @endif
                @endif
                @endauth


                @auth
                @if(auth()->user()->hasAdminPermission('users', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</span>
                </div>
                
                <a href="{{ route('admin.users') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                    <i class="fas fa-users sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Manage Agents</span>
                </a>
                @endif
                @endauth

                @auth
                @if(auth()->user()->isAdmin())
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">Leads</span>
                </div>

                <a href="{{ route('admin.zoopla-leads') }}"
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.zoopla-leads*') ? 'active' : '' }}">
                    <i class="fas fa-envelope-open-text sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Zoopla Leads</span>
                </a>
                @endif
                @endauth


                @auth
                @if(auth()->user()->hasAdminPermission('admin_permissions', 'view'))
                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold text-gray-500 uppercase tracking-wider">System</span>
                </div>
                
                <a href="{{ route('admin.user-permissions.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.user-permissions*') ? 'active' : '' }}">
                    <i class="fas fa-shield-alt sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">User Permissions</span>
                </a>
                
                <a href="{{ route('admin.scraper.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 text-gray-700 hover:text-blue-600 {{ request()->routeIs('admin.scraper*') ? 'active' : '' }}">
                    <i class="fas fa-spider sidebar-icon mr-3 text-lg"></i>
                    <span class="sidebar-text">Property Scraper</span>
                </a>
                @endif
                @endauth

                <div class="px-4 mt-6 mb-4">
                    <span class="sidebar-text text-xs font-semibold uppercase tracking-wider" style="color: #fbbf24;">Public Access</span>
                </div>
                
                <a href="{{ route('properties.index') }}" 
                   class="sidebar-item flex items-center px-4 py-3 transition-colors {{ request()->routeIs('properties.index') ? 'active' : '' }}"
                   style="color: #9ca3af; {{ request()->routeIs('properties.index') ? 'background-color: #374151; border-left: 2px solid #fbbf24; color: #d1d5db;' : '' }}"
                   onmouseover="this.style.backgroundColor='#374151'; this.style.color='#d1d5db';"
                   onmouseout="this.style.backgroundColor='{{ request()->routeIs('properties.index') ? '#374151' : 'transparent' }}'; this.style.color='{{ request()->routeIs('properties.index') ? '#d1d5db' : '#9ca3af' }}';">
                    <i class="fas fa-globe sidebar-icon mr-3 text-lg" style="color: #fbbf24;"></i>
                    <span class="sidebar-text font-medium">View Public Site</span>
                </a>
                
                <a href="{{ route('properties.map') }}" 
                   class="sidebar-item flex items-center px-4 py-3 transition-colors {{ request()->routeIs('properties.map') ? 'active' : '' }}"
                   style="color: #9ca3af; {{ request()->routeIs('properties.map') ? 'background-color: #374151; border-left: 2px solid #fbbf24; color: #d1d5db;' : '' }}"
                   onmouseover="this.style.backgroundColor='#374151'; this.style.color='#d1d5db';"
                   onmouseout="this.style.backgroundColor='{{ request()->routeIs('properties.map') ? '#374151' : 'transparent' }}'; this.style.color='{{ request()->routeIs('properties.map') ? '#d1d5db' : '#9ca3af' }}';">
                    <i class="fas fa-map-marked-alt sidebar-icon mr-3 text-lg" style="color: #fbbf24;"></i>
                    <span class="sidebar-text font-medium">Interactive Map</span>
                </a>
            </nav>

            <!-- Sidebar Footer -->
            <div class="w-full p-4 mt-auto" style="border-top: 1px solid #374151;">
                <div class="flex items-center justify-between">
                    @auth
                    @if(Auth::user()->role === 'agent')
                        <a href="{{ route('agent.profile.dashboard') }}" class="flex items-center flex-1 hover:bg-gray-700 rounded-lg p-2 transition-colors {{ request()->routeIs('agent.profile.*') ? 'bg-gray-700' : '' }}">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #1f2937, #374151); border: 1px solid #fbbf24;">
                                <span class="text-sm font-bold" style="color: #fbbf24;">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>
                            <div class="ml-3 sidebar-text">
                                <p class="text-sm font-medium" style="color: #d1d5db;">{{ Auth::user()->name }}</p>
                                <p class="text-xs" style="color: #9ca3af;">{{ Auth::user()->email }}</p>
                            </div>
                        </a>
                    @else
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #1f2937, #374151); border: 1px solid #fbbf24;">
                                <span class="text-sm font-bold" style="color: #fbbf24;">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>
                            <div class="ml-3 sidebar-text">
                                <p class="text-sm font-medium" style="color: #d1d5db;">{{ Auth::user()->name }}</p>
                                <p class="text-xs" style="color: #9ca3af;">{{ Auth::user()->email }}</p>
                            </div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="transition-colors" style="color: #9ca3af;"
                                onmouseover="this.style.color='#ef4444';"
                                onmouseout="this.style.color='#9ca3af';">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                    @else
                        <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="flex items-center flex-1 hover:bg-gray-700 rounded-lg p-2 transition-colors">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: linear-gradient(135deg, #1f2937, #374151); border: 1px solid #fbbf24;">
                                <i class="fas fa-user text-sm" style="color: #fbbf24;"></i>
                            </div>
                            <div class="ml-3 sidebar-text">
                                <p class="text-sm font-medium" style="color: #d1d5db;">Guest</p>
                                <p class="text-xs" style="color: #9ca3af;">Click to Login</p>
                            </div>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
        <!-- Mobile Sidebar Overlay -->
        <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-40 z-30 md:hidden"></div>

        <!-- Main Content -->
        <div class="main-content flex-1 ml-64" style="background-color: #111827;">
            <!-- Top Navigation -->
            <header class="shadow-sm" style="background-color: #1f2937; border-bottom: 1px solid #374151;">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center">
                        <!-- Mobile Menu Toggle - Only visible on smaller screens -->
                        <button id="mobileMenuToggle" class="md:hidden mr-4 hover:text-gray-300 p-2 rounded-lg transition-colors" style="color: #fbbf24; background-color: rgba(251, 191, 36, 0.1);">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <h1 class="text-2xl font-bold" style="color: #d1d5db;">@yield('page-title', 'Dashboard')</h1>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <button class="relative" style="color: #fbbf24;">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                        </button>
                        
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6" style="background-color: #111827;">
                @if(session('success'))
                    <div class="mb-6 px-4 py-3 rounded-lg" style="background-color: #064e3b; border: 1px solid #10b981; color: #f9fafb;">
                        <i class="fas fa-check-circle mr-2" style="color: #10b981;"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 px-4 py-3 rounded-lg" style="background-color: #7f1d1d; border: 1px solid #ef4444; color: #f9fafb;">
                        <i class="fas fa-exclamation-circle mr-2" style="color: #ef4444;"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        (function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            console.log('Toggle buttons found:', {
                sidebarToggle: !!sidebarToggle,
                mobileMenuToggle: !!mobileMenuToggle,
                sidebar: !!sidebar,
                overlay: !!sidebarOverlay
            });

            function isMobile() {
                return window.innerWidth < 768;
            }

            function openMobileSidebar() {
                console.log('Opening mobile sidebar');
                sidebar.classList.add('open');
                if (sidebarOverlay) sidebarOverlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeMobileSidebar() {
                console.log('Closing mobile sidebar');
                sidebar.classList.remove('open');
                if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            // Sidebar toggle for all views
            if (sidebarToggle) {
                console.log('Adding event listener to sidebarToggle');
                sidebarToggle.addEventListener('click', function(e) {
                    console.log('Sidebar toggle clicked', { isMobile: isMobile() });
                    e.preventDefault();
                    if (isMobile()) {
                        // On mobile, use slide-in/out
                        if (sidebar.classList.contains('open')) {
                            closeMobileSidebar();
                        } else {
                            openMobileSidebar();
                        }
                    } else {
                        // On desktop, use collapse/expand
                        sidebar.classList.toggle('collapsed');
                        if (sidebar.classList.contains('collapsed')) {
                            mainContent.style.marginLeft = '4rem';
                        } else {
                            mainContent.style.marginLeft = '16rem';
                        }
                    }
                });
            } else {
                console.log('Sidebar toggle button not found');
            }

            // Mobile menu toggle (header button)
            if (mobileMenuToggle) {
                console.log('Adding event listener to mobileMenuToggle');
                mobileMenuToggle.addEventListener('click', function(e) {
                    console.log('Mobile menu toggle clicked', { isMobile: isMobile() });
                    e.preventDefault();
                    if (isMobile()) {
                        // On mobile, use slide-in/out
                        if (sidebar.classList.contains('open')) {
                            closeMobileSidebar();
                        } else {
                            openMobileSidebar();
                        }
                    }
                });
            } else {
                console.log('Mobile menu toggle button not found');
            }

            // Close when clicking overlay (mobile)
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeMobileSidebar);
            }

            // Handle responsive state
            function applyResponsiveLayout() {
                if (isMobile()) {
                    // Reset desktop-specific states
                    sidebar.classList.remove('collapsed');
                    mainContent.style.marginLeft = '0';
                    closeMobileSidebar();
                } else {
                    // Ensure overlay is hidden on desktop
                    closeMobileSidebar();
                    mainContent.style.marginLeft = sidebar.classList.contains('collapsed') ? '4rem' : '16rem';
                }
            }

            // Force mobile behavior for testing
            function forceMobileToggle() {
                console.log('Force mobile toggle called');
                if (sidebar.classList.contains('open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            }

            // Add global function for testing
            window.toggleSidebar = forceMobileToggle;

            window.addEventListener('resize', applyResponsiveLayout);
            applyResponsiveLayout();
        })();
    </script>
    
    <!-- Bootstrap JS - Local first, then CDN fallback -->
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';"></script>
    
    <!-- Chart.js for charts - Local first, then CDN fallback -->
    <script src="{{ asset('js/chart.min.js') }}" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js';"></script>
    
    <!-- Page-specific scripts -->
    @stack('scripts')
</body>
</html>
