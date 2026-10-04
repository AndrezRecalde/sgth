'use client'

import { Card, Checkbox, Group, Stack, Text, TextInput } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { CountBadge } from '@/components/ui'
import type { CategoriaRiesgoMsp } from '../../services/catalogoRiesgosService'
import type { FactorRiesgoForm } from '../../schemas/femoEvaluacion.schema'
import classes from './FemoMatrizRiesgos.module.css'

interface Props {
  categoria:     CategoriaRiesgoMsp
  /** Los factores de esta categoría ya marcados en la actividad. */
  seleccionados: FactorRiesgoForm[]
  onToggle:      (factor: string) => void
  onDetalle:     (factor: string, detalle: string) => void
}

/** Una categoría del MSP (físico, de seguridad…) dentro de una actividad. */
export function FemoCategoriaRiesgo({ categoria, seleccionados, onToggle, onDetalle }: Props) {
  const contained = useContainedInput()
  const marcado = (factor: string) => seleccionados.some(f => f.factor === factor)

  return (
    <Card withBorder radius="md" padding="sm">
      <Group gap="xs" mb="sm">
        <Text size="sm" fw={600}>{categoria.etiqueta}</Text>
        {seleccionados.length > 0 && <CountBadge size="xs">{seleccionados.length}</CountBadge>}
      </Group>

      <Stack gap="sm">
        {categoria.grupos.map((grupo, g) => (
          <div key={grupo.subcategoria ?? g}>
            {/* Solo «De seguridad» se subdivide; en el resto los factores
                cuelgan directo de la categoría. */}
            {grupo.etiqueta && (
              <Text component="div" className={classes.subcategoria}>{grupo.etiqueta}</Text>
            )}
            <div className={classes.factores}>
              {grupo.factores.map((factor) => (
                <Checkbox
                  key={factor}
                  label={factor}
                  size="sm"
                  checked={marcado(factor)}
                  onChange={() => onToggle(factor)}
                />
              ))}
            </div>
          </div>
        ))}
      </Stack>

      {seleccionados.length > 0 && (
        <Stack gap="xs" mt="md">
          {seleccionados.map((f) => {
            const esOtros = f.factor === 'Otros'
            return (
              <TextInput
                key={f.factor}
                label={esOtros ? '¿Cuál? (Otros)' : `Detalle — ${f.factor}`}
                placeholder={esOtros ? 'Describa el factor' : 'Opcional'}
                // «Otros ____» del impreso: sin el detalle no dice nada. El
                // PDF lo imprime junto al factor.
                required={esOtros}
                error={esOtros && !f.medida_preventiva?.trim() ? 'Indique cuál es el factor' : undefined}
                {...contained}
                value={f.medida_preventiva ?? ''}
                onChange={(e) => onDetalle(f.factor, e.currentTarget.value)}
              />
            )
          })}
        </Stack>
      )}
    </Card>
  )
}
