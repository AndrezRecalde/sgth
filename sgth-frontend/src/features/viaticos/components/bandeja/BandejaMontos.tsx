'use client'

import { Grid } from '@mantine/core'
import { IconAlarm, IconCash, IconReceipt2, IconWallet } from '@tabler/icons-react'
import { StatCard } from '@/components/ui'
import { dolares } from '../../utils/monto'
import type { BandejaResumen } from '@/types/api'

interface Props {
  resumen?: BandejaResumen
  loading: boolean
}

/** Cuánto dinero hay en cada etapa y cuántas liquidaciones están fuera de plazo. */
export function BandejaMontos({ resumen, loading }: Props) {
  const vencidas = resumen?.vencidas ?? 0

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Comprometido"
          value={dolares(resumen?.montos.comprometido ?? 0)}
          hint="Aprobado y aún sin contabilizar"
          icon={IconWallet}
          loading={loading}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Anticipos entregados"
          value={dolares(resumen?.montos.anticipos_entregados ?? 0)}
          hint="En viáticos sin cerrar"
          icon={IconCash}
          loading={loading}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Por contabilizar"
          value={dolares(resumen?.montos.por_contabilizar ?? 0)}
          hint={`${resumen?.conteos.por_revisar ?? 0} liquidación(es) por revisar`}
          icon={IconReceipt2}
          loading={loading}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6, lg: 3 }}>
        <StatCard
          label="Liquidaciones vencidas"
          value={vencidas}
          hint={`De ${resumen?.conteos.por_liquidar ?? 0} pendiente(s) de liquidar`}
          icon={IconAlarm}
          tone={vencidas > 0 ? 'danger' : undefined}
          loading={loading}
        />
      </Grid.Col>
    </Grid>
  )
}
