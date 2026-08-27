'use client'

import { useEffect } from 'react'
import { Select } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  codigosDePartida, exigeElegirPartida,
} from '@/features/expediente/utils/partidaPorModalidad'
import { usePartidasPresupuestarias } from '../hooks/usePartidasPresupuestarias'

interface Props {
  value?: number | null
  onChange: (value: number | null) => void
  label?: string
  placeholder?: string
  description?: string
  error?: string
  required?: boolean
  disabled?: boolean
  /**
   * Limita el listado a partidas con disponibilidad presupuestaria
   * verificada. Útil donde la selección compromete presupuesto (acciones de
   * personal con efecto económico, Art. 105 LOSEP); en el catálogo de
   * Puestos se deja en false para no ocultar partidas aún sin verificar.
   */
  soloDisponibles?: boolean
  /**
   * Limita el listado a las partidas que pagan esta modalidad de vinculación
   * y preselecciona la única posible cuando no hay ambigüedad.
   *
   * Sin esto, registrar un ingreso obligaba a buscar entre veinte partidas la
   * que correspondía — y equivocarse no costaba nada. Es una ayuda, no un
   * candado: si la modalidad no tiene correspondencia definida, se muestra el
   * catálogo completo.
   */
  modalidad?: string | null
}

/**
 * Selector del catálogo de partidas presupuestarias, compartido por Puestos
 * y por las acciones de personal para no duplicar el fetch ni el formato de
 * la etiqueta.
 */
export function SelectPartidaPresupuestaria({
  value,
  onChange,
  label = 'Partida presupuestaria',
  placeholder = 'Seleccione una partida...',
  description,
  error,
  required,
  disabled,
  soloDisponibles = false,
  modalidad = null,
}: Props) {
  const contained = useContainedInput()

  const { data: partidas = [], isLoading } = usePartidasPresupuestarias({
    activo: true,
    ...(soloDisponibles ? { disponible: true } : {}),
  })

  const codigos = codigosDePartida(modalidad)

  // Se filtra solo si la modalidad tiene correspondencia y esas partidas están
  // en el catálogo: si no, mostrar una lista vacía sería peor que mostrarlas
  // todas.
  // Se respeta el orden del mapeo, no el del catálogo: cuando hay dos opciones
  // la primera es la que Financiera señaló como habitual, y es la que verá
  // arriba quien elija sin detenerse a comparar.
  const aplicables = codigos.length > 0
    ? partidas
      .filter((p) => codigos.includes(p.codigo ?? ''))
      .sort((a, b) => codigos.indexOf(a.codigo ?? '') - codigos.indexOf(b.codigo ?? ''))
    : []

  const listado = aplicables.length > 0 ? aplicables : partidas

  const options = listado.map((p) => ({
    value: String(p.id),
    label: `${p.codigo} — ${p.descripcion}`,
  }))

  // Con una sola partida posible no hay nada que decidir: se deja puesta. El
  // campo sigue siendo editable por si aparece una excepción.
  //
  // Va en un efecto y no en el render porque avisarle al padre mientras se
  // dibuja es lo que produce el "cannot update a component while rendering".
  const unica = aplicables.length === 1 ? aplicables[0] : null
  const unicaId = unica?.id ?? null

  // Al cambiar de modalidad la partida anterior puede dejar de corresponder.
  // Desaparece del listado pero seguía en el formulario: el campo se veía
  // lleno y se guardaba una imputación que ya no era la de esa modalidad.
  const vigente = aplicables.length === 0
    || value == null
    || aplicables.some((p) => p.id === value)

  useEffect(() => {
    if (unicaId !== null && value !== unicaId) {
      onChange(unicaId)
    } else if (!vigente) {
      onChange(null)
    }
    // onChange se omite a propósito: los padres la redefinen en cada render y
    // volvería a disparar el efecto sin que nada haya cambiado.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unicaId, value, vigente])

  const ayuda = description
    ?? (exigeElegirPartida(modalidad)
      ? 'Esta modalidad se imputa a gasto corriente o de inversión: elija según el fondo que financia el contrato.'
      : undefined)

  return (
    <Select
      label={label}
      placeholder={isLoading ? 'Cargando partidas...' : placeholder}
      description={ayuda}
      data={options}
      value={value ? String(value) : null}
      onChange={(v) => onChange(v ? Number(v) : null)}
      error={error}
      required={required}
      disabled={disabled || isLoading}
      searchable
      clearable
      nothingFoundMessage="Sin partidas que coincidan"
      {...contained}
    />
  )
}
