</main>
</div><!-- /.pc-content -->
</div><!-- /.pc-app -->

<!--
    Nota: Bootstrap JS y SweetAlert2 NO se cargan aquí.
    Cada vista que los necesite debe cargarlos en su propio <script> JUSTO ANTES
    de su bloque de JS inline (ej. antes de "const modalX = new bootstrap.Modal(...)"),
    para garantizar el orden de ejecución. Si se cargan aquí en el footer, se
    ejecutan DESPUÉS del script de la vista y rompen cualquier uso de bootstrap/Swal
    en el primer render (error "Cannot access ... before initialization").
-->

</body>
</html>