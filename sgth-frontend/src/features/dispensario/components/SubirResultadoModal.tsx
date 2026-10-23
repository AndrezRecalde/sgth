'use client'

import { FileInput, Select, Stack, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { formatFechaMes, hoyIso } from '@/lib/fecha'
import { useSubirResultado } from '../hooks/useResultadoMedico'
import { useOrdenesExamen } from '../hooks/useExamenes'
import {
  ARCHIVO_MAX_MB, NINGUNA, resultadoMedicoSchema, type ResultadoMedicoFormData,
} from '../schemas/resultadoMedico.schema'
import { TIPO_RESULTADO_OPTIONS } from '../services/resultadoMedicoService'
import { consultaMedicaService } from '../services/consultaMedicaService'
import { descripcionSugerida, ordenAbierta, pendientesDe, tipoSugerido } from '../utils/resultadoDeOrden'
import { OrdenDelResultado } from './examenes/OrdenDelResultado'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { OrdenExamen } from '../services/examenService'

interface Props {
  opened:            boolean
  onClose:           () => void
  historiaClinicaId: number
  /** La consulta abierta, si se sube desde el panel de atención. */
  consulta?:         ConsultaMedica | null
  /** La orden de la que viene, si se abrió desde ella. */
  ordenInicial?:     OrdenExamen | null
}

/** Subir un resultado: suelto, como siempre, o contra una orden abierta del paciente. */
export function SubirResultadoModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, historiaClinicaId, consulta = null, ordenInicial = null }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const subir = useSubirResultado()
  const { data: ordenes = [] } = useOrdenesExamen(historiaClinicaId)
  const abiertas = ordenes.filter(ordenAbierta)

  // A qué consulta pertenece: la abierta, la que pidió la orden u otra de las
  // últimas del paciente; un laboratorio que llega una semana después es de
  // la consulta que lo pidió.
  const { data: delPaciente } = useQuery({
    queryKey: ['consultas', 'para-resultado', historiaClinicaId],
    queryFn:  () => consultaMedicaService.listarPorHistoria(historiaClinicaId, { per_page: 20 }),
    staleTime: 1000 * 60,
  })
  const opcionesConsulta = [
    ...(consulta ? [{ value: String(consulta.id), label: `Esta consulta (${formatFechaMes(consulta.fecha_consulta)})` }] : []),
    ...(delPaciente?.consultas ?? [])
      .filter((c) => c.id !== consulta?.id)
      .map((c) => ({ value: String(c.id), label: `Consulta del ${formatFechaMes(c.fecha_consulta)}` })),
    { value: NINGUNA, label: 'Sin consulta' },
  ]

  const inicialesDe = (o: OrdenExamen | null) => o?.items.filter((i) => i.estado === 'pendiente') ?? []
  const { control, register, handleSubmit, setValue, setError, formState: { errors, dirtyFields } } =
    useForm<ResultadoMedicoFormData>({
      resolver: zodResolver(resultadoMedicoSchema),
      defaultValues: {
        consulta_medica_id: String(ordenInicial?.consulta_medica_id ?? consulta?.id ?? NINGUNA),
        orden_examen_id:    ordenInicial ? String(ordenInicial.id) : NINGUNA,
        items:              ordenInicial ? pendientesDe(ordenInicial) : [],
        tipo:               tipoSugerido(inicialesDe(ordenInicial)),
        descripcion:        descripcionSugerida(inicialesDe(ordenInicial)),
        fecha_resultado:    hoyIso(),
        archivo:            null,
      },
    })
  const [ordenId, items] = useWatch({ control, name: ['orden_examen_id', 'items'] })

  // Con orden, el tipo y la descripción se proponen a partir de lo marcado,
  // hasta que se escriban a mano.
  const proponer = (orden: OrdenExamen | undefined, ids: number[]) => {
    const marcados = orden?.items.filter((i) => ids.includes(i.id)) ?? []
    if (!dirtyFields.tipo) setValue('tipo', tipoSugerido(marcados))
    if (!dirtyFields.descripcion) setValue('descripcion', descripcionSugerida(marcados))
  }
  const elegirOrden = (id: string) => {
    const orden = abiertas.find((o) => String(o.id) === id)
    const ids = orden ? pendientesDe(orden) : []
    setValue('orden_examen_id', id)
    setValue('items', ids, { shouldValidate: errors.items !== undefined })
    if (orden?.consulta_medica_id && opcionesConsulta.some((c) => c.value === String(orden.consulta_medica_id))) {
      setValue('consulta_medica_id', String(orden.consulta_medica_id))
    }
    proponer(orden, ids)
  }
  const elegirItems = (ids: number[]) => {
    setValue('items', ids, { shouldValidate: errors.items !== undefined })
    proponer(abiertas.find((o) => String(o.id) === ordenId), ids)
  }

  const enviar = (v: ResultadoMedicoFormData) => {
    const fd = new FormData()
    fd.append('historia_clinica_id', String(historiaClinicaId))
    if (v.consulta_medica_id !== NINGUNA) fd.append('consulta_medica_id', v.consulta_medica_id)
    if (v.orden_examen_id !== NINGUNA) {
      fd.append('orden_examen_id', v.orden_examen_id)
      v.items.forEach((id) => fd.append('items[]', String(id)))
    }
    fd.append('tipo', v.tipo)
    fd.append('descripcion', v.descripcion.trim())
    fd.append('fecha_resultado', v.fecha_resultado)
    if (v.archivo) fd.append('archivo', v.archivo)

    subir.mutateAsync(fd).then(onClose).catch((e) => erroresAlFormulario(
      e, setError, Object.keys(resultadoMedicoSchema.shape), 'No se pudo subir el resultado',
    ))
  }

  return (
    <FormModal opened onClose={onClose} title="Subir resultado médico" size="lg"
      onSubmit={handleSubmit(enviar)} submitLabel="Subir resultado" submitting={subir.isPending}>
      <Stack gap="sm">
        <OrdenDelResultado ordenes={abiertas} ordenId={ordenId} onOrden={elegirOrden}
          items={items} onItems={elegirItems}
          errorOrden={errors.orden_examen_id?.message} errorItems={errors.items?.message} />

        <Controller name="consulta_medica_id" control={control} render={({ field }) => (
          <Select label="Consulta" allowDeselect={false} {...contained}
            description="Si es de lo que se pidió en otra consulta, elíjala: así aparece en ella."
            data={opcionesConsulta} value={field.value} onChange={(v) => field.onChange(v ?? NINGUNA)}
            error={errors.consulta_medica_id?.message} />
        )} />

        <Controller name="tipo" control={control} render={({ field }) => (
          <Select label="Tipo de resultado" placeholder="Seleccione" withAsterisk {...contained}
            data={TIPO_RESULTADO_OPTIONS} value={field.value || null}
            onChange={(v) => field.onChange(v ?? '')} error={errors.tipo?.message} />
        )} />

        <Textarea label="Descripción" placeholder="Ej.: Hemograma completo, radiografía de tórax AP…" withAsterisk
          autosize minRows={2} maxLength={500} {...contained} {...register('descripcion')}
          error={errors.descripcion?.message} />

        <Controller name="fecha_resultado" control={control} render={({ field }) => (
          // Como el servidor: un resultado no tiene fecha futura.
          <DatePickerInput label="Fecha del resultado" valueFormat="DD/MM/YYYY" maxDate={new Date()} withAsterisk
            {...contained} value={field.value || null} onChange={(v) => field.onChange(v ?? '')}
            error={errors.fecha_resultado?.message} />
        )} />

        <Controller name="archivo" control={control} render={({ field }) => (
          <FileInput label="Archivo" placeholder="Seleccionar archivo…" withAsterisk
            description={`PDF, JPG o PNG — máximo ${ARCHIVO_MAX_MB} MB`}
            accept="application/pdf,image/jpeg,image/png" {...contained}
            value={field.value} onChange={field.onChange} error={errors.archivo?.message} />
        )} />
      </Stack>
    </FormModal>
  )
}
