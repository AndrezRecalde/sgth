'use client'

import { Box, Grid, Paper, Skeleton, Text } from '@mantine/core'
import { useServidor } from '../hooks/useServidor'

interface Props {
  servidorId: number
}

/**
 * Forma mínima de la respuesta del expediente que consume este panel. El tipo
 * generado de Servidor no describe las relaciones anidadas que llegan aquí.
 */
type PuestoSituacion = {
  cargo?: { nombre?: string | null } | null
  rmu?: string | number | null
  partida_presupuestaria?: { codigo?: string | null } | null
}

type ServidorSituacion = {
  nombre?: string | null
  segundo_nombre?: string | null
  apellido?: string | null
  segundo_apellido?: string | null
  cedula?: string | null
  numero_papeleta_votacion?: string | null
  puesto?: PuestoSituacion | null
  unidad_administrativa?: { nombre?: string | null } | null
  contrato_vigente?: {
    remuneracion?: string | number | null
    puesto?: PuestoSituacion | null
    unidad_administrativa?: { nombre?: string | null } | null
  } | null
}

function Campo({ etiqueta, valor }: { etiqueta: string; valor?: string | null }) {
  return (
    <Box>
      <Text size="xs" fw={600} c="dimmed" tt="uppercase">{etiqueta}</Text>
      <Text size="sm">{valor?.toString().trim() || '—'}</Text>
    </Box>
  )
}

/**
 * Situación actual del servidor, en solo lectura: es el bloque izquierdo del
 * documento de Acción de Personal. Se lee del expediente en vez de pedirlo en
 * el formulario — son datos que ya existen y que nadie debería re-teclear.
 */
export function SituacionActualPanel({ servidorId }: Props) {
  const { data: servidor, isLoading } = useServidor(servidorId)

  if (isLoading) return <Skeleton height={190} radius="md" />
  if (!servidor) return null

  const s = servidor as unknown as ServidorSituacion
  const contrato = s.contrato_vigente ?? null
  const puesto = contrato?.puesto ?? s.puesto ?? null
  const sinVinculo = !contrato

  const nombres = [s.nombre, s.segundo_nombre].filter(Boolean).join(' ')
  const apellidos = [s.apellido, s.segundo_apellido].filter(Boolean).join(' ')

  const rmu = contrato?.remuneracion ?? puesto?.rmu
  const rmuFmt = rmu != null ? `$ ${Number(rmu).toFixed(2)}` : null

  return (
    <Paper withBorder p="sm" radius="md" bg="var(--mantine-color-gray-0)">
      <Text size="sm" fw={700} mb="xs">SITUACIÓN ACTUAL</Text>
      <Grid>
        <Grid.Col span={6}><Campo etiqueta="Apellidos" valor={apellidos} /></Grid.Col>
        <Grid.Col span={6}><Campo etiqueta="Nombres" valor={nombres} /></Grid.Col>
        <Grid.Col span={6}><Campo etiqueta="Cédula" valor={s.cedula} /></Grid.Col>
        <Grid.Col span={6}>
          <Campo etiqueta="Papeleta de votación" valor={s.numero_papeleta_votacion} />
        </Grid.Col>
        {sinVinculo ? (
          // Un ingreso no tiene situación previa que comparar. Mostrar unidad,
          // puesto y R.M.U. en blanco haría pensar que faltan datos por leer.
          <Grid.Col span={12}>
            <Text size="sm" c="dimmed">
              Sin vínculo laboral vigente — no hay situación previa que comparar.
            </Text>
          </Grid.Col>
        ) : (
          <>
            <Grid.Col span={12}>
              <Campo
                etiqueta="Unidad administrativa"
                valor={contrato?.unidad_administrativa?.nombre ?? s.unidad_administrativa?.nombre}
              />
            </Grid.Col>
            <Grid.Col span={12}>
              <Campo etiqueta="Puesto" valor={puesto?.cargo?.nombre} />
            </Grid.Col>
            <Grid.Col span={6}><Campo etiqueta="R.M.U." valor={rmuFmt} /></Grid.Col>
            <Grid.Col span={6}>
              <Campo
                etiqueta="Partida presupuestaria"
                valor={puesto?.partida_presupuestaria?.codigo}
              />
            </Grid.Col>
          </>
        )}
      </Grid>
    </Paper>
  )
}
