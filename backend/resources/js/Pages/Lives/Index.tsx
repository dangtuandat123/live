import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Card, CardContent } from "@/components/ui/card"
import { PlusIcon, SearchIcon } from "lucide-react"
import { Link } from "@inertiajs/react"
import * as React from "react"

const mockSessions = [
  {
    id: "1",
    name: "Flash Sale Mùa Hè",
    platform: "facebook",
    status: "ended",
    comments: 1247,
    views: 8432,
    duration: "2h 15m",
    products: 5,
    date: "20/05/2026",
  },
  {
    id: "2",
    name: "Giới thiệu BST mới",
    platform: "tiktok",
    status: "live",
    comments: 523,
    views: 3201,
    duration: "45m",
    products: 3,
    date: "20/05/2026",
  },
  {
    id: "3",
    name: "Thanh lý cuối tuần",
    platform: "instagram",
    status: "ended",
    comments: 892,
    views: 5678,
    duration: "1h 30m",
    products: 8,
    date: "19/05/2026",
  },
  {
    id: "4",
    name: "Review sản phẩm mới",
    platform: "facebook",
    status: "ended",
    comments: 2103,
    views: 12450,
    duration: "3h 05m",
    products: 4,
    date: "18/05/2026",
  },
  {
    id: "5",
    name: "Live Q&A khách hàng",
    platform: "tiktok",
    status: "ended",
    comments: 756,
    views: 4320,
    duration: "1h 10m",
    products: 2,
    date: "17/05/2026",
  },
  {
    id: "6",
    name: "Combo giá sốc",
    platform: "facebook",
    status: "ended",
    comments: 1890,
    views: 9870,
    duration: "2h 45m",
    products: 6,
    date: "16/05/2026",
  },
]

function PlatformBadge({ platform }: { platform: string }) {
  const config: Record<string, { label: string; variant: "default" | "secondary" | "outline" }> = {
    facebook: { label: "Facebook", variant: "default" },
    tiktok: { label: "TikTok", variant: "secondary" },
    instagram: { label: "Instagram", variant: "outline" },
  }
  const c = config[platform] ?? config.facebook
  return <Badge variant={c.variant}>{c.label}</Badge>
}

function StatusBadge({ status }: { status: string }) {
  if (status === "live") {
    return (
      <Badge variant="destructive" className="gap-1">
        <span className="relative flex size-2">
          <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
          <span className="relative inline-flex size-2 rounded-full bg-current" />
        </span>
        Đang Live
      </Badge>
    )
  }
  return <Badge variant="secondary">Đã kết thúc</Badge>
}

export default function LivesIndex() {
  const [search, setSearch] = React.useState("")
  const [platformFilter, setPlatformFilter] = React.useState("all")
  const [statusFilter, setStatusFilter] = React.useState("all")

  const filtered = mockSessions.filter((s) => {
    const matchSearch = s.name.toLowerCase().includes(search.toLowerCase())
    const matchPlatform =
      platformFilter === "all" || s.platform === platformFilter
    const matchStatus = statusFilter === "all" || s.status === statusFilter
    return matchSearch && matchPlatform && matchStatus
  })

  return (
    <AuthenticatedLayout>
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
                <BreadcrumbPage>Phiên Live</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Phiên Live</h1>
            <p className="text-muted-foreground">
              Quản lý và theo dõi tất cả phiên livestream
            </p>
          </div>
          <Button asChild>
            <Link href={route("lives.create")}>
              <PlusIcon className="mr-2 size-4" />
              Tạo phiên mới
            </Link>
          </Button>
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
          <Select value={platformFilter} onValueChange={setPlatformFilter}>
            <SelectTrigger className="w-[140px]">
              <SelectValue placeholder="Nền tảng" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Tất cả</SelectItem>
              <SelectItem value="facebook">Facebook</SelectItem>
              <SelectItem value="tiktok">TikTok</SelectItem>
              <SelectItem value="instagram">Instagram</SelectItem>
            </SelectContent>
          </Select>
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

        {/* Table */}
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tên phiên</TableHead>
                  <TableHead>Nền tảng</TableHead>
                  <TableHead>Trạng thái</TableHead>
                  <TableHead className="text-right">Bình luận</TableHead>
                  <TableHead className="text-right">Lượt xem</TableHead>
                  <TableHead>Sản phẩm</TableHead>
                  <TableHead>Thời lượng</TableHead>
                  <TableHead>Ngày</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((session) => (
                  <TableRow key={session.id}>
                    <TableCell className="font-medium">
                      <Link
                        href={route("lives.show", session.id)}
                        className="hover:underline"
                      >
                        {session.name}
                      </Link>
                    </TableCell>
                    <TableCell>
                      <PlatformBadge platform={session.platform} />
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={session.status} />
                    </TableCell>
                    <TableCell className="text-right">
                      {session.comments.toLocaleString()}
                    </TableCell>
                    <TableCell className="text-right">
                      {session.views.toLocaleString()}
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{session.products} SP</Badge>
                    </TableCell>
                    <TableCell>{session.duration}</TableCell>
                    <TableCell className="text-muted-foreground">
                      {session.date}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
