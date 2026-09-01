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
  'analista-uath':     'Analista UATH',
  'maxima-autoridad':  'Máxima Autoridad',
  'director':          'Director de Área',
  'jefe-unidad':       'Jefe de Unidad',
  'servidor':          'Servidor Público',
  'recepcion':         'Recepción',
  'trabajo-social':    'Trabajo Social',
  'medico':            'Médico',
  'odontologo':        'Odontólogo',
  'enfermera':         'Enfermera',
  'admin-dispensario': 'Administrativo Dispensario',
  'tecnico-dtic':      'Técnico DTIC',
  'admin-ti':          'Administrador TI',
  'auditor':           'Auditor',
}

/** Colores del tema Mantine. `auditor` usaba 'brown', que no está en la paleta. */
export const ROL_COLORS: Record<string, string> = {
  'admin-ti':          'red',
  'admin-uath':        'violet',
  'asistente-uath':    'grape',
  'analista-uath':     'grape',
  'maxima-autoridad':  'dark',
  'director':          'blue',
  'jefe-unidad':       'cyan',
  'servidor':          'teal',
  'recepcion':         'orange',
  'trabajo-social':    'pink',
  'medico':            'green',
  'odontologo':        'lime',
  'enfermera':         'yellow',
  'admin-dispensario': 'indigo',
  'tecnico-dtic':      'gray',
  'auditor':           'dark',
}

/** Etiqueta legible de un rol; cae al slug si el rol es desconocido. */
export const etiquetaRol = (valor: string): string =>
  ROL_ETIQUETAS[valor] ?? valor

export const colorRol = (valor: string): string =>
  ROL_COLORS[valor] ?? 'gray'
