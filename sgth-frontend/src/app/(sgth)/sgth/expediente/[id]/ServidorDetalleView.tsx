'use client'

import { Button, Group, Skeleton, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import {
  IconCertificate,
  IconEdit,
} from '@tabler/icons-react'
import {
  PageHeader,
  PageShell,
  StatusBadge,
} from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useServidor } from '@/features/expediente/hooks/useServidor'
import { ServidorEncabezado, nombreCompletoDe }
  from '@/features/expediente/components/ServidorEncabezado'
import { ServidorEditarModal }
  from '@/features/expediente/components/ServidorEditarModal'
import { ExpedienteNoDisponible }
  from '@/features/expediente/components/ExpedienteNoDisponible'
import { CertificadoLaboralModal }
  from '@/features/expediente/components/CertificadoLaboralModal'
import { ExpedienteTabs } from '@/features/expediente/components/ExpedienteTabs'
import { situacionDe } from '@/features/expediente/utils/situacion'

interface Props {
  id: string
}

/**
 * El expediente de un servidor, en su propia página.
 *
 * Antes vivía en dos paneles laterales distintos —la ficha por un lado y sus
 * acciones de personal por otro—, así que la mitad del expediente no se veía
 * sin volver al listado, y nada de esto se podía enlazar ni recargar.
 *
 * La URL lleva el id y no la cédula: la cédula se corrige cuando viene con
 * una errata (es editable por Talento Humano), así que un enlace guardado
 * dejaría de funcionar, y además es un dato personal que quedaría en el
 * historial del navegador y en los registros del servidor.
 */
export function ServidorDetalleView({ id }: Props) {
  const router = useRouter()
  const servidorId = Number(id)
  const { data: servidor, isLoading, error, refetch } = useServidor(servidorId)
  const [editarOpened, { open: abrirEditar, close: cerrarEditar }] = useDisclosure(false)
  const [certificadoOpened, { open: abrirCertificado, close: cerrarCertificado }] =
    useDisclosure(false)

  if (isLoading) {
    return (
      <PageShell>
        <Skeleton height={60} radius="lg" />
        <Skeleton height={120} radius="lg" />
        <Skeleton height={320} radius="lg" />
      </PageShell>
    )
  }

  if (!servidor) {
    return (
      <PageShell>
        <PageHeader
          title="Expediente del servidor"
          backHref={ROUTES.SGTH.EXPEDIENTE}
        />
        <ExpedienteNoDisponible
          error={error}
          onRetry={() => refetch()}
          onIrAlListado={() => router.push(ROUTES.SGTH.EXPEDIENTE)}
        />
      </PageShell>
    )
  }

  const situacion = situacionDe(servidor)

  return (
    <PageShell>
      <PageHeader
        title={nombreCompletoDe(servidor)}
        description={`Cédula ${servidor.cedula ?? '—'}`}
        estado={<StatusBadge tone={situacion.tone}>{situacion.texto}</StatusBadge>}
        // Al expediente se llega desde el listado, desde la bandeja de
        // acciones y desde otros módulos: se vuelve a donde se estaba.
        onBack={() => router.back()}
        actions={
          <Group gap="xs" wrap="nowrap">
            {/* Lo emite Talento Humano, nunca el interesado: el endpoint está
                cerrado a admin-uath y asistente-uath. */}
            <Button
              variant="default"
              leftSection={<IconCertificate size={16} />}
              onClick={abrirCertificado}
            >
              Certificado
            </Button>
            <Button
              variant="light"
              leftSection={<IconEdit size={16} />}
              onClick={abrirEditar}
            >
              Editar datos
            </Button>
          </Group>
        }
      />

      <Stack gap="md">
        <ServidorEncabezado servidor={servidor} />

        <ExpedienteTabs servidor={servidor} />
      </Stack>

      <ServidorEditarModal
        // Por versión: tras guardar, el formulario parte de lo guardado.
        key={`${servidor.id}-${servidor.updated_at ?? ''}`}
        opened={editarOpened}
        onClose={cerrarEditar}
        servidor={servidor}
      />

      <CertificadoLaboralModal
        opened={certificadoOpened}
        onClose={cerrarCertificado}
        servidor={servidor}
      />
    </PageShell>
  )
}
