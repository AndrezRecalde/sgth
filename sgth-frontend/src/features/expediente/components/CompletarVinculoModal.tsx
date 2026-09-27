'use client'

import { Alert, Grid, Stack, Switch, TextInput } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { Controller, useForm, type DefaultValues } from 'react-hook-form'
import { CompletarVinculoPlazo } from './CompletarVinculoPlazo'
import { CompletarVinculoRemuneracion } from './CompletarVinculoRemuneracion'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SelectPartidaPresupuestaria } from '@/features/estructura/components/SelectPartidaPresupuestaria'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import { admiteMarcacion, esLosep } from '../utils/nombramiento'
import {
  completarVinculoSchema, type CompletarVinculoFormData,
} from '../schemas/completarVinculo.schema'
import type { MovimientoPersonal } from '@/types/api'

/** Nombramientos cuyo vínculo lleva plazo pactado. */
const CON_PLAZO = ['servicios_ocasionales', 'servicios_profesionales']

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
  const derivaDelPuesto = esLosep(nombramiento)
  const llevaPlazo = nombramiento ? CON_PLAZO.includes(nombramiento) : false
  const marca = admiteMarcacion(nombramiento)

  const puesto = movimiento.puesto_destino

  // La RMU solo se sugiere en LOSEP, donde sale del grupo ocupacional del
  // puesto. En Código del Trabajo y Servicios Profesionales se negocia en el
  // contrato, así que el campo arranca vacío a propósito.
  const rmuSugerida = derivaDelPuesto && puesto?.rmu != null ? Number(puesto.rmu) : undefined

  /*
  | Hasta el 2026-09-27 este formulario llevaba seis `useState` y validaba a mano
  | en el `submit`, con lo que faltaba en un `Alert` al pie: el usuario leía «falta
  | número de contrato y remuneración» y tenía que buscar cuáles de los seis
  | campos eran. Es el estándar del proyecto desde hace tiempo (regla 07), y era
  | el último formulario del módulo que no lo usaba.
  */
  const iniciales: DefaultValues<CompletarVinculoFormData> = {
    numero_contrato: movimiento.numero_contrato ?? '',
    remuneracion_propuesta: movimiento.remuneracion_propuesta != null
      ? Number(movimiento.remuneracion_propuesta)
      : rmuSugerida,
    resolucion_numero: movimiento.resolucion_numero ?? '',
    partida_presupuestaria_id: movimiento.partida_presupuestaria_id
      ?? puesto?.partida_presupuestaria?.id
      ?? null,
    // La modalidad manda sobre lo que quedó guardado: servicios profesionales,
    // libre nombramiento y elección popular no marcan nunca.
    puede_marcar: marca && (movimiento.puede_marcar ?? false),
    fecha_fin_propuesta: movimiento.fecha_fin_propuesta?.split('T')[0] ?? null,
  }

  const form = useForm<CompletarVinculoFormData>({
    resolver: zodResolver(completarVinculoSchema),
    defaultValues: iniciales,
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
        fecha_fin_propuesta: llevaPlazo ? datos.fecha_fin_propuesta : null,
        // Cinturón: el interruptor está bloqueado, pero el valor guardado pudo
        // llegar en true desde el borrador.
        puede_marcar: marca && datos.puede_marcar,
      })
      .then(() => { onClose(); onSaved?.() })
      // El fallo ya lo notifica el `onError` de la mutación.
      .catch(() => {})

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
          llevaPlazo={llevaPlazo}
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
