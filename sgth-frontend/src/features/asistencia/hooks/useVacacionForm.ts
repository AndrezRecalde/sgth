import { useEffect, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAuth } from '@/hooks/useAuth'
import { useVacacionMutations } from './useVacacionMutations'
import { useExportarVacacion } from './useExportarVacacion'
import { vacacionSchema, type VacacionFormData } from '../components/vacacion.schema'
import { diasCalendario } from '../utils/fechas'
import type { Vacacion } from '@/types/api'

/**
 * La solicitud de vacaciones: el formulario, los días que salen de las fechas,
 * el envío y los dos pasos del modal (datos y confirmación).
 *
 * `soloPropio` la limita a quien tiene la sesión: la usa «Mis vacaciones» del
 * portal, donde el servidor ni elige unidad ni elige a nombre de quién pide.
 */
export function useVacacionForm(onClose: () => void, soloPropio = false) {
  const { usuario } = useAuth()
  const propio = soloPropio ? usuario?.servidor ?? null : null
  // La unidad no decide nada: el backend guarda la del servidor. Sin ella,
  // sin embargo, el servidor no está completo y no hay jefe de quién elegir.
  const puedeRegistrar = !soloPropio || (propio !== null && !!propio.unidad_administrativa_id)

  const [paso, setPaso] = useState(0)
  const [vacacionCreada, setVacacionCreada] = useState<Vacacion | null>(null)
  const [unidadSelId, setUnidadSelId] = useState<number | null>(null)
  const [servidorSelId, setServidorSelId] = useState<number | null>(null)

  const { crear } = useVacacionMutations()
  const { exportar, exportandoId } = useExportarVacacion()

  const form = useForm<VacacionFormData>({
    resolver: zodResolver(vacacionSchema),
    defaultValues: {
      unidad_administrativa_id: propio?.unidad_administrativa_id ?? undefined,
      servidor_id:              propio?.id,
      jefe_id:                  null,
      persona_reemplaza_id:     null,
      motivo:                   '',
      fecha_inicio:             '',
      fecha_fin:                '',
      fecha_retorno:            '',
      dias_solicitados:         1,
      tipo_dias:                'calendario',
      observacion:              '',
    },
  })
  const { control, setValue } = form

  const fechaInicio = useWatch({ control, name: 'fecha_inicio' })
  const fechaFin    = useWatch({ control, name: 'fecha_fin' })

  // Días calendario, con ambos extremos, en los dos regímenes: la LOSEP
  // (art. 29) y el Código del Trabajo (art. 69), confirmado con Talento
  // Humano. Antes la LOSEP contaba solo de lunes a viernes.
  useEffect(() => {
    const dias = diasCalendario(fechaInicio, fechaFin)
    if (dias !== null) setValue('dias_solicitados', dias, { shouldValidate: true })
  }, [fechaInicio, fechaFin, setValue])

  const cerrar = () => {
    form.reset()
    setUnidadSelId(null)
    setServidorSelId(null)
    setPaso(0)
    setVacacionCreada(null)
    onClose()
  }

  const enviar = form.handleSubmit(async (values) => {
    try {
      // La unidad no viaja: el backend guarda la del servidor. En el
      // formulario de Talento Humano solo filtra a quién se elige.
      const result = await crear.mutateAsync({
        servidor_id:              values.servidor_id,
        jefe_id:                  values.jefe_id ?? null,
        persona_reemplaza_id:     values.persona_reemplaza_id ?? null,
        motivo:                   values.motivo,
        fecha_inicio:             values.fecha_inicio,
        fecha_fin:                values.fecha_fin,
        fecha_retorno:            values.fecha_retorno ?? null,
        dias_solicitados:         values.dias_solicitados,
        tipo_dias:                values.tipo_dias,
        observacion:              values.observacion ?? null,
      })
      setVacacionCreada(result ?? null)
      setPaso(1)
    } catch {
      // El hook de mutación ya notifica el error; el formulario sigue abierto
      // para corregirlo. Sin esto, el rechazo quedaba sin atrapar.
    }
  })

  const exportarCreada = () => {
    if (vacacionCreada) exportar(Number(vacacionCreada.id), vacacionCreada.folio)
  }

  return {
    form,
    propio,
    puedeRegistrar,
    unidadSelId,
    setUnidadSelId,
    servidorSelId,
    setServidorSelId,
    paso,
    vacacionCreada,
    exportando: exportandoId !== null,
    enviar,
    cerrar,
    exportarCreada,
  }
}
