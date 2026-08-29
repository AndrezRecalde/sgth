'use client'

import { Stack, Grid, Card, Radio, Text, Textarea } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import { APTITUD_OPTIONS, APTITUD_COLORS } from '../../services/femoOptions'
import { FemoSeccion } from './FemoSeccion'

interface Props {
  fichaData:     Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

export function FemoAptitudSelector({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  return (
    <FemoSeccion letra="L" titulo="Aptitud médica para el trabajo">
      <Grid>
        {APTITUD_OPTIONS.map((opt) => {
          const isSelected = fichaData.aptitud === opt.value
          return (
            <Grid.Col key={opt.value} span={{ base: 6, md: 3 }}>
              <Card
                withBorder
                radius="md"
                p="sm"
                style={{
                  borderColor: isSelected
                    ? `var(--mantine-color-${APTITUD_COLORS[opt.value]}-6)`
                    : undefined,
                  borderWidth: isSelected ? 2 : 1,
                  cursor: 'pointer',
                }}
                onClick={() => onFichaChange({
                  ...fichaData,
                  aptitud: opt.value as FichaBaseForm['aptitud'],
                })}
              >
                <Stack gap={4} align="center">
                  <Radio
                    checked={isSelected}
                    onChange={() => {}}
                    color={APTITUD_COLORS[opt.value]}
                  />
                  <Text
                    size="sm"
                    fw={500}
                    ta="center"
                    c={isSelected ? APTITUD_COLORS[opt.value] : undefined}
                  >
                    {opt.label}
                  </Text>
                </Stack>
              </Card>
            </Grid.Col>
          )
        })}
      </Grid>

      {(fichaData.aptitud === 'apto_con_restricciones' ||
        fichaData.aptitud === 'no_apto') && (
        <Textarea
          label="Restricciones"
          placeholder="Detalle las restricciones para el puesto de trabajo"
          autosize
          minRows={2}
          {...contained}
          value={fichaData.restricciones ?? ''}
          onChange={(e) => onFichaChange({
            ...fichaData, restricciones: e.currentTarget.value,
          })}
        />
      )}
    </FemoSeccion>
  )
}
