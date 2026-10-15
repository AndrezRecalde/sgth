'use client'

import Link from 'next/link'
import { Button, Stack } from '@mantine/core'
import { IconCamera, IconId, IconPalette, IconSpeakerphone } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SectionHeading } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { TableroCredenciales } from '@/features/comunicacion/components/TableroCredenciales'
import { TableroFotografias } from '@/features/comunicacion/components/TableroFotografias'
import { TableroIdentidad } from '@/features/comunicacion/components/TableroIdentidad'
import { useTableroComunicacion } from '@/features/comunicacion/hooks/useTableroComunicacion'

function Enlace({ href, children }: { href: string; children: string }) {
  return <Button variant="subtle" size="xs" component={Link} href={href}>{children}</Button>
}

/**
 * Inicio de Comunicación Social (docs/diseno-comunicacion-social.md, 8; fase
 * 3.3): cuánto falta. Las fotos por unidad, las credenciales por imprimir,
 * reimprimir, vencer y recibir, y los logos. Cada bloque con su permiso:
 * Talento Humano y Auditoría ven fotos y credenciales; la persona designada
 * para los logos, solo los logos.
 */
export function ComunicacionHomeView() {
  const { data, isLoading, error } = useTableroComunicacion()
  const vacio = !!data && !data.fotografias && !data.credenciales && !data.identidad

  return (
    <PageShell>
      <PageHeader
        title="Comunicación Social"
        description="Fotografías de los servidores, credenciales e identidad institucional"
      />
      <DataState
        loading={isLoading}
        error={error}
        empty={vacio}
        emptyProps={{
          icon: IconSpeakerphone,
          title: 'Sin nada que mostrar',
          description: 'Su rol no incluye las fotografías, las credenciales ni los logos.',
        }}
      >
        <Stack gap="xl">
          {data?.credenciales && (
            <Stack gap="md">
              <SectionHeading title="Credenciales" icon={<IconId size={18} />}
                action={<Enlace href={ROUTES.COMUNICACION.CREDENCIALES}>Ir a Credenciales</Enlace>} />
              <TableroCredenciales datos={data.credenciales} />
            </Stack>
          )}
          {data?.fotografias && (
            <Stack gap="md">
              <SectionHeading title="Fotografías" icon={<IconCamera size={18} />}
                action={<Enlace href={ROUTES.COMUNICACION.FOTOGRAFIAS}>Ir a Fotografías</Enlace>} />
              <TableroFotografias datos={data.fotografias} />
            </Stack>
          )}
          {data?.identidad && (
            <Stack gap="md">
              <SectionHeading title="Identidad institucional" icon={<IconPalette size={18} />}
                action={<Enlace href={ROUTES.COMUNICACION.IDENTIDAD}>Ir a Identidad</Enlace>} />
              <TableroIdentidad datos={data.identidad} />
            </Stack>
          )}
        </Stack>
      </DataState>
    </PageShell>
  )
}
