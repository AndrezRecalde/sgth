<?php

namespace App\Services\Expediente;

use App\Enums\RolFirmaAccionPersonal;
use App\Mail\Expediente\CertificadoLaboralMail;
use App\Enums\TipoCertificadoLaboral;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CertificadoLaboralService
{
    /** Lo que la UATH fijó el 2026-09-25. */
    private const DIAS_DE_VIGENCIA = 30;

    public function __construct(
        private readonly FirmanteAccionPersonalService $firmantes,
    ) {
    }

    /**
     * Emite el certificado y deja constancia.
     *
     * La emisión se registra siempre, aunque el PDF se genere después: el
     * código impreso tiene que existir en la bitácora para que la página de
     * verificación pueda confirmarlo.
     */
    public function emitir(
        Servidor $servidor,
        bool $conRemuneracion,
        int $emitidoPor,
    ): EmisionCertificadoLaboral {
        $servidor->loadMissing([
            'contratos.unidadAdministrativa',
            'contratos.puesto.cargo',
            'unidadAdministrativa',
            'puesto.cargo',
        ]);

        $tipo = TipoCertificadoLaboral::paraRegimen($servidor->regimen_laboral?->value);
        $firma = $this->firmantes->resolver(
            RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO,
            now()->toDateString(),
        );
        $firmante = $firma['servidor'];

        return EmisionCertificadoLaboral::create([
            'codigo'           => $this->generarCodigo(),
            'servidor_id'      => $servidor->id,
            'tipo'             => $tipo->value,
            'con_remuneracion' => $conRemuneracion,
            'emitido_por'      => $emitidoPor,
            'emitido_en'       => now(),
            'vence_en'         => now()->addDays(self::DIAS_DE_VIGENCIA)->toDateString(),
            'firmante_nombre'  => $firmante
                ? trim("{$firmante->apellido} {$firmante->nombre}")
                : null,
            'firmante_cargo'   => $firma['cargo'],
            'firmante_cedula'  => $firmante?->cedula,
            'datos'            => $this->fotografiar($servidor, $tipo, $conRemuneracion),
        ]);
    }

    /**
     * A qué dirección se manda, y de dónde salió.
     *
     * Regla de la UATH del 2026-09-25: el correo institucional, que vive en la
     * cuenta de usuario. Si no tiene cuenta —o ya salió de la institución—, el
     * personal. Y si no hay ninguno, no se inventa nada: se le dice a Talento
     * Humano que lo descargue y lo envíe a mano.
     *
     * @return array{direccion: ?string, origen: string}
     */
    public function destinatario(Servidor $servidor): array
    {
        $servidor->loadMissing('usuario');

        if ($institucional = $servidor->usuario?->email) {
            return ['direccion' => $institucional, 'origen' => 'institucional'];
        }

        if ($personal = $servidor->correo_personal) {
            return ['direccion' => $personal, 'origen' => 'personal'];
        }

        return ['direccion' => null, 'origen' => 'sin_direccion'];
    }

    /**
     * Envía el certificado ya emitido. Devuelve de dónde salió la dirección,
     * o 'sin_direccion' si no había ninguna.
     *
     * Síncrono a propósito: quien emite espera la respuesta y necesita saber
     * en ese momento si el correo salió o si le toca enviarlo a mano.
     */
    public function enviar(EmisionCertificadoLaboral $emision, Servidor $servidor): string
    {
        $destino = $this->destinatario($servidor);

        if (! $destino['direccion']) {
            return 'sin_direccion';
        }

        // Un correo que no sale no puede llevarse por delante la emisión.
        //
        // Se descubrió probando contra el servidor real: el SMTP no respondía,
        // la excepción subió hasta el controlador y quien emitía perdía el PDF
        // —ya descargado en su navegador— de un certificado que SÍ había
        // quedado registrado en la bitácora. Ahora se avisa de que hay que
        // enviarlo a mano, que es justo la salida que la UATH pidió para
        // cuando no hay dirección.
        try {
            Mail::to($destino['direccion'])->send(new CertificadoLaboralMail(
                $emision,
                $this->pdf($emision),
                $this->nombreArchivo($emision),
            ));
        } catch (\Throwable $e) {
            Log::error('No salió el correo del certificado laboral', [
                'emision' => $emision->id,
                'codigo'  => $emision->codigo,
                'destino' => $destino['origen'],
                'motivo'  => $e->getMessage(),
            ]);

            return 'fallo_envio';
        }

        return $destino['origen'];
    }

    /** El PDF de una emisión, compuesto a partir de su foto. */
    public function pdf(EmisionCertificadoLaboral $emision): string
    {
        return Pdf::loadView('pdf.expediente.certificado-laboral', [
            'emision' => $emision,
            'datos'   => $emision->datos,
            'tipo'    => $emision->tipo,
            'logo'    => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait')->output();
    }

    public function nombreArchivo(EmisionCertificadoLaboral $emision): string
    {
        return "certificado_{$emision->datos['cedula']}_{$emision->codigo}.pdf";
    }

    /**
     * Lo que el documento dice hoy, congelado.
     *
     * Sin esto la página de verificación no podría confirmar un certificado de
     * hace seis meses: para entonces la persona puede haber cambiado de unidad
     * o sumado otro vínculo, y el documento en papel seguiría diciendo lo de
     * antes.
     */
    private function fotografiar(
        Servidor $servidor,
        TipoCertificadoLaboral $tipo,
        bool $conRemuneracion,
    ): array {
        // Los cancelados quedan fuera: un vínculo anulado se dejó sin efecto,
        // no es tiempo trabajado. La UATH lo confirmó el 2026-09-26.
        $periodos = $servidor->contratos
            ->reject(fn ($c) => $c->estado?->value === 'cancelado')
            ->sortBy('fecha_inicio')
            ->values()
            ->map(fn ($c) => [
                'nombramiento'    => $c->tipo_nombramiento?->etiqueta() ?? '—',
                'numero_contrato' => $c->numero_contrato,
                'unidad'          => $c->unidadAdministrativa?->nombre,
                // `puesto->denominacion` no existe en el modelo: hasta hoy la
                // columna Puesto del certificado imprimía «N/A» en todas las
                // filas. El nombre del cargo vive en puesto.cargo.
                'puesto'          => $c->puesto?->cargo?->nombre,
                'desde'           => $c->fecha_inicio?->toDateString(),
                'hasta'           => $c->fecha_fin?->toDateString(),
                'remuneracion'    => $conRemuneracion ? $c->remuneracion : null,
            ])->all();

        return [
            'nombre_completo'  => $servidor->nombre_completo,
            'cedula'           => $servidor->cedula,
            'regimen'          => $servidor->regimen_laboral?->etiqueta(),
            'cargo_actual'     => $servidor->contratoVigente?->puesto?->cargo?->nombre
                ?? $servidor->puesto?->cargo?->nombre,
            'unidad_actual'    => $servidor->contratoVigente?->unidadAdministrativa?->nombre
                ?? $servidor->unidadAdministrativa?->nombre,
            'ingreso'          => $servidor->fecha_ingreso_institucion?->toDateString(),
            // En la institución, no en el sector público: así lo pidió la UATH
            // el 2026-09-25. El accesor `anios_servicio` del modelo cuenta
            // desde el sector público en régimen LOSEP, y por eso no se usa.
            'anios_servicio'   => $servidor->fecha_ingreso_institucion
                ? (int) floor($servidor->fecha_ingreso_institucion->diffInYears(now()))
                : null,
            'etiqueta_tiempo'  => $tipo->etiquetaTiempo(),
            'periodos'         => $periodos,
            'subrogaciones'    => $this->subrogaciones($servidor),
            'con_remuneracion' => $conRemuneracion,
        ];
    }

    /**
     * Subrogaciones y encargos que la persona ejerció. La UATH pidió que
     * consten: sirven para acreditar experiencia en un concurso de méritos.
     *
     * Solo las que surtieron efecto — una pendiente todavía no empezó y una
     * cancelada nunca llegó a ocurrir.
     */
    private function subrogaciones(Servidor $servidor): array
    {
        return Subrogacion::with('puestoSubrogado.cargo', 'unidadAdministrativa')
            ->where('servidor_subrogante_id', $servidor->id)
            ->whereIn('estado', ['activa', 'finalizada'])
            ->orderBy('fecha_inicio')
            ->get()
            ->map(fn ($s) => [
                'tipo'   => $s->tipo?->etiqueta(),
                'puesto' => $s->puestoSubrogado?->cargo?->nombre,
                'unidad' => $s->unidadAdministrativa?->nombre,
                'desde'  => $s->fecha_inicio?->toDateString(),
                'hasta'  => $s->fecha_fin?->toDateString(),
            ])->all();
    }

    /**
     * Aleatorio, no correlativo: con un número de serie cualquiera adivina el
     * siguiente y va probando en la página de verificación.
     */
    private function generarCodigo(): string
    {
        do {
            $codigo = 'CL-'.strtoupper(Str::random(4))
                .'-'.strtoupper(Str::random(4))
                .'-'.strtoupper(Str::random(4));
        } while (EmisionCertificadoLaboral::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
