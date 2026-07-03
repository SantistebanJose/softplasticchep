<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/controllers/clssAuth.php';

cerrarSesionUsuario();

redirect('login.php');