import { Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { AutoridadEntrante } from '../services/autoridadElectaService'

/** A quién sucede, y si su período termina a tiempo (2026-10-21). */
function SucedeA({ e }: { e: AutoridadEntrante }) {
  if (!e.sucede_a) return <Text size="sm" c="dimmed">Nadie: el puesto está sin autoridad</Text>

  // Las fechas llegan en ISO: se comparan como texto.
  const aTiempo = !!e.desde && e.sucede_a.hasta <= e.desde

  return (
    <div>
      <Text size="sm">{e.sucede_a.nombre}</Text>
      <Text size="xs" c={aTiempo ? 'dimmed' : 'red'}>
        Su período termina el {formatFecha(e.sucede_a.hasta)}
      </Text>
    </div>
  )
}

/** Las autoridades que entran: su período por empezar y a quién suceden (2026-10-21). */
export const getAutoridadEntranteColumns = (): DataTableColumn<AutoridadEntrante>[] => [
  {
    accessor: 'servidor',
    title: 'Autoridad',
    render: (e) => (
      <div>
        <Text size="sm" fw={500}>{e.servidor.nombre || '—'}</Text>
        <Text size="xs" c="dimmed">{e.dignidad ?? '—'}</Text>
      </div>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período',
    width: 210,
    render: (e) => <Text size="sm">{formatFecha(e.desde)} – {formatFecha(e.hasta)}</Text>,
  },
  {
    accessor: 'inicio',
    title: 'Inicio',
    render: (e) => (
      <div>
        {e.inicio.codigo_registro ? (
          <Text size="sm" ff="monospace">{e.inicio.codigo_registro}</Text>
        ) : (
          <StatusBadge tone="warning">En trámite</StatusBadge>
        )}
        {e.credencial && <Text size="xs" c="dimmed">Credencial {e.credencial}</Text>}
        {e.designacion && <Text size="xs" c="dimmed">Designado por el Consejo: {e.designacion}</Text>}
      </div>
    ),
  },
  {
    accessor: 'sucede_a',
    title: 'Sucede a',
    render: (e) => <SucedeA e={e} />,
  },
]
