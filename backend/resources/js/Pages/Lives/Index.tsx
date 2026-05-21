import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, router } from "@inertiajs/react"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  PlusIcon,
  SearchIcon,
  EyeIcon,
  VideoIcon,
  MessageSquareIcon,
  ClockIcon,
  RadioIcon,
  PackageIcon,
  UsersIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from "lucide-react"
import { Link } from "@inertiajs/react"
import * as React from "react"

// --- Types ---

interface Session {
  id: number
  name: string
  status: string
  comments: number
  views: number
  leads: number
  sentiment: number
  duration: string
  products: number
  date: string
  thumbnail: string | null
}

interface PaginatedSessions {
  data: Session[]
  current_page: number
  last_page: number
  total: number
}

interface KPI {
  total_sessions: number
  live_count: number
  live_views: number
  total_views: number
  total_comments: number
}

interface Props {
  sessions: PaginatedSessions
  kpi: KPI
  filters: { search: string | null; status: string | null }
}

export default function LivesIndex({ sessions, kpi, filters }: Props) {
  const [search, setSearch] = React.useState(filters.search ?? "")
  const [statusFilter, setStatusFilter] = React.useState(filters.status ?? "all")

  // Debounced search
  const searchTimerRef = React.useRef<ReturnType<typeof setTimeout>>(undefined)
  const applyFilters = (newSearch?: string, newStatus?: string) => {
    const params: Record<string, string> = {}
    const s = newSearch ?? search
    const st = newStatus ?? statusFilter
    if (s) params.search = s
    if (st && st !== "all") params.status = st
    router.get(route("lives.index"), params, { preserveState: true, replace: true })
  }

  const handleSearch = (value: string) => {
    setSearch(value)
    clearTimeout(searchTimerRef.current)
    searchTimerRef.current = setTimeout(() => applyFilters(value), 400)
  }

  const handleStatusChange = (value: string) => {
    setStatusFilter(value)
    applyFilters(undefined, value)
  }

  const paginated = sessions.data

  return (
    <AuthenticatedLayout>
      <Head title="Phân tích phiên live" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
        <div className="flex items-center gap-2 px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator
            orientation="vertical"
            className="mr-2 data-vertical:h-4 data-vertical:self-auto"
          />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href={route("dashboard")}>
                  Trang chủ
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Phân tích phiên live</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Phân tích phiên live</h1>
            <p className="text-muted-foreground">
              Quản lý và theo dõi tất cả phiên livestream TikTok
            </p>
          </div>
          <Button asChild>
            <Link href={route("lives.create")}>
              <PlusIcon className="mr-2 size-4" />
              Tạo phân tích phiên live
            </Link>
          </Button>
        </div>

        {/* KPI Cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng phiên</CardTitle>
              <VideoIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{kpi.total_sessions}</div>
              <p className="text-xs text-muted-foreground">
                {kpi.live_count > 0 ? `${kpi.live_count} đang live` : "Không có phiên live"}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Đang Live</CardTitle>
              <RadioIcon className="size-4 text-red-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-red-500">{kpi.live_count}</div>
              <p className="text-xs text-muted-foreground">
                {kpi.live_count > 0 ? `${kpi.live_views.toLocaleString()} lượt xem` : "—"}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng lượt xem</CardTitle>
              <EyeIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{kpi.total_views.toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">Từ tất cả phiên</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng bình luận</CardTitle>
              <MessageSquareIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{kpi.total_comments.toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">Từ tất cả phiên</p>
            </CardContent>
          </Card>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-3">
          <div className="relative flex-1 max-w-sm">
            <SearchIcon className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Tìm phiên live..."
              value={search}
              onChange={(e) => handleSearch(e.target.value)}
              className="pl-9"
            />
          </div>
          <Select value={statusFilter} onValueChange={handleStatusChange}>
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder="Trạng thái" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Tất cả</SelectItem>
              <SelectItem value="live">Đang Live</SelectItem>
              <SelectItem value="ended">Đã kết thúc</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Session Cards Grid */}
        <div className="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
          {paginated.map((session) => (
            <Link
              key={session.id}
              href={route("lives.show", session.id)}
              className="group block"
            >
              <div className="relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg hover:border-primary/30">
                {/* Portrait Thumbnail */}
                <div className="relative aspect-[9/16] overflow-hidden bg-muted">
                  {session.thumbnail ? (
                    <img
                      src={session.thumbnail}
                      alt={session.name}
                      className="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                  ) : (
                    <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5">
                      <VideoIcon className="size-8 text-muted-foreground/50" />
                    </div>
                  )}
                  <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />

                  {/* Status badge */}
                  <div className="absolute left-2 top-2">
                    {session.status === "live" ? (
                      <Badge variant="destructive" className="gap-1 text-[10px] px-1.5 py-0.5 shadow-lg">
                        <span className="relative flex size-1.5">
                          <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                          <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                        </span>
                        LIVE
                      </Badge>
                    ) : (
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5 bg-black/50 text-white border-0">
                        Đã kết thúc
                      </Badge>
                    )}
                  </div>

                  {/* Duration badge */}
                  <div className="absolute right-2 top-2">
                    <span className="inline-flex items-center gap-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">
                      <ClockIcon className="size-2.5" />
                      {session.duration}
                    </span>
                  </div>

                  {/* Bottom info overlay */}
                  <div className="absolute bottom-0 left-0 right-0 p-3">
                    <h3 className="font-semibold text-sm text-white leading-tight line-clamp-2 mb-1.5">
                      {session.name}
                    </h3>
                    <div className="flex items-center gap-2.5 text-[11px] text-white/80">
                      <span className="flex items-center gap-1">
                        <EyeIcon className="size-3" />
                        {session.views.toLocaleString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <MessageSquareIcon className="size-3" />
                        {session.comments.toLocaleString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <PackageIcon className="size-3" />
                        {session.products}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Bottom bar */}
                <div className="px-3 py-2 flex items-center justify-between">
                  <span className="text-xs text-muted-foreground">{session.date}</span>
                  <div className="flex items-center gap-1.5">
                    <UsersIcon className="size-3 text-muted-foreground" />
                    <span className="text-xs font-medium">{session.leads}</span>
                  </div>
                </div>
              </div>
            </Link>
          ))}
        </div>

        {/* Empty state */}
        {paginated.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <VideoIcon className="size-12 text-muted-foreground/30 mb-4" />
            <h3 className="text-lg font-semibold">Chưa có phiên live nào</h3>
            <p className="text-muted-foreground text-sm mt-1">Tạo phiên phân tích đầu tiên để bắt đầu</p>
            <Button asChild className="mt-4">
              <Link href={route("lives.create")}>
                <PlusIcon className="mr-2 size-4" />
                Tạo phân tích
              </Link>
            </Button>
          </div>
        )}

        {/* Pagination */}
        {sessions.last_page > 1 && (
          <div className="flex items-center justify-center gap-2 pt-2">
            <Button
              variant="outline"
              size="sm"
              disabled={sessions.current_page <= 1}
              onClick={() => {
                const params: Record<string, any> = { page: sessions.current_page - 1 }
                if (search) params.search = search
                if (statusFilter && statusFilter !== "all") params.status = statusFilter
                router.get(route("lives.index"), params, { preserveState: true })
              }}
            >
              <ChevronLeftIcon className="mr-1 size-4" />
              Trước
            </Button>
            <span className="text-sm text-muted-foreground px-3">
              Trang {sessions.current_page} / {sessions.last_page}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={sessions.current_page >= sessions.last_page}
              onClick={() => {
                const params: Record<string, any> = { page: sessions.current_page + 1 }
                if (search) params.search = search
                if (statusFilter && statusFilter !== "all") params.status = statusFilter
                router.get(route("lives.index"), params, { preserveState: true })
              }}
            >
              Sau
              <ChevronRightIcon className="ml-1 size-4" />
            </Button>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  )
}
