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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
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
import { Progress } from "@/components/ui/progress"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import {
  TrendingUpIcon,
  TrendingDownIcon,
  VideoIcon,
  MessageSquareIcon,
  EyeIcon,
  SmileIcon,
  SparklesIcon,
  DownloadIcon,
} from "lucide-react"
import * as React from "react"

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

const platformStats = [
  {
    platform: "Facebook",
    sessions: 12,
    comments: 7234,
    views: 52340,
    sentiment: 79,
    topProduct: "Áo thun basic cotton",
  },
  {
    platform: "TikTok",
    sessions: 8,
    comments: 4213,
    views: 28940,
    sentiment: 74,
    topProduct: "Váy hoa mùa hè",
  },
  {
    platform: "Instagram",
    sessions: 4,
    comments: 1400,
    views: 7954,
    sentiment: 72,
    topProduct: "Túi xách da PU",
  },
]

const recentSessions = [
  { name: "Flash Sale Mùa Hè", platform: "Facebook", comments: 1247, views: 8432, sentiment: 82, date: "20/05" },
  { name: "Giới thiệu BST mới", platform: "TikTok", comments: 523, views: 3201, sentiment: 75, date: "20/05" },
  { name: "Thanh lý cuối tuần", platform: "Instagram", comments: 892, views: 5678, sentiment: 68, date: "19/05" },
  { name: "Review sản phẩm mới", platform: "Facebook", comments: 2103, views: 12450, sentiment: 85, date: "18/05" },
  { name: "Live Q&A khách hàng", platform: "TikTok", comments: 756, views: 4320, sentiment: 71, date: "17/05" },
]

export default function ReportsIndex() {
  const [period, setPeriod] = React.useState("7d")

  return (
    <AuthenticatedLayout>
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

        <div className="grid gap-4 lg:grid-cols-2">
          {/* Platform Comparison */}
          <Card>
            <CardHeader>
              <CardTitle>So sánh nền tảng</CardTitle>
              <CardDescription>Hiệu suất theo từng nền tảng trong khoảng thời gian đã chọn</CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nền tảng</TableHead>
                    <TableHead className="text-right">Phiên</TableHead>
                    <TableHead className="text-right">Bình luận</TableHead>
                    <TableHead className="text-right">Lượt xem</TableHead>
                    <TableHead>Cảm xúc</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {platformStats.map((p) => (
                    <TableRow key={p.platform}>
                      <TableCell className="font-medium">{p.platform}</TableCell>
                      <TableCell className="text-right">{p.sessions}</TableCell>
                      <TableCell className="text-right">{p.comments.toLocaleString()}</TableCell>
                      <TableCell className="text-right">{p.views.toLocaleString()}</TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Progress value={p.sentiment} className="h-2 w-16" />
                          <span className="text-xs">{p.sentiment}%</span>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          {/* Top Sessions */}
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
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Progress value={s.sentiment} className="h-2 w-16" />
                          <span className="text-xs">{s.sentiment}%</span>
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
