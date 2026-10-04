<?php

namespace App\Policies\Expediente;

use App\Models\User;
use App\Models\Expediente\Servidor;

/**
 * No hay `super-admin` en ninguna rama, como en SubrogacionPolicy: ese rol no
 * está en el enum `Rol` ni lo siembra `RolPermisoSeeder`. Tampoco hay
 * `eliminar`: una ficha no se borra —la ruta excluye `destroy`—, se
 * desvincula con una acción de personal.
 */
class ServidorPolicy
{
    /**
     * Listar los servidores de la institución.
     *
     * asistente-uath registra permisos a nombre de cualquier servidor
     * (`registrar-permisos-servidores`), y para eso el formulario lista los
     * servidores de la unidad elegida. Sin él aquí, ese selector le respondía
     * 403 y no podía elegir a nadie.
     *
     * Se agrega el rol y no `ver-expediente-todos`, que también tienen
     * analista-uath, maxima-autoridad y auditor: abrirlo por ese permiso
     * habría ampliado el listado a más roles de los que lo necesitan.
     */
    public function verAny(User $user): bool
    {
        return $user->hasRole('admin-uath')
            || $user->hasRole('asistente-uath');
    }

    /**
     * Determine whether the user can view the model.
     *
     * 'asistente-uath' entró aquí el 2026-09-27. Las rutas de Acciones de
     * Personal le conceden el rol —registrar, transicionar, la bandeja— pero
     * los controladores autorizan contra este policy, así que veía la bandeja
     * y recibía 403 al abrir cualquier fila, al pedir el PDF y al intentar
     * registrar o hacer avanzar una acción: la ruta y el policy se
     * contradecían. Se cierra por aquí, que es lo que decidió Talento Humano,
     * y no con un policy propio de MovimientoPersonal.
     *
     * Lo que esto abre, además de las acciones de personal: el expediente
     * completo de cualquier servidor y sus documentos (listar y descargar).
     * Coherente con lo que el asistente ya gestionaba por middleware de ruta
     * —cargas familiares, sus discapacidades y enfermedades, permisos—.
     */
    public function ver(User $user, Servidor $servidor): bool
    {
        // Un servidor solo puede ver su propio expediente. UATH puede ver todos.
        if ($user->hasRole('admin-uath') || $user->hasRole('asistente-uath')) {
            return true;
        }

        // servidores.user_id ya no existe (la FK se invirtió en
        // 2026_05_27_161227_reestructurar_relacion_users_servidores.php):
        // ahora es users.servidor_id el que apunta al Servidor.
        return $user->servidor_id === $servidor->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function crear(User $user): bool
    {
        return $user->hasRole('admin-uath');
    }

    /**
     * Determine whether the user can update the model.
     *
     * 'asistente-uath' entró aquí el 2026-09-27 por el mismo motivo que en
     * ver(): registrar y transicionar una acción de personal autorizan contra
     * este método. Deliberadamente NO entra en crear(), y de ahí sale el
     * límite: `UpdateServidorRequest` da la ficha entera solo a quien pasa
     * `can('crear', Servidor::class)` y marca el resto de campos como
     * `prohibited`, así que el asistente edita los cuatro campos de contacto
     * del titular —teléfonos, correo, domicilio— y nada más. Cédula, régimen y
     * fecha de ingreso siguen siendo de admin-uath.
     *
     * Lo que sí gana además de las acciones de personal: subir y borrar
     * documentos del expediente de cualquier servidor, que no tienen middleware
     * de rol y se autorizan solo por aquí.
     */
    public function actualizar(User $user, Servidor $servidor): bool
    {
        // El servidor titular puede actualizar partes no sensibles (ej. teléfono),
        // pero UATH puede actualizar todo. La validación de campos se delega al Request/Service.
        // Aquí validamos el acceso general a la actualización.
        if ($user->hasRole('admin-uath') || $user->hasRole('asistente-uath')) {
            return true;
        }

        return $user->servidor_id === $servidor->id;
    }
}
