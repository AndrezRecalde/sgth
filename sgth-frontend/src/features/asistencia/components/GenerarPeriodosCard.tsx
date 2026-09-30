'use client'

import { useState } from 'react'
import { Button, Group, NumberInput } from '@mantine/core'
import { IconUsers } from '@tabler/icons-react'
import { SectionCard, confirmar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { ANIO_MAXIMO, ANIO_MINIMO } from './periodos.constants'
import { usePeriodosMutations } from '../hooks/usePeriodosMutations'

/**
 * Generación masiva de períodos: un año, y todos los servidores activos.
 *
 * El año es suyo y no de la pantalla entera. Antes había un solo `anio` en el
 * componente padre, y el botón de la consulta individual —dos secciones más
 * abajo— decía «Generar período {anio}» leyendo ese mismo estado: cambiar el
 * año aquí cambiaba en silencio lo que hacía el otro botón, sin que nada lo
 * dijera.
 */
export function GenerarPeriodosCard() {
  const contained = useContainedInput('sm')
  const { generarTodos } = usePeriodosMutations()
  const [anio, setAnio] = useState(() => new Date().getFullYear())

  return (
    <SectionCard
      title="Generación masiva de períodos"
      description="Genera los períodos de vacaciones de todos los servidores activos. Los períodos ya cerrados se dejan intactos."
    >
      <Group gap="sm" wrap="wrap" align="flex-end">
        <NumberInput
          label="Año"
          {...contained}
          min={ANIO_MINIMO}
          max={ANIO_MAXIMO}
          clampBehavior="strict"
          allowDecimal={false}
          allowNegative={false}
          w={120}
          value={anio}
          // Mantine entrega una cadena mientras se escribe y `''` al borrar.
          // Antes cualquier valor no numérico devolvía el campo al año actual,
          // así que borrarlo para reescribirlo lo hacía saltar solo.
          onChange={(valor) => {
            const numero = typeof valor === 'number' ? valor : Number(valor)
            if (Number.isFinite(numero) && numero > 0) setAnio(numero)
          }}
        />
        <Button
          variant="light"
          leftSection={<IconUsers size={16} />}
          loading={generarTodos.isPending}
          onClick={() =>
            confirmar({
              title: 'Generar períodos de vacaciones',
              message: (
                <>
                  Se generará el período <b>{anio}</b> para todos los servidores
                  activos.
                </>
              ),
              confirmLabel: 'Generar',
              onConfirm: () => generarTodos.mutate(anio),
            })
          }
        >
          Generar para todos
        </Button>
      </Group>
    </SectionCard>
  )
}
