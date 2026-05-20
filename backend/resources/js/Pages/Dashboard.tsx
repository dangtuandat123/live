import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import {
  MessageSquareIcon,
  EyeIcon,
  VideoIcon,
  SmileIcon,
  PlusIcon,
  ArrowUpIcon,
  ArrowDownIcon,
} from "lucide-react"
import { Link } from "@inertiajs/react"

const stats = [
  {
    title: "Tổng phiên Live",
    value: "24",
    change: "+3 tuần này",
    trend: "up" as const,
    icon: <VideoIcon className="size-4" />,
  },
  {
    title: "Tổng bình luận",
    value: "12,847",
    change: "+18% so với tuần trước",
    trend: "up" as const,
    icon: <MessageSquareIcon className="size-4" />,
  },
  {
    title: "Tổng lượt xem",
    value: "89,234",
    change: "-5% so với tuần trước",
    trend: "down" as const,
    icon: <EyeIcon className="size-4" />,
  },
  {
    title: "Cảm xúc tích cực",
    value: "78%",
    change: "+2% so với tuần trước",
    trend: "up" as const,
    icon: <SmileIcon className="size-4" />,
  },
]

const recentSessions = [
  {
    id: "1",
    name: "Flash Sale Mùa Hè",
    platform: "facebook",
    status: "ended",
    comments: 1247,
    views: 8432,
    duration: "2h 15m",
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
    date: "17/05/2026",
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

export default function Dashboard() {
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
              <BreadcrumbItem>
                <BreadcrumbPage>Tổng quan</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Tổng quan</h1>
            <p className="text-muted-foreground">
              Tổng quan hoạt động livestream của bạn
            </p>
          </div>
          <Button asChild>
            <Link href={route("lives.create")}>
              <PlusIcon className="mr-2 size-4" />
              Tạo phiên Live
            </Link>
          </Button>
        </div>

        {/* KPI Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {stats.map((stat) => (
            <Card key={stat.title}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                  {stat.title}
                </CardTitle>
                <div className="text-muted-foreground">{stat.icon}</div>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stat.value}</div>
                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                  {stat.trend === "up" ? (
                    <ArrowUpIcon className="size-3 text-green-500" />
                  ) : (
                    <ArrowDownIcon className="size-3 text-red-500" />
                  )}
                  {stat.change}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Recent Sessions Table */}
        <Card>
          <CardHeader>
            <CardTitle>Phiên Live gần đây</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tên phiên</TableHead>
                  <TableHead>Nền tảng</TableHead>
                  <TableHead>Trạng thái</TableHead>
                  <TableHead className="text-right">Bình luận</TableHead>
                  <TableHead className="text-right">Lượt xem</TableHead>
                  <TableHead>Thời lượng</TableHead>
                  <TableHead>Ngày</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentSessions.map((session) => (
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
