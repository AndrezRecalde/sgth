'use client'

import { Button, Card, Group, Stack, Text, Tooltip } from '@mantine/core'
import { IconEdit } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { CorreccionesConsulta } from './CorreccionesConsulta'
import { motivoParaNoEditar } from '../utils/consultaMedica'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import classes from './ConsultaGuardada.module.css'

interface Props {
  consulta:  ConsultaMedica
  usuarioId?: number
  onEditar:  () => void
}

function SeccionVista({
  label,
  valor,
}: {
  label: string
  valor?: string | null
}) {
  if (!valor) return null

  // El HTML llega saneado del servidor: se limpia al guardar con lista blanca
  // de las etiquetas que produce el editor, y lo que ya estaba guardado se
  // saneó en una migración. Ver App\Support\HtmlClinico.
  const esHtml = valor.startsWith('<')

  return (
    <Stack gap={4} py="sm" className={classes.seccion}>
      <Text size="xs" fw={500} c="dimmed" tt="uppercase" className={classes.etiqueta}>
        {label}
      </Text>
      {esHtml ? (
        <div
          className={classes.contenido}
          dangerouslySetInnerHTML={{ __html: valor }}
        />
      ) : (
        <Text size="sm" className={classes.contenido}>
          {valor}
        </Text>
      )}
    </Stack>
  )
}

/**
 * La consulta tal como quedó registrada. Se lee; para cambiarla hay que entrar
 * en modo edición, y eso solo lo permite `motivoParaNoEditar`.
 */
export function ConsultaGuardada({ consulta, usuarioId, onEditar }: Props) {
  const noEditable = motivoParaNoEditar(consulta, usuarioId)

  return (
    <Card withBorder radius="lg" p={0}>
      <Group
        justify="space-between"
        align="center"
        px="md"
        py="sm"
        className={classes.cabecera}
      >
        <Group gap="xs" wrap="wrap">
          <StatusBadge tone="success">Guardada</StatusBadge>
          <StatusBadge>
            {consulta.tipo_atencion?.replace(/_/g, ' ')}
          </StatusBadge>
          <StatusBadge
            tone={consulta.tipo_diagnostico === 'definitivo' ? 'success' : 'warning'}
          >
            {consulta.tipo_diagnostico}
          </StatusBadge>
        </Group>

        <Tooltip
          label={noEditable ?? 'Editar consulta'}
          withArrow
          multiline
          w={noEditable ? 240 : undefined}
        >
          {/* El `span` sostiene el tooltip cuando el botón está deshabilitado:
              un botón inerte no emite los eventos que lo abren, y sin el
              motivo a la vista el bloqueo parecería un fallo. */}
          <span>
            <Button
              size="compact-xs"
              variant="subtle"
              leftSection={<IconEdit size={13} />}
              disabled={!!noEditable}
              onClick={onEditar}
            >
              Editar
            </Button>
          </span>
        </Tooltip>
      </Group>

      <CorreccionesConsulta consultaId={consulta.id} />

      <Stack gap={0} px="md" pb="md">
        <SeccionVista
          label="Motivo de consulta"
          valor={consulta.motivo_consulta}
        />
        <SeccionVista
          label="Enfermedad actual / Anamnesis"
          valor={consulta.enfermedad_actual}
        />
        <SeccionVista
          label="Examen físico"
          valor={consulta.examen_fisico}
        />

        {consulta.diagnostico_cie10_principal && (
          <Stack gap={8} py="sm" px="sm" my="xs" className={classes.diagnostico}>
            <Text
              size="xs"
              fw={500}
              c="ocean"
              tt="uppercase"
              className={classes.etiqueta}
            >
              Diagnóstico
            </Text>
            <Group gap="xs" align="flex-start">
              <StatusBadge size="md">
                {consulta.diagnostico_cie10_principal.codigo}
              </StatusBadge>
              <Text size="sm" fw={500} flex={1}>
                {consulta.diagnostico_cie10_principal.descripcion}
              </Text>
            </Group>

            {(consulta.diagnosticos_secundarios?.length ?? 0) > 0 && (
              <Stack gap={4}>
                {consulta.diagnosticos_secundarios?.map((ds) => (
                  <Group key={ds.id} gap="xs" align="flex-start">
                    <StatusBadge>{ds.diagnostico?.codigo}</StatusBadge>
                    <Text size="xs" c="dimmed" flex={1}>
                      {ds.diagnostico?.descripcion}
                    </Text>
                  </Group>
                ))}
              </Stack>
            )}
          </Stack>
        )}

        <SeccionVista
          label="Diagnóstico detallado"
          valor={consulta.diagnostico_detallado}
        />
        <SeccionVista
          label="Plan de tratamiento"
          valor={consulta.plan_tratamiento}
        />
        <SeccionVista
          label="Notas del médico"
          valor={consulta.notas_medico}
        />
      </Stack>
    </Card>
  )
}
