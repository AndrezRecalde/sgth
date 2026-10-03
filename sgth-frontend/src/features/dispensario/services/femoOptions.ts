import type { SemanticTone } from '@/config/design.tokens'
export const TIPO_FICHA_OPTIONS = [
  // «Ingreso», como el impreso del MSP. «Ingreso / Pre-ocupacional» no cabía
  // en la insignia de las tablas y se salía de su columna.
  { value: 'ingreso',    label: 'Ingreso' },
  { value: 'periodica',  label: 'Periódica'                 },
  { value: 'reintegro',  label: 'Reintegro'                 },
  { value: 'retiro',     label: 'Retiro'                    },
  { value: 'especial',   label: 'Especial'                  },
]

/**
 * Sección L, con los literales y el orden del impreso del MSP. El valor
 * `apto_con_restricciones` se conserva; lo que se lee es «limitaciones».
 */
export const APTITUD_OPTIONS = [
  { value: 'apto',                  label: 'Apto'                    },
  { value: 'en_observacion',        label: 'Apto en observación'     },
  { value: 'apto_con_restricciones',label: 'Apto con limitaciones'   },
  { value: 'no_apto',               label: 'No apto'                 },
]

export const TONO_APTITUD: Record<string, SemanticTone> = {
  apto:                   'success',
  apto_con_restricciones: 'warning',
  en_observacion:         'info',
  no_apto:                'danger',
}

export const TIPO_ANTECEDENTE_OPTIONS = [
  { value: 'clinico',               label: 'Clínico'                     },
  { value: 'quirurgico',            label: 'Quirúrgico'                  },
  { value: 'familiar',              label: 'Familiar'                    },
  { value: 'ginecologico',          label: 'Ginecológico'                },
  { value: 'reproductivo_masculino',label: 'Reproductivo masculino'      },
  { value: 'transfusion',           label: 'Autorización de transfusión' },
  { value: 'tratamiento_hormonal',  label: 'Tratamiento hormonal'        },
  { value: 'otro',                  label: 'Otro'                        },
]

/**
 * Los tipos que se ofrecen al registrar un antecedente. Transfusión y
 * tratamiento hormonal tienen ya su propia pregunta SÍ/NO en la sección C:
 * ofrecerlos también aquí daba dos sitios donde registrar lo mismo. Siguen en
 * la lista completa para nombrar los antecedentes antiguos.
 */
export const TIPO_ANTECEDENTE_SELECCIONABLES = TIPO_ANTECEDENTE_OPTIONS.filter(
  o => o.value !== 'transfusion' && o.value !== 'tratamiento_hormonal',
)

export const TIPO_EVENTO_LABORAL_OPTIONS = [
  { value: 'ninguno',                 label: 'Ninguno'                 },
  { value: 'incidente',               label: 'Incidente'               },
  { value: 'accidente',               label: 'Accidente'               },
  { value: 'enfermedad_profesional',  label: 'Enfermedad profesional'  },
]

export const METODO_PLANIFICACION_OPTIONS = [
  { value: 'si',          label: 'Sí'          },
  { value: 'no',          label: 'No'          },
  { value: 'no_responde', label: 'No responde' },
]

export const SUSTANCIA_OPTIONS = [
  { value: 'tabaco',  label: 'Tabaco'  },
  { value: 'alcohol', label: 'Alcohol' },
  { value: 'otra',    label: 'Otra'    },
]

// El catálogo de factores de riesgo (sección G) ya no vive aquí: lo sirve el
// backend en /dispensario/fichas-sso/catalogo-riesgos, que es el mismo que
// valida al guardar y alimenta el PDF. Ver useCatalogoRiesgos().

export interface RegionExamenFisico {
  value: string
  label: string
  items: string[]
}

// Espejo de RegionExamenFisico::items() del backend: el PDF imprime esa lista
// y busca lo registrado por nombre, y el backend rechaza un ítem que no esté.
// Las etiquetas llevan el numeral del impreso (1 a 13).
export const REGIONES_EXAMEN_FISICO: RegionExamenFisico[] = [
  { value: 'piel',         label: '1. Piel',      items: ['Cicatrices', 'Piel y Faneras'] },
  { value: 'ojos',         label: '2. Ojos',         items: ['Párpados', 'Conjuntivas', 'Pupilas', 'Córnea', 'Motilidad'] },
  { value: 'oido',         label: '3. Oído',         items: ['Conducto auditivo externo', 'Pabellón', 'Tímpanos'] },
  { value: 'orofaringe',   label: '4. Oro Faringe',  items: ['Labios', 'Lengua', 'Faringe', 'Amígdalas', 'Dentadura'] },
  { value: 'nariz',        label: '5. Nariz',        items: ['Tabique', 'Cornetes', 'Mucosas', 'Senos paranasales'] },
  { value: 'cuello',       label: '6. Cuello',       items: ['Tiroides / Masas', 'Movilidad'] },
  { value: 'torax_1',      label: '7. Tórax',     items: ['Mamas', 'Corazón'] },
  { value: 'torax_2',      label: '8. Tórax', items: ['Pulmones', 'Corazón', 'Parrilla Costal'] },
  { value: 'abdomen',      label: '9. Abdomen',      items: ['Vísceras', 'Pared Abdominal'] },
  { value: 'columna',      label: '10. Columna',      items: ['Flexibilidad', 'Desviación', 'Dolor'] },
  { value: 'pelvis',       label: '11. Pelvis',       items: ['Pelvis', 'Genitales'] },
  { value: 'extremidades', label: '12. Extremidades', items: ['Vascular', 'Miembros Superiores', 'Miembros Inferiores'] },
  { value: 'neurologico',  label: '13. Neurológico',  items: ['Fuerza', 'Sensibilidad', 'Marcha', 'Reflejos'] },
]
