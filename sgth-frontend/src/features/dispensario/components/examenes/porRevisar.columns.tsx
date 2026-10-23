import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { pacienteDeOrden, type ResultadoPorRevisar } from '../../services/bandejaExamenesService'
import { TIPO_RESULTADO_OPTIONS, type PersonaResultado } from '../../services/resultadoMedicoService'
import { diasDesde, textoEspera } from '../../utils/espera'

const nombre = (p?: PersonaResultado | null) => (p?.servidor ? `${p.servidor.nombre} ${p.servidor.apellido}` : '—')

export const columnasPorRevisar = (
  accionesDe: (r: ResultadoPorRevisar) => TableAction[],
): DataTableColumn<ResultadoPorRevisar>[] => [
  {
    accessor: 'descripcion',
    title: 'Resultado',
    render: (r) => (
      <Stack gap={0}>
        <Text size="sm">{r.descripcion}</Text>
        <Text size="xs" c="dimmed">
          {TIPO_RESULTADO_OPTIONS.find((o) => o.value === r.tipo)?.label ?? r.tipo} · {formatFechaMes(r.fecha_resultado)}
        </Text>
      </Stack>
    ),
  },
  {
    accessor: 'paciente',
    title: 'Paciente',
    width: 190,
    render: (r) => {
      const { nombre: paciente, cedula } = pacienteDeOrden(r)
      return (
        <Stack gap={0}>
          <Text size="sm">{paciente}</Text>
          {cedula && <Text size="xs" c="dimmed">{cedula}</Text>}
        </Stack>
      )
    },
  },
  {
    accessor: 'orden_examen',
    title: 'Orden',
    width: 200,
    render: (r) => r.orden_examen ? (
      <Stack gap={0}>
        <Text size="sm" ff="monospace">{r.orden_examen.folio}</Text>
        <Text size="xs" c="dimmed" lineClamp={1}>
          {r.items_orden?.map((i) => i.examen?.nombre).filter(Boolean).join(', ')}
        </Text>
      </Stack>
    ) : <Text size="sm" c="dimmed">Sin orden</Text>,
  },
  {
    accessor: 'responde',
    title: 'Responde',
    width: 170,
    // Quien pidió la orden o, sin orden, quien atendió la consulta (D3).
    render: (r) => nombre(r.orden_examen?.solicitante ?? r.consulta_medica?.medico),
  },
  {
    accessor: 'espera',
    title: 'Subido',
    width: 110,
    render: (r) => {
      const dias = diasDesde(r.created_at)
      return dias > 7
        ? <StatusBadge tone="warning">{textoEspera(dias)}</StatusBadge>
        : <Text size="sm">{textoEspera(dias)}</Text>
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (r) => <TableActions actions={accionesDe(r)} />,
  },
]
