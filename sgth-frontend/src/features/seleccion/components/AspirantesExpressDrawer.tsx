'use client'

import { useState } from 'react'
import { Alert, Button, Group, Select, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconAlertTriangle, IconSettings, IconUsers } from '@tabler/icons-react'
import { confirmar, DataState, SgthDrawer, SgthModal, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import { useConfirmarIncorporacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { useAspirantesExpress, ASPIRANTES_POR_PAGINA } from '../hooks/useExpress'
import { useCriterios } from '../hooks/useCriterio'
import { useEnviarAlDispensario } from '../hooks/useConvocatoria'
import { ESTADO_POSTULANTE_OPTIONS } from '../services/convocatoriaService'
import type { AspiranteExpress, FiltroAnios, TarjetaExpress } from '../services/expressService'
import { columnasAspirantesExpress, nombreAspirante } from './aspirantesExpress.columns'
import { CalificarPostulanteModal } from './CalificarPostulanteModal'
import { OnboardingChecklist } from './perfil/OnboardingChecklist'
import { SeleccionarPlantillaModal } from './SeleccionarPlantillaModal'

interface Props {
  opened: boolean
  onClose: () => void
  contenedor: TarjetaExpress | null
  filtro: FiltroAnios
  estado: string | null
  onEstadoChange: (estado: string | null) => void
}

export function AspirantesExpressDrawer({
  opened, onClose, contenedor, filtro, estado, onEstadoChange,
}: Props) {
  const contained = useContainedInput('sm')
  const [aspiranteSel, setAspiranteSel] = useState<AspiranteExpress | null>(null)
  const [calAbierto, cal] = useDisclosure(false)
  const [plantillaAbierta, plantilla] = useDisclosure(false)
  const [enInduccion, setEnInduccion] = useState<AspiranteExpress | null>(null)
  const convocatoriaId = contenedor?.convocatoria_id ?? null

  // Paginado (2026-10-05): antes se veían los 20 primeros y del resto ni se
  // sabía que existía. Cambiar de modalidad, de años o de estado vuelve a la
  // página 1; se ajusta en el render para no pintar una página vacía.
  const clave = JSON.stringify([convocatoriaId, filtro, estado])
  const [pagina, setPagina] = useState({ clave, page: 1 })
  const page = pagina.clave === clave ? pagina.page : 1

  const { data, isLoading, error, refetch } = useAspirantesExpress(
    opened ? convocatoriaId : null,
    { ...filtro, ...(estado ? { estado } : {}), page },
  )
  const aspirantes = data?.data ?? []
  // El de la lista, que se refresca al guardar; no la copia del clic.
  const induccion = aspirantes.find((a) => a.id === enInduccion?.id) ?? enInduccion

  // El contenedor necesita criterios de evaluación antes de poder calificar a
  // nadie. Se comparten por modalidad, así que se configuran una sola vez.
  const { data: criterios = [] } = useCriterios(opened ? convocatoriaId : null)
  const tieneCriterios = criterios.length > 0

  const enviarAlDispensario = useEnviarAlDispensario(convocatoriaId ?? 0)
  const confirmarIncorporacion = useConfirmarIncorporacion()

  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-convocatorias')

  const columns = columnasAspirantesExpress({
    califica: hasPermiso('evaluar-postulantes'),
    gestiona,
    puedeIncorporar: hasPermiso('gestionar-onboarding'),
    tieneCriterios,
    onCalificar: (a) => { setAspiranteSel(a); cal.open() },
    onEnviar: (a) => confirmar({
      title: 'Enviar al Dispensario Médico',
      message: (
        <>
          Se solicitará la ficha ocupacional de <b>{nombreAspirante(a)}</b>. La
          incorporación se genera recién cuando el dispensario emita un dictamen
          de aptitud.
        </>
      ),
      confirmLabel: 'Enviar',
      onConfirm: () => enviarAlDispensario.mutate(a.id),
    }),
    onInduccion: setEnInduccion,
    onIncorporar: (a) => confirmar({
      title: 'Confirmar incorporación',
      message: (
        <>
          Se creará el expediente de servidor de <b>{nombreAspirante(a)}</b> con
          su acción de ingreso y su proceso de inducción. No se puede deshacer.
        </>
      ),
      confirmLabel: 'Incorporar',
      onConfirm: () => confirmarIncorporacion.mutate(a.solicitud_certificacion!.id),
    }),
  })

  return (
    <>
      <SgthDrawer opened={opened} onClose={onClose} title={contenedor?.titulo ?? 'Aspirantes'} ancho="lg">
        <Stack gap="md">
          {convocatoriaId && !tieneCriterios && (
            <Alert color="amber" variant="light" radius="lg" icon={<IconAlertTriangle size={18} />}
              title="Falta configurar la evaluación">
              <Text size="sm" mb="sm">
                Esta modalidad todavía no tiene criterios de evaluación, así que
                no se puede calificar a nadie. Aplique una plantilla una sola vez
                y servirá para todos sus aspirantes.
              </Text>
              {gestiona && (
                <Button variant="light" size="xs" leftSection={<IconSettings size={14} />} onClick={plantilla.open}>
                  Configurar criterios
                </Button>
              )}
            </Alert>
          )}

          <Group justify="space-between" align="flex-end">
            <Group gap="sm" align="flex-end">
              <Select
                label="Filtrar por estado"
                placeholder="Todos"
                data={ESTADO_POSTULANTE_OPTIONS}
                value={estado}
                onChange={onEstadoChange}
                clearable
                {...contained}
                style={{ minWidth: 220 }}
              />
              <Text size="sm" c="dimmed" pb={10}>
                {data?.total ?? 0} aspirante{data?.total === 1 ? '' : 's'}
              </Text>
            </Group>

            {gestiona && tieneCriterios && (
              <Button variant="subtle" size="xs" leftSection={<IconSettings size={14} />} onClick={plantilla.open}>
                Criterios de evaluación
              </Button>
            )}
          </Group>

          <DataState
            loading={isLoading}
            error={error}
            errorTitle="No se pudieron cargar los aspirantes"
            onRetry={refetch}
            empty={!aspirantes.length}
            emptyProps={{
              icon: IconUsers,
              title: 'Sin aspirantes',
              description: estado
                ? 'Ningún aspirante está en ese estado en los años elegidos.'
                : 'Esta modalidad no tiene aspirantes en los años elegidos.',
            }}
            page={page}
          >
            <SgthTable
              records={aspirantes}
              columns={columns}
              minHeight={200}
              totalRecords={data?.total ?? 0}
              recordsPerPage={ASPIRANTES_POR_PAGINA}
              page={page}
              onPageChange={(p) => setPagina({ clave, page: p })}
            />
          </DataState>
        </Stack>
      </SgthDrawer>

      {convocatoriaId && (
        <>
          <CalificarPostulanteModal opened={calAbierto} onClose={cal.close}
            postulante={aspiranteSel} convocatoriaId={convocatoriaId} />
          <SeleccionarPlantillaModal opened={plantillaAbierta} onClose={plantilla.close}
            convocatoriaId={convocatoriaId} tieneCriterios={tieneCriterios} />
          <SgthModal opened={induccion !== null} onClose={() => setEnInduccion(null)}
            title={induccion ? `Inducción — ${nombreAspirante(induccion)}` : 'Inducción'} size="md">
            {induccion?.onboarding && (
              <OnboardingChecklist convocatoriaId={convocatoriaId} onboarding={induccion.onboarding}
                editable={hasPermiso('gestionar-onboarding')} />
            )}
          </SgthModal>
        </>
      )}
    </>
  )
}
