'use client'

import {
  Stack, Grid, Card, Text, Group,
  Alert, UnstyledButton, Box,
} from '@mantine/core'
import { Controller, type Control,
         type FieldErrors } from 'react-hook-form'
import type { TramoFormData } from '../schemas/viatico.schema'

interface Props {
  control:      Control<TramoFormData>
  errors:       FieldErrors<TramoFormData>
  esPrimerTramo: boolean
  tipoTramo:    string | null | undefined
}

const OPCIONES = [
  {
    value:       'destino',
    emoji:       'D',
    label:       'DESTINO',
    description: 'Realizas actividades de la comisión en esta ciudad.',
    color:       'teal',
  },
  {
    value:       'escala',
    emoji:       'P',
    label:       'PARADA / ESCALA',
    description: 'Solo pasas por esta ciudad, no realizas actividades.',
    color:       'orange',
  },
  {
    value:       'regreso',
    emoji:       'R',
    label:       'REGRESO',
    description: 'Último tramo de vuelta a tu ciudad base.',
    color:       'red',
  },
]

export function TramoTipoSelector({
  control,
  errors,
  esPrimerTramo,
  tipoTramo,
}: Props) {
  if (esPrimerTramo) {
    return (
      <Alert color="blue" variant="light" p="xs">
        <Group gap="xs">
          <Text size="xs" fw={600} c="blue">Tramo de IDA</Text>
          <Text size="xs" c="dimmed">
            — se asigna automáticamente como el primer tramo
          </Text>
        </Group>
      </Alert>
    )
  }

  return (
    <Controller
      name="tipo_tramo"
      control={control}
      rules={{ required: 'Debe seleccionar el tipo de tramo' }}
      render={({ field }) => (
        <Stack gap="xs">
          <Text size="xs" c="dimmed">
            Selecciona el rol de este tramo en tu itinerario:
          </Text>
          <Grid>
            {OPCIONES.map((opt) => {
              const selected = field.value === opt.value
              return (
                <Grid.Col key={opt.value} span={{ base: 12, sm: 4 }}>
                  <UnstyledButton
                    onClick={() => field.onChange(opt.value)}
                    style={{ width: '100%' }}
                  >
                    <Card
                      withBorder
                      radius="md"
                      p="sm"
                      style={{
                        borderColor: selected
                          ? `var(--mantine-color-${opt.color}-6)`
                          : undefined,
                        borderWidth:  selected ? 2 : 1,
                        background:   selected
                          ? `var(--mantine-color-${opt.color}-0)`
                          : undefined,
                        cursor:     'pointer',
                        transition: 'all 0.15s ease',
                      }}
                    >
                      <Group justify="space-between" mb={4}>
                        <Text size="sm" fw={700}
                          c={selected ? opt.color : 'dark'}>
                          {opt.emoji}
                        </Text>
                        <Box
                          style={{
                            width:        18,
                            height:       18,
                            borderRadius: 4,
                            border:       selected
                              ? 'none'
                              : '2px solid var(--mantine-color-gray-4)',
                            background:   selected
                              ? `var(--mantine-color-${opt.color}-6)`
                              : 'white',
                            display:        'flex',
                            alignItems:     'center',
                            justifyContent: 'center',
                            flexShrink:     0,
                          }}
                        >
                          {selected && (
                            <Text size="xs" c="white" fw={700}
                              style={{ lineHeight: 1 }}>
                              V
                            </Text>
                          )}
                        </Box>
                      </Group>
                      <Text size="xs" fw={700}
                        c={selected ? opt.color : 'dark'} mb={4}>
                        {opt.label}
                      </Text>
                      <Text size="xs" c="dimmed" lh={1.4}>
                        {opt.description}
                      </Text>
                    </Card>
                  </UnstyledButton>
                </Grid.Col>
              )
            })}
          </Grid>
          {errors.tipo_tramo && (
            <Text size="xs" c="red">
              {errors.tipo_tramo.message as string}
            </Text>
          )}
        </Stack>
      )}
    />
  )
}
