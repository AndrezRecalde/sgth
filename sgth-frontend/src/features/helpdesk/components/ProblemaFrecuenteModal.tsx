'use client'

import { Grid, Radio, Select, Stack, Switch, Text, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { TIPOS_TICKET } from '../constants/estados'
import { useProblemaFrecuenteMutations } from '../hooks/useConfiguracionHelpdesk'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { problemaSchema, type ProblemaFormData } from '../schemas/configuracion.schema'
import type { ProblemaFrecuenteConfig } from '../services/tipos'

const CAMPOS = ['nombre', 'categoria_id', 'tipo_ticket', 'prioridad', 'descripcion', 'activo']

interface Props {
  opened: boolean
  onClose: () => void
  problema: ProblemaFrecuenteConfig | null
}

/**
 * Un problema frecuente: lo que ve el servidor y todo lo que lleva el ticket
 * que sale al elegirlo. La subunidad y el plazo los da la categoría.
 */
export function ProblemaFrecuenteModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, problema }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { guardar } = useProblemaFrecuenteMutations()
  const categorias = catalogos?.categorias ?? []

  const valores: DefaultValues<ProblemaFormData> = problema
    ? {
        nombre: problema.nombre, descripcion: problema.descripcion, tipo_ticket: problema.tipo_ticket,
        prioridad: problema.prioridad, activo: problema.activo,
        // Con la categoría inactiva no se puede guardar: queda vacía para elegir otra.
        ...(problema.categoria_activa ? { categoria_id: problema.categoria_id } : {}),
      }
    : { nombre: '', descripcion: '', tipo_ticket: 'incidente', prioridad: 'media', activo: true }

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ProblemaFormData>({
    resolver: zodResolver(problemaSchema),
    defaultValues: valores,
  })

  const enviar = (v: ProblemaFormData) =>
    guardar.mutateAsync({ id: problema?.id, data: v })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo guardar el problema frecuente'))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={problema ? 'Editar problema frecuente' : 'Nuevo problema frecuente'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={problema ? 'Guardar cambios' : 'Registrar problema'}
      submitting={guardar.isPending}
      size="lg"
    >
      <Grid>
        <Grid.Col span={12}>
          <TextInput label="Nombre" placeholder="Ej.: La impresora no imprime" withAsterisk
            description="Es el botón que ve el servidor y el asunto del ticket. Escríbalo con sus palabras."
            error={errors.nombre?.message} {...contained} {...register('nombre')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Descripción" placeholder="Ej.: La impresora que uso no imprime." withAsterisk autosize minRows={2}
            description="Es el texto que lleva el ticket. El servidor puede agregar un comentario al enviarlo."
            error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 7 }}>
          <Controller name="categoria_id" control={control} render={({ field }) => (
            <Select label="Categoría" placeholder="Elija la categoría" withAsterisk searchable {...contained}
              description="Decide la subunidad que atiende y el plazo."
              error={errors.categoria_id?.message}
              data={categorias.map((c) => ({ value: String(c.id), label: c.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller name="prioridad" control={control} render={({ field }) => (
            <Select label="Prioridad" withAsterisk allowDeselect={false} {...contained}
              description="TIC puede cambiarla al asignar."
              error={errors.prioridad?.message}
              data={(catalogos?.prioridades ?? []).map((p) => ({ value: p.valor, label: p.etiqueta }))}
              value={field.value ?? null} onChange={(v) => v && field.onChange(v)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="tipo_ticket" control={control} render={({ field }) => (
            <Radio.Group label="Tipo" withAsterisk value={field.value} onChange={field.onChange} error={errors.tipo_ticket?.message}>
              <Stack gap="xs" mt={6}>
                {TIPOS_TICKET.map((t) => (
                  <Radio key={t.value} value={t.value} label={t.label} description={t.descripcion} />
                ))}
              </Stack>
            </Radio.Group>
          )} />
        </Grid.Col>
        {problema && (
          <Grid.Col span={12}>
            <Controller name="activo" control={control} render={({ field }) => (
              <Switch label="Activo" description="Inactivo, deja de ofrecerse al pedir ayuda."
                checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
            )} />
          </Grid.Col>
        )}
        {problema && !problema.categoria_activa && (
          <Grid.Col span={12}>
            <Text size="xs" c="dimmed">Su categoría, {problema.categoria}, está inactiva: elija otra para que vuelva a ofrecerse.</Text>
          </Grid.Col>
        )}
      </Grid>
    </FormModal>
  )
}
