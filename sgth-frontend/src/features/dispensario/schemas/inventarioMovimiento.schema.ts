import { z } from 'zod/v4'

/**
 * Los dos movimientos que mueven existencias a mano: dar de baja y ajustar el
 * conteo. Las reglas son las de `InventarioMedicinasController`.
 *
 * Los dos mandan un solo campo `motivo`, limitado a 255 en el servidor. En la
 * baja ese campo no existe en pantalla: es la causa y el detalle pegados, y de
 * ahí que el error que llegaba —«El campo motivo no puede tener más de 255
 * caracteres»— nombrara algo que quien lo leía no encontraba por ninguna parte.
 */

/** El tope del servidor para `motivo`. */
const MOTIVO_MAX = 255

/** Las causas, escritas una sola vez: de aquí salen el enum y el desplegable. */
const CAUSAS = [
  'Caducidad', 'Merma', 'Rotura', 'Contaminación', 'Otra',
] as const

const ETIQUETA_CAUSA: Record<(typeof CAUSAS)[number], string> = {
  'Caducidad':     'Caducidad',
  'Merma':         'Merma',
  'Rotura':        'Rotura o envase dañado',
  'Contaminación': 'Contaminación',
  'Otra':          'Otra',
}

export const CAUSAS_BAJA = CAUSAS.map(value => ({
  value,
  label: ETIQUETA_CAUSA[value],
}))

/** Lo que se manda como `motivo`: la causa y, si lo hay, el detalle. */
export function motivoDeBaja(causa: string, detalle: string): string {
  const limpio = detalle.trim()
  return limpio ? `${causa} — ${limpio}` : causa
}

export const bajaStockSchema = z.object({
  lote_id:  z.string().min(1, 'Indique de qué lote salen'),
  cantidad: z.number()
    .int('Las unidades no se parten por la mitad')
    .min(1, 'Debe ser al menos 1'),
  causa:    z.enum(CAUSAS, { message: 'Seleccione la causa' }),
  detalle:  z.string(),
})
  // El tope se comprueba sobre lo que se va a enviar y no sobre el detalle
  // suelto: cuánto cabe depende de la causa elegida, que va delante.
  .superRefine((v, ctx) => {
    const sobra = motivoDeBaja(v.causa, v.detalle).length - MOTIVO_MAX
    if (sobra > 0) {
      ctx.addIssue({
        code: 'custom',
        path: ['detalle'],
        message: `Sobran ${sobra} caracteres para el motivo, que admite ${MOTIVO_MAX} contando la causa`,
      })
    }
  })

export type BajaStockFormData = z.infer<typeof bajaStockSchema>

export const ajusteInventarioSchema = z.object({
  nuevo_stock: z.number()
    .int('Las unidades no se parten por la mitad')
    .min(0, 'No puede ser negativo'),
  motivo:      z.string()
    .min(5, 'Explique por qué se ajusta')
    .max(MOTIVO_MAX, `Máximo ${MOTIVO_MAX} caracteres`),
})

export type AjusteInventarioFormData = z.infer<typeof ajusteInventarioSchema>
