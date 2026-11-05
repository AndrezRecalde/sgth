'use client'

import { useState } from 'react'
import { SimpleGrid, Stack, Text, TextInput, UnstyledButton } from '@mantine/core'
import { IconChevronRight, IconPencil } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ProblemaOfrecido } from '../services/tipos'
import classes from './ProblemasFrecuentesGrid.module.css'

/** A partir de cuántos aparece el buscador: se recomiendan entre 8 y 12 a la vista. */
const CON_BUSCADOR = 12

const normalizar = (t: string) => t.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()

interface Props {
  problemas: ProblemaOfrecido[]
  onElegir: (p: ProblemaOfrecido) => void
  /** «Otro problema»: el formulario libre. */
  onOtro: () => void
}

/**
 * «¿Qué necesita?»: los problemas frecuentes que definió TIC, como botones, y
 * al final «Otro problema». Cada botón se alcanza con Tab y se elige con Enter.
 */
export function ProblemasFrecuentesGrid({ problemas, onElegir, onOtro }: Props) {
  const contained = useContainedInput()
  const [busqueda, setBusqueda] = useState('')
  const filtro = normalizar(busqueda.trim())
  const visibles = filtro ? problemas.filter((p) => normalizar(p.nombre).includes(filtro)) : problemas

  return (
    <Stack gap="sm">
      {problemas.length > CON_BUSCADOR && (
        <TextInput label="Buscar" placeholder="Buscar un problema: impresora, correo…" {...contained}
          value={busqueda} onChange={(e) => setBusqueda(e.currentTarget.value)} />
      )}
      <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
        {visibles.map((p) => (
          <UnstyledButton key={p.id} className={`${classes.opcion} mantine-focus-auto`} onClick={() => onElegir(p)}>
            <Text size="sm" fw={500} flex={1}>{p.nombre}</Text>
            <IconChevronRight size={16} className={classes.icono} aria-hidden />
          </UnstyledButton>
        ))}
        <UnstyledButton className={`${classes.opcion} ${classes.otro} mantine-focus-auto`} onClick={onOtro}>
          <Stack gap={0} flex={1}>
            <Text size="sm" fw={500}>Otro problema</Text>
            <Text size="xs" c="dimmed">Cuéntenos con sus palabras</Text>
          </Stack>
          <IconPencil size={16} className={classes.icono} aria-hidden />
        </UnstyledButton>
      </SimpleGrid>
      {filtro && !visibles.length && (
        <Text size="sm" c="dimmed">Ningún problema coincide con «{busqueda.trim()}». Elija «Otro problema».</Text>
      )}
    </Stack>
  )
}
