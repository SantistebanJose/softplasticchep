<?php
session_start();
require __DIR__ . '/controllers/clssAuth.php';

cerrarSesionUsuario();

header('Location: login.php');
exit;