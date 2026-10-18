'use client'

import { Anchor, Stack, Text } from '@mantine/core'
import Link from 'next/link'
import { DetailList, SectionCard, StatusBadge, type DetailItem } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFechaMesHora } from '@/lib/fecha'
import { TONO_VIGENCIA } from '../constants/comunicados'
import type { Comunicado, PersonaQueActuo } from '../services/tipos'
import { rangoVigencia, resumenDestinatarios } from '../utils/presentacion'

const quienYCuando = (persona: PersonaQueActuo | null | undefined, cuando: string | null) =>
  persona || cuando ? `${persona?.nombre ?? '—'} · ${formatFechaMesHora(cuando)}` : null

/** La ficha de una publicación junto a su vista previa: cómo sale, a quién y quién la movió. */
export function ComunicadoResumen({ comunicado: c }: { comunicado: Comunicado }) {
  const publicacion: DetailItem[] = [
    { label: 'Estado', value: <StatusBadge tone={TONO_VIGENCIA[c.vigencia]}>{c.vigencia_etiqueta}</StatusBadge> },
    { label: 'Tipo', value: c.tipo_etiqueta },
    { label: 'Destinatarios', value: `${resumenDestinatarios(c)} · ${c.total_destinatarios ?? 0} hoy`, ancho: true },
    { label: 'Vigencia', value: rangoVigencia(c), ancho: true },
    // Una retirada conserva la marca guardada, pero ya no está fijada en ningún sitio.
    ...(c.estado !== 'retirado' ? [{ label: 'Fijada', value: c.fijado ? 'Sí' : 'No' }] : []),
    { label: 'Confirmación de lectura', value: c.requiere_confirmacion ? 'Se pide' : 'No se pide' },
    ...(c.estado !== 'borrador'
      ? [{
          label: 'Lecturas',
          value: `${c.leidos ?? 0} de ${c.total_destinatarios ?? 0} leídos`
            + (c.requiere_confirmacion ? ` · ${c.confirmados ?? 0} confirmados` : ''),
          ancho: true,
        }]
      : []),
    {
      label: c.resumen ? 'En la tarjeta del Portal (resumen)' : 'En la tarjeta del Portal (comienzo del texto)',
      value: c.extracto,
      ancho: true,
    },
  ]

  const historial: DetailItem[] = [
    { label: 'Redactado', value: quienYCuando(c.creado_por, c.creado_en), ancho: true },
    { label: 'Publicado', value: quienYCuando(c.publicado_por, c.publicado_at), ancho: true },
    ...(c.estado === 'retirado'
      ? [
          { label: 'Retirado', value: quienYCuando(c.retirado_por, c.retirado_at), ancho: true },
          { label: 'Motivo del retiro', value: c.motivo_retiro, ancho: true },
        ]
      : []),
  ]

  return (
    <Stack gap="md">
      <SectionCard title="Publicación">
        <DetailList items={publicacion} columnas={1} />
      </SectionCard>
      <SectionCard title="Historial">
        <Stack gap="sm">
          <DetailList items={historial} columnas={1} />
          {c.duplicado_de_id && (
            <Text size="sm">
              Es copia de{' '}
              <Anchor component={Link} href={ROUTES.SGTH.COMUNICADO(c.duplicado_de_id)} size="sm">
                otra publicación
              </Anchor>.
            </Text>
          )}
        </Stack>
      </SectionCard>
    </Stack>
  )
}
