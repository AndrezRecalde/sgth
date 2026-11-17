import { Grid } from '@mantine/core'
import { IconCalendarDollar, IconCoins, IconFileInvoice, IconSum } from '@tabler/icons-react'
import { StatCard } from '@/components/ui'
import { dineroConMiles } from '@/lib/dinero'
import type { MasaSalarial } from '@/types/api'

interface Props {
  totales?: MasaSalarial['totales']
  loading:  boolean
}

const personas = (n: number, una: string, varias: string) => `${n} ${n === 1 ? una : varias}`

/**
 * Las cuatro cifras de arriba. Servicios Profesionales va aparte porque son
 * honorarios de un contrato civil, no remuneración (D6), y el total general
 * suma las dos cosas.
 */
export function MasaSalarialTotales({ totales, loading }: Props) {
  const { dependencia, servicios_profesionales: profesionales, general } = totales ?? {}

  return (
    <Grid gap="md">
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Al mes, en relación de dependencia"
          value={dineroConMiles(dependencia?.mensual ?? 0)}
          icon={IconCoins}
          hint={dependencia ? personas(dependencia.personas, 'persona', 'personas') : undefined}
          loading={loading}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Al año (× 12)"
          value={dineroConMiles(dependencia?.anual ?? 0)}
          icon={IconCalendarDollar}
          hint="Sin aportes, décimos ni fondos de reserva"
          loading={loading}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Honorarios de Servicios Profesionales"
          value={dineroConMiles(profesionales?.mensual ?? 0)}
          icon={IconFileInvoice}
          hint={profesionales ? `${personas(profesionales.personas, 'contrato', 'contratos')} · al mes` : undefined}
          loading={loading}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Total general al mes"
          value={dineroConMiles(general?.mensual ?? 0)}
          icon={IconSum}
          hint={general ? `${dineroConMiles(general.anual)} al año` : undefined}
          loading={loading}
        />
      </Grid.Col>
    </Grid>
  )
}
