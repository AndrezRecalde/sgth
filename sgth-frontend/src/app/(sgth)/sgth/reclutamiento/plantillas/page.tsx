'use client'

import {
  Stack, Group, Badge, Text, Button,
  Card, ActionIcon, Modal,
  TextInput, Textarea, Select,
} from '@mantine/core'
import {
  IconTemplate, IconPlus,
  IconEdit, IconTrash,
  IconCheck,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import { useForm, useWatch } from 'react-hook-form'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  usePlantillas,
  useCrearPlantilla,
  useEliminarPlantilla,
} from '@/features/seleccion/hooks/usePlantilla'
import {
  TIPO_CONTRATO_PLANTILLA_OPTIONS,
} from '@/features/seleccion/services/plantillaService'

export default function PlantillasPage() {
  const router   = useRouter()
  const contained = useContainedInput()
  const [modalOpened,
    { open, close }] = useDisclosure(false)

  const { data: plantillas = [], isLoading } = usePlantillas()
  const crear    = useCrearPlantilla()
  const eliminar = useEliminarPlantilla()

  const { register, handleSubmit, reset, setValue, control } =
    useForm<{
      nombre:        string
      descripcion:   string
      tipo_contrato: string
    }>()

  const tipoContrato = useWatch({ control, name: 'tipo_contrato' })

  const onSubmit = (values: {
    nombre: string
    descripcion: string
    tipo_contrato: string
  }) => {
    crear.mutate(
      {
        nombre:        values.nombre,
        descripcion:   values.descripcion || null,
        tipo_contrato: values.tipo_contrato || null,
      },
      {
        onSuccess: (p) => {
          reset()
          close()
          router.push(
            `/sgth/reclutamiento/plantillas/${p.id}`
          )
        },
      }
    )
  }

  const getLabelTipo = (tipo: string | null | undefined) =>
    TIPO_CONTRATO_PLANTILLA_OPTIONS.find(
      o => o.value === tipo
    )?.label ?? 'General'

  return (
    <Stack gap="md">
      <PageHeader
        title="Plantillas de evaluación"
        subtitle="Configuración de criterios reutilizables para convocatorias"
        icon={<IconTemplate size={24} />}
        actions={
          <Button
            color="emerald"
            leftSection={<IconPlus size={14} />}
            onClick={open}
          >
            Nueva plantilla
          </Button>
        }
      />

      {plantillas.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconTemplate}
          title="Sin plantillas"
          description="Crea plantillas de criterios reutilizables para tus convocatorias."
        />
      ) : (
        <Stack gap="sm">
          {plantillas.map(p => (
            <Card key={p.id} withBorder radius="lg" p="md">
              <Group justify="space-between" wrap="nowrap">
                <Stack gap={4}>
                  <Group gap="xs">
                    <Text fw={600}>{p.nombre}</Text>
                    {!p.activa && (
                      <Badge size="xs" color="gray" variant="light">
                        Inactiva
                      </Badge>
                    )}
                  </Group>
                  {p.descripcion && (
                    <Text size="xs" c="dimmed" lineClamp={2}>
                      {p.descripcion}
                    </Text>
                  )}
                  <Group gap="xs" mt={2}>
                    <Badge size="xs" variant="light" color="blue">
                      {getLabelTipo(p.tipo_contrato)}
                    </Badge>
                    <Badge size="xs" variant="light" color="gray">
                      {p.criterios_count ?? 0} criterios
                    </Badge>
                  </Group>
                </Stack>
                <Group gap="xs" wrap="nowrap">
                  <ActionIcon
                    variant="light"
                    color="blue"
                    onClick={() =>
                      router.push(
                        `/sgth/reclutamiento/plantillas/${p.id}`
                      )
                    }
                  >
                    <IconEdit size={16} />
                  </ActionIcon>
                  <ActionIcon
                    variant="light"
                    color="red"
                    onClick={() => {
                      if (confirm(
                        `¿Eliminar la plantilla "${p.nombre}"?`
                      )) {
                        eliminar.mutate(p.id)
                      }
                    }}
                  >
                    <IconTrash size={16} />
                  </ActionIcon>
                </Group>
              </Group>
            </Card>
          ))}
        </Stack>
      )}

      <Modal
        opened={modalOpened}
        onClose={() => { reset(); close() }}
        title="Nueva plantilla de evaluación"
        size="md"
        radius="xl"
      >
        <form noValidate onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="sm">
            <TextInput
              label="Nombre de la plantilla"
              placeholder="Ej: Concurso LOSEP estándar"
              required
              {...contained}
              {...register('nombre')}
            />
            <Textarea
              label="Descripción"
              placeholder="Describe cuándo usar esta plantilla"
              autosize
              minRows={2}
              {...contained}
              {...register('descripcion')}
            />
            <Select
              label="Tipo de contrato"
              description="Ayuda a filtrar la plantilla según el tipo de convocatoria"
              data={TIPO_CONTRATO_PLANTILLA_OPTIONS}
              clearable
              {...contained}
              value={tipoContrato ?? null}
              onChange={(v) =>
                setValue('tipo_contrato', v ?? '')
              }
            />
            <Group justify="flex-end" mt="sm">
              <Button
                variant="default"
                onClick={() => { reset(); close() }}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                color="emerald"
                leftSection={<IconCheck size={14} />}
                loading={crear.isPending}
              >
                Crear plantilla
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>
    </Stack>
  )
}
