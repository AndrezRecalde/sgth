import { z } from 'zod/v4'
import type { MiCargaFamiliar } from '@/types/api'

/**
 * Lo que el servidor manda desde «Mi perfil» sobre un familiar
 * (docs/diseno-cargas-familiares-portal.md, 5.2). Un solo esquema para
 * registrar y corregir: la cédula solo se valida al registrar, porque una
 * corrección no la cambia.
 */
const campos = z.object({
  cedula:           z.string(),
  nombres:          z.string().trim().min(2, 'Mínimo 2 caracteres').max(100, 'Máximo 100 caracteres'),
  apellidos:        z.string().trim().min(2, 'Mínimo 2 caracteres').max(100, 'Máximo 100 caracteres'),
  parentesco:       z.enum(['conyugue', 'hijo'], { error: 'Indique el parentesco' }),
  genero:           z.enum(['masculino', 'femenino'], { error: 'Indique el sexo' }),
  fecha_nacimiento: z.string().min(1, 'Indique la fecha de nacimiento'),
  motivo:           z.string().max(1000, 'Máximo 1000 caracteres').optional(),
  documento:        z.instanceof(File).nullable().optional(),
})

export type SolicitudCargaFormData = z.infer<typeof campos>

const DOCUMENTO_DEL_HIJO = 'Adjunte la partida de nacimiento o la cédula de su hijo en PDF'

/**
 * El documento es obligatorio para un hijo y opcional para el cónyuge o
 * conviviente (D11). En una corrección, solo si cambia la fecha de nacimiento
 * o el parentesco de un hijo: es lo que el backend vuelve a exigir.
 */
export function solicitudCargaSchema(original?: MiCargaFamiliar | null) {
  // La cédula, en el objeto y no en el `superRefine`: este solo corre cuando
  // lo demás ya es válido, y la cédula vacía no se marcaba con el resto.
  const base = original
    ? campos
    : campos.extend({ cedula: z.string().regex(/^\d{10}$/, 'La cédula debe tener 10 dígitos') })

  return base.superRefine((d, ctx) => {
    const sinDocumento = !(d.documento instanceof File)

    if (!original) {
      if (d.parentesco === 'hijo' && sinDocumento) {
        ctx.addIssue({ code: 'custom', path: ['documento'], message: DOCUMENTO_DEL_HIJO })
      }
      return
    }

    const esHijo = original.parentesco === 'hijo' || d.parentesco === 'hijo'
    const cambiaLoQueSeProbo = d.fecha_nacimiento !== original.fecha_nacimiento
      || d.parentesco !== original.parentesco
    if (esHijo && cambiaLoQueSeProbo && sinDocumento) {
      ctx.addIssue({ code: 'custom', path: ['documento'], message: DOCUMENTO_DEL_HIJO })
    }
  })
}

export const bajaCargaSchema = z.object({
  motivo:    z.string().trim().min(5, 'Indique por qué este familiar deja de ser su carga').max(1000, 'Máximo 1000 caracteres'),
  documento: z.instanceof(File).nullable().optional(),
})

export type BajaCargaFormData = z.infer<typeof bajaCargaSchema>
