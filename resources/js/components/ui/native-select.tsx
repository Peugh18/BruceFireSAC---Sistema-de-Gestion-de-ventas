import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Selector nativo con la misma cara que Input en todos los equipos. Conserva
 * el comportamiento del navegador (teclado, móvil); el ancho y el alto los
 * pone quien lo usa.
 */
function NativeSelect({ className, ...props }: React.ComponentProps<"select">) {
  return (
    <select
      data-slot="native-select"
      className={cn(
        className,
        "border-input bg-background/50 text-foreground focus-visible:border-ring focus-visible:ring-ring/50 appearance-none rounded-xl border bg-[length:1rem] bg-[position:right_0.75rem_center] bg-no-repeat py-1.5 pr-9 pl-3.5 text-sm shadow-xs transition-[color,box-shadow,border-color] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50",
        "bg-[url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E\")]"
      )}
      {...props}
    />
  )
}

export { NativeSelect }
