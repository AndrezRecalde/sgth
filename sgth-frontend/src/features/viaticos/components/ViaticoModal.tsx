"use client";

import {
  Modal,
  Stack,
  Grid,
  Select,
  Textarea,
  Button,
  Group,
  Divider,
  Alert,
  Text,
  Card,
  ThemeIcon,
  MultiSelect,
} from "@mantine/core";
import { DateTimePicker } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  IconInfoCircle,
  IconArrowRight,
  IconCalendar,
  IconUsers,
} from "@tabler/icons-react";
import { useQuery } from "@tanstack/react-query";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { ViaticoServidorCard } from "./ViaticoServidorCard";
import {
  ZONA_OPTIONS,
  MODALIDAD_OPTIONS,
  TIPO_VIAJE_OPTIONS,
  PAISES_OPTIONS,
} from "../constants/viatico.constants";
import { viaticoSchema, type ViaticoFormData } from "../schemas/viatico.schema";
import type { Viatico } from "@/types/api";
import api from "@/lib/axios";

function fromDateTime(d: Date | null | string): string {
  if (!d) return "";
  const dt = typeof d === "string" ? new Date(d) : d;
  if (isNaN(dt.getTime())) return "";
  return (
    [
      dt.getFullYear(),
      String(dt.getMonth() + 1).padStart(2, "0"),
      String(dt.getDate()).padStart(2, "0"),
    ].join("-") +
    "T" +
    [
      String(dt.getHours()).padStart(2, "0"),
      String(dt.getMinutes()).padStart(2, "0"),
    ].join(":")
  );
}

interface Props {
  opened: boolean;
  onClose: () => void;
  onCreated: (viatico: Viatico) => void;
}

type MiPerfil = {
  id: number;
  name: string;
  servidor?: {
    id: number;
    nombre?: string | null;
    segundo_nombre?: string | null;
    apellido?: string | null;
    segundo_apellido?: string | null;
    puesto?: {
      cargo?: { nombre?: string } | null;
      unidad_administrativa?: { nombre?: string } | null;
    } | null;
  } | null;
};

export function ViaticoModal({ opened, onClose, onCreated }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { solicitar } = useViaticoMutations();

  const { data: miPerfil } = useQuery({
    queryKey: ["mi-perfil"],
    queryFn: () =>
      api.get<{ datos: MiPerfil }>("/auth/me").then((r) => r.data.datos),
    enabled: opened,
    staleTime: 1000 * 60 * 5,
  });

  const servidor = miPerfil?.servidor;

  const { data: servidoresData } = useServidores({ per_page: 200 });

  const servidoresOptions = (servidoresData?.data ?? [])
    .filter((s) => {
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

  const calcularDias = (): string => {
    if (!salidaWatch || !llegadaWatch) return "—";
    const s = new Date(salidaWatch);
    const l = new Date(llegadaWatch);
    if (isNaN(s.getTime()) || isNaN(l.getTime())) return "—";
    const fs = new Date(s.getFullYear(), s.getMonth(), s.getDate());
    const fl = new Date(l.getFullYear(), l.getMonth(), l.getDate());
    const diff = Math.round((fl.getTime() - fs.getTime()) / 86400000);
    return diff < 0 ? "—" : diff + 1 + " días";
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
          {servidor && (
            <ViaticoServidorCard
              servidor={servidor}
              nombreDisplay={miPerfil?.name}
            />
          )}

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
                  El monto se calculará cuando el gestor apruebe la solicitud,
                  aplicando la tarifa y coeficiente del país de destino.
                </Text>
              </Alert>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 6 }}>
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
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name="pais_destino"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="País de destino"
                        data={PAISES_OPTIONS}
                        searchable
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? null)}
                        error={errors.pais_destino?.message}
                      />
                    )}
                  />
                </Grid.Col>
              </Grid>
              <Alert
                color="blue"
                variant="light"
                p="xs"
                icon={<IconInfoCircle size={12} />}
              >
                <Text size="xs">
                  El gestor calculará el monto al aprobar según la tarifa del
                  país y el coeficiente.
                </Text>
              </Alert>
            </>
          )}

          <Divider
            label="¿Viajan más servidores en esta comisión?"
            labelPosition="left"
          />

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
                    {[servidor.nombre, servidor.apellido]
                      .filter(Boolean)
                      .join(" ") || miPerfil?.name}
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

          <Divider label="¿Por qué realiza este viaje?" labelPosition="left" />

          <Controller
            name="justificacion"
            control={control}
            render={({ field }) => (
              <Textarea
                label="Justificación del viaje"
                description="Explique el objetivo de la comisión
                  (mínimo 10 caracteres)"
                placeholder="Ej: Participación en el taller de
                  capacitación sobre contratación pública..."
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
