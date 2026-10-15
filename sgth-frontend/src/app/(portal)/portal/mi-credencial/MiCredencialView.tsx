'use client'

import { Button, Stack, Text } from '@mantine/core'
import { IconDownload, IconId } from '@tabler/icons-react'
import { DataState, EmptyState, PageHeader, PageShell } from '@/components/ui'
import { CredencialDigital } from '@/features/comunicacion/components/CredencialDigital'
import { useDescargarMiCredencial, useMiCredencial } from '@/features/comunicacion/hooks/useCredenciales'

/**
 * «Mi credencial» del Portal (docs/diseno-comunicacion-social.md, 6.1; fase
 * 3.2): la credencial digital con los datos de hoy y su PDF. Siempre la propia.
 */
export function MiCredencialView() {
  const { data, isLoading, error } = useMiCredencial()
  const descargar = useDescargarMiCredencial()
  const credencial = data?.credencial ?? null

  return (
    <PageShell>
      <PageHeader
        title="Mi credencial"
        description="Su credencial institucional. Quien escanee el código comprueba que es auténtica y está vigente."
        actions={credencial?.vigente ? (
          <Button
            variant="light"
            leftSection={<IconDownload size={16} />}
            loading={descargar.isPending}
            onClick={() => descargar.mutate()}
          >
            Descargar en PDF
          </Button>
        ) : undefined}
      />

      <DataState loading={isLoading} error={error}>
        {credencial ? (
          <Stack gap="md">
            <CredencialDigital credencial={credencial} />
            <Text size="sm" c="dimmed" ta="center" maw={560} mx="auto">
              Los datos salen de su expediente. Si alguno está mal, pida a Talento Humano que lo corrija. Si pierde la
              tarjeta impresa, avise a Comunicación Social para que la revoque y le entregue otra.
            </Text>
          </Stack>
        ) : (
          <EmptyState icon={IconId} title="Todavía no tiene credencial" description={data?.mensaje} />
        )}
      </DataState>
    </PageShell>
  )
}
