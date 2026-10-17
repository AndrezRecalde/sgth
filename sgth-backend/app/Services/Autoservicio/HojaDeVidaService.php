<?php

namespace App\Services\Autoservicio;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\EstadoFormacion;
use App\Enums\TipoEstudio;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Services\Comunicacion\FotografiaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * La hoja de vida en PDF de «Mi perfil», armada con lo que ya está en el
 * expediente.
 *
 * La hoja de vida no es el certificado laboral: no lleva firma, código ni
 * remuneración, y lo dice al pie. El certificado lo emite solo Talento Humano
 * (decisión de la UATH del 2026-09-25).
 */
final class HojaDeVidaService
{
    /** Los mismos que ofrece la ficha (`ESTADO_CIVIL_OPTIONS` del frontend). */
    private const ESTADOS_CIVILES = [
        'soltero'     => 'Soltero/a',
        'casado'      => 'Casado/a',
        'divorciado'  => 'Divorciado/a',
        'viudo'       => 'Viudo/a',
        'union_libre' => 'Unión libre',
    ];

    public function __construct(
        private readonly IdentidadInstitucionalServiceInterface $identidad,
    ) {}

    /** @return array<string, mixed> */
    public function datos(Servidor $servidor): array
    {
        $servidor->loadMissing([
            'contratos.unidadAdministrativa',
            'contratos.puesto.cargo',
            'contratoVigente.puesto.cargo',
            'contratoVigente.unidadAdministrativa',
            'unidadAdministrativa',
            'puesto.cargo',
            'fotografiaVigente',
        ]);

        [$estudios, $capacitaciones] = $this->formacionValidada($servidor)
            ->partition(fn (HistorialAcademicoServidor $h) => $h->tipo_estudio === TipoEstudio::ESTUDIO);

        return [
            'nombre_completo' => $servidor->nombre_completo,
            'cedula'          => $servidor->cedula,
            'personales'      => array_filter([
                'Fecha de nacimiento' => $servidor->fecha_nacimiento?->format('d/m/Y'),
                'Nacionalidad'        => $servidor->es_extranjero ? $servidor->nacionalidad : 'Ecuatoriana',
                'Estado civil'        => self::ESTADOS_CIVILES[$servidor->estado_civil] ?? null,
                'Correo personal'     => $servidor->correo_personal,
                'Teléfono celular'    => $servidor->telefono_celular,
                'Dirección'           => $servidor->direccion_domicilio,
            ]),
            'cargo_actual'    => $servidor->contratoVigente?->puesto?->cargo?->nombre
                ?? $servidor->puesto?->cargo?->nombre,
            'unidad_actual'   => $servidor->contratoVigente?->unidadAdministrativa?->nombre
                ?? $servidor->unidadAdministrativa?->nombre,
            'regimen'         => $servidor->regimen_laboral?->etiqueta(),
            'ingreso'         => $servidor->fecha_ingreso_institucion?->format('d/m/Y'),
            'estudios'        => $estudios->values()->map(fn (HistorialAcademicoServidor $h) => [
                'titulo'      => $h->titulo_capacitacion,
                'nivel'       => $h->nivel_estudio?->etiqueta(),
                'institucion' => $h->institucion,
                'extranjero'  => $h->nacionalidad_estudio?->value === 'internacional',
                'periodo'     => $this->periodo($h->fecha_inicio?->year, $h->fecha_fin?->year),
                'senescyt'    => $h->codigo_senescyt,
            ])->all(),
            'capacitaciones'  => $capacitaciones->values()->map(fn (HistorialAcademicoServidor $h) => [
                'titulo'      => $h->titulo_capacitacion,
                'institucion' => $h->institucion,
                'periodo'     => $this->periodo(
                    $h->fecha_inicio?->format('m/Y'),
                    $h->fecha_fin?->format('m/Y'),
                ),
            ])->all(),
            // Los vínculos cancelados quedan fuera, como en el certificado: un
            // vínculo anulado se dejó sin efecto, no es tiempo trabajado.
            'trayectoria'     => $servidor->contratos
                ->reject(fn ($c) => $c->estado?->value === 'cancelado')
                ->sortByDesc('fecha_inicio')
                ->values()
                ->map(fn ($c) => [
                    'puesto'       => $c->puesto?->cargo?->nombre,
                    'unidad'       => $c->unidadAdministrativa?->nombre,
                    'nombramiento' => $c->tipo_nombramiento?->etiqueta(),
                    'desde'        => $c->fecha_inicio?->format('d/m/Y'),
                    'hasta'        => $c->fecha_fin?->format('d/m/Y'),
                ])->all(),
            'subrogaciones'   => Subrogacion::with('puestoSubrogado.cargo', 'unidadAdministrativa')
                ->where('servidor_subrogante_id', $servidor->id)
                ->whereIn('estado', ['activa', 'finalizada'])
                ->orderByDesc('fecha_inicio')
                ->get()
                ->map(fn ($s) => [
                    'tipo'   => $s->tipo?->etiqueta(),
                    'puesto' => $s->puestoSubrogado?->cargo?->nombre,
                    'unidad' => $s->unidadAdministrativa?->nombre,
                    'desde'  => $s->fecha_inicio?->format('d/m/Y'),
                    'hasta'  => $s->fecha_fin?->format('d/m/Y'),
                ])->all(),
            'foto'            => $this->foto($servidor),
        ];
    }

    public function pdf(Servidor $servidor): string
    {
        $pdf = Pdf::loadView('pdf.autoservicio.hoja-de-vida', [
            ...$this->datos($servidor),
            // La hoja membretada y los datos de la institución vigentes hoy.
            ...$this->identidad->paraDocumento(),
        ])->setPaper('a4');

        // La página, sobre la línea del pie: el total solo se sabe al final.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(
            495, 774, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('helvetica'), 7.5, [0.4, 0.4, 0.4],
        );

        return $dompdf->output();
    }

    public function nombreArchivo(Servidor $servidor): string
    {
        return "hoja_de_vida_{$servidor->cedula}.pdf";
    }

    /**
     * Solo lo validado: lo que el servidor registró desde el portal no entra
     * mientras Talento Humano no lo revise, ni lo rechazado. Del más reciente
     * al más viejo.
     *
     * @return Collection<int, HistorialAcademicoServidor>
     */
    private function formacionValidada(Servidor $servidor): Collection
    {
        return $servidor->historialAcademico()
            ->where('estado', EstadoFormacion::VALIDADO)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();
    }

    private function periodo(int|string|null $desde, int|string|null $hasta): string
    {
        return ($desde ?? '—').' – '.($hasta ?? 'En curso');
    }

    /** La foto que registró Comunicación Social, por ruta: dompdf la lee del disco. */
    private function foto(Servidor $servidor): ?string
    {
        $fotografia = $servidor->fotografiaVigente;
        $disco = Storage::disk(FotografiaService::DISCO);

        return $fotografia && $disco->exists($fotografia->archivo)
            ? $disco->path($fotografia->archivo)
            : null;
    }
}
