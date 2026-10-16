{{-- Signed-in agents get a way out; guests see "Agent Login" instead. Uses
     the page's .btn-agent sizing (phone breakpoints included), outlined so it
     reads as secondary. --}}
@auth
<li>
    <form method="POST" action="{{ route('logout') }}" class="nav-logout-form">
        @csrf
        <button type="submit" class="btn-agent btn-logout" title="Signed in as {{ auth()->user()->name }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
            Log out
        </button>
    </form>
</li>
@once
<style>
.nav-logout-form { margin: 0; }
.btn-logout {
    background: transparent;
    color: var(--primary-navy, #1e3a5f);
    border: 2px solid var(--gold, #d4af37);
    box-shadow: none;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}
.btn-logout:hover { background: rgba(212, 175, 55, 0.12); }
</style>
@endonce
@endauth
