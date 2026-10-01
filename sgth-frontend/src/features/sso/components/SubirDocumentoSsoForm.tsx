'use client'

import { Button, Group, Input, Text, TextInput } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconFile, IconUpload, IconX } from '@tabler/icons-react'
import { Controller, useForm, useWatch, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import {
  documentoSsoSchema, MAX_BYTES_DOCUMENTO_SSO, MIMES_DOCUMENTO_SSO,
  type DocumentoSsoFormData,
} from '../schemas/documentoSso.schema'

interface Props {
  /** La mutación del panel, para no duplicar la invalidación de caché. */
  subir: {
    mutateAsync: (datos: { nombre: string; archivo: File }) => Promise<unknown>
    isPending: boolean
  }
}

const VALORES_INICIALES = { nombre: '' } as const

/**
 * Adjuntar un documento de respaldo a un registro SSO.
 *
 * Estaba con `useState`: dos estados para los campos y dos más para sus
 * errores, con las reglas del backend —tipo, 10 MB, 255 caracteres— escritas
 * solo allí. Subir un PDF de 12 MB costaba el viaje entero para recibir un
 * 422.
 *
 * Con esto, los formularios de CAPTURA del módulo están todos en React Hook
 * Form (los dos cuestionarios públicos, en #251 y #252). Los `useState` que
 * quedan son de otra cosa: paginación, fila seleccionada, apertura de modal y
 * barras de filtro.
 *
 * Sale del panel a su propio archivo porque son dos cosas distintas: una lista
 * de adjuntos y un formulario que captura. El panel se quedó con la lista.
 */
export function SubirDocumentoSsoForm({ subir }: Props) {
  const contained = useContainedInput()

  const {
    register, control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<DocumentoSsoFormData>({
    resolver: zodResolver(documentoSsoSchema) as Resolver<DocumentoSsoFormData>,
    // `archivo` se omite a propósito: su valor inicial es «no hay», y el
    // esquema lo exige. Declararlo como `undefined as never` es justo lo que
    // la regla 09 prohíbe.
    defaultValues: VALORES_INICIALES,
  })

  const archivo = useWatch({ control, name: 'archivo' })

  const guardar = (valores: DocumentoSsoFormData) => {
    subir.mutateAsync({ nombre: valores.nombre, archivo: valores.archivo })
      .then(() => reset(VALORES_INICIALES))
      .catch((error) => {
        const campos = erroresDeCampo(error)
        if (! campos) return // el hook ya lo notificó
        if (campos.nombre) setError('nombre', { message: campos.nombre })
        // `documentable_*` son del registro al que se adjunta, no del archivo,
        // pero quien sube solo ve este formulario: su sitio menos malo es el
        // campo del archivo.
        const delArchivo = campos.archivo ?? campos.documentable_type ?? campos.documentable_id
        if (delArchivo) setError('archivo', { message: delArchivo })
      })
  }

  // En un `<div>` y NO en un `<form>`: este panel se monta DENTRO del
  // formulario del modal que lo contiene —el de registrar cumplimiento y el de
  // seguimiento del programa—, y HTML no admite formularios anidados. El
  // navegador lo aplana, y el botón acababa haciendo un envío nativo GET que
  // navegaba la página a `?nombre=`. Comprobado en pantalla.
  //
  // Sin `<form>` no hay evento submit, así que el botón es `type="button"` y
  // llama a `handleSubmit` él mismo. La validación y el reparto de errores son
  // los mismos.
  return (
    <div>
      <TextInput
        label="Nombre del documento"
        placeholder="Ej: Acta de socialización, evidencia fotográfica..."
        {...contained}
        {...register('nombre')}
        error={errors.nombre?.message}
      />

      <Controller
        name="archivo"
        control={control}
        render={({ field }) => (
          // `Input.Wrapper` y no la zona a secas: el campo necesita etiqueta
          // visible como cualquier otro, y es donde Mantine pinta su error.
          <Input.Wrapper
            label="Archivo"
            required
            error={errors.archivo?.message}
            mt="sm"
          >
            <Dropzone
              mt={4}
              onDrop={(archivos) => field.onChange(archivos[0])}
              // El motivo, y no «Archivo no válido»: la zona rechaza por tipo
              // y por tamaño, y sin decir cuál se prueba a ciegas.
              onReject={() => setError('archivo', {
                message: 'Solo se admiten PDF, DOC, DOCX, JPG o PNG, y hasta 10 MB',
              })}
              maxSize={MAX_BYTES_DOCUMENTO_SSO}
              accept={MIMES_DOCUMENTO_SSO}
            >
              <Group justify="center" gap="md" mih={60}>
                <Dropzone.Accept>
                  <IconUpload size={22} color="var(--mantine-color-emerald-6)" />
                </Dropzone.Accept>
                <Dropzone.Reject>
                  <IconX size={22} color="var(--mantine-color-red-6)" />
                </Dropzone.Reject>
                <Dropzone.Idle>
                  <IconFile size={22} color="var(--mantine-color-dimmed)" />
                </Dropzone.Idle>
                <Text size="xs" c={archivo ? 'emerald' : 'dimmed'}>
                  {archivo
                    ? archivo.name
                    : 'Arrastre el archivo aquí o haga clic (PDF, DOC, JPG, PNG — máx. 10 MB)'}
                </Text>
              </Group>
            </Dropzone>
          </Input.Wrapper>
        )}
      />

      <Button
        type="button"
        onClick={handleSubmit(guardar)}
        size="xs"
        variant="light"
        mt="sm"
        leftSection={<IconUpload size={14} />}
        loading={subir.isPending}
      >
        Subir documento
      </Button>
    </div>
  )
}
