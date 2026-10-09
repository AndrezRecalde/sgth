/**
 * Régimen LOSEP vs Código de Trabajo — mismo criterio que
 * TipoNombramiento::esLosep() en el backend: todo lo que no sea Código de
 * Trabajo ni Servicios Profesionales es LOSEP (incluida Elección Popular).
 */
export function esLosep(tipoNombramiento?: string | null): boolean {
  return tipoNombramiento !== 'codigo_trabajo'
    && tipoNombramiento !== 'servicios_profesionales'
}

/**
 * ¿Esta modalidad puede marcar biométrico, aunque sea de forma excepcional?
 *
 * Espeja `TipoNombramiento::admiteMarcacion()` del backend. Tres no marcan
 * nunca, por motivos distintos:
 *
 *  - Servicios Profesionales: contrato civil sin relación de dependencia.
 *  - Libre Nombramiento y Remoción, y Elección Popular: autoridades y personal
 *    de confianza, sin horario sujeto a control biométrico.
 *
 * Los obreros del Código del Trabajo sí quedan editables: entre ellos unos
 * marcan y otros no.
 *
 * El backend fuerza el valor a falso igual, así que esto solo evita ofrecer un
 * interruptor que no haría nada.
 */
export function admiteMarcacion(tipoNombramiento?: string | null): boolean {
  return ![
    'servicios_profesionales',
    'libre_nombramiento_remocion',
    'eleccion_popular',
  ].includes(tipoNombramiento ?? '')
}

/**
 * ¿La R.M.U. se teclea o se hereda?
 *
 * En LOSEP la fija el grupo ocupacional del puesto, así que el campo va en
 * solo lectura: escribir un monto distinto crearía una diferencia con la
 * escala vigente que nadie podría justificar después. En Código del Trabajo y
 * Servicios Profesionales sí se negocia en el contrato, y ahí el campo tiene
 * que estar abierto.
 *
 * Excepción: si el puesto LOSEP no tiene grupo ocupacional asignado no hay
 * nada que heredar, y bloquear el campo dejaría la acción imposible de
 * completar. En ese caso se abre y se avisa por qué.
 */
export function remuneracionEsHeredada(
  tipoNombramiento?: string | null,
  rmuDelPuesto?: number | null,
): boolean {
  return esLosep(tipoNombramiento) && rmuDelPuesto != null && rmuDelPuesto > 0
}

/*
| Aquí vivían `AccionPersonalTipo`, `ACCION_PERSONAL_LABELS` y
| `accionesElegibles()`, y `esExterno()` arriba. Se borraron el 2026-09-27: no
| los importaba nadie.
|
| Los tres primeros eran una copia vieja de la taxonomía —cinco tipos, sin
| Cesación de Funciones, sin Régimen Disciplinario y sin Incremento de
| Remuneración, y con la comisión sin remuneración como tipo propio cuando ya es
| subtipo de Cambio Administrativo—. Desde la fase 1.1 del rediseño esas reglas
| ya no se copian en el frontend: las sirve el backend en el catálogo de
| acciones (`hooks/useCatalogoAcciones.ts`, `utils/catalogoAcciones.ts`).
*/
