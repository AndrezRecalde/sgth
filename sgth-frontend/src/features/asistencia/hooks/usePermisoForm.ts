import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAuth } from '@/hooks/useAuth'
import { usePermisoMutations } from './usePermisoMutations'
import { useExportarPermiso } from './useExportarPermiso'
import { permisoSchema, type PermisoFormData } from '../components/permiso.schema'
import type { PermisoServidor } from '@/types/api'

/**
 * El registro de un permiso: a nombre de quién se puede registrar, el
 * formulario, el envío y los dos pasos del modal (datos y confirmación).
 */
export function usePermisoForm(onClose: () => void) {
  // Talento Humano emite permisos a nombre de cualquier servidor; el resto,
  // solo el propio. Es la misma regla que aplica el backend
  // (`PermisoServidorPolicy::crear`): ofrecer aquí la lista de toda la
  // institución solo serviría para que el alta respondiera 403.
  const { usuario, hasPermiso } = useAuth()
  const emiteATodos = hasPermiso('registrar-permisos-servidores')
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
      const result = await crear.mutateAsync({
        unidad_administrativa_id: values.unidad_administrativa_id,
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
    } catch {
      // El hook de mutación ya notifica el error; el formulario sigue abierto
      // para corregirlo. Sin esto, el rechazo quedaba sin atrapar.
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
