'use client'

import { useRouter } from 'next/navigation'
import { Button, Group, Skeleton } from '@mantine/core'
import { IconStethoscope, IconDownload, IconEdit } from '@tabler/icons-react'
import { useFemoDetalle } from '@/features/dispensario/hooks/useFemo'
import { usePdfFemo } from '@/features/dispensario/hooks/usePdfFemo'
import { FemoResumen } from '@/features/dispensario/components/femo/FemoResumen'
import { APTITUD_OPTIONS, TONO_APTITUD } from '@/features/dispensario/services/femoOptions'
import { EmptyState, PageHeader, PageShell, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'

interface Props {
  id: string
}

/**
 * Una ficha FEMO en solo lectura.
 *
 * Ya no se edita aquí: el detalle tenía su propia copia del asistente, que se
 * había quedado atrás (sin sexo, sin puesto, sin borrador). Un borrador se
 * retoma en el mismo asistente de la bandeja; con el dictamen emitido, la
 * ficha está cerrada.
 */
export function FemoDetalleView({ id }: Props) {
  const femoId = Number(id)
  const router = useRouter()

  const { data: ficha, isLoading, isError } = useFemoDetalle(femoId)
  const { descargarFemo, loading: descargando } = usePdfFemo()

  const volver = () => router.push(ROUTES.SALUD.FEMO)

  if (isLoading) {
    return (
      <PageShell>
        <Skeleton height={60} radius="lg" />
        <Skeleton height={400} radius="lg" />
      </PageShell>
    )
  }

  if (isError || !ficha) {
    return (
      <PageShell>
        <PageHeader title={`Ficha FEMO #${femoId}`} onBack={volver} />
        <EmptyState
          icon={IconStethoscope}
          title="Ficha no encontrada"
          description={`La ficha FEMO #${id} no existe o no está disponible. Vuelva al listado y ábrala desde allí.`}
        />
      </PageShell>
    )
  }

  const persona = ficha.servidor
    ? `${ficha.servidor.nombre} ${ficha.servidor.apellido}`
    : ficha.postulante
      ? `${ficha.postulante.nombres} ${ficha.postulante.apellidos}`
      : undefined
  const aptitud = APTITUD_OPTIONS.find(o => o.value === ficha.aptitud)?.label ?? 'Borrador'
  const solicitudEnCurso = ficha.solicitud?.estado === 'en_proceso' ? ficha.solicitud : null

  return (
    <PageShell>
      <PageHeader
        title={`Ficha FEMO #${femoId}`}
        description={persona}
        onBack={volver}
        estado={
          <StatusBadge tone={TONO_APTITUD[ficha.aptitud ?? ''] ?? 'neutral'}>{aptitud}</StatusBadge>
        }
        actions={
          <Group>
            <Button
              variant="default"
              leftSection={<IconDownload size={14} />}
              loading={descargando}
              onClick={() => descargarFemo(femoId, `femo-${femoId}.pdf`)}
            >
              Descargar PDF
            </Button>
            {solicitudEnCurso && (
              <Button
                leftSection={<IconEdit size={14} />}
                onClick={() => router.push(ROUTES.SALUD.FEMO_NUEVA(solicitudEnCurso.id))}
              >
                Continuar en el asistente
              </Button>
            )}
          </Group>
        }
      />

      <FemoResumen ficha={ficha} />
    </PageShell>
  )
}
