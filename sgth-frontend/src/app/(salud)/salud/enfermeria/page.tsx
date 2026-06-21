"use client";

import { useState } from "react";
import {
  Stack,
  Card,
  Alert,
  Text,
  Tabs,
  Chip,
  Button,
  Group,
  Badge,
} from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { Container } from "@mantine/core";
import { FlujoStepper, type PasoStepper } from "@/features/dispensario/components/FlujoStepper";
import {
  IconNurse,
  IconCheck,
  IconList,
  IconUserPlus,
  IconVaccine,
} from "@tabler/icons-react";
import { useDisclosure } from "@mantine/hooks";
import { PageHeader } from "@/components/ui/PageHeader";
import { BuscarPacienteForm } from "@/features/dispensario/components/BuscarPacienteForm";
import {
  SeleccionarAccionPaciente,
  type AccionPaciente,
} from "@/features/dispensario/components/SeleccionarAccionPaciente";
import { CrearTurnoForm } from "@/features/dispensario/components/CrearTurnoForm";
import { AtencionEnfermeriaForm } from "@/features/dispensario/components/AtencionEnfermeriaForm";
import { OfrecerTriajeInmediato } from "@/features/dispensario/components/OfrecerTriajeInmediato";
import { TriajeForm } from "@/features/dispensario/components/TriajeForm";
import { AtencionesEnfermeriaDrawer } from "@/features/dispensario/components/AtencionesEnfermeriaDrawer";
import { ColaTurnosTable } from "@/features/dispensario/components/ColaTurnosTable";
import { TriajePendientesList } from "@/features/dispensario/components/TriajePendientesList";
import {
  useColaTurnos,
  useCancelarTurno,
} from "@/features/dispensario/hooks/useAgenda";
import { useTriajesPendientes } from "@/features/dispensario/hooks/useTriaje";
import type { PacienteEncontrado } from "@/features/dispensario/services/pacienteService";
import type { AgendaMedica } from "@/features/dispensario/services/agendaService";
import type { AtencionEnfermeria } from "@/features/dispensario/services/atencionEnfermeriaService";
import type { Triaje } from "@/features/dispensario/services/triajeService";

type Paso =
  | "buscar"
  | "elegir_accion"
  | "crear_turno"
  | "servicio_enfermeria"
  | "ofrecer_triaje"
  | "tomar_triaje"
  | "finalizado";

function formatFechaLocal(d: Date): string {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function mapearPasoVisual(paso: Paso): PasoStepper {
  if (paso === "buscar") return "buscar";
  if (paso === "elegir_accion") return "elegir_accion";
  if (paso === "finalizado") return "confirmacion";
  return "completar_datos";
}

function AtenderPaciente() {
  const [paso, setPaso] = useState<Paso>("buscar");
  const [paciente, setPaciente] = useState<PacienteEncontrado | null>(null);
  const [agendaCreada, setAgendaCreada] = useState<AgendaMedica | null>(null);
  const [mensajeFinal, setMensajeFinal] = useState("");

  const handleReiniciar = () => {
    setPaso("buscar");
    setPaciente(null);
    setAgendaCreada(null);
    setMensajeFinal("");
  };

  const handleElegirAccion = (accion: AccionPaciente) => {
    setPaso(accion === "turno" ? "crear_turno" : "servicio_enfermeria");
  };

  const handleTurnoCreado = (agenda: AgendaMedica) => {
    setAgendaCreada(agenda);
    setPaso("ofrecer_triaje");
  };

  const handleServicioCreado = (atencion: AtencionEnfermeria) => {
    setMensajeFinal(`Atención ${atencion.folio} registrada`);
    setPaso("finalizado");
  };

  const handleTriajeCreado = (_triaje: Triaje) => {
    setMensajeFinal(`Turno ${agendaCreada?.folio} — triaje registrado`);
    setPaso("finalizado");
  };

  return (
    <Container size="sm" px={0}>
      <FlujoStepper pasoActual={mapearPasoVisual(paso)} />

      {paso === "buscar" && (
        <BuscarPacienteForm
          onPacienteListo={(p) => {
            setPaciente(p);
            setPaso("elegir_accion");
          }}
        />
      )}

      {paso === "elegir_accion" && paciente && (
        <SeleccionarAccionPaciente
          paciente={paciente}
          onElegir={handleElegirAccion}
          onVolver={handleReiniciar}
        />
      )}

      {paso === "crear_turno" && paciente && (
        <CrearTurnoForm
          paciente={paciente}
          onCreado={handleTurnoCreado}
          onCancelar={() => setPaso("elegir_accion")}
        />
      )}

      {paso === "servicio_enfermeria" && paciente && (
        <AtencionEnfermeriaForm
          paciente={paciente}
          onCreado={handleServicioCreado}
          onCancelar={() => setPaso("elegir_accion")}
        />
      )}

      {paso === "ofrecer_triaje" && agendaCreada && (
        <OfrecerTriajeInmediato
          agenda={agendaCreada}
          onTomarTriaje={() => setPaso("tomar_triaje")}
          onMasTarde={() => {
            setMensajeFinal(`Turno ${agendaCreada.folio} en cola de espera`);
            setPaso("finalizado");
          }}
        />
      )}

      {paso === "tomar_triaje" && agendaCreada && (
        <TriajeForm
          turno={agendaCreada}
          onCreado={handleTriajeCreado}
          onCancelar={() => {
            setMensajeFinal(`Turno ${agendaCreada.folio} en cola de espera`);
            setPaso("finalizado");
          }}
        />
      )}

      {paso === "finalizado" && (
        <Card withBorder radius="lg" p="lg">
          <Stack gap="md" align="center">
            <Alert
              icon={<IconCheck size={16} />}
              color="emerald"
              variant="light"
              w="100%"
            >
              <Text size="sm" fw={600} ta="center">
                {mensajeFinal}
              </Text>
            </Alert>
            <Card.Section
              p="sm"
              onClick={handleReiniciar}
              style={{ cursor: "pointer", textAlign: "center" }}
            >
              <Text size="sm" c="emerald" fw={600}>
                Atender otro paciente
              </Text>
            </Card.Section>
          </Stack>
        </Card>
      )}
    </Container>
  );
}

type VistaMonitoreo = "todos" | "pendientes_triaje";

function ColaYMonitoreo() {
  const [fecha, setFecha] = useState<Date | null>(new Date());
  const [vista, setVista] = useState<VistaMonitoreo>("todos");
  const [turnoTriaje, setTurnoTriaje] = useState<AgendaMedica | null>(null);
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false);

  const fechaStr = formatFechaLocal(fecha ?? new Date());

  const { data, isLoading } = useColaTurnos({ fecha: fechaStr });
  const { data: pendientesTriaje = [] } = useTriajesPendientes();
  const cancelar = useCancelarTurno();

  const turnos = data?.data ?? [];

  // Si se eligió tomar el triaje de un turno
  // desde la lista de pendientes
  if (turnoTriaje) {
    return (
      <Stack gap="md" maw={620}>
        <TriajeForm
          turno={turnoTriaje}
          onCreado={() => setTurnoTriaje(null)}
          onCancelar={() => setTurnoTriaje(null)}
        />
      </Stack>
    );
  }

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="wrap">
        <Group gap="xs">
          <Chip
            checked={vista === "todos"}
            onChange={() => setVista("todos")}
            color="blue"
            size="sm"
          >
            Todos los turnos
          </Chip>
          <Chip
            checked={vista === "pendientes_triaje"}
            onChange={() => setVista("pendientes_triaje")}
            color="orange"
            size="sm"
          >
            Pendientes de triaje
            {pendientesTriaje.length > 0 ? ` (${pendientesTriaje.length})` : ""}
          </Chip>
          <Button
            size="xs"
            variant="light"
            color="violet"
            leftSection={<IconVaccine size={14} />}
            onClick={abrirDrawer}
          >
            Servicios de enfermería
          </Button>
        </Group>

        {vista === "todos" && (
          <DatePickerInput
            label="Fecha"
            value={fecha}
            onChange={(v) => {
              if (!v) {
                setFecha(new Date());
                return;
              }
              const str = typeof v === "string" ? v : String(v);
              const [y, m, d] = str.slice(0, 10).split("-").map(Number);
              setFecha(new Date(y, m - 1, d));
            }}
            valueFormat="DD/MM/YYYY"
            maw={200}
          />
        )}
      </Group>

      {vista === "todos" && (
        <ColaTurnosTable
          turnos={turnos}
          isLoading={isLoading}
          onCancelar={(id) => {
            if (confirm("¿Cancelar este turno?")) {
              cancelar.mutate(id);
            }
          }}
          onTomarTriaje={(turno) => setTurnoTriaje(turno)}
        />
      )}

      {vista === "pendientes_triaje" && (
        <TriajePendientesList
          onSeleccionar={(turno) => setTurnoTriaje(turno)}
        />
      )}

      <AtencionesEnfermeriaDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
      />
    </Stack>
  );
}

export default function EnfermeriaPage() {
  const { data: pendientesTriaje = [] } = useTriajesPendientes();

  return (
    <Stack gap="md">
      <PageHeader
        title="Enfermería"
        subtitle="Admisión, triaje y servicios de enfermería"
        icon={<IconNurse size={24} />}
      />

      <Tabs defaultValue="atender">
        <Tabs.List>
          <Tabs.Tab value="atender" leftSection={<IconUserPlus size={14} />}>
            Atender paciente
          </Tabs.Tab>
          <Tabs.Tab
            value="monitoreo"
            leftSection={<IconList size={14} />}
            rightSection={
              pendientesTriaje.length > 0 ? (
                <Badge size="xs" color="orange" circle>
                  {pendientesTriaje.length}
                </Badge>
              ) : null
            }
          >
            Cola y monitoreo
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="atender" pt="md">
          <AtenderPaciente />
        </Tabs.Panel>

        <Tabs.Panel value="monitoreo" pt="md">
          <ColaYMonitoreo />
        </Tabs.Panel>
      </Tabs>
    </Stack>
  );
}
