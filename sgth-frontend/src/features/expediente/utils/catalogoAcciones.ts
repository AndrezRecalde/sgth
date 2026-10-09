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

  if (!clase.requiere_vinculo) return vinculo.sinVinculo === true

  if (vinculo.sinVinculo || !vinculo.tipoNombramiento) return false

  return incluye(clase.nombramientos_elegibles, vinculo.tipoNombramiento)
}

/** Las causales de la clase que aplican al nombramiento vigente. */
export function causalesDisponibles(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
): CausalDelCatalogo[] {
  if (!clase) return []
  if (!tipoNombramiento) return clase.causales

  return clase.causales.filter((c) => incluye(c.nombramientos_elegibles, tipoNombramiento))
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
