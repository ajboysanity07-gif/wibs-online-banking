import { Slot } from "@radix-ui/react-slot"
import * as React from "react"

import { cn } from "@/lib/utils"

type TabsContextValue = {
  value?: string
  orientation?: "horizontal" | "vertical"
  setValue?: (value: string) => void
}

const TabsContext = React.createContext<TabsContextValue>({})

type TabsProps = React.ComponentProps<"div"> & {
  value?: string
  defaultValue?: string
  onValueChange?: (value: string) => void
  orientation?: "horizontal" | "vertical"
}

function Tabs({
  className,
  value,
  defaultValue,
  onValueChange,
  orientation = "horizontal",
  ...props
}: TabsProps) {
  const [internalValue, setInternalValue] = React.useState(defaultValue)
  const isControlled = value !== undefined
  const currentValue = isControlled ? value : internalValue

  React.useEffect(() => {
    if (!isControlled && defaultValue !== undefined) {
      setInternalValue(defaultValue)
    }
  }, [defaultValue, isControlled])

  const setValue = React.useCallback(
    (nextValue: string) => {
      if (!isControlled) {
        setInternalValue(nextValue)
      }

      onValueChange?.(nextValue)
    },
    [isControlled, onValueChange]
  )

  return (
    <TabsContext.Provider value={{ value: currentValue, setValue, orientation }}>
      <div
        data-slot="tabs"
        data-orientation={orientation}
        className={cn("flex", className)}
        {...props}
      />
    </TabsContext.Provider>
  )
}

function TabsList({ className, ...props }: React.ComponentProps<"div">) {
  const { orientation } = React.useContext(TabsContext)

  return (
    <div
      data-slot="tabs-list"
      data-orientation={orientation}
      role="tablist"
      className={cn(
        "inline-flex items-center justify-center rounded-lg bg-muted/40 p-1 text-muted-foreground",
        className
      )}
      {...props}
    />
  )
}

type TabsTriggerProps = React.ComponentProps<"button"> & {
  value: string
  asChild?: boolean
}

function TabsTrigger({
  className,
  value,
  asChild = false,
  onClick,
  ...props
}: TabsTriggerProps) {
  const { value: currentValue, setValue, orientation } =
    React.useContext(TabsContext)
  const isActive = currentValue === value
  const Comp = asChild ? Slot : "button"
  const triggerProps = asChild ? {} : { type: "button" as const }

  const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
    onClick?.(event)

    if (event.defaultPrevented || props.disabled) {
      return
    }

    setValue?.(value)
  }

  return (
    <Comp
      data-slot="tabs-trigger"
      data-orientation={orientation}
      data-state={isActive ? "active" : "inactive"}
      role="tab"
      aria-selected={isActive}
      tabIndex={isActive ? 0 : -1}
      className={cn(
        "inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-[color,box-shadow,transform] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm",
        className
      )}
      onClick={handleClick}
      {...triggerProps}
      {...props}
    />
  )
}

type TabsContentProps = React.ComponentProps<"div"> & {
  value: string
  forceMount?: boolean
}

function TabsContent({
  className,
  value,
  forceMount = false,
  ...props
}: TabsContentProps) {
  const { value: currentValue, orientation } = React.useContext(TabsContext)
  const isActive = currentValue === value

  if (!forceMount && !isActive) {
    return null
  }

  return (
    <div
      data-slot="tabs-content"
      data-orientation={orientation}
      data-state={isActive ? "active" : "inactive"}
      role="tabpanel"
      hidden={!isActive}
      className={cn(
        "mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40",
        className
      )}
      {...props}
    />
  )
}

export { Tabs, TabsList, TabsTrigger, TabsContent }
