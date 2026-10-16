{{-- Shared phone fixes, included in the head of each public page.

     iOS zooms the page in when a field under 16px takes focus and does not
     zoom back out, which is what left the login form and the assistant panel
     hanging off the side of the screen. --}}
<style>
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
@media (max-width:768px){
    input:not([type=checkbox]):not([type=radio]),select,textarea{font-size:16px!important}
}
/* The header has no room for "Agent Login" / "Log out" beside Properties and
   Map View on a phone, so the button shrinks to its icon. */
@media (max-width:480px){
    .nav-links .btn-agent{font-size:0!important;gap:0!important;padding:8px!important;margin-left:2px!important}
    .nav-links .btn-agent svg{width:18px!important;height:18px!important}
}
</style>
