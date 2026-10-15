'use client'

import { AspectRatio, Box, Group, Image, Paper, SimpleGrid, Stack, Text } from '@mantine/core'
import type { DatosInstitucionales, VarianteIdentidad } from '../services/tiposIdentidad'

interface Props {
  variante: VarianteIdentidad
  /** La imagen elegida, todavía sin subir. */
  src: string
  datos: DatosInstitucionales | undefined
}

function Rotulo({ children }: { children: string }) {
  return <Text size="xs" c="dimmed" mb={4}>{children}</Text>
}

/** El sidebar, en claro y en oscuro: el isotipo con las siglas. */
function EnSidebar({ src, datos }: { src: string; datos: DatosInstitucionales | undefined }) {
  return (
    <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="xs">
      {(['white', 'dark.7'] as const).map((fondo) => (
        <div key={fondo}>
          <Rotulo>{fondo === 'white' ? 'Sidebar en modo claro' : 'Sidebar en modo oscuro'}</Rotulo>
          <Paper withBorder p="sm" bg={fondo}>
            <Group gap="xs" wrap="nowrap">
              <Image src={src} alt="" w={32} h={32} fit="contain" />
              <Stack gap={0}>
                <Text size="sm" fw={700} c={fondo === 'white' ? 'dark.7' : 'white'}>{datos?.siglas}</Text>
                <Text size="xs" c={fondo === 'white' ? 'slate.6' : 'slate.3'}>{datos?.nombre_corto}</Text>
              </Stack>
              <Group gap={4} ml="auto" align="center">
                <Image src={src} alt="" w={16} h={16} fit="contain" />
                <Image src={src} alt="" w={32} h={32} fit="contain" />
              </Group>
            </Group>
          </Paper>
        </div>
      ))}
    </SimpleGrid>
  )
}

/** La cabecera de un documento: el logo, el nombre oficial y el secundario a la derecha. */
function EnDocumento({ src, datos, secundario }: { src: string; datos: DatosInstitucionales | undefined; secundario: boolean }) {
  return (
    <div>
      <Rotulo>Cabecera de un documento</Rotulo>
      <Paper withBorder p="md" bg="white">
        <Group wrap="nowrap" gap="md">
          {secundario ? (
            <Box w={110} h={36} bg="slate.1" />
          ) : (
            <Image src={src} alt="" h={40} w="auto" maw={150} fit="contain" />
          )}
          <Stack gap={0} style={{ flex: 1 }} align="flex-end">
            <Text size="xs" fw={700} c="dark.7" ta="right">{datos?.nombre_oficial}</Text>
            <Text size="xs" c="slate.6" ta="right">DIRECCIÓN DE GESTIÓN DE TALENTO HUMANO</Text>
          </Stack>
          {secundario && <Image src={src} alt="" h={40} w="auto" maw={90} fit="contain" />}
        </Group>
      </Paper>
    </div>
  )
}

/**
 * Cómo se vería el archivo elegido donde se usa, antes de subirlo
 * (docs/diseno-comunicacion-social.md, 4.4). Es una imitación: la pantalla de
 * verdad se ve al guardar.
 */
export function VistaPreviaContexto({ variante, src, datos }: Props) {
  switch (variante) {
    case 'isotipo':
      return <EnSidebar src={src} datos={datos} />
    case 'logo_documentos':
      return <EnDocumento src={src} datos={datos} secundario={false} />
    case 'logo_secundario_documentos':
      return <EnDocumento src={src} datos={datos} secundario />
    case 'hoja_membretada':
      return (
        <div>
          <Rotulo>La hoja A4, de fondo</Rotulo>
          <AspectRatio ratio={210 / 297} w={160}>
            <Paper withBorder bg="white"><Image src={src} alt="" fit="cover" /></Paper>
          </AspectRatio>
        </div>
      )
    default: {
      const oscuro = variante === 'imagotipo_negativo'
      return (
        <div>
          <Rotulo>{oscuro ? 'Inicio de sesión en modo oscuro' : 'Inicio de sesión y correos'}</Rotulo>
          <Paper withBorder p="lg" bg={oscuro ? 'dark.7' : 'white'}>
            <Image src={src} alt="" h={64} w="auto" maw="100%" fit="contain" />
          </Paper>
        </div>
      )
    }
  }
}
