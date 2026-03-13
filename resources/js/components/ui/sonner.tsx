import type { ComponentProps } from "react"
import { Toaster as SonnerToaster } from "sonner"

import { cn } from "@/lib/utils"

type ToasterProps = ComponentProps<typeof SonnerToaster>

export function Toaster({ className, ...props }: ToasterProps) {
  return (
    <SonnerToaster
      position="top-right"
      toastOptions={{
        duration: 4000,
        classNames: {
          toast: cn(
            "group pointer-events-auto relative flex w-full items-start gap-3 rounded-lg border border-border bg-popover px-4 py-3 text-sm text-popover-foreground shadow-sm",
            "motion-safe:data-[state=open]:animate-in motion-safe:data-[state=closed]:animate-out motion-safe:data-[state=closed]:fade-out-0 motion-safe:data-[state=open]:fade-in-0 motion-safe:data-[state=closed]:slide-out-to-right-2 motion-safe:data-[state=open]:slide-in-from-right-2 motion-reduce:animate-none"
          ),
          title: "text-sm font-medium leading-none",
          description: "text-xs text-muted-foreground leading-relaxed",
          actionButton:
            "h-8 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90",
          cancelButton:
            "h-8 rounded-md border border-border bg-transparent px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted",
        },
      }}
      className={cn("toaster group", className)}
      {...props}
    />
  )
}
