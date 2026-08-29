'use client'

import { Card, Checkbox, NumberInput, Stack, Text, TextInput } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ConsumoSustanciaForm } from '../../schemas/femo.schema'
import { SUSTANCIA_OPTIONS } from '../../services/femoOptions'
import classes from './FemoConsumoSustanciasTable.module.css'

interface Props {
  data: ConsumoSustanciaForm[]
  onChange: (data: ConsumoSustanciaForm[]) => void
}

export function FemoConsumoSustanciasTable({ data, onChange }: Props) {
  // Variante compacta: es una sub-tabla de tres filas, no un formulario de
  // captura, y así los campos conviven con las casillas a la misma altura.
  const contained = useContainedInput('sm')

  const getFila = (sustancia: string): ConsumoSustanciaForm =>
    data.find((d) => d.sustancia === sustancia) ?? {
      sustancia: sustancia as ConsumoSustanciaForm['sustancia'],
      ex_consumidor: false,
      no_consume: false,
    }

  const setFila = (sustancia: string, valores: Partial<ConsumoSustanciaForm>) => {
    const actualizada = { ...getFila(sustancia), ...valores }
    onChange([...data.filter((d) => d.sustancia !== sustancia), actualizada])
  }

  return (
    <Stack gap="xs">
      <Text size="xs" fw={600} c="dimmed">
        Consumo de sustancias
      </Text>

      <Stack gap="sm">
        {SUSTANCIA_OPTIONS.map((opt) => {
          const fila = getFila(opt.value)

          return (
            <Card key={opt.value} withBorder radius="md" padding="sm">
              <Stack gap="sm">
                <div className={classes.cabecera}>
                  <span className={classes.nombre}>{opt.label}</span>

                  {opt.value === 'otra' && (
                    <div className={classes.detalleOtra}>
                      <TextInput
                        label="¿Cuál?"
                        placeholder="Sustancia"
                        disabled={fila.no_consume}
                        {...contained}
                        value={fila.sustancia_otra_detalle ?? ''}
                        onChange={(e) => setFila(opt.value, {
                          sustancia_otra_detalle: e.currentTarget.value,
                        })}
                      />
                    </div>
                  )}

                  <Checkbox
                    className={classes.maestro}
                    label="No consume"
                    checked={fila.no_consume ?? false}
                    onChange={(e) => setFila(opt.value, {
                      no_consume: e.currentTarget.checked,
                    })}
                  />
                </div>

                {!fila.no_consume && (
                  <div className={classes.campos}>
                    <NumberInput
                      label="Consumo (meses)"
                      min={0}
                      // Sin flechas: se teclea un número de meses, y los
                      // controles se montaban sobre la etiqueta interior.
                      hideControls
                      {...contained}
                      value={fila.tiempo_consumo_meses ?? undefined}
                      onChange={(v) => setFila(opt.value, {
                        tiempo_consumo_meses: v !== '' ? Number(v) : null,
                      })}
                    />

                    <div className={classes.casilla}>
                      <Checkbox
                        label="Ex-consumidor"
                        checked={fila.ex_consumidor ?? false}
                        onChange={(e) => setFila(opt.value, {
                          ex_consumidor: e.currentTarget.checked,
                          // Si deja de ser ex-consumidor, el tiempo de
                          // abstinencia ya no significa nada.
                          ...(e.currentTarget.checked
                            ? {}
                            : { tiempo_abstinencia_meses: null }),
                        })}
                      />
                    </div>

                    <div className={fila.ex_consumidor ? undefined : classes.filaInactiva}>
                      <NumberInput
                        label="Abstinencia (meses)"
                        min={0}
                        hideControls
                        disabled={!fila.ex_consumidor}
                        {...contained}
                        value={fila.tiempo_abstinencia_meses ?? undefined}
                        onChange={(v) => setFila(opt.value, {
                          tiempo_abstinencia_meses: v !== '' ? Number(v) : null,
                        })}
                      />
                    </div>
                  </div>
                )}
              </Stack>
            </Card>
          )
        })}
      </Stack>
    </Stack>
  )
}
