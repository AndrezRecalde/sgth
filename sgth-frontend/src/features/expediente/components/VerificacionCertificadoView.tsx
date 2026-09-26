'use client'

import { Alert, Center, Container, Paper, Skeleton, Stack, Text, Title } from '@mantine/core'
import { IconCircleCheck, IconClockExclamation, IconFileOff } from '@tabler/icons-react'
import { DetailList, SectionHeading, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { useVerificacionCertificado } from '../hooks/useVerificacionCertificado'

interface Props {
  codigo: string
}

/**
 * Comprobación pública de un certificado laboral. Aquí llega quien recibió el
 * papel —un banco, el IESS— siguiendo la dirección impresa en el pie.
 *
 * Enseña lo justo para cotejar contra el documento que tiene delante. La
 * cédula viene enmascarada del backend y la remuneración no viaja nunca, ni
 * cuando el certificado se emitió con ella.
 */
export function VerificacionCertificadoView({ codigo }: Props) {
  const { data: certificado, isLoading, isError } = useVerificacionCertificado(codigo)

  return (
    <Container size="sm" py="xl">
      <Stack gap="lg">
        <div>
          <Title order={1} size="h2">Verificación de certificado</Title>
          <Text size="sm" c="dimmed">
            GAD Provincial de Esmeraldas · Unidad de Administración del Talento Humano
          </Text>
        </div>

        {isLoading && <Skeleton height={280} radius="lg" />}

        {isError && (
          <Alert
            variant="light"
            color="red"
            icon={<IconFileOff size={18} />}
            title="No se encontró este certificado"
          >
            Ningún certificado emitido por esta institución corresponde al código
            <Text span fw={600} ff="monospace"> {codigo}</Text>. Compruebe que lo
            transcribió completo; si el documento lo dice tal cual, no salió de aquí.
          </Alert>
        )}

        {certificado && (
          <Paper withBorder radius="lg" p="lg">
            <Stack gap="md">
              <Center>
                {certificado.vigente ? (
                  <StatusBadge tone="success" size="lg">
                    <IconCircleCheck size={14} style={{ verticalAlign: 'middle' }} />
                    {' '}Certificado auténtico y vigente
                  </StatusBadge>
                ) : (
                  <StatusBadge tone="warning" size="lg">
                    <IconClockExclamation size={14} style={{ verticalAlign: 'middle' }} />
                    {' '}Auténtico, pero vencido
                  </StatusBadge>
                )}
              </Center>

              {!certificado.vigente && (
                <Alert variant="light" color="amber">
                  Este certificado lo emitió esta institución, pero su vigencia
                  terminó el {formatFecha(certificado.vence_en)}. Solicite uno
                  nuevo si necesita información al día.
                </Alert>
              )}

              <SectionHeading title={certificado.documento} />

              <DetailList
                items={[
                  { label: 'Nombre', value: certificado.nombre_completo, ancho: true },
                  { label: 'Cédula', value: certificado.cedula },
                  { label: 'Código de verificación', value: certificado.codigo },
                  { label: 'Puesto', value: certificado.puesto, ancho: true },
                  { label: 'Unidad administrativa', value: certificado.unidad, ancho: true },
                  {
                    label: 'Tiempo de servicio',
                    value: certificado.anios_servicio != null
                      ? `${certificado.anios_servicio} ${certificado.anios_servicio === 1 ? 'año' : 'años'}`
                      : null,
                  },
                  { label: 'Fecha de emisión', value: formatFecha(certificado.emitido_en) },
                  { label: 'Válido hasta', value: formatFecha(certificado.vence_en) },
                  { label: 'Firmado por', value: certificado.firmante, ancho: true },
                  { label: 'Cargo del firmante', value: certificado.firmante_cargo, ancho: true },
                ]}
              />

              <Text size="xs" c="dimmed">
                Los datos corresponden al expediente en la fecha de emisión. La
                cédula se muestra parcialmente: coteje los cuatro últimos
                dígitos con el documento en papel.
              </Text>
            </Stack>
          </Paper>
        )}
      </Stack>
    </Container>
  )
}
