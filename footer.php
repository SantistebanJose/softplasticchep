</main>
</div><!-- /.pc-content -->
</div><!-- /.pc-app -->

<!--
    Nota: Bootstrap JS y SweetAlert2 NO se cargan aquí.
    ...
-->

<script>
// ── Sidebar responsivo (toggle mobile) ───────────────────────────────────────
function pcToggleSidebar() {
    document.getElementById('pcSidebar').classList.toggle('open');
    document.getElementById('pcSidebarOverlay').classList.toggle('show');
}

// Cierra el sidebar automáticamente si el usuario agranda la ventana
// (evita que quede "abierto" en modo desktop tras rotar o resize)
window.addEventListener('resize', () => {
    if (window.innerWidth > 992) {
        document.getElementById('pcSidebar').classList.remove('open');
        document.getElementById('pcSidebarOverlay').classList.remove('show');
    }
});

// Cierra el sidebar al hacer click en cualquier link del menú (mobile)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#pcSidebar a, #pcSidebar .pc-nav-subitem').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                document.getElementById('pcSidebar').classList.remove('open');
                document.getElementById('pcSidebarOverlay').classList.remove('show');
            }
        });
    });
});
</script>

</body>
</html>