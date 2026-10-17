'use client'

import { Grid, SegmentedControl, Select, Text, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { formatFecha, fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useContratosVigencia, useUbicaciones } from '../hooks/useCatalogosInventario'
import { useCampaniaMutations } from '../hooks/useCampanias'
import { useTecnicosTic } from '../hooks/useMantenimientos'
import { campaniaSchema, type CampaniaFormData } from '../schemas/campania.schema'
import type { Campania } from '../services/campaniasService'

const CAMPOS = [
  'nombre', 'descripcion', 'realizado_por', 'contrato_vigencia_id', 'tecnico_id', 'fecha_inicio', 'fecha_fin',
  'ubicacion_id', 'unidad_administrativa_id',
]

interface Props {
  opened: boolean
  /** La campaña que se edita; sin ella, se crea una. */
  campania?: Campania
  onClose: () => void
  onCreada?: (c: Campania) => void
}

/**
 * Crear o editar una campaña de preventivo. Al crearla entran los equipos del
 * lugar; por eso el lugar ya no se cambia después: se agregan o se quitan
 * equipos. La del proveedor (5.1b) la hace la empresa de la vigencia: entran
 * los equipos de su contrato (de un lugar, o todos) y el técnico de TIC, si
 * se elige, solo acompaña. Quién la hace tampoco cambia después.
 */
export function CampaniaModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ campania, onClose, onCreada }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const editando = !!campania
  const { data: ubicaciones = [] } = useUbicaciones()
  const { data: unidades = [] } = useTodasUnidades()
  const { data: tecnicos = [] } = useTecnicosTic()
  const { data: contratos = [] } = useContratosVigencia()
  const { crear, actualizar } = useCampaniaMutations(campania?.id)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<CampaniaFormData>({
    resolver: zodResolver(campaniaSchema),
    defaultValues: campania
      ? {
          nombre: campania.nombre, descripcion: campania.descripcion, realizado_por: campania.realizado_por,
          contrato_vigencia_id: campania.contrato_id, tecnico_id: campania.tecnico_id,
          fecha_inicio: campania.fecha_inicio, fecha_fin: campania.fecha_fin,
          ubicacion_id: campania.ubicacion_id, unidad_administrativa_id: campania.unidad_id,
        }
      : {
          nombre: '', descripcion: '', realizado_por: 'tic', contrato_vigencia_id: null, tecnico_id: null,
          fecha_inicio: hoyIso(), fecha_fin: '', ubicacion_id: null, unidad_administrativa_id: null,
        },
  })
  const inicio = useWatch({ control, name: 'fecha_inicio' })
  const delProveedor = useWatch({ control, name: 'realizado_por' }) === 'proveedor'
  const contratoId = useWatch({ control, name: 'contrato_vigencia_id' })

  // Solo los contratos que no han vencido: lo de después ya no cuenta contra ellos.
  const opcionesContrato = contratos.filter((k) => k.vigente).map((k) => ({
    value: String(k.id),
    label: `${k.proceso}${k.proveedor ? ` · ${k.proveedor}` : ''}`,
  }))
  const contrato = contratos.find((k) => k.id === contratoId)
  const ayudaContrato = contrato
    ? `${plural(contrato.bienes_count ?? 0, 'equipo cubierto', 'equipos cubiertos')}${contrato.fecha_fin ? `, hasta el ${formatFecha(contrato.fecha_fin)}` : ''}.`
    : 'Entran los equipos que cubre.'
  const ayudaUbicacion = editando
    ? 'Para cambiar los equipos, agréguelos o quítelos.'
    : delProveedor ? 'Opcional: solo los equipos del contrato en ella.' : 'Entran los equipos que el sistema tiene en ella.'

  // Al editar, quién la hace ya no cambia: se dice, no se elige.
  const quienLaHace = campania && (campania.realizado_por === 'proveedor'
    ? <>La hace el proveedor del contrato <b>{campania.contrato}</b>{campania.proveedor ? ` (${campania.proveedor})` : ''}.</>
    : 'La hace TIC.')

  const enviar = ({ ubicacion_id, unidad_administrativa_id, realizado_por, contrato_vigencia_id, ...cabecera }: CampaniaFormData) =>
    (editando
      ? actualizar.mutateAsync(cabecera).then(onClose)
      : crear.mutateAsync({
          ...cabecera, realizado_por, ubicacion_id, unidad_administrativa_id,
          contrato_vigencia_id: realizado_por === 'proveedor' ? contrato_vigencia_id : null,
        }).then((c) => { onClose(); onCreada?.(c) }))
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, editando ? 'No se pudo actualizar la campaña' : 'No se pudo crear la campaña'))

  return (
    <FormModal opened onClose={onClose} title={editando ? 'Editar campaña' : 'Nueva campaña de mantenimiento preventivo'}
      onSubmit={handleSubmit(enviar)} submitLabel={editando ? 'Guardar' : 'Crear campaña'}
      submitting={crear.isPending || actualizar.isPending} size="lg">
      <Grid>
        <Grid.Col span={12}>
          {quienLaHace ? (
            <Text size="sm" c="dimmed">{quienLaHace}</Text>
          ) : (
            <Controller name="realizado_por" control={control} render={({ field }) => (
              <SegmentedControl fullWidth value={field.value} onChange={field.onChange}
                data={[{ value: 'tic', label: 'La hace TIC' }, { value: 'proveedor', label: 'La hace el proveedor de la vigencia' }]} />
            )} />
          )}
        </Grid.Col>
        <Grid.Col span={12}>
          <TextInput label="Nombre" placeholder="Ej.: Preventivo del piso 3, octubre de 2026" withAsterisk
            error={errors.nombre?.message} {...contained} {...register('nombre')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Qué se hace en cada equipo" placeholder="Ej.: Limpieza interna, cambio de pasta térmica y actualización del sistema"
            withAsterisk autosize minRows={2} description="Queda como descripción del mantenimiento en la ficha de cada equipo."
            error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        </Grid.Col>
        {delProveedor && !editando && (
          <Grid.Col span={12}>
            <Controller name="contrato_vigencia_id" control={control} render={({ field }) => (
              <Select label="Contrato de vigencia" placeholder="Seleccionar" withAsterisk searchable {...contained}
                description={ayudaContrato} nothingFoundMessage="No hay contratos vigentes."
                error={errors.contrato_vigencia_id?.message} data={opcionesContrato}
                value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
            )} />
          </Grid.Col>
        )}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="ubicacion_id" control={control} render={({ field }) => (
            <Select label="Ubicación" placeholder={delProveedor ? 'Todas las del contrato' : 'Sede y piso'}
              withAsterisk={!delProveedor} clearable={delProveedor} searchable disabled={editando} {...contained}
              description={ayudaUbicacion} error={errors.ubicacion_id?.message}
              data={ubicaciones.map((u) => ({ value: String(u.id), label: u.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="unidad_administrativa_id" control={control} render={({ field }) => (
            <Select label="Unidad" placeholder={delProveedor ? 'Todas' : 'Todas las de la ubicación'} clearable searchable disabled={editando} {...contained}
              description="Opcional: solo los equipos de esa unidad."
              error={errors.unidad_administrativa_id?.message}
              data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="tecnico_id" control={control} render={({ field }) => (
            <Select label={delProveedor ? 'Técnico de TIC que acompaña' : 'Técnico responsable'} placeholder={delProveedor ? 'Opcional' : 'Seleccionar'}
              withAsterisk={!delProveedor} clearable={delProveedor} searchable {...contained}
              description={delProveedor ? 'Hace el seguimiento; el mantenimiento queda a nombre del proveedor.' : undefined}
              error={errors.tecnico_id?.message}
              data={tecnicos.map((t) => ({ value: String(t.id), label: t.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha_inicio" control={control} render={({ field }) => (
            <DatePickerInput label="Desde" placeholder="Seleccionar" valueFormat="DD/MM/YYYY" withAsterisk
              error={errors.fecha_inicio?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha_fin" control={control} render={({ field }) => (
            <DatePickerInput label="Hasta" placeholder="Seleccionar" valueFormat="DD/MM/YYYY" withAsterisk minDate={inicio || undefined}
              error={errors.fecha_fin?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
