'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Combobox, InputBase, Loader, Stack, Text, useCombobox } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { useContainedInput } from '@/hooks/useContainedInput'
import { clavesInventario } from '../constants/claves'
import { bienesService } from '../services/bienesService'
import type { BienInformatico } from '../services/tipos'

const RETARDO_BUSQUEDA_MS = 300

interface Props {
  label: string
  value: number | null
  /** El texto con que se pinta el equipo ya elegido (al editar). */
  textoInicial?: string
  /** El propio equipo, que no puede ser su principal. */
  excluirId?: number
  onChange: (id: number | null) => void
  error?: string
  /** Solo equipos principales: para elegir de quién es componente uno. */
  soloPrincipales?: boolean
  placeholder?: string
}

const textoDe = (b: Pick<BienInformatico, 'codigo_institucional' | 'modelo' | 'tipo'>) =>
  [b.codigo_institucional, b.tipo?.nombre, b.modelo].filter(Boolean).join(' · ')

/**
 * Buscar un equipo por código, serie o modelo. Para elegir el principal de un
 * componente ofrece solo equipos principales: un componente cuelga de uno, y
 * solo un nivel.
 */
export function BuscarEquipoSelect({
  label, value, textoInicial, excluirId, onChange, error, soloPrincipales = true,
  placeholder = 'Código, serie o modelo del equipo principal',
}: Props) {
  const contained = useContainedInput()
  const combobox = useCombobox()
  // `null`: no se ha escrito nada y el campo muestra el elegido.
  const [search, setSearch] = useState<string | null>(null)
  const [elegido, setElegido] = useState<string | null>(null)

  const escrito = search ?? ''
  const [conRetardo] = useDebouncedValue(escrito, RETARDO_BUSQUEDA_MS)
  const termino = escrito.length < 2 ? '' : conRetardo

  const { data, isFetching } = useQuery({
    queryKey: [...clavesInventario.bienes.todos, 'buscar', soloPrincipales, termino],
    queryFn: () => bienesService.listar({ search: termino, solo_principales: soloPrincipales || undefined }, 1),
    enabled: termino.length >= 2,
  })
  const opciones = (data?.data ?? []).filter((b) => b.id !== excluirId)
  const buscando = isFetching || (escrito.length >= 2 && termino !== escrito)

  const texto = search ?? (value ? elegido ?? textoInicial ?? '' : '')

  const elegir = (b: BienInformatico) => {
    setElegido(textoDe(b))
    setSearch(null)
    onChange(b.id)
    combobox.closeDropdown()
  }

  return (
    <Combobox store={combobox} onOptionSubmit={(v) => {
      const b = opciones.find((o) => String(o.id) === v)
      if (b) elegir(b)
    }}>
      <Combobox.Target>
        <InputBase
          label={label}
          error={error}
          placeholder={placeholder}
          rightSection={buscando ? <Loader size="xs" /> : <Combobox.Chevron />}
          {...contained}
          value={texto}
          onChange={(e) => {
            const v = e.currentTarget.value
            setSearch(v)
            combobox.openDropdown()
            if (!v) onChange(null)
          }}
          onFocus={() => combobox.openDropdown()}
          onBlur={() => setTimeout(() => combobox.closeDropdown(), 200)}
        />
      </Combobox.Target>
      <Combobox.Dropdown>
        <Combobox.Options>
          {buscando ? (
            <Combobox.Empty>Buscando...</Combobox.Empty>
          ) : opciones.length === 0 ? (
            <Combobox.Empty>{escrito.length < 2 ? 'Escriba al menos 2 caracteres' : 'Sin resultados'}</Combobox.Empty>
          ) : (
            opciones.map((b) => (
              <Combobox.Option key={b.id} value={String(b.id)}>
                <Stack gap={0}>
                  <Text size="sm" fw={500}>{b.codigo_institucional}</Text>
                  <Text size="xs" c="dimmed">{[b.tipo?.nombre, b.marca?.nombre, b.modelo].filter(Boolean).join(' · ')}</Text>
                </Stack>
              </Combobox.Option>
            ))
          )}
        </Combobox.Options>
      </Combobox.Dropdown>
    </Combobox>
  )
}
