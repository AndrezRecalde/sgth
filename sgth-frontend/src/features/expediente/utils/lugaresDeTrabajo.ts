/**
 * Los tres lugares de trabajo de la institución (Talento Humano, 2026-09-29).
 *
 * El campo era texto libre y en el documento impreso sale tal cual, así que lo
 * que se guarda es el NOMBRE y no un código: el PDF y el cajón de detalle lo
 * imprimen directamente, y un código obligaría a traducirlo en los dos sitios
 * —y a que cualquier fila vieja se imprimiera mal.
 *
 * La dirección no se guarda: acompaña a la opción para reconocerla al elegir.
 * En la hoja sobraría, porque el pie del documento ya lleva la dirección de la
 * institución.
 */
export interface LugarDeTrabajo {
  /** Lo que se guarda en `lugar_trabajo` y se imprime. */
  nombre: string
  /** Solo para el desplegable. */
  direccion: string
}

export const LUGARES_DE_TRABAJO: LugarDeTrabajo[] = [
  {
    nombre: 'Edificio Central',
    direccion: '10 de Agosto entre Bolívar y Pedro Vicente Maldonado',
  },
  {
    nombre: 'Edificio del Dispensario Médico',
    direccion: 'Juan Montalvo entre Olmedo y Sucre',
  },
  {
    nombre: 'San Mateo',
    direccion: 'San Mateo',
  },
]

/**
 * Opciones para el `Select`, más el valor que traiga el formulario si no es
 * ninguno de los tres.
 *
 * Hace falta porque el campo vivió como texto libre: hay acciones guardadas con
 * «Esmeraldas» y con lo que cada quien escribiera. Un `Select` de Mantine cuyo
 * `value` no está entre sus opciones no enseña la etiqueta, enseña el valor
 * crudo, y al editar una acción vieja el campo parecería vacío o roto. Se añade
 * al final y marcado, para que se vea que es de antes y se pueda cambiar.
 */
export function opcionesLugarDeTrabajo(valorActual?: string | null) {
  const opciones = LUGARES_DE_TRABAJO.map((l) => ({
    value: l.nombre,
    label: `${l.nombre} — ${l.direccion}`,
  }))

  const esConocido = LUGARES_DE_TRABAJO.some((l) => l.nombre === valorActual)

  if (valorActual && !esConocido) {
    opciones.push({
      value: valorActual,
      label: `${valorActual} (registrado antes)`,
    })
  }

  return opciones
}
