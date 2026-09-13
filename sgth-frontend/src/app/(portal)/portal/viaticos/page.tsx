import { Metadata } from "next";
import { ViaticoView } from "@/features/viaticos/components/ViaticoView";

export const metadata: Metadata = {
  title: "Mis viáticos",
  description: "Tus comisiones de servicio y viáticos",
};

export default function ViaticoPage() {
  return <ViaticoView />;
}
