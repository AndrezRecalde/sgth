/**
 * Fuente única de verdad para la presentación de roles.
 *
 * Antes había cuatro mapas divergentes (UsuarioDrawer, UsuarioForm,
 * usuario.columns y un `replace(/-/g,' ')` en UsuarioToolbar). A los tres
 * primeros les faltaba `analista-uath`, así que ese rol no se podía asignar
 * desde la UI y en la tabla salía como slug crudo.
 *
 * Las etiquetas autoritativas vienen de /admin/usuarios-roles (enum Rol del
 * backend). Este mapa es solo el respaldo para pintar sin esperar a la red.
 */
export const ROL_ETIQUETAS: Record<string, string> = {
  'admin-uath':        'Administrador UATH',
  'asistente-uath':    'Asistente UATH',
  'director':          'Director de Área',
  'jefe-unidad':       'Jefe de Unidad',
  'servidor':          'Servidor Público',
  'recepcion':         'Recepción',
  'trabajo-social':    'Trabajo Social',
  'financiero':        'Financiero',
  'medico':            'Médico',
  'medico-ocupacional': 'Médico ocupacional',
  'odontologo':        'Odontólogo',
  'enfermera':         'Enfermera',
  'admin-dispensario': 'Administrativo Dispensario',
  'auxiliar-farmacia': 'Auxiliar de Farmacia',
  'tecnico-dtic':      'Técnico DTIC',
  'jefe-tic':          'Jefe de TIC',
  'admin-ti':          'Administrador TI',
  'comunicacion-social':   'Comunicación Social',
  'responsable-identidad': 'Responsable de Identidad Institucional',
  'tecnico-sso':           'Técnico de Seguridad y Salud Ocupacional',
  'auditor':           'Auditor',
}

/** Etiqueta legible de un rol; cae al slug si el rol es desconocido. */
export const etiquetaRol = (valor: string): string =>
  ROL_ETIQUETAS[valor] ?? valor
