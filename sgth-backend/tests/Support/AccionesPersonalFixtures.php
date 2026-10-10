<?php

/**
 * Da a los roles de Talento Humano los permisos del trámite de una acción de
 * personal, con la misma migración que los reparte en producción.
 *
 * RefreshDatabase corre las migraciones antes de que la prueba cree sus roles,
 * así que la migración encuentra los permisos pero ningún rol al que dárselos.
 * Las pruebas que crean `admin-uath` o `asistente-uath` y usan las rutas de
 * acciones de personal la vuelven a correr después de crearlos.
 */
function permisosDeAccionesPersonal(): void
{
    (require database_path('migrations/2026_10_09_120000_crear_permisos_acciones_personal.php'))->up();
}
