'use client'

import { Alert, Button, Stack, Text } from '@mantine/core'
import { IconDownload, IconId, IconInfoCircle } from '@tabler/icons-react'
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
  const faltan = credencial?.datos_faltantes ?? []

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
            {faltan.length > 0 && (
              <Alert variant="light" color="ocean" icon={<IconInfoCircle size={18} />} title="Faltan datos en su expediente">
                {faltan.map((f, i) => (i === 0 ? f : f.toLowerCase())).join(' y ')}
                {faltan.length === 1 ? ' no está registrado, así que no sale' : ' no están registrados, así que no salen'} en su
                credencial. Pida a Talento Humano que {faltan.length === 1 ? 'lo registre' : 'los registre'}: la credencial
                {faltan.length === 1 ? ' lo toma' : ' los toma'} sola.
              </Alert>
            )}
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
