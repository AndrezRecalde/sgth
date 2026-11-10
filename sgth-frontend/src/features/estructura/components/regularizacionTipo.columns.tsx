import { Stack, Text } from '@mantine/core'
import { IconArrowsExchange, IconFileCertificate } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import type { ContratoPorRegularizar, PuestoPorRegularizar } from '../services/regularizacionTipoService'

/*
| Las dos tablas de «Por revisar» (P9, PR 4). En las dos, `hidden` es por
| permiso y `disabled` por el estado de la fila (regla 06).
*/

/** Los puestos de plantilla que solo sostienen contratos que no ocupan plaza. */
export const getPuestosPorRegularizarColumns = (
  { alPasarAContrato, puedeGestionar }: {
    alPasarAContrato: (p: PuestoPorRegularizar) => void
    puedeGestionar: boolean
  },
): DataTableColumn<PuestoPorRegularizar>[] => [
  {
    accessor: 'cargo',
    title: 'Puesto',
    render: ({ cargo, unidad }) => (
      <div>
        <Text size="sm" fw={500}>{cargo ?? '—'}</Text>
        <Text size="xs" c="dimmed">{unidad ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'ocupantes',
    title: 'Lo ocupan',
    render: ({ ocupantes }) => (
      <Stack gap={2}>
        {ocupantes.map((o) => (
          <Text key={o.contrato_id} size="sm" lineClamp={1}>
            {o.nombre} <Text span size="xs" c="dimmed">· {o.modalidad}</Text>
          </Text>
        ))}
      </Stack>
    ),
  },
  {
    accessor: 'plazas',
    title: 'Plazas',
    width: 170,
    // Las que la Plantilla cuenta hoy como vacantes, sin serlo.
    render: ({ plazas }) => (
      <Text size="sm">{plazas ?? 0} contadas como vacantes</Text>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (p) => (
      <TableActions actions={[{
        label: 'Pasar a contrato',
        icon: <IconFileCertificate size={14} />,
        hidden: !puedeGestionar,
        onClick: () => alPasarAContrato(p),
      }]} />
    ),
  },
]

/** El ocasional y el profesional en un puesto de plantilla, sin reemplazar a nadie. */
export const getContratosPorRegularizarColumns = (
  { alReubicar, puedeReubicar }: {
    alReubicar: (c: ContratoPorRegularizar) => void
    /** `gestionar-expediente`: reubicar cambia el vínculo de un servidor. */
    puedeReubicar: boolean
  },
): DataTableColumn<ContratoPorRegularizar>[] => [
  {
    accessor: 'servidor',
    title: 'Servidor',
    render: ({ servidor, modalidad }) => (
      <div>
        <Text size="sm" fw={500}>{servidor ?? '—'}</Text>
        {/* La modalidad es una categoría: va neutra (regla 06). */}
        <StatusBadge size="xs" mt={2}>{modalidad}</StatusBadge>
      </div>
    ),
  },
  {
    accessor: 'puesto',
    title: 'Puesto de plantilla',
    render: ({ puesto }) => (
      <div>
        <Text size="sm">{puesto?.cargo ?? '—'}</Text>
        <Text size="xs" c="dimmed">{puesto?.unidad ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'destinos',
    title: 'Puestos de contrato de su unidad',
    render: ({ destinos }) => destinos.length
      ? <Text size="sm" lineClamp={2}>{destinos.map((d) => d.cargo ?? `Puesto ${d.id}`).join(', ')}</Text>
      : <Text size="sm" c="dimmed">Ninguno: créelo con «Nuevo puesto», de contrato</Text>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (c) => (
      <TableActions actions={[{
        label: 'Reubicar en un puesto de contrato',
        icon: <IconArrowsExchange size={14} />,
        hidden: !puedeReubicar,
        disabled: c.destinos.length === 0,
        onClick: () => alReubicar(c),
      }]} />
    ),
  },
]
