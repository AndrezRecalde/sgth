'use client'

import { useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import {
  Kbd, Modal, ScrollArea, Stack, Text, TextInput, UnstyledButton, Group,
} from '@mantine/core'
import { useHotkeys } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { flattenNav, type NavLeaf } from '@/config/nav'
import { getNavIcon } from '@/lib/tablerIcons'
import { usePaletteStore } from '@/store/ui.palette.store'
import type { Subsistema } from '@/config/routes'
import classes from './CommandPalette.module.css'

interface Props {
  subsistema: Subsistema
}

/**
 * Normaliza para buscar sin tildes: "nomina" debe encontrar "Nómina".
 * `NFD` separa la letra de su tilde y el reemplazo descarta las marcas
 * combinantes (U+0300–U+036F) que quedan sueltas.
 */
function normalizar(texto: string): string {
  return texto
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
}

/**
 * Buscador de pantallas (Ctrl+K / ⌘K).
 *
 * El SGTH tiene más de cincuenta pantallas repartidas en tres subsistemas;
 * llegar a "Entregas de EPP" son tres clics y saber de antemano que vive bajo
 * Riesgos laborales. Este buscador recorre el MISMO `config/nav.ts` que pinta
 * el menú, así que ninguna pantalla nueva hay que registrarla aquí.
 */
export function CommandPalette({ subsistema }: Props) {
  const router = useRouter()
  const { usuario } = useAuth()
  const { opened, close, toggle } = usePaletteStore()
  const [query, setQuery] = useState('')
  const [indice, setIndice] = useState(0)

  useHotkeys([['mod+K', toggle]])

  // Se depende del contenido de los permisos, no de la identidad del array,
  // que es nuevo en cada render y invalidaría el memo siempre.
  const permisosKey = (usuario?.permisos ?? []).join(',')

  const destinos = useMemo(
    () => flattenNav(subsistema, permisosKey ? permisosKey.split(',') : []),
    [subsistema, permisosKey],
  )

  const resultados = useMemo(() => {
    const q = normalizar(query.trim())
    if (!q) return destinos.slice(0, 8)

    return destinos
      .filter((d) =>
        normalizar(`${d.label} ${d.grupo} ${d.padre ?? ''}`).includes(q),
      )
      .slice(0, 12)
  }, [destinos, query])

  /** Escribir reinicia la selección al primer resultado. */
  const buscar = (texto: string) => {
    setQuery(texto)
    setIndice(0)
  }

  const ir = (destino: NavLeaf) => {
    close()
    setQuery('')
    router.push(destino.href)
  }

  const onKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setIndice((i) => (i + 1) % Math.max(resultados.length, 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setIndice((i) => (i - 1 + resultados.length) % Math.max(resultados.length, 1))
    } else if (e.key === 'Enter' && resultados[indice]) {
      e.preventDefault()
      ir(resultados[indice])
    }
  }

  return (
    <Modal
      opened={opened}
      onClose={close}
      withCloseButton={false}
      size="lg"
      padding={0}
      radius="xl"
      centered={false}
      classNames={{ content: classes.content, inner: classes.inner }}
    >
      <TextInput
        value={query}
        onChange={(e) => buscar(e.currentTarget.value)}
        onKeyDown={onKeyDown}
        placeholder="Buscar pantalla…"
        aria-label="Buscar pantalla"
        size="md"
        variant="unstyled"
        data-autofocus
        leftSection={<IconSearch size={18} />}
        rightSection={<Kbd size="xs">esc</Kbd>}
        rightSectionWidth={52}
        className={classes.search}
      />

      <ScrollArea.Autosize mah={360} type="scroll">
        <Stack gap={2} p="xs">
          {resultados.length === 0 && (
            <Text size="sm" c="dimmed" ta="center" py="lg">
              Sin coincidencias para «{query}»
            </Text>
          )}

          {resultados.map((destino, i) => (
            <UnstyledButton
              key={destino.href}
              onClick={() => ir(destino)}
              onMouseEnter={() => setIndice(i)}
              className={classes.result}
              data-selected={i === indice || undefined}
            >
              <Group gap="sm" wrap="nowrap">
                <span className={classes.resultIcon}>{getNavIcon(destino.icon)}</span>
                <Text size="sm" fw={500} style={{ flex: 1, minWidth: 0 }} truncate>
                  {destino.label}
                </Text>
                <Text size="xs" c="dimmed" truncate maw={180}>
                  {destino.padre ? `${destino.grupo} · ${destino.padre}` : destino.grupo}
                </Text>
              </Group>
            </UnstyledButton>
          ))}
        </Stack>
      </ScrollArea.Autosize>
    </Modal>
  )
}
