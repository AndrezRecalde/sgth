'use client'

import { useState } from 'react'
import { Select } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'

interface Props {
  label: string
  placeholder: string
  data: { value: string; label: string }[]
  nothingFoundMessage: string
  onElegir: (valor: string) => void
}

/**
 * Un selector para sumar renglones: al elegir, avisa y vuelve a quedar vacío,
 * texto de búsqueda incluido, listo para el siguiente.
 */
export function AgregarSelect({ label, placeholder, data, nothingFoundMessage, onElegir }: Props) {
  const contained = useContainedInput()
  const [busqueda, setBusqueda] = useState('')

  return (
    <Select label={label} placeholder={placeholder} searchable {...contained} data={data} nothingFoundMessage={nothingFoundMessage}
      value={null} searchValue={busqueda} onSearchChange={setBusqueda}
      onChange={(v) => {
        if (v) onElegir(v)
        setBusqueda('')
      }} />
  )
}
