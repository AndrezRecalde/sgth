<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Un usuario de PRUEBA por rol, para recorrer el sistema con lo que ve y puede
 * hacer cada uno (docs/plan-roles-y-accesos.md, PR 1).
 *
 * Se probaba con `crecalde`, que tiene siete roles. Así nadie vio que el
 * buscador de servidores le respondía 403 a todo el personal de TIC: los roles
 * de Talento Humano de esa cuenta lo tapaban.
 *
 * NO está en DatabaseSeeder a propósito: son cuentas ficticias, solo para el
 * entorno local. Se ejecuta a mano:
 *
 *     php artisan db:seed --class=UsuariosPruebaPorRolSeeder
 *
 * - La contraseña de todas sale de SGTH_CLAVE_USUARIOS_PRUEBA, en el .env. No
 *   se escribe aquí, como sí hace AdminTiSeeder con la suya.
 * - Cada usuario es `prueba.<rol>`, con el correo `prueba.<rol>@gadpe.test`: el
 *   dominio no existe, así que ningún aviso llega a un buzón real. Tiene un
 *   solo rol y no se le pide cambiar la clave al entrar. Un rol complementario
 *   va con su rol base (`Rol::rolBase()`): `prueba.medico-ocupacional` también
 *   es `medico`, porque solo no sirve.
 * - Cada uno queda vinculado a su ficha «Usuario PRUEBA <ROL>» (cédula
 *   0999000xxx), en Talento Humano. Sin la ficha, el Portal le cerraba casi
 *   todo. La que crea este seeder no tiene contrato ni marcación biométrica: la
 *   marcación en línea escribe en Sirha7, y una cédula inventada no tiene nada
 *   que hacer ahí.
 * - Se puede volver a correr. Reutiliza los usuarios y las fichas, les vuelve a
 *   poner la contraseña del .env y les deja un solo rol. No toca los permisos
 *   directos: el que se le dé a mano a una cuenta de prueba —`marcar-en-linea`,
 *   `publicar-comunicados`— se queda.
 * - Un rol que todavía no existe en la base se salta con un aviso.
 */
class UsuariosPruebaPorRolSeeder extends Seeder
{
    /** Prefijo reconocible de las cédulas de las fichas de prueba. */
    private const PREFIJO_CEDULA = '0999000';

    /** Donde quedan las fichas, como la de Recepción que ya existía. */
    private const UNIDAD = 'Gestión de Talento Humano y Riesgos Laborales';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException(
                'Los usuarios de prueba solo se crean en el entorno local, y este es «'.app()->environment().'».'
            );
        }

        $clave = config('auth.usuarios_prueba.clave');

        if (blank($clave)) {
            throw new \RuntimeException(
                'Falta SGTH_CLAVE_USUARIOS_PRUEBA en el .env: es la contraseña de todos los usuarios de prueba.'
            );
        }

        $unidad = UnidadAdministrativa::where('nombre', self::UNIDAD)->first();

        if (! $unidad) {
            throw new \RuntimeException(
                "No se encontró la unidad '".self::UNIDAD."'. Ejecute antes UnidadAdministrativaSeeder."
            );
        }

        // Una sola vez: es la misma para todos, y cifrarla es lo lento.
        $hash = Hash::make($clave);

        foreach (Rol::cases() as $rol) {
            if (! Role::where('name', $rol->value)->where('guard_name', 'sanctum')->exists()) {
                $this->command?->warn("  {$rol->value}: el rol no existe en la base, se salta.");

                continue;
            }

            $usuario = $this->usuario($rol, $hash, $unidad);

            $this->command?->line("  {$usuario->usuario_ti} — {$rol->etiqueta()}");
        }
    }

    private function usuario(Rol $rol, string $hash, UnidadAdministrativa $unidad): User
    {
        $usuarioTi = 'prueba.'.$rol->value;

        $usuario = User::firstOrNew(['usuario_ti' => $usuarioTi]);

        $usuario->fill([
            'email'        => $usuarioTi.'@gadpe.test',
            'password'     => $hash,
            'primer_login' => false,
            'activo'       => true,
        ]);

        $usuario->servidor_id ??= $this->ficha($rol, $unidad)->id;
        $usuario->save();

        $usuario->syncRoles(array_values(array_filter([$rol->rolBase()?->value, $rol->value])));

        return $usuario;
    }

    /**
     * La ficha de prueba de ese rol: la que ya tenga, o una nueva con la
     * siguiente cédula libre del prefijo.
     */
    private function ficha(Rol $rol, UnidadAdministrativa $unidad): Servidor
    {
        $apellido = 'PRUEBA '.mb_strtoupper(str_replace('-', ' ', $rol->value));

        $existente = Servidor::where('cedula', 'like', self::PREFIJO_CEDULA.'%')
            ->where('apellido', $apellido)
            ->whereDoesntHave('usuario')
            ->first();

        if ($existente) {
            return $existente;
        }

        return Servidor::create([
            'cedula'                   => $this->siguienteCedula(),
            'nombre'                   => 'Usuario',
            'apellido'                 => $apellido,
            // Sin contrato no hay de dónde derivar el régimen, que es lo que
            // hace ContratoServidorService al registrar un ingreso.
            'regimen_laboral'          => 'losep',
            'unidad_administrativa_id' => $unidad->id,
            'estado'                   => true,
            'puede_marcar'             => false,
        ]);
    }

    private function siguienteCedula(): string
    {
        $ultima = Servidor::withTrashed()
            ->where('cedula', 'like', self::PREFIJO_CEDULA.'%')
            ->max('cedula');

        $numero = $ultima ? (int) substr($ultima, strlen(self::PREFIJO_CEDULA)) + 1 : 1;

        return self::PREFIJO_CEDULA.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }
}
