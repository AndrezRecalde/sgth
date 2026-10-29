import { describe, expect, it } from 'vitest'
import permisosPorRol from '@/test/permisos-por-rol.json'
import {
  buildNav,
  findNavTrail,
  flattenNav,
  getSubsistemasDisponibles,
  inicioDelSubsistema,
  puedeAbrirPantalla,
} from './nav'
import { ROUTES, type Subsistema } from './routes'

/*
| El menú decide qué ve cada quien, a qué subsistemas entra, por qué pantalla
| y qué pantallas puede abrir (docs/plan-roles-y-accesos.md, PR 9). Los
| permisos de cada rol son los de `RolPermisoSeeder`: el backend vigila que
| `permisos-por-rol.json` no se le desfase (PermisosPorRolDelFrontendTest).
*/

type Rol = keyof typeof permisosPorRol

const ROLES = Object.keys(permisosPorRol) as Rol[]

// Se dan junto con su rol base y no traen los permisos de todo servidor:
// `medico-ocupacional` va siempre con `medico` (2026-10-29).
const COMPLEMENTARIOS: Rol[] = ['medico-ocupacional']

const ROLES_CON_BASE = ROLES.filter((rol) => !COMPLEMENTARIOS.includes(rol))

const SUBSISTEMAS: Subsistema[] = ['sgth', 'salud', 'tic', 'comunicacion', 'portal']

/** Lo que trae la sesión de alguien con estos roles: sus permisos, sumados. */
function sesion(...roles: Rol[]): [string[], string[]] {
  const permisos = new Set(roles.flatMap((rol) => permisosPorRol[rol]))
  return [[...permisos], roles]
}

const pantallas = (subsistema: Subsistema, ...roles: Rol[]) =>
  flattenNav(subsistema, ...sesion(...roles)).map((pantalla) => pantalla.href)

describe('las guardas del menú', () => {
  // Desde el PR 9 una entrada sin guarda no es un descuido menor: el
  // subsistema se ofrece a quien ve una pantalla, así que esa entrada se lo
  // abriría a cualquier usuario.
  it.each(SUBSISTEMAS)('toda pantalla de %s declara la suya', (subsistema) => {
    expect(flattenNav(subsistema, [], [])).toEqual([])
  })
})

describe('buildNav', () => {
  it('filtra los hijos por su propia guarda', () => {
    const medico = pantallas('salud', 'medico')

    expect(medico).toContain(ROUTES.SALUD.FARMACIA)
    expect(medico).toContain(ROUTES.SALUD.FARMACIA_DESPACHO)
    expect(medico).not.toContain(ROUTES.SALUD.FARMACIA_ADQUISICIONES)
  })

  it('quita el padre que se queda sin hijos visibles', () => {
    const grupos = buildNav('sgth', ...sesion('recepcion'))
    const items = grupos.flatMap((grupo) => grupo.items.map((item) => item.label))

    expect(items).not.toContain('Estructura')
    expect(pantallas('sgth', 'recepcion')).toEqual([ROUTES.SGTH.ASISTENCIA_PERMISOS])
  })

  it('con permisos alternativos, basta con tener uno', () => {
    const conLosDeTodos = flattenNav('sgth', ['ver-asistencia-todos'], []).map((p) => p.href)
    const conLosDeSuUnidad = flattenNav('sgth', ['ver-asistencia-unidad'], []).map((p) => p.href)

    expect(conLosDeTodos).toContain(ROUTES.SGTH.ASISTENCIA)
    expect(conLosDeSuUnidad).toContain(ROUTES.SGTH.ASISTENCIA)
  })

  it('una entrada por rol solo la ve ese rol', () => {
    expect(pantallas('salud', 'medico')).toContain(ROUTES.SALUD.CONSULTAS)
    expect(pantallas('salud', 'odontologo')).not.toContain(ROUTES.SALUD.CONSULTAS)
    expect(pantallas('salud', 'odontologo')).toContain(ROUTES.SALUD.ODONTOLOGIA)
  })

  it('no le da al rol lo que el backend le quitó', () => {
    // admin-ti es solo técnico desde el PR 3, y el Consolidado le respondía
    // 403 al director.
    expect(pantallas('sgth', 'admin-ti')).not.toContain(ROUTES.SGTH.ESTRUCTURA)
    expect(pantallas('sgth', 'director')).not.toContain(ROUTES.SGTH.ASISTENCIA_CONSOLIDADO)
    expect(pantallas('sgth', 'trabajo-social')).toEqual([
      ROUTES.SGTH.ASISTENCIA_PERMISOS,
      ROUTES.SGTH.ASISTENCIA_LICENCIAS,
    ])
  })

  it('ofrece Usuarios en Tecnologías, solo a quien gestiona las cuentas', () => {
    // Se mudó del SGTH en el PR 10: allí a admin-ti le quedan los comunicados.
    expect(pantallas('tic', 'admin-ti')).toContain(ROUTES.TIC.USUARIOS)
    expect(pantallas('sgth', 'admin-ti')).toEqual([ROUTES.SGTH.COMUNICADOS])
    expect(pantallas('tic', 'jefe-tic')).not.toContain(ROUTES.TIC.USUARIOS)
    expect(pantallas('tic', 'tecnico-dtic')).not.toContain(ROUTES.TIC.USUARIOS)
  })

  it('ofrece el directorio a todos en el Portal y su gestión solo a TIC', () => {
    // PR 11: salió del SGTH, donde lo editaba también Talento Humano.
    for (const rol of ROLES_CON_BASE) {
      expect(pantallas('portal', rol), rol).toContain(ROUTES.PORTAL.DIRECTORIO)
    }

    for (const rol of ['tecnico-dtic', 'jefe-tic', 'admin-ti'] as const) {
      expect(pantallas('tic', rol), rol).toContain(ROUTES.TIC.DIRECTORIO)
    }

    expect(pantallas('tic', 'auditor')).not.toContain(ROUTES.TIC.DIRECTORIO)
    expect(pantallas('sgth', 'admin-uath')).not.toContain('/sgth/estructura/directorio')
  })

  it('al técnico de SSO le ofrece Riesgos laborales y nada más del SGTH', () => {
    // PR 12: el módulo pedía los roles de Talento Humano, y ahora sus permisos.
    const tecnico = pantallas('sgth', 'tecnico-sso')

    expect(tecnico).toContain(ROUTES.SGTH.RIESGOS_LABORALES_ACCIDENTES)
    expect(tecnico).toContain(ROUTES.SGTH.RIESGOS_LABORALES_AUSENTISMO)
    expect(tecnico.filter((href) => !href.startsWith(ROUTES.SGTH.RIESGOS_LABORALES))).toEqual([])
    expect(inicioDelSubsistema('sgth', ...sesion('tecnico-sso'))).toBe(ROUTES.SGTH.RIESGOS_LABORALES)

    for (const rol of ['admin-uath', 'asistente-uath', 'auditor'] as const) {
      expect(pantallas('sgth', rol), rol).toContain(ROUTES.SGTH.RIESGOS_LABORALES)
    }
  })

  it('el expediente y la asistencia propios del Portal van con sus permisos', () => {
    // PR 16: `ver-expediente-propio` y `ver-asistencia-propia` estaban en
    // todos los roles y no los miraba nadie.
    const soloAutoservicio = flattenNav('portal', ['acceso-autoservicio'], []).map((p) => p.href)
    expect(soloAutoservicio).not.toContain(ROUTES.PORTAL.MI_PERFIL)
    expect(soloAutoservicio).not.toContain(ROUTES.PORTAL.DOCUMENTOS)
    expect(soloAutoservicio).not.toContain(ROUTES.PORTAL.MIS_MARCACIONES)

    for (const rol of ROLES_CON_BASE) {
      const portal = pantallas('portal', rol)
      expect(portal, rol).toContain(ROUTES.PORTAL.MI_PERFIL)
      expect(portal, rol).toContain(ROUTES.PORTAL.DOCUMENTOS)
      expect(portal, rol).toContain(ROUTES.PORTAL.MIS_MARCACIONES)
    }
  })

  it('el expediente va por sus permisos y lo ve solo talento humano', () => {
    // docs/revision-permisos-sin-uso.md, D2 y D4: antes las tres entradas
    // pedían el rol `admin-uath|asistente-uath`.
    const expediente = [
      ROUTES.SGTH.EXPEDIENTE,
      ROUTES.SGTH.FORMACION_POR_VALIDAR,
      ROUTES.SGTH.CARGAS_POR_VALIDAR,
    ]

    for (const rol of ROLES) {
      const sgth = pantallas('sgth', rol)
      const deTalentoHumano = rol === 'admin-uath' || rol === 'asistente-uath'
      for (const href of expediente) {
        expect(sgth.includes(href), `${rol} ${href}`).toBe(deTalentoHumano)
      }
    }

    expect(flattenNav('sgth', ['gestionar-cargas-familiares'], []).map((p) => p.href))
      .toEqual([ROUTES.SGTH.CARGAS_POR_VALIDAR])
  })

  it('régimen disciplinario va por ver-sumarios y lo ve solo admin-uath', () => {
    // docs/revision-permisos-sin-uso.md, D8: antes la entrada pedía el rol.
    for (const rol of ROLES) {
      expect(pantallas('sgth', rol).includes(ROUTES.SGTH.DISCIPLINARIO), rol).toBe(rol === 'admin-uath')
    }
    expect(flattenNav('sgth', ['ver-sumarios'], []).map((p) => p.href)).toEqual([ROUTES.SGTH.DISCIPLINARIO])
  })

  it('la farmacia va por permiso: uno suelto basta, sin el rol', () => {
    // PR 17a: antes las entradas pedían roles del Dispensario.
    expect(flattenNav('salud', ['ver-inventario-med'], []).map((p) => p.href)).toEqual([ROUTES.SALUD.FARMACIA])
    expect(flattenNav('salud', ['ver-recetas'], []).map((p) => p.href)).toEqual([ROUTES.SALUD.FARMACIA_DESPACHO])
    expect(pantallas('salud', 'admin-dispensario')).toContain(ROUTES.SALUD.FARMACIA_ADQUISICIONES)
    expect(pantallas('salud', 'enfermera')).not.toContain(ROUTES.SALUD.FARMACIA_ADQUISICIONES)
  })

  it('Enfermería va por el permiso del triaje (PR 17b)', () => {
    expect(flattenNav('salud', ['registrar-triaje'], []).map((p) => p.href)).toEqual([
      ROUTES.SALUD.ENFERMERIA,
      ROUTES.SALUD.ENFERMERIA_COLA,
      ROUTES.SALUD.ENFERMERIA_SSO,
    ])
    expect(pantallas('salud', 'enfermera')).toContain(ROUTES.SALUD.ENFERMERIA_SSO)
    expect(pantallas('salud', 'medico')).not.toContain(ROUTES.SALUD.ENFERMERIA)
  })

  it('Exámenes va por permiso; Consultas y Odontología siguen por especialidad (PR 17c)', () => {
    expect(flattenNav('salud', ['ver-examenes'], []).map((p) => p.href)).toEqual([ROUTES.SALUD.EXAMENES])
    for (const rol of ['medico', 'odontologo', 'admin-dispensario'] as const) {
      expect(pantallas('salud', rol), rol).toContain(ROUTES.SALUD.EXAMENES)
    }
    expect(pantallas('salud', 'enfermera')).not.toContain(ROUTES.SALUD.EXAMENES)
    // La cola es de la especialidad: el permiso solo no la abre.
    expect(flattenNav('salud', ['crear-consulta'], []).map((p) => p.href)).not.toContain(ROUTES.SALUD.CONSULTAS)
  })

  it('el Dispensario queda por permisos salvo las colas por especialidad (PR 17d)', () => {
    expect(flattenNav('salud', ['ver-tablero-dispensario'], []).map((p) => p.href)).toEqual([ROUTES.SALUD.HOME])
    expect(flattenNav('salud', ['ver-reportes-dispensario'], []).map((p) => p.href)).toEqual([ROUTES.SALUD.REPORTES])
    expect(flattenNav('salud', ['ver-fichas-sso-med'], []).map((p) => p.href)).toContain(ROUTES.SALUD.FEMO)
    expect(pantallas('salud', 'odontologo')).not.toContain(ROUTES.SALUD.FEMO)
    expect(inicioDelSubsistema('salud', ...sesion('admin-dispensario'))).toBe(ROUTES.SALUD.HOME)
  })

  it('la salud ocupacional es del médico ocupacional, no de todo médico (2026-10-29)', () => {
    const general = pantallas('salud', 'medico')
    const ocupacional = pantallas('salud', 'medico', 'medico-ocupacional')

    for (const ruta of [ROUTES.SALUD.SSO, ROUTES.SALUD.FEMO, ROUTES.SALUD.SSO_COBERTURA, ROUTES.SALUD.SSO_TABLERO]) {
      expect(general, ruta).not.toContain(ruta)
      expect(ocupacional, ruta).toContain(ruta)
    }
    // Sigue siendo médico: su cola y su inicio no cambian.
    expect(ocupacional).toContain(ROUTES.SALUD.CONSULTAS)
    expect(inicioDelSubsistema('salud', ...sesion('medico', 'medico-ocupacional'))).toBe(ROUTES.SALUD.CONSULTAS)
  })

  it('al auxiliar de farmacia le ofrece toda la Farmacia y sus reportes, nada clínico', () => {
    // PR 13: el mostrador, sin historias, consultas, agenda ni exámenes. Desde
    // el 2026-10-29, también las adquisiciones y los reportes de Farmacia.
    expect(pantallas('salud', 'auxiliar-farmacia')).toEqual([
      ROUTES.SALUD.FARMACIA,
      ROUTES.SALUD.FARMACIA_ADQUISICIONES,
      ROUTES.SALUD.FARMACIA_DESPACHO,
      ROUTES.SALUD.REPORTES,
    ])
    expect(inicioDelSubsistema('salud', ...sesion('auxiliar-farmacia'))).toBe(ROUTES.SALUD.FARMACIA)
  })
})

describe('getSubsistemasDisponibles', () => {
  const ESPERADOS: Record<Rol, Subsistema[]> = {
    'admin-dispensario':     ['salud', 'portal'],
    'admin-ti':              ['sgth', 'tic', 'comunicacion', 'portal'],
    'admin-uath':            ['sgth', 'comunicacion', 'portal'],
    'asistente-uath':        ['sgth', 'portal'],
    'auditor':               ['sgth', 'tic', 'comunicacion', 'portal'],
    'auxiliar-farmacia':     ['salud', 'portal'],
    'comunicacion-social':   ['comunicacion', 'portal'],
    'director':              ['sgth', 'portal'],
    'enfermera':             ['salud', 'portal'],
    'financiero':            ['portal'],
    'jefe-tic':              ['tic', 'portal'],
    'jefe-unidad':           ['sgth', 'portal'],
    'medico':                ['salud', 'portal'],
    // Complementario: solo, sin `medico`, ni siquiera trae el Portal. El
    // backend no deja asignarlo así (RolesComplementariosConSuBase).
    'medico-ocupacional':    ['salud'],
    'odontologo':            ['salud', 'portal'],
    'recepcion':             ['sgth', 'portal'],
    'responsable-identidad': ['comunicacion', 'portal'],
    'servidor':              ['portal'],
    'tecnico-dtic':          ['tic', 'portal'],
    'tecnico-sso':           ['sgth', 'portal'],
    'trabajo-social':        ['sgth', 'portal'],
  }

  it('cubre todos los roles del seeder', () => {
    expect(Object.keys(ESPERADOS).sort()).toEqual([...ROLES].sort())
  })

  it.each(ROLES)('ofrece a %s los subsistemas donde ve alguna pantalla', (rol) => {
    expect(getSubsistemasDisponibles(...sesion(rol))).toEqual(ESPERADOS[rol])
  })

  it('suma los de cada rol de quien tiene varios', () => {
    expect(getSubsistemasDisponibles(...sesion('medico', 'admin-uath')))
      .toEqual(['sgth', 'salud', 'comunicacion', 'portal'])
  })

  it('sin permisos ni roles no ofrece ninguno', () => {
    expect(getSubsistemasDisponibles([], [])).toEqual([])
  })
})

describe('inicioDelSubsistema', () => {
  it('lleva a cada profesional del Dispensario a su pantalla de trabajo', () => {
    expect(inicioDelSubsistema('salud', ...sesion('medico'))).toBe(ROUTES.SALUD.CONSULTAS)
    expect(inicioDelSubsistema('salud', ...sesion('odontologo'))).toBe(ROUTES.SALUD.ODONTOLOGIA)
    expect(inicioDelSubsistema('salud', ...sesion('enfermera'))).toBe(ROUTES.SALUD.ENFERMERIA)
  })

  it('deja en el home a quien lo tiene en el menú', () => {
    expect(inicioDelSubsistema('salud', ...sesion('admin-dispensario'))).toBe(ROUTES.SALUD.HOME)
    expect(inicioDelSubsistema('tic', ...sesion('jefe-tic'))).toBe(ROUTES.TIC.HOME)
  })

  it('lleva a la portada del Portal, que no es una entrada del menú', () => {
    for (const rol of ROLES) {
      expect(inicioDelSubsistema('portal', ...sesion(rol))).toBe(ROUTES.PORTAL.HOME)
    }
  })

  it('entra al SGTH por la primera pantalla, porque no tiene una de inicio', () => {
    expect(inicioDelSubsistema('sgth', ...sesion('recepcion'))).toBe(ROUTES.SGTH.ASISTENCIA_PERMISOS)
    expect(inicioDelSubsistema('sgth', ...sesion('admin-ti'))).toBe(ROUTES.SGTH.COMUNICADOS)

    for (const rol of ROLES.filter((r) => getSubsistemasDisponibles(...sesion(r)).includes('sgth'))) {
      expect(inicioDelSubsistema('sgth', ...sesion(rol))).not.toBe(ROUTES.SGTH.HOME)
    }
  })

  it('sin pantallas en el subsistema, devuelve su home', () => {
    expect(inicioDelSubsistema('salud', ...sesion('servidor'))).toBe(ROUTES.SALUD.HOME)
  })
})

describe('findNavTrail', () => {
  it('en una ruta de detalle, devuelve la entrada del listado', () => {
    const miga = findNavTrail('sgth', ...sesion('admin-uath'), ROUTES.SGTH.EXPEDIENTE_SERVIDOR(15))

    expect(miga?.href).toBe(ROUTES.SGTH.EXPEDIENTE)
  })

  it('elige la entrada más larga que contiene la ruta', () => {
    const miga = findNavTrail('sgth', ...sesion('admin-uath'), `${ROUTES.SGTH.SUBROGACIONES}/3`)

    expect(miga?.href).toBe(ROUTES.SGTH.SUBROGACIONES)
  })

  it('no da migas de una pantalla que el usuario no tiene', () => {
    expect(findNavTrail('sgth', ...sesion('recepcion'), ROUTES.SGTH.EXPEDIENTE)).toBeNull()
  })
})

describe('puedeAbrirPantalla', () => {
  it('abre las pantallas del menú del usuario y sus detalles', () => {
    expect(puedeAbrirPantalla('salud', ROUTES.SALUD.CONSULTA_TURNO('T-0042'), ...sesion('medico'))).toBe(true)
    expect(puedeAbrirPantalla('sgth', ROUTES.SGTH.ASISTENCIA_PERMISOS, ...sesion('recepcion'))).toBe(true)
  })

  it('no abre la que el menú no le ofrece', () => {
    expect(puedeAbrirPantalla('salud', ROUTES.SALUD.CONSULTAS, ...sesion('enfermera'))).toBe(false)
    expect(puedeAbrirPantalla('sgth', ROUTES.SGTH.ESTRUCTURA, ...sesion('recepcion'))).toBe(false)
    expect(puedeAbrirPantalla('sgth', ROUTES.SGTH.ASISTENCIA_CONSOLIDADO, ...sesion('director'))).toBe(false)
    expect(puedeAbrirPantalla('portal', ROUTES.PORTAL.MARCACION_ONLINE, ...sesion('servidor'))).toBe(false)
  })

  it('decide la entrada más específica, no la del listado que la contiene', () => {
    // «Nueva convocatoria» pide además gestionarlas; el listado, solo verlas.
    const soloVer = ['ver-postulantes']

    expect(puedeAbrirPantalla('sgth', ROUTES.SGTH.CONVOCATORIA(7), soloVer, [])).toBe(true)
    expect(puedeAbrirPantalla('sgth', ROUTES.SGTH.CONVOCATORIA_NUEVA, soloVer, [])).toBe(false)
  })

  it('no juzga las rutas que no cuelgan de ninguna entrada', () => {
    expect(puedeAbrirPantalla('sgth', ROUTES.SGTH.HOME, [], [])).toBe(true)
    expect(puedeAbrirPantalla('portal', ROUTES.PORTAL.HOME, [], [])).toBe(true)
  })

  it.each(ROLES)('abre a %s cada pantalla que su menú le ofrece', (rol) => {
    for (const subsistema of SUBSISTEMAS) {
      for (const href of pantallas(subsistema, rol)) {
        expect(puedeAbrirPantalla(subsistema, href, ...sesion(rol)), href).toBe(true)
      }
    }
  })
})
