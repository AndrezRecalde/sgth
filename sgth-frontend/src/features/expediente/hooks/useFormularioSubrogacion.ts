'use client'

import { useState } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { MOTIVO_POR_DEFECTO } from '../utils/subrogaciones'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type { PuestoConRelaciones, ServidorConRelaciones, TipoSubrogacion } from '@/types/api'

/**
 * Lo que el formulario de subrogación deriva de lo elegido: el puesto, quién lo
 * ocupa, si la figura corresponde y qué se le muestra a Talento Humano.
 *
 * Vive fuera del JSX porque son seis reglas encadenadas —la figura depende del
 * puesto, el titular depende de la figura, la diferencia depende del
 * subrogante— y dentro del modal eran cien líneas entre los campos.
 */
export function useFormularioSubrogacion(form: UseFormReturn<SubrogacionFormData>) {
  const { control, setValue } = form

  const tipo = useWatch({ control, name: 'tipo' })
  const unidadSelId = useWatch({ control, name: 'unidad_administrativa_id' })
  const subroganteId = useWatch({ control, name: 'servidor_subrogante_id' })
  const subrogadoId = useWatch({ control, name: 'servidor_subrogado_id' })
  const puestoSelId = useWatch({ control, name: 'puesto_subrogado_id' })

  /**
   * El subrogante llega entero desde el buscador, no de una lista precargada:
   * hace falta su contrato vigente para calcular la diferencia que se le va a
   * pagar, y pedir los 500 servidores de la institución para eso castigaba a
   * quien abre el modal desde una conexión de la Prefectura.
   */
  const [subroganteSel, setSubroganteSel] = useState<ServidorConRelaciones | null>(null)

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })

  // Sin unidad no se piden puestos: antes se pedía la primera página de TODOS
  // los puestos para alimentar un Select que está deshabilitado.
  const { data: puestosData } = usePuestos(
    unidadSelId ? { unidad_administrativa_id: Number(unidadSelId), per_page: 100 } : undefined,
    { habilitado: Boolean(unidadSelId) },
  )
  const puestos = puestosData?.data ?? []

  const unidadSel = unidades.find((u) => u.id === Number(unidadSelId)) ?? null
  const puestoSel = puestos.find((p) => p.id === Number(puestoSelId)) ?? null

  // La diferencia se calcula contra lo que el subrogante gana hoy, que vive en
  // su contrato vigente; si no lo tiene, se cae a la R.M.U. de su puesto.
  const rmuSubroganteRaw = subroganteSel?.contrato_vigente?.remuneracion
    ?? subroganteSel?.puesto?.rmu
  const rmuSubrogante = rmuSubroganteRaw != null ? Number(rmuSubroganteRaw) : null

  /**
   * Quiénes ocupan el puesto. El titular no se pide aparte —pedirlo permitía
   * nombrar titular a alguien que nunca ocupó ese puesto, y el documento
   * firmado afirmaba un reemplazo que no ocurrió—, pero un puesto con varias
   * plazas tiene varios ocupantes y ahí sí hay que elegir cuál se subroga: el
   * formulario se quedaba con el primero de la lista, sin decirlo.
   */
  const ocupantesDe = (puestoId?: number | null) =>
    puestos.find((p) => p.id === Number(puestoId))?.ocupantes ?? []

  const ocupantes = ocupantesDe(puestoSelId)
  const puestoVacante = puestoSel != null && ocupantes.length === 0
  const hayQueElegirTitular = tipo === 'subrogacion' && ocupantes.length > 1

  /** El titular que corresponde por defecto: el único ocupante, o ninguno. */
  const titularPorDefecto = (puestoId?: number | null): number | null => {
    const lista = ocupantesDe(puestoId)
    return lista.length === 1 ? lista[0].id : null
  }

  /**
   * Cambiar de figura arrastra al titular y al motivo: en encargo no hay a
   * quién reemplazar, y en subrogación el titular vuelve a salir del puesto.
   */
  const elegirTipo = (valor: string) => {
    const figura = valor as TipoSubrogacion

    setValue('tipo', figura)
    setValue(
      'servidor_subrogado_id',
      figura === 'encargo' ? null : titularPorDefecto(puestoSelId),
    )
    setValue('motivo', MOTIVO_POR_DEFECTO[figura])
  }

  /** De aquí sale el titular, así que no puede quedar el del puesto anterior. */
  const elegirPuesto = (puestoId: number | undefined, alCambiar: (id?: number) => void) => {
    alCambiar(puestoId)
    setValue(
      'servidor_subrogado_id',
      tipo === 'encargo' ? null : titularPorDefecto(puestoId),
    )
  }

  const elegirSubrogante = (servidor: ServidorConRelaciones | null) => {
    setSubroganteSel(servidor)
  }

  // La figura la determina el puesto, no quien llena el formulario: un puesto
  // vacante se encarga y uno con titular se subroga. Se avisa en vez de dejar
  // que el backend lo rechace al guardar.
  const figuraEquivocada =
    puestoSel != null && (tipo === 'subrogacion' ? puestoVacante : !puestoVacante)

  return {
    tipo,
    unidades,
    puestos: puestos as PuestoConRelaciones[],
    unidadSel,
    puestoSel,
    unidadSelId,
    subroganteId,
    subrogadoId,
    puestoSelId,
    ocupantes,
    puestoVacante,
    hayQueElegirTitular,
    figuraEquivocada,
    rmuSubrogante,
    nombrePuesto: puestoSel?.cargo?.nombre ?? 'el puesto',
    elegirTipo,
    elegirPuesto,
    elegirSubrogante,
    limpiarSubrogante: () => setSubroganteSel(null),
  }
}
