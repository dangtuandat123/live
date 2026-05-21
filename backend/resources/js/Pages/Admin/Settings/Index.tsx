import AdminLayout from "@/Layouts/AdminLayout"
import { Head, useForm } from "@inertiajs/react"
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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import {
  ServerIcon,
  ShieldCheckIcon,
  BellIcon,
  LoaderIcon,
  CheckIcon,
} from "lucide-react"
import * as React from "react"

interface SystemSettings {
  app_name: string
  app_url: string
  allow_registration: boolean
  require_email_verification: boolean
  enable_two_factor: boolean
  max_free_sessions: number
  notify_new_user: boolean
  weekly_report: boolean
}

interface Props {
  settings: SystemSettings
}

export default function AdminSettings({ settings }: Props) {
  const { data, setData, put, processing, recentlySuccessful, errors } = useForm({
    app_name: settings.app_name || "",
    app_url: settings.app_url || "",
    allow_registration: settings.allow_registration ?? true,
    require_email_verification: settings.require_email_verification ?? true,
    enable_two_factor: settings.enable_two_factor ?? false,
    max_free_sessions: settings.max_free_sessions ?? 5,
    notify_new_user: settings.notify_new_user ?? true,
    weekly_report: settings.weekly_report ?? true,
  })

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    put(route("admin.settings.update"), {
      preserveScroll: true,
    })
  }

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

      <form onSubmit={handleSubmit} className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-4 pt-0">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Cài đặt hệ thống</h1>
            <p className="text-muted-foreground">Quản lý cấu hình toàn hệ thống</p>
          </div>
          <Button type="submit" disabled={processing} className="gap-2">
            {processing ? (
              <LoaderIcon className="size-4 animate-spin" />
            ) : recentlySuccessful ? (
              <CheckIcon className="size-4" />
            ) : null}
            {recentlySuccessful ? "Đã lưu" : "Lưu cấu hình"}
          </Button>
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
              <Input
                id="app-name"
                value={data.app_name}
                onChange={(e) => setData("app_name", e.target.value)}
              />
              {errors.app_name && (
                <p className="text-xs text-destructive">{errors.app_name}</p>
              )}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="app-url">URL ứng dụng</Label>
              <Input
                id="app-url"
                value={data.app_url}
                onChange={(e) => setData("app_url", e.target.value)}
              />
              {errors.app_url && (
                <p className="text-xs text-destructive">{errors.app_url}</p>
              )}
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Cho phép đăng ký mới</Label>
                <p className="text-xs text-muted-foreground">Cho phép người dùng mới tự đăng ký tài khoản</p>
              </div>
              <Switch
                checked={data.allow_registration}
                onCheckedChange={(checked) => setData("allow_registration", checked)}
              />
            </div>
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
              <Switch
                checked={data.require_email_verification}
                onCheckedChange={(checked) => setData("require_email_verification", checked)}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Two-Factor Authentication</Label>
                <p className="text-xs text-muted-foreground">Bật xác thực 2 lớp cho toàn bộ user</p>
              </div>
              <Switch
                checked={data.enable_two_factor}
                onCheckedChange={(checked) => setData("enable_two_factor", checked)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="max-sessions">Giới hạn phiên live/user/tháng (Free)</Label>
              <Input
                id="max-sessions"
                type="number"
                value={data.max_free_sessions}
                onChange={(e) => setData("max_free_sessions", parseInt(e.target.value) || 0)}
                className="w-32"
              />
              {errors.max_free_sessions && (
                <p className="text-xs text-destructive">{errors.max_free_sessions}</p>
              )}
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
              <Switch
                checked={data.notify_new_user}
                onCheckedChange={(checked) => setData("notify_new_user", checked)}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Báo cáo hàng tuần</Label>
                <p className="text-xs text-muted-foreground">Gửi email tổng kết hoạt động hàng tuần</p>
              </div>
              <Switch
                checked={data.weekly_report}
                onCheckedChange={(checked) => setData("weekly_report", checked)}
              />
            </div>
          </CardContent>
        </Card>
      </form>
    </AdminLayout>
  )
}
