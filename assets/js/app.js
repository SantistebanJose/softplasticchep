document.addEventListener('click', function (e) {
    const sidebar = document.getElementById('pcSidebar');
    const toggle = document.querySelector('.pc-menu-toggle');
    if (!sidebar || !toggle) return;
    const clickedInside = sidebar.contains(e.target) || toggle.contains(e.target);
    if (!clickedInside && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
    }
});
