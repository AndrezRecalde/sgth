<?php

namespace App\Services\Dispensario;

use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * El certificado de aptitud médica ocupacional.
 *
 * Es el papel que Talento Humano necesita de una evaluación, y no es el FEMO.
 * El formulario 028 del MSP lleva motivo de consulta, antecedentes personales,
 * examen físico regional y diagnóstico CIE-10: historia clínica, que por el
 * acuerdo con la UATH del 2026-09-26 no entra al expediente administrativo
 * —es la razón por la que `observaciones` se excluye del listado de
 * solicitudes y por la que se retiró el botón de descarga del expediente.
 *
 * Aquí viaja solo lo que condiciona un puesto: la aptitud, sus restricciones,
 * la fecha del acto médico, hasta cuándo rige y quién firma con su registro
 * ante el ACESS. Ni un diagnóstico.
 */
final class CertificadoAptitudService
{
    public const VISTA = 'pdf.dispensario.certificado-aptitud';

    /** @return array{content: string, filename: string} */
    public function generarContent(int $solicitudId): array
    {
        ['datos' => $datos, 'filename' => $filename] = $this->componer($solicitudId);

        return [
            'content' => Pdf::loadView(self::VISTA, $datos)
                ->setPaper('a4', 'portrait')
                ->output(),
            'filename' => $filename,
        ];
    }

    /**
     * Lo que va a imprimirse, antes de componerlo, con sus guardas.
     *
     * Está separado del render porque dompdf comprime el flujo del PDF
     * (`/FlateDecode`): sobre los bytes no se puede comprobar qué salió y, lo
     * que más importa aquí, qué no salió. Las pruebas de contenido renderizan
     * esta misma vista con estos mismos datos.
     *
     * @return array{datos: array<string, mixed>, filename: string}
     */
    public function componer(int $solicitudId): array
    {
        $solicitud = SolicitudCertificacionMedica::with([
            'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula,puesto_id,unidad_administrativa_id',
            'servidor.puesto.cargo:id,nombre',
            'servidor.unidadAdministrativa:id,nombre',
            'postulante:id,nombres,apellidos,cedula',
            'fichaSaludOcupacional',
            'fichaSaludOcupacional.evaluador:id,servidor_id',
            'fichaSaludOcupacional.evaluador.servidor:id,nombre,apellido,cedula,codigo_medico',
            'fichaSaludOcupacional.puesto.cargo:id,nombre',
            'fichaSaludOcupacional.puesto.unidadAdministrativa:id,nombre',
        ])->findOrFail($solicitudId);

        $ficha = $solicitud->fichaSaludOcupacional;

        if ($solicitud->estado !== 'completada') {
            throw new ReglaNegocioException(
                'La evaluación no está completada: todavía no hay aptitud que certificar.'
            );
        }

        // Una solicitud puede cerrarse con dictamen y sin ficha —`ficha_femo_id`
        // admite nulos—, y entonces no hay acto médico firmado: el certificado
        // no tendría de quién llevar la firma ni el registro profesional.
        if (! $ficha) {
            throw new ReglaNegocioException(
                'La evaluación se cerró sin ficha FEMO, así que no hay un acto médico '
                .'firmado del que emitir el certificado.'
            );
        }

        $evaluador = $ficha->evaluador?->servidor;

        return [
            'datos' => [
                'logo' => public_path('images/logo-gadpe.png'),
                'paciente' => $this->paciente($solicitud),
                // Un candidato en proceso de selección todavía no es servidor,
                // y llamarlo así en un documento firmado sería falso.
                'tratamiento' => $solicitud->servidor
                    ? 'el/la servidor/a'
                    : 'el/la aspirante',
                'puesto' => $this->puesto($solicitud),
                'ficha' => $ficha,
                'tipoEvaluacion' => $ficha->tipo_ficha?->etiqueta()
                    ?? ucfirst(str_replace('_', ' ', (string) $solicitud->tipo_evento)),
                'rigeHasta' => $this->rigeHasta($ficha->fecha_evaluacion),
                'evaluador' => $evaluador,
                'emitidoEn' => Carbon::now(),
            ],
            'filename' => 'certificado-aptitud-'.$solicitud->cedula_paciente.'-'.$solicitud->id.'.pdf',
        ];
    }

    /**
     * Hasta cuándo rige la aptitud.
     *
     * Es el mismo plazo con el que el tablero decide si una evaluación venció,
     * y por eso sale de la misma constante: dos certificados que dijeran
     * fechas distintas de la misma evaluación serían peores que ninguno.
     */
    private function rigeHasta(mixed $fechaEvaluacion): ?Carbon
    {
        return $fechaEvaluacion
            ? Carbon::parse($fechaEvaluacion)
                ->addYears(CoberturaCertificacionService::ANIOS_ENTRE_EVALUACIONES)
            : null;
    }

    /** @return array{nombre: string, cedula: string} */
    private function paciente(SolicitudCertificacionMedica $solicitud): array
    {
        $s = $solicitud->servidor;

        if ($s) {
            return [
                'nombre' => trim(implode(' ', array_filter([
                    $s->nombre, $s->segundo_nombre, $s->apellido, $s->segundo_apellido,
                ]))),
                'cedula' => $s->cedula,
            ];
        }

        // Un candidato en proceso de selección: la identidad todavía no es un
        // Servidor, y los datos de la solicitud son los que hay.
        return [
            'nombre' => $solicitud->nombres_paciente,
            'cedula' => $solicitud->cedula_paciente,
        ];
    }

    /**
     * El puesto que se evaluó.
     *
     * Manda el de la ficha: es el que quien evaluó tenía delante. Si la ficha
     * no lo fijó, el del expediente, y si tampoco, el texto que la solicitud
     * arrastra desde el proceso de selección.
     *
     * @return array{cargo: ?string, unidad: ?string}
     */
    private function puesto(SolicitudCertificacionMedica $solicitud): array
    {
        $deLaFicha = $solicitud->fichaSaludOcupacional?->puesto;
        $delExpediente = $solicitud->servidor?->puesto;

        return [
            'cargo' => $deLaFicha?->cargo?->nombre
                ?? $delExpediente?->cargo?->nombre
                ?? $solicitud->puesto_solicitado,
            'unidad' => $deLaFicha?->unidadAdministrativa?->nombre
                ?? $solicitud->servidor?->unidadAdministrativa?->nombre,
        ];
    }
}
