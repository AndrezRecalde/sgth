import { bienesService } from '@/features/inventario-ti/services/bienesService'

/** La etiqueta QR lleva la URL de la ficha (`EtiquetaQrService::urlFicha`). */
const URL_FICHA = /\/tic\/inventario\/equipos\/(\d+)/

/**
 * El id del equipo a partir de lo leído en su etiqueta o de su código
 * institucional escrito a mano. La misma regla que la constatación física.
 */
export async function resolverEquipo(texto: string): Promise<number | null> {
  const limpio = texto.trim()
  if (!limpio) return null

  const enUrl = URL_FICHA.exec(limpio)
  if (enUrl) return Number(enUrl[1])

  const { data } = await bienesService.listar({ search: limpio }, 1)
  return data.find((b) => b.codigo_institucional === limpio || b.codigo_qr === limpio)?.id ?? null
}
