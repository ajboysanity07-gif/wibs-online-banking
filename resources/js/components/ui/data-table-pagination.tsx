import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"

type DataTablePaginationProps = {
  page: number
  perPage: number
  total: number
  onPageChange: (page: number) => void
}

export function DataTablePagination({
  page,
  perPage,
  total,
  onPageChange,
}: DataTablePaginationProps) {
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const canPrevious = page > 1
  const canNext = page < lastPage

  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-xs text-muted-foreground">
        Page {page} of {lastPage} ({total} results)
      </p>
      <div className="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={!canPrevious}
          onClick={() => onPageChange(page - 1)}
        >
          Previous
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={!canNext}
          onClick={() => onPageChange(page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  )
}

export function DataTablePaginationSkeleton() {
  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <Skeleton className="h-4 w-36" />
      <div className="flex items-center gap-2">
        <Skeleton className="h-8 w-20" />
        <Skeleton className="h-8 w-20" />
      </div>
    </div>
  )
}
