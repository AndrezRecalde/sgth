'use client'

import { SimpleGrid } from '@mantine/core'
import {
  IconCalendarOff, IconCircleCheck, IconClockExclamation, IconUserOff, IconUsers,
} from '@tabler/icons-react'
import { StatCard } from '@/components/ui'
import type { ResumenCobertura } from '../services/coberturaCertificacionService'

interface Props {
  resumen?: ResumenCobertura
  cargando: boolean
}

/**
 * El semáforo del tablero de cobertura.
 *
 * El tono va solo cuando la cifra tiene lectura (regla 06): una plantilla
 * entera al día no debe abrir la pantalla con tres tarjetas en rojo y ámbar,
 * y por eso «vencidas», «sin evaluación» y «por vencer» solo se tiñen cuando
 * valen algo. «Al día» no lleva tono: es el conteo neutro de lo que está bien.
 *
 * Las cifras son de TODA la plantilla filtrada por unidad y búsqueda, no del
 * estado que se esté mirando: el servidor las calcula sin aplicar
 * `estado_cobertura` para que pulsar «Vencidas» no ponga las otras tres a
 * cero (ver `CoberturaCertificacionService::resumen`).
 */
export function ResumenCoberturaTarjetas({ resumen, cargando }: Props) {
  const total = resumen?.total ?? 0

  /** «3 de 247» dice más que «3» a secas cuando se audita una unidad. */
  const sobreTotal = (valor: number) =>
    total > 0 ? `${valor} de ${total} servidores` : undefined

  return (
    <SimpleGrid cols={{ base: 2, sm: 3, lg: 5 }} spacing="md">
      <StatCard
        label="Plantilla activa"
        value={total}
        icon={IconUsers}
        loading={cargando}
      />
      <StatCard
        label="Vencidas"
        value={resumen?.vencida ?? 0}
        icon={IconCalendarOff}
        tone={resumen?.vencida ? 'danger' : undefined}
        hint={resumen ? sobreTotal(resumen.vencida) : undefined}
        loading={cargando}
      />
      <StatCard
        label="Sin evaluación"
        value={resumen?.sin_evaluacion ?? 0}
        icon={IconUserOff}
        tone={resumen?.sin_evaluacion ? 'danger' : undefined}
        hint={resumen ? sobreTotal(resumen.sin_evaluacion) : undefined}
        loading={cargando}
      />
      <StatCard
        label="Por vencer"
        value={resumen?.por_vencer ?? 0}
        icon={IconClockExclamation}
        tone={resumen?.por_vencer ? 'warning' : undefined}
        hint="En los próximos 90 días"
        loading={cargando}
      />
      <StatCard
        label="Al día"
        value={resumen?.al_dia ?? 0}
        icon={IconCircleCheck}
        hint={resumen ? sobreTotal(resumen.al_dia) : undefined}
        loading={cargando}
      />
    </SimpleGrid>
  )
}
