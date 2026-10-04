/**
 * Los tipos de documento del expediente, agrupados para el selector. Los
 * valores son los de `StoreDocumentoServidorRequest`.
 */
export const TIPO_DOCUMENTO_OPTIONS = [
  {
    group: 'Identificación',
    items: [
      { value: 'cedula_identidad',    label: 'Cédula de identidad' },
      { value: 'papeleta_votacion',   label: 'Papeleta de votación' },
      { value: 'carnet_conadis',      label: 'Carnet CONADIS' },
    ],
  },
  {
    group: 'Académico',
    items: [
      { value: 'titulo_tercer_nivel', label: 'Título de tercer nivel' },
      { value: 'titulo_cuarto_nivel', label: 'Título de cuarto nivel (posgrado)' },
    ],
  },
  {
    group: 'Laboral',
    items: [
      { value: 'contrato_laboral',    label: 'Contrato laboral' },
      { value: 'nombramiento',        label: 'Nombramiento' },
      { value: 'certificado_trabajo_anterior', label: 'Certificado trabajo anterior' },
    ],
  },
  {
    group: 'Médico',
    items: [
      { value: 'certificado_medico',              label: 'Certificado médico' },
      { value: 'certificado_enfermedad_catastrofica', label: 'Certificado enfermedad catastrófica' },
    ],
  },
  {
    group: 'Otros',
    items: [
      { value: 'otro', label: 'Otro documento' },
    ],
  },
]

/** Los mismos formatos y tamaño que valida el backend. */
export const ARCHIVO_DOCUMENTO = {
  accept: ['application/pdf', 'image/jpeg', 'image/png'],
  maxMb: 5,
  formatos: 'PDF, JPG o PNG — máx. 5 MB',
}
