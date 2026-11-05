import { Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { dinero } from '@/lib/dinero'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { GrupoOcupacional } from '@/types/api'
import { etiquetaComplejidad } from '../constants/clasificacionPuesto'

/*
| La escala de grupos ocupacionales. El grado es un código (`outline`, regla
| 06) y el régimen y la complejidad, categorías neutras. La RMU iba en verde,
| que es el color del éxito: un sueldo no es un logro de nadie.
*/
export const columnasGrupoOcupacional: DataTableColumn<GrupoOcupacional>[] = [
  {
    accessor: 'grado_codigo',
    title: 'Grado',
    // Cabe «NJS-5-ASESOR»: con 100 la etiqueta se montaba sobre el grupo.
    width: 140,
    render: ({ grado_codigo }) => <StatusBadge variant="outline">{grado_codigo}</StatusBadge>,
  },
  {
    accessor: 'grupo',
    title: 'Grupo ocupacional',
    render: ({ grupo, denominacion_generica }) => (
      <div>
        <Text size="sm" fw={500}>{grupo}</Text>
        {denominacion_generica && <Text size="xs" c="dimmed">{denominacion_generica}</Text>}
      </div>
    ),
  },
  {
    accessor: 'rmu',
    title: 'RMU',
    width: 120,
    textAlign: 'right',
    render: ({ rmu }) => <Text size="sm" fw={600}>{dinero(rmu)}</Text>,
  },
  {
    accessor: 'regimen',
    title: 'Régimen',
    width: 170,
    render: ({ regimen }) => <StatusBadge>{REGIMEN_LABELS[regimen] ?? regimen}</StatusBadge>,
  },
  {
    accessor: 'nivel_complejidad',
    title: 'Complejidad',
    width: 120,
    render: ({ nivel_complejidad }) => nivel_complejidad
      ? <StatusBadge>{etiquetaComplejidad(nivel_complejidad)}</StatusBadge>
      : <Text size="sm" c="dimmed">-</Text>,
  },
]
