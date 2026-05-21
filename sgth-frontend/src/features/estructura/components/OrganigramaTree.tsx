'use client'

import { useState } from 'react'
import { Stack, Text, Skeleton, Box } from '@mantine/core'
import type { UnidadAdministrativa } from '@/types/api'
import { OrganigramaNode } from './OrganigramaNode'

interface OrganigramaTreeProps {
  unidades: UnidadAdministrativa[]
  nivel?: number
  isLoading?: boolean
  error?: Error | null
}

export function OrganigramaTree({ unidades, nivel = 0, isLoading, error }: OrganigramaTreeProps) {
  const [expandedIds, setExpandedIds] = useState<Set<string>>(new Set())

  if (error) {
    return <Text c="red">Ocurrió un error al cargar el organigrama.</Text>
  }

  if (isLoading) {
    return (
      <Stack>
        <Skeleton height={60} radius="md" />
        <Skeleton height={60} radius="md" ml="xl" />
        <Skeleton height={60} radius="md" ml="xl" />
      </Stack>
    )
  }

  const handleToggle = (id: string) => {
    setExpandedIds(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  return (
    <Stack gap="xs" style={{ paddingLeft: nivel > 0 ? '24px' : '0' }}>
      {unidades.map(unidad => {
        const isExpanded = expandedIds.has(unidad.id)
        const hijos = (unidad as any).hijas || (unidad as any).unidades_hijas || (unidad as any).children || []

        return (
          <Box key={unidad.id}>
            <OrganigramaNode
              unidad={unidad}
              nivel={nivel}
              expanded={isExpanded}
              onToggle={handleToggle}
            />
            {isExpanded && hijos.length > 0 && (
              <Box mt="xs">
                <OrganigramaTree unidades={hijos} nivel={nivel + 1} />
              </Box>
            )}
          </Box>
        )
      })}
    </Stack>
  )
}
