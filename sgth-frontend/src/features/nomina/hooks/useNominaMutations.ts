import { useMutation, useQueryClient } from '@tanstack/react-query'
import { nominaService } from '../services/nominaService'
import { notificar } from '@/components/ui'

export function useNominaMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['nominas'] })

  const calcular = useMutation({
    mutationFn: (periodo: string) => nominaService.calcular(periodo),
    onSuccess: () => {
      notificar.exito('Nómina calculada', 'La nómina fue calculada en borrador.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo calcular la nómina'),
  })

  const cerrar = useMutation({
    mutationFn: (id: number) => nominaService.cerrar(id),
    onSuccess: () => {
      notificar.exito('Nómina cerrada', 'La nómina fue cerrada correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cerrar la nómina'),
  })

  return { calcular, cerrar }
}
