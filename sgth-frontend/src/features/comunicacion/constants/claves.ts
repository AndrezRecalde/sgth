import type { TamanoFotografia } from '../services/tipos'

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
}
