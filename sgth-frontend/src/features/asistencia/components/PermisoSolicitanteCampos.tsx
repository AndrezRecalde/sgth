'use client'

import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { DirigirATalentoHumano } from './DirigirATalentoHumano'
import { PermisoSolicitantePropio } from './PermisoSolicitantePropio'
import { PermisoSolicitanteTalentoHumano } from './PermisoSolicitanteTalentoHumano'
import type { PermisoFormData } from './permiso.schema'

interface Props {
  form:         UseFormReturn<PermisoFormData>
  /** Talento Humano elige unidad y servidor; el resto registra solo el propio. */
  emiteATodos:  boolean
  nombrePropio: string
  unidadSelId:  number | null
  onUnidad:     (id: number | null) => void
}

/**
 * A nombre de quién va el permiso y quién lo firma.
 *
 * Cada caso vive en su componente porque consulta una fuente distinta:
 * Talento Humano, el listado de unidades y de expedientes; el servidor, sus
 * compañeros de unidad desde el autoservicio. Con un solo componente, quien
 * registraba lo suyo también pedía el listado de expedientes, que le responde
 * 403, y se quedaba sin jefes que elegir.
 */
export function PermisoSolicitanteCampos({
  form, emiteATodos, nombrePropio, unidadSelId, onUnidad,
}: Props) {
  const { control, setValue } = form
  const servidorId = useWatch({ control, name: 'servidor_id' })

  return (
    <>
      {emiteATodos ? (
        <PermisoSolicitanteTalentoHumano
          form={form}
          unidadSelId={unidadSelId}
          onUnidad={onUnidad}
        />
      ) : (
        <PermisoSolicitantePropio form={form} nombrePropio={nombrePropio} />
      )}

      {/*
        Omitir al jefe inmediato. Quien firma entonces no se elige: es el
        jefe vigente de la unidad de Talento Humano, o quien lo subrogue, y lo
        resuelve el backend con la misma regla que las Acciones de Personal.
      */}
      <Controller
        name="dirigido_a_talento_humano"
        control={control}
        render={({ field }) => (
          <DirigirATalentoHumano
            activo={field.value}
            servidorId={servidorId || undefined}
            onCambiar={(activo) => {
              field.onChange(activo)
              if (activo) setValue('jefe_id', null)
            }}
          />
        )}
      />
    </>
  )
}
