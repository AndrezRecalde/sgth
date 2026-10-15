'use client'

import { Button, Group, Paper, Skeleton, Stack, Text } from '@mantine/core'
import { IconArrowBackUp, IconPhotoOff } from '@tabler/icons-react'
import { EmptyState, SgthDrawer, StatusBadge, confirmar } from '@/components/ui'
import { formatFechaMesHora } from '@/lib/fecha'
import { useHistorialFotografias, useMutacionesFotografia } from '../hooks/useFotografias'
import type { FotografiaServidor, ServidorConFotografia } from '../services/tipos'
import { ImagenFotografia } from './ImagenFotografia'

interface Props {
  servidor: ServidorConFotografia | null
  onClose: () => void
}

/** Quién y cuándo, en una línea: «14 oct 2026 10:32 · Ana Pérez». */
const cuandoYQuien = (fecha: string | null, persona?: { nombre: string } | null) =>
  [formatFechaMesHora(fecha), persona?.nombre].filter(Boolean).join(' · ')

/**
 * Si volvió a ser la vigente después de subirse. Al subirla, el backend fija
 * las dos fechas en el mismo instante, con unos milisegundos de diferencia.
 */
const fueRestaurada = (foto: FotografiaServidor) =>
  !!foto.activada_en && !!foto.subida_en
  && Math.abs(Date.parse(foto.activada_en) - Date.parse(foto.subida_en)) > 5000

/**
 * Todas las fotos de un servidor (fase 1.2). Ninguna se borra: la vigente se
 * puede retirar y cualquiera anterior, restaurar.
 */
export function HistorialFotografiasDrawer({ servidor, onClose }: Props) {
  const { data: fotos = [], isLoading } = useHistorialFotografias(servidor?.id ?? null)
  const { restaurar, retirar } = useMutacionesFotografia()

  const pedirRetiro = (foto: FotografiaServidor) => confirmar({
    title: 'Retirar la foto vigente',
    message: <>{servidor?.nombre_completo} quedará <b>sin foto</b>. El archivo sigue en el historial y se puede restaurar.</>,
    confirmLabel: 'Retirar la foto',
    destructiva: true,
    onConfirm: () => retirar.mutate(foto.servidor_id),
  })

  return (
    <SgthDrawer opened={!!servidor} onClose={onClose} title="Historial de fotos" description={servidor?.nombre_completo} ancho="sm">
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={140} radius="md" />
          <Skeleton height={140} radius="md" />
        </Stack>
      ) : fotos.length === 0 ? (
        <EmptyState icon={IconPhotoOff} title="Sin fotos" description="Todavía no se ha subido ninguna foto de este servidor." />
      ) : (
        <Stack gap="sm">
          {fotos.map((foto) => (
            <Paper key={foto.id} withBorder p="sm" radius="md">
              <Group align="flex-start" wrap="nowrap" gap="md">
                <ImagenFotografia
                  fotografiaId={foto.id}
                  ancho={96}
                  alt={`Foto de ${servidor?.nombre_completo ?? 'el servidor'}, subida el ${formatFechaMesHora(foto.subida_en)}`}
                />
                <Stack gap={6} style={{ flex: 1, minWidth: 0 }}>
                  {foto.vigente && <StatusBadge tone="success" size="xs">Vigente</StatusBadge>}
                  <div>
                    <Text size="xs" c="dimmed">{foto.origen_etiqueta}</Text>
                    <Text size="sm">{cuandoYQuien(foto.subida_en, foto.subida_por)}</Text>
                  </div>
                  {foto.vigente && fueRestaurada(foto) && (
                    <div>
                      <Text size="xs" c="dimmed">Restaurada</Text>
                      <Text size="sm">{cuandoYQuien(foto.activada_en, foto.activada_por)}</Text>
                    </div>
                  )}
                  <Group gap="xs" mt={4}>
                    {foto.vigente ? (
                      <Button size="xs" variant="light" color="red" loading={retirar.isPending} onClick={() => pedirRetiro(foto)}>
                        Retirar
                      </Button>
                    ) : (
                      <Button size="xs" variant="light" leftSection={<IconArrowBackUp size={14} />}
                        loading={restaurar.isPending && restaurar.variables === foto.id}
                        onClick={() => restaurar.mutate(foto.id)}>
                        Restaurar
                      </Button>
                    )}
                  </Group>
                </Stack>
              </Group>
            </Paper>
          ))}
        </Stack>
      )}
    </SgthDrawer>
  )
}
