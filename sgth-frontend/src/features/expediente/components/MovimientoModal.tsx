'use client'

import React from 'react'
import { Alert, Button, Center, Grid, Loader, Stack, Stepper } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { useForm, useWatch, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconInfoCircle } from '@tabler/icons-react'
import { crearMovimientoSchema, type MovimientoFormData } from '../schemas/movimiento.schema'
import { SituacionActualPanel } from './SituacionActualPanel'
import { useGuardarAccionPersonal } from '../hooks/useGuardarAccionPersonal'
import { useCatalogoAcciones } from '../hooks/useCatalogoAcciones'
import {
  buscarClase, causalesDisponibles, clasesDisponibles, type VinculoDelServidor,
} from '../utils/catalogoAcciones'
import type {
  CatalogoAccionesPersonal, FamiliaAccionPersonal, MovimientoPersonal,
} from '@/types/api'
import { getApiErrorMessage } from '@/types/api'
import { MovimientoPasoTipo } from './MovimientoPasoTipo'
import { MovimientoSituacionPropuesta } from './MovimientoSituacionPropuesta'
import { MovimientoDatosDelActo } from './MovimientoDatosDelActo'
import { valoresDelBorrador, VALORES_EN_BLANCO } from '../utils/movimientoIniciales'

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
  /** El nombramiento del vínculo vigente. Decide qué clases se ofrecen. */
  tipoNombramiento?: string | null
  /** El servidor todavía no tiene vínculo: solo cabe el ingreso, que lo crea. */
  sinVinculo?: boolean
  /**
   * Presente = modo edición sobre un borrador. El mismo formulario sirve para
   * crear y para corregir: separarlos era lo que producía dos pantallas con
   * campos distintos y ninguna con todos.
   */
  movimiento?: MovimientoPersonal | null
  /**
   * Abre con la familia ya elegida: desde el selector de «Nueva acción de
   * personal», o el ingreso desde su propia pantalla. Si en ella hay una sola
   * clase sin causal, no hay nada que preguntar y el paso de selección se salta.
   */
  familia?: FamiliaAccionPersonal
  /** Encabezado del modal cuando el contexto ya dice de quién se trata. */
  titulo?: string
}

export function MovimientoModal({
  opened, onClose, servidorId, tipoNombramiento, sinVinculo, movimiento = null,
  familia, titulo,
}: Props) {
  const catalogo = useCatalogoAcciones()

  const etiquetaFamilia = familia
    ? catalogo.data?.familias.find((f) => f.codigo === familia)?.etiqueta
    : undefined

  const encabezado = titulo
    ?? (movimiento
      ? 'Editar acción de personal en borrador'
      : etiquetaFamilia ?? 'Registrar acción de personal')

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
        catalogo.isPending ? (
          <Center py="xl"><Loader size="sm" /></Center>
        ) : catalogo.isError ? (
          <Alert icon={<IconInfoCircle size={16} />} color="red" variant="light">
            {getApiErrorMessage(catalogo.error, 'No se pudo cargar el catálogo de acciones de personal.')}
          </Alert>
        ) : movimiento && !movimiento.editable_en_formulario ? (
          /* La subrogación y el encargo nacen en su propia pantalla, y la
             bitácora del expediente no se edita. Abrirlos aquí dejaba un
             formulario que no los representa, y «Guardar cambios» no hacía
             nada ni decía por qué. */
          <>
            <Alert icon={<IconInfoCircle size={16} />} color="amber" variant="light">
              «{movimiento.etiqueta}» no se corrige con este formulario: nace en su
              propia pantalla. Anule el borrador y registre la acción que
              corresponda.
            </Alert>
            <ModalFooter onCancel={onClose} cancelLabel="Cerrar" sinPrincipal />
          </>
        ) : (
          <FormularioAccion
            key={movimiento?.id ?? familia ?? 'nuevo'}
            catalogo={catalogo.data}
            servidorId={servidorId}
            vinculo={{ sinVinculo, tipoNombramiento }}
            movimiento={movimiento}
            familia={familia}
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
 * los datos que necesita. Qué bloque aparece lo dice la clase elegida, que
 * trae del catálogo lo que pide.
 */
function FormularioAccion({
  catalogo,
  servidorId,
  vinculo,
  movimiento,
  familia,
  onClose,
}: {
  catalogo: CatalogoAccionesPersonal
  servidorId: number
  vinculo: VinculoDelServidor
  movimiento?: MovimientoPersonal | null
  familia?: FamiliaAccionPersonal
  onClose: () => void
}) {
  const edicion = !!movimiento

  const clases = clasesDisponibles(catalogo, vinculo, familia)
  const unicaClase = clases.length === 1 ? clases[0] : undefined

  /**
   * Sin paso de selección: al editar, porque cambiar la naturaleza del acto no
   * es editarlo; y cuando la familia elegida deja una sola clase sin causal,
   * porque ya no hay nada que preguntar.
   *
   * Con causal —la cesación— el paso sigue haciendo falta aunque la clase sea
   * una sola: es la causal la que determina las reglas y el documento.
   */
  const sinPasoDeTipo = edicion || (!!unicaClase && unicaClase.causales.length === 0)

  const [paso, setPaso] = React.useState(sinPasoDeTipo ? 1 : 0)

  const iniciales: DefaultValues<MovimientoFormData> = edicion
    ? valoresDelBorrador(movimiento!)
    : {
      ...VALORES_EN_BLANCO,
      clase: unicaClase?.codigo,
      requiere_dictamen_medico: unicaClase?.dictamen_medico_por_defecto ?? false,
    }

  const schema = React.useMemo(() => crearMovimientoSchema(catalogo), [catalogo])

  const form = useForm<MovimientoFormData>({
    resolver: zodResolver(schema),
    defaultValues: iniciales,
  })

  const { control, handleSubmit, reset, setValue, formState: { errors } } = form

  const claseElegida = buscarClase(catalogo, useWatch({ control, name: 'clase' }))
  const causalElegida = useWatch({ control, name: 'causal' })

  const causales = causalesDisponibles(claseElegida, vinculo.tipoNombramiento)
  const esIngreso = !!claseElegida?.pide_contratacion
  const muestraPropuesta = !!claseElegida?.pide_situacion_propuesta

  const handleClose = () => {
    reset(VALORES_EN_BLANCO)
    onClose()
  }

  /**
   * Elegir clase o causal arrastra el valor inicial de la ficha de salud
   * ocupacional: abre marcada en el ingreso, la jubilación y la incapacidad, y
   * sigue siendo editable. Se deriva aquí y no en un efecto: es consecuencia
   * directa de elegir, no una sincronización con nada externo.
   */
  const elegirClase = (codigo: string | null) => {
    const clase = buscarClase(catalogo, codigo)

    setValue('clase', clase?.codigo ?? '')
    setValue('causal', null)
    setValue('requiere_dictamen_medico', clase?.dictamen_medico_por_defecto ?? false)
  }

  const elegirCausal = (codigo: string | null) => {
    const causal = claseElegida?.causales.find((c) => c.codigo === codigo)

    setValue('causal', causal?.codigo ?? null)
    setValue(
      'requiere_dictamen_medico',
      causal?.dictamen_medico_por_defecto ?? claseElegida?.dictamen_medico_por_defecto ?? false,
    )
  }

  const guardar = useGuardarAccionPersonal({
    servidorId,
    movimientoId: movimiento?.id,
    catalogo,
    onGuardado: handleClose,
  })

  if (!sinPasoDeTipo && clases.length === 0) {
    return (
      <Alert icon={<IconInfoCircle size={16} />} color="amber" variant="light">
        El servidor no tiene un contrato vigente elegible para ninguna acción de
        personal formal, o no tiene contrato vigente registrado.
      </Alert>
    )
  }

  const soloFaltaLaCausal = !!unicaClase && unicaClase.causales.length > 0

  return (
    <form onSubmit={handleSubmit((v) => guardar.mutate(v))} noValidate>
      <Stepper
        active={paso}
        onStepClick={sinPasoDeTipo ? undefined : setPaso}
        size="sm"
      >
        {/* Con la clase ya decidida, lo único que queda por elegir aquí es la
            causal — y el rótulo debe decirlo solo cuando de verdad la haya. */}
        <Stepper.Step
          label={soloFaltaLaCausal ? 'Causal' : 'Tipo de acción'}
          description={soloFaltaLaCausal ? 'Por qué termina' : 'Qué se va a registrar'}
        >
          <MovimientoPasoTipo
            form={form}
            catalogo={catalogo}
            clases={clases}
            causales={causales}
            claseElegida={claseElegida}
            causalElegida={causalElegida}
            preguntaLaClase={!unicaClase}
            agruparPorFamilia={!familia}
            elegirClase={elegirClase}
            elegirCausal={elegirCausal}
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
                    tipoNombramiento={vinculo.tipoNombramiento}
                  />
                </Grid.Col>
              </Grid>
            )}

            <MovimientoDatosDelActo form={form} clase={claseElegida} />

            {/* Red de seguridad: si algo no valida, se dice. Con el error en
                cada campo bastaría, pero esto cubre también lo que valide el
                esquema mañana y quede fuera de la vista —un campo del paso
                anterior, o uno oculto por la clase elegida—. */}
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
