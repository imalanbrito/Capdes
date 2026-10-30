<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

if (es_post()) {
    verificar_csrf();
    if (usuario_actual()) {
        registrar('Salió del panel');
    }
    cerrar_sesion();
    session_start();
    avisar('Saliste del panel.', 'info');
}
redirigir('entrar.php');
