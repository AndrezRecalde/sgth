import { Group, Text } from '@mantine/core'
import { IconArrowsExchange, IconEdit, IconScale, IconTrash, IconList } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { PuestoConRelaciones } from '@/types/api'
import { REGIMEN_LABELS } from '@/lib/regimen'
import { dinero } from '@/lib/dinero'

type Acciones = {
  alEditar:     (puesto: PuestoConRelaciones) => void
  alEliminar:   (puesto: PuestoConRelaciones) => void
  alVerPerfil:  (puesto: PuestoConRelaciones) => void
  /** Pasarlo ocupado a otra unidad, con el acto de cada ocupante (fase 7.1a). */
  alTraspasar:  (puesto: PuestoConRelaciones) => void
  /** Cambiarle ocupado el grado o la denominación (fase 7.1b). */
  alRevisar:    (puesto: PuestoConRelaciones) => void
  /** `gestionar-puestos`. Sin él, el perfil del puesto solo se consulta. */
  puedeGestionar: boolean
  /** `preparar-accion-personal`: el traspaso y la revisión son lotes de acciones. */
  puedeTraspasar: boolean
}

/** «Vacante», o quiénes lo ocupan: los dos primeros y cuántos más. */
function ocupantesDe(puesto: PuestoConRelaciones): string {
  const nombres = (puesto.ocupantes ?? []).map((o) => o.nombre).filter(Boolean) as string[]

  if (!nombres.length) return puesto.ocupado ? 'Reservado o por regir' : 'Vacante'

  return nombres.length > 2 ? `${nombres.slice(0, 2).join(', ')} y ${nombres.length - 2} más` : nombres.join(', ')
}

/*
| En el menú de cada fila, `hidden` es por permiso y `disabled` por el estado
| del puesto (regla 06): quien puede traspasar ve la acción también en un
| puesto vacante, apagada, en vez de preguntarse por qué no aparece.
*/
export const getPuestoColumns = (
  { alEditar, alEliminar, alVerPerfil, alTraspasar, alRevisar, puedeGestionar, puedeTraspasar }: Acciones,
): DataTableColumn<PuestoConRelaciones>[] => [
  {
    accessor: 'cargo',
    title: 'Cargo',
    render: ({ cargo, es_jefe, activo }) => (
      <div>
        <Text size="sm" fw={500} c={activo === false ? 'dimmed' : undefined}>{cargo?.nombre ?? '-'}</Text>
        {(es_jefe || activo === false) && (
          <Group gap={4} mt={2}>
            {/* «Jefe» es una categoría: va neutra (regla 06). */}
            {es_jefe && <StatusBadge size="xs">Jefe</StatusBadge>}
            {/* Desactivado en vez de eliminado: se veía igual que uno vigente. */}
            {activo === false && <StatusBadge size="xs" variant="dot">Inactivo</StatusBadge>}
          </Group>
        )}
      </div>
    ),
  },
  {
    accessor: 'unidad_administrativa',
    title: 'Unidad',
    render: ({ unidad_administrativa }) => (
      <Text size="sm">{unidad_administrativa?.nombre ?? '-'}</Text>
    ),
  },
  {
    accessor: 'regimen_laboral',
    title: 'Régimen',
    width: 170,
    render: ({ regimen_laboral }) => regimen_laboral ? (
      <StatusBadge>
        {REGIMEN_LABELS[regimen_laboral] ?? regimen_laboral}
      </StatusBadge>
    ) : <Text size="sm" c="dimmed">-</Text>,
  },
  {
    accessor: 'ocupantes',
    title: 'Ocupantes',
    render: (puesto) => (
      <Text size="sm" c={puesto.ocupado ? undefined : 'dimmed'} lineClamp={2}>{ocupantesDe(puesto)}</Text>
    ),
  },
  {
    accessor: 'plazas',
    title: 'Plazas',
    width: 90,
    textAlign: 'center',
    render: ({ plazas }) => <Text size="sm">{plazas ?? 1}</Text>,
  },
  {
    accessor: 'rmu',
    title: 'RMU',
    width: 110,
    textAlign: 'right',
    render: ({ rmu }) => <Text size="sm">{dinero(rmu)}</Text>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (puesto) => (
      <TableActions actions={[
        {
          label: 'Perfil del puesto',
          icon: <IconList size={14} />,
          onClick: () => alVerPerfil(puesto),
        },
        {
          label: 'Editar puesto',
          icon: <IconEdit size={14} />,
          hidden: !puedeGestionar,
          onClick: () => alEditar(puesto),
        },
        {
          // Ocupado, cambia de unidad con el acto de cada ocupante (fase 7.1a).
          // La autoridad electa y la jefatura no se traspasan: la jefatura va
          // con su unidad. Vacante, la unidad se cambia al editarlo.
          label: 'Traspasar a otra unidad',
          icon: <IconArrowsExchange size={14} />,
          hidden: !puedeTraspasar,
          disabled: !puesto.ocupado || puesto.admite_traspaso === false,
          onClick: () => alTraspasar(puesto),
        },
        {
          // Ocupado, el grado y la denominación cambian con el acto de cada
          // ocupante (fase 7.1b). La autoridad y el obrero, no.
          label: 'Revisar la clasificación',
          icon: <IconScale size={14} />,
          hidden: !puedeTraspasar,
          disabled: !puesto.ocupado || puesto.admite_revision === false,
          onClick: () => alRevisar(puesto),
        },
        {
          // Ni ocupado ni con historia: si no, se desactiva.
          label: 'Eliminar puesto',
          icon: <IconTrash size={14} />,
          color: 'red',
          hidden: !puedeGestionar,
          disabled: puesto.puede_eliminar === false,
          onClick: () => alEliminar(puesto),
        },
      ]} />
    ),
  },
]
