import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { cn } from "@/lib/utils"

type TableSkeletonColumn = {
  headerClassName?: string
  cellClassName?: string
  align?: "left" | "center" | "right"
}

type TableSkeletonProps = {
  columns: TableSkeletonColumn[]
  rows?: number
  className?: string
  tableClassName?: string
  showHeader?: boolean
}

const alignMap: Record<NonNullable<TableSkeletonColumn["align"]>, string> = {
  left: "justify-start",
  center: "justify-center",
  right: "justify-end",
}

export function TableSkeleton({
  columns,
  rows = 5,
  className,
  tableClassName,
  showHeader = true,
}: TableSkeletonProps) {
  return (
    <div className={cn(className)}>
      <Table className={tableClassName}>
        {showHeader ? (
          <TableHeader>
            <TableRow>
              {columns.map((column, index) => (
                <TableHead key={`skeleton-head-${index}`}>
                  <div
                    className={cn(
                      "flex items-center",
                      alignMap[column.align ?? "left"]
                    )}
                  >
                    <Skeleton
                      className={cn("h-3 w-16", column.headerClassName)}
                    />
                  </div>
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
        ) : null}
        <TableBody>
          {Array.from({ length: rows }).map((_, rowIndex) => (
            <TableRow key={`skeleton-row-${rowIndex}`}>
              {columns.map((column, columnIndex) => (
                <TableCell
                  key={`skeleton-cell-${rowIndex}-${columnIndex}`}
                >
                  <div
                    className={cn(
                      "flex items-center",
                      alignMap[column.align ?? "left"]
                    )}
                  >
                    <Skeleton
                      className={cn("h-4 w-24", column.cellClassName)}
                    />
                  </div>
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
