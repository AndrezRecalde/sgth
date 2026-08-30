'use client'

import { useState } from 'react'
import {
  Box, Button, Center, Container, Group, SegmentedControl,
  Stack, Text, Title, Switch,
} from '@mantine/core'
import {
  IconFileTypePdf, IconHierarchy, IconListTree, IconSitemap,
} from '@tabler/icons-react'
import Image from 'next/image'
import { DataState } from '@/components/ui'
import { OrganigramaChart } from '@/features/estructura/components/OrganigramaChart'
import { OrganigramaTree } from '@/features/estructura/components/OrganigramaTree'
import { useOrganigrama } from '@/features/estructura/hooks/useOrganigrama'
import { estructuraService } from '@/features/estructura/services/estructuraService'

/**
 * El organigrama tal como lo ve cualquier persona, con sesión o sin ella.
 *
 * No monta el shell del sistema: quien llega aquí puede no tener usuario, y un
 * menú lateral de módulos privados no le dice nada. Reutiliza en cambio los
 * mismos dos componentes de árbol y de nodos que la pantalla interna, para que
 * lo que se publica y lo que ve Talento Humano no se separen con el tiempo.
 *
 * Sin sesión el endpoint devuelve solo la estructura —ni puestos, ni personas,
 * ni subrogaciones—, así que aquí no hay nada que ocultar por interfaz.
 */
export function OrganigramaPublicoView() {
  const [vista, setVista] = useState<'acordeon' | 'nodo'>('nodo')
  const [subprocesos, setSubprocesos] = useState(true)

  const { data: organigrama, isLoading, error } = useOrganigrama()

  return (
    <Box mih="100dvh" bg="var(--sgth-surface-sunken)" py="xl">
      <Container size={1400}>
        <Stack gap="lg">
          <Group justify="space-between" align="flex-start" wrap="wrap">
            <Group gap="md" wrap="nowrap">
              <Image
                src="/logo.png"
                alt="GAD Provincial de Esmeraldas"
                width={56}
                height={56}
                style={{ objectFit: 'contain' }}
              />
              <Stack gap={2}>
                <Title order={1} size="h3">Organigrama institucional</Title>
                <Text size="sm" c="dimmed">
                  Estructura orgánica del GAD Provincial de Esmeraldas,
                  con sus unidades administrativas y subprocesos.
                </Text>
              </Stack>
            </Group>

            <Button
              variant="light"
              color="emerald"
              leftSection={<IconFileTypePdf size={16} />}
              component="a"
              href={estructuraService.organigramaPdfUrl()}
              target="_blank"
              rel="noopener"
            >
              Descargar PDF
            </Button>
          </Group>

          <Group justify="space-between">
            <SegmentedControl
              value={vista}
              onChange={(v) => setVista(v as 'acordeon' | 'nodo')}
              color="emerald"
              data={[
                {
                  value: 'nodo',
                  label: (
                    <Center style={{ gap: 10 }}>
                      <IconHierarchy size={14} />
                      Nodos
                    </Center>
                  ),
                },
                {
                  value: 'acordeon',
                  label: (
                    <Center style={{ gap: 10 }}>
                      <IconListTree size={14} />
                      Lista
                    </Center>
                  ),
                },
              ]}
            />

            {vista === 'nodo' && (
              <Switch
                label="Mostrar subprocesos"
                color="emerald"
                checked={subprocesos}
                onChange={(e) => setSubprocesos(e.currentTarget.checked)}
              />
            )}
          </Group>

          <DataState
            loading={isLoading}
            error={error}
            empty={!organigrama?.length}
            emptyProps={{
              icon: IconSitemap,
              title: 'El organigrama aún no está publicado',
              description:
                'La institución todavía no ha registrado su estructura orgánica.',
            }}
            skeletonRows={8}
          >
            {vista === 'nodo' ? (
              <OrganigramaChart
                unidades={organigrama ?? []}
                mostrarSubprocesos={subprocesos}
              />
            ) : (
              <OrganigramaTree unidades={organigrama ?? []} />
            )}
          </DataState>
        </Stack>
      </Container>
    </Box>
  )
}
