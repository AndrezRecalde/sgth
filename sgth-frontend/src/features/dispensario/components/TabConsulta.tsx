'use client'

import { Button, Group, Stack } from '@mantine/core'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck, IconX } from '@tabler/icons-react'
import { useState, useEffect, useRef } from 'react'
import { useBorradorConsulta } from '../hooks/useBorradorConsulta'
import {
  useRegistrarConsulta,
  useActualizarConsulta,
} from '../hooks/useConsultaMedica'
import { BorradorConsultaAviso } from './BorradorConsultaAviso'
import { ConsultaCampos } from './ConsultaCampos'
import { ConsultaGuardada } from './ConsultaGuardada'
import { useAuthStore } from '@/store/auth.store'
import {
  consultaMedicaSchema,
  type ConsultaMedicaFormData,
} from '../schemas/consultaMedica.schema'
import { hayAlgoEscrito } from '../utils/consultaMedica'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { DiagnosticoCie10 } from '../services/cie10Service'
import { fromDateValue } from '@/lib/fecha'

interface Props {
  turno: AgendaMedica
  historiaClinicaId: number
  consultaPrevia?: ConsultaMedica | null
  onGuardada: (consulta: ConsultaMedica) => void
}

/** Cómo arranca el formulario: sin nada escrito salvo lo que pidió el paciente. */
function valoresIniciales(turno: AgendaMedica): ConsultaMedicaFormData {
  return {
    tipo_atencion: 'primera_vez',
    tipo_diagnostico: 'presuntivo',
    motivo_consulta: turno.motivo_solicitud ?? '',
    enfermedad_actual: '',
    examen_fisico: '',
    diagnostico_detallado: '',
    plan_tratamiento: '',
    notas_medico: '',
  }
}

export function TabConsulta({
  turno,
  historiaClinicaId,
  consultaPrevia,
  onGuardada,
}: Props) {
  const registrar = useRegistrarConsulta()
  const actualizar = useActualizarConsulta()
  const { usuario } = useAuthStore()
  const [modoEdicion, setModoEdicion] = useState(!consultaPrevia)

  // Al cargarse una consulta previa —o al cambiar de consulta— el formulario
  // vuelve al modo lectura. Se ajusta durante el render, no en un efecto:
  // hacerlo en el efecto sacaba del modo edición en cada refresco de la
  // consulta, perdiendo lo que el médico estuviera escribiendo.
  const semillaConsulta = consultaPrevia?.id ?? null
  const [semillaAplicada, setSemillaAplicada] =
    useState<number | null>(semillaConsulta)

  if (semillaConsulta !== semillaAplicada) {
    setSemillaAplicada(semillaConsulta)
    setModoEdicion(!semillaConsulta)
  }

  const [cie10Principal, setCie10Principal] = useState<DiagnosticoCie10 | null>(null)
  const [cie10Secundarios, setCie10Secundarios] = useState<DiagnosticoCie10[]>([])

  const form = useForm<ConsultaMedicaFormData>({
    resolver: zodResolver(consultaMedicaSchema),
    defaultValues: valoresIniciales(turno),
  })
  const { control, handleSubmit, reset } = form

  // El borrador solo tiene sentido en una consulta que aún no existe: una ya
  // guardada se corrige, y esa corrección tiene su propio rastro versionado.
  const esConsultaNueva = !consultaPrevia
  const motivoInicial = turno.motivo_solicitud ?? ''
  const borradorCtl = useBorradorConsulta(turno.id, esConsultaNueva)
  const [recuperado, setRecuperado] = useState(false)
  const yaReseteado = useRef(false)

  const contenidoBorrador = esConsultaNueva
    ? (borradorCtl.borrador?.contenido as
        | (ConsultaMedicaFormData & {
            cie10_principal?: DiagnosticoCie10 | null
            cie10_secundarios?: DiagnosticoCie10[]
          })
        | undefined)
    : undefined

  // Se recupera una sola vez, al llegar el borrador: volver a aplicarlo en cada
  // refresco pisaría con una copia vieja lo que se está escribiendo. El ajuste
  // se hace durante el render y no en un efecto, igual que el del modo edición
  // de más arriba: React lo vuelve a pintar antes de que nada llegue a verse.
  const idBorrador = borradorCtl.borrador?.id ?? null
  const [borradorAplicado, setBorradorAplicado] = useState<number | null>(null)

  if (idBorrador !== null && idBorrador !== borradorAplicado) {
    setBorradorAplicado(idBorrador)

    // Un borrador puede no llevar nada dentro: no se anuncia como recuperación
    // de algo que nadie escribió.
    if (
      contenidoBorrador &&
      hayAlgoEscrito(
        contenidoBorrador,
        motivoInicial,
        contenidoBorrador.cie10_principal ?? null,
        contenidoBorrador.cie10_secundarios ?? [],
      )
    ) {
      setCie10Principal(contenidoBorrador.cie10_principal ?? null)
      setCie10Secundarios(contenidoBorrador.cie10_secundarios ?? [])
      setRecuperado(true)
    }
  }

  // Los campos del formulario sí van por efecto: `reset` toca el estado interno
  // de react-hook-form y no se puede llamar mientras se renderiza.
  useEffect(() => {
    if (!recuperado || yaReseteado.current || !contenidoBorrador) return

    yaReseteado.current = true
    const { cie10_principal, cie10_secundarios, ...campos } = contenidoBorrador
    void cie10_principal
    void cie10_secundarios
    reset(campos)
  }, [recuperado, contenidoBorrador, reset])

  // Lo que hay escrito ahora mismo, para el guardado automático.
  const valores = useWatch({ control })
  const huella = JSON.stringify([valores, cie10Principal, cie10Secundarios])

  useEffect(() => {
    if (!esConsultaNueva) return
    // Hasta que no se recupere lo guardado no se anota nada: si no, el
    // formulario vacío del primer render pisaría el borrador que iba a llegar.
    if (borradorCtl.cargando) return
    if (!hayAlgoEscrito(
      valores, motivoInicial, cie10Principal, cie10Secundarios
    )) {
      return
    }

    borradorCtl.anotar({
      ...valores,
      cie10_principal: cie10Principal,
      cie10_secundarios: cie10Secundarios,
    })
    // `huella` resume el contenido: `valores` es un objeto nuevo en cada render
    // y dispararía el efecto sin que nada hubiera cambiado.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [huella, esConsultaNueva, borradorCtl.cargando])

  useEffect(() => {
    if (consultaPrevia) {
      reset({
        tipo_atencion:
          (consultaPrevia.tipo_atencion as ConsultaMedicaFormData['tipo_atencion']) ??
          'primera_vez',
        tipo_diagnostico:
          (consultaPrevia.tipo_diagnostico as ConsultaMedicaFormData['tipo_diagnostico']) ??
          'presuntivo',
        motivo_consulta: consultaPrevia.motivo_consulta ?? '',
        enfermedad_actual: consultaPrevia.enfermedad_actual ?? '',
        examen_fisico: consultaPrevia.examen_fisico ?? '',
        diagnostico_detallado: consultaPrevia.diagnostico_detallado ?? '',
        plan_tratamiento: consultaPrevia.plan_tratamiento ?? '',
        notas_medico: consultaPrevia.notas_medico ?? '',
      })
    }
  }, [consultaPrevia, reset])

  const descartarBorrador = () => {
    reset(valoresIniciales(turno))
    setCie10Principal(null)
    setCie10Secundarios([])
    setRecuperado(false)
    void borradorCtl.descartar()
  }

  /** Lo que el formulario manda al servidor, igual al registrar que al corregir. */
  const datosClinicos = (values: ConsultaMedicaFormData) => ({
    tipo_atencion: values.tipo_atencion,
    tipo_diagnostico: values.tipo_diagnostico,
    motivo_consulta: values.motivo_consulta,
    enfermedad_actual: values.enfermedad_actual || null,
    examen_fisico: values.examen_fisico || null,
    diagnostico_cie10_id: cie10Principal?.id ?? null,
    diagnosticos_secundarios: cie10Secundarios.map((d) => d.id),
    diagnostico_detallado: values.diagnostico_detallado,
    plan_tratamiento: values.plan_tratamiento || null,
    notas_medico: values.notas_medico || null,
  })

  const onSubmit = (values: ConsultaMedicaFormData) => {
    if (consultaPrevia) {
      actualizar.mutate(
        { id: consultaPrevia.id, data: datosClinicos(values) },
        { onSuccess: () => setModoEdicion(false) },
      )
      return
    }

    const ahora = new Date()
    registrar.mutate(
      {
        historia_clinica_id: historiaClinicaId,
        agenda_medica_id: turno.id,
        fecha_consulta: fromDateValue(ahora),
        hora_consulta: ahora.toTimeString().slice(0, 5),
        ...datosClinicos(values),
      },
      {
        onSuccess: (consulta) => {
          // El servidor ya retiró el borrador al registrar la consulta; esto
          // corta el guardado que pudiera quedar en vuelo y limpia la caché.
          borradorCtl.olvidar()
          onGuardada(consulta)
        },
      },
    )
  }

  if (!modoEdicion && consultaPrevia) {
    return (
      <ConsultaGuardada
        consulta={consultaPrevia}
        usuarioId={usuario?.id}
        onEditar={() => setModoEdicion(true)}
      />
    )
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="sm" p="md">
        <BorradorConsultaAviso
          borrador={borradorCtl}
          recuperado={recuperado}
          esConsultaNueva={esConsultaNueva}
          onDescartar={descartarBorrador}
        />

        {consultaPrevia && (
          <Group justify="flex-end">
            <Button
              size="xs"
              variant="subtle"
              leftSection={<IconX size={13} />}
              onClick={() => setModoEdicion(false)}
            >
              Cancelar edición
            </Button>
          </Group>
        )}

        <ConsultaCampos
          form={form}
          cie10Principal={cie10Principal}
          onCie10Principal={setCie10Principal}
          cie10Secundarios={cie10Secundarios}
          onCie10Secundarios={setCie10Secundarios}
        />

        <Group justify="flex-end" pt="sm">
          <Button
            type="submit"
            leftSection={<IconCheck size={14} />}
            loading={registrar.isPending || actualizar.isPending}
          >
            {consultaPrevia ? 'Guardar cambios' : 'Guardar consulta'}
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
