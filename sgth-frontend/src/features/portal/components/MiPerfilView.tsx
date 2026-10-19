'use client'

import { Button, Stack, Tabs, Text } from '@mantine/core'
import { IconFileDownload, IconSchool, IconUser, IconUserOff, IconUsers } from '@tabler/icons-react'
import { DataState, EmptyState, PageHeader, PageShell } from '@/components/ui'
import { DatosPersonalesTab } from '@/features/expediente/components/tabs/DatosPersonalesTab'
import { useServidor } from '@/features/expediente/hooks/useServidor'
import { useAuth } from '@/hooks/useAuth'
import { useDescargarHojaDeVida } from '../hooks/useMiPerfil'
import { MiFormacionAcademica } from './MiFormacionAcademica'
import { MisCargasFamiliares } from './MisCargasFamiliares'
import { MiSituacionLaboral } from './MiSituacionLaboral'

const TITULO      = 'Mi perfil'
const DESCRIPCION = 'Su ficha del expediente, su formación académica, sus cargas familiares y su hoja de vida.'

/**
 * «Mi perfil» del Portal del Servidor.
 *
 * El menú llevaba aquí, a «Mi CV» y a «Formación académica», y ninguna de las
 * tres páginas existía. Son los mismos datos —la hoja de vida se arma con la
 * ficha y la formación—, así que viven juntos (decisión del 2026-10-17).
 *
 * La ficha es la del expediente, que el titular ya podía leer
 * (`ServidorPolicy::ver`); aquí solo se consulta. Lo corrige Talento Humano.
 */
export function MiPerfilView() {
  const { usuario } = useAuth()
  const servidorId = usuario?.servidor?.id ?? null
  const { data: servidor, isLoading, error, refetch } = useServidor(servidorId)
  const descargar = useDescargarHojaDeVida()

  if (!servidorId) {
    return (
      <PageShell>
        <PageHeader title={TITULO} description={DESCRIPCION} />
        <EmptyState
          icon={IconUserOff}
          title="Su usuario no está vinculado a un servidor"
          description="La ficha y la hoja de vida salen del expediente del servidor. Pídale a Talento Humano que complete la vinculación."
        />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title={TITULO}
        description={DESCRIPCION}
        actions={
          <Button
            variant="light"
            leftSection={<IconFileDownload size={16} />}
            loading={descargar.isPending}
            onClick={() => descargar.mutate()}
          >
            Descargar hoja de vida
          </Button>
        }
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar su ficha"
        onRetry={() => void refetch()}
      >
        {servidor && (
          <Tabs defaultValue="datos" keepMounted={false}>
            <Tabs.List>
              <Tabs.Tab value="datos" leftSection={<IconUser size={14} />}>
                Datos personales
              </Tabs.Tab>
              <Tabs.Tab value="formacion" leftSection={<IconSchool size={14} />}>
                Formación académica
              </Tabs.Tab>
              <Tabs.Tab value="cargas" leftSection={<IconUsers size={14} />}>
                Cargas familiares
              </Tabs.Tab>
            </Tabs.List>

            <Tabs.Panel value="datos" pt="md">
              <Stack gap="lg">
                <Text size="sm" c="dimmed">
                  Si algún dato está mal o le falta, pídale a Talento Humano que lo corrija
                  en su expediente.
                </Text>
                <MiSituacionLaboral
                  servidor={servidor}
                  nombramiento={usuario?.servidor?.tipo_nombramiento_label}
                />
                <DatosPersonalesTab servidor={servidor} conCondicion={false} />
              </Stack>
            </Tabs.Panel>

            <Tabs.Panel value="formacion" pt="md">
              <MiFormacionAcademica />
            </Tabs.Panel>

            {/* Lo que registra el servidor no cuenta hasta que lo valida
                Talento Humano (docs/diseno-cargas-familiares-portal.md). */}
            <Tabs.Panel value="cargas" pt="md">
              <MisCargasFamiliares />
            </Tabs.Panel>
          </Tabs>
        )}
      </DataState>
    </PageShell>
  )
}
