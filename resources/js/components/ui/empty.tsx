import * as React from "react"

import { cn } from "@/lib/utils"

/** Estado vacío: ícono, qué pasa y, si hay, la acción siguiente. */
function Empty({
  icon,
  title,
  description,
  children,
  className,
  ...props
}: Omit<React.ComponentProps<"div">, "title"> & {
  icon?: React.ReactNode
  title: React.ReactNode
  description?: React.ReactNode
}) {
  return (
    <div
      data-slot="empty"
      className={cn(
        "flex flex-col items-center justify-center gap-3 px-4 py-10 text-center",
        className
      )}
      {...props}
    >
      {icon && (
        <span
          aria-hidden="true"
          className="bg-muted text-muted-foreground flex size-11 items-center justify-center rounded-full [&_svg]:size-5"
        >
          {icon}
        </span>
      )}
      <div className="space-y-1">
        <p className="text-foreground text-sm font-semibold">{title}</p>
        {description && (
          <p className="text-muted-foreground mx-auto max-w-sm text-xs leading-relaxed">
            {description}
          </p>
        )}
      </div>
      {children}
    </div>
  )
}

export { Empty }
