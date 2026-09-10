<?php

namespace App\Services\Dispensario;

use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\RecetaMedica;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * La receta médica en papel.
 *
 * Es el único documento del dispensario que sale del edificio en manos del
 * paciente: lo presenta en el mostrador para que le entreguen lo que hay, y se
 * lo lleva a una farmacia externa para comprar lo que el dispensario no
 * maneja. De ahí lo que lleva impreso y lo que no.
 */
final class PdfRecetaService
{
    /**
     * Datos de la institución que emite. Van aquí y no en el impreso porque el
     * RUC y el establecimiento son los mismos que ya declara el formulario
     * ocupacional (SNS-MSP/HCU-form.123), y conviene que no se contradigan.
     */
    private const INSTITUCION = [
        'nombre'          => 'GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS',
        'unidad'          => 'Dispensario Médico Laboral',
        'ruc'             => '0860000160001',
        'establecimiento' => 'Dispensario Médico Laboral del GADPE',
    ];

    /** @return array{content: string, filename: string} */
    public function generarContent(int $id): array
    {
        // Sin los diagnósticos: el impreso no los lleva a propósito —ver la
        // nota en la plantilla— y traerlos sería cargar de la base un dato
        // que no se va a usar.
        $receta = RecetaMedica::with([
            'items.inventario',
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar',
            'consultaMedica.historiaClinica.alergias',
            'consultaMedica.medico.servidor',
            'anulador.servidor',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.dispensario.receta-medica', [
            'receta'      => $receta,
            'institucion' => self::INSTITUCION,
            'paciente'    => $this->datosDelPaciente($receta),
            'prescriptor' => $this->datosDelPrescriptor($receta),
            'alergias'    => $this->alergiasAMedicamentos($receta),
            // Del catálogo primero y lo externo después: lo que el paciente
            // recoge aquí va junto, y lo que tiene que comprar fuera también.
            'items'       => $receta->items
                ->sortBy(fn (ItemReceta $item) => $item->esExterno() ? 1 : 0)
                ->values(),
            'logo'        => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => 'receta-' . ($receta->folio ?? $receta->id) . '.pdf',
        ];
    }

    /**
     * Quién es el paciente, venga de servidor o de carga familiar.
     *
     * La edad y el sexo se imprimen porque una dosis se lee contra ellos: la
     * misma cantidad no significa lo mismo en un adulto que en un niño, y quien
     * despacha —aquí o en una farmacia externa— necesita poder detectarlo.
     *
     * @return array{nombre: string, cedula: ?string, edad: ?int, sexo: ?string}
     */
    private function datosDelPaciente(RecetaMedica $receta): array
    {
        $historia = $receta->consultaMedica?->historiaClinica;
        $servidor = $historia?->servidor;
        $familiar = $historia?->cargaFamiliar;

        if ($servidor) {
            return [
                'nombre'    => trim("{$servidor->nombre} {$servidor->apellido}"),
                'cedula'    => $servidor->cedula,
                'edad'      => $this->edad($servidor->fecha_nacimiento),
                'sexo'      => $servidor->genero,
            ];
        }

        if ($familiar) {
            return [
                'nombre'    => trim("{$familiar->nombres} {$familiar->apellidos}"),
                'cedula'    => $familiar->cedula,
                'edad'      => $this->edad($familiar->fecha_nacimiento),
                // La carga familiar no registra sexo: `cargas_familiares` no
                // tiene esa columna, y el impreso lo deja en blanco antes que
                // suponerlo.
                'sexo'      => null,
            ];
        }

        return [
            'nombre'    => '—',
            'cedula'    => null,
            'edad'      => null,
            'sexo'      => null,
        ];
    }

    /**
     * Quién prescribe.
     *
     * El código médico es su registro profesional ante el ACESS, el mismo que
     * pide el formulario ocupacional. Una receta sin él no identifica a quien
     * la firma más que por el nombre, y el nombre se repite.
     *
     * @return array{nombre: string, cedula: ?string, codigo: ?string}
     */
    private function datosDelPrescriptor(RecetaMedica $receta): array
    {
        $medico   = $receta->consultaMedica?->medico;
        $servidor = $medico?->servidor;

        return [
            'nombre' => $medico?->nombre_completo
                ?? trim(($servidor?->nombre ?? '') . ' ' . ($servidor?->apellido ?? ''))
                ?: '—',
            'cedula' => $servidor?->cedula,
            'codigo' => $servidor?->codigo_medico,
        ];
    }

    /**
     * Las alergias a medicamentos vigentes en la historia.
     *
     * Se imprimen en la receta, no solo en la pantalla: quien la despacha en
     * una farmacia externa no tiene acceso a la historia clínica, y es
     * precisamente quien puede estar a punto de entregar el fármaco al que el
     * paciente reacciona.
     *
     * Las anuladas se quedan fuera. Una alergia se anula cuando se descarta, y
     * arrastrarla al papel haría que el paciente cargara para siempre con una
     * advertencia que su médico ya retiró.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Dispensario\AlergiaPaciente>
     */
    private function alergiasAMedicamentos(RecetaMedica $receta)
    {
        return $receta->consultaMedica?->historiaClinica?->alergias
            ?->whereNull('anulado_en')
            ->where('tipo', 'medicamento')
            ->values()
            ?? collect();
    }

    private function edad(mixed $fechaNacimiento): ?int
    {
        if (! $fechaNacimiento) {
            return null;
        }

        return \Carbon\Carbon::parse($fechaNacimiento)->age;
    }
}
