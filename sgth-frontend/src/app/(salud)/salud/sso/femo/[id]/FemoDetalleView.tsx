'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Stack, Skeleton, Alert, Text, Button, Group, Card, Badge, SimpleGrid, Stepper } from '@mantine/core'
import {
  IconAlertCircle, IconStethoscope, IconDownload,
  IconEdit, IconUser, IconStretching, IconBriefcase,
  IconArrowLeft, IconArrowRight, IconCheck, IconX,
} from '@tabler/icons-react'
import { useFemoDetalle, useActualizarFemo } from
  '@/features/dispensario/hooks/useFemo'
import { useFemoWizardState } from
  '@/features/dispensario/hooks/useFemoWizardState'
import { usePdfFemo } from
  '@/features/dispensario/hooks/usePdfFemo'
import { FemoPaso1 } from
  '@/features/dispensario/components/femo/FemoPaso1'
import { FemoPasoExamenFisico } from
  '@/features/dispensario/components/femo/FemoPasoExamenFisico'
import { FemoPaso2 } from
  '@/features/dispensario/components/femo/FemoPaso2'
import { FemoPaso3 } from
  '@/features/dispensario/components/femo/FemoPaso3'
import {
  TIPO_FICHA_OPTIONS, APTITUD_OPTIONS, APTITUD_COLORS,
} from '@/features/dispensario/services/femoOptions'
import { PageHeader, PageShell } from '@/components/ui'

interface Props {
  id: string
}

export function FemoDetalleView({ id }: Props) {
  const femoId  = Number(id)
  const router  = useRouter()

  const [modo, setModo] = useState<'lectura' | 'edicion'>('lectura')

  const { data: ficha, isLoading, isError } = useFemoDetalle(femoId)
  const actualizar = useActualizarFemo()
  const { descargarFemo, loading: descargando } = usePdfFemo()

  const wizard = useFemoWizardState()

  useEffect(() => {
    if (ficha && modo === 'edicion') {
      wizard.cargarDesdeFicha(ficha)
      wizard.setActive(0)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ficha, modo])

  if (isLoading) {
    return (
      <Stack gap="md">
        <Skeleton height={60} radius="lg" />
        <Skeleton height={400} radius="lg" />
      </Stack>
    )
  }

  if (isError || !ficha) {
    return (
      <Alert icon={<IconAlertCircle size={16} />} color="red" variant="light" title="Ficha no encontrada">
        <Text size="sm">La ficha FEMO #{id} no existe o no está disponible.</Text>
      </Alert>
    )
  }

  const tipoLabel = TIPO_FICHA_OPTIONS.find(o => o.value === ficha.tipo_ficha)?.label ?? ficha.tipo_ficha
  const aptitudLabel = APTITUD_OPTIONS.find(o => o.value === ficha.aptitud)?.label ?? ficha.aptitud

  const handleGuardar = () => {
    const payload = wizard.construirPayload()
    if (!payload) return

    actualizar.mutate({ id: femoId, data: payload }, {
      onSuccess: () => setModo('lectura'),
    })
  }

  if (modo === 'edicion') {
    const pasos = [
      { label: 'Información del paciente', icon: <IconUser size={16} /> },
      { label: 'Examen físico',             icon: <IconStretching size={16} /> },
      { label: 'Evaluación laboral',        icon: <IconBriefcase size={16} /> },
      { label: 'Diagnóstico y cierre',      icon: <IconStethoscope size={16} /> },
    ]

    return (
      <PageShell>
        <PageHeader
          title={`Editar ficha FEMO #${femoId}`}
          description="Ficha de evaluación médica ocupacional"
          actions={
            <Button variant="default" leftSection={<IconX size={14} />} onClick={() => setModo('lectura')}>
              Cancelar edición
            </Button>
          }
        />

        <Stepper active={wizard.active} onStepClick={wizard.setActive} size="sm">
          {pasos.map((paso, i) => (
            <Stepper.Step key={i} label={paso.label} icon={paso.icon} />
          ))}
        </Stepper>

        <Card withBorder radius="lg" p="lg">
          {wizard.active === 0 && (
            <FemoPaso1
              fichaData={wizard.fichaData}
              constantesData={wizard.constantesData}
              antecedentes={wizard.antecedentes}
              antecedenteReproductivo={wizard.antecedenteReproductivo}
              consumoSustancias={wizard.consumoSustancias}
              onFichaChange={wizard.setFichaData}
              onAntecedentesChange={wizard.setAntecedentes}
              onAntecedenteReproductivoChange={wizard.setAntecedenteReproductivo}
              onConsumoSustanciasChange={wizard.setConsumoSustancias}
            />
          )}
          {wizard.active === 1 && (
            <FemoPasoExamenFisico
              examenFisico={wizard.examenFisico}
              onChange={wizard.setExamenFisico}
            />
          )}
          {wizard.active === 2 && (
            <FemoPaso2
              fichaData={wizard.fichaData}
              puestoId={null}
              actividadesRiesgo={wizard.actividadesRiesgo}
              factoresRiesgo={wizard.factoresRiesgo}
              empleosAnteriores={wizard.empleosAnteriores}
              onFichaChange={wizard.setFichaData}
              onActividadesChange={wizard.setActividadesRiesgo}
              onFactoresChange={wizard.setFactoresRiesgo}
              onEmpleosChange={wizard.setEmpleosAnteriores}
            />
          )}
          {wizard.active === 3 && (
            <FemoPaso3
              fichaData={wizard.fichaData}
              examenes={wizard.examenes}
              diagnosticos={wizard.diagnosticos}
              onFichaChange={wizard.setFichaData}
              onExamenesChange={wizard.setExamenes}
              onDiagnosticosChange={wizard.setDiagnosticos}
            />
          )}
        </Card>

        <Group justify="space-between">
          <Button
            variant="default"
            leftSection={<IconArrowLeft size={14} />}
            disabled={wizard.active === 0}
            onClick={() => wizard.setActive(a => a - 1)}
          >
            Anterior
          </Button>
          {wizard.active < 3 ? (
            <Button
              color="blue"
              rightSection={<IconArrowRight size={14} />}
              onClick={() => wizard.setActive(a => a + 1)}
            >
              Siguiente
            </Button>
          ) : (
            <Button
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={actualizar.isPending}
              onClick={handleGuardar}
            >
              Guardar cambios
            </Button>
          )}
        </Group>
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title={`Ficha FEMO #${femoId}`}
        description={
          ficha.servidor
            ? `${ficha.servidor.nombre} ${ficha.servidor.apellido}`
            : ficha.postulante
              ? `${ficha.postulante.nombres} ${ficha.postulante.apellidos}`
              : undefined
        }
        actions={
          <Group>
            <Button
              variant="default"
              leftSection={<IconDownload size={14} />}
              loading={descargando}
              onClick={() => descargarFemo(femoId, `femo-${femoId}.pdf`)}
            >
              Descargar PDF
            </Button>
            <Button
              color="blue"
              leftSection={<IconEdit size={14} />}
              onClick={() => setModo('edicion')}
            >
              Editar
            </Button>
          </Group>
        }
      />

      <SimpleGrid cols={{ base: 1, md: 3 }}>
        <Card withBorder radius="md" p="md">
          <Text size="xs" c="dimmed" tt="uppercase" fw={600}>Tipo de evaluación</Text>
          <Badge size="lg" variant="light" color="blue" mt={4}>{tipoLabel}</Badge>
        </Card>
        <Card withBorder radius="md" p="md">
          <Text size="xs" c="dimmed" tt="uppercase" fw={600}>Aptitud médica</Text>
          <Badge size="lg" variant="light" color={APTITUD_COLORS[ficha.aptitud] ?? 'gray'} mt={4}>
            {aptitudLabel}
          </Badge>
        </Card>
        <Card withBorder radius="md" p="md">
          <Text size="xs" c="dimmed" tt="uppercase" fw={600}>Fecha de evaluación</Text>
          <Text size="sm" fw={500} mt={4}>
            {new Date(ficha.fecha_evaluacion).toLocaleDateString('es-EC', {
              day: '2-digit', month: 'long', year: 'numeric',
            })}
          </Text>
        </Card>
      </SimpleGrid>

      <Card withBorder radius="lg" p="lg">
        <Stack gap="xs">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase">Puesto de trabajo</Text>
          <Text size="sm">{ficha.puesto_trabajo ?? 'No registrado'}</Text>
        </Stack>
      </Card>

      <SimpleGrid cols={{ base: 1, md: 2 }}>
        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">Antecedentes personales</Text>
          {(ficha.antecedentes ?? []).length === 0 ? (
            <Text size="sm" c="dimmed">Ninguno registrado.</Text>
          ) : (
            <Stack gap={4}>
              {ficha.antecedentes!.map((a, i) => (
                <Text key={i} size="sm">• {a.tipo}: {a.descripcion}</Text>
              ))}
            </Stack>
          )}
        </Card>

        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">Examen físico regional</Text>
          <Text size="sm">
            {(ficha.examen_fisico ?? []).filter(e => !e.normal).length} hallazgo(s) anormal(es) de{' '}
            {(ficha.examen_fisico ?? []).length} ítems evaluados.
          </Text>
        </Card>

        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">Factores de riesgo laboral</Text>
          {(ficha.factores_riesgo ?? []).length === 0 ? (
            <Text size="sm" c="dimmed">Ninguno registrado.</Text>
          ) : (
            <Stack gap={4}>
              {ficha.factores_riesgo!.map((f, i) => (
                <Text key={i} size="sm">• [{f.categoria}] {f.factor}</Text>
              ))}
            </Stack>
          )}
        </Card>

        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">Diagnósticos CIE-10</Text>
          {(ficha.diagnosticos ?? []).length === 0 ? (
            <Text size="sm" c="dimmed">Ninguno registrado.</Text>
          ) : (
            <Stack gap={4}>
              {ficha.diagnosticos!.map((d, i) => (
                <Text key={i} size="sm">
                  • {d.diagnostico?.codigo ?? '—'} — {d.diagnostico?.descripcion ?? ''} ({d.tipo})
                </Text>
              ))}
            </Stack>
          )}
        </Card>
      </SimpleGrid>

      {ficha.restricciones && (
        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">Restricciones</Text>
          <Text size="sm">{ficha.restricciones}</Text>
        </Card>
      )}

      {ficha.recomendaciones && (
        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">Recomendaciones y tratamiento</Text>
          <Text size="sm">{ficha.recomendaciones}</Text>
          {ficha.tratamiento && <Text size="sm" mt={4}>{ficha.tratamiento}</Text>}
        </Card>
      )}

      {ficha.tipo_ficha === 'retiro' && (
        <Card withBorder radius="lg" p="lg">
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">N. Retiro (evaluación)</Text>
          <Text size="sm">
            Se realiza la evaluación: {ficha.se_realiza_evaluacion_retiro ? 'Sí' : 'No'} · Condición
            relacionada con el trabajo: {ficha.condicion_relacionada_trabajo ? 'Sí' : 'No'}
          </Text>
          {ficha.observacion_retiro && <Text size="sm" mt={4}>{ficha.observacion_retiro}</Text>}
        </Card>
      )}

      <Button variant="subtle" onClick={() => router.push('/salud/sso/femo')}>
        Volver al listado
      </Button>
    </PageShell>
  )
}
