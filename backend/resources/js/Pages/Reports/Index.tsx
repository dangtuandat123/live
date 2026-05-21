import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, Link, router } from "@inertiajs/react"
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { Area, AreaChart, Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"
import {
  TrendingUpIcon,
  TrendingDownIcon,
  VideoIcon,
  MessageSquareIcon,
  SmileIcon,
  SparklesIcon,
  DownloadIcon,
  UsersIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  SearchIcon,
  EyeIcon,
} from "lucide-react"
import * as React from "react"

// --- Types ---

interface Props {
  overviewStats: {
    title: string
    value: string
    prev: string
  }[]
  trendData: {
    date: string
    views: number
    comments: number
    leads: number
  }[]
  sessionCompareData: {
    name: string
    views: number
    comments: number
  }[]
  hotKeywords: {
    keyword: string
    count: number
    trend: "up" | "down"
  }[]
  recentSessions: {
    id: string
    name: string
    comments: number
    views: number
    leads: number
    sentiment: number
    date: string
  }[]
  aiRecommendation: string
  filters: {
    period: string
  }
}

const trendConfig = {
  views: { label: "Lượt xem", color: "var(--chart-1)" },
  comments: { label: "Bình luận", color: "var(--chart-2)" },
  leads: { label: "KH tiềm năng", color: "var(--chart-3)" },
} satisfies ChartConfig

const sessionBarConfig = {
  views: { label: "Lượt xem", color: "var(--chart-1)" },
  comments: { label: "Bình luận", color: "var(--chart-2)" },
} satisfies ChartConfig

// --- Main ---

export default function ReportsIndex({
  overviewStats = [],
  trendData = [],
  sessionCompareData = [],
  hotKeywords = [],
  recentSessions = [],
  aiRecommendation = "",
  filters,
}: Props) {
  const [period, setPeriod] = React.useState(filters.period ?? "7d")

  const handlePeriodChange = (val: string) => {
    setPeriod(val)
    router.get(route("reports.index"), { period: val }, { preserveState: true })
  }

  const getIcon = (title: string) => {
    switch (title) {
      case "Tổng phiên Live":
        return <VideoIcon className="size-4" />
      case "Tổng bình luận":
        return <MessageSquareIcon className="size-4" />
      case "Tổng lượt xem":
        return <EyeIcon className="size-4" />
      case "TB cảm xúc tích cực":
      default:
        return <SmileIcon className="size-4" />
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Báo cáo" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
        <div className="flex items-center gap-2 px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-auto" />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href={route("dashboard")}>Trang chủ</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Báo cáo</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Báo cáo tổng hợp</h1>
            <p className="text-muted-foreground">Phân tích hiệu suất livestream qua nhiều phiên</p>
          </div>
          <div className="flex items-center gap-2">
            <Select value={period} onValueChange={handlePeriodChange}>
              <SelectTrigger className="w-[150px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="7d">7 ngày qua</SelectItem>
                <SelectItem value="30d">30 ngày qua</SelectItem>
                <SelectItem value="90d">90 ngày qua</SelectItem>
              </SelectContent>
            </Select>
            <Button variant="outline">
              <DownloadIcon className="mr-2 size-4" />
              Xuất PDF
            </Button>
          </div>
        </div>

        {/* KPI Overview */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {overviewStats.map((stat) => {
            const current = parseFloat(stat.value.replace(/,/g, "")) || 0
            const prev = parseFloat(stat.prev.replace(/,/g, "")) || 0
            const isUp = current >= prev
            return (
              <Card key={stat.title}>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                  <div className="text-muted-foreground">{getIcon(stat.title)}</div>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">{stat.value}</div>
                  <p className="flex items-center gap-1 text-xs text-muted-foreground">
                    {isUp ? (
                      <TrendingUpIcon className="size-3 text-green-500" />
                    ) : (
                      <TrendingDownIcon className="size-3 text-red-500" />
                    )}
                    so với kỳ trước: {stat.prev}
                  </p>
                </CardContent>
              </Card>
            )
          })}
        </div>

        {/* AI Recommendations */}
        {aiRecommendation && (
          <Alert className="border-primary/20 bg-primary/5">
            <SparklesIcon className="size-4 text-primary" />
            <AlertTitle className="text-primary font-semibold">Gợi ý từ AI</AlertTitle>
            <AlertDescription className="text-muted-foreground text-sm mt-1">
              {aiRecommendation}
            </AlertDescription>
          </Alert>
        )}

        {/* Trend Chart */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUpIcon className="size-4" />
              Xu hướng theo kỳ
            </CardTitle>
            <CardDescription>Lượt xem, bình luận và KH tiềm năng theo ngày trong khoảng thời gian đã chọn</CardDescription>
          </CardHeader>
          <CardContent>
            {trendData.length > 0 ? (
              <ChartContainer config={trendConfig} className="aspect-auto h-[280px] w-full">
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
                    <linearGradient id="fillLeads" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="var(--color-leads)" stopOpacity={0.8} />
                      <stop offset="95%" stopColor="var(--color-leads)" stopOpacity={0.1} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid vertical={false} />
                  <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={8} />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                  <Area dataKey="views" type="natural" fill="url(#fillViews)" stroke="var(--color-views)" strokeWidth={2} />
                  <Area dataKey="comments" type="natural" fill="url(#fillComments)" stroke="var(--color-comments)" strokeWidth={2} />
                  <Area dataKey="leads" type="natural" fill="url(#fillLeads)" stroke="var(--color-leads)" strokeWidth={2} />
                  <ChartLegend content={<ChartLegendContent />} />
                </AreaChart>
              </ChartContainer>
            ) : (
              <div className="flex h-[280px] items-center justify-center text-muted-foreground text-sm">
                Chưa có dữ liệu xu hướng trong kỳ này
              </div>
            )}
          </CardContent>
        </Card>

        {/* Bottom Row: Session Compare + Keywords */}
        <div className="grid gap-4 lg:grid-cols-2">
          {/* Session Comparison */}
          <Card>
            <CardHeader>
              <CardTitle>So sánh phiên Live</CardTitle>
              <CardDescription>Top 5 phiên theo lượt xem và bình luận</CardDescription>
            </CardHeader>
            <CardContent>
              {sessionCompareData.length > 0 ? (
                <ChartContainer config={sessionBarConfig} className="aspect-auto h-[280px] w-full">
                  <BarChart data={sessionCompareData} layout="vertical" margin={{ left: 10 }}>
                    <CartesianGrid horizontal={false} />
                    <YAxis
                      dataKey="name"
                      type="category"
                      tickLine={false}
                      axisLine={false}
                      width={90}
                      tick={{ fontSize: 12 }}
                    />
                    <XAxis type="number" hide />
                    <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                    <Bar dataKey="views" fill="var(--color-views)" radius={[0, 4, 4, 0]} barSize={14} />
                    <Bar dataKey="comments" fill="var(--color-comments)" radius={[0, 4, 4, 0]} barSize={14} />
                    <ChartLegend content={<ChartLegendContent />} />
                  </BarChart>
                </ChartContainer>
              ) : (
                <div className="flex h-[280px] items-center justify-center text-muted-foreground text-sm">
                  Chưa có dữ liệu so sánh phiên live
                </div>
              )}
            </CardContent>
          </Card>

          {/* Hot Keywords */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <SearchIcon className="size-4" />
                Từ khóa nổi bật trong kỳ
              </CardTitle>
              <CardDescription>Top 10 từ khóa khách nhắc nhiều nhất từ bình luận</CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-8">#</TableHead>
                    <TableHead>Từ khóa</TableHead>
                    <TableHead className="text-right">Số lần</TableHead>
                    <TableHead className="w-12 text-right">Trend</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {hotKeywords.length > 0 ? (
                    hotKeywords.map((item, i) => (
                      <TableRow key={item.keyword}>
                        <TableCell className="font-medium text-muted-foreground">{i + 1}</TableCell>
                        <TableCell>
                          <span className="font-medium">"{item.keyword}"</span>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">{item.count}</TableCell>
                        <TableCell className="text-right">
                          {item.trend === "up" ? (
                            <ArrowUpIcon className="ml-auto size-4 text-green-500 animate-in fade-in" />
                          ) : (
                            <ArrowDownIcon className="ml-auto size-4 text-red-500 animate-in fade-in" />
                          )}
                        </TableCell>
                      </TableRow>
                    ))
                  ) : (
                    <TableRow>
                      <TableCell colSpan={4} className="text-center py-6 text-muted-foreground text-sm">
                        Chưa có dữ liệu từ khóa
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>

        {/* Recent Sessions */}
        <Card>
          <CardHeader>
            <CardTitle>Phiên Live gần đây</CardTitle>
            <CardDescription>Hiệu suất từng phiên để so sánh</CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Phiên</TableHead>
                  <TableHead className="text-right">Bình luận</TableHead>
                  <TableHead className="text-right">Lượt xem</TableHead>
                  <TableHead className="text-right">
                    <span className="flex items-center justify-end gap-1"><UsersIcon className="size-3" />KH tiềm năng</span>
                  </TableHead>
                  <TableHead>Cảm xúc</TableHead>
                  <TableHead className="text-right">Thao tác</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentSessions.length > 0 ? (
                  recentSessions.map((s) => (
                    <TableRow key={s.id}>
                      <TableCell>
                        <div>
                          <div className="font-medium text-sm">
                            <Link href={route("lives.show", s.id)} className="hover:underline">
                              {s.name}
                            </Link>
                          </div>
                          <div className="text-xs text-muted-foreground">{s.date}</div>
                        </div>
                      </TableCell>
                      <TableCell className="text-right">{s.comments.toLocaleString()}</TableCell>
                      <TableCell className="text-right">{s.views.toLocaleString()}</TableCell>
                      <TableCell className="text-right">{s.leads}</TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Progress value={s.sentiment} className="h-2 w-16" />
                          <span className="text-xs tabular-nums">{s.sentiment}%</span>
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" asChild>
                          <Link href={route("lives.show", s.id)}>
                            <EyeIcon className="mr-1.5 size-4" />
                            Xem
                          </Link>
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                      Không có phiên live nào trong kỳ đã chọn
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
