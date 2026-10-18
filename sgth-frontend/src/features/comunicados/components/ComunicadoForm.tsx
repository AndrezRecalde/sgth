'use client'

import { Button, Grid, Group, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { IconArrowLeft, IconDeviceFloppy } from '@tabler/icons-react'
import { Controller, useForm, useWatch, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { RichTextInput, SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { OPCIONES_TIPO } from '../constants/comunicados'
import { comunicadoSchema, type ComunicadoFormData } from '../schemas/comunicado.schema'
import { COMUNICADO_VACIO } from '../utils/formulario'
import { DestinatariosCampos } from './DestinatariosCampos'
import { PublicacionCampos } from './PublicacionCampos'

interface Props {
  /** Sin valores, el formulario es de un borrador nuevo. */
  valoresIniciales?: ComunicadoFormData
  /** Recibe `setError` para devolver los 422 del backend a su campo. */
  onSubmit: (valores: ComunicadoFormData, setError: UseFormSetError<ComunicadoFormData>) => void
  enviando: boolean
  onCancelar: () => void
}

const esTipo = (v: string | null): v is ComunicadoFormData['tipo'] => v === 'noticia' || v === 'comunicado'

/**
 * El formulario de un borrador (docs/diseno-noticias-comunicados.md, 6.3), el
 * mismo para crearlo y para seguir editándolo. Publicar va aparte, en el
 * detalle, que es también la vista previa: se guarda, se revisa cómo lo verá
 * el servidor y se publica.
 */
export function ComunicadoForm({ valoresIniciales, onSubmit, enviando, onCancelar }: Props) {
  const contained = useContainedInput()

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<ComunicadoFormData>({
    resolver: zodResolver(comunicadoSchema),
    defaultValues: valoresIniciales ?? COMUNICADO_VACIO,
  })

  const [tipo, resumen] = useWatch({ control, name: ['tipo', 'resumen'] })

  return (
    <form onSubmit={handleSubmit((datos) => onSubmit(datos, setError))} noValidate>
      <Stack gap="md">
        <SectionCard title="Datos">
          <Grid gap="md">
            <Grid.Col span={{ base: 12, sm: 5 }}>
              <Controller
                name="tipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo"
                    description={OPCIONES_TIPO.find((o) => o.value === field.value)?.descripcion}
                    data={OPCIONES_TIPO.map(({ value, label }) => ({ value, label }))}
                    allowDeselect={false}
                    {...contained}
                    value={field.value}
                    onChange={(v) => esTipo(v) && field.onChange(v)}
                  />
                )}
              />
            </Grid.Col>
            {tipo === 'comunicado' && (
              <Grid.Col span={{ base: 12, sm: 7 }}>
                <TextInput
                  label="Número de documento"
                  description="Opcional: el de la circular o el memorando"
                  placeholder="Ej: Circular GADPE-DATH-2026-045"
                  {...contained}
                  {...register('referencia')}
                  error={errors.referencia?.message}
                />
              </Grid.Col>
            )}
            <Grid.Col span={12}>
              <TextInput
                label="Título"
                placeholder="Ej: Descanso obligatorio del 2 y 3 de noviembre"
                required
                {...contained}
                {...register('titulo')}
                error={errors.titulo?.message}
              />
            </Grid.Col>
            <Grid.Col span={12}>
              <Textarea
                label="Resumen"
                description={`Lo que se lee en la tarjeta del Portal · ${resumen.length} de 300`}
                placeholder="Una o dos frases con lo esencial"
                autosize
                minRows={2}
                maxRows={4}
                {...contained}
                {...register('resumen')}
                error={errors.resumen?.message}
              />
            </Grid.Col>
          </Grid>
        </SectionCard>

        <SectionCard title="Texto" description="Lo que se lee al abrir la publicación. Las imágenes van como portada.">
          <Controller
            name="cuerpo"
            control={control}
            render={({ field }) => (
              <RichTextInput
                label="Texto de la publicación"
                completo
                minHeight={220}
                maxHeight={600}
                value={field.value}
                onChange={field.onChange}
                error={errors.cuerpo?.message}
              />
            )}
          />
        </SectionCard>

        <SectionCard title="Destinatarios">
          <DestinatariosCampos control={control} />
        </SectionCard>

        <SectionCard title="Publicación">
          <PublicacionCampos control={control} errors={errors} />
        </SectionCard>

        <Group justify="space-between">
          <Button variant="default" leftSection={<IconArrowLeft size={14} />} onClick={onCancelar}>
            Cancelar
          </Button>
          <Button type="submit" leftSection={<IconDeviceFloppy size={14} />} loading={enviando}>
            Guardar borrador
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
