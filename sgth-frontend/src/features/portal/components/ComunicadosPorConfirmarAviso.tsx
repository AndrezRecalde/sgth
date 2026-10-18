'use client'

import Link from 'next/link'
import { Alert, Anchor } from '@mantine/core'
import { IconSignature } from '@tabler/icons-react'
import { ROUTES } from '@/config/routes'
import { useResumenComunicados } from '@/features/comunicados/hooks/useMisComunicados'

/**
 * En la portada del Portal, los comunicados que esperan que confirme haberlos
 * leído (docs/diseno-noticias-comunicados.md, 5.1), como el aviso de los
 * documentos laborales. Sin pendientes, no se muestra nada.
 */
export function ComunicadosPorConfirmarAviso() {
  const pendientes = useResumenComunicados().data?.por_confirmar ?? 0

  if (!pendientes) return null

  return (
    <Alert variant="light" color="amber" icon={<IconSignature size={16} />}>
      {pendientes === 1
        ? 'Talento Humano le pide confirmar la lectura de un comunicado. '
        : `Talento Humano le pide confirmar la lectura de ${pendientes} comunicados. `}
      <Anchor component={Link} href={ROUTES.PORTAL.COMUNICADOS_POR_CONFIRMAR} size="sm">
        Ver los comunicados por confirmar
      </Anchor>
    </Alert>
  )
}
