<?php
session_start();
require __DIR__ . '/../controllers_tablet/clssAuthOperario.php';

cerrarSesionOperario();
session_destroy();

header('Location: loginoperarios.php');
exit;