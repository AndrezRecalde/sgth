'use client'

import { useState } from 'react'
import {
  Stepper, Button, Group, Stack,
  Card, Text, Paper,
} from '@mantine/core'
import {
  IconUser, IconBriefcase, IconStethoscope,
  IconArrowLeft, IconArrowRight, IconCheck,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
import { FemoPaso1 } from
  '@/features/dispensario/components/femo/FemoPaso1'
import { FemoPaso2 } from
  '@/features/dispensario/components/femo/FemoPaso2'
import { FemoPaso3 } from
  '@/features/dispensario/components/femo/FemoPaso3'
import { useCrearFemo } from
  '@/features/dispensario/hooks/useFemo'
import type {
  FichaBaseForm, AntecedenteForm, FactorRiesgoForm,
  ExamenForm, DiagnosticoFemoForm, EmpleoAnteriorForm,
} from '@/features/dispensario/schemas/femo.schema'

export default function NuevaFemoPage() {
  const router  = useRouter()
  const crear   = useCrearFemo()
  const [active, setActive] = useState(0)

  const [fichaData, setFichaData] =
    useState<Partial<FichaBaseForm>>({
      tipo_ficha:       'ingreso',
      aptitud:          'apto',
      grupo_embarazada: false,
      grupo_discapacidad: false,
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
        constantes_vitales: hayConstantes ? constantesData as Parameters<typeof crear.mutate>[0]['constantes_vitales'] : null,
        antecedentes:       antecedentes,
        factores_riesgo:    factoresRiesgo,
        diagnosticos:       diagnosticos,
        examenes:           examenes,
        empleos_anteriores: empleosAnteriores,
      },
      {
        onSuccess: () => router.push('/salud/sso/femo'),
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
            if (active === 0) router.push('/salud/sso/femo')
            else setActive(a => a - 1)
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
            loading={crear.isPending}
            onClick={handleGuardar}
          >
            Guardar ficha
          </Button>
        )}
      </Group>
    </Stack>
  )
}
