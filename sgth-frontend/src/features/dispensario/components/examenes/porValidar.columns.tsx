import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { pacienteDeOrden } from '../../services/bandejaExamenesService'
import { diasDesde, textoEspera } from '../../utils/espera'
import type { EnvioPorValidar } from '../../services/envioResultadoService'

export const columnasPorValidar = (
  accionesDe: (e: EnvioPorValidar) => TableAction[],
): DataTableColumn<EnvioPorValidar>[] => [
  {
    accessor: 'paciente',
    title: 'Paciente',
    width: 200,
    render: (e) => {
      const { nombre, cedula } = pacienteDeOrden(e.orden_examen)
      return (
        <Stack gap={0}>
          <Text size="sm">{nombre}</Text>
          {cedula && <Text size="xs" c="dimmed">{cedula}</Text>}
        </Stack>
      )
    },
  },
  {
    accessor: 'orden_examen',
    title: 'Orden y exámenes que dice traer',
    render: (e) => (
      <Stack gap={0}>
        <Text size="sm" ff="monospace">{e.orden_examen.folio}</Text>
        <Text size="xs" c="dimmed" lineClamp={2}>
          {e.orden_examen.items.filter((i) => e.items.includes(i.id)).map((i) => i.examen?.nombre).filter(Boolean).join(', ')}
        </Text>
      </Stack>
    ),
  },
  {
    accessor: 'solicitante',
    title: 'Pidió la orden',
    width: 170,
    render: (e) => {
      const s = e.orden_examen.solicitante?.servidor
      return s ? `${s.nombre} ${s.apellido}` : '—'
    },
  },
  {
    accessor: 'fecha_resultado',
    title: 'Resultado del',
    width: 120,
    render: (e) => formatFechaMes(e.fecha_resultado),
  },
  {
    accessor: 'espera',
    title: 'Enviado',
    width: 110,
    render: (e) => {
      const dias = diasDesde(e.created_at)
      return dias > 7
        ? <StatusBadge tone="warning">{textoEspera(dias)}</StatusBadge>
        : <Text size="sm">{textoEspera(dias)}</Text>
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (e) => <TableActions actions={accionesDe(e)} />,
  },
]
