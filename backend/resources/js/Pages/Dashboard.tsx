import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, Link } from "@inertiajs/react"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
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
import { Progress } from "@/components/ui/progress"
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { Area, AreaChart, CartesianGrid, XAxis } from "recharts"
import {
  MessageSquareIcon,
  EyeIcon,
  VideoIcon,
  SmileIcon,
  PlusIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  ArrowRightIcon,
  TrendingUpIcon,
  PackageIcon,
  UsersIcon,
} from "lucide-react"

// --- Mock Data ---

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

const liveSession = {
  id: "2",
  name: "Giới thiệu BST mới",
  platform: "tiktok",
  views: 3201,
  comments: 523,
  duration: "45m",
}

const trendData = [
  { date: "14/05", views: 4320, comments: 756 },
  { date: "15/05", views: 6210, comments: 980 },
  { date: "16/05", views: 9870, comments: 1890 },
  { date: "17/05", views: 5678, comments: 892 },
  { date: "18/05", views: 12450, comments: 2103 },
  { date: "19/05", views: 3201, comments: 523 },
  { date: "20/05", views: 8432, comments: 1247 },
]

const trendConfig = {
  views: {
    label: "Lượt xem",
    color: "var(--chart-1)",
  },
  comments: {
    label: "Bình luận",
    color: "var(--chart-2)",
  },
} satisfies ChartConfig

const topProducts = [
  { name: "Áo thun basic cotton", mentions: 342, percent: 100 },
  { name: "Váy hoa mùa hè", mentions: 278, percent: 81 },
  { name: "Quần jean slim fit", mentions: 195, percent: 57 },
  { name: "Túi xách da PU", mentions: 156, percent: 46 },
  { name: "Giày sneaker trắng", mentions: 98, percent: 29 },
]

const recentSessions = [
  {
    id: "1",
    name: "Flash Sale Mùa Hè",
    platform: "facebook",
    status: "ended",
    comments: 1247,
    views: 8432,
    leads: 45,
    sentiment: 82,
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
    leads: 12,
    sentiment: 75,
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
    leads: 28,
    sentiment: 68,
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
    leads: 67,
    sentiment: 85,
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
    leads: 15,
    sentiment: 71,
    duration: "1h 10m",
    date: "17/05/2026",
  },
]

// --- Components ---

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

// --- Main ---

export default function Dashboard() {
  return (
    <AuthenticatedLayout>
      <Head title="Tổng quan" />
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

        {/* Live Session Banner */}
        {liveSession && (
          <Card className="border-destructive/30 bg-destructive/5">
            <CardContent className="flex items-center justify-between py-3">
              <div className="flex items-center gap-3">
                <Badge variant="destructive" className="gap-1.5 px-2.5 py-1">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                  </span>
                  LIVE
                </Badge>
                <div>
                  <p className="font-semibold">{liveSession.name}</p>
                  <p className="flex items-center gap-3 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1"><EyeIcon className="size-3" />{liveSession.views.toLocaleString()} lượt xem</span>
                    <span className="flex items-center gap-1"><MessageSquareIcon className="size-3" />{liveSession.comments} bình luận</span>
                    <span>· {liveSession.duration}</span>
                  </p>
                </div>
              </div>
              <Button size="sm" asChild>
                <Link href={route("lives.show", liveSession.id)}>
                  Vào phân tích
                  <ArrowRightIcon className="ml-1.5 size-4" />
                </Link>
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Charts Row */}
        <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
          {/* Trend Chart */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <TrendingUpIcon className="size-4" />
                Xu hướng 7 ngày qua
              </CardTitle>
              <CardDescription>Lượt xem và bình luận theo ngày</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartContainer config={trendConfig} className="aspect-auto h-[240px] w-full">
                <AreaChart data={trendData}>
                  <defs>
                    <linearGradient id="fillViews" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="var(--color-views)" stopOpacity={0.8} />
                      <stop offset="95%" stopColor="var(--color-views)" stopOpacity={0.1} />
                    </linearGradient>
                    <linearGradient id="fillComments" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="var(--color-comments)" stopOpacity={0.8} />
                      <stop offset="95%" stopColor="var(--color-comments)" stopOpacity={0.1} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid vertical={false} />
                  <XAxis
                    dataKey="date"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                  />
                  <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent indicator="dot" />}
                  />
                  <Area
                    dataKey="views"
                    type="natural"
                    fill="url(#fillViews)"
                    stroke="var(--color-views)"
                    strokeWidth={2}
                  />
                  <Area
                    dataKey="comments"
                    type="natural"
                    fill="url(#fillComments)"
                    stroke="var(--color-comments)"
                    strokeWidth={2}
                  />
                </AreaChart>
              </ChartContainer>
            </CardContent>
          </Card>

          {/* Top Products */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <PackageIcon className="size-4" />
                Sản phẩm được nhắc nhiều
              </CardTitle>
              <CardDescription>Tuần này, từ bình luận phiên live</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {topProducts.map((product, i) => (
                  <div key={product.name} className="space-y-1">
                    <div className="flex items-center justify-between text-sm">
                      <span className="flex items-center gap-2">
                        <span className="flex size-5 items-center justify-center rounded-full bg-muted text-xs font-medium">{i + 1}</span>
                        <span className="truncate">{product.name}</span>
                      </span>
                      <span className="font-medium tabular-nums">{product.mentions}</span>
                    </div>
                    <Progress value={product.percent} className="h-1.5" />
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
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
                  <TableHead className="text-right">
                    <span className="flex items-center justify-end gap-1"><UsersIcon className="size-3" />Leads</span>
                  </TableHead>
                  <TableHead>Cảm xúc</TableHead>
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
                    <TableCell className="text-right">
                      {session.leads}
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Progress value={session.sentiment} className="h-2 w-12" />
                        <span className="text-xs tabular-nums">{session.sentiment}%</span>
                      </div>
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
