/** Las opciones de los datos personales de un postulante: las usan la inscripción y el perfil. */
export const GENERO_OPTIONS = [
  { value: 'masculino', label: 'Masculino' },
  { value: 'femenino',  label: 'Femenino'  },
  { value: 'otro',      label: 'Otro'      },
]

export const ESTADO_CIVIL_OPTIONS = [
  { value: 'soltero',     label: 'Soltero/a'      },
  { value: 'casado',      label: 'Casado/a'       },
  { value: 'union_libre', label: 'Unión de hecho' },
  { value: 'divorciado',  label: 'Divorciado/a'   },
  { value: 'viudo',       label: 'Viudo/a'        },
]

export const TIPO_SANGRE_OPTIONS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
  .map(v => ({ value: v, label: v }))

export const etiquetaDe = (opciones: { value: string; label: string }[], valor?: string | null) =>
  opciones.find(o => o.value === valor)?.label ?? valor ?? '—'
