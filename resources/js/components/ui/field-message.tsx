import { cn } from "@/lib/utils"

type FieldMessageProps = {
  error?: string
  hint?: string
  id?: string
  lines?: 1 | 2
  reserveSpace?: boolean
  className?: string
}

function FieldMessage({
  error,
  hint,
  id,
  lines = 1,
  reserveSpace = true,
  className,
}: FieldMessageProps) {
  const text = error ?? hint ?? ""
  const displayText = reserveSpace && text === "" ? "\u00A0" : text
  const isError = Boolean(error)
  const lineClasses = reserveSpace
    ? lines === 2
      ? "min-h-[2rem] line-clamp-2"
      : "min-h-[1rem] line-clamp-1"
    : "line-clamp-1"

  return (
    <p
      id={id}
      aria-live="polite"
      className={cn(
        "mt-1 text-xs leading-4",
        lineClasses,
        isError ? "text-destructive" : "text-muted-foreground",
        className
      )}
    >
      {displayText}
    </p>
  )
}

export { FieldMessage }
