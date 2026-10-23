'use client'

import { useState } from 'react'
import { Card, Checkbox, Group, SimpleGrid, Stack, Text, TextInput } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { filtrarPorNombre } from '../../utils/buscarExamen'
import type { AreaExamen, CatalogoExamen } from '../../services/examenService'

interface Props {
  examenes:   CatalogoExamen[]
  areas:      AreaExamen[]
  marcados:   ReadonlySet<number>
  onAlternar: (examen: CatalogoExamen) => void
}

/**
 * El catálogo como la hoja del laboratorio que los médicos ya marcaban a mano:
 * cada área con sus exámenes en casillas, en el mismo orden (D7). El filtro
 * deja solo los que coinciden, para no recorrer los doscientos.
 */
export function CatalogoExamenesCasillas({ examenes, areas, marcados, onAlternar }: Props) {
  const contained = useContainedInput()
  const [filtro, setFiltro] = useState('')

  const visibles = filtrarPorNombre(examenes, filtro)
  const grupos = areas
    .map((area) => ({ area, examenes: visibles.filter((e) => e.area === area.value) }))
    .filter((g) => g.examenes.length > 0)

  return (
    <Stack gap="sm">
      <TextInput
        label="Filtrar exámenes"
        placeholder="Parte del nombre: «gluc», «rx», «tsh»"
        {...contained}
        value={filtro}
        onChange={(e) => setFiltro(e.currentTarget.value)}
      />

      {grupos.length === 0 ? (
        <Text size="sm" c="dimmed">
          Ningún examen del catálogo coincide con «{filtro.trim()}».
        </Text>
      ) : (
        <SimpleGrid cols={{ base: 1, sm: 2, md: 3, xl: 4 }} spacing="sm">
          {grupos.map(({ area, examenes: delArea }) => (
            <Card key={area.value} withBorder radius="md" p="sm">
              <Text size="xs" fw={700} tt="uppercase" c="dimmed" mb={6}>
                {area.etiqueta}
              </Text>
              <Stack gap={6}>
                {delArea.map((examen) => (
                  <Checkbox
                    key={examen.id}
                    size="xs"
                    checked={marcados.has(examen.id)}
                    onChange={() => onAlternar(examen)}
                    label={
                      <Group gap={4} wrap="nowrap">
                        <Text size="xs">{examen.nombre}</Text>
                        {examen.sensible && (
                          <StatusBadge tone="warning" size="xs">Sensible</StatusBadge>
                        )}
                      </Group>
                    }
                  />
                ))}
              </Stack>
            </Card>
          ))}
        </SimpleGrid>
      )}
    </Stack>
  )
}
