"use client";

import {
  Modal,
  Stack,
  Grid,
  Select,
  Textarea,
  Button,
  Group,
  NumberInput,
  Divider,
  Alert,
  Text,
  Card,
  Badge,
  ThemeIcon,
  MultiSelect,
} from "@mantine/core";
import { DateTimePicker } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  IconInfoCircle,
  IconUser,
  IconArrowRight,
  IconCalendar,
  IconUsers,
} from "@tabler/icons-react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { viaticoSchema, type ViaticoFormData } from "../schemas/viatico.schema";
import type { Viatico } from "@/types/api";
import { useQuery } from "@tanstack/react-query";
import api from "@/lib/axios";

const ZONA_OPTIONS = [
  { value: "dentro_provincia", label: "📍 Dentro de la provincia" },
  { value: "fuera_provincia", label: "🗺 Fuera de la provincia" },
  { value: "exterior", label: "✈️ Exterior (internacional)" },
];

const MODALIDAD_OPTIONS = [
  { value: "total", label: "💰 Anticipo (70% del monto calculado)" },
  { value: "sin_anticipo", label: "🚫 Sin anticipo" },
];

const TIPO_VIAJE_OPTIONS = [
  { value: "capacitacion", label: "Capacitación" },
  { value: "reunion_oficial", label: "Reunión oficial" },
  { value: "taller_foro_seminario", label: "Taller / Foro / Seminario" },
  { value: "feria_evento_especial", label: "Feria o evento especial" },
  { value: "visita_protocolar", label: "Visita protocolar" },
  { value: "firma_acuerdo", label: "Firma de acuerdo" },
  { value: "visita_tecnica", label: "Visita técnica" },
  { value: "cooperacion_internacional", label: "Cooperación internacional" },
  { value: "asistencia_humanitaria", label: "Asistencia humanitaria" },
];

const PAISES = [
  "Colombia",
  "Perú",
  "Bolivia",
  "Chile",
  "Argentina",
  "Brasil",
  "Venezuela",
  "México",
  "España",
  "Estados Unidos",
  "Canadá",
  "Francia",
  "Alemania",
  "Italia",
  "China",
  "Japón",
  "Otro",
];

const fromDateTime = (d: Date | null | string): string => {
  if (!d) return "";
  const dt = typeof d === "string" ? new Date(d) : d;
  if (isNaN(dt.getTime())) return "";

  const year = dt.getFullYear();
  const month = String(dt.getMonth() + 1).padStart(2, "0");
  const day = String(dt.getDate()).padStart(2, "0");
  const hours = String(dt.getHours()).padStart(2, "0");
  const minutes = String(dt.getMinutes()).padStart(2, "0");

  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

interface Props {
  opened: boolean;
  onClose: () => void;
  onCreated: (viatico: Viatico) => void;
}

export function ViaticoModal({ opened, onClose, onCreated }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { solicitar } = useViaticoMutations();

  // Cargar datos del servidor autenticado
  const { data: miPerfil } = useQuery({
    queryKey: ["mi-perfil"],
    queryFn: () =>
      api
        .get<{
          datos: {
            id: number;
            name: string;
            servidor?: {
              id: number;
              nombre?: string;
              segundo_nombre?: string | null;
              apellido?: string;
              segundo_apellido?: string | null;
              puesto?: {
                cargo?: { nombre?: string } | null;
                unidad_administrativa?: { nombre?: string } | null;
              } | null;
            } | null;
          };
        }>("/auth/me")
        .then((r) => r.data.datos),
    enabled: opened,
    staleTime: 1000 * 60 * 5,
  });

  const servidor = miPerfil?.servidor;
  const nombreCompleto = [
    servidor?.nombre,
    servidor?.segundo_nombre,
    servidor?.apellido,
    servidor?.segundo_apellido,
  ]
    .filter(Boolean)
    .join(" ");

  // Cargar servidores para acompañantes
  const { data: servidoresData } = useServidores({
    per_page: 200,
  });

  const servidoresOptions = (servidoresData?.data ?? [])
    .filter((s) => {
      // Excluir al servidor autenticado (titular)
      const miId = miPerfil?.servidor?.id;
      return miId ? s.id !== miId : true;
    })
    .map((s) => ({
      value: String(s.id),
      label: [s.apellido, s.nombre].filter(Boolean).join(" "),
    }));

  const {
    control,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ViaticoFormData>({
    resolver: zodResolver(viaticoSchema),
    defaultValues: {
      zona: "fuera_provincia",
      datetime_salida: "",
      datetime_llegada: "",
      tipo_viaje: null,
      pais_destino: null,
      justificacion: "",
      modalidad_anticipo: "total",
      monto_calculado: null,
      servidores_acompanantes: [],
    },
  });

  const zonaWatch = useWatch({ control, name: "zona" });
  const salidaWatch = useWatch({ control, name: "datetime_salida" });
  const llegadaWatch = useWatch({ control, name: "datetime_llegada" });

  // Calcular días en tiempo real
  const calcularDias = (): string => {
    if (!salidaWatch || !llegadaWatch) return "—";
    const s = new Date(salidaWatch);
    const l = new Date(llegadaWatch);
    if (isNaN(s.getTime()) || isNaN(l.getTime())) return "—";
    // Opción B: días calendario sin considerar horas
    // Solo la fecha (sin hora) y +1 porque el día
    // de regreso cuenta como día completo
    const soloFechaSalida = new Date(
      s.getFullYear(),
      s.getMonth(),
      s.getDate(),
    );
    const soloFechaLlegada = new Date(
      l.getFullYear(),
      l.getMonth(),
      l.getDate(),
    );
    const diffMs = soloFechaLlegada.getTime() - soloFechaSalida.getTime();
    const diffDias = Math.round(diffMs / 86400000);
    if (diffDias < 0) return "—";
    return diffDias + 1 + " días";
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  const onSubmit = async (values: ViaticoFormData) => {
    const viatico = await solicitar.mutateAsync(values);
    reset();
    onClose();
    if (viatico) onCreated(viatico as Viatico);
  };

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="sm">
            <IconCalendar size={14} />
          </ThemeIcon>
          <Text fw={600} size="md">
            Nueva solicitud de viático
          </Text>
        </Group>
      }
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="md">
          {/* ── Información del servidor ── */}
          {servidor && (
            <Card withBorder radius="md" p="sm" bg="blue.0">
              <Group gap="sm">
                <ThemeIcon color="blue" variant="light" size="lg" radius="xl">
                  <IconUser size={18} />
                </ThemeIcon>
                <div>
                  <Text fw={600} size="sm">
                    {nombreCompleto || miPerfil?.name}
                  </Text>
                  <Text size="xs" c="dimmed">
                    {servidor.puesto?.cargo?.nombre ?? "Sin cargo asignado"}
                  </Text>
                  <Text size="xs" c="dimmed">
                    {servidor.puesto?.unidad_administrativa?.nombre ?? ""}
                  </Text>
                </div>
                <Badge size="xs" color="blue" variant="light" ml="auto">
                  Solicitante
                </Badge>
              </Group>
            </Card>
          )}

          {/* ── Datos del viaje ── */}
          <Divider label="¿A dónde y cuándo viaja?" labelPosition="left" />

          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="zona"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Zona geográfica del viaje"
                    description="¿Viaja dentro o fuera de la provincia?"
                    data={ZONA_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v ?? "fuera_provincia")}
                    error={errors.zona?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="modalidad_anticipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="¿Necesita anticipo de dinero?"
                    description="El anticipo se entregará antes del viaje"
                    data={MODALIDAD_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v ?? "total")}
                    error={errors.modalidad_anticipo?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          {/* Fechas y horas */}
          <Grid>
            <Grid.Col span={{ base: 12, sm: 5 }}>
              <Controller
                name="datetime_salida"
                control={control}
                render={({ field }) => (
                  <DateTimePicker
                    label="Fecha y hora de salida"
                    description="¿Cuándo sale de Esmeraldas?"
                    placeholder="Seleccionar"
                    valueFormat="DD/MM/YYYY HH:mm"
                    timePickerProps={{
                      withDropdown: true,
                      popoverProps: { withinPortal: false },
                      format: "24h",
                    }}
                    {...contained}
                    value={field.value ? new Date(field.value) : null}
                    onChange={(v) => field.onChange(fromDateTime(v))}
                    error={errors.datetime_salida?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 5 }}>
              <Controller
                name="datetime_llegada"
                control={control}
                render={({ field }) => (
                  <DateTimePicker
                    label="Fecha y hora de regreso"
                    description="¿Cuándo regresa a Esmeraldas?"
                    placeholder="Seleccionar"
                    valueFormat="DD/MM/YYYY HH:mm"
                    timePickerProps={{
                      withDropdown: true,
                      popoverProps: { withinPortal: false },
                      format: "24h",
                    }}
                    {...contained}
                    value={field.value ? new Date(field.value) : null}
                    onChange={(v) => field.onChange(fromDateTime(v))}
                    error={errors.datetime_llegada?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 2 }}>
              <div>
                <Text size="xs" c="dimmed" mb={4}>
                  Total días
                </Text>
                <Card
                  withBorder
                  p="xs"
                  radius="md"
                  style={{ textAlign: "center" }}
                >
                  <Text
                    fw={700}
                    size="lg"
                    c={calcularDias() === "—" ? "dimmed" : "emerald"}
                  >
                    {calcularDias()}
                  </Text>
                </Card>
              </div>
            </Grid.Col>
          </Grid>

          {/* Exterior */}
          {zonaWatch === "exterior" && (
            <>
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="orange"
                variant="light"
              >
                <Text size="xs" fw={500}>
                  Viaje al exterior (internacional)
                </Text>
                <Text size="xs" mt={2}>
                  El monto se calculará automáticamente cuando el gestor
                  apruebe la solicitud, aplicando la tarifa según su nivel
                  y el coeficiente del país de destino.
                </Text>
              </Alert>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller
                    name="tipo_viaje"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Motivo del viaje al exterior"
                        data={TIPO_VIAJE_OPTIONS}
                        searchable
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? null)}
                        error={errors.tipo_viaje?.message}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller
                    name="pais_destino"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="País de destino"
                        data={PAISES}
                        searchable
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? null)}
                        error={errors.pais_destino?.message}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Stack gap={4} justify="flex-end" h="100%">
                    <Text size="xs" fw={500} c="dimmed">
                      Monto a cobrar
                    </Text>
                    <Alert
                      color="blue"
                      variant="light"
                      p="xs"
                      icon={<IconInfoCircle size={12} />}
                    >
                      <Text size="xs">
                        El gestor calculará el monto al
                        aprobar la solicitud según la
                        tarifa del país y el coeficiente.
                      </Text>
                    </Alert>
                  </Stack>
                </Grid.Col>
              </Grid>
            </>
          )}

          {/* Servidores acompañantes */}
          <Divider
            label="¿Viajan más servidores en esta comisión?"
            labelPosition="left"
          />

          {/* Servidor titular — solo informativo */}
          {servidor && (
            <Card withBorder radius="md" p="xs" bg="emerald.0">
              <Group gap="xs">
                <ThemeIcon
                  color="emerald"
                  variant="light"
                  size="sm"
                  radius="xl"
                >
                  <IconUsers size={12} />
                </ThemeIcon>
                <div>
                  <Text size="xs" fw={600}>
                    {nombreCompleto || miPerfil?.name}
                  </Text>
                  <Text size="xs" c="dimmed">
                    Servidor titular — se agrega automáticamente
                  </Text>
                </div>
              </Group>
            </Card>
          )}

          <Controller
            name="servidores_acompanantes"
            control={control}
            render={({ field }) => (
              <MultiSelect
                label="Servidores acompañantes (opcional)"
                description="Seleccione los servidores que también
                  participan en esta comisión"
                placeholder="Buscar servidor..."
                data={servidoresOptions}
                searchable
                clearable
                {...contained}
                value={(field.value ?? []).map(String)}
                onChange={(v) => field.onChange(v.map(Number))}
              />
            )}
          />

          {/* Justificación */}
          <Divider label="¿Por qué realiza este viaje?" labelPosition="left" />

          <Controller
            name="justificacion"
            control={control}
            render={({ field }) => (
              <Textarea
                label="Justificación del viaje"
                description="Explique el objetivo de la comisión (mínimo 10 caracteres)"
                placeholder="Ej: Participación en el taller de capacitación sobre contratación pública organizado por el SERCOP en la ciudad de Quito, del 10 al 12 de junio de 2026..."
                autosize
                minRows={4}
                maxRows={6}
                {...contained}
                value={field.value}
                onChange={(e) => field.onChange(e.currentTarget.value)}
                error={errors.justificacion?.message}
              />
            )}
          />

          <Group justify="flex-end" mt="xs">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              loading={isSubmitting}
              rightSection={<IconArrowRight size={14} />}
            >
              Crear solicitud
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
