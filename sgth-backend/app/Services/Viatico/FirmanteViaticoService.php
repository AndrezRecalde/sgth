<?php

namespace App\Services\Viatico;

use App\Enums\EstadoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoFirmante;
use App\Services\Estructura\FirmanteOrganigramaService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Sella quién firma cada documento del viático, en el momento de emitirlo.
 *
 * Los tres PDF resolvían las firmas al imprimir: un informe reimpreso meses
 * después salía con quien ocupara el cargo ese día, aunque lo hubiera firmado
 * otra persona. Y las autoridades rotan —el prefecto que autoriza la salida
 * puede estar de vacaciones cuando el servidor presenta el informe—, así que
 * cada documento se sella por separado y con la fecha en que se emite:
 *
 * - la solicitud, al aprobar;
 * - el informe, al presentar la liquidación;
 * - el comprobante, al contabilizar.
 *
 * Quién firma sale del organigrama, con la subrogación vigente de ese día. Si
 * el titular tiene vacaciones aprobadas y nadie registró una subrogación, el
 * documento sale igual a su nombre —es lo único que el sistema sabe— pero la
 * firma queda con el aviso, para que Financiero lo vea y lo corrija.
 */
final class FirmanteViaticoService
{
    public const SOLICITUD   = 'solicitud';
    public const INFORME     = 'informe';
    public const COMPROBANTE = 'comprobante';
    /** La orden de pago del anticipo, que se sella al generarla. */
    public const ORDEN       = 'orden_anticipo';

    /** Quien prepara el pago del anticipo: no es un puesto del organigrama. */
    public const ELABORA = 'elaborado';

    /** Qué firma cada quién, y con qué cargo cuando el puesto está vacante. */
    private const ROLES = [
        'maxima_autoridad'    => 'Prefecto/a Provincial',
        'jefe_unidad'         => 'Director/a de Unidad',
        'director_financiero' => 'Director/a Financiero/a',
    ];

    public function __construct(
        private readonly FirmanteOrganigramaService $organigrama,
    ) {}

    /**
     * Guarda los firmantes del documento. Es idempotente: si ya están sellados
     * no los toca, para que una corrección de estado no reescriba quién firmó.
     *
     * @return Collection<int, ViaticoFirmante>
     */
    public function sellar(Viatico $viatico, string $documento, ?string $fecha = null): Collection
    {
        $yaSellados = ViaticoFirmante::where('viatico_id', $viatico->id)
            ->where('documento', $documento)
            ->get();

        if ($yaSellados->isNotEmpty()) {
            return $yaSellados;
        }

        $fecha = $fecha ?? now()->toDateString();

        return collect(self::ROLES)
            ->map(fn (string $cargoPorDefecto, string $rol) => $this->sellarDelOrganigrama(
                $viatico, $documento, $rol, $cargoPorDefecto, $fecha
            ))
            ->values();
    }

    /**
     * Los firmantes de la orden de pago del anticipo: el Director Financiero,
     * que la ordena, y el funcionario de Financiero que prepara el pago
     * (decidido con Gestión Financiera, 2026-11-23, S4).
     *
     * El segundo no sale del organigrama —ningún puesto es «quien elabora»—:
     * es quien genera la orden. Se sella la primera vez, igual que los otros
     * documentos, para que reimprimirla no le cambie el nombre a un papel que
     * ya está firmado.
     *
     * @return Collection<int, ViaticoFirmante>
     */
    public function sellarOrdenDeAnticipo(Viatico $viatico, ?Servidor $elabora): Collection
    {
        $yaSellados = ViaticoFirmante::where('viatico_id', $viatico->id)
            ->where('documento', self::ORDEN)
            ->get();

        if ($yaSellados->isNotEmpty()) {
            return $yaSellados;
        }

        $fecha = now()->toDateString();

        return collect([
            $this->sellarDelOrganigrama(
                $viatico, self::ORDEN, 'director_financiero',
                self::ROLES['director_financiero'], $fecha
            ),
            ViaticoFirmante::create([
                'viatico_id'  => $viatico->id,
                'documento'   => self::ORDEN,
                'rol'         => self::ELABORA,
                'servidor_id' => $elabora?->id,
                'nombre'      => $elabora ? $this->organigrama->nombreCompleto($elabora) : null,
                'cedula'      => $elabora?->cedula,
                'cargo'       => $elabora?->puesto?->cargo?->nombre ?? 'Gestión Financiera',
                'subrogado'   => false,
                'aviso'       => null,
                'sellado_en'  => now(),
            ]),
        ]);
    }

    /** Un firmante que sale del organigrama, con la subrogación de ese día. */
    private function sellarDelOrganigrama(
        Viatico $viatico,
        string $documento,
        string $rol,
        string $cargoPorDefecto,
        string $fecha
    ): ViaticoFirmante {
        $firma    = $this->organigrama->jefeDeUnidadEn($this->unidadDe($rol, $viatico), $fecha, $cargoPorDefecto);
        $servidor = $firma['servidor'];

        return ViaticoFirmante::create([
            'viatico_id'  => $viatico->id,
            'documento'   => $documento,
            'rol'         => $rol,
            'servidor_id' => $servidor?->id,
            'nombre'      => $servidor ? $this->organigrama->nombreCompleto($servidor) : null,
            'cedula'      => $servidor?->cedula,
            // El cargo impreso es el del puesto; la (S) deja constancia de
            // que quien firmó lo hacía por subrogación.
            'cargo'       => ($firma['cargo'] ?? $cargoPorDefecto).($firma['subrogado'] ? ' (S)' : ''),
            'subrogado'   => $firma['subrogado'],
            'aviso'       => $this->aviso($servidor, $firma['subrogado'], $fecha),
            'sellado_en'  => now(),
        ]);
    }

    /**
     * Los firmantes de un documento, listos para imprimir.
     *
     * Si el viático es anterior al sellado no hay nada guardado: se resuelven
     * al vuelo, como se hacía antes, y el PDF sale con quien ocupa el cargo hoy.
     *
     * @return array<string, array{nombre: ?string, cargo: string, aviso: ?string}>
     */
    public function paraDocumento(Viatico $viatico, string $documento): array
    {
        $sellados = ViaticoFirmante::where('viatico_id', $viatico->id)
            ->where('documento', $documento)
            ->get()
            ->keyBy('rol');

        $firmas = [];

        foreach ($this->rolesDe($documento) as $rol => $cargoPorDefecto) {
            if ($firmante = $sellados->get($rol)) {
                $firmas[$rol] = [
                    'nombre' => $firmante->nombre,
                    'cargo'  => $firmante->cargo,
                    'aviso'  => $firmante->aviso,
                ];

                continue;
            }

            // Quien elabora no se puede resolver al vuelo: no es un puesto,
            // es quien generó la orden. Sin sellar, la línea sale en blanco.
            if ($rol === self::ELABORA) {
                $firmas[$rol] = ['nombre' => null, 'cargo' => $cargoPorDefecto, 'aviso' => null];

                continue;
            }

            $firma    = $this->organigrama->jefeDeUnidadEn($this->unidadDe($rol, $viatico), now()->toDateString(), $cargoPorDefecto);
            $servidor = $firma['servidor'];

            $firmas[$rol] = [
                'nombre' => $servidor ? $this->organigrama->nombreCompleto($servidor) : null,
                'cargo'  => ($firma['cargo'] ?? $cargoPorDefecto).($firma['subrogado'] ? ' (S)' : ''),
                'aviso'  => null,
            ];
        }

        return $firmas;
    }

    /**
     * Quién firma cada documento. La orden de pago del anticipo lleva dos
     * firmas, no las tres del resto del trámite.
     *
     * @return array<string, string>
     */
    private function rolesDe(string $documento): array
    {
        return $documento === self::ORDEN
            ? [
                'director_financiero' => self::ROLES['director_financiero'],
                self::ELABORA         => 'Gestión Financiera',
            ]
            : self::ROLES;
    }

    /** La unidad de la que sale cada firma. */
    private function unidadDe(string $rol, Viatico $viatico): mixed
    {
        return match ($rol) {
            'maxima_autoridad'    => $this->organigrama->unidadAnclada('es_maxima_autoridad'),
            'director_financiero' => $this->organigrama->unidadAnclada('es_unidad_financiera'),
            // La del servidor que viaja, que es quien autoriza su comisión.
            'jefe_unidad'         => $viatico->servidor?->puesto?->unidad_administrativa_id,
        };
    }

    /**
     * El sistema no puede saber quién firmó de verdad: si el titular estaba de
     * vacaciones y nadie registró la subrogación, lo dice en vez de callarlo.
     */
    private function aviso(?Servidor $servidor, bool $subrogado, string $fecha): ?string
    {
        if (! $servidor || $subrogado) {
            return null;
        }

        // Las que Talento Humano ya registró, y las ya gozadas: una que el jefe
        // autorizó pero que no pasó por ventanilla no prueba que faltara.
        $enVacaciones = Vacacion::where('servidor_id', $servidor->id)
            ->whereIn('estado', EstadoVacacion::confirmadas())
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->exists();

        return $enVacaciones
            ? 'Tenía vacaciones el '.Carbon::parse($fecha)->format('d/m/Y')
                .' y no había subrogación registrada: confirme quién firma.'
            : null;
    }
}
