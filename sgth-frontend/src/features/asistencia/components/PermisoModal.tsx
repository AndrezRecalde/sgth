"use client";

import { useState } from "react";
import {
  Modal,
  Button,
  Group,
  Stack,
  Select,
  Textarea,
  Grid,
  Text,
  Stepper,
  Alert,
  Divider,
} from "@mantine/core";
import { DatePickerInput, TimeInput } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { notifications } from "@mantine/notifications";
import {
  IconCheck,
  IconInfoCircle,
  IconFileDownload,
} from "@tabler/icons-react";
import React from "react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useUnidades } from "@/features/estructura/hooks/useUnidades";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { usePermisoMutations } from "../hooks/usePermisoMutations";
import { asistenciaService } from "../services/asistenciaService";
import type {
  UnidadConRelaciones,
  ServidorConRelaciones,
  PermisoServidor,
} from "@/types/api";

const TIPO_OPTIONS = [
  { value: "personal", label: "Personal (máx. 4 horas)" },
  { value: "oficial", label: "Oficial" },
  { value: "enfermedad", label: "Por enfermedad" },
  { value: "calamidad", label: "Calamidad doméstica" },
];

const schema = z.object({
  unidad_administrativa_id: z.number({
    error: "Seleccione la unidad",
  }),
  servidor_id: z.number({ error: "Seleccione el servidor" })
    .min(1, "Seleccione el servidor"),
  jefe_id: z.number({ error: "Seleccione el jefe" }).optional().nullable(),
  tipo: z.enum(["personal", "oficial", "enfermedad", "calamidad"]),
  fecha: z.string().min(1, "La fecha es requerida"),
  hora_inicio: z.string().min(1, "Requerido"),
  hora_fin: z.string().min(1, "Requerido"),
  observacion: z.string().optional().nullable(),
});

type FormData = z.infer<typeof schema>;

const toDate = (v?: string | null): Date | null => {
  if (!v) return null;
  const [y, m, d] = v.split("-").map(Number);
  return new Date(y, m - 1, d);
};
const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null;
  if (typeof d === "string") return d.substring(0, 10);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

interface Props {
  opened: boolean;
  onClose: () => void;
  isAdmin?: boolean;
}

export function PermisoModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { crear } = usePermisoMutations();

  const [paso, setPaso] = useState(0);
  const [permisoCreado, setPermisoCreado] = useState<PermisoServidor | null>(
    null,
  );
  const [exportando, setExportando] = useState(false);
  const [unidadSelId, setUnidadSelId] = useState<number | null>(null);

  // Datos
  const { data: unidadesRaw } = useUnidades({ nivel: 2 });
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[];

  const { data: servidoresData } = useServidores({
    per_page: 200,
  });
  const todosServidores = (servidoresData?.data ??
    []) as ServidorConRelaciones[];

  // Filtrar servidores de la unidad seleccionada
  const servidoresUnidad = unidadSelId
    ? todosServidores.filter(
        (s) => Number(s.unidad_administrativa?.id) === unidadSelId,
      )
    : [];

  const unidadOptions = unidades.map((u) => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }));

  const servidorOptions = servidoresUnidad.map((s) => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre].filter(Boolean).join(" ")} — ${s.cedula}`,
  }));

  // Jefe: servidores de la misma unidad con es_jefe = true
  const jefeOptions = servidoresUnidad
    .filter((s) => (s.puesto as { es_jefe?: boolean } | null)?.es_jefe === true)
    .map((s) => ({
      value: String(s.id),
      label: `${[s.apellido, s.nombre].filter(Boolean).join(" ")}`,
    }));

  const {
    control,
    handleSubmit,
    reset,
    register,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      unidad_administrativa_id: undefined,
      servidor_id: undefined,
      jefe_id: null,
      tipo: "personal",
      fecha: "",
      hora_inicio: "08:00",
      hora_fin: "12:00",
      observacion: "",
    },
  });

  const tipoWatch = watch("tipo");

  const handleClose = () => {
    reset();
    setUnidadSelId(null);
    setPaso(0);
    setPermisoCreado(null);
    onClose();
  };

  const onSubmit = async (values: FormData) => {
    const result = await crear.mutateAsync(values);
    setPermisoCreado(result ?? null);
    setPaso(1);
  };

  const handleExportar = async () => {
    if (!permisoCreado) return;
    setExportando(true);
    try {
      const blob = await asistenciaService.permisos.exportar(
        Number(permisoCreado.id),
      );
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `permiso_${permisoCreado.folio ?? permisoCreado.id}.pdf`;
      link.click();
      URL.revokeObjectURL(url);
      notifications.show({
        title: "PDF descargado",
        message: "El permiso fue exportado correctamente.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
    } catch {
      notifications.show({
        title: "Error",
        message: "No se pudo exportar el PDF.",
        color: "red",
      });
    } finally {
      setExportando(false);
    }
  };

  return (
    <Modal
      closeOnClickOutside={false}
      opened={opened}
      onClose={handleClose}
      title="Registrar permiso de ausencia"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <Stepper active={paso} mb="lg" size="sm">
        <Stepper.Step label="Datos del permiso" />
        <Stepper.Step label="Confirmación" />
      </Stepper>

      {/* ── PASO 0: Formulario ── */}
      {paso === 0 && (
        <form onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="sm">
            {/* Unidad administrativa */}
            <Controller
              name="unidad_administrativa_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Unidad administrativa"
                  placeholder="Seleccionar unidad"
                  data={unidadOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => {
                    const id = v ? Number(v) : undefined;
                    field.onChange(id);
                    setUnidadSelId(id ?? null);
                    setValue("servidor_id", 0);
                    setValue("jefe_id", null);
                  }}
                  error={errors.unidad_administrativa_id?.message}
                />
              )}
            />

            <Grid>
              {/* Servidor */}
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Controller
                  name="servidor_id"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Servidor"
                      placeholder={
                        !unidadSelId
                          ? "Seleccione primero la unidad"
                          : servidorOptions.length === 0
                            ? "Sin servidores en esta unidad"
                            : "Seleccionar servidor"
                      }
                      data={servidorOptions}
                      searchable
                      disabled={!unidadSelId}
                      {...contained}
                      value={field.value ? String(field.value) : null}
                      onChange={(v) =>
                        field.onChange(v ? Number(v) : undefined)
                      }
                      error={errors.servidor_id?.message}
                    />
                  )}
                />
              </Grid.Col>

              {/* Jefe inmediato */}
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <Controller
                  name="jefe_id"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Jefe inmediato"
                      placeholder={
                        !unidadSelId
                          ? "Seleccione primero la unidad"
                          : jefeOptions.length === 0
                            ? "Sin jefes en esta unidad"
                            : "Seleccionar jefe"
                      }
                      data={jefeOptions}
                      searchable
                      clearable
                      disabled={!unidadSelId}
                      {...contained}
                      value={field.value ? String(field.value) : null}
                      onChange={(v) => field.onChange(v ? Number(v) : null)}
                      error={errors.jefe_id?.message}
                    />
                  )}
                />
              </Grid.Col>
            </Grid>

            <Divider label="Datos del permiso" labelPosition="left" />

            {/* Tipo de permiso */}
            <Controller
              name="tipo"
              control={control}
              render={({ field }) => (
                <Select
                  label="Tipo de permiso"
                  data={TIPO_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v ?? "personal")}
                  error={errors.tipo?.message}
                />
              )}
            />

            {tipoWatch === "personal" && (
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="orange"
                variant="light"
                py={6}
              >
                <Text size="xs">
                  Los permisos personales tienen un máximo de 4 horas y se
                  descuentan del saldo de vacaciones.
                </Text>
              </Alert>
            )}

            {/* Fecha */}
            <Controller
              name="fecha"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha del permiso"
                  placeholder="Seleccionar fecha"
                  valueFormat="YYYY-MM-DD"
                  minDate={new Date()}
                  {...contained}
                  value={toDate(field.value)}
                  onChange={(d: Date | string | null) => field.onChange(fromDate(d instanceof Date ? d : null) ?? "")}
                  error={errors.fecha?.message}
                />
              )}
            />

            <Grid>
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <TimeInput
                  label="Hora inicio"
                  {...contained}
                  {...register("hora_inicio")}
                  error={errors.hora_inicio?.message}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <TimeInput
                  label="Hora fin"
                  {...contained}
                  {...register("hora_fin")}
                  error={errors.hora_fin?.message}
                />
              </Grid.Col>
            </Grid>

            <Textarea
              label="Observación"
              placeholder={
                tipoWatch === "oficial"
                  ? "Requerido para permisos oficiales"
                  : "Motivo del permiso (opcional)"
              }
              autosize
              minRows={4}
              maxRows={6}
              {...contained}
              {...register("observacion")}
              error={errors.observacion?.message}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={handleClose}>
                Cancelar
              </Button>
              <Button
                type="submit"
                color="emerald"
                variant="light"
                loading={isSubmitting}
              >
                Crear permiso
              </Button>
            </Group>
          </Stack>
        </form>
      )}

      {/* ── PASO 1: Confirmación y exportar ── */}
      {paso === 1 && permisoCreado && (
        <Stack gap="md" align="center">
          <Alert
            icon={<IconCheck size={20} />}
            color="emerald"
            variant="light"
            w="100%"
          >
            <Text fw={600}>Permiso registrado correctamente</Text>
            <Text size="sm" mt={4}>
              Folio: <strong>{permisoCreado.folio ?? "—"}</strong>
            </Text>
          </Alert>

          <Stack gap="xs" w="100%">
            <Text size="sm" c="dimmed" ta="center">
              ¿Desea exportar el permiso en PDF para firma y archivo físico?
            </Text>
            <Group justify="center" mt="xs">
              <Button
                variant="light"
                color="blue"
                leftSection={<IconFileDownload size={16} />}
                loading={exportando}
                onClick={handleExportar}
              >
                Exportar PDF
              </Button>
              <Button variant="default" onClick={handleClose}>
                Cerrar
              </Button>
            </Group>
          </Stack>
        </Stack>
      )}
    </Modal>
  );
}
