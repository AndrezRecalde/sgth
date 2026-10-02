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
import type { ApiResponse, PaginatedResponse, ServidorConRelaciones } from '@/types/api'

// Cada tecla pedía una lista: escribir un apellido disparaba una petición por
// letra y solo importaba la última. Contra el servidor de desarrollo, que
// atiende de una en una, se encolan y el desplegable se queda en «Buscando...».
const RETARDO_BUSQUEDA_MS = 300

/*
| El tipo es el del API, no una copia local.
|
| Había una interfaz `Servidor` propia con siete campos, y como no declaraba
| `contrato_vigente` —que el listado sí carga: ExpedienteService::listarServidores
| lo trae en su `with()`— quien la consumía tenía que asertar. De ahí venía el
| `srv as ServidorConRelaciones` del selector de categorías, justo el gesto que
| la regla 09 señala: una relación que el endpoint devuelve y el tipo no declara.
*/
interface Props {
  label:     string
  value?:    number | null
  onChange:  (id: number | null) => void
  onSelect?: (servidor: ServidorConRelaciones) => void
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

  const getNombreCompleto = (s: ServidorConRelaciones) =>
    [s.nombre, s.segundo_nombre, s.apellido, s.segundo_apellido]
      .filter(Boolean).join(' ')

  // El texto visible solo se rellenaba al elegir en la lista, así que un
  // formulario que llegaba con servidor ya asignado —editar un registro—
  // pintaba el campo vacío y parecía que no había ninguno. Se resuelve el id
  // contra la API para poder mostrarlo.
  const { data: servidorSel } = useQuery({
    queryKey: ['expediente', 'servidor', value],
    queryFn: () =>
      api
        .get<ApiResponse<ServidorConRelaciones>>(`/expediente/servidores/${value}`)
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
    queryFn: async () => {
      // El listado devuelve `datos` como arreglo y la paginación en `meta`,
      // pero otros endpoints del expediente anidan `datos.data`: se aceptan
      // las dos formas porque este selector se usa en ambos sitios.
      const { data } = await api.get<
        ApiResponse<ServidorConRelaciones[] | PaginatedResponse<ServidorConRelaciones>>
      >('/expediente/servidores', { params: { search: termino, per_page: 10 } })

      const datos = data.datos

      return Array.isArray(datos) ? datos : datos?.data ?? []
    },
    enabled: termino.length >= 2,
  })

  // Mientras corre el retardo todavía no hay petición, pero lo que se ve es la
  // lista del término anterior: sin esto el desplegable diría «Sin resultados»
  // en mitad de una palabra.
  const buscando = isFetching || (escrito.length >= 2 && termino !== escrito)

  const handleSelect = (srv: ServidorConRelaciones) => {
    // Sembrar la caché con el servidor recién elegido evita que el campo
    // parpadee vacío mientras la consulta por id va y vuelve.
    queryClient.setQueryData(['expediente', 'servidor', srv.id], srv)
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
