'use client'

import { Stack, Grid, Card, Radio, Text, Textarea } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import { APTITUD_OPTIONS, TONO_APTITUD } from '../../services/femoOptions'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { FemoSeccion } from './FemoSeccion'

interface Props {
  fichaData:     Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

/** Qué se le pide al médico según la aptitud (sección L, «Observaciones»). */
const ETIQUETA_OBSERVACION: Record<string, string> = {
  apto_con_restricciones: 'Restricciones para el puesto',
  en_observacion:         'Observaciones',
  no_apto:                'Motivo de la no aptitud',
}

const colorDe = (aptitud: string) => SEMANTIC_COLOR[TONO_APTITUD[aptitud] ?? 'neutral']

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
                    ? `var(--mantine-color-${colorDe(opt.value)}-6)`
                    : undefined,
                  borderWidth: isSelected ? 2 : 1,
                  cursor: 'pointer',
                }}
                onClick={() => onFichaChange({
                  ...fichaData,
                  aptitud: opt.value as FichaBaseForm['aptitud'],
                  // «Apto» no lleva observación: si quedara la de otra opción,
                  // el certificado de un apto imprimiría restricciones.
                  ...(opt.value === 'apto' ? { restricciones: null } : {}),
                })}
              >
                <Stack gap={4} align="center">
                  <Radio
                    checked={isSelected}
                    onChange={() => {}}
                    color={colorDe(opt.value)}
                  />
                  <Text
                    size="sm"
                    fw={500}
                    ta="center"
                    c={isSelected ? colorDe(opt.value) : undefined}
                  >
                    {opt.label}
                  </Text>
                </Stack>
              </Card>
            </Grid.Col>
          )
        })}
      </Grid>

      {fichaData.aptitud && fichaData.aptitud !== 'apto' && (
        <Textarea
          label={ETIQUETA_OBSERVACION[fichaData.aptitud] ?? 'Observaciones'}
          placeholder="Llega a Talento Humano junto con el dictamen"
          autosize
          minRows={2}
          // Con restricciones o no apto, el dictamen no se emite sin esto.
          required={fichaData.aptitud !== 'en_observacion'}
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
