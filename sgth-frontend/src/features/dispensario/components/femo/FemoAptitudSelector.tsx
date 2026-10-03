'use client'

import { Grid, Group, Radio, Text, Textarea } from '@mantine/core'
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
  apto_con_restricciones: 'Limitaciones para el puesto',
  en_observacion:         'Observaciones',
  no_apto:                'Motivo de la no aptitud',
}

const colorDe = (aptitud: string) => SEMANTIC_COLOR[TONO_APTITUD[aptitud] ?? 'neutral']

/**
 * Sección L. Es el dictamen: lo que se marque aquí es lo que recibe Talento
 * Humano y lo que imprime el certificado.
 *
 * Tarjetas de `Radio.Card`, que se eligen también con teclado; antes eran
 * tarjetas con un clic y un radio decorativo que no respondía.
 */
export function FemoAptitudSelector({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  return (
    <FemoSeccion letra="L" titulo="Aptitud médica para el trabajo">
      <Radio.Group
        value={fichaData.aptitud ?? null}
        onChange={(v) => onFichaChange({
          ...fichaData,
          aptitud: v as FichaBaseForm['aptitud'],
          // «Apto» no lleva observación: si quedara la de otra opción, el
          // certificado de un apto imprimiría restricciones.
          ...(v === 'apto' ? { restricciones: null } : {}),
        })}
      >
        <Grid>
          {APTITUD_OPTIONS.map((opt) => (
            <Grid.Col key={opt.value} span={{ base: 12, xs: 6, md: 3 }}>
              <Radio.Card value={opt.value} radius="md" p="sm" h="100%">
                <Group wrap="nowrap" gap="sm">
                  <Radio.Indicator color={colorDe(opt.value)} />
                  <Text size="sm" fw={500}>{opt.label}</Text>
                </Group>
              </Radio.Card>
            </Grid.Col>
          ))}
        </Grid>
      </Radio.Group>

      {!fichaData.aptitud && (
        <Text size="xs" c="dimmed">Se elige antes de emitir el dictamen.</Text>
      )}

      {fichaData.aptitud && fichaData.aptitud !== 'apto' && (
        <Textarea
          label={ETIQUETA_OBSERVACION[fichaData.aptitud] ?? 'Observaciones'}
          placeholder="Llega a Talento Humano junto con el dictamen"
          autosize
          minRows={2}
          // Con limitaciones o no apto, el dictamen no se emite sin esto.
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
