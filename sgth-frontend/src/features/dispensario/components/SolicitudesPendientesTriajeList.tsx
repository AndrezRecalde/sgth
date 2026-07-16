'use client'

import { Text, Badge, Button } from '@mantine/core'
import { IconHeartbeat } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { EmptyState } from '@/components/ui/EmptyState'
import { useSolicitudesPendientesTriaje } from '../hooks/useSolicitudSignosVitales'
import { TIPO_EVENTO_OPTIONS } from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  onSeleccionar: (solicitud: SolicitudCertificacion) => void
}

export function SolicitudesPendientesTriajeList({ onSeleccionar }: Props) {
  const { data: solicitudes = [], isLoading } = useSolicitudesPendientesTriaje()

  const getLabelTipo = (v: string) =>
    TIPO_EVENTO_OPTIONS.find(o => o.value === v)?.label ?? v

  const columns: DataTableColumn<SolicitudCertificacion>[] = [
    {
      accessor: 'nombres_paciente',
      title:    'Servidor / Aspirante',
      render: (s) => (
        <div>
          <Text size="sm" fw={500}>{s.nombres_paciente}</Text>
          <Text size="xs" c="dimmed" ff="monospace">{s.cedula_paciente}</Text>
        </div>
      ),
    },
    {
      accessor: 'tipo_evento',
      title:    'Tipo de evaluación',
      width:    170,
      render: (s) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelTipo(s.tipo_evento)}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    170,
      render: (s) => (
        <Button
          size="xs"
          variant="light"
          color="emerald"
          leftSection={<IconHeartbeat size={13} />}
          onClick={() => onSeleccionar(s)}
        >
          Tomar signos vitales
        </Button>
      ),
    },
  ]

  if (solicitudes.length === 0 && !isLoading) {
    return (
      <EmptyState
        icon={IconHeartbeat}
        title="Sin pendientes"
        description="No hay solicitudes SSO pendientes de signos vitales."
      />
    )
  }

  return (
    <SgthTable
      records={solicitudes}
      columns={columns}
      fetching={isLoading}
      minHeight={200}
      noRecordsText="Sin solicitudes pendientes de signos vitales"
    />
  )
}
