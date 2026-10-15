'use client'

import { Alert, Button, Group, Switch, Text } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconPhoto, IconUpload, IconX } from '@tabler/icons-react'
import { PESO_MAXIMO_FOTO_MB, TIPOS_FOTO } from '../constants/resultadosCarga'

interface Props {
  cantidad: number
  rechazados: string[]
  reemplazar: boolean
  onReemplazar: (valor: boolean) => void
  onAgregar: (archivos: File[], rechazados: string[]) => void
  onQuitarTodas: () => void
}

/** Elegir las fotos del lote: varias a la vez, y se pueden ir sumando. */
export function ElegirFotosLote({ cantidad, rechazados, reemplazar, onReemplazar, onAgregar, onQuitarTodas }: Props) {
  return (
    <>
      <Dropzone
        onDrop={(archivos) => onAgregar(archivos, [])}
        onReject={(rechazadas) => onAgregar([], rechazadas.map((r) => r.file.name))}
        accept={TIPOS_FOTO}
        maxSize={PESO_MAXIMO_FOTO_MB * 1024 * 1024}
        multiple
      >
        <Group justify="center" gap="xl" mih={120}>
          <Dropzone.Accept><IconUpload size={32} color="var(--sgth-accent)" /></Dropzone.Accept>
          <Dropzone.Reject><IconX size={32} color="var(--mantine-color-red-6)" /></Dropzone.Reject>
          <Dropzone.Idle><IconPhoto size={32} color="var(--mantine-color-dimmed)" /></Dropzone.Idle>
          <div>
            <Text size="sm" fw={500}>Arrastre las fotos de la carpeta o haga clic para elegirlas</Text>
            <Text size="xs" c="dimmed" mt={4}>
              Cada archivo con la cédula en el nombre (0801234567.jpg) · JPG, PNG o WebP · hasta {PESO_MAXIMO_FOTO_MB} MB
            </Text>
          </div>
        </Group>
      </Dropzone>

      {cantidad > 0 && (
        <Group justify="space-between">
          <Text size="sm">{cantidad} {cantidad === 1 ? 'foto elegida' : 'fotos elegidas'}</Text>
          <Button variant="subtle" size="xs" onClick={onQuitarTodas}>Quitar todas</Button>
        </Group>
      )}

      {rechazados.length > 0 && (
        <Alert color="red" title={`${rechazados.length} ${rechazados.length === 1 ? 'archivo no se agregó' : 'archivos no se agregaron'}`}>
          No son JPG, PNG o WebP, o pesan más de {PESO_MAXIMO_FOTO_MB} MB: {rechazados.slice(0, 5).join(', ')}
          {rechazados.length > 5 ? ` y ${rechazados.length - 5} más` : ''}.
        </Alert>
      )}

      <Switch
        label="Reemplazar las fotos que ya existen"
        description="Sin esto, a quien ya tiene foto se le deja la suya. La anterior queda en el historial."
        checked={reemplazar}
        onChange={(e) => onReemplazar(e.currentTarget.checked)}
      />
    </>
  )
}
