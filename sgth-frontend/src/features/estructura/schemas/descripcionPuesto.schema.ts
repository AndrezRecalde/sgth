import { z } from 'zod/v4'

/** Los valores de `NivelInstruccion` del backend (constants/perfilPuesto.ts). */
const nivelInstruccion = z.enum([
  'bachiller', 'tecnico_superior', 'tecnologico_superior', 'estudios_superiores_en_curso', 'tercer_nivel', 'cuarto_nivel',
], { message: 'Seleccione el nivel' })

const texto = (max: number) => z.string().trim().max(max, `Máximo ${max} caracteres`)

/**
 * Una alternativa de instrucción y su experiencia. La experiencia se escribe en
 * años y meses, como en el manual («2 años y 6 meses»), y viaja en meses.
 */
const requisito = z.object({
  nivel_instruccion: nivelInstruccion,
  detalle:           texto(200),
  anios:             z.number({ message: 'Indique los años' }).int().min(0, 'No puede ser negativo').max(50, 'Máximo 50 años'),
  meses:             z.number({ message: 'Indique los meses' }).int().min(0, 'No puede ser negativo').max(11, 'Máximo 11 meses'),
}).superRefine((r, ctx) => {
  // Las mismas reglas que `GuardarPerfilPuestoRequest`: los estudios en curso
  // no son un título, así que se dice qué está aprobado.
  if (r.nivel_instruccion === 'estudios_superiores_en_curso' && !r.detalle) {
    ctx.addIssue({ code: 'custom', path: ['detalle'], message: 'Diga qué tiene aprobado, por ejemplo «tercer año o sexto semestre»' })
  }
})

export const descripcionPuestoSchema = z.object({
  mision:                    texto(3000),
  relaciones:                texto(3000),
  subproceso:                texto(200),
  nivel:                     z.enum(['profesional', 'no_profesional']).nullable(),
  requisitos:                z.array(requisito).max(5, 'Máximo 5 alternativas'),
  area_conocimiento:         texto(1000),
  especificidad_experiencia: texto(1000),
  capacitacion:              texto(3000),
})

export type DescripcionPuestoFormData = z.infer<typeof descripcionPuestoSchema>
