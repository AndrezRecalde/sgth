export const TIPO_FICHA_OPTIONS = [
  { value: 'ingreso',    label: 'Ingreso / Pre-ocupacional' },
  { value: 'periodica',  label: 'Periódica'                 },
  { value: 'reintegro',  label: 'Reintegro'                 },
  { value: 'retiro',     label: 'Retiro'                    },
  { value: 'especial',   label: 'Especial'                  },
]

export const APTITUD_OPTIONS = [
  { value: 'apto',                  label: 'Apto'                    },
  { value: 'apto_con_restricciones',label: 'Apto con restricciones'  },
  { value: 'en_observacion',        label: 'En observación'          },
  { value: 'no_apto',               label: 'No apto'                 },
]

export const APTITUD_COLORS: Record<string, string> = {
  apto:                   'emerald',
  apto_con_restricciones: 'orange',
  en_observacion:         'blue',
  no_apto:                'red',
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

// Espejo del catálogo de secciones/sub-ítems del Formulario 028 (MSP),
// alineado con el enum backend App\Enums\RegionExamenFisico.
export const REGIONES_EXAMEN_FISICO: RegionExamenFisico[] = [
  { value: 'piel',         label: 'Piel',         items: ['Cicatrices', 'Piel y Faneras'] },
  { value: 'ojos',         label: 'Ojos',         items: ['Párpados', 'Conjuntivas', 'Pupilas', 'Córnea', 'Motilidad'] },
  { value: 'oido',         label: 'Oído',         items: ['Conducto auditivo externo', 'Pabellón', 'Tímpanos'] },
  { value: 'orofaringe',   label: 'Oro Faringe',  items: ['Labios', 'Lengua', 'Faringe', 'Amígdalas', 'Dentadura'] },
  { value: 'nariz',        label: 'Nariz',        items: ['Tabique', 'Cornetes', 'Mucosas', 'Senos paranasales'] },
  { value: 'cuello',       label: 'Cuello',       items: ['Tiroides / Masas', 'Movilidad'] },
  { value: 'torax_1',      label: 'Tórax',        items: ['Mamas', 'Corazón'] },
  { value: 'torax_2',      label: 'Tórax (Pulmones / Corazón / Parrilla Costal)', items: ['Pulmones', 'Corazón', 'Parrilla Costal'] },
  { value: 'abdomen',      label: 'Abdomen',      items: ['Vísceras', 'Pared Abdominal'] },
  { value: 'columna',      label: 'Columna',      items: ['Flexibilidad', 'Desviación', 'Dolor'] },
  { value: 'pelvis',       label: 'Pelvis',       items: ['Pelvis', 'Genitales'] },
  { value: 'extremidades', label: 'Extremidades', items: ['Vascular', 'Miembros Superiores', 'Miembros Inferiores'] },
  { value: 'neurologico',  label: 'Neurológico',  items: ['Fuerza', 'Sensibilidad', 'Marcha', 'Reflejos'] },
]
