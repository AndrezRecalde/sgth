'use client'

import { useState } from 'react'
import {
  Combobox, InputBase, useCombobox,
  Text, Stack, Loader, Badge, Group,
} from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import api from '@/lib/axios'

interface Puesto {
  id:    number
  cargo?: { nombre: string }
  unidad_administrativa?: { nombre: string }
  grupo_ocupacional?: { nombre?: string; rmu?: number }
  regimen_laboral?: string
}

interface Props {
  label:       string
  value?:      number | null
  onChange:    (id: number | null, puesto?: Puesto) => void
  required?:   boolean
  error?:      string
  description?: string
}

export function BuscarPuestoSelect({
  label, value, onChange, required, error, description,
}: Props) {
  const contained  = useContainedInput()
  const combobox   = useCombobox()
  const [search, setSearch]     = useState('')
  const [puestos, setPuestos]   = useState<Puesto[]>([])
  const [loading, setLoading]   = useState(false)

  const getNombrePuesto = (p: Puesto) =>
    [
      p.cargo?.nombre,
      p.unidad_administrativa?.nombre
        ? `— ${p.unidad_administrativa.nombre}`
        : null,
    ].filter(Boolean).join(' ')

  const buscar = async (q: string) => {
    if (q.length < 2) { setPuestos([]); return }
    setLoading(true)
    try {
      const res = await api.get('/estructura/puestos', {
        params: { search: q, per_page: 10, all: false },
      })
      const datos = res.data?.datos
      const items: Puesto[] = Array.isArray(datos)
        ? datos
        : Array.isArray(datos?.data)
          ? datos.data
          : []
      setPuestos(items)
    } catch {
      setPuestos([])
    } finally {
      setLoading(false)
    }
  }

  const handleSelect = (p: Puesto) => {
    setSearch(getNombrePuesto(p))
    onChange(p.id, p)
    combobox.closeDropdown()
  }

  return (
    <Combobox
      store={combobox}
      onOptionSubmit={(val) => {
        const p = puestos.find(p => String(p.id) === val)
        if (p) handleSelect(p)
      }}
    >
      <Combobox.Target>
        <InputBase
          label={label}
          required={required}
          error={error}
          description={description}
          placeholder="Buscar puesto por nombre del cargo..."
          rightSection={loading
            ? <Loader size="xs" />
            : <Combobox.Chevron />}
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
          ) : puestos.length === 0 ? (
            <Combobox.Empty>
              {search.length < 2
                ? 'Escriba al menos 2 caracteres'
                : 'Sin resultados'}
            </Combobox.Empty>
          ) : (
            puestos.map((p) => (
              <Combobox.Option
                key={p.id}
                value={String(p.id)}
              >
                <Stack gap={2}>
                  <Text size="sm" fw={500}>
                    {p.cargo?.nombre ?? '—'}
                  </Text>
                  <Group gap="xs">
                    {p.unidad_administrativa?.nombre && (
                      <Text size="xs" c="dimmed">
                        {p.unidad_administrativa.nombre}
                      </Text>
                    )}
                    {p.regimen_laboral && (
                      <Badge size="xs" variant="light" color="orange">
                        {p.regimen_laboral.toUpperCase()}
                      </Badge>
                    )}
                  </Group>
                </Stack>
              </Combobox.Option>
            ))
          )}
        </Combobox.Options>
      </Combobox.Dropdown>
    </Combobox>
  )
}
