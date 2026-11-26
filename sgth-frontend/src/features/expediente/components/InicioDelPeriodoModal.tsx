'use client'

import { Alert, Grid, NumberInput, Select, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { useEffect } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { formatFecha, fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useDignidades } from '../hooks/useAutoridadesElectas'
import { useRegistrarActoDeAutoridad } from '../hooks/useRegistrarActoDeAutoridad'
import {
  inicioDelPeriodoSchema, type InicioDelPeriodoFormData,
} from '../schemas/inicioDelPeriodo.schema'
import { BuscarServidorSelect } from './BuscarServidorSelect'
import type { Dignidad } from '../services/autoridadElectaService'

const CAUSALES = [
  { value: 'eleccion', label: 'Elección popular' },
  { value: 'designacion_del_consejo', label: 'Designación del Consejo Provincial' },
]

interface Props {
  opened: boolean
  onClose: () => void
  /** La autoridad reelegida: su período siguiente empieza cuando termina el actual. */
  reeleccion?: { servidorId: number; nombre: string; puestoId: number; hasta: string } | null
}

/**
 * El inicio del período de una autoridad electa (COOTAD; diseño, 9.14; TH 1–4;
 * fase 4.1).
 *
 * Vive aquí y no en «Nueva acción de personal» porque al Prefecto y al
 * Viceprefecto no se les emite acción de personal: su cargo nace de la
 * elección o de una resolución del Consejo, no de una autoridad nominadora.
 * Compartir formulario con el catálogo de los servidores produjo cuatro de los
 * defectos de la prueba del 2026-11-26 — entre ellos un callejón sin salida,
 * porque el error caía en un campo del documento del Ministerio que esta
 * pantalla no enseña nunca. Aquí esos campos sencillamente no existen.
 *
 * Solo ofrece las dos dignidades: la Prefectura y la Viceprefectura.
 */
export function InicioDelPeriodoModal({ opened, onClose, reeleccion = null }: Props) {
  return opened ? <Formulario onClose={onClose} reeleccion={reeleccion} /> : null
}

function Formulario({ onClose, reeleccion }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { data: dignidades = [], isLoading } = useDignidades()

  const {
    control, register, handleSubmit, setValue, setError, getValues,
    formState: { errors },
  } = useForm<InicioDelPeriodoFormData>({
    resolver: zodResolver(inicioDelPeriodoSchema),
    defaultValues: {
      servidor_id: reeleccion?.servidorId ?? undefined,
      causal: 'eleccion',
      puesto_destino_id: reeleccion?.puestoId ?? undefined,
      unidad_destino_id: undefined,
      fecha_efectiva: '',
      fecha_fin_propuesta: null,
      credencial_cne_numero: '',
      credencial_cne_fecha: null,
      acta_posesion_numero: '',
      acto_origen_numero: '',
      acto_origen_fecha: null,
      remuneracion_propuesta: null,
      descripcion: reeleccion
        ? `Inicio del período de ${reeleccion.nombre}, reelegido para un período nuevo.`
        : '',
    },
  })

  const [causal, puestoId, unidadId] = useWatch({
    control, name: ['causal', 'puesto_destino_id', 'unidad_destino_id'],
  })
  const designacion = causal === 'designacion_del_consejo'
  const elegida = dignidades.find((d) => d.puesto_id === puestoId)

  /*
  | La reelección abre con la dignidad puesta, así que nadie toca el selector y
  | su unidad y su remuneración nunca se rellenaban: el formulario se quedaba
  | inválido por un campo que esta pantalla no enseña y el botón no hacía nada.
  | Es el mismo defecto que H4, y por eso también está la red de `alFallar`.
  */
  useEffect(() => {
    if (!elegida || unidadId) return

    setValue('unidad_destino_id', elegida.unidad_administrativa_id)
    setValue('remuneracion_propuesta', elegida.rmu != null ? Number(elegida.rmu) : null)
  }, [elegida, unidadId, setValue])

  /** Elegir la dignidad arrastra su unidad y la remuneración de su escala. */
  const elegirDignidad = (d: Dignidad | undefined) => {
    setValue('puesto_destino_id', d?.puesto_id ?? 0, { shouldValidate: true })
    setValue('unidad_destino_id', d?.unidad_administrativa_id ?? 0)
    setValue('remuneracion_propuesta', d?.rmu != null ? Number(d.rmu) : null)
  }

  const { registrar, enviando } = useRegistrarActoDeAutoridad<InicioDelPeriodoFormData>({
    servidorId: () => getValues('servidor_id'),
    campos: [
      'fecha_efectiva', 'fecha_fin_propuesta', 'credencial_cne_numero', 'credencial_cne_fecha',
      'acto_origen_numero', 'acto_origen_fecha', 'puesto_destino_id', 'descripcion',
    ],
    tituloDelError: 'No se pudo registrar el inicio del período',
    exito: {
      titulo: 'Inicio del período registrado',
      detalle: 'Quedó en borrador: suscríbalo y regístrelo en la bandeja de acciones.',
    },
    onListo: onClose,
  })

  const guardar = (v: InicioDelPeriodoFormData) => {
    const comun = {
      clase: 'inicio_periodo' as const,
      causal: v.causal,
      descripcion: v.descripcion.trim(),
      fecha_efectiva: v.fecha_efectiva,
      unidad_destino_id: v.unidad_destino_id,
      puesto_destino_id: v.puesto_destino_id,
      remuneracion_propuesta: v.remuneracion_propuesta,
    }

    // Cada variante manda lo suyo y nada de la otra: el backend limpia
    // igualmente, pero un número de credencial viajando en una designación no
    // significa nada y confunde al revisar el borrador.
    const datos = designacion
      ? {
          ...comun,
          acto_origen_numero: v.acto_origen_numero?.trim() || null,
          acto_origen_fecha: v.acto_origen_fecha,
          // No se pregunta porque no hay nada que preguntar: lo que emite el
          // Consejo Provincial es una resolución. El campo existe para los
          // actos del Ministerio —decreto, acuerdo o resolución—, que esta
          // pantalla no enseña; y `StoreMovimientoPersonalRequest` lo exige en
          // cuanto llega un número de acto.
          acto_origen_tipo: 'resolucion' as const,
        }
      : {
          ...comun,
          credencial_cne_numero: v.credencial_cne_numero?.trim() || null,
          credencial_cne_fecha: v.credencial_cne_fecha,
          acta_posesion_numero: v.acta_posesion_numero?.trim() || null,
          fecha_fin_propuesta: v.fecha_fin_propuesta,
        }

    return registrar(datos, setError)
  }

  /*
  | Un formulario no se queda mudo. Los campos con control propio pintan su
  | error; si lo que falla es uno que esta pantalla no enseña —la unidad, que
  | viene de la dignidad, o la autoridad en una reelección—, el botón parecería
  | no hacer nada. Avisar es lo que faltaba en el formulario general y lo que
  | convirtió un error de validación en un callejón sin salida (H4).
  */
  const SIN_CONTROL_PROPIO: Array<keyof InicioDelPeriodoFormData> = [
    'unidad_destino_id', 'servidor_id',
  ]

  const alFallar = (fallos: Record<string, { message?: string } | undefined>) => {
    const mudos = Object.keys(fallos).filter(
      (campo) => SIN_CONTROL_PROPIO.includes(campo as keyof InicioDelPeriodoFormData),
    )
    if (!mudos.length) return

    notificar.aviso(
      'Faltan datos de la dignidad',
      'Vuelva a elegir la dignidad: de ella salen su unidad y su remuneración.',
    )
  }

  const fecha = (
    name: 'fecha_efectiva' | 'fecha_fin_propuesta' | 'credencial_cne_fecha' | 'acto_origen_fecha',
    label: string,
    extra: { description?: string; maxDate?: Date; required?: boolean } = {},
  ) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          description={extra.description}
          placeholder="Seleccionar fecha"
          valueFormat="DD/MM/YYYY"
          withAsterisk={extra.required}
          maxDate={extra.maxDate}
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          value={toDateValue(field.value)}
          onChange={(d) => field.onChange(fromDateValueOrNull(d))}
          error={errors[name]?.message}
          {...contained}
        />
      )}
    />
  )

  return (
    <FormModal
      opened
      onClose={onClose}
      title={reeleccion ? `Nuevo período de ${reeleccion.nombre}` : 'Registrar inicio del período'}
      size="lg"
      onSubmit={handleSubmit(guardar, alFallar)}
      submitLabel="Registrar en borrador"
      submitting={enviando}
    >
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          El período de una autoridad electa no lleva acción de personal ni firmantes: se
          registra con su documento de origen y su propia numeración (AU). Dura hasta cuatro
          años y se cierra solo el día que termina.
        </Alert>

        {reeleccion && (
          <Alert color="amber" variant="light" icon={<IconInfoCircle size={16} />}>
            Su período actual termina el <b>{formatFecha(reeleccion.hasta)}</b>. El nuevo
            empieza después, sin cortar su antigüedad ni su nómina.
          </Alert>
        )}

        {!reeleccion && (
          <Controller
            name="servidor_id"
            control={control}
            render={({ field }) => (
              <BuscarServidorSelect
                label="Autoridad"
                required
                value={field.value ?? null}
                onChange={(id) => field.onChange(id ?? undefined)}
                error={errors.servidor_id?.message}
              />
            )}
          />
        )}

        <Controller
          name="causal"
          control={control}
          render={({ field }) => (
            <Select
              label="Cómo llega al cargo"
              description="La designación es solo para la Viceprefectura vacante (COOTAD Art. 47 n)."
              required
              data={CAUSALES}
              value={field.value}
              onChange={(v) => field.onChange(v ?? 'eleccion')}
              error={errors.causal?.message}
              {...contained}
            />
          )}
        />

        <Select
          label="Dignidad"
          required
          placeholder={isLoading ? 'Cargando…' : 'Seleccionar'}
          data={dignidades.map((d) => ({ value: String(d.puesto_id), label: d.dignidad ?? `Puesto ${d.puesto_id}` }))}
          value={puestoId ? String(puestoId) : null}
          onChange={(v) => elegirDignidad(dignidades.find((d) => d.puesto_id === Number(v)))}
          error={errors.puesto_destino_id?.message}
          disabled={Boolean(reeleccion)}
          {...contained}
        />

        {elegida && (
          <Text size="xs" c="dimmed">
            {elegida.unidad}
            {elegida.ocupada_por
              ? ` · la ocupa ${elegida.ocupada_por}${elegida.ocupada_hasta ? ` hasta el ${formatFecha(elegida.ocupada_hasta)}` : ''}: el período nuevo empieza después`
              : ' · vacante'}
          </Text>
        )}

        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>
            {fecha('fecha_efectiva', 'Rige a partir de', {
              description: 'El día de la posesión.', required: true,
            })}
          </Grid.Col>

          {!designacion && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              {fecha('fecha_fin_propuesta', 'Fin del período', {
                description: 'Ese día el vínculo se cierra solo.', required: true,
              })}
            </Grid.Col>
          )}
        </Grid>

        {designacion ? (
          <Grid gap="sm">
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Resolución del Consejo N.º"
                placeholder="Ej. RES-CP-2027-031"
                withAsterisk
                error={errors.acto_origen_numero?.message}
                {...contained}
                {...register('acto_origen_numero')}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              {fecha('acto_origen_fecha', 'Fecha de la resolución', {
                maxDate: new Date(), required: true,
              })}
            </Grid.Col>
            <Grid.Col span={12}>
              <Text size="xs" c="dimmed">
                Termina con el período del Prefecto en funciones —ese fin lo pone el sistema— y
                no lleva acta de posesión (COOTAD Art. 47 n).
              </Text>
            </Grid.Col>
          </Grid>
        ) : (
          <Grid gap="sm">
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Credencial del CNE N.º"
                placeholder="Ej. CNE-2027-0147"
                withAsterisk
                error={errors.credencial_cne_numero?.message}
                {...contained}
                {...register('credencial_cne_numero')}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              {fecha('credencial_cne_fecha', 'Fecha de la credencial', {
                maxDate: new Date(), required: true,
              })}
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Acta de posesión N.º"
                placeholder="Opcional"
                error={errors.acta_posesion_numero?.message}
                {...contained}
                {...register('acta_posesion_numero')}
              />
            </Grid.Col>
          </Grid>
        )}

        <Controller
          name="remuneracion_propuesta"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Remuneración"
              description="La de la escala de la dignidad; cámbiela si este caso es distinto."
              min={0}
              decimalScale={2}
              thousandSeparator=","
              prefix="$ "
              value={field.value ?? ''}
              onChange={(v) => field.onChange(v === '' ? null : Number(v))}
              error={errors.remuneracion_propuesta?.message}
              {...contained}
            />
          )}
        />

        <Textarea
          label="Explicación"
          description="Es el texto que lleva el documento del acto."
          placeholder="Ej. Posesión del Prefecto Provincial electo para el período 2027–2031."
          required
          autosize
          minRows={2}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />
      </Stack>
    </FormModal>
  )
}
