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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
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
  EyeIcon,
  SmileIcon,
  SparklesIcon,
  DownloadIcon,
  UsersIcon,
} from "lucide-react"
import * as React from "react"

// --- Mock Data ---

const overviewStats = [
  {
    title: "Tổng phiên Live",
    value: "24",
    prev: "21",
    icon: <VideoIcon className="size-4" />,
  },
  {
    title: "Tổng bình luận",
    value: "12,847",
    prev: "10,892",
    icon: <MessageSquareIcon className="size-4" />,
  },
  {
    title: "Tổng lượt xem",
    value: "89,234",
    prev: "93,872",
    icon: <EyeIcon className="size-4" />,
  },
  {
    title: "TB cảm xúc tích cực",
    value: "76%",
    prev: "72%",
    icon: <SmileIcon className="size-4" />,
  },
]

const trendData = [
  { date: "14/05", views: 4320, comments: 756, leads: 15 },
  { date: "15/05", views: 6210, comments: 980, leads: 22 },
  { date: "16/05", views: 9870, comments: 1890, leads: 38 },
  { date: "17/05", views: 5678, comments: 892, leads: 28 },
  { date: "18/05", views: 12450, comments: 2103, leads: 67 },
  { date: "19/05", views: 3201, comments: 523, leads: 12 },
  { date: "20/05", views: 8432, comments: 1247, leads: 45 },
]

const trendConfig = {
  views: { label: "Lượt xem", color: "var(--chart-1)" },
  comments: { label: "Bình luận", color: "var(--chart-2)" },
  leads: { label: "Leads", color: "var(--chart-3)" },
} satisfies ChartConfig

const platformData = [
  { platform: "Facebook", comments: 7234, views: 52340, sentiment: 79 },
  { platform: "TikTok", comments: 4213, views: 28940, sentiment: 74 },
  { platform: "Instagram", comments: 1400, views: 7954, sentiment: 72 },
]

const platformBarConfig = {
  comments: { label: "Bình luận", color: "var(--chart-1)" },
  views: { label: "Lượt xem", color: "var(--chart-2)" },
} satisfies ChartConfig

const recentSessions = [
  { name: "Flash Sale Mùa Hè", platform: "Facebook", comments: 1247, views: 8432, leads: 45, sentiment: 82, date: "20/05" },
  { name: "Giới thiệu BST mới", platform: "TikTok", comments: 523, views: 3201, leads: 12, sentiment: 75, date: "20/05" },
  { name: "Thanh lý cuối tuần", platform: "Instagram", comments: 892, views: 5678, leads: 28, sentiment: 68, date: "19/05" },
  { name: "Review sản phẩm mới", platform: "Facebook", comments: 2103, views: 12450, leads: 67, sentiment: 85, date: "18/05" },
  { name: "Live Q&A khách hàng", platform: "TikTok", comments: 756, views: 4320, leads: 15, sentiment: 71, date: "17/05" },
]

// --- Main ---

export default function ReportsIndex() {
  const [period, setPeriod] = React.useState("7d")

  return (
    <AuthenticatedLayout>
      <Head title="Báo cáo" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
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
            <Select value={period} onValueChange={setPeriod}>
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
            const current = parseFloat(stat.value.replace(/,/g, ""))
            const prev = parseFloat(stat.prev.replace(/,/g, ""))
            const isUp = current >= prev
            return (
              <Card key={stat.title}>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                  <div className="text-muted-foreground">{stat.icon}</div>
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
        <Alert>
          <SparklesIcon className="size-4" />
          <AlertTitle>Gợi ý từ AI</AlertTitle>
          <AlertDescription>
            Facebook đang là nền tảng hiệu quả nhất (cảm xúc tích cực 79%). TikTok có tăng trưởng lượt xem nhanh
            nhưng tỷ lệ bình luận/lượt xem thấp hơn. Gợi ý: tăng tần suất live trên Facebook vào khung giờ 19h-21h,
            và thử hình thức hỏi đáp trên TikTok để tăng tương tác.
          </AlertDescription>
        </Alert>

        {/* Trend Chart */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUpIcon className="size-4" />
              Xu hướng theo kỳ
            </CardTitle>
            <CardDescription>Lượt xem, bình luận và leads theo ngày trong khoảng thời gian đã chọn</CardDescription>
          </CardHeader>
          <CardContent>
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
          </CardContent>
        </Card>

        <div className="grid gap-4 lg:grid-cols-2">
          {/* Platform Comparison Bar Chart */}
          <Card>
            <CardHeader>
              <CardTitle>So sánh nền tảng</CardTitle>
              <CardDescription>Bình luận và lượt xem theo nền tảng</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartContainer config={platformBarConfig} className="aspect-auto h-[220px] w-full">
                <BarChart data={platformData} layout="vertical" margin={{ left: 20 }}>
                  <CartesianGrid horizontal={false} />
                  <YAxis
                    dataKey="platform"
                    type="category"
                    tickLine={false}
                    axisLine={false}
                    width={80}
                  />
                  <XAxis type="number" hide />
                  <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                  <Bar dataKey="comments" fill="var(--color-comments)" radius={[0, 4, 4, 0]} barSize={16} />
                  <Bar dataKey="views" fill="var(--color-views)" radius={[0, 4, 4, 0]} barSize={16} />
                  <ChartLegend content={<ChartLegendContent />} />
                </BarChart>
              </ChartContainer>
              {/* Sentiment row */}
              <div className="mt-4 space-y-2 border-t pt-4">
                <p className="text-xs font-medium text-muted-foreground">Cảm xúc tích cực</p>
                {platformData.map((p) => (
                  <div key={p.platform} className="flex items-center gap-3 text-sm">
                    <span className="w-20 text-muted-foreground">{p.platform}</span>
                    <Progress value={p.sentiment} className="h-2 flex-1" />
                    <span className="w-10 text-right font-medium tabular-nums">{p.sentiment}%</span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

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
                      <span className="flex items-center justify-end gap-1"><UsersIcon className="size-3" />Leads</span>
                    </TableHead>
                    <TableHead>Cảm xúc</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recentSessions.map((s) => (
                    <TableRow key={s.name}>
                      <TableCell>
                        <div>
                          <div className="font-medium text-sm">{s.name}</div>
                          <div className="text-xs text-muted-foreground">{s.platform} · {s.date}</div>
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
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
