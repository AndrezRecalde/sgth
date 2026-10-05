import { Stack, Text } from '@mantine/core'
import { IconEdit, IconStar, IconUsers } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { ESTADO_POSTULANTE_OPTIONS, TONO_POSTULANTE, type Postulante } from '../services/convocatoriaService'
import { etiquetaDe } from '../constants/postulante'
import { nombreCandidato } from './RankingCandidatoCard'

interface Acciones {
  califica:    boolean
  onCalificar: (p: Postulante) => void
  onVerPerfil: (p: Postulante) => void
}

/** Los candidatos de una convocatoria formal, en la pestaña «Candidatos». */
export function columnasPostulantes({ califica, onCalificar, onVerPerfil }: Acciones): DataTableColumn<Postulante>[] {
  return [
    {
      accessor: 'cedula',
      title: 'Cédula',
      width: 120,
      render: (p) => <Text size="sm" ff="monospace">{p.cedula}</Text>,
    },
    {
      accessor: 'nombres',
      title: 'Candidato',
      render: (p) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{nombreCandidato(p)}</Text>
          <Text size="xs" c="dimmed">{p.correo}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'telefono',
      title: 'Teléfono',
      width: 120,
      render: (p) => <Text size="sm">{p.telefono || '—'}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 160,
      render: (p) => (
        <StatusBadge tone={TONO_POSTULANTE[p.estado] ?? 'neutral'}>
          {etiquetaDe(ESTADO_POSTULANTE_OPTIONS, p.estado)}
        </StatusBadge>
      ),
    },
    {
      accessor: 'evaluacion',
      title: 'Puntaje',
      width: 100,
      render: (p) => (
        <Text size="sm" ta="center" fw={500}>
          {p.evaluacion ? `${Number(p.evaluacion.puntaje_total).toFixed(2)}/100` : '—'}
        </Text>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (p) => (
        <TableActions actions={[
          {
            hidden: !califica,
            label: p.evaluacion ? 'Editar calificación' : 'Calificar',
            icon: p.evaluacion ? <IconEdit size={14} /> : <IconStar size={14} />,
            onClick: () => onCalificar(p),
          },
          {
            label: 'Ver perfil',
            icon: <IconUsers size={14} />,
            onClick: () => onVerPerfil(p),
          },
        ]} />
      ),
    },
  ]
}
