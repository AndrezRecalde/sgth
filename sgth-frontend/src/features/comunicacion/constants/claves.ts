import type { TamanoFotografia } from '../services/tipos'
import type { VarianteIdentidad } from '../services/tiposIdentidad'

/**
 * Claves de consulta de Comunicación Social, del recurso a lo particular
 * (regla 08). La imagen va por el id de la foto, que no cambia nunca: una foto
 * nueva tiene otro id, así que la caché no se invalida al reemplazarla.
 */
export const clavesComunicacion = {
  fotografias: {
    todas: ['comunicacion', 'fotografias'] as const,
    lista: (filtros: unknown, page: number) => ['comunicacion', 'fotografias', 'lista', filtros, page] as const,
    historial: (servidorId: number) => ['comunicacion', 'fotografias', 'historial', servidorId] as const,
    imagen: (id: number, tamano: TamanoFotografia) => ['comunicacion', 'fotografias', 'imagen', id, tamano] as const,
  },

  /**
   * Las credenciales. «Mi credencial» va aparte: la pide el Portal de
   * cualquier servidor, y una revocación o una foto nueva la cambian.
   */
  credenciales: {
    todas: ['comunicacion', 'credenciales'] as const,
    lista: (filtros: unknown, page: number) => ['comunicacion', 'credenciales', 'lista', filtros, page] as const,
    historial: (servidorId: number) => ['comunicacion', 'credenciales', 'historial', servidorId] as const,
    mia: ['mi-credencial'] as const,
    verificada: (codigo: string) => ['credencial-verificada', codigo] as const,
  },

  /**
   * La identidad institucional. La pública va en su propia raíz porque la
   * piden el sidebar y el login fuera de Comunicación; un cambio invalida las dos.
   */
  identidad: {
    todas: ['comunicacion', 'identidad'] as const,
    estado: ['comunicacion', 'identidad', 'estado'] as const,
    versiones: (variante: VarianteIdentidad) => ['comunicacion', 'identidad', 'versiones', variante] as const,
    archivo: (versionId: number) => ['comunicacion', 'identidad', 'archivo', versionId] as const,
    publica: ['identidad-publica'] as const,
  },
}
