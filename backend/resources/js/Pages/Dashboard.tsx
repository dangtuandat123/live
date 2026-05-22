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
  SearchIcon,
} from "lucide-react"

// --- Types ---

interface StatItem {
  title: string
  value: string
  change: string
  trend: "up" | "down"
}

interface ActiveLiveSession {
  id: string
  name: string
  views: number
  comments: number
  duration: string
}

interface TrendDay {
  date: string
  views: number
  comments: number
}

interface KeywordItem {
  keyword: string
  count: number
  trend: "up" | "down"
}

interface ProductItem {
  name: string
  mentions: number
  percent: number
}

interface RecentSessionItem {
  id: string
  name: string
  status: string
  comments: number
  views: number
  leads: number
  sentiment: number
  duration: string
  date: string
}

interface DashboardProps {
  stats: StatItem[]
  liveSession: ActiveLiveSession | null
  trendData: TrendDay[]
  hotKeywords: KeywordItem[]
  topProducts: ProductItem[]
  recentSessions: RecentSessionItem[]
}

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

// --- Components ---

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

export default function Dashboard({
  stats = [],
  liveSession = null,
  trendData = [],
  hotKeywords = [],
  topProducts = [],
  recentSessions = [],
}: DashboardProps) {
  // Icon mapper cho KPI cards
  const getIcon = (title: string) => {
    switch (title) {
      case "Tổng phiên Live":
        return <VideoIcon className="size-4" />
      case "Tổng bình luận":
        return <MessageSquareIcon className="size-4" />
      case "Tổng lượt xem":
        return <EyeIcon className="size-4" />
      case "Cảm xúc tích cực":
      default:
        return <SmileIcon className="size-4" />
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Tổng quan" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
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

      <div className="flex flex-1 flex-col gap-4 p-4 pt-4">
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
              Tạo phân tích phiên live
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
                <div className="text-muted-foreground">{getIcon(stat.title)}</div>
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
              {trendData.length > 0 ? (
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
              ) : (
                <div className="flex h-[240px] items-center justify-center text-muted-foreground text-sm">
                  Chưa có dữ liệu xu hướng
                </div>
              )}
            </CardContent>
          </Card>

          {/* Right Sidebar: Keywords + Top Products */}
          <div className="flex flex-col gap-4">
            {/* Hot Keywords */}
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                  <SearchIcon className="size-4" />
                  Từ khóa nổi bật
                </CardTitle>
                <CardDescription>Khách hàng đang hỏi gì trong live</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {hotKeywords.length > 0 ? (
                    hotKeywords.map((item, i) => (
                      <div key={item.keyword} className="flex items-center justify-between text-sm animate-in fade-in slide-in-from-bottom-1 duration-200" style={{ animationDelay: `${i * 50}ms` }}>
                        <span className="flex items-center gap-2">
                          <span className="flex size-5 items-center justify-center rounded-full bg-muted text-xs font-medium">{i + 1}</span>
                          <span className="truncate">"{item.keyword}"</span>
                        </span>
                        <span className="flex items-center gap-1.5">
                          <span className="font-medium tabular-nums">{item.count}</span>
                          {item.trend === "up" ? (
                            <ArrowUpIcon className="size-3 text-green-500" />
                          ) : (
                            <ArrowDownIcon className="size-3 text-red-500" />
                          )}
                        </span>
                      </div>
                    ))
                  ) : (
                    <div className="text-muted-foreground text-sm text-center py-4">
                      Chưa có từ khóa nổi bật
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Top Products */}
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                  <PackageIcon className="size-4" />
                  Top sản phẩm
                </CardTitle>
                <CardDescription>Được nhắc nhiều nhất tuần này</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {topProducts.length > 0 ? (
                    topProducts.map((product, i) => (
                      <div key={product.name} className="space-y-1 animate-in fade-in slide-in-from-bottom-1 duration-200" style={{ animationDelay: `${i * 50}ms` }}>
                        <div className="flex items-center justify-between text-sm">
                          <span className="flex items-center gap-2">
                            <span className="flex size-5 items-center justify-center rounded-full bg-muted text-xs font-medium">{i + 1}</span>
                            <span className="truncate">{product.name}</span>
                          </span>
                          <span className="font-medium tabular-nums">{product.mentions}</span>
                        </div>
                        <Progress value={product.percent} className="h-1.5" />
                      </div>
                    ))
                  ) : (
                    <div className="text-muted-foreground text-sm text-center py-4">
                      Chưa có sản phẩm nổi bật
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>
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
                  <TableHead>Trạng thái</TableHead>
                  <TableHead className="text-right">Bình luận</TableHead>
                  <TableHead className="text-right">Lượt xem</TableHead>
                  <TableHead className="text-right">
                    <span className="flex items-center justify-end gap-1"><UsersIcon className="size-3" />KH tiềm năng</span>
                  </TableHead>
                  <TableHead>Cảm xúc</TableHead>
                  <TableHead>Thời lượng</TableHead>
                  <TableHead>Ngày</TableHead>
                  <TableHead className="text-right">Thao tác</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentSessions.length > 0 ? (
                  recentSessions.map((session) => (
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
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" asChild>
                          <Link href={route("lives.show", session.id)}>
                            <EyeIcon className="mr-1.5 size-4" />
                            Xem
                          </Link>
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                      Chưa có phiên live nào được tạo
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
