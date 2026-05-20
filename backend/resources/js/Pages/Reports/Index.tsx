import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, Link } from "@inertiajs/react"
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
  leads: { label: "KH tiềm năng", color: "var(--chart-3)" },
} satisfies ChartConfig

const recentSessions = [
  { id: "1", name: "Flash Sale Mùa Hè", comments: 1247, views: 8432, leads: 45, sentiment: 82, date: "20/05" },
  { id: "2", name: "Giới thiệu BST mới", comments: 523, views: 3201, leads: 12, sentiment: 75, date: "20/05" },
  { id: "3", name: "Thanh lý cuối tuần", comments: 892, views: 5678, leads: 28, sentiment: 68, date: "19/05" },
  { id: "4", name: "Review sản phẩm mới", comments: 2103, views: 12450, leads: 67, sentiment: 85, date: "18/05" },
  { id: "5", name: "Live Q&A khách hàng", comments: 756, views: 4320, leads: 15, sentiment: 71, date: "17/05" },
]

const sessionCompareData = [
  { name: "Review SP mới", views: 12450, comments: 2103 },
  { name: "Flash Sale", views: 8432, comments: 1247 },
  { name: "Thanh lý", views: 5678, comments: 892 },
  { name: "Q&A KH", views: 4320, comments: 756 },
  { name: "BST mới", views: 3201, comments: 523 },
]

const sessionBarConfig = {
  views: { label: "Lượt xem", color: "var(--chart-1)" },
  comments: { label: "Bình luận", color: "var(--chart-2)" },
} satisfies ChartConfig

const hotKeywords = [
  { keyword: "giá bao nhiêu", count: 487, trend: "up" as const },
  { keyword: "còn hàng không", count: 342, trend: "up" as const },
  { keyword: "ship về HN", count: 256, trend: "down" as const },
  { keyword: "có size L không", count: 198, trend: "up" as const },
  { keyword: "mua 2 giảm không", count: 134, trend: "down" as const },
  { keyword: "chất liệu gì", count: 121, trend: "up" as const },
  { keyword: "giao hàng bao lâu", count: 98, trend: "up" as const },
  { keyword: "màu khác không", count: 87, trend: "down" as const },
  { keyword: "freeship không", count: 76, trend: "up" as const },
  { keyword: "đổi trả được không", count: 65, trend: "down" as const },
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
            Khung giờ 19h-21h đang cho hiệu suất tốt nhất (cảm xúc tích cực 85%). Các phiên có hình thức hỏi đáp
            tăng tương tác gấp 2.3 lần so với phiên thường. Gợi ý: tập trung live vào khung giờ vàng,
            và lồng thêm phần Q&A để tăng tỷ lệ bình luận.
          </AlertDescription>
        </Alert>

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

        {/* Bottom Row: Session Compare + Keywords */}
        <div className="grid gap-4 lg:grid-cols-2">
          {/* Session Comparison */}
          <Card>
            <CardHeader>
              <CardTitle>So sánh phiên Live</CardTitle>
              <CardDescription>Top 5 phiên theo lượt xem và bình luận</CardDescription>
            </CardHeader>
            <CardContent>
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
                  {hotKeywords.map((item, i) => (
                    <TableRow key={item.keyword}>
                      <TableCell className="font-medium text-muted-foreground">{i + 1}</TableCell>
                      <TableCell>
                        <span className="font-medium">"{item.keyword}"</span>
                      </TableCell>
                      <TableCell className="text-right tabular-nums">{item.count}</TableCell>
                      <TableCell className="text-right">
                        {item.trend === "up" ? (
                          <ArrowUpIcon className="ml-auto size-4 text-green-500" />
                        ) : (
                          <ArrowDownIcon className="ml-auto size-4 text-red-500" />
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
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
                {recentSessions.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell>
                      <div>
                        <div className="font-medium text-sm">{s.name}</div>
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
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
