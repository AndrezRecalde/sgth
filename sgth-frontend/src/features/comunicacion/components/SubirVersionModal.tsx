'use client'

import { useState } from 'react'
import { Group, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Dropzone } from '@mantine/dropzone'
import { IconPhoto, IconUpload, IconX } from '@tabler/icons-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm, type Resolver } from 'react-hook-form'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { PESO_MAXIMO_LOGO_MB, REGLAS_VARIANTE } from '../constants/identidad'
import { useMutacionesIdentidad } from '../hooks/useIdentidad'
import { versionIdentidadSchema, type VersionIdentidadFormData } from '../schemas/versionIdentidad.schema'
import type { DatosInstitucionales, EstadoVariante } from '../services/tiposIdentidad'
import { medirImagen, problemaDelLogo } from '../utils/validarLogo'
import { VistaPreviaContexto } from './VistaPreviaContexto'

interface Props {
  variante: EstadoVariante | null
  datos: DatosInstitucionales | undefined
  onClose: () => void
}

const CAMPOS = ['archivo', 'vigente_desde', 'nota'] as const

/**
 * Una versión nueva de una variante del logo (fase 2.3): el archivo, desde
 * cuándo rige —hoy, o una fecha futura para programarla— y una nota. Antes de
 * subir se ve cómo quedaría donde se usa.
 */
export function SubirVersionModal({ variante, datos, onClose }: Props) {
  const contained = useContainedInput()
  const { subir } = useMutacionesIdentidad()
  const [vista, setVista] = useState<{ url: string; problema: string | null } | null>(null)

  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<VersionIdentidadFormData>({
    resolver: zodResolver(versionIdentidadSchema) as Resolver<VersionIdentidadFormData>,
    // `archivo` se omite: arranca sin archivo y el esquema lo exige.
    defaultValues: { vigente_desde: null, nota: '' },
  })

  // La URL de la vista previa se libera al cambiar de archivo o al cerrar, en
  // el manejador y no en un efecto (regla 08).
  const soltarVista = () => {
    if (vista) URL.revokeObjectURL(vista.url)
    setVista(null)
  }

  const cerrar = () => {
    soltarVista()
    reset({ vigente_desde: null, nota: '' })
    onClose()
  }

  const elegir = async (archivo: File | undefined, alCambiar: (f: File) => void) => {
    if (!archivo || !variante) return
    soltarVista()
    alCambiar(archivo)
    const url = URL.createObjectURL(archivo)
    const medidas = await medirImagen(url).catch(() => null)
    setVista({
      url,
      problema: medidas ? problemaDelLogo(variante.variante, medidas, variante.requisito) : 'La imagen no se pudo leer.',
    })
  }

  const guardar = (valores: VersionIdentidadFormData) => {
    if (!variante) return
    subir.mutateAsync({ variante: variante.variante, ...valores })
      .then(cerrar)
      .catch((error) => erroresAlFormulario(error, setError, CAMPOS, null))
  }

  const tipos = variante ? REGLAS_VARIANTE[variante.variante].tipos : []

  return (
    <SgthModal opened={!!variante} onClose={cerrar} title={`Subir ${variante?.etiqueta.toLowerCase() ?? 'logo'}`} size="lg">
      <form id="version-identidad" noValidate onSubmit={handleSubmit(guardar)}>
        <Stack gap="md">
          <Text size="sm" c="dimmed">{variante?.requisito}. Hasta {PESO_MAXIMO_LOGO_MB} MB.</Text>

          <Controller
            name="archivo"
            control={control}
            render={({ field }) => (
              <div>
                <Dropzone
                  onDrop={(archivos) => elegir(archivos[0], field.onChange)}
                  onReject={() => notificar.error('El archivo no sirve', `Se acepta ${tipos.includes('image/jpeg') ? 'PNG o JPG' : 'PNG'} de hasta ${PESO_MAXIMO_LOGO_MB} MB.`)}
                  accept={tipos}
                  maxSize={PESO_MAXIMO_LOGO_MB * 1024 * 1024}
                  multiple={false}
                >
                  <Group justify="center" gap="lg" mih={90}>
                    <Dropzone.Accept><IconUpload size={28} color="var(--sgth-accent)" /></Dropzone.Accept>
                    <Dropzone.Reject><IconX size={28} color="var(--mantine-color-red-6)" /></Dropzone.Reject>
                    <Dropzone.Idle><IconPhoto size={28} color="var(--mantine-color-dimmed)" /></Dropzone.Idle>
                    <Text size="sm" fw={500}>{field.value ? field.value.name : 'Arrastre el archivo aquí o haga clic para elegirlo'}</Text>
                  </Group>
                </Dropzone>
                {errors.archivo && <Text size="xs" c="red" mt={4}>{errors.archivo.message}</Text>}
              </div>
            )}
          />

          {vista && (
            vista.problema
              ? <Text size="sm" c="red">{vista.problema}</Text>
              : variante && <VistaPreviaContexto variante={variante.variante} src={vista.url} datos={datos} />
          )}

          <Controller
            name="vigente_desde"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Rige desde"
                placeholder="Hoy"
                description="Una fecha futura la deja programada: hasta ese día sigue la actual."
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d ?? null))}
                error={errors.vigente_desde?.message}
              />
            )}
          />

          <Textarea label="Nota" placeholder="Ej: logo de la administración 2027–2031" autosize minRows={2}
            {...contained} {...register('nota')} error={errors.nota?.message} />
        </Stack>
      </form>

      <ModalFooter
        onCancel={cerrar}
        form="version-identidad"
        submitLabel="Guardar versión"
        submitting={subir.isPending}
        submitDisabled={!vista || !!vista.problema}
      />
    </SgthModal>
  )
}
