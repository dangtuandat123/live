import AdminLayout from "@/Layouts/AdminLayout"
import { Head } from "@inertiajs/react"
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
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { Bar, BarChart, CartesianGrid, XAxis } from "recharts"
import {
  UsersIcon,
  VideoIcon,
  DollarSignIcon,
  TrendingUpIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  ActivityIcon,
} from "lucide-react"
import * as React from "react"

// --- Types ---

interface DashboardStat {
  title: string
  value: string
  change: string
  trend: "up" | "down"
}

interface RevenueDataPoint {
  month: string
  revenue: number
  users: number
}

interface RecentUser {
  id: number
  name: string
  email: string
  role: string
  plan: string
  date: string
  sessions: number
}

interface Props {
  stats: DashboardStat[]
  revenueData: RevenueDataPoint[]
  recentUsers: RecentUser[]
}

const revenueConfig = {
  revenue: {
    label: "Doanh thu",
    color: "var(--chart-1)",
  },
  users: {
    label: "Người dùng",
    color: "var(--chart-2)",
  },
} satisfies ChartConfig

function PlanBadge({ plan }: { plan: string }) {
  const variant = plan === "Business" ? "default" : plan === "Pro" ? "secondary" : "outline"
  return <Badge variant={variant}>{plan}</Badge>
}

// --- Main ---

export default function AdminDashboard({ stats = [], revenueData = [], recentUsers = [] }: Props) {
  const getStatIcon = (title: string) => {
    if (title.toLowerCase().includes("người dùng")) return <UsersIcon className="size-4" />
    if (title.toLowerCase().includes("phiên live")) return <VideoIcon className="size-4" />
    if (title.toLowerCase().includes("doanh thu")) return <DollarSignIcon className="size-4" />
    return <ActivityIcon className="size-4" />
  }

  return (
    <AdminLayout>
      <Head title="Admin - Tổng quan" />
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
                <BreadcrumbPage>Admin · Tổng quan</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-4">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Tổng quan hệ thống</h1>
          <p className="text-muted-foreground">
            Thống kê toàn bộ hoạt động trên nền tảng LiveStream AI
          </p>
        </div>

        {/* KPI Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {stats.map((stat) => (
            <Card key={stat.title}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                  {stat.title}
                </CardTitle>
                <div className="text-muted-foreground">{getStatIcon(stat.title)}</div>
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

        {/* Revenue Chart */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUpIcon className="size-4" />
              Doanh thu & Tăng trưởng
            </CardTitle>
            <CardDescription>Doanh thu và số lượng user theo tháng (VNĐ)</CardDescription>
          </CardHeader>
          <CardContent>
            {revenueData.length > 0 ? (
              <ChartContainer config={revenueConfig} className="aspect-auto h-[280px] w-full">
                <BarChart data={revenueData}>
                  <CartesianGrid vertical={false} />
                  <XAxis
                    dataKey="month"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                  />
                  <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent indicator="dashed" />}
                  />
                  <Bar dataKey="revenue" fill="var(--color-revenue)" radius={4} />
                </BarChart>
              </ChartContainer>
            ) : (
              <div className="flex h-[280px] items-center justify-center text-muted-foreground text-sm">
                Chưa có dữ liệu tăng trưởng
              </div>
            )}
          </CardContent>
        </Card>

        {/* Recent Users Table */}
        <Card>
          <CardHeader>
            <CardTitle>Người dùng gần đây</CardTitle>
            <CardDescription>Danh sách người dùng mới đăng ký</CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tên</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Vai trò</TableHead>
                  <TableHead>Gói</TableHead>
                  <TableHead className="text-right">Phiên live</TableHead>
                  <TableHead>Ngày đăng ký</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentUsers.length > 0 ? (
                  recentUsers.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell className="font-medium">{user.name}</TableCell>
                      <TableCell className="text-muted-foreground">{user.email}</TableCell>
                      <TableCell>
                        <Badge variant={user.role === "admin" ? "destructive" : "outline"}>
                          {user.role === "admin" ? "Admin" : "User"}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <PlanBadge plan={user.plan} />
                      </TableCell>
                      <TableCell className="text-right">{user.sessions}</TableCell>
                      <TableCell className="text-muted-foreground">{user.date}</TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-6 text-muted-foreground">
                      Chưa có người dùng mới đăng ký
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  )
}
