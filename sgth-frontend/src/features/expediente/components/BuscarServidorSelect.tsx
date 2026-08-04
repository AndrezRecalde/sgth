'use client'

import { useState } from 'react'
import {
  Combobox, InputBase, useCombobox,
  Text, Stack, Loader,
} from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import api from '@/lib/axios'

interface Servidor {
  id:              number
  nombre:          string
  apellido:        string
  segundo_nombre?: string | null
  segundo_apellido?: string | null
  cedula?:         string
  pendiente_vinculacion?: boolean | null
}

interface Props {
  label:     string
  value?:    number | null
  onChange:  (id: number | null) => void
  onSelect?: (servidor: Servidor) => void
  required?: boolean
  error?:    string
}

export function BuscarServidorSelect({
  label, value, onChange, onSelect, required, error,
}: Props) {
  const contained   = useContainedInput()
  const combobox    = useCombobox()
  const [search, setSearch]       = useState('')
  const [servidores, setServidores] = useState<Servidor[]>([])
  const [loading, setLoading]     = useState(false)

  const getNombreCompleto = (s: Servidor) =>
    [s.nombre, s.segundo_nombre, s.apellido, s.segundo_apellido]
      .filter(Boolean).join(' ')

  const buscar = async (q: string) => {
    if (q.length < 2) { setServidores([]); return }
    setLoading(true)
    try {
      const res = await api.get('/expediente/servidores', {
        params: { search: q, per_page: 10 },
      })
      const datos = res.data?.datos
      const items: Servidor[] = Array.isArray(datos)
        ? datos
        : Array.isArray(datos?.data)
          ? datos.data
          : []
      setServidores(items)
    } catch {
      setServidores([])
    } finally {
      setLoading(false)
    }
  }

  const handleSelect = (srv: Servidor) => {
    setSearch(getNombreCompleto(srv))
    onChange(srv.id)
    onSelect?.(srv)
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
          rightSection={loading ? <Loader size="xs" /> : <Combobox.Chevron />}
          {...contained}
          value={search}
          onChange={(e) => {
            const v = e.currentTarget.value
            setSearch(v)
            buscar(v)
            combobox.openDropdown()
            if (!v) onChange(null)
          }}
          onFocus={() => {
            combobox.openDropdown()
            if (search.length >= 2) buscar(search)
          }}
          onBlur={() =>
            setTimeout(() => combobox.closeDropdown(), 200)
          }
        />
      </Combobox.Target>

      <Combobox.Dropdown>
        <Combobox.Options>
          {loading ? (
            <Combobox.Empty>Buscando...</Combobox.Empty>
          ) : servidores.length === 0 ? (
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
                    {getNombreCompleto(srv)}
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
