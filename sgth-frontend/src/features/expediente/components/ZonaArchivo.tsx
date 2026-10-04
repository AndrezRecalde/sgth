'use client'

import { Group, Input, Text } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconFile, IconUpload, IconX } from '@tabler/icons-react'

interface Props {
  value: File | null | undefined
  onChange: (archivo: File | null) => void
  /** El error del formulario, bajo la zona como el de cualquier campo. */
  error?: string
  /** Se avisa cuando el archivo no cumple tipo o tamaño. */
  onRechazo: (mensaje: string) => void
  /** Tipos MIME aceptados: los mismos que valida el backend. */
  accept: string[]
  maxMb: number
  /** Lo que se lee bajo el texto: «PDF, JPG, PNG — máx. 5 MB». */
  formatos: string
}

/**
 * La zona para soltar o elegir un archivo, como campo de un formulario de
 * React Hook Form (regla 07: el archivo va por un `Controller`). Antes el
 * archivo vivía en un `useState` y su error se pintaba a mano.
 */
export function ZonaArchivo({
  value, onChange, error, onRechazo, accept, maxMb, formatos,
}: Props) {
  return (
    <div>
      <Dropzone
        onDrop={(archivos) => onChange(archivos[0] ?? null)}
        onReject={() => onRechazo(`El archivo no sirve. Se acepta: ${formatos}.`)}
        maxSize={maxMb * 1024 * 1024}
        accept={accept}
        multiple={false}
      >
        <Group justify="center" gap="xl" mih={80}>
          <Dropzone.Accept>
            <IconUpload size={28} color="var(--sgth-accent)" />
          </Dropzone.Accept>
          <Dropzone.Reject>
            <IconX size={28} color="var(--mantine-color-red-6)" />
          </Dropzone.Reject>
          <Dropzone.Idle>
            <IconFile size={28} color="var(--mantine-color-dimmed)" />
          </Dropzone.Idle>
          <div>
            {value ? (
              <Text size="sm" fw={500}>{value.name}</Text>
            ) : (
              <>
                <Text size="sm" fw={500}>
                  Arrastre el archivo aquí o haga clic para seleccionarlo
                </Text>
                <Text size="xs" c="dimmed" mt={4}>{formatos}</Text>
              </>
            )}
          </div>
        </Group>
      </Dropzone>
      {error && <Input.Error mt={4}>{error}</Input.Error>}
    </div>
  )
}
