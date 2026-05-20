import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head } from "@inertiajs/react"
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
import { Progress } from "@/components/ui/progress"
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

// --- Mock Data ---

const mockSessions = [
  {
    id: "1",
    name: "Flash Sale Mùa Hè",
    status: "ended",
    comments: 1247,
    views: 8432,
    leads: 45,
    sentiment: 82,
    duration: "2h 15m",
    products: 5,
    date: "20/05/2026",
    thumbnail: "https://images.unsplash.com/photo-1607083206869-4c7672e72a8a?w=240&h=420&fit=crop&auto=format",
  },
  {
    id: "2",
    name: "Giới thiệu BST mới",
    status: "live",
    comments: 523,
    views: 3201,
    leads: 12,
    sentiment: 75,
    duration: "45m",
    products: 3,
    date: "20/05/2026",
    thumbnail: "https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=240&h=420&fit=crop&auto=format",
  },
  {
    id: "3",
    name: "Thanh lý cuối tuần",
    status: "ended",
    comments: 892,
    views: 5678,
    leads: 28,
    sentiment: 68,
    duration: "1h 30m",
    products: 8,
    date: "19/05/2026",
    thumbnail: "https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=240&h=420&fit=crop&auto=format",
  },
  {
    id: "4",
    name: "Review sản phẩm mới",
    status: "ended",
    comments: 2103,
    views: 12450,
    leads: 67,
    sentiment: 85,
    duration: "3h 05m",
    products: 4,
    date: "18/05/2026",
    thumbnail: "https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=240&h=420&fit=crop&auto=format",
  },
  {
    id: "5",
    name: "Live Q&A khách hàng",
    status: "ended",
    comments: 756,
    views: 4320,
    leads: 15,
    sentiment: 71,
    duration: "1h 10m",
    products: 2,
    date: "17/05/2026",
    thumbnail: "https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=240&h=420&fit=crop&auto=format",
  },
  {
    id: "6",
    name: "Combo giá sốc",
    status: "ended",
    comments: 1890,
    views: 9870,
    leads: 52,
    sentiment: 79,
    duration: "2h 45m",
    products: 6,
    date: "16/05/2026",
    thumbnail: "https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=240&h=420&fit=crop&auto=format",
  },
]

const totalViews = mockSessions.reduce((s, x) => s + x.views, 0)
const totalComments = mockSessions.reduce((s, x) => s + x.comments, 0)
const liveSessions = mockSessions.filter((s) => s.status === "live")

export default function LivesIndex() {
  const [search, setSearch] = React.useState("")
  const [statusFilter, setStatusFilter] = React.useState("all")
  const [page, setPage] = React.useState(1)
  const perPage = 12

  const filtered = mockSessions.filter((s) => {
    const matchSearch = s.name.toLowerCase().includes(search.toLowerCase())
    const matchStatus = statusFilter === "all" || s.status === statusFilter
    return matchSearch && matchStatus
  })

  const totalPages = Math.max(1, Math.ceil(filtered.length / perPage))
  const safePage = Math.min(page, totalPages)
  const paginated = filtered.slice((safePage - 1) * perPage, safePage * perPage)

  // Reset to page 1 when filters change
  React.useEffect(() => {
    setPage(1)
  }, [search, statusFilter])

  return (
    <AuthenticatedLayout>
      <Head title="Phân tích phiên live" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
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
              <div className="text-2xl font-bold">{mockSessions.length}</div>
              <p className="text-xs text-muted-foreground">
                {liveSessions.length > 0 ? `${liveSessions.length} đang live` : "Không có phiên live"}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Đang Live</CardTitle>
              <RadioIcon className="size-4 text-red-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-red-500">{liveSessions.length}</div>
              <p className="text-xs text-muted-foreground">
                {liveSessions.length > 0
                  ? `${liveSessions.reduce((s, x) => s + x.views, 0).toLocaleString()} lượt xem`
                  : "—"
                }
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng lượt xem</CardTitle>
              <EyeIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{totalViews.toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">Từ tất cả phiên</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng bình luận</CardTitle>
              <MessageSquareIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{totalComments.toLocaleString()}</div>
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
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
          <Select value={statusFilter} onValueChange={setStatusFilter}>
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

        {/* Session Cards Grid — TikTok-style portrait */}
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
                  <img
                    src={session.thumbnail}
                    alt={session.name}
                    className="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                  {/* Gradient overlay */}
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

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="flex items-center justify-center gap-2 pt-2">
            <Button
              variant="outline"
              size="sm"
              disabled={safePage <= 1}
              onClick={() => setPage(safePage - 1)}
            >
              <ChevronLeftIcon className="mr-1 size-4" />
              Trước
            </Button>
            <span className="text-sm text-muted-foreground px-3">
              Trang {safePage} / {totalPages}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={safePage >= totalPages}
              onClick={() => setPage(safePage + 1)}
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
