import type { DocumentoServidor } from '@/types/api'

/**
 * Lo mínimo que debería tener anexado un expediente. Algunos se cumplen con
 * cualquiera de varios tipos —un título de tercer o de cuarto nivel, un
 * contrato o un nombramiento—, así que cada entrada acepta una lista.
 *
 * No incluye los documentos que dependen de una condición (carnet del
 * CONADIS, certificados médicos): esos se piden donde se registra el caso.
 */
export const DOCUMENTOS_BASICOS: { nombre: string; tipos: string[] }[] = [
  { nombre: 'cédula de identidad', tipos: ['cedula_identidad'] },
  { nombre: 'papeleta de votación', tipos: ['papeleta_votacion'] },
  { nombre: 'título académico', tipos: ['titulo_tercer_nivel', 'titulo_cuarto_nivel'] },
  { nombre: 'contrato o nombramiento', tipos: ['contrato_laboral', 'nombramiento'] },
]

/** Cuáles de los básicos no están anexados todavía. */
export function documentosQueFaltan(documentos: DocumentoServidor[]): string[] {
  const subidos = new Set(documentos.map((d) => d.tipo_documento))
  return DOCUMENTOS_BASICOS
    .filter(({ tipos }) => !tipos.some((tipo) => subidos.has(tipo)))
    .map(({ nombre }) => nombre)
}
