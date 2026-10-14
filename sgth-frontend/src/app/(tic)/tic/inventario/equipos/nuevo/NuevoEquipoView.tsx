'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Alert, Anchor, Group, Skeleton, Text } from '@mantine/core'
import { IconCopy } from '@tabler/icons-react'
import { PageHeader, PageShell, SectionCard } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { BienForm } from '@/features/inventario-ti/components/BienForm'
import { useBien } from '@/features/inventario-ti/hooks/useBienes'
import type { BienInformatico } from '@/features/inventario-ti/services/tipos'

/**
 * Registrar un equipo. Con `copiar`, el formulario arranca con lo de otro
 * equipo (tipo, marca, modelo, ubicación, características); con «Registrar y
 * agregar otro», se queda con lo común del lote para el siguiente, y abajo se
 * lleva la cuenta de los que se registraron en la tanda.
 */
export function NuevoEquipoView({ copiarId }: { copiarId: number | null }) {
  const router = useRouter()
  const plantilla = useBien(copiarId ?? 0)
  const [tanda, setTanda] = useState<BienInformatico[]>([])
  const esperando = copiarId !== null && plantilla.isLoading

  return (
    <PageShell>
      <PageHeader
        title="Registrar equipo"
        description="El código institucional es el que asignó Activos Fijos. La custodia se registra después, desde la ficha."
        backHref={ROUTES.TIC.EQUIPOS}
      />

      {plantilla.data && (
        <Alert color={SEMANTIC_COLOR.info} variant="light" icon={<IconCopy size={16} />}>
          Copiado de <b>{plantilla.data.codigo_institucional}</b>: escriba el código y la serie del equipo nuevo.
        </Alert>
      )}

      {tanda.length > 0 && (
        <SectionCard title={`Registrados en esta tanda (${tanda.length})`}>
          <Group gap="md">
            {tanda.map((b) => (
              <Anchor key={b.id} size="sm" component={Link} href={ROUTES.TIC.EQUIPO(b.id)}>{b.codigo_institucional}</Anchor>
            ))}
          </Group>
          <Text size="xs" c="dimmed" mt="xs">El formulario conserva tipo, marca, modelo, ubicación y características para el siguiente.</Text>
        </SectionCard>
      )}

      {esperando ? (
        <Skeleton height={420} radius="lg" />
      ) : (
        <BienForm
          bien={null}
          plantilla={plantilla.data ?? null}
          onGuardado={(bien) => router.push(ROUTES.TIC.EQUIPO(bien.id))}
          onGuardadoYOtro={(bien) => setTanda((t) => [...t, bien])}
          onCancelar={() => router.push(ROUTES.TIC.EQUIPOS)}
        />
      )}
    </PageShell>
  )
}
