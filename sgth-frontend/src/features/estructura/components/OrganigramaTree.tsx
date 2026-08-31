'use client'

import { useState } from 'react'
import { Stack, Text, Skeleton, Box } from '@mantine/core'
import type { UnidadConRelaciones } from '@/types/api'
import { OrganigramaNode } from './OrganigramaNode'

interface OrganigramaTreeProps {
  unidades: UnidadConRelaciones[]
  nivel?: number
  isLoading?: boolean
  error?: Error | null
}

export function OrganigramaTree({
  unidades,
  nivel = 0,
  isLoading,
  error,
}: OrganigramaTreeProps) {
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set())

  const handleToggle = (id: number) => {
    setExpandedIds(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  if (isLoading) {
    return (
      <Stack>
        <Skeleton height={60} radius="md" />
        <Skeleton height={60} radius="md" ml="xl" />
        <Skeleton height={60} radius="md" ml="xl" />
        <Skeleton height={60} radius="md" ml={48} />
      </Stack>
    )
  }

  if (error) {
    return (
      <Text c="red" size="sm">
        Error al cargar el organigrama: {error.message}
      </Text>
    )
  }

  if (!unidades.length) {
    return (
      <Text c="dimmed" size="sm">
        No hay unidades registradas.
      </Text>
    )
  }

  return (
    <Stack gap="xs" style={{ paddingLeft: nivel > 0 ? 24 : 0 }}>
      {unidades.map(unidad => {
        const id         = Number(unidad.id)
        const isExpanded = expandedIds.has(id)
        const hijos      = unidad.hijos ?? []

        return (
          <Box key={id}>
            <OrganigramaNode
              unidad={unidad}
              nivel={nivel}
              expanded={isExpanded}
              onToggle={handleToggle}
            />
            {isExpanded && hijos.length > 0 && (
              <Box mt="xs">
                <OrganigramaTree
                  unidades={hijos}
                  nivel={nivel + 1}
                />
              </Box>
            )}
          </Box>
        )
      })}
    </Stack>
  )
}
