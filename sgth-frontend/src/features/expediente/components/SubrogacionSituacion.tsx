'use client'

import { Grid, Text } from '@mantine/core'
import { SituacionActualPanel } from './SituacionActualPanel'
import { SituacionSubrogadaPanel } from './SituacionSubrogadaPanel'
import { BloqueDetalle } from './BloqueDetalle'
import type { useFormularioSubrogacion } from '../hooks/useFormularioSubrogacion'

type Datos = ReturnType<typeof useFormularioSubrogacion>

/**
 * Las tres situaciones del acto: de dónde viene quien subroga, a quién
 * reemplaza y qué puesto asume.
 *
 * Sin esto, Talento Humano autorizaba a ciegas — en particular la diferencia de
 * remuneraciones, que es lo que realmente se paga.
 */
export function SubrogacionSituacion({ datos }: { datos: Datos }) {
  if (!datos.subroganteId && !datos.puestoSel) return null

  return (
    <Grid mt="xs">
      {datos.subroganteId && (
        <Grid.Col span={{ base: 12, md: 4 }}>
          <SituacionActualPanel
            servidorId={Number(datos.subroganteId)}
            titulo={datos.tipo === 'encargo' ? 'Situación del encargado' : 'Situación del subrogante'}
            soloVinculo
          />
        </Grid.Col>
      )}

      <Grid.Col span={{ base: 12, md: 4 }}>
        {datos.tipo === 'encargo' ? (
          <BloqueDetalle hundido altoCompleto>
            <Text size="sm" fw={700} mb="xs">TITULAR</Text>
            <Text size="sm" c="dimmed">
              Encargo: el puesto no tiene titular que reemplazar.
            </Text>
          </BloqueDetalle>
        ) : datos.subrogadoId ? (
          <SituacionActualPanel
            servidorId={Number(datos.subrogadoId)}
            titulo="Titular subrogado"
            soloVinculo
          />
        ) : (
          <BloqueDetalle hundido altoCompleto>
            <Text size="sm" fw={700} mb="xs">TITULAR SUBROGADO</Text>
            <Text size="sm" c="dimmed">
              {!datos.puestoSel
                ? 'Seleccione el puesto: el titular es quien lo ocupa.'
                : datos.hayQueElegirTitular
                  ? 'Varias personas ocupan el puesto: elija a cuál se reemplaza.'
                  : 'El puesto está vacante — no hay titular.'}
            </Text>
          </BloqueDetalle>
        )}
      </Grid.Col>

      <Grid.Col span={{ base: 12, md: 4 }}>
        <SituacionSubrogadaPanel
          unidad={datos.unidadSel}
          puesto={datos.puestoSel}
          rmuSubrogante={datos.rmuSubrogante}
        />
      </Grid.Col>
    </Grid>
  )
}
