import type {
  CatalogoAccionesPersonal, CausalDelCatalogo, ClaseDelCatalogo,
  FamiliaAccionPersonal, FamiliaDelCatalogo,
} from '@/types/api'

/*
| Lectura del catálogo de acciones de personal que sirve el backend.
|
| Aquí no hay reglas: hay datos. Qué clase aplica a qué nombramiento, qué pide
| el formulario en cada una y qué causales tiene lo dice
| `GET /expediente/acciones-personal/catalogo`; estas funciones solo lo cruzan
| con el servidor que está en pantalla. Hasta la fase 1.1 del rediseño esas
| reglas vivían copiadas a mano en `taxonomiaAccionPersonal.ts`, y cada cambio
| de Talento Humano había que escribirlo dos veces.
*/

/** El vínculo del servidor sobre el que se va a registrar la acción. */
export interface VinculoDelServidor {
  /** Sin vínculo vigente: solo cabe el ingreso, que es la acción que lo crea. */
  sinVinculo?: boolean
  /** El nombramiento del vínculo vigente, cuando lo hay. */
  tipoNombramiento?: string | null
  /**
   * Sin vínculo, el de su último: la restitución del obrero es su reintegro
   * por despido ineficaz (fase 4.5b).
   */
  ultimoNombramiento?: string | null
}

/** `nombramientos_elegibles` llega como tupla; para buscar basta una lista. */
function incluye(lista: readonly string[], valor: string): boolean {
  return lista.includes(valor)
}

export function buscarClase(
  catalogo: CatalogoAccionesPersonal | undefined,
  codigo?: string | null,
): ClaseDelCatalogo | undefined {
  return codigo ? catalogo?.clases.find((c) => c.codigo === codigo) : undefined
}

/**
 * ¿Se puede registrar esta clase a este servidor?
 *
 * Sin vínculo solo cabe la clase que no lo requiere —el ingreso—, y con vínculo
 * cabe todo lo demás que el nombramiento vigente admita. Sin saber ni lo uno ni
 * lo otro no se ofrece nada: no hay con qué decidir.
 */
export function claseDisponible(clase: ClaseDelCatalogo, vinculo: VinculoDelServidor): boolean {
  if (!clase.se_crea_desde_formulario) return false

  /*
  | A una autoridad electa este formulario no le ofrece nada (fase 4.1): su
  | inicio del período, su licencia del Consejo y su cesación se registran en
  | «Autoridades electas», con su documento de origen y su numeración AU.
  |
  | La cesación hay que descartarla aquí y no en el catálogo porque es la única
  | de sus clases que comparte figura con el resto del personal: la misma cesa
  | a un permanente y a una Prefecta, y solo cambian las causales. Al servidor
  | le sigue llegando igual desde la otra pantalla.
  */
  if (vinculo.tipoNombramiento === 'eleccion_popular') return false

  // Sin vínculo cabe lo que no lo requiere. Con él, de esas clases solo la que
  // el catálogo admite para su nombramiento: el inicio del período de la
  // autoridad reelegida (fase 4.1).
  if (!clase.requiere_vinculo) {
    return vinculo.sinVinculo === true
      || (!!vinculo.tipoNombramiento && incluye(clase.nombramientos_elegibles, vinculo.tipoNombramiento))
  }

  if (vinculo.sinVinculo || !vinculo.tipoNombramiento) return false

  return incluye(clase.nombramientos_elegibles, vinculo.tipoNombramiento)
}

/**
 * La base legal de la causal para este nombramiento (fase 4.1c).
 *
 * `base_legal` es la del régimen LOSEP, que es el caso general. La renuncia y
 * el fallecimiento también cierran el período de una autoridad electa y un
 * contrato civil, y ahí rige otra norma: el backend manda esas variantes en
 * `base_legal_por_nombramiento`.
 */
function baseLegalDe(causal: CausalDelCatalogo, tipoNombramiento?: string | null): string | null {
  const propia = tipoNombramiento
    ? causal.base_legal_por_nombramiento.find((b) => b.nombramiento === tipoNombramiento)
    : undefined

  return propia?.base_legal ?? causal.base_legal
}

/**
 * Las causales de la clase que aplican al nombramiento vigente, cada una con la
 * base legal de ese régimen: así quien las muestra no tiene que volver a
 * preguntarse de qué régimen era el servidor.
 */
export function causalesDisponibles(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
): CausalDelCatalogo[] {
  if (!clase) return []
  if (!tipoNombramiento) return clase.causales

  return clase.causales
    .filter((c) => incluye(c.nombramientos_elegibles, tipoNombramiento))
    .map((c) => ({ ...c, base_legal: baseLegalDe(c, tipoNombramiento) }))
}

/** Las clases que se le pueden registrar a este servidor, opcionalmente de una sola familia. */
export function clasesDisponibles(
  catalogo: CatalogoAccionesPersonal,
  vinculo: VinculoDelServidor,
  familia?: FamiliaAccionPersonal,
): ClaseDelCatalogo[] {
  return catalogo.clases.filter((c) =>
    (!familia || c.familia === familia) && claseDisponible(c, vinculo),
  )
}

/**
 * Las familias que el formulario ofrece, en el orden del catálogo. La de
 * reemplazo de autoridades queda fuera: la subrogación y el encargo nacen en su
 * propia pantalla.
 */
export function familiasDelFormulario(catalogo: CatalogoAccionesPersonal): FamiliaDelCatalogo[] {
  return catalogo.familias.filter((f) =>
    catalogo.clases.some((c) => c.familia === f.codigo && c.se_crea_desde_formulario),
  )
}

/** ¿Hay en esta familia algo que registrarle a este servidor? */
export function familiaDisponible(
  catalogo: CatalogoAccionesPersonal,
  familia: FamiliaAccionPersonal,
  vinculo: VinculoDelServidor,
): boolean {
  return clasesDisponibles(catalogo, vinculo, familia).length > 0
}

/** ¿Todas las clases de la familia exigen vínculo? Decide qué se le explica a quien no lo tiene. */
export function familiaRequiereVinculo(
  catalogo: CatalogoAccionesPersonal,
  familia: FamiliaAccionPersonal,
): boolean {
  return catalogo.clases
    .filter((c) => c.familia === familia && c.se_crea_desde_formulario)
    .every((c) => c.requiere_vinculo)
}
