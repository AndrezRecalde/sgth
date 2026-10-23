import { Group, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { ETIQUETA_ESPECIALIDAD } from '../../services/kpisService'
import type { CatalogoExamen, PerfilExamen } from '../../services/examenService'

type Acciones<T> = (registro: T) => TableAction[]

const accionesCol = <T,>(accionesDe: Acciones<T>): DataTableColumn<T> => ({
  accessor: 'acciones',
  title: '',
  width: 50,
  render: (r) => <TableActions actions={accionesDe(r)} />,
})

const estado = <T extends { activo: boolean }>(): DataTableColumn<T> => ({
  accessor: 'activo',
  title: 'Estado',
  width: 110,
  render: (r) => <StatusBadge tone={r.activo ? 'success' : 'neutral'}>{r.activo ? 'Activo' : 'Inactivo'}</StatusBadge>,
})

export const columnasCatalogoExamenes = (
  etiquetaArea: (area: string) => string,
  accionesDe: Acciones<CatalogoExamen>,
): DataTableColumn<CatalogoExamen>[] => [
  {
    accessor: 'nombre',
    title: 'Examen',
    render: (e) => (
      <Stack gap={0}>
        <Group gap={6} wrap="nowrap">
          <Text size="sm" fw={500}>{e.nombre}</Text>
          {e.sensible && <StatusBadge size="xs" tone="warning">Sensible</StatusBadge>}
        </Group>
        {e.indicaciones && <Text size="xs" c="dimmed" lineClamp={1}>{e.indicaciones}</Text>}
      </Stack>
    ),
  },
  { accessor: 'area', title: 'Área', width: 190, render: (e) => etiquetaArea(e.area) },
  {
    accessor: 'etiqueta_nota',
    title: 'Dato que pide',
    width: 190,
    render: (e) => e.etiqueta_nota ?? <Text size="sm" c="dimmed">—</Text>,
  },
  estado<CatalogoExamen>(),
  accionesCol(accionesDe),
]

export const columnasPerfilesExamen = (accionesDe: Acciones<PerfilExamen>): DataTableColumn<PerfilExamen>[] => [
  {
    accessor: 'nombre',
    title: 'Perfil',
    render: (p) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{p.nombre}</Text>
        {p.descripcion && <Text size="xs" c="dimmed" lineClamp={1}>{p.descripcion}</Text>}
      </Stack>
    ),
  },
  {
    accessor: 'especialidad',
    title: 'Especialidad',
    width: 160,
    render: (p) => <StatusBadge>{ETIQUETA_ESPECIALIDAD[p.especialidad]}</StatusBadge>,
  },
  {
    accessor: 'examenes',
    title: 'Exámenes',
    width: 320,
    render: (p) => (
      <Text size="xs" lineClamp={2} title={p.examenes.map((e) => e.nombre).join(', ')}>
        <Text span fw={600}>{p.examenes.length}</Text>: {p.examenes.map((e) => e.nombre).join(', ')}
      </Text>
    ),
  },
  {
    accessor: 'ocupacional',
    title: 'Uso',
    width: 130,
    render: (p) => (p.ocupacional ? <StatusBadge>Ocupacional</StatusBadge> : <Text size="sm">Consulta</Text>),
  },
  estado<PerfilExamen>(),
  accionesCol(accionesDe),
]
