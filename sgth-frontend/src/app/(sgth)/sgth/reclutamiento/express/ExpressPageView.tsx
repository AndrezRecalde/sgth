'use client'

import { Box } from '@mantine/core'
import { IconBolt } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { ReclutamientoExpressView } from '@/features/seleccion/components/ReclutamientoExpressView'

export function ExpressPageView() {
  return (
    <Box>
      <PageHeader
        title="Reclutamiento Express"
        subtitle="Provisionales, ocasionales, servicios profesionales y Código del Trabajo"
        icon={<IconBolt size={28} />}
      />

      <ReclutamientoExpressView />
    </Box>
  )
}
