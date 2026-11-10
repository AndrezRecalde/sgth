'use client'

import { useState } from 'react'
import { useDebouncedValue } from '@mantine/hooks'
import type { PuestoConRelaciones } from '@/types/api'
import {
  SOLO_PUESTOS_DE_CONTRATO, avisoDelTipoDePuesto, tipoDePuestoPara,
} from '../constants/clasificacionPuesto'
import { usePuestos } from './usePuestos'

/**
 * Los formularios piden 100 puestos de la unidad de una vez. Con eso basta
 * salvo en una dirección grande, y ahí el recorte se avisaba y no había nada
 * que hacer: el endpoint no aceptaba `search`. Ahora sí, así que lo que se
 * teclea viaja al servidor y el recorte deja de ser un callejón.
 */
const PUESTOS_POR_PAGINA = 100

// Una petición por tecla: escribir «analista» dispararía ocho y solo importa
// la última. Mismo retardo que BuscarServidorSelect y BuscarPuestoSelect.
const RETARDO_BUSQUEDA_MS = 300

interface Opciones {
  /** Puesto elegido hoy en el formulario, para no perderlo de vista. */
  seleccionadoId?: number | string | null
  /**
   * La modalidad del vínculo: se ofrecen solo los puestos del tipo que admite
   * (P9). Sin ella, todos.
   */
  modalidad?: string | null
  /** Cubre la ausencia de un servidor: entra al puesto del titular, de cualquier tipo [TH D13]. */
  esReemplazo?: boolean
}

/**
 * Puestos de una unidad para un `Select`, con búsqueda del lado del servidor
 * **solo cuando hace falta**.
 *
 * Mientras la unidad quepa en una página —que es lo normal: ninguna dirección
 * del GAD tiene cien puestos— no se le pregunta nada al servidor y filtra
 * Mantine sobre lo que ya está en memoria, igual que siempre. El término viaja
 * únicamente cuando el listado viene recortado, que es el único caso en que
 * filtrar en el cliente esconde puestos que existen.
 *
 * Hacerlo al revés —mandar siempre lo tecleado— cambiaba el comportamiento de
 * todos los formularios para resolver un caso que hoy no se da, y además
 * Mantine mete la etiqueta del puesto elegido en su propia búsqueda al
 * seleccionarlo (ver el `useEffect` sobre `[value, selectedOption]` en
 * `Select.cjs`), así que cada selección habría disparado una consulta de más.
 */
export function usePuestosDeUnidad(
  unidadId?: number | string | null,
  { seleccionadoId, modalidad, esReemplazo = false }: Opciones = {},
) {
  const [texto, setTexto] = useState('')
  const [termino] = useDebouncedValue(texto, RETARDO_BUSQUEDA_MS)

  const habilitado = Boolean(unidadId)

  const paramsUnidad = {
    unidad_administrativa_id: Number(unidadId),
    per_page: PUESTOS_POR_PAGINA,
  }

  const { data: base } = usePuestos(
    habilitado ? paramsUnidad : undefined,
    { habilitado },
  )

  const enPagina = (base?.data ?? []) as PuestoConRelaciones[]
  const totalEnUnidad = base?.total ?? 0

  // Se decide con la consulta SIN término, que no cambia mientras se teclea:
  // derivarlo del resultado filtrado se apagaría solo al primer acierto y
  // volvería a encenderse al borrar, pidiendo y dejando de pedir en bucle.
  const recortada = totalEnUnidad > enPagina.length

  const busqueda = termino.trim()
  const buscando = habilitado && recortada && busqueda.length > 0

  const { data: filtrado, isFetching } = usePuestos(
    buscando ? { ...paramsUnidad, search: busqueda } : undefined,
    { habilitado: buscando },
  )

  const encontrados = (filtrado?.data ?? []) as PuestoConRelaciones[]
  const recibidos = buscando ? encontrados : enPagina
  const total = buscando ? (filtrado?.total ?? 0) : totalEnUnidad

  /*
  | El puesto elegido tiene que seguir entre las opciones aunque la búsqueda ya
  | no lo devuelva: un `Select` de Mantine cuyo `value` no está en su `data` no
  | enseña la etiqueta, enseña el número, y el campo pasaría a leerse «57».
  | Por eso la consulta sin término va aparte: queda en caché y de ahí sale.
  */
  const seleccionado = seleccionadoId == null
    ? undefined
    : [...enPagina, ...encontrados].find((p) => p.id === Number(seleccionadoId))

  /*
  | Solo los del tipo que admite la modalidad (P9). Se filtra aquí y no en el
  | servidor: el listado ya trae el tipo, cambiar de modalidad no pide nada, y
  | el elegido sigue encontrándose aunque no sea del tipo. Ese se queda a la
  | vista con su aviso, en vez de leerse «57».
  */
  const admitido = tipoDePuestoPara(modalidad, esReemplazo)
  const delTipo = recibidos.filter((p) => !admitido || (p.tipo ?? 'plantilla') === admitido)

  const puestos = seleccionado && !delTipo.some((p) => p.id === seleccionado.id)
    ? [seleccionado, ...delTipo]
    : delTipo

  const truncados = total > recibidos.length
  const descripcionRecorte = truncados
    ? `Se muestran ${recibidos.length} de ${total} puestos. Escriba para buscar entre todos.`
    : undefined

  return {
    puestos,
    total,
    truncados,
    buscando: isFetching,
    /** Texto del aviso de recorte, o undefined si no hay nada que avisar. */
    descripcionRecorte,
    /** La descripción del campo: el recorte, o que solo se ven los de contrato. */
    descripcion: descripcionRecorte ?? (admitido === 'contrato' ? SOLO_PUESTOS_DE_CONTRATO : undefined),
    /** Si el puesto elegido no es del tipo que admite la modalidad, por qué. */
    avisoDelTipo: avisoDelTipoDePuesto(seleccionado?.tipo, admitido),
    searchValue: texto,
    onSearchChange: setTexto,
  }
}
