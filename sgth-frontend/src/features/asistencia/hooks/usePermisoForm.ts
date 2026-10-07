import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAuth } from '@/hooks/useAuth'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { usePermisoMutations } from './usePermisoMutations'
import { useExportarPermiso } from './useExportarPermiso'
import { permisoSchema, type PermisoFormData } from '../components/permiso.schema'
import type { PermisoServidor } from '@/types/api'

/**
 * El registro de un permiso: a nombre de quién se puede registrar, el
 * formulario, el envío y los dos pasos del modal (datos y confirmación).
 *
 * `soloPropio` limita el registro al permiso de quien tiene la sesión aunque
 * sea de Talento Humano. Lo usa «Mis permisos» del portal, donde un permiso
 * registrado a nombre de otro no aparecería en la lista.
 */
export function usePermisoForm(onClose: () => void, soloPropio = false) {
  // Talento Humano emite permisos a nombre de cualquier servidor; el resto,
  // solo el propio. Es la misma regla que aplica el backend
  // (`PermisoServidorPolicy::crear`): ofrecer aquí la lista de toda la
  // institución solo serviría para que el alta respondiera 403.
  const { usuario, hasPermiso } = useAuth()
  const esTalentoHumano = hasPermiso('registrar-permisos-servidores')
  const emiteATodos = !soloPropio && esTalentoHumano
  // Dirigir el permiso al jefe de Talento Humano es decisión de TH, también
  // cuando registra el suyo desde el portal (`PermisoServidorPolicy::
  // dirigirATalentoHumano`). Al servidor no se le ofrece: saltarse al jefe
  // inmediato respondería 403.
  const puedeDirigirATh = esTalentoHumano
  const propio = usuario?.servidor ?? null
  const unidadPropia = propio?.unidad_administrativa_id ?? null
  const puedeRegistrar = emiteATodos || (propio !== null && unidadPropia !== null)
  const unidadInicial = emiteATodos ? null : unidadPropia

  const [paso, setPaso] = useState(0)
  const [permisoCreado, setPermisoCreado] = useState<PermisoServidor | null>(null)
  const [unidadSelId, setUnidadSelId] = useState<number | null>(unidadInicial)

  const { crear } = usePermisoMutations()
  const { exportar, exportandoId } = useExportarPermiso()

  const form = useForm<PermisoFormData>({
    resolver: zodResolver(permisoSchema),
    defaultValues: {
      unidad_administrativa_id:  unidadInicial ?? undefined,
      servidor_id:               emiteATodos ? undefined : propio?.id,
      jefe_id:                   null,
      dirigido_a_talento_humano: false,
      tipo:                      'personal',
      fecha:                     '',
      hora_inicio:               '08:00',
      hora_fin:                  '12:00',
      observacion:               '',
    },
  })

  const cerrar = () => {
    form.reset()
    setUnidadSelId(unidadInicial)
    setPaso(0)
    setPermisoCreado(null)
    onClose()
  }

  const enviar = form.handleSubmit(async (values) => {
    try {
      // La unidad no viaja: el backend usa la del servidor. En el formulario
      // solo sirve para filtrar a quién se elige.
      const result = await crear.mutateAsync({
        servidor_id:              values.servidor_id,
        // Con la opción activa el jefe lo resuelve el backend: mandar además un
        // `jefe_id` sería ofrecer un dato que se va a ignorar.
        jefe_id:                  values.dirigido_a_talento_humano
          ? null
          : (values.jefe_id ?? null),
        dirigido_a_talento_humano: values.dirigido_a_talento_humano,
        tipo:                     values.tipo,
        fecha:                    values.fecha,
        hora_inicio:              values.hora_inicio,
        hora_fin:                 values.hora_fin,
        observacion:              values.observacion ?? null,
      })
      setPermisoCreado(result ?? null)
      setPaso(1)
    } catch (error) {
      // El hook de mutación ya notifica el error y el formulario sigue abierto
      // para corregirlo. Un 422 de validación además marca su campo (regla
      // 07): solo con la notificación, quien veía «Elija al jefe inmediato»
      // tenía que adivinar dónde. Las reglas de negocio no traen campo y se
      // quedan en la notificación.
      const campos = erroresDeCampo(error)
      const enFormulario = form.getValues()

      for (const [campo, mensaje] of Object.entries(campos ?? {})) {
        if (campo in enFormulario) {
          form.setError(campo as keyof PermisoFormData, { type: 'server', message: mensaje })
        }
      }
    }
  })

  const exportarCreado = () => {
    if (permisoCreado) exportar(Number(permisoCreado.id), permisoCreado.folio)
  }

  const nombrePropio = propio
    ? [[propio.apellido, propio.nombre].filter(Boolean).join(' '), propio.cedula]
        .filter(Boolean)
        .join(' — ')
    : ''

  return {
    form,
    emiteATodos,
    puedeDirigirATh,
    puedeRegistrar,
    nombrePropio,
    unidadSelId,
    setUnidadSelId,
    paso,
    permisoCreado,
    exportando: exportandoId !== null,
    enviar,
    cerrar,
    exportarCreado,
  }
}
