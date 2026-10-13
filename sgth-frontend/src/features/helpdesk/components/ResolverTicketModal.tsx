'use client'

import { Group, Radio, Stack, Switch, Text, Textarea } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import { resolverSchema, type ResolverFormData } from '../schemas/tramite.schema'
import type { Ticket } from '../services/tipos'

const ESTADOS_EQUIPO: { value: 'disponible' | 'en_mantenimiento' | 'en_garantia'; label: string }[] = [
  { value: 'disponible', label: 'Vuelve al servicio' },
  { value: 'en_mantenimiento', label: 'Sigue en mantenimiento' },
  { value: 'en_garantia', label: 'Queda con el proveedor' },
]

/**
 * Resolver con la solución escrita: es lo que lee el usuario al confirmar. Si
 * el ticket tiene equipo, queda el mantenimiento correctivo en su ficha.
 */
export function ResolverTicketModal({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const contained = useContainedInput()
  const { resolver } = useTicketTicMutations()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ResolverFormData>({
    resolver: zodResolver(resolverSchema),
    defaultValues: { solucion: '', registrar_mantenimiento: Boolean(ticket.bien && ticket.categoria?.es_hardware), estado_equipo: null },
  })
  const mantenimiento = useWatch({ control, name: 'registrar_mantenimiento' })

  const enviar = (v: ResolverFormData) =>
    resolver.mutateAsync({ id: ticket.id, data: v }).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['solucion', 'estado_equipo'], 'No se pudo resolver el ticket'))

  return (
    <FormModal opened onClose={onClose} title={`Resolver ${ticket.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Resolver" submitting={resolver.isPending} size="lg">
      <Stack gap="md">
        <Textarea label="Solución" description="La lee el usuario al confirmar: escríbala para él." withAsterisk autosize minRows={3}
          placeholder="Ej.: Se reemplazó el cable de red del puesto y se probó la navegación."
          error={errors.solucion?.message} {...contained}
          {...register('solucion')} />
        {ticket.bien && (
          <>
            <Controller name="registrar_mantenimiento" control={control} render={({ field }) => (
              <Switch label={`Registrar el mantenimiento correctivo en ${ticket.bien?.codigo_institucional}`}
                checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
            )} />
            {mantenimiento && (
              // Botones de opción y no una lista: la lista se abría hacia arriba,
              // sobre el interruptor, y el clic que elegía la opción lo apagaba.
              <Controller name="estado_equipo" control={control} render={({ field }) => (
                <Radio.Group label="El equipo queda" error={errors.estado_equipo?.message}
                  value={field.value ?? 'sin_cambios'}
                  onChange={(v) => field.onChange(ESTADOS_EQUIPO.find((e) => e.value === v)?.value ?? null)}>
                  <Group gap="md" mt={4}>
                    <Radio value="sin_cambios" label="Sin cambios" />
                    {ESTADOS_EQUIPO.map((e) => <Radio key={e.value} value={e.value} label={e.label} />)}
                  </Group>
                </Radio.Group>
              )} />
            )}
          </>
        )}
        <Text size="xs" c="dimmed">Si el usuario no confirma ni reabre, el ticket se cierra solo a los 3 días hábiles.</Text>
      </Stack>
    </FormModal>
  )
}
