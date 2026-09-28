'use client'

import React from 'react'
import { Alert, Button, Grid, Stack, Stepper } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { useForm, useWatch, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconInfoCircle } from '@tabler/icons-react'
import { movimientoSchema, type MovimientoFormData } from '../schemas/movimiento.schema'
import { SituacionActualPanel } from './SituacionActualPanel'
import { useGuardarAccionPersonal } from '../hooks/useGuardarAccionPersonal'
import {
  TIPO_LABELS, esTipoDelFormulario, etiquetaTipoMovimiento,
  requiereDictamenPorDefecto, requiereSubtipo, reubicaAlServidor,
  subtiposElegibles, tiposElegibles,
  type AccionSubtipo, type AccionTipo,
} from '../utils/taxonomiaAccionPersonal'
import type { MovimientoPersonal } from '@/types/api'
import { MovimientoPasoTipo } from './MovimientoPasoTipo'
import { MovimientoSituacionPropuesta } from './MovimientoSituacionPropuesta'
import { MovimientoDatosDelActo } from './MovimientoDatosDelActo'
import { valoresDelBorrador, VALORES_EN_BLANCO } from '../utils/movimientoIniciales'

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
  tipoNombramiento?: string | null
  /**
   * Presente = modo edición sobre un borrador. El mismo formulario sirve para
   * crear y para corregir: separarlos era lo que producía dos pantallas con
   * campos distintos y ninguna con todos.
   */
  movimiento?: MovimientoPersonal | null
  /**
   * Crea con el tipo ya decidido y sin paso de selección. Lo usa Ingreso y
   * Vinculación, que se registra desde su propia pantalla y no compite con los
   * demás tipos: el servidor todavía no tiene vínculo sobre el que actuar.
   */
  tipoFijo?: AccionTipo
  /** Encabezado del modal cuando el contexto ya dice de quién se trata. */
  titulo?: string
}

export function MovimientoModal({
  opened, onClose, servidorId, tipoNombramiento, movimiento = null,
  tipoFijo, titulo,
}: Props) {
  const encabezado = titulo
    ?? (movimiento
      ? 'Editar acción de personal en borrador'
      : tipoFijo
        ? TIPO_LABELS[tipoFijo]
        : 'Registrar acción de personal')

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={encabezado}
      size="xl"
    >
      {/* Se monta al abrir, así el formulario arranca limpio sin resetear
          estado desde un efecto. */}
      {opened && (
        movimiento && !esTipoDelFormulario(movimiento.tipo_movimiento) ? (
          /* Un borrador de tipo plano legado —traslado, traspaso, comisión de
             servicios, destitución— o de subrogación. También nacen en borrador,
             pero este formulario no los representa: se abría igual, entraba con
             un tipo que el esquema Zod rechaza, y «Guardar cambios» no hacía
             nada ni decía por qué. */
          <>
            <Alert icon={<IconInfoCircle size={16} />} color="amber" variant="light">
              «{etiquetaTipoMovimiento(movimiento.tipo_movimiento)}» no se corrige
              con este formulario: es un tipo anterior a la taxonomía de dos
              niveles, o nace en su propia pantalla. Anule el borrador y registre
              la acción que corresponda.
            </Alert>
            <ModalFooter onCancel={onClose} cancelLabel="Cerrar" sinPrincipal />
          </>
        ) : (
          <FormularioAccion
            key={movimiento?.id ?? tipoFijo ?? 'nuevo'}
            servidorId={servidorId}
            tipoNombramiento={tipoNombramiento}
            movimiento={movimiento}
            tipoFijo={tipoFijo}
            onClose={onClose}
          />
        )
      )}
    </SgthModal>
  )
}

/**
 * Orquesta el formulario: decide los pasos, junta los bloques y envía.
 *
 * Los cuatro bloques —paso de tipo, situación propuesta, datos de la
 * contratación y datos del acto— viven en sus propios archivos y cada uno pide
 * los datos que necesita. Aquí quedan solo las decisiones que son de todo el
 * formulario.
 */
function FormularioAccion({
  servidorId,
  tipoNombramiento,
  movimiento,
  tipoFijo,
  onClose,
}: {
  servidorId: number
  tipoNombramiento?: string | null
  movimiento?: MovimientoPersonal | null
  tipoFijo?: AccionTipo
  onClose: () => void
}) {
  const edicion = !!movimiento
  /**
   * Sin paso de selección: al editar, porque cambiar la naturaleza del acto no
   * es editarlo; y con tipo fijo, porque ya venía decidido desde la pantalla.
   *
   * Salvo que ese tipo exija subtipo —cesación, cambio administrativo, régimen
   * disciplinario—: ahí el paso sigue haciendo falta, porque es el subtipo el
   * que determina las reglas y el documento. Saltárselo dejaba un formulario
   * que el backend rechazaba por un dato que nunca se pidió.
   */
  const sinPasoDeTipo = edicion || (!!tipoFijo && !requiereSubtipo(tipoFijo))

  const [paso, setPaso] = React.useState(sinPasoDeTipo ? 1 : 0)

  const tipos = tiposElegibles(tipoNombramiento)

  /** Garantizado no nulo en modo edición por la guarda del envoltorio. */
  const tipoDelBorrador = movimiento && esTipoDelFormulario(movimiento.tipo_movimiento)
    ? movimiento.tipo_movimiento
    : null

  const iniciales: DefaultValues<MovimientoFormData> = edicion
    ? valoresDelBorrador(movimiento!, tipoDelBorrador)
    : { ...VALORES_EN_BLANCO, tipo_movimiento: tipoFijo ?? tipos[0] }

  const form = useForm<MovimientoFormData>({
    resolver: zodResolver(movimientoSchema),
    defaultValues: iniciales,
  })

  const { control, handleSubmit, reset, setValue, formState: { errors } } = form

  const tipo = useWatch({ control, name: 'tipo_movimiento' })
  const subtipo = useWatch({ control, name: 'subtipo_movimiento' })

  const subtipos = tipo ? subtiposElegibles(tipo, tipoNombramiento) : []
  const esIngreso = tipo === 'ingreso'
  // El ingreso siempre propone puesto y unidad: es donde nace el vínculo.
  const muestraPropuesta = reubicaAlServidor(tipo, subtipo) || esIngreso

  const handleClose = () => {
    reset(VALORES_EN_BLANCO)
    onClose()
  }

  /**
   * El dictamen médico viene pre-marcado en jubilación e incapacidad, igual
   * que en el backend, y sigue siendo editable. Se deriva aquí y no en un
   * efecto: es consecuencia directa de elegir el subtipo, no una
   * sincronización con nada externo.
   */
  const elegirSubtipo = (valor: AccionSubtipo | null) => {
    setValue('subtipo_movimiento', valor)
    setValue('requiere_dictamen_medico', requiereDictamenPorDefecto(valor))
  }

  const guardar = useGuardarAccionPersonal({
    servidorId,
    movimientoId: movimiento?.id,
    onGuardado: handleClose,
  })

  if (!sinPasoDeTipo && tipos.length === 0) {
    return (
      <Alert icon={<IconInfoCircle size={16} />} color="amber" variant="light">
        El servidor no tiene un contrato vigente elegible para ninguna acción de
        personal formal, o no tiene contrato vigente registrado.
      </Alert>
    )
  }

  return (
    <form onSubmit={handleSubmit((v) => guardar.mutate(v))} noValidate>
      <Stepper
        active={paso}
        onStepClick={sinPasoDeTipo ? undefined : setPaso}
        size="sm"
      >
        {/* Con el tipo ya fijado, lo único que queda por elegir aquí es el
            subtipo — y el rótulo debe decirlo solo cuando de verdad lo haya. */}
        <Stepper.Step
          label={tipoFijo && requiereSubtipo(tipoFijo) ? 'Subtipo' : 'Tipo de acción'}
          description={
            tipoFijo && requiereSubtipo(tipoFijo)
              ? 'Bajo qué figura'
              : 'Qué se va a registrar'
          }
        >
          <MovimientoPasoTipo
            form={form}
            tipos={tipos}
            subtipos={subtipos}
            tipo={tipo}
            subtipo={subtipo}
            tipoFijo={tipoFijo}
            elegirSubtipo={elegirSubtipo}
            onCancel={handleClose}
            onContinuar={() => setPaso(1)}
          />
        </Stepper.Step>

        <Stepper.Step label="Detalle" description="Datos de la acción">
          <Stack gap="sm" mt="md">
            {muestraPropuesta && (
              <Grid>
                <Grid.Col span={{ base: 12, md: 6 }}>
                  <SituacionActualPanel servidorId={servidorId} />
                </Grid.Col>
                <Grid.Col span={{ base: 12, md: 6 }}>
                  <MovimientoSituacionPropuesta
                    form={form}
                    esIngreso={esIngreso}
                    tipoNombramiento={tipoNombramiento}
                  />
                </Grid.Col>
              </Grid>
            )}

            <MovimientoDatosDelActo form={form} />

            {/* Red de seguridad: si algo no valida, se dice.
                Ocho campos no pintaban su error, así que un texto de más de 100
                caracteres en «Número de contrato» o un ingreso sin unidad dejaban
                el botón sin efecto: `handleSubmit` no llama a la mutación y no
                había nada en pantalla. Con el error en cada campo basta para los
                ocho, pero esto cubre también lo que valide el esquema mañana y
                quede fuera de la vista —un campo del paso anterior, o uno oculto
                por el subtipo elegido—. */}
            {Object.keys(errors).length > 0 && (
              <Alert color="red" variant="light" icon={<IconInfoCircle size={16} />}>
                Revise los campos marcados: hay {Object.keys(errors).length} dato(s)
                que el formulario no puede enviar todavía.
              </Alert>
            )}

            <ModalFooter
              onCancel={handleClose}
              leftSection={!sinPasoDeTipo && (
                <Button variant="default" onClick={() => setPaso(0)}>Atrás</Button>
              )}
              submitLabel={edicion ? 'Guardar cambios' : 'Registrar en borrador'}
              submitting={guardar.isPending}
            />
          </Stack>
        </Stepper.Step>
      </Stepper>
    </form>
  )
}
