import { Metadata } from "next";
import { ViaticoView } from "@/features/viaticos/components/ViaticoView";

export const metadata: Metadata = {
  title: "Viaticos",
  description: "Gestión del control de viaticos institucional",
};

export default function ViaticoPage() {
  return <ViaticoView />;
}
