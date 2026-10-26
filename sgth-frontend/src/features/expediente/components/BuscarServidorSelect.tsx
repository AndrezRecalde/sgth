'use client'

import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Combobox, InputBase, useCombobox,
  Text, Stack, Loader,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { useContainedInput, type ContainedSize } from '@/hooks/useContainedInput'
import api from '@/lib/axios'
import type { ApiResponse, ServidorBusqueda } from '@/types/api'

// Cada tecla pedía una lista: escribir un apellido disparaba una petición por
// letra y solo importaba la última. Contra el servidor de desarrollo, que
// atiende de una en una, se encolan y el desplegable se queda en «Buscando...».
const RETARDO_BUSQUEDA_MS = 300

/*
| Busca en `expediente/servidores/buscar`, con su propio permiso
| (`buscar-servidores`), y no en el listado del Expediente.
|
| El listado solo lo sirve ServidorPolicy a Talento Humano, así que el personal
| de TIC recibía 403 al registrar un ticket, entregar o prestar un equipo, y el
| auditor no podía filtrar por servidor (docs/plan-roles-y-accesos.md, PR 2).
| El buscador devuelve lo justo para elegir a una persona; lo del vínculo
| —nombramiento, remuneración— solo llega a Talento Humano, que lo usa en
| Acciones de Personal, Subrogaciones y el sumario.
*/
interface Props {
  label:     string
  value?:    number | null
  onChange:  (id: number | null) => void
  onSelect?: (servidor: ServidorBusqueda) => void
  required?: boolean
  error?:    string
  /** `sm` para la variante compacta de una barra de filtros (regla 06). */
  size?:     ContainedSize
}

export function BuscarServidorSelect({
  label, value, onChange, onSelect, required, error, size = 'md',
}: Props) {
  const contained   = useContainedInput(size)
  const combobox    = useCombobox()
  const queryClient = useQueryClient()
  // `null` significa que el usuario no ha escrito nada: entonces el campo
  // muestra el servidor seleccionado. Una cadena vacía sí es escritura suya.
  const [search, setSearch]       = useState<string | null>(null)

  const getNombreCompleto = (s: ServidorBusqueda) =>
    [s.nombre, s.segundo_nombre, s.apellido, s.segundo_apellido]
      .filter(Boolean).join(' ')

  // El texto visible solo se rellenaba al elegir en la lista, así que un
  // formulario que llegaba con servidor ya asignado —editar un registro—
  // pintaba el campo vacío y parecía que no había ninguno. Se resuelve el id
  // contra la API para poder mostrarlo.
  //
  // La clave no es la del expediente (`['expediente', 'servidor', id]`): este
  // servidor viene recortado, y sembrarlo ahí dejaba la ficha sin sus datos.
  const { data: servidorSel } = useQuery({
    queryKey: ['expediente', 'servidor', 'buscar', value],
    queryFn: () =>
      api
        .get<ApiResponse<ServidorBusqueda>>(`/expediente/servidores/buscar/${value}`)
        .then((r) => r.data.datos),
    enabled: !!value,
    staleTime: Infinity,
  })

  const escrito = search ?? ''
  const textoInput = search ?? (servidorSel ? getNombreCompleto(servidorSel) : '')

  const [escritoConRetardo] = useDebouncedValue(escrito, RETARDO_BUSQUEDA_MS)
  // Bajar de dos caracteres corta la búsqueda al instante: el retardo aplaza
  // las peticiones, no el vaciado de la lista.
  const termino = escrito.length < 2 ? '' : escritoConRetardo

  const { data: servidores = [], isFetching } = useQuery({
    queryKey: ['expediente', 'servidores', 'buscar', termino],
    queryFn: () =>
      api
        .get<ApiResponse<ServidorBusqueda[]>>('/expediente/servidores/buscar', {
          params: { search: termino, per_page: 10 },
        })
        .then((r) => r.data.datos),
    enabled: termino.length >= 2,
  })

  // Mientras corre el retardo todavía no hay petición, pero lo que se ve es la
  // lista del término anterior: sin esto el desplegable diría «Sin resultados»
  // en mitad de una palabra.
  const buscando = isFetching || (escrito.length >= 2 && termino !== escrito)

  const handleSelect = (srv: ServidorBusqueda) => {
    // Sembrar la caché con el servidor recién elegido evita que el campo
    // parpadee vacío mientras la consulta por id va y vuelve.
    queryClient.setQueryData(['expediente', 'servidor', 'buscar', srv.id], srv)
    setSearch(null)
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
          rightSection={buscando ? <Loader size="xs" /> : <Combobox.Chevron />}
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
          ) : servidores.length === 0 ? (
            <Combobox.Empty>
              {escrito.length < 2
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
