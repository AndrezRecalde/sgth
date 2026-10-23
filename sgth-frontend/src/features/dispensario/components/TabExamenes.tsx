'use client'

import { Box, Divider, Stack } from '@mantine/core'
import { OrdenesExamenSection } from './examenes/OrdenesExamenSection'
import { TabResultados } from './TabResultados'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  consulta:          ConsultaMedica
  historiaClinicaId: number
}

/**
 * Lo que se pide y lo que vuelve, en la misma pestaña: arriba las órdenes de
 * esta consulta; debajo, los resultados que ya llegaron.
 */
export function TabExamenes({ consulta, historiaClinicaId }: Props) {
  return (
    <Stack gap={0}>
      <Box p="md">
        <OrdenesExamenSection consulta={consulta} historiaClinicaId={historiaClinicaId} />
      </Box>
      <Divider mx="md" />
      <TabResultados consulta={consulta} historiaClinicaId={historiaClinicaId} />
    </Stack>
  )
}
