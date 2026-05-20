import AdminLayout from "@/Layouts/AdminLayout"
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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import {
  ServerIcon,
  ShieldCheckIcon,
  BellIcon,
} from "lucide-react"

export default function AdminSettings() {
  return (
    <AdminLayout>
      <Head title="Admin - Cài đặt hệ thống" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
        <div className="flex items-center gap-2 px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-auto" />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href={route("admin.dashboard")}>Admin</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Cài đặt hệ thống</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-4 pt-0">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Cài đặt hệ thống</h1>
          <p className="text-muted-foreground">Quản lý cấu hình toàn hệ thống</p>
        </div>

        {/* General Settings */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ServerIcon className="size-5" />
              Cài đặt chung
            </CardTitle>
            <CardDescription>Cấu hình cơ bản của hệ thống</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="app-name">Tên ứng dụng</Label>
              <Input id="app-name" defaultValue="LiveStream AI" />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="app-url">URL ứng dụng</Label>
              <Input id="app-url" defaultValue="https://livestream.ai" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Cho phép đăng ký mới</Label>
                <p className="text-xs text-muted-foreground">Cho phép người dùng mới tự đăng ký tài khoản</p>
              </div>
              <Switch defaultChecked />
            </div>
            <Button>Lưu thay đổi</Button>
          </CardContent>
        </Card>

        {/* Security */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ShieldCheckIcon className="size-5" />
              Bảo mật
            </CardTitle>
            <CardDescription>Cấu hình bảo mật và xác thực</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <Label>Bắt buộc xác thực email</Label>
                <p className="text-xs text-muted-foreground">User phải xác thực email trước khi sử dụng</p>
              </div>
              <Switch defaultChecked />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Two-Factor Authentication</Label>
                <p className="text-xs text-muted-foreground">Bật xác thực 2 lớp cho toàn bộ user</p>
              </div>
              <Switch />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="max-sessions">Giới hạn phiên live/user/tháng (Free)</Label>
              <Input id="max-sessions" type="number" defaultValue="5" className="w-32" />
            </div>
          </CardContent>
        </Card>

        {/* Notifications */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BellIcon className="size-5" />
              Thông báo
            </CardTitle>
            <CardDescription>Cấu hình thông báo hệ thống</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <Label>Thông báo user mới đăng ký</Label>
                <p className="text-xs text-muted-foreground">Gửi email cho admin khi có user mới</p>
              </div>
              <Switch defaultChecked />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Báo cáo hàng tuần</Label>
                <p className="text-xs text-muted-foreground">Gửi email tổng kết hoạt động hàng tuần</p>
              </div>
              <Switch defaultChecked />
            </div>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  )
}
