'use client'

import { useState } from 'react'
import { Button, Group, Image, Skeleton, Stack, Text } from '@mantine/core'
import { IconPhoto, IconPhotoX } from '@tabler/icons-react'
import { confirmar, SectionCard } from '@/components/ui'
import { useArchivosComunicado } from '../hooks/useArchivosComunicado'
import { usePortadaComunicado } from '../hooks/useComunicados'
import type { Comunicado } from '../services/tipos'
import { RecortarPortadaModal } from './RecortarPortadaModal'
import classes from './ComunicadoContenido.module.css'

interface Props {
  comunicado: Comunicado
}

/** La portada de un borrador: subirla, cambiarla o quitarla (regla 6, D10). */
export function PortadaComunicado({ comunicado }: Props) {
  const [recortando, setRecortando] = useState(false)
  const { data: src, isLoading } = usePortadaComunicado(comunicado)
  const { quitarPortada } = useArchivosComunicado(comunicado.id)

  const quitar = () => confirmar({
    title: 'Quitar la portada',
    message: 'La tarjeta del Portal mostrará el icono del tipo en su lugar.',
    confirmLabel: 'Quitar',
    destructiva: true,
    onConfirm: () => quitarPortada.mutate(),
  })

  return (
    <SectionCard
      title="Portada"
      description="Opcional. Se recorta a 16:9, como sale en la tarjeta del Portal."
      actions={
        <Group gap="xs">
          {comunicado.tiene_portada && (
            <Button variant="subtle" color="red" leftSection={<IconPhotoX size={16} />} onClick={quitar} loading={quitarPortada.isPending}>
              Quitar
            </Button>
          )}
          <Button variant="light" leftSection={<IconPhoto size={16} />} onClick={() => setRecortando(true)}>
            {comunicado.tiene_portada ? 'Cambiar portada' : 'Subir portada'}
          </Button>
        </Group>
      }
    >
      {comunicado.tiene_portada ? (
        isLoading ? <Skeleton className={classes.portada} /> : src && <Image src={src} alt="Portada actual" className={classes.portada} />
      ) : (
        <Stack gap={4} align="center" py="lg">
          <IconPhoto size={28} color="var(--mantine-color-dimmed)" />
          <Text size="sm" c="dimmed">Sin portada</Text>
        </Stack>
      )}

      <RecortarPortadaModal comunicadoId={comunicado.id} opened={recortando} onClose={() => setRecortando(false)} />
    </SectionCard>
  )
}
