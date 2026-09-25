'use client'

import { confirmar, DataState, PAGINACION_ES, SgthModal, SgthTable, TableActions } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Grid, Group, TextInput, NumberInput, Button, Text, Select, Alert,
} from '@mantine/core'
import { Controller, useForm, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus, IconClock, IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useHorasTrabajadas, useHorasTrabajadasMutations } from '../hooks/useHorasTrabajadas'
import {
  horasTrabajadasSchema, type HorasTrabajadasFormData,
} from '../schemas/horasTrabajadas.schema'
import { EJEMPLO_PERIODO } from '../constants/periodo'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { UnidadConRelaciones } from '@/types/api'
import type { HorasTrabajadasPeriodo } from '../services/tipos'
import type { DataTableColumn } from 'mantine-datatable'

const VALORES_INICIALES: HorasTrabajadasFormData = {
  periodo: '',
  unidad_administrativa_id: null,
  total_horas: 1,
}

interface Props {
  opened: boolean
  onClose: () => void
}

export function GestionarHorasTrabajadasModal({ opened, onClose }: Props) {
  const contained = useContainedInput()

  // La tabla estaba sin paginador contra un endpoint que pagina de 15 en 15:
  // el registro 16 y los siguientes existían, contaban para los índices y no
  // había forma de verlos ni de borrarlos. Con carga mensual son 15 meses.
  const [page, setPage] = useState(1)

  const { data, isLoading, error, refetch } = useHorasTrabajadas({ page })
  const registros = data?.data ?? []
  const { registrar, eliminar } = useHorasTrabajadasMutations()
  const { data: unidades = [], error: errorUnidades } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = ((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
    value: String(u.id), label: u.nombre ?? `Unidad ${u.id}`,
  }))

  // El formulario no validaba nada: «2026-13» o «el año pasado» salían hacia
  // el servidor y el 422 volvía como notificación, sin decir qué campo estaba
  // mal. Zod comprueba el formato con la misma expresión que usa `PeriodoSso`
  // en el backend, y lo que solo el servidor puede saber —que el período ya
  // está cargado— cae en su campo.
  const {
    register, control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<HorasTrabajadasFormData>({
    resolver: zodResolver(horasTrabajadasSchema) as Resolver<HorasTrabajadasFormData>,
    defaultValues: VALORES_INICIALES,
  })

  const guardar = (valores: HorasTrabajadasFormData) => {
    registrar.mutateAsync({
      periodo: valores.periodo,
      unidad_administrativa_id: valores.unidad_administrativa_id ?? undefined,
      total_horas: valores.total_horas,
    })
      .then(() => {
        reset(VALORES_INICIALES)
        // El listado va por período descendente: lo que se acaba de cargar
        // aparece en la primera página, no en la que se esté mirando.
        setPage(1)
      })
      .catch((error) => {
        const campos = erroresDeCampo(error)
        if (!campos) return // el hook ya lo notificó
        for (const [campo, mensaje] of Object.entries(campos)) {
          setError(campo as keyof HorasTrabajadasFormData, { message: mensaje })
        }
      })
  }

  const columns: DataTableColumn<HorasTrabajadasPeriodo>[] = [
    { accessor: 'periodo', title: 'Período', width: 110 },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (r) => r.unidad_administrativa?.nombre ?? 'Total institucional',
    },
    {
      accessor: 'total_horas',
      title: 'Horas',
      width: 110,
      render: (r) => r.total_horas.toLocaleString(),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (r) => (
        <TableActions
          actions={[
            {
              label: 'Eliminar registro',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title:   'Eliminar registro de horas',
                message: (
                  <>
                    Se eliminará el registro de <b>{r.periodo}</b>. Los índices CD 513 de ese
                    período se quedan sin denominador hasta que se vuelva a cargar.
                  </>
                ),
                destructiva: true,
                onConfirm: () => eliminar.mutate(r.id),
              }),
            },
          ]}
        />
      ),
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Horas trabajadas por período"
      size="lg"
    >
      <Stack gap="md">
        <Text size="xs" c="dimmed">
          Cargue manualmente el total de horas trabajadas por período (formato AAAA para un año, o AAAA-MM para
          un mes). Deje la unidad en blanco para registrar el total institucional. Si carga los meses y consulta
          el año, los índices CD 513 suman los meses cargados; el total institucional manda sobre las unidades.
        </Text>
        {/* El fallo del catálogo va en un aviso y no en el campo: la fila es un
            `Group` alineado abajo y sin envolver, así que un error debajo del
            Select desalinea los otros dos campos. Además no es un error de lo
            que se escribió, sino del dato que hay detrás, y aquí hay algo que
            se puede hacer igual. */}
        {errorUnidades && (
          <Alert icon={<IconAlertTriangle size={16} />} color="amber" variant="light">
            No se pudieron cargar las unidades administrativas. Puede registrar el total
            institucional dejando la unidad en blanco.
          </Alert>
        )}

        {/* En rejilla y no en una fila sin envolver: los cuatro controles no
            caben en el ancho de este modal, y el que se llevaba el recorte era
            la unidad. En un teléfono cada campo baja a su propia fila en vez
            de estrujarse. */}
        <form onSubmit={handleSubmit(guardar)} noValidate>
          <Grid gap="sm">
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput
                label="Período"
                placeholder={EJEMPLO_PERIODO}
                {...contained}
                {...register('periodo')}
                error={errors.periodo?.message}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="total_horas"
                control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Total de horas"
                    min={1}
                    hideControls
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)}
                    error={errors.total_horas?.message}
                  />
                )}
              />
            </Grid.Col>
            {/* Fila entera: los nombres de unidad del GAD llegan a los 48
                caracteres y compartiendo fila se quedaban en la mitad. */}
            <Grid.Col span={12}>
              <Controller
                name="unidad_administrativa_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Unidad (opcional)"
                    placeholder="Total institucional"
                    data={unidadOptions}
                    searchable
                    clearable
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => field.onChange(v ? Number(v) : null)}
                    error={errors.unidad_administrativa_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={12}>
              <Group justify="flex-end">
                <Button
                  type="submit"
                  h={48}
                  leftSection={<IconPlus size={16} />}
                  loading={registrar.isPending}
                >
                  Guardar
                </Button>
              </Group>
            </Grid.Col>
          </Grid>
        </form>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudieron cargar las horas trabajadas"
          errorHint="No quiere decir que no haya períodos cargados: no se pudieron consultar."
          onRetry={() => refetch()}
          skeletonRows={3}
          empty={!registros.length}
          page={page}
          emptyProps={{
            icon: IconClock,
            title: 'Sin registros de horas trabajadas',
            description: 'Cargue el total de horas del período con el formulario de arriba: es el denominador de los índices CD 513.',
          }}
        >
          <SgthTable
            {...PAGINACION_ES}
            records={registros}
            columns={columns}
            totalRecords={data?.total ?? 0}
            recordsPerPage={15}
            page={page}
            onPageChange={setPage}
            minHeight={120}
          />
        </DataState>
      </Stack>
    </SgthModal>
  )
}
