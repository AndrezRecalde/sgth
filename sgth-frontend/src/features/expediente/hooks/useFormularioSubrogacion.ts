'use client'

import { useState } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { useAusenciasDelTitular } from './useSubrogaciones'
import { esNivelJerarquicoSuperior } from '../utils/subrogaciones'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type {
  AusenciaDelTitular, PuestoConRelaciones, ServidorConRelaciones, TipoSubrogacion,
} from '@/types/api'

/**
 * Lo que el formulario de subrogación deriva de lo elegido: el puesto, quién lo
 * ocupa, si la figura corresponde y qué se le muestra a Talento Humano.
 *
 * Vive fuera del JSX porque son seis reglas encadenadas —la figura depende del
 * puesto, el titular depende de la figura, la diferencia depende del
 * subrogante— y dentro del modal eran cien líneas entre los campos.
 */
export function useFormularioSubrogacion(form: UseFormReturn<SubrogacionFormData>) {
  const { control, setValue, resetField } = form

  const tipo = useWatch({ control, name: 'tipo' })
  const unidadSelId = useWatch({ control, name: 'unidad_administrativa_id' })
  const subroganteId = useWatch({ control, name: 'servidor_subrogante_id' })
  const subrogadoId = useWatch({ control, name: 'servidor_subrogado_id' })
  const puestoSelId = useWatch({ control, name: 'puesto_subrogado_id' })
  const ausenciaTipo = useWatch({ control, name: 'ausencia_tipo' })
  const ausenciaId = useWatch({ control, name: 'ausencia_id' })

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
   * Las ausencias del titular que pueden motivar la subrogación (fase 5.2). La
   * subrogación ya no se motiva eligiendo «Vacaciones» de una lista: se enlaza
   * con la ausencia registrada, cabe dentro de sus fechas y la sigue —si la
   * ausencia se anula, se cancela—.
   */
  const ausencias = useAusenciasDelTitular(tipo === 'subrogacion' ? subrogadoId : null)
  const ausenciaSel: AusenciaDelTitular | null = (ausencias.data ?? [])
    .find((a) => a.tipo === ausenciaTipo && a.id === ausenciaId) ?? null

  /** La ausencia es del titular: cambiar de titular la deja sin sentido. */
  const olvidarAusencia = () => {
    setValue('ausencia_tipo', null)
    setValue('ausencia_id', null)
  }

  /**
   * Elegir la ausencia propone sus fechas: la subrogación cabe dentro de ella,
   * y lo habitual es que la cubra entera. Se pueden acortar.
   */
  const elegirAusencia = (valor: string | null) => {
    if (!valor) return olvidarAusencia()
    if (valor === 'otra') {
      setValue('ausencia_tipo', 'otra')
      setValue('ausencia_id', null)
      return
    }

    const elegida = (ausencias.data ?? []).find((a) => `${a.tipo}:${a.id}` === valor)
    if (!elegida) return olvidarAusencia()

    setValue('ausencia_tipo', elegida.tipo)
    setValue('ausencia_id', elegida.id)
    setValue('fecha_inicio', elegida.desde)
    setValue('fecha_fin', elegida.hasta ?? '')
  }

  /**
   * Cambiar de figura arrastra al titular y a la ausencia: en encargo no hay a
   * quién reemplazar, y en subrogación el titular vuelve a salir del puesto.
   */
  const elegirTipo = (valor: string) => {
    const figura = valor as TipoSubrogacion

    setValue('tipo', figura)
    setValue(
      'servidor_subrogado_id',
      figura === 'encargo' ? null : titularPorDefecto(puestoSelId),
    )
    olvidarAusencia()
  }

  /**
   * Otra unidad, otros puestos: el puesto se vacía y con él el titular y su
   * ausencia. Antes solo se vaciaba el puesto, y el titular del anterior seguía
   * elegido —sin puesto a la vista— hasta que se elegía otro.
   */
  const elegirUnidad = (unidadId: number | undefined, alCambiar: (id?: number) => void) => {
    alCambiar(unidadId)
    resetField('puesto_subrogado_id')
    setValue('servidor_subrogado_id', null)
    olvidarAusencia()
  }

  /** De aquí sale el titular, así que no puede quedar el del puesto anterior. */
  const elegirPuesto = (puestoId: number | undefined, alCambiar: (id?: number) => void) => {
    alCambiar(puestoId)
    setValue(
      'servidor_subrogado_id',
      tipo === 'encargo' ? null : titularPorDefecto(puestoId),
    )
    olvidarAusencia()
  }

  const elegirTitular = (servidorId: number | null) => {
    setValue('servidor_subrogado_id', servidorId)
    olvidarAusencia()
  }

  const elegirSubrogante = (servidor: ServidorConRelaciones | null) => {
    setSubroganteSel(servidor)
  }

  // La figura la determina el puesto, no quien llena el formulario: un puesto
  // vacante se encarga y uno con titular se subroga. Se avisa en vez de dejar
  // que el backend lo rechace al guardar.
  const figuraEquivocada =
    puestoSel != null && (tipo === 'subrogacion' ? puestoVacante : !puestoVacante)

  /**
   * Solo se subroga un puesto del nivel jerárquico superior (LOSEP, Art. 126).
   * Si el puesto tiene titular y no lo es, no hay figura que corresponda: se
   * dice aquí en vez de dejar que el backend lo rechace al guardar.
   */
  const puestoNoSubrogable =
    tipo === 'subrogacion' && puestoSel != null && !puestoVacante && !esNivelJerarquicoSuperior(puestoSel)

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
    puestoNoSubrogable,
    ausencias: ausencias.data ?? [],
    cargandoAusencias: ausencias.isFetching,
    ausenciaSel,
    ausenciaTipo,
    rmuSubrogante,
    nombrePuesto: puestoSel?.cargo?.nombre ?? 'el puesto',
    elegirTipo,
    elegirUnidad,
    elegirPuesto,
    elegirTitular,
    elegirAusencia,
    elegirSubrogante,
    limpiarSubrogante: () => setSubroganteSel(null),
  }
}
