'use client'

import { useEffect, useMemo } from 'react'
import {
  TextInput, Select, Textarea, Grid, Switch, Divider, Text, Badge, Group,
  Skeleton, Stack,
} from '@mantine/core'
import {
  useForm, useWatch, Controller,
  type Resolver, type DefaultValues,
} from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTiposUnidad } from '../hooks/useTiposUnidad'
import { useTodasUnidades } from '../hooks/useUnidades'
import { useCodigoSugerido } from '../hooks/useCodigoSugerido'
import { PROFUNDIDAD_MAXIMA, etiquetaNivel } from '../utils/jerarquia'
import {
  unidadSchema, unidadConPadreSchema, type UnidadFormData,
} from '../schemas/unidad.schema'
import type { UnidadAdministrativa } from '@/types/api'

interface Props {
  initialValues?: Partial<UnidadFormData>
  onSubmit: (values: UnidadFormData) => void
  isPending: boolean
  /** Al editar, la unidad que se edita: no puede ser su propio padre. */
  unidadId?: number | null
}

export function UnidadForm({ initialValues, onSubmit, unidadId }: Props) {
  const contained = useContainedInput()
  const { data: tiposRaw }    = useTiposUnidad()
  const { data: unidadesRaw, isLoading: cargandoUnidades } = useTodasUnidades()

  const tipos    = tiposRaw ?? []
  const unidades = useMemo(() => unidadesRaw ?? [], [unidadesRaw])

  const esEdicion = unidadId != null

  // La raíz ya registrada. Mientras exista, ninguna otra unidad puede quedarse
  // sin padre: sería una segunda raíz, y ni el organigrama de nodos ni el PDF
  // dibujan más de una.
  const raiz = useMemo(
    () => unidades.find(u => (u.unidad_padre_id ?? null) === null) ?? null,
    [unidades]
  )
  const padreObligatorio = raiz !== null && raiz.id !== unidadId

  const {
    register,
    control,
    setValue,
    handleSubmit,
    formState: { errors },
  } = useForm<UnidadFormData>({
    resolver: zodResolver(
      padreObligatorio ? unidadConPadreSchema : unidadSchema
    ) as Resolver<UnidadFormData>,
    // `tipo_unidad_id` es obligatorio, así que su ausencia se expresa
    // OMITIENDO la clave, no poniéndola en `null`: el tipo del formulario es
    // el de salida del esquema, y un `null` ahí obligaría a asertar.
    defaultValues: {
      nombre:          initialValues?.nombre          ?? '',
      codigo:          initialValues?.codigo          ?? '',
      acronimo:        initialValues?.acronimo        ?? '',
      ...(initialValues?.tipo_unidad_id
        ? { tipo_unidad_id: initialValues.tipo_unidad_id }
        : {}),
      unidad_padre_id: initialValues?.unidad_padre_id ?? null,
      descripcion:     initialValues?.descripcion     ?? '',
      estado:          initialValues?.estado          ?? true,
      es_unidad_talento_humano: initialValues?.es_unidad_talento_humano ?? false,
      es_maxima_autoridad:      initialValues?.es_maxima_autoridad      ?? false,
    } satisfies DefaultValues<UnidadFormData>,
  })

  const tipoOptions = tipos.map(t => ({
    value: String(t.id),
    label: t.descripcion ?? t.acronimo ?? `Tipo ${t.id}`,
  }))

  /**
   * Padres posibles, ordenados como el árbol y con la sangría del nivel.
   *
   * Se descartan tres casos que el backend rechazaría igualmente, para que el
   * error aparezca antes de llenar el formulario y no después de enviarlo:
   * la propia unidad, su descendencia (crearía un ciclo) y las del último
   * nivel, que no pueden tener nada debajo.
   */
  const unidadOptions = useMemo(() => {
    const hijosPorPadre = new Map<number | null, UnidadAdministrativa[]>()
    for (const u of unidades) {
      const padre = u.unidad_padre_id ?? null
      hijosPorPadre.set(padre, [...(hijosPorPadre.get(padre) ?? []), u])
    }

    const descendientes = new Set<number>()
    if (unidadId != null) {
      const pendientes = [unidadId]
      while (pendientes.length) {
        const actual = pendientes.pop()!
        for (const hijo of hijosPorPadre.get(actual) ?? []) {
          descendientes.add(hijo.id)
          pendientes.push(hijo.id)
        }
      }
    }

    const opciones: { value: string; label: string }[] = []

    const recorrer = (padre: number | null, profundidad: number) => {
      for (const u of hijosPorPadre.get(padre) ?? []) {
        const esInvalida =
          u.id === unidadId ||
          descendientes.has(u.id) ||
          (u.nivel ?? 1) >= PROFUNDIDAD_MAXIMA

        if (!esInvalida) {
          opciones.push({
            value: String(u.id),
            label: `${'— '.repeat(profundidad)}${u.nombre ?? `Unidad ${u.id}`}`,
          })
        }

        recorrer(u.id, profundidad + 1)
      }
    }

    recorrer(null, 0)
    return opciones
  }, [unidades, unidadId])

  // El nivel no se captura: lo fija el padre. Se muestra para que quien
  // registra sepa qué está creando antes de guardar.
  //
  // `useWatch` y no `watch()`: el compilador de React no puede memoizar la
  // función que devuelve `useForm`, y con ella se salta la optimización del
  // componente entero.
  const padreSeleccionado = useWatch({ control, name: 'unidad_padre_id' })
  const nivelResultante = useMemo(() => {
    if (padreSeleccionado == null) return 1
    const padre = unidades.find(u => u.id === padreSeleccionado)
    return (padre?.nivel ?? 1) + 1
  }, [padreSeleccionado, unidades])

  // El código se propone a partir de la jerarquía —`GADPE-01-03`— en cuanto se
  // elige la unidad superior, y queda editable: a veces tiene que coincidir
  // con el orgánico funcional aprobado o con la codificación presupuestaria.
  // Al editar no se sugiere nada: el código ya existe y puede estar impreso.
  const { data: codigoSugerido } = useCodigoSugerido(
    padreSeleccionado ?? null,
    { habilitado: !esEdicion }
  )

  // La sugerencia solo rellena el hueco: lo que ya está escrito no se pisa.
  // Cambiar de unidad superior vacía el campo (lo hace el propio selector),
  // y entonces esto vuelve a proponer el código de la rama nueva — un
  // `GADPE-01-02` heredado de otro padre sería sencillamente falso.
  const codigoActual = useWatch({ control, name: 'codigo' })

  useEffect(() => {
    if (codigoSugerido && !codigoActual) {
      setValue('codigo', codigoSugerido, { shouldValidate: true })
    }
  }, [codigoSugerido, codigoActual, setValue])

  // Sin la lista de unidades el formulario miente: «Depende de» sale vacío
  // aunque venga preseleccionado, no se sabe si el padre es obligatorio y el
  // nivel que anuncia la insignia es el equivocado. Mejor no dibujarlo hasta
  // que llegue, que enseñar un estado falso donde ya se puede teclear.
  if (cargandoUnidades) {
    return (
      <Stack gap="sm">
        <Skeleton height={56} radius="md" />
        <Skeleton height={56} radius="md" />
        <Skeleton height={56} radius="md" />
        <Skeleton height={80} radius="md" />
      </Stack>
    )
  }

  return (
    <form id="unidad-form" onSubmit={handleSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <TextInput
            label="Nombre"
            placeholder="Nombre de la unidad"
            withAsterisk
            {...contained}
            {...register('nombre')}
            error={errors.nombre?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TextInput
            label="Código"
            placeholder={esEdicion ? 'Ej: GADPE-01' : 'Se propone al elegir la unidad superior'}
            withAsterisk
            {...contained}
            {...register('codigo')}
            error={errors.codigo?.message}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TextInput
            label="Acrónimo"
            placeholder="Ej: UATH"
            {...contained}
            {...register('acronimo')}
            error={errors.acronimo?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <Controller
            name="tipo_unidad_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de proceso"
                placeholder="Seleccionar tipo"
                withAsterisk
                data={tipoOptions}
                searchable
                {...contained}
                value={field.value ?? null}
                onChange={(v) => field.onChange(v || null)}
                error={errors.tipo_unidad_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <Controller
            name="unidad_padre_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Depende de"
                placeholder={padreObligatorio
                  ? 'Seleccionar unidad superior'
                  : 'Sin unidad superior (institución)'}
                withAsterisk={padreObligatorio}
                data={unidadOptions}
                searchable
                // Con la institución ya registrada, vaciar este campo crearía
                // una segunda raíz que ninguna de las dos vistas dibuja.
                clearable={!padreObligatorio}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : null)
                  // Al crear, el código cuelga de la rama: cambiar de padre lo
                  // invalida, así que se vacía y se vuelve a proponer. Al
                  // editar no se toca — es un identificador ya emitido.
                  if (!esEdicion) setValue('codigo', '')
                }}
                error={errors.unidad_padre_id?.message}
              />
            )}
          />

          {/*
            El nivel resultante va DEBAJO del selector, no en su `description`.
            El patrón contained dibuja la descripción encima del control, y
            ahí esta frase quedaba flotando entre dos campos, leyéndose como
            un enunciado suelto en vez de como la consecuencia de lo que se
            acaba de elegir.
          */}
          <Group gap="xs" mt={6} wrap="nowrap">
            {padreObligatorio && padreSeleccionado == null ? (
              // Anunciar «Nivel 1 · Institución» aquí sería prometer lo único
              // que no se puede crear: la institución ya está registrada.
              <Text size="xs" c="dimmed">
                Elija la unidad superior para saber en qué nivel quedará.
              </Text>
            ) : (
              <>
                <Badge size="sm" variant="light" color="emerald">
                  Nivel {nivelResultante} · {etiquetaNivel(nivelResultante)}
                </Badge>
                {nivelResultante >= PROFUNDIDAD_MAXIMA && (
                  <Text size="xs" c="dimmed">
                    Último nivel: no podrá tener unidades debajo.
                  </Text>
                )}
              </>
            )}
          </Group>
        </Grid.Col>

        <Grid.Col span={12}>
          <Textarea
            label="Descripción"
            placeholder="Descripción o misión de la unidad administrativa"
            rows={3}
            {...contained}
            {...register('descripcion')}
            error={errors.descripcion?.message}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Switch
                label="Unidad activa"
                description="Las unidades inactivas no aparecen en el organigrama."
                checked={field.value !== false}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <Divider
            my="xs"
            label="Firmas de Acciones de Personal"
            labelPosition="left"
          />
          <Text size="xs" c="dimmed" mb="xs">
            El jefe de la unidad marcada es quien firma los documentos. Solo una
            unidad puede llevar cada marca: al activarla aquí se desactiva en la
            que la tuviera antes.
          </Text>
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="es_maxima_autoridad"
            control={control}
            render={({ field }) => (
              <Switch
                label="Unidad de la máxima autoridad"
                description="Su jefe firma como Autoridad Nominadora."
                checked={!!field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="es_unidad_talento_humano"
            control={control}
            render={({ field }) => (
              <Switch
                label="Unidad de Talento Humano"
                description="Su jefe firma como Responsable de Talento Humano."
                checked={!!field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </form>
  )
}
