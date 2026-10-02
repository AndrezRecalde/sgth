import { Button, Stack, Text } from '@mantine/core'
import { IconFileCertificate } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import {
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
  TIPO_EVENTO_OPTIONS,
  TONO_DICTAMEN,
  TONO_ESTADO_SOLICITUD,
  type SolicitudCertificacion,
} from '@/features/dispensario/services/solicitudCertificacionService'

const etiquetaTipo = (tipo: string) =>
  TIPO_EVENTO_OPTIONS.find((o) => o.value === tipo)?.label ?? tipo

interface Acciones {
  /** `null` cuando no hay ninguna descarga en curso. */
  descargandoId: number | null
  onDescargarCertificado: (solicitud: SolicitudCertificacion) => void
}

/*
| El historial de evaluaciones tal como lo ve Talento Humano.
|
| Lo que se descarga es el CERTIFICADO DE APTITUD, no la ficha. El PDF del FEMO
| es el formulario 028 del MSP completo —motivo de consulta, antecedentes,
| examen físico y diagnóstico CIE-10—, o sea historia clínica, y por el acuerdo
| con la UATH del 2026-09-26 no entra al expediente administrativo: su ruta
| pide `role:medico|admin-dispensario` y a Talento Humano le respondía 403
| disfrazado de «No se pudo generar el PDF».
|
| El certificado lleva solo lo que condiciona un puesto —aptitud,
| restricciones, vigencia y la firma del profesional con su registro—, que es
| justo lo que esta tabla ya enseña en pantalla.
*/
export const getSaludOcupacionalColumns = (
  { descargandoId, onDescargarCertificado }: Acciones,
): DataTableColumn<SolicitudCertificacion>[] => [
  {
    accessor: 'tipo_evento',
    title: 'Tipo de evaluación',
    render: (s) => <StatusBadge>{etiquetaTipo(s.tipo_evento)}</StatusBadge>,
  },
  {
    accessor: 'created_at',
    title: 'Fecha de solicitud',
    width: 130,
    render: (s) => (
      <Text size="sm">{formatFechaHora(s.created_at, { conHora: false })}</Text>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 140,
    render: (s) => (
      <StatusBadge tone={TONO_ESTADO_SOLICITUD[s.estado] ?? 'neutral'}>
        {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
      </StatusBadge>
    ),
  },
  {
    accessor: 'dictamen',
    title: 'Aptitud',
    // El dictamen iba como una insignia pequeña debajo del estado, dentro de
    // su columna: es el resultado de la evaluación, no un detalle del trámite.
    // Y las restricciones, que son lo que condiciona el puesto, no se veían.
    render: (s) => {
      if (!s.dictamen) return <Text size="sm" c="dimmed">—</Text>

      const restricciones = s.ficha_salud_ocupacional?.restricciones

      return (
        <Stack gap={4}>
          <StatusBadge tone={TONO_DICTAMEN[s.dictamen] ?? 'neutral'}>
            {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
          </StatusBadge>
          {restricciones && (
            <Text size="xs" c="dimmed" lineClamp={2}>{restricciones}</Text>
          )}
        </Stack>
      )
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 130,
    // Sin ficha no hay acto médico firmado y el API responde 422: la columna
    // no ofrece una descarga que no se puede emitir.
    render: (s) =>
      s.ficha_salud_ocupacional ? (
        <Button
          size="xs"
          variant="light"
          leftSection={<IconFileCertificate size={13} />}
          loading={descargandoId === s.id}
          disabled={descargandoId !== null && descargandoId !== s.id}
          onClick={() => onDescargarCertificado(s)}
        >
          Certificado
        </Button>
      ) : (
        <Text size="xs" c="dimmed">—</Text>
      ),
  },
]
