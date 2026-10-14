import { z } from 'zod/v4'

const IPV4 = /^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){3}$/

/**
 * La ficha de un equipo (docs/diseno-subsistema-tic.md, 4.2). Que el código y
 * la serie no se repitan, que el subtipo sea del tipo y lo del equipo
 * principal lo comprueba el backend, y su respuesta cae en el campo.
 */
export const bienSchema = z.object({
  codigo_institucional: z.string().trim().min(1, 'Indique el código institucional').max(50, 'Máximo 50 caracteres'),
  codigo_anterior: z.string().trim().max(50, 'Máximo 50 caracteres'),
  numero_serie: z.string().trim().max(100, 'Máximo 100 caracteres'),
  tipo_bien_id: z.number({ message: 'Elija el tipo de equipo' }),
  subtipo_bien_id: z.number().nullable(),
  marca_id: z.number({ message: 'Elija la marca' }),
  modelo: z.string().trim().max(100, 'Máximo 100 caracteres'),
  fecha_compra: z.string(),
  condicion_fisica: z.enum(['bueno', 'regular', 'malo']),
  estado_operativo: z.enum(['en_uso', 'disponible', 'en_mantenimiento', 'en_garantia', 'extraviado', 'dado_de_baja']),

  ubicacion_id: z.number().nullable(),
  unidad_administrativa_id: z.number().nullable(),
  bien_padre_id: z.number().nullable(),
  contrato_vigencia_id: z.number().nullable(),
  prestable: z.boolean(),
  nombre_red: z.string().trim().max(100, 'Máximo 100 caracteres'),
  usuario_dominio: z.string().trim().max(100, 'Máximo 100 caracteres'),
  direccion_ip: z.string().trim().refine((v) => v === '' || IPV4.test(v), 'Escriba una IP como 192.168.1.20'),

  procesador: z.string().trim().max(100),
  memoria_ram: z.string().trim().max(50),
  tipo_disco: z.string(),
  capacidad_disco: z.string().trim().max(50),
  sistema_operativo: z.string().trim().max(100),
  conexion: z.string(),
  consumible: z.string().trim().max(100, 'Máximo 100 caracteres'),
  color: z.string(),
  pulgadas: z.string().trim().max(10, 'Máximo 10 caracteres'),
  entradas_video: z.string().trim().max(100, 'Máximo 100 caracteres'),
  alimentacion: z.string(),
  observaciones: z.string().trim().max(2000, 'Máximo 2000 caracteres'),
})

export type BienFormData = z.infer<typeof bienSchema>

export const CAMPOS_BIEN = Object.keys(bienSchema.shape)
