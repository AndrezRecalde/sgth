'use client'

import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Combobox, InputBase, useCombobox,
  Text, Stack, Loader, Badge, Group,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { useContainedInput } from '@/hooks/useContainedInput'
import api from '@/lib/axios'

// Cada tecla pedía una lista: escribir «Analista» disparaba siete peticiones y
// solo importaba la última. Contra el servidor de desarrollo, que atiende de
// una en una, se encolan y el desplegable se queda en «Buscando...» segundos.
const RETARDO_BUSQUEDA_MS = 300

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
  const queryClient = useQueryClient()
  // `null` significa que el usuario no ha escrito nada: entonces el campo
  // muestra el puesto seleccionado. Una cadena vacía sí es escritura suya.
  const [search, setSearch]     = useState<string | null>(null)

  const getNombrePuesto = (p: Puesto) =>
    [
      p.cargo?.nombre,
      p.unidad_administrativa?.nombre
        ? `— ${p.unidad_administrativa.nombre}`
        : null,
    ].filter(Boolean).join(' ')

  // El texto visible solo se rellenaba al elegir en la lista, así que un
  // formulario que llegaba con puesto ya asignado —editar un registro— pintaba
  // el campo vacío y parecía que no había ninguno. Se resuelve el id contra la
  // API para poder mostrarlo.
  const { data: puestoSel } = useQuery({
    queryKey: ['estructura', 'puesto', value],
    queryFn: async () => {
      const res = await api.get(`/estructura/puestos/${value}`)
      return res.data?.datos as Puesto
    },
    enabled: !!value,
    staleTime: Infinity,
  })

  const escrito = search ?? ''
  const textoInput = search ?? (puestoSel ? getNombrePuesto(puestoSel) : '')

  const [escritoConRetardo] = useDebouncedValue(escrito, RETARDO_BUSQUEDA_MS)
  // Bajar de dos caracteres corta la búsqueda al instante: el retardo aplaza
  // las peticiones, no el vaciado de la lista.
  const termino = escrito.length < 2 ? '' : escritoConRetardo

  const { data: puestos = [], isFetching } = useQuery({
    queryKey: ['estructura', 'puestos', 'buscar', termino],
    queryFn: async () => {
      const res = await api.get('/estructura/puestos', {
        params: { search: termino, per_page: 10, all: false },
      })
      const datos = res.data?.datos
      const items: Puesto[] = Array.isArray(datos)
        ? datos
        : Array.isArray(datos?.data)
          ? datos.data
          : []
      return items
    },
    enabled: termino.length >= 2,
  })

  // Mientras corre el retardo todavía no hay petición, pero lo que se ve es la
  // lista del término anterior: sin esto el desplegable diría «Sin resultados»
  // en mitad de una palabra.
  const buscando = isFetching || (escrito.length >= 2 && termino !== escrito)

  const handleSelect = (p: Puesto) => {
    // Sembrar la caché con el puesto recién elegido evita que el campo
    // parpadee vacío mientras la consulta por id va y vuelve.
    queryClient.setQueryData(['estructura', 'puesto', p.id], p)
    setSearch(null)
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
          rightSection={buscando
            ? <Loader size="xs" />
            : <Combobox.Chevron />}
          {...contained}
          value={textoInput}
          onChange={(e) => {
            const v = e.currentTarget.value
            setSearch(v)
            combobox.openDropdown()
            if (!v) onChange(null)
          }}
          onFocus={() => combobox.openDropdown()}
          onBlur={() =>
            setTimeout(() => combobox.closeDropdown(), 200)
          }
        />
      </Combobox.Target>

      <Combobox.Dropdown>
        <Combobox.Options>
          {buscando ? (
            <Combobox.Empty>Buscando...</Combobox.Empty>
          ) : puestos.length === 0 ? (
            <Combobox.Empty>
              {escrito.length < 2
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
