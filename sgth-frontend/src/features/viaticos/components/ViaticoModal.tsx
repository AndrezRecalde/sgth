"use client";

import {
  Stack,
  Grid,
  Select,
  Textarea,
  Divider,
  Alert,
  Text,
  Card,
} from "@mantine/core";
import { FormModal } from "@/components/ui";
import { DateTimePicker } from "@mantine/dates";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  IconInfoCircle,
} from "@tabler/icons-react";
import { useAuth } from "@/hooks/useAuth";
import { useContainedInput } from "@/hooks/useContainedInput";
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

export function ViaticoModal({ opened, onClose, onCreated }: Props) {
  const contained = useContainedInput();
  const { solicitar } = useViaticoMutations();

  // El usuario con sesión ya trae el cargo y la unidad (`auth/perfil`). Antes
  // se pedía otra vez y se leía con otra forma: salía «Sin cargo asignado».
  const { usuario } = useAuth();
  const servidor = usuario?.servidor;

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
    },
  });

  const zonaWatch = useWatch({ control, name: "zona" });
  const salidaWatch = useWatch({ control, name: "datetime_salida" });
  const llegadaWatch = useWatch({ control, name: "datetime_llegada" });

  // Noches de pernocte: es lo que se paga. Antes contaba los días de
  // calendario, el de regreso incluido.
  const calcularNoches = (): string => {
    if (!salidaWatch || !llegadaWatch) return "—";
    const s = new Date(salidaWatch);
    const l = new Date(llegadaWatch);
    if (isNaN(s.getTime()) || isNaN(l.getTime())) return "—";
    const fs = new Date(s.getFullYear(), s.getMonth(), s.getDate());
    const fl = new Date(l.getFullYear(), l.getMonth(), l.getDate());
    const noches = Math.round((fl.getTime() - fs.getTime()) / 86400000);
    return noches < 1 ? "—" : noches + (noches === 1 ? " noche" : " noches");
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  const onSubmit = async (values: ViaticoFormData) => {
    try {
      const viatico = await solicitar.mutateAsync(values);
      reset();
      onClose();
      if (viatico) onCreated(viatico as Viatico);
    } catch {
      // El hook de mutación ya notifica el error.
    }
  };

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title="Nueva solicitud de viático"
      size="xl"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Crear solicitud"
      submitting={isSubmitting}
    >
      <Stack gap="md">
        {servidor && (
          <ViaticoServidorCard
            nombre={
              [servidor.nombre, servidor.apellido].filter(Boolean).join(" ") ||
              (usuario?.nombre_completo ?? "")
            }
            cargo={servidor.puesto?.nombre}
            unidad={servidor.unidad_administrativa?.nombre}
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
                Noches
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
                  c={calcularNoches() === "—" ? "dimmed" : "emerald"}
                >
                  {calcularNoches()}
                </Text>
              </Card>
            </div>
          </Grid.Col>
        </Grid>

        {zonaWatch === "exterior" && (
          <>
            <Alert
              icon={<IconInfoCircle size={14} />}
              color="amber"
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
              color="ocean"
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
      </Stack>
    </FormModal>
  );
}
