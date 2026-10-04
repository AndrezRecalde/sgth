'use client'

import { Alert, Grid, Stack, Switch, TextInput } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { Controller, useForm } from 'react-hook-form'
import { CompletarVinculoPlazo } from './CompletarVinculoPlazo'
import { CompletarVinculoRemuneracion } from './CompletarVinculoRemuneracion'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SelectPartidaPresupuestaria } from '@/features/estructura/components/SelectPartidaPresupuestaria'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import { admiteMarcacion } from '../utils/nombramiento'
import { llevaPlazo, valoresIniciales } from '../utils/completarVinculoValores'
import {
  completarVinculoSchema, type CompletarVinculoFormData,
} from '../schemas/completarVinculo.schema'
import type { MovimientoPersonal } from '@/types/api'

/**
 * Cierre de un ingreso: los datos que el contrato necesita para nacer, en el
 * propio acto de aprobar la acción.
 *
 * Existe únicamente para ese momento. Una acción suscrita ya no se edita —el
 * documento circuló—, pero el vínculo todavía tiene que materializarse con
 * número y remuneración, y este es el único formulario que puede aportarlos
 * sin reabrir el acto. Mientras la acción sigue en borrador no se usa: ahí
 * todo se corrige en el formulario completo de la acción.
 */
interface Props {
  opened: boolean
  onClose: () => void
  movimiento: MovimientoPersonal | null
  /** Se dispara solo cuando el guardado tuvo éxito, no al cancelar. */
  onSaved?: () => void
}

export function CompletarVinculoModal({ opened, onClose, movimiento, onSaved }: Props) {
  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Aprobar y registrar el vínculo"
      size="lg"
    >
      {opened && movimiento && (
        <Formulario
          key={movimiento.id}
          movimiento={movimiento}
          onClose={onClose}
          onSaved={onSaved}
        />
      )}
    </SgthModal>
  )
}

function Formulario({
  movimiento,
  onClose,
  onSaved,
}: {
  movimiento: MovimientoPersonal
  onClose: () => void
  onSaved?: () => void
}) {
  const contained = useContainedInput()
  const { transicionar } = useMovimientoMutations()

  const nombramiento = movimiento.tipo_nombramiento_propuesto ?? null
  const conPlazo = llevaPlazo(nombramiento)
  const marca = admiteMarcacion(nombramiento)
  const puesto = movimiento.puesto_destino

  const form = useForm<CompletarVinculoFormData>({
    resolver: zodResolver(completarVinculoSchema),
    defaultValues: valoresIniciales(movimiento),
  })

  const { control, handleSubmit, register, formState: { errors } } = form

  const registrar = (datos: CompletarVinculoFormData) =>
    transicionar
      .mutateAsync({
        id: Number(movimiento.id),
        estado: 'registrada',
        ...datos,
        resolucion_numero: datos.resolucion_numero || null,
        // Sin plazo pactado no se manda fecha de término, aunque haya quedado
        // una escrita antes de cambiar de modalidad.
        fecha_fin_propuesta: conPlazo ? datos.fecha_fin_propuesta : null,
        // Cinturón: el interruptor está bloqueado, pero el valor guardado pudo
        // llegar en true desde el borrador.
        puede_marcar: marca && datos.puede_marcar,
      })
      .then(() => { onClose(); onSaved?.() })
      // El fallo ya lo notifica el `onError` de la mutación, que comparten
      // acciones sin formulario; aquí solo se marca el campo, si lo tiene.
      .catch((e) => erroresAlFormulario(
        e, form.setError, Object.keys(completarVinculoSchema.shape), null,
      ))

  return (
    <form onSubmit={handleSubmit(registrar)} noValidate>
      <Stack gap="sm">
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
          Al registrar se crea el vínculo laboral con estos datos. Después la acción
          queda inmutable: solo se corrige registrando una acción nueva.
        </Alert>

        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Número de contrato"
              placeholder="Ej: CT-2026-0099"
              error={errors.numero_contrato?.message}
              {...contained}
              {...register('numero_contrato')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Número de resolución"
              placeholder="Opcional"
              error={errors.resolucion_numero?.message}
              {...contained}
              {...register('resolucion_numero')}
            />
          </Grid.Col>
        </Grid>

        <CompletarVinculoPlazo
          form={form}
          fechaEfectiva={movimiento.fecha_efectiva}
          llevaPlazo={conPlazo}
        />

        <CompletarVinculoRemuneracion
          form={form}
          nombramiento={nombramiento}
          rmuDelPuesto={puesto?.rmu != null ? Number(puesto.rmu) : null}
        />

        <Controller
          name="partida_presupuestaria_id"
          control={control}
          render={({ field }) => (
            <SelectPartidaPresupuestaria
              value={field.value ?? null}
              onChange={field.onChange}
              modalidad={nombramiento}
              error={errors.partida_presupuestaria_id?.message}
            />
          )}
        />

        <Controller
          name="puede_marcar"
          control={control}
          render={({ field }) => (
            <Switch
              label="Marcación biométrica"
              description={marca
                ? 'Sugerida según el nombramiento; ajústela si este caso es distinto.'
                : 'Esta modalidad no marca biométrico.'}
              checked={marca && !!field.value}
              disabled={!marca}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )}
        />

        <ModalFooter
          onCancel={onClose}
          submitLabel="Registrar vínculo"
          submitting={transicionar.isPending}
        />
      </Stack>
    </form>
  )
}
