'use client'

import { useState } from 'react'
import { Combobox, InputBase, useCombobox, Text, Stack } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

interface Servidor {
  id:       number
  nombre:   string
  apellido: string
  cedula?:  string
}

interface Props {
  label:     string
  value?:    number | null
  onChange:  (id: number | null) => void
  required?: boolean
  error?:    string
}

export function BuscarServidorSelect({
  label, value, onChange, required, error,
}: Props) {
  const contained   = useContainedInput()
  const combobox    = useCombobox()
  const [search, setSearch]       = useState('')
  const [servidores, setServidores] = useState<Servidor[]>([])
  const [loading, setLoading]     = useState(false)
  const [seleccionado, setSeleccionado] = useState<Servidor | null>(null)

  const buscar = async (q: string) => {
    if (q.length < 2) { setServidores([]); return }
    setLoading(true)
    try {
      const res = await api.get<ApiResponse<{ data: Servidor[] }>>(
        '/expediente/servidores',
        { params: { search: q, per_page: 10 } }
      )
      setServidores(res.data.datos?.data ?? [])
    } catch {
      setServidores([])
    } finally {
      setLoading(false)
    }
  }

  const handleSelect = (srv: Servidor) => {
    setSeleccionado(srv)
    setSearch(`${srv.nombre} ${srv.apellido}`)
    onChange(srv.id)
    combobox.closeDropdown()
  }

  return (
    <Combobox
      store={combobox}
      onOptionSubmit={(val) => {
        const srv = servidores.find(s => String(s.id) === val)
        if (srv) handleSelect(srv)
      }}
    >
      <Combobox.Target>
        <InputBase
          label={label}
          required={required}
          error={error}
          placeholder="Buscar por nombre o cédula..."
          rightSection={loading
            ? <Combobox.Chevron />
            : <Combobox.Chevron />}
          {...contained}
          value={search}
          onChange={(e) => {
            setSearch(e.currentTarget.value)
            buscar(e.currentTarget.value)
            combobox.openDropdown()
            if (!e.currentTarget.value) {
              setSeleccionado(null)
              onChange(null)
            }
          }}
          onFocus={() => combobox.openDropdown()}
          onBlur={() => combobox.closeDropdown()}
        />
      </Combobox.Target>

      <Combobox.Dropdown>
        <Combobox.Options>
          {servidores.length === 0 ? (
            <Combobox.Empty>
              {search.length < 2
                ? 'Escriba al menos 2 caracteres'
                : 'Sin resultados'}
            </Combobox.Empty>
          ) : (
            servidores.map((srv) => (
              <Combobox.Option
                key={srv.id}
                value={String(srv.id)}
              >
                <Stack gap={0}>
                  <Text size="sm" fw={500}>
                    {srv.nombre} {srv.apellido}
                  </Text>
                  {srv.cedula && (
                    <Text size="xs" c="dimmed">{srv.cedula}</Text>
                  )}
                </Stack>
              </Combobox.Option>
            ))
          )}
        </Combobox.Options>
      </Combobox.Dropdown>
    </Combobox>
  )
}
