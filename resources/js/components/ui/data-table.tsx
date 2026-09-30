import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
  type OnChangeFn,
  type RowSelectionState,
} from "@tanstack/react-table"

import {
  ResponsiveDataList,
  type ResponsiveDataListColumn,
  type ResponsiveDataListRow,
} from "@/components/responsive-data-list"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { cn } from "@/lib/utils"

interface DataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[]
  data: TData[]
  emptyMessage?: string
  className?: string
  getRowId?: (row: TData, index: number) => string
  rowSelection?: RowSelectionState
  onRowSelectionChange?: OnChangeFn<RowSelectionState>
  enableRowSelection?: boolean | ((row: { original: TData }) => boolean)
}

export function DataTable<TData, TValue>({
  columns,
  data,
  emptyMessage = "No results.",
  className,
  getRowId,
  rowSelection,
  onRowSelectionChange,
  enableRowSelection,
}: DataTableProps<TData, TValue>) {
  // eslint-disable-next-line react-hooks/incompatible-library -- TanStack Table returns non-memoizable helpers.
  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getRowId,
    state: rowSelection ? { rowSelection } : undefined,
    onRowSelectionChange,
    enableRowSelection,
  })

  // Mobile list is opt-in: a column with `meta.priority: "title"` enables it.
  const mobileColumns: ResponsiveDataListColumn[] = table
    .getVisibleLeafColumns()
    .map((column) => ({
      key: column.id,
      label:
        column.columnDef.meta?.label ??
        (typeof column.columnDef.header === "string"
          ? column.columnDef.header
          : column.id),
      priority: column.columnDef.meta?.priority ?? "detail",
    }))
  const titleColumn = table
    .getVisibleLeafColumns()
    .find((column) => column.columnDef.meta?.priority === "title")
  const mobileRows: ResponsiveDataListRow[] = titleColumn
    ? table.getRowModel().rows.map((row) => {
        const titleText = titleColumn.columnDef.meta?.text?.(row.original)
        const titleValue = row.getValue(titleColumn.id)

        return {
          id: row.id,
          initials:
            titleText ?? (typeof titleValue === "string" ? titleValue : ""),
          cells: Object.fromEntries(
            row
              .getVisibleCells()
              .map((cell) => [
                cell.column.id,
                // The mobile title is one truncated line, so use plain text.
                cell.column.id === titleColumn.id && titleText
                  ? titleText
                  : flexRender(cell.column.columnDef.cell, cell.getContext()),
              ])
          ),
        }
      })
    : []

  const desktopTable = (
      <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          {table.getHeaderGroups().map((headerGroup) => (
            <TableRow key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <TableHead key={header.id}>
                  {header.isPlaceholder
                    ? null
                    : flexRender(
                        header.column.columnDef.header,
                        header.getContext()
                      )}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>
        <TableBody>
          {table.getRowModel().rows?.length ? (
            table.getRowModel().rows.map((row) => (
              <TableRow key={row.id}>
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : (
            <TableRow>
              <TableCell
                colSpan={columns.length}
                className="h-24 text-center text-sm text-muted-foreground"
              >
                {emptyMessage}
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
      </div>
  )

  return (
    <div
      className={cn(
        "rounded-xl border border-border bg-card shadow-card",
        className
      )}
    >
      {titleColumn ? (
        <ResponsiveDataList
          columns={mobileColumns}
          rows={mobileRows}
          emptyMessage={emptyMessage}
        >
          {desktopTable}
        </ResponsiveDataList>
      ) : (
        desktopTable
      )}
    </div>
  )
}
