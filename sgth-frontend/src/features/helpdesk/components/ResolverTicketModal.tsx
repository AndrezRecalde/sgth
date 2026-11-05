'use client'

import { Checkbox, Group, Radio, Stack, Switch, Text, Textarea } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { BuscarEquipoSelect } from '@/features/inventario-ti/components/BuscarEquipoSelect'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import { resolverSchema, type ResolverFormData } from '../schemas/tramite.schema'
import type { Ticket } from '../services/tipos'

const ESTADOS_EQUIPO: { value: 'disponible' | 'en_mantenimiento' | 'en_garantia'; label: string }[] = [
  { value: 'disponible', label: 'Vuelve al servicio' },
  { value: 'en_mantenimiento', label: 'Sigue en mantenimiento' },
  { value: 'en_garantia', label: 'Queda con el proveedor' },
]

const CAMPOS = ['solucion', 'estado_equipo', 'bien_informatico_id', 'motivo_sin_equipo']

/**
 * Resolver con la solución escrita: es lo que lee el usuario al confirmar. Si
 * el ticket tiene equipo, queda el mantenimiento correctivo en su ficha. Uno
 * de hardware sin equipo pide el que se atendió o por qué no es del
 * inventario (2026-11-05): así nadie elige cualquiera para poder cerrar.
 */
export function ResolverTicketModal({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const contained = useContainedInput()
  const { resolver } = useTicketTicMutations()
  const deHardware = Boolean(ticket.categoria?.es_hardware && !ticket.categoria?.es_consumibles)
  const pideEquipo = deHardware && !ticket.bien

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ResolverFormData>({
    resolver: zodResolver(resolverSchema),
    defaultValues: {
      solucion: '', registrar_mantenimiento: Boolean(ticket.bien && ticket.categoria?.es_hardware), estado_equipo: null,
      pide_equipo: pideEquipo, bien_informatico_id: null, sin_equipo: false, motivo_sin_equipo: '',
    },
  })
  const mantenimiento = useWatch({ control, name: 'registrar_mantenimiento' })
  const sinEquipo = useWatch({ control, name: 'sin_equipo' })
  const equipoElegido = useWatch({ control, name: 'bien_informatico_id' })
  // El interruptor del mantenimiento: con el equipo que ya tenía, o con el que se elige aquí.
  const conEquipo = Boolean(ticket.bien) || (pideEquipo && !sinEquipo && Boolean(equipoElegido))

  const enviar = (v: ResolverFormData) =>
    resolver.mutateAsync({
      id: ticket.id,
      data: {
        solucion: v.solucion,
        // Con el equipo elegido aquí, el de hardware deja su correctivo (lo decide el backend).
        registrar_mantenimiento: ticket.bien ? v.registrar_mantenimiento : true,
        estado_equipo: conEquipo && (ticket.bien ? v.registrar_mantenimiento : true) ? v.estado_equipo : null,
        bien_informatico_id: pideEquipo && !v.sin_equipo ? v.bien_informatico_id : null,
        motivo_sin_equipo: pideEquipo && v.sin_equipo ? v.motivo_sin_equipo : null,
      },
    }).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo resolver el ticket'))

  const estadoEquipo = (
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
  )

  return (
    <FormModal opened onClose={onClose} title={`Resolver ${ticket.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Resolver" submitting={resolver.isPending} size="lg">
      <Stack gap="md">
        <Textarea label="Solución" description="La lee el usuario al confirmar: escríbala para él." withAsterisk autosize minRows={3}
          placeholder="Ej.: Se reemplazó el cable de red del puesto y se probó la navegación."
          error={errors.solucion?.message} {...contained}
          {...register('solucion')} />

        {pideEquipo && (
          <Stack gap="xs">
            {!sinEquipo && (
              <Controller name="bien_informatico_id" control={control} render={({ field }) => (
                <BuscarEquipoSelect label="Equipo atendido" required soloPrincipales={false} placeholder="Código, serie o modelo"
                  value={field.value} onChange={(id) => field.onChange(id ?? null)} error={errors.bien_informatico_id?.message} />
              )} />
            )}
            <Controller name="sin_equipo" control={control} render={({ field }) => (
              <Checkbox label="No es un equipo del inventario"
                description="Una laptop personal, un periférico sin código, un equipo de otra entidad. No deja mantenimiento."
                checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
            )} />
            {sinEquipo && (
              <Textarea label="¿Por qué?" placeholder="Ej.: laptop personal del servidor" withAsterisk autosize minRows={2}
                error={errors.motivo_sin_equipo?.message} {...contained} {...register('motivo_sin_equipo')} />
            )}
            {conEquipo && estadoEquipo}
          </Stack>
        )}

        {ticket.bien && (
          <>
            <Controller name="registrar_mantenimiento" control={control} render={({ field }) => (
              <Switch label={`Registrar el mantenimiento correctivo en ${ticket.bien?.codigo_institucional}`}
                checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
            )} />
            {mantenimiento && estadoEquipo}
          </>
        )}
        <Text size="xs" c="dimmed">Si el usuario no confirma ni reabre, el ticket se cierra solo a los 3 días hábiles.</Text>
      </Stack>
    </FormModal>
  )
}
