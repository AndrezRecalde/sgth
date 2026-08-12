'use client'

import { Box, Grid, Paper, Text } from '@mantine/core'
import type { PuestoConRelaciones, UnidadConRelaciones } from '@/types/api'

interface Props {
  unidad?: UnidadConRelaciones | null
  puesto?: PuestoConRelaciones | null
  /** R.M.U. del subrogante, para calcular la diferencia que se le pagará. */
  rmuSubrogante?: number | null
}

function Campo({ etiqueta, valor }: { etiqueta: string; valor?: string | null }) {
  return (
    <Box>
      <Text size="xs" fw={600} c="dimmed" tt="uppercase">{etiqueta}</Text>
      <Text size="sm">{valor?.toString().trim() || '—'}</Text>
    </Box>
  )
}

const dinero = (v?: number | null) => (v != null ? `$ ${v.toFixed(2)}` : null)

/**
 * El puesto que se va a subrogar o encargar.
 *
 * Muestra la diferencia de remuneraciones porque es lo que realmente se
 * autoriza: la subrogación no paga el sueldo del puesto, paga la diferencia
 * entre lo que el servidor ya gana y lo que corresponde al puesto que asume.
 * Sin verla, Talento Humano aprueba un gasto que no está viendo.
 */
export function SituacionSubrogadaPanel({ unidad, puesto, rmuSubrogante }: Props) {
  const rmuPuesto = puesto?.rmu != null ? Number(puesto.rmu) : null

  const diferencia = rmuPuesto != null && rmuSubrogante != null
    ? rmuPuesto - rmuSubrogante
    : null

  return (
    <Paper withBorder p="sm" radius="md" h="100%">
      <Text size="sm" fw={700} mb="xs">PUESTO A SUBROGAR</Text>
      <Grid>
        <Grid.Col span={12}>
          <Campo etiqueta="Unidad administrativa" valor={unidad?.nombre} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Campo etiqueta="Puesto" valor={puesto?.cargo?.nombre} />
        </Grid.Col>
        <Grid.Col span={6}>
          <Campo
            etiqueta="Grupo ocupacional"
            valor={puesto?.grupo_ocupacional?.denominacion_generica
              ?? puesto?.grupo_ocupacional?.grupo}
          />
        </Grid.Col>
        <Grid.Col span={6}>
          <Campo etiqueta="Partida" valor={puesto?.partida_presupuestaria?.codigo} />
        </Grid.Col>
        <Grid.Col span={6}>
          <Campo etiqueta="R.M.U. del puesto" valor={dinero(rmuPuesto)} />
        </Grid.Col>
        <Grid.Col span={6}>
          <Box>
            <Text size="xs" fw={600} c="dimmed" tt="uppercase">Diferencia a pagar</Text>
            {diferencia === null ? (
              <Text size="sm">—</Text>
            ) : diferencia > 0 ? (
              <Text size="sm" fw={700} c="emerald">{dinero(diferencia)}</Text>
            ) : (
              <Text size="xs" c="orange">
                El puesto no supera la remuneración actual: no genera diferencia.
              </Text>
            )}
          </Box>
        </Grid.Col>
      </Grid>
    </Paper>
  )
}
