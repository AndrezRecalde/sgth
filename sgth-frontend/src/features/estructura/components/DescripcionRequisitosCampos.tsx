'use client'

import { ActionIcon, Button, Grid, NumberInput, Select, Stack, Text, TextInput } from '@mantine/core'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { Controller, useFieldArray, useWatch, type Control, type FieldErrors, type UseFormRegister } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { NIVELES_INSTRUCCION } from '../constants/perfilPuesto'
import type { DescripcionPuestoFormData } from '../schemas/descripcionPuesto.schema'

interface Props {
  control:  Control<DescripcionPuestoFormData>
  register: UseFormRegister<DescripcionPuestoFormData>
  errors:   FieldErrors<DescripcionPuestoFormData>
}

/** El manual da hasta tres alternativas; el backend admite cinco. */
const MAXIMO = 5

/**
 * Las alternativas de instrucción formal y su experiencia, en el orden de la
 * ficha: «técnico superior y 6 años, o tecnológico superior y 5, o tercer
 * nivel y 2 años y 6 meses».
 */
export function DescripcionRequisitosCampos({ control, register, errors }: Props) {
  const contained = useContainedInput()
  const alternativas = useFieldArray({ control, name: 'requisitos' })
  const niveles = useWatch({ control, name: 'requisitos' })

  return (
    <Stack gap="xs">
      {alternativas.fields.length === 0 && (
        <Text size="xs" c="dimmed">Sin alternativas. Agregue la instrucción que pide el puesto.</Text>
      )}
      {alternativas.fields.map((campo, i) => {
        const error = errors.requisitos?.[i]
        return (
          <Grid key={campo.id} gap="xs" align="flex-start">
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name={`requisitos.${i}.nivel_instruccion`}
                control={control}
                render={({ field }) => (
                  <Select
                    label={`Alternativa ${i + 1}`}
                    data={NIVELES_INSTRUCCION}
                    allowDeselect={false}
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => field.onChange(NIVELES_INSTRUCCION.find((n) => n.value === v)?.value)}
                    error={error?.nivel_instruccion?.message}
                  />
                )}
              />
            </Grid.Col>
            {(['anios', 'meses'] as const).map((parte) => (
              <Grid.Col key={parte} span={{ base: 5, sm: 2.5 }}>
                <Controller
                  name={`requisitos.${i}.${parte}`}
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      label={parte === 'anios' ? 'Años' : 'Meses'}
                      min={0}
                      max={parte === 'anios' ? 50 : 11}
                      allowDecimal={false}
                      {...contained}
                      value={field.value ?? 0}
                      onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)}
                      error={error?.[parte]?.message}
                    />
                  )}
                />
              </Grid.Col>
            ))}
            <Grid.Col span={{ base: 2, sm: 1 }}>
              <ActionIcon variant="subtle" color="red" mt="md" aria-label={`Quitar la alternativa ${i + 1}`} onClick={() => alternativas.remove(i)}>
                <IconTrash size={16} />
              </ActionIcon>
            </Grid.Col>
            {/* Los estudios en curso no son un título: se dice qué está aprobado. */}
            {niveles?.[i]?.nivel_instruccion === 'estudios_superiores_en_curso' && (
              <Grid.Col span={12}>
                <TextInput
                  label="Qué tiene aprobado"
                  placeholder="Ej: Tercer año o sexto semestre aprobado"
                  {...contained}
                  {...register(`requisitos.${i}.detalle`)}
                  error={error?.detalle?.message}
                />
              </Grid.Col>
            )}
          </Grid>
        )
      })}
      {alternativas.fields.length < MAXIMO && (
        <Button
          size="xs"
          variant="subtle"
          leftSection={<IconPlus size={14} />}
          style={{ alignSelf: 'flex-start' }}
          onClick={() => alternativas.append({ nivel_instruccion: 'tercer_nivel', detalle: '', anios: 0, meses: 0 })}
        >
          Agregar alternativa
        </Button>
      )}
    </Stack>
  )
}
