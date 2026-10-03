import type { SemanticTone } from '@/config/design.tokens'

/*
| Etiquetas, tonos y opciones de filtro de las solicitudes de certificación.
| Las leen la bandeja del Dispensario, Certificaciones médicas, el expediente
| y Reclutamiento Express.
*/

export const TIPO_EVENTO_OPTIONS = [
  // «Ingreso», como el impreso del MSP. «Ingreso / Pre-ocupacional» no cabía
  // en la insignia de las tablas y se salía de su columna.
  { value: 'ingreso',    label: 'Ingreso' },
  { value: 'reintegro',  label: 'Reintegro'                 },
  { value: 'periodica',  label: 'Periódica'                 },
  { value: 'retiro',     label: 'Retiro'                    },
  { value: 'especial',   label: 'Especial'                  },
]

/** La etiqueta del tipo de evaluación, o el valor crudo si llega uno nuevo. */
export const etiquetaTipoEvento = (tipo: string) =>
  TIPO_EVENTO_OPTIONS.find(o => o.value === tipo)?.label ?? tipo

export const TONO_ESTADO_SOLICITUD: Record<string, SemanticTone> = {
  pendiente:   'warning',
  en_proceso:  'info',
  completada:  'success',
  cancelada:   'danger',
}

/**
 * El dictamen del Dispensario. «Apto con restricciones» es una advertencia,
 * no un fallo: el candidato entra, pero con condiciones que alguien debe leer.
 */
export const TONO_DICTAMEN: Record<string, SemanticTone> = {
  apto:                   'success',
  apto_con_restricciones: 'warning',
  en_observacion:         'info',
  no_apto:                'danger',
}

export const DICTAMEN_LABELS: Record<string, string> = {
  apto:                   'Apto',
  apto_con_restricciones: 'Apto c/limitaciones',
  en_observacion:         'Apto en observación',
  no_apto:                'No apto',
}

/**
 * Si el dictamen permite incorporar al candidato. Todos menos «no apto»:
 * «apto en observación» no bloquea (decidido con el usuario el 2026-10-02).
 * Es la misma regla que `AptitudMedica::habilitaIncorporacion()` del backend.
 */
export const dictamenHabilitaIncorporacion = (dictamen?: string | null): boolean =>
  !!dictamen && dictamen !== 'no_apto'

export const ESTADO_SOLICITUD_LABELS: Record<string, string> = {
  pendiente:   'Pendiente',
  en_proceso:  'En proceso',
  completada:  'Completada',
  cancelada:   'Cancelada',
}

/**
 * Las opciones del filtro de estado, que Salud Ocupacional y Certificaciones
 * médicas comparten. Estaban escritas a mano e idénticas en las dos vistas.
 */
export const ESTADO_SOLICITUD_FILTRO_OPTIONS = [
  { value: '',           label: 'Todas'       },
  { value: 'activas',    label: 'Pendientes y en proceso' },
  { value: 'pendiente',  label: 'Pendientes'  },
  { value: 'en_proceso', label: 'En proceso'  },
  { value: 'completada', label: 'Completadas' },
  { value: 'cancelada',  label: 'Canceladas'  },
]
