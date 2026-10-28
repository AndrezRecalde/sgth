'use client'

import Link from 'next/link'
import { Alert, Anchor } from '@mantine/core'
import { IconSignature } from '@tabler/icons-react'
import { ROUTES } from '@/config/routes'
import { useTieneFicha } from '@/hooks/useTieneFicha'
import { useMisDocumentos } from '../hooks/useMisDocumentos'

/**
 * En la portada del portal, lo que espera su acuse de recibo (fase 6.3a): una
 * acción notificada por el portal se ve aquí aunque el correo no llegue. Sin
 * pendientes, no se muestra nada.
 */
export function DocumentosPorAcusarAviso() {
  // Sin ficha no hay documentos y el API responde 422: la portada ya no lo
  // monta, pero el aviso no depende de que quien lo use se acuerde.
  const pendientes = (useMisDocumentos(useTieneFicha()).data ?? []).filter((d) => d.puede_acusar).length

  if (!pendientes) return null

  return (
    <Alert variant="light" color="amber" icon={<IconSignature size={16} />}>
      {pendientes === 1
        ? 'Talento Humano le notificó un documento que espera su acuse de recibo. '
        : `Talento Humano le notificó ${pendientes} documentos que esperan su acuse de recibo. `}
      <Anchor component={Link} href={ROUTES.PORTAL.DOCUMENTOS} size="sm">
        Ver mis documentos laborales
      </Anchor>
    </Alert>
  )
}
