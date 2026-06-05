import type { Metadata } from "next";
import { AsistenciaView } from "./AsistenciaView";
export const metadata: Metadata = {
  title: "Asistencia",
  description: "Gestión del control de asistencia institucional",
};
export default function AsistenciaPage() {
  return <AsistenciaView />;
}
