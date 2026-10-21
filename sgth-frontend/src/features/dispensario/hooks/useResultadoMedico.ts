import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { resultadoMedicoService } from '../services/resultadoMedicoService'
import type { ResultadoMedico } from '../services/resultadoMedicoService'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'

export function useResultadosPorConsulta(
  historiaClinicaId: number,
  consultaId: number
) {
  return useQuery({
    queryKey: ['resultados', 'consulta', consultaId],
    queryFn:  () => resultadoMedicoService.listar({
      historia_clinica_id: historiaClinicaId,
      consulta_medica_id:  consultaId,
    }),
    enabled:  !!historiaClinicaId && !!consultaId,
    staleTime: 1000 * 60,
  })
}

/**
 * Abre el archivo de un resultado. Se pide por la API porque vive en el disco
 * privado; si el navegador bloquea la pestaña —se abre al volver del servidor,
 * ya fuera del clic— se descarga en su lugar.
 */
export function useVerResultado() {
  return useMutation({
    mutationFn: (resultado: ResultadoMedico) =>
      resultadoMedicoService.descargar(resultado.id),
    onSuccess: (blob, resultado) => {
      const extension = resultado.archivo.split('.').pop() ?? 'pdf'
      abrirOGuardarArchivo(blob, `resultado-${resultado.id}.${extension}`)
    },
    onError: notificar.alFallar('No se pudo abrir el resultado'),
  })
}

export function useSubirResultado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (formData: FormData) =>
      resultadoMedicoService.subir(formData),
    onSuccess: () => {
      notificar.exito('Resultado subido', 'El archivo fue registrado correctamente.')
      qc.invalidateQueries({
        queryKey: ['resultados', 'consulta', consultaId],
      })
    },
    onError: notificar.alFallar('No se pudo subir el resultado'),
  })
}

export function useEliminarResultado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) =>
      resultadoMedicoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Resultado retirado', 'Ya no aparece en la consulta.')
      qc.invalidateQueries({
        queryKey: ['resultados', 'consulta', consultaId],
      })
    },
    onError: notificar.alFallar('No se pudo retirar el resultado'),
  })
}
