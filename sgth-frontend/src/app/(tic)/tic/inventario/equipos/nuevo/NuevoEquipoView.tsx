'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Anchor, Group, Skeleton, Text } from '@mantine/core'
import { IconCopy, IconScan } from '@tabler/icons-react'
import { PageHeader, PageShell, SectionCard, notificar } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { BienForm } from '@/features/inventario-ti/components/BienForm'
import { clavesInventario } from '@/features/inventario-ti/constants/claves'
import { useBien } from '@/features/inventario-ti/hooks/useBienes'
import { constatacionesService } from '@/features/inventario-ti/services/constatacionesService'
import type { BienInformatico } from '@/features/inventario-ti/services/tipos'

/** El alta en sitio desde una constatación (5.2): el código leído y la ubicación que se recorre. */
export type AltaEnSitio = { constatacionId: number; ubicacionId: number; codigo: string }

interface Props {
  copiarId: number | null
  enSitio: AltaEnSitio | null
}

/**
 * Registrar un equipo. Con `copiar`, el formulario arranca con lo de otro
 * equipo; con «Registrar y agregar otro», se queda con lo común del lote y
 * lleva la cuenta de la tanda. Desde una constatación, arranca con el código
 * leído y la ubicación, y al guardar el equipo queda escaneado en ella.
 */
export function NuevoEquipoView({ copiarId, enSitio }: Props) {
  const router = useRouter()
  const qc = useQueryClient()
  const plantilla = useBien(copiarId ?? 0)
  const [tanda, setTanda] = useState<BienInformatico[]>([])
  const esperando = copiarId !== null && plantilla.isLoading
  const volver = enSitio ? ROUTES.TIC.CONSTATACION(enSitio.constatacionId) : ROUTES.TIC.EQUIPOS

  const guardado = (bien: BienInformatico) => {
    if (!enSitio) {
      router.push(ROUTES.TIC.EQUIPO(bien.id))
      return
    }
    constatacionesService.escanear(enSitio.constatacionId, bien.codigo_institucional)
      .catch(() => notificar.error('El equipo se registró', 'Pero no quedó en la constatación: escanéelo de nuevo.'))
      .finally(() => {
        // Sin esto, la constatación se vería como antes del alta.
        qc.invalidateQueries({ queryKey: clavesInventario.constataciones.una(enSitio.constatacionId) })
        router.push(volver)
      })
  }

  return (
    <PageShell>
      <PageHeader
        title="Registrar equipo"
        description="El código institucional es el que asignó Activos Fijos. La custodia se registra después, desde la ficha."
        backHref={volver}
      />

      {enSitio && (
        <Alert color={SEMANTIC_COLOR.info} variant="light" icon={<IconScan size={16} />}>
          Alta en sitio durante la constatación: al guardar, el equipo queda en esta ubicación y escaneado.
        </Alert>
      )}
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
          iniciales={enSitio ? { codigo_institucional: enSitio.codigo, ubicacion_id: enSitio.ubicacionId } : undefined}
          onGuardado={guardado}
          onGuardadoYOtro={enSitio ? undefined : (bien) => setTanda((t) => [...t, bien])}
          onCancelar={() => router.push(volver)}
        />
      )}
    </PageShell>
  )
}
