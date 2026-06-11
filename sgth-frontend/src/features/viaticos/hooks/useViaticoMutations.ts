import { useViaticoEstados }    from './useViaticoEstados'
import { useViaticoLiquidacion } from './useViaticoLiquidacion'

export function useViaticoMutations() {
  const estados     = useViaticoEstados()
  const liquidacion = useViaticoLiquidacion()

  return {
    ...estados,
    ...liquidacion,
  }
}
