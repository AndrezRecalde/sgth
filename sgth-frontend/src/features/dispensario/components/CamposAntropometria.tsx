'use client'

import { NumberInput, SimpleGrid, Stack, Alert, Text } from '@mantine/core'
import { Controller, useWatch, type Control, type FieldErrors } from 'react-hook-form'
import { IconScale } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SectionCard } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { calcularImc, clasificacionImc } from '../constants/signosVitales'
import type { SignosVitalesFormData } from '../schemas/signosVitales.schema'

interface Props {
  control:       Control<SignosVitalesFormData>
  errors:        FieldErrors<SignosVitalesFormData>
  /** Solo el previo al FEMO pide el perímetro abdominal (sección E). */
  conPerimetro?: boolean
}

/** Peso, talla (y perímetro), con el IMC calculado al vuelo. */
export function CamposAntropometria({ control, errors, conPerimetro = false }: Props) {
  const contained = useContainedInput()
  const [peso, talla] = useWatch({ control, name: ['peso_kg', 'talla_cm'] })

  const imc = calcularImc(peso, talla)
  const clasificacion = clasificacionImc(imc)

  return (
    <SectionCard title="Antropometría">
      <Stack gap="md">
        <SimpleGrid cols={{ base: 1, sm: conPerimetro ? 3 : 2 }}>
          <Controller
            name="peso_kg"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Peso (kg)"
                decimalScale={2}
                hideControls
                {...contained}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(v === '' ? undefined : Number(v))}
                error={errors.peso_kg?.message}
              />
            )}
          />
          <Controller
            name="talla_cm"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Talla (cm)"
                decimalScale={1}
                hideControls
                {...contained}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(v === '' ? undefined : Number(v))}
                error={errors.talla_cm?.message}
              />
            )}
          />
          {conPerimetro && (
            <Controller
              name="perimetro_abdominal_cm"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Perímetro abdominal (cm)"
                  description="Opcional"
                  decimalScale={1}
                  hideControls
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(v === '' ? null : Number(v))}
                  error={errors.perimetro_abdominal_cm?.message}
                />
              )}
            />
          )}
        </SimpleGrid>

        {imc !== null && (
          <Alert
            icon={<IconScale size={14} />}
            color={SEMANTIC_COLOR[clasificacion.tono]}
            variant="light"
            radius="md"
          >
            <Text size="xs">
              IMC calculado: <strong>{imc}</strong>
              {' — '}{clasificacion.texto}
            </Text>
          </Alert>
        )}
      </Stack>
    </SectionCard>
  )
}
