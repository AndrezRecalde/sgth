'use client'

import { Alert, Anchor, Stack, Text } from '@mantine/core'
import { IconBolt } from '@tabler/icons-react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ConvocatoriaForm } from '@/features/seleccion/components/ConvocatoriaForm'
import { useCrearConvocatoria } from '@/features/seleccion/hooks/useConvocatoria'
import { CAMPOS_CONVOCATORIA } from '@/features/seleccion/schemas/convocatoria.schema'

/**
 * Solo crea concursos de méritos y oposición. Las cuatro modalidades que no
 * pasan por concurso no abren una convocatoria por proceso: sus aspirantes se
 * inscriben en los contenedores permanentes de Reclutamiento Express.
 */
export function NuevaConvocatoriaView() {
  const router = useRouter()
  const crear  = useCrearConvocatoria()

  return (
    <PageShell>
      <PageHeader
        title="Nueva convocatoria"
        description="Concurso de méritos y oposición para un puesto del organigrama"
      />

      <Stack gap="md">
        <Alert color="amethyst" variant="light" icon={<IconBolt size={16} />}>
          <Text size="xs">
            Los nombramientos provisionales, los servicios ocasionales, los
            servicios profesionales y el Código del Trabajo no pasan por
            concurso: sus aspirantes se registran en{' '}
            <Anchor component={Link} href={ROUTES.SGTH.RECLUTAMIENTO_EXPRESS} size="xs" fw={600}>
              Reclutamiento Express
            </Anchor>.
          </Text>
        </Alert>

        <ConvocatoriaForm
          textoEnviar="Crear convocatoria"
          enviando={crear.isPending}
          onCancelar={() => router.push(ROUTES.SGTH.CONVOCATORIAS)}
          onSubmit={(valores, setError) =>
            crear.mutateAsync({ ...valores, tipo_proceso: 'formal' })
              .then((conv) => router.push(ROUTES.SGTH.CONVOCATORIA(conv.id)))
              .catch((e) => erroresAlFormulario(e, setError, CAMPOS_CONVOCATORIA, 'No se pudo crear la convocatoria'))
          }
        />
      </Stack>
    </PageShell>
  )
}
