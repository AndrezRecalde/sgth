import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { cie10Service } from '../services/cie10Service'

export function useBuscarCie10(termino: string) {
  const [debounced, setDebounced] = useState(termino)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(termino), 350)
    return () => clearTimeout(timer)
  }, [termino])

  return useQuery({
    queryKey: ['cie10', 'buscar', debounced],
    queryFn:  () => cie10Service.buscar(debounced),
    enabled:  debounced.length >= 2,
    staleTime: 1000 * 60,
    placeholderData: (anterior) => anterior,
  })
}
