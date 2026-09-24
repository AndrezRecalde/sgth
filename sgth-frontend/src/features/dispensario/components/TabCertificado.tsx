'use client'

import { Button, Group, Stack, Text } from '@mantine/core'
import { IconCertificate, IconPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import {
  useCertificadosPorConsulta, useAnularCertificado,
  useDescargarCertificado,
} from '../hooks/useCertificado'
import { EmitirCertificadoModal } from './EmitirCertificadoModal'
import { CertificadoItem } from './CertificadoItem'
import {
  AnularRegistroModal, MOTIVOS_ANULAR_CERTIFICADO,
} from './AnularRegistroModal'
import { DataState } from '@/components/ui'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { CertificadoMedico } from '../services/certificadoService'

interface Props {
  turno:    AgendaMedica
  consulta: ConsultaMedica
}

export function TabCertificado({ turno, consulta }: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)

  const { data: certificados = [], isLoading, error, refetch } =
    useCertificadosPorConsulta(consulta.id)
  const anular = useAnularCertificado(consulta.id)
  const { descargar, descargando } = useDescargarCertificado()
  const [aAnular, setAAnular] = useState<CertificadoMedico | null>(null)

  const esFamiliar = !!turno.carga_familiar_id

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Certificados de esta consulta
          {certificados.length > 0 && (
            <Text span c="dimmed" ml={4}>
              ({certificados.length})
            </Text>
          )}
        </Text>
        <Button
          size="xs"
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
        >
          Nuevo certificado
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los certificados"
        errorHint="No quiere decir que esta consulta no tenga certificados emitidos: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!certificados.length}
        emptyProps={{
          icon: IconCertificate,
          title: 'Sin certificados',
          description: 'No se han emitido certificados para esta consulta.',
        }}
        skeletonRows={2}
      >
        <Stack gap="sm">
          {certificados.map((cert) => (
            <CertificadoItem
              key={cert.id}
              certificado={cert}
              descargando={descargando === cert.id}
              onDescargar={() => descargar(cert.id, cert.folio)}
              onAnular={() => setAAnular(cert)}
            />
          ))}
        </Stack>
      </DataState>

      <EmitirCertificadoModal
        opened={modalOpened}
        onClose={cerrarModal}
        consulta={consulta}
        esFamiliar={esFamiliar}
      />

      <AnularRegistroModal
        opened={!!aAnular}
        onClose={() => setAAnular(null)}
        titulo="Anular certificado médico"
        descripcion={
          aAnular?.permiso_servidor
            ? `Se anulará ${aAnular.folio} y con él el permiso ` +
              `${aAnular.permiso_servidor.folio}, que dejará de justificar ` +
              'la ausencia.'
            : `Se anulará ${aAnular?.folio ?? ''}.`
        }
        motivos={MOTIVOS_ANULAR_CERTIFICADO}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!aAnular) return
          anular.mutate(
            { id: aAnular.id, motivo },
            { onSuccess: () => setAAnular(null) }
          )
        }}
      />
    </Stack>
  )
}
