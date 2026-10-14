import type { FichaTipo } from '../services/tipos'

/** Las fichas que puede tener un tipo de equipo (`FichaTipoBien`). */
export const FICHAS: { value: FichaTipo; label: string; descripcion: string }[] = [
  { value: 'computador', label: 'Computador', descripcion: 'Procesador, memoria, disco y sistema operativo' },
  { value: 'impresora', label: 'Impresora', descripcion: 'Conexión, consumible y color' },
  { value: 'monitor', label: 'Monitor', descripcion: 'Pulgadas y entradas de video' },
  { value: 'escaner', label: 'Escáner', descripcion: 'Alimentación de hojas y conexión' },
  { value: 'general', label: 'General', descripcion: 'Solo los datos comunes' },
]

export const ETIQUETA_FICHA = Object.fromEntries(FICHAS.map((f) => [f.value, f.label])) as Record<FichaTipo, string>

/** Las características que se guardan en `caracteristicas_tecnicas`. */
export type ClaveCaracteristica =
  | 'procesador' | 'memoria_ram' | 'tipo_disco' | 'capacidad_disco' | 'sistema_operativo'
  | 'conexion' | 'consumible' | 'color' | 'pulgadas' | 'entradas_video' | 'alimentacion'

export type CampoRed = 'nombre_red' | 'usuario_dominio' | 'direccion_ip'

type Opcion = { value: string; label: string }

export type CampoCaracteristica = {
  clave: ClaveCaracteristica
  etiqueta: string
  placeholder: string
  /** Con opciones, es una lista; sin ellas, texto libre. */
  opciones?: Opcion[]
}

const CONEXION: Opcion[] = [
  { value: 'red', label: 'Red (cable)' },
  { value: 'wifi', label: 'Wi-Fi' },
  { value: 'usb', label: 'USB' },
  { value: 'compartida', label: 'Compartida desde un computador' },
]

export const CAMPOS_CARACTERISTICA: Record<ClaveCaracteristica, CampoCaracteristica> = {
  procesador:        { clave: 'procesador', etiqueta: 'Procesador', placeholder: 'Ej.: Core i7' },
  memoria_ram:       { clave: 'memoria_ram', etiqueta: 'Memoria RAM', placeholder: 'Ej.: 16 GB' },
  sistema_operativo: { clave: 'sistema_operativo', etiqueta: 'Sistema operativo', placeholder: 'Ej.: Windows 11 Pro' },
  tipo_disco:        { clave: 'tipo_disco', etiqueta: 'Tipo de disco', placeholder: 'SSD o HDD', opciones: [{ value: 'SSD', label: 'SSD' }, { value: 'HDD', label: 'HDD' }] },
  capacidad_disco:   { clave: 'capacidad_disco', etiqueta: 'Capacidad del disco', placeholder: 'Ej.: 512 GB' },
  conexion:          { clave: 'conexion', etiqueta: 'Conexión', placeholder: 'Cómo se conecta', opciones: CONEXION },
  consumible:        { clave: 'consumible', etiqueta: 'Consumible', placeholder: 'Ej.: Tóner HP 85A' },
  color:             { clave: 'color', etiqueta: 'Impresión', placeholder: 'Monocromática o a color', opciones: [{ value: 'monocromatica', label: 'Monocromática' }, { value: 'color', label: 'A color' }] },
  pulgadas:          { clave: 'pulgadas', etiqueta: 'Pulgadas', placeholder: 'Ej.: 24' },
  entradas_video:    { clave: 'entradas_video', etiqueta: 'Entradas de video', placeholder: 'Ej.: HDMI y VGA' },
  alimentacion:      { clave: 'alimentacion', etiqueta: 'Alimentación de hojas', placeholder: 'Cómo recibe las hojas', opciones: [{ value: 'plana', label: 'Cama plana' }, { value: 'automatica', label: 'Alimentador automático' }, { value: 'ambas', label: 'Ambas' }] },
}

/** Qué pide cada ficha: sus datos de red y sus características, en orden. */
export const CAMPOS_POR_FICHA: Record<FichaTipo, { red: CampoRed[]; caracteristicas: ClaveCaracteristica[] }> = {
  computador: { red: ['nombre_red', 'usuario_dominio', 'direccion_ip'], caracteristicas: ['procesador', 'memoria_ram', 'sistema_operativo', 'tipo_disco', 'capacidad_disco'] },
  impresora:  { red: ['nombre_red', 'direccion_ip'], caracteristicas: ['conexion', 'consumible', 'color'] },
  monitor:    { red: [], caracteristicas: ['pulgadas', 'entradas_video'] },
  escaner:    { red: ['direccion_ip'], caracteristicas: ['alimentacion', 'conexion'] },
  general:    { red: ['nombre_red', 'direccion_ip'], caracteristicas: [] },
}

/** Para mostrar una característica guardada: la etiqueta de su opción, o el texto tal cual. */
export function valorCaracteristica(clave: string, valor: string): string {
  const campo = CAMPOS_CARACTERISTICA[clave as ClaveCaracteristica]
  return campo?.opciones?.find((o) => o.value === valor)?.label ?? valor
}
