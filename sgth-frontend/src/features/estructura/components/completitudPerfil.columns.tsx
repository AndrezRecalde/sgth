import { Text } from '@mantine/core'
import { IconFileDescription } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import type { CompletitudPuesto, ParteDelPerfil } from '../services/perfilesPuestosService'

/*
| El tablero de completitud de los perfiles (P6, PR 15): cada puesto activo con
| lo que tiene de su perfil y lo que le falta para quedar listo para evaluar.
*/

/** La cifra, o «Falta» si no hay ninguna. */
function Parte({ valor }: { valor: number | boolean }) {
  if (!valor) return <StatusBadge tone="warning">Falta</StatusBadge>
  return <Text size="sm">{valor === true ? 'Sí' : valor}</Text>
}

const parte = (accessor: ParteDelPerfil, title: string): DataTableColumn<CompletitudPuesto> => ({
  accessor,
  title,
  width: 120,
  textAlign: 'center',
  render: (p) => <Parte valor={p[accessor]} />,
})

export const getCompletitudPerfilColumns = (
  { alAbrir }: { alAbrir: (p: CompletitudPuesto) => void },
): DataTableColumn<CompletitudPuesto>[] => [
  {
    accessor: 'cargo',
    title: 'Puesto',
    render: ({ id, cargo, unidad_administrativa, grupo_ocupacional, tipo }) => (
      <div>
        <Text size="sm" fw={500}>{cargo?.nombre ?? `Puesto ${id}`}</Text>
        <Text size="xs" c="dimmed">
          {[unidad_administrativa?.nombre, grupo_ocupacional?.grado_codigo ? `grado ${grupo_ocupacional.grado_codigo}` : null]
            .filter(Boolean).join(' · ') || '—'}
        </Text>
        {tipo === 'contrato' && <Text size="xs" c="dimmed">De contrato</Text>}
      </div>
    ),
  },
  {
    accessor: 'ocupado',
    title: 'Ocupación',
    width: 110,
    render: ({ ocupado }) => <StatusBadge>{ocupado ? 'Ocupado' : 'Vacante'}</StatusBadge>,
  },
  parte('mision', 'Misión'),
  parte('requisitos', 'Instrucción'),
  parte('actividades', 'Actividades'),
  parte('competencias', 'Competencias'),
  {
    accessor: 'completo',
    title: 'Perfil',
    width: 120,
    render: ({ completo }) => completo
      ? <StatusBadge tone="success">Completo</StatusBadge>
      : <StatusBadge tone="warning">Incompleto</StatusBadge>,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (p) => (
      <TableActions actions={[{
        label: p.completo ? 'Ver el perfil' : 'Completar el perfil',
        icon: <IconFileDescription size={14} />,
        onClick: () => alAbrir(p),
      }]} />
    ),
  },
]
