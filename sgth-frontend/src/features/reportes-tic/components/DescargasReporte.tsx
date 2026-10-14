'use client'

import { Button, Group } from '@mantine/core'
import { IconFileSpreadsheet, IconFileTypePdf } from '@tabler/icons-react'
import { useDescargaReporteTic } from '../hooks/useReportesTic'
import type { NombreReporteTic, ParametrosReporte } from '../services/reportesTicService'

interface Props {
  reporte: NombreReporteTic
  parametros: ParametrosReporte
  disabled?: boolean
}

/** Excel y PDF con los mismos filtros que se ven en pantalla. */
export function DescargasReporte({ reporte, parametros, disabled }: Props) {
  const descarga = useDescargaReporteTic()
  const pendiente = descarga.isPending ? descarga.variables?.formato : null

  return (
    <Group gap="xs">
      <Button variant="light" leftSection={<IconFileSpreadsheet size={16} />} disabled={disabled || !!pendiente}
        loading={pendiente === 'excel'} onClick={() => descarga.mutate({ reporte, parametros, formato: 'excel' })}>
        Excel
      </Button>
      <Button variant="light" leftSection={<IconFileTypePdf size={16} />} disabled={disabled || !!pendiente}
        loading={pendiente === 'pdf'} onClick={() => descarga.mutate({ reporte, parametros, formato: 'pdf' })}>
        PDF
      </Button>
    </Group>
  )
}
