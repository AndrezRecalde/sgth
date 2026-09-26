'use client'

import { useState } from 'react'
import { Alert, Box, Group, Select, Text } from '@mantine/core'
import { IconInfoCircle, IconUserOff } from '@tabler/icons-react'
import { EmptyState, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAusenciasTemporales } from '../hooks/useAusenciasTemporales'
import { getAusenciaColumns } from './ausenciasTemporales.columns'

const COBERTURA_OPTIONS = [
  { value: 'pendientes', label: 'Sin cubrir' },
  { value: 'cubiertas', label: 'Ya cubiertas' },
]

/**
 * Quién está temporalmente fuera y qué huecos quedan por cubrir.
 *
 * La ausencia no es un estado guardado: se deriva del período de la comisión o
 * la licencia, así que al vencer sale sola de este listado. El contrato del
 * titular sigue vigente todo el tiempo — no se libera la plaza, se autoriza un
 * apoyo temporal encima de ella.
 */
export function AusenciasTemporalesPanel() {
  const contained = useContainedInput()
  const [cobertura, setCobertura] = useState<string | null>(null)

  const { data: ausencias = [], isLoading } = useAusenciasTemporales(
    cobertura ? { cubiertas: cobertura === 'cubiertas' } : {},
  )

  const columns = getAusenciaColumns()

  if (!isLoading && ausencias.length === 0 && cobertura === null) {
    return (
      <EmptyState
        icon={IconUserOff}
        title="Nadie está temporalmente ausente"
        description="Aquí aparecen las comisiones de servicios y licencias sin remuneración vigentes hoy, para cubrir el hueco con personal de apoyo."
      />
    )
  }

  return (
    <Box>
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />} mb="md">
        El titular conserva su vínculo y su plaza mientras dura la ausencia. El
        reemplazo se contrata por Servicios Ocasionales o Profesionales, encima
        de esa plaza y sin pasar de la fecha en que el titular regresa.
      </Alert>

      <Group justify="space-between" mb="md">
        <Select
          label="Filtrar por cobertura"
          placeholder="Todas"
          data={COBERTURA_OPTIONS}
          value={cobertura}
          onChange={setCobertura}
          clearable
          {...contained}
          style={{ minWidth: 260 }}
        />
        <Text size="sm" c="dimmed">
          {ausencias.length} ausencia(s) vigente(s)
        </Text>
      </Group>

      <SgthTable
        records={ausencias}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />
    </Box>
  )
}
