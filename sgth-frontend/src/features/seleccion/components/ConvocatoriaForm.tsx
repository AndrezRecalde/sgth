'use client'

import { Button, Group, Stack, Textarea, TextInput } from '@mantine/core'
import { IconArrowLeft, IconDeviceFloppy } from '@tabler/icons-react'
import { Controller, useForm, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { convocatoriaSchema, type ConvocatoriaFormData } from '../schemas/convocatoria.schema'
import { ConvocatoriaProcesoCampos } from './ConvocatoriaProcesoCampos'

interface Props {
  /** Sin valores, el formulario es de una convocatoria nueva. */
  valoresIniciales?: ConvocatoriaFormData
  /** Recibe `setError` para devolver los 422 del backend a su campo. */
  onSubmit:   (valores: ConvocatoriaFormData, setError: UseFormSetError<ConvocatoriaFormData>) => void
  enviando:   boolean
  textoEnviar: string
  onCancelar: () => void
}

const VACIO: Partial<ConvocatoriaFormData> = {
  titulo: '', descripcion: '', tipo: 'externa', vacantes: 1,
}

/**
 * El formulario de una convocatoria formal, el mismo para crearla y para
 * editarla en borrador (2026-10-05). Antes solo existía el de crear, y
 * «Editar» llevaba a una página que no existía.
 */
export function ConvocatoriaForm({ valoresIniciales, onSubmit, enviando, textoEnviar, onCancelar }: Props) {
  const contained = useContainedInput()

  const {
    control, register, handleSubmit, setError,
    formState: { errors },
  } = useForm<ConvocatoriaFormData>({
    resolver: zodResolver(convocatoriaSchema),
    defaultValues: valoresIniciales ?? VACIO,
  })

  return (
    <form onSubmit={handleSubmit((valores) => onSubmit(valores, setError))} noValidate>
      <Stack gap="md">
        <SectionCard title="Puesto a convocar">
          <Stack gap="md">
            <Controller
              name="puesto_id"
              control={control}
              render={({ field }) => (
                <BuscarPuestoSelect
                  label="Puesto del organigrama"
                  description="Busque el puesto por nombre del cargo o unidad administrativa"
                  required
                  value={field.value ?? null}
                  onChange={(id) => field.onChange(id)}
                  error={errors.puesto_id?.message}
                />
              )}
            />

            <TextInput
              label="Título de la convocatoria"
              placeholder="Ej: Concurso de méritos y oposición — Técnico en Sistemas SP3"
              description="Nombre oficial del proceso que aparecerá en la convocatoria pública"
              required
              {...contained}
              {...register('titulo')}
              error={errors.titulo?.message}
            />

            <Textarea
              label="Descripción del proceso"
              placeholder="Describa el proceso, los requisitos generales y el perfil del cargo"
              required
              autosize
              minRows={3}
              {...contained}
              {...register('descripcion')}
              error={errors.descripcion?.message}
            />
          </Stack>
        </SectionCard>

        <SectionCard title="Configuración del proceso">
          <Stack gap="md">
            <ConvocatoriaProcesoCampos control={control} errors={errors} />
          </Stack>
        </SectionCard>

        <Group justify="space-between">
          <Button variant="default" leftSection={<IconArrowLeft size={14} />} onClick={onCancelar}>
            Cancelar
          </Button>
          <Button type="submit" leftSection={<IconDeviceFloppy size={14} />} loading={enviando}>
            {textoEnviar}
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
