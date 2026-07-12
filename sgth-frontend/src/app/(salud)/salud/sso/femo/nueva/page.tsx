'use client'

import { useState, useEffect } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import {
  Stepper, Button, Group, Stack,
  Card, Text, Alert, Badge,
} from '@mantine/core'
import {
  IconUser, IconBriefcase, IconStethoscope,
  IconArrowLeft, IconArrowRight, IconCheck,
  IconInfoCircle,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { FemoPaso1 } from
  '@/features/dispensario/components/femo/FemoPaso1'
import { FemoPaso2 } from
  '@/features/dispensario/components/femo/FemoPaso2'
import { FemoPaso3 } from
  '@/features/dispensario/components/femo/FemoPaso3'
import { useCrearFemo } from
  '@/features/dispensario/hooks/useFemo'
import { useCompletarSolicitud } from
  '@/features/dispensario/hooks/useSolicitudCertificacion'
import type {
  FichaBaseForm, AntecedenteForm, FactorRiesgoForm,
  ExamenForm, DiagnosticoFemoForm, EmpleoAnteriorForm,
} from '@/features/dispensario/schemas/femo.schema'
import api from '@/lib/axios'

const TIPO_FICHA_POR_EVENTO: Record<string, FichaBaseForm['tipo_ficha']> = {
  ingreso:   'ingreso',
  reintegro: 'reintegro',
  periodica: 'periodica',
  retiro:    'retiro',
  especial:  'ingreso',
}

const TIPO_EVENTO_LABELS: Record<string, string> = {
  ingreso:   'Ingreso / Pre-ocupacional',
  reintegro: 'Reintegro',
  periodica: 'Periódica',
  retiro:    'Retiro',
  especial:  'Especial',
}

function fromDate(d: Date): string {
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

export default function NuevaFemoPage() {
  const router       = useRouter()
  const searchParams = useSearchParams()
  const crear        = useCrearFemo()
  const completar    = useCompletarSolicitud()

  const solicitudId    = searchParams.get('solicitud')
  const cedulaParam    = searchParams.get('cedula')
  const nombresParam   = searchParams.get('nombres')
  const tipoEventoParam = searchParams.get('tipo_evento')
    ?? 'ingreso'

  const [active, setActive] = useState(0)
  const [servidorId, setServidorId] =
    useState<number | null>(null)

  const [fichaData, setFichaData] =
    useState<Partial<FichaBaseForm>>({
      tipo_ficha:        TIPO_FICHA_POR_EVENTO[tipoEventoParam]
        ?? 'ingreso',
      aptitud:           'apto',
      grupo_embarazada:  false,
      grupo_discapacidad: false,
      fecha_evaluacion:  fromDate(new Date()),
    })

  const [constantesData, setConstantesData] =
    useState<Record<string, number | null>>({})
  const [antecedentes, setAntecedentes] =
    useState<AntecedenteForm[]>([])
  const [factoresRiesgo, setFactoresRiesgo] =
    useState<FactorRiesgoForm[]>([])
  const [empleosAnteriores, setEmpleosAnteriores] =
    useState<EmpleoAnteriorForm[]>([])
  const [examenes, setExamenes] =
    useState<ExamenForm[]>([])
  const [diagnosticos, setDiagnosticos] =
    useState<DiagnosticoFemoForm[]>([])

  useEffect(() => {
    if (!cedulaParam) return
    api.get('/expediente/servidores', {
      params: { search: cedulaParam, per_page: 1 },
    }).then(res => {
      const datos = res.data?.datos
      const items = Array.isArray(datos)
        ? datos
        : Array.isArray(datos?.data)
          ? datos.data
          : []
      const srv = items[0]
      if (srv) {
        setServidorId(srv.id)
        setFichaData(prev => ({
          ...prev,
          servidor_id: srv.id,
        }))
      }
    }).catch(() => {})
  }, [cedulaParam])

  const pasos = [
    {
      label:       'Información del paciente',
      description: 'Datos, signos vitales y antecedentes',
      icon:        <IconUser size={16} />,
    },
    {
      label:       'Evaluación laboral',
      description: 'Factores de riesgo y empleos anteriores',
      icon:        <IconBriefcase size={16} />,
    },
    {
      label:       'Diagnóstico y cierre',
      description: 'Exámenes, CIE-10 y aptitud médica',
      icon:        <IconStethoscope size={16} />,
    },
  ]

  const puedeAvanzar = () => {
    if (active === 0) {
      return !!(
        fichaData.servidor_id &&
        fichaData.fecha_evaluacion &&
        fichaData.tipo_ficha
      )
    }
    return true
  }

  const handleGuardar = () => {
    if (!fichaData.servidor_id || !fichaData.fecha_evaluacion) return

    const hayConstantes = Object.values(constantesData)
      .some(v => v !== null && v !== undefined)

    crear.mutate(
      {
        ficha: {
          servidor_id:       fichaData.servidor_id!,
          fecha_evaluacion:  fichaData.fecha_evaluacion!,
          tipo_ficha:        fichaData.tipo_ficha!,
          aptitud:           fichaData.aptitud!,
          puesto_trabajo:    fichaData.puesto_trabajo ?? null,
          puesto_trabajo_ciuo: fichaData.puesto_trabajo_ciuo ?? null,
          fecha_ingreso_trabajo: fichaData.fecha_ingreso_trabajo ?? null,
          grupo_embarazada:  fichaData.grupo_embarazada ?? false,
          grupo_discapacidad: fichaData.grupo_discapacidad ?? false,
          porcentaje_discapacidad: fichaData.porcentaje_discapacidad ?? null,
          restricciones:     fichaData.restricciones ?? null,
          observaciones:     fichaData.observaciones ?? null,
          enfermedad_actual: fichaData.enfermedad_actual ?? null,
          recomendaciones:   fichaData.recomendaciones ?? null,
          tratamiento:       fichaData.tratamiento ?? null,
          condicion_relacionada_trabajo:
            fichaData.condicion_relacionada_trabajo ?? null,
        },
        constantes_vitales: hayConstantes
          ? constantesData as Parameters<typeof crear.mutate>[0]['constantes_vitales']
          : null,
        antecedentes,
        factores_riesgo:    factoresRiesgo,
        diagnosticos,
        examenes,
        empleos_anteriores: empleosAnteriores,
      },
      {
        onSuccess: (ficha) => {
          if (solicitudId) {
            completar.mutate(
              {
                id:          Number(solicitudId),
                fichaFemoId: ficha?.id,
              },
              {
                onSuccess: () =>
                  router.push('/salud/sso'),
              }
            )
          } else {
            router.push('/salud/sso/femo')
          }
        },
      }
    )
  }

  return (
    <Stack gap="md">
      <PageHeader
        title="Nueva ficha FEMO"
        subtitle="Ficha de evaluación médica ocupacional"
        icon={<IconStethoscope size={24} />}
      />

      {solicitudId && (
        <Alert
          color="blue"
          variant="light"
          icon={<IconInfoCircle size={16} />}
        >
          <Group gap="xs">
            <Text size="xs">
              Solicitud de Talento Humano —
            </Text>
            <Badge size="xs" variant="light" color="blue">
              {TIPO_EVENTO_LABELS[tipoEventoParam]}
            </Badge>
            {nombresParam && (
              <Text size="xs" fw={600}>
                {decodeURIComponent(nombresParam)}
              </Text>
            )}
            {cedulaParam && (
              <Text size="xs" c="dimmed" ff="monospace">
                {cedulaParam}
              </Text>
            )}
          </Group>
        </Alert>
      )}

      <Stepper
        active={active}
        onStepClick={setActive}
        size="sm"
      >
        {pasos.map((paso, i) => (
          <Stepper.Step
            key={i}
            label={paso.label}
            description={paso.description}
            icon={paso.icon}
          />
        ))}
      </Stepper>

      <Card withBorder radius="lg" p="lg">
        {active === 0 && (
          <FemoPaso1
            fichaData={fichaData}
            constantesData={constantesData}
            antecedentes={antecedentes}
            onFichaChange={setFichaData}
            onConstantesChange={setConstantesData}
            onAntecedentesChange={setAntecedentes}
          />
        )}
        {active === 1 && (
          <FemoPaso2
            factoresRiesgo={factoresRiesgo}
            empleosAnteriores={empleosAnteriores}
            onFactoresChange={setFactoresRiesgo}
            onEmpleosChange={setEmpleosAnteriores}
          />
        )}
        {active === 2 && (
          <FemoPaso3
            fichaData={fichaData}
            examenes={examenes}
            diagnosticos={diagnosticos}
            onFichaChange={setFichaData}
            onExamenesChange={setExamenes}
            onDiagnosticosChange={setDiagnosticos}
          />
        )}
      </Card>

      <Group justify="space-between">
        <Button
          variant="default"
          leftSection={<IconArrowLeft size={14} />}
          onClick={() => {
            if (active === 0) {
              router.push(
                solicitudId ? '/salud/sso' : '/salud/sso/femo'
              )
            } else {
              setActive(a => a - 1)
            }
          }}
        >
          {active === 0 ? 'Cancelar' : 'Anterior'}
        </Button>

        {active < 2 ? (
          <Button
            color="blue"
            rightSection={<IconArrowRight size={14} />}
            disabled={!puedeAvanzar()}
            onClick={() => setActive(a => a + 1)}
          >
            Siguiente
          </Button>
        ) : (
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={crear.isPending || completar.isPending}
            onClick={handleGuardar}
          >
            {solicitudId
              ? 'Guardar FEMO y completar solicitud'
              : 'Guardar ficha'}
          </Button>
        )}
      </Group>
    </Stack>
  )
}
