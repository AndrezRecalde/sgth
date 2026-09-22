'use client'

import { Text, Button } from '@mantine/core'
import { IconHeartbeat } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { TIPO_EVENTO_OPTIONS } from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'

interface ColumnActions {
  onSeleccionar: (solicitud: SolicitudCertificacion) => void
}

function etiquetaTipo(valor: string): string {
  return TIPO_EVENTO_OPTIONS.find(o => o.value === valor)?.label ?? valor
}

export function getSolicitudesTriajeColumns(
  actions: ColumnActions
): DataTableColumn<SolicitudCertificacion>[] {
  return [
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
        <StatusBadge>{etiquetaTipo(s.tipo_evento)}</StatusBadge>
      ),
    },
    {
      // Una sola acción con nombre, no un menú: es lo único que se hace desde
      // esta lista, y esconderla tras los tres puntos añadiría un clic al
      // trabajo que la pantalla existe para hacer.
      accessor: 'acciones',
      title:    '',
      width:    170,
      render: (s) => (
        <Button
          size="xs"
          variant="light"
          leftSection={<IconHeartbeat size={14} />}
          onClick={() => actions.onSeleccionar(s)}
        >
          Tomar signos vitales
        </Button>
      ),
    },
  ]
}
