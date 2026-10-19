'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Button, Group, Text } from '@mantine/core'
import { IconUsers } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import type { SolicitudCargaFamiliar } from '@/types/api'
import { useCargasPorValidar, useValidacionCargas } from '../hooks/useValidacionCargas'
import { getCargasPorValidarColumns } from './cargasPorValidar.columns'
import { RechazarSolicitudCargaModal } from './RechazarSolicitudCargaModal'
import { ValidarSolicitudCargaModal } from './ValidarSolicitudCargaModal'

interface Props {
  /** Solo las de un servidor: el enlace del aviso del expediente. */
  servidorId: number | null
}

/**
 * «Cargas por validar» (docs/diseno-cargas-familiares-portal.md, 6.2): lo que
 * los servidores piden desde «Mi perfil» sobre sus cargas familiares, de lo
 * más viejo a lo más nuevo. Validar aplica el cambio en el expediente;
 * rechazar deja el motivo para el servidor.
 */
export function CargasPorValidarView({ servidorId }: Props) {
  const router = useRouter()
  const { data: solicitudes = [], isLoading, error, refetch } = useCargasPorValidar(servidorId)
  const { verDocumento } = useValidacionCargas()
  const [validando, setValidando] = useState<SolicitudCargaFamiliar | null>(null)
  const [rechazando, setRechazando] = useState<SolicitudCargaFamiliar | null>(null)

  const columns = getCargasPorValidarColumns({
    onDocumento:  (s) => verDocumento.mutate(s),
    onValidar:    setValidando,
    onRechazar:   setRechazando,
    onExpediente: (s) => s.servidor && router.push(ROUTES.SGTH.EXPEDIENTE_SERVIDOR(s.servidor.id)),
  })

  const servidor = servidorId ? solicitudes[0]?.servidor : null

  return (
    <PageShell>
      <PageHeader
        title="Cargas por validar"
        description="Los familiares que los servidores registran, corrigen o dan de baja desde su perfil. No cuentan hasta que Talento Humano los valida."
      />

      {servidorId && (
        <Group gap="sm">
          <Text size="sm" c="dimmed">
            {servidor
              ? `Solo las de ${[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}.`
              : 'Solo las de un servidor.'}
          </Text>
          <Button component={Link} href={ROUTES.SGTH.CARGAS_POR_VALIDAR} size="xs" variant="subtle">
            Ver todas
          </Button>
        </Group>
      )}

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        errorTitle="No se pudieron cargar las cargas por validar"
        onRetry={() => void refetch()}
        emptyProps={{
          icon: IconUsers,
          title: 'No hay cargas por validar',
          description: 'Cuando un servidor registre, corrija o dé de baja a un familiar desde su perfil, aparecerá aquí.',
        }}
      >
        <SgthTable records={solicitudes} columns={columns} minHeight={200} />
      </DataState>

      <ValidarSolicitudCargaModal solicitud={validando} onClose={() => setValidando(null)} />
      <RechazarSolicitudCargaModal solicitud={rechazando} onClose={() => setRechazando(null)} />
    </PageShell>
  )
}
