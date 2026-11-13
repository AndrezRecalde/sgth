import { Stack, Text } from '@mantine/core'
import { IconFileDescription, IconFolder } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { estadoInstruccion } from '../constants/brechasDePerfil'
import type { BrechaDePerfil } from '../services/brechasDePerfilService'

/*
| Las brechas de perfil (P7, PR 17): cada servidor con lo que tiene frente a lo
| que pide el perfil de su puesto.
*/
export const getBrechasDePerfilColumns = (
  { alVerDetalle, alVerExpediente }: { alVerDetalle: (b: BrechaDePerfil) => void; alVerExpediente: (b: BrechaDePerfil) => void },
): DataTableColumn<BrechaDePerfil>[] => [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: ({ servidor }) => (
      <div>
        <Text size="sm" fw={500}>{servidor.nombre}</Text>
        <Text size="xs" c="dimmed">{servidor.cedula}</Text>
      </div>
    ),
  },
  {
    accessor: 'puesto',
    title: 'Puesto',
    render: ({ puesto }) => (
      <div>
        <Text size="sm">{puesto.cargo ?? `Puesto ${puesto.id}`}</Text>
        <Text size="xs" c="dimmed">{[puesto.unidad, puesto.grado ? `grado ${puesto.grado}` : null].filter(Boolean).join(' · ') || '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'instruccion',
    title: 'Instrucción',
    width: 280,
    render: ({ instruccion }) => {
      const { label, tone } = estadoInstruccion(instruccion.estado)
      return (
        <Stack gap={2}>
          <StatusBadge tone={tone}>{label}</StatusBadge>
          {instruccion.estado !== 'sin_requisitos' && (
            <>
              <Text size="xs" c="dimmed">Tiene: {instruccion.tiene ?? 'ningún título validado'}</Text>
              <Text size="xs" c="dimmed" lineClamp={2}>Pide: {instruccion.pide.join(' o ')}</Text>
            </>
          )}
          {instruccion.por_validar > 0 && (
            <Text size="xs" c="amber">
              {instruccion.por_validar === 1 ? 'Un título por validar' : `${instruccion.por_validar} títulos por validar`}
            </Text>
          )}
        </Stack>
      )
    },
  },
  {
    accessor: 'capacitacion',
    title: 'Capacitación',
    width: 260,
    render: ({ capacitacion }) => {
      if (!capacitacion.tematicas) return <Text size="xs" c="dimmed">El perfil no pide temáticas.</Text>
      const { relacionadas, registradas } = capacitacion
      return (
        <Stack gap={2}>
          <Text size="sm">
            {relacionadas.length === 0
              ? 'Ningún curso relacionado'
              : `${relacionadas.length} ${relacionadas.length === 1 ? 'curso relacionado' : 'cursos relacionados'}`}
          </Text>
          <Text size="xs" c="dimmed">
            De {registradas} {registradas === 1 ? 'curso validado' : 'cursos validados'}
          </Text>
        </Stack>
      )
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (b) => (
      <TableActions actions={[
        { label: 'Ver el detalle', icon: <IconFileDescription size={14} />, onClick: () => alVerDetalle(b) },
        { label: 'Abrir el expediente', icon: <IconFolder size={14} />, onClick: () => alVerExpediente(b) },
      ]} />
    ),
  },
]
