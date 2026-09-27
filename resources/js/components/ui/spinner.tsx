import { Cargando } from "@/components/cargando"

/** Alias del spinner único del sistema (components/cargando.tsx). */
function Spinner({ className }: { className?: string }) {
  return <Cargando className={className} />
}

export { Spinner }
