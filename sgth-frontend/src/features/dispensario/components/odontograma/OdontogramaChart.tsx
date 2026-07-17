'use client'

import { Stack, Group, Text, Divider, Badge } from '@mantine/core'
import {
  CONDICION_LABELS, CONDICION_COLORS,
} from '@/features/dispensario/services/odontogramaService'
import type { OdontogramaPieza } from '@/features/dispensario/services/odontogramaService'
import { Tooth } from './Tooth'
import classes from './OdontogramaChart.module.css'

interface Props {
  piezas:              OdontogramaPieza[]
  mostrarTemporal:     boolean
  onSeleccionarPieza:  (pieza: OdontogramaPieza) => void
}

const PERMANENTE_SUPERIOR_DERECHA = [18, 17, 16, 15, 14, 13, 12, 11]
const PERMANENTE_SUPERIOR_IZQUIERDA = [21, 22, 23, 24, 25, 26, 27, 28]
const PERMANENTE_INFERIOR_DERECHA = [48, 47, 46, 45, 44, 43, 42, 41]
const PERMANENTE_INFERIOR_IZQUIERDA = [31, 32, 33, 34, 35, 36, 37, 38]

const TEMPORAL_SUPERIOR_DERECHA = [55, 54, 53, 52, 51]
const TEMPORAL_SUPERIOR_IZQUIERDA = [61, 62, 63, 64, 65]
const TEMPORAL_INFERIOR_DERECHA = [85, 84, 83, 82, 81]
const TEMPORAL_INFERIOR_IZQUIERDA = [71, 72, 73, 74, 75]

export function OdontogramaChart({
  piezas, mostrarTemporal, onSeleccionarPieza,
}: Props) {
  const porNumero = new Map(piezas.map(p => [p.numero_pieza, p]))

  const renderFila = (derecha: number[], izquierda: number[]) => (
    <div className={classes.fila}>
      <div className={classes.cuadrante}>
        {derecha.map(n => renderDiente(n))}
      </div>
      <div className={classes.separador} />
      <div className={classes.cuadrante}>
        {izquierda.map(n => renderDiente(n))}
      </div>
    </div>
  )

  const renderDiente = (numero: number) => {
    const pieza = porNumero.get(numero)
    if (!pieza) return null

    return (
      <Tooth
        key={numero}
        pieza={pieza}
        color={CONDICION_COLORS[pieza.condicion]}
        onClick={onSeleccionarPieza}
      />
    )
  }

  const condicionesEnUso = Array.from(
    new Set(piezas.map(p => p.condicion))
  )

  return (
    <Stack gap="md">
      <Stack gap={4}>
        <Text size="xs" c="dimmed" fw={600}>Dentición permanente</Text>
        {renderFila(PERMANENTE_SUPERIOR_DERECHA, PERMANENTE_SUPERIOR_IZQUIERDA)}
        {renderFila(PERMANENTE_INFERIOR_DERECHA, PERMANENTE_INFERIOR_IZQUIERDA)}
      </Stack>

      {mostrarTemporal && (
        <>
          <Divider label="Dentición temporal" labelPosition="left" />
          <Stack gap={4}>
            {renderFila(TEMPORAL_SUPERIOR_DERECHA, TEMPORAL_SUPERIOR_IZQUIERDA)}
            {renderFila(TEMPORAL_INFERIOR_DERECHA, TEMPORAL_INFERIOR_IZQUIERDA)}
          </Stack>
        </>
      )}

      <Group gap="xs" wrap="wrap">
        {condicionesEnUso.map(condicion => (
          <Badge
            key={condicion}
            size="xs"
            variant="light"
            color={CONDICION_COLORS[condicion]}
          >
            {CONDICION_LABELS[condicion]}
          </Badge>
        ))}
      </Group>
    </Stack>
  )
}
