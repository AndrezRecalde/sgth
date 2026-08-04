<?php

namespace Database\Seeders;

use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Database\Seeder;

class ServidorTicSeeder extends Seeder
{
    public function run(): void
    {
        // Garantiza que existan el puesto/cargo de TIC y el usuario admin-TI
        // a los que se va a vincular esta ficha (todos son idempotentes).
        $this->call([
            PuestoTicSeeder::class,
            RolPermisoSeeder::class,
            AdminTiSeeder::class,
        ]);

        $unidad = UnidadAdministrativa::where(
            'nombre',
            'Gestión de Tecnologías de la Información y Comunicación'
        )->firstOrFail();

        $puesto = Puesto::where('unidad_administrativa_id', $unidad->id)->firstOrFail();

        $servidor = Servidor::updateOrCreate(
            ['cedula' => '0802704171'],
            [
                'nombre' => 'Cristhian',
                'segundo_nombre' => 'Andrés',
                'apellido' => 'Recalde',
                'segundo_apellido' => 'Solano',
                'fecha_nacimiento' => '1993-08-19',
                'telefono_celular' => '0939242242',
                'regimen_laboral' => 'losep',
                'unidad_administrativa_id' => $unidad->id,
                'puesto_id' => $puesto->id,
                'estado' => true,
                'puede_marcar' => true,
            ]
        );

        // Solo se crea el contrato vigente si el servidor aún no tiene uno
        // registrado (evita duplicar contratos al re-ejecutar el seeder).
        if (!$servidor->contratos()->where('estado', 'vigente')->exists()) {
            app(ContratoServidorService::class)->crear($servidor->id, [
                'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
                'unidad_administrativa_id' => $unidad->id,
                'puesto_id' => $puesto->id,
                'fecha_inicio' => '2021-03-01',
                'estado' => 'vigente',
                'remuneracion' => 1412.00,
            ]);
        }

        // Vincula la ficha con el usuario creado en AdminTiSeeder.
        User::where('email', 'crecalde@gadpe.gob.ec')
            ->update(['servidor_id' => $servidor->id]);
    }
}
