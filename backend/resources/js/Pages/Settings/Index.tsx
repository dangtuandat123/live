import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, useForm, usePage } from "@inertiajs/react"
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  LinkIcon,
  CheckCircle2Icon,
  XCircleIcon,
  CrownIcon,
  SparklesIcon,
  CheckIcon,
  LoaderIcon,
} from "lucide-react"
import type { PageProps } from "@/types"

interface SettingsData {
  ai_language: string
  auto_extract_phone: boolean
  auto_extract_address: boolean
  realtime_alerts: boolean
}

interface Props extends PageProps {
  settings: SettingsData
}

const platforms = [
  { name: "TikTok", connected: true, account: "@shopthoitrang_abc" },
]

export default function SettingsIndex({ settings }: Props) {
  const { auth } = usePage<Props>().props

  // AI Settings Form
  const aiForm = useForm({
    ai_language: settings.ai_language,
    auto_extract_phone: settings.auto_extract_phone,
    auto_extract_address: settings.auto_extract_address,
    realtime_alerts: settings.realtime_alerts,
  })

  // Profile Form
  const profileForm = useForm({
    name: auth.user.name,
    email: auth.user.email,
  })

  function submitAiSettings(e: React.FormEvent) {
    e.preventDefault()
    aiForm.put(route("settings.update-ai"), { preserveScroll: true })
  }

  function submitProfile(e: React.FormEvent) {
    e.preventDefault()
    profileForm.put(route("settings.update-profile"), { preserveScroll: true })
  }

  return (
    <AuthenticatedLayout>
      <Head title="Cài đặt" />
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
                <BreadcrumbPage>Cài đặt</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-4 pt-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Cài đặt</h1>
          <p className="text-muted-foreground">Quản lý tài khoản, kết nối nền tảng và tùy chỉnh AI</p>
        </div>

        {/* Subscription */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CrownIcon className="size-5 text-yellow-500" />
              Gói đăng ký
            </CardTitle>
            <CardDescription>Quản lý gói sử dụng của bạn</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-semibold">Gói Pro</span>
                  <Badge>Đang sử dụng</Badge>
                </div>
                <p className="text-sm text-muted-foreground mt-1">
                  30 phiên live/tháng · Phân tích cảm xúc · Trích xuất SĐT · Xuất PDF
                </p>
              </div>
              <div className="text-right">
                <div className="text-2xl font-bold">299K</div>
                <div className="text-xs text-muted-foreground">VNĐ/tháng</div>
              </div>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">Phiên live đã dùng tháng này</span>
              <span className="font-medium">18 / 30 phiên</span>
            </div>
            <Button variant="outline" className="gap-2">
              <SparklesIcon className="size-4" />
              Nâng cấp lên Business
            </Button>
          </CardContent>
        </Card>

        {/* Platform Connections */}
        <Card>
          <CardHeader>
            <CardTitle>Kết nối nền tảng</CardTitle>
            <CardDescription>Kết nối tài khoản bán hàng để AI thu thập dữ liệu</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {platforms.map((p) => (
              <div key={p.name} className="flex items-center justify-between rounded-lg border p-4">
                <div className="flex items-center gap-3">
                  {p.connected ? (
                    <CheckCircle2Icon className="size-5 text-green-500" />
                  ) : (
                    <XCircleIcon className="size-5 text-muted-foreground" />
                  )}
                  <div>
                    <div className="font-medium">{p.name}</div>
                    {p.connected ? (
                      <div className="text-sm text-muted-foreground">{p.account}</div>
                    ) : (
                      <div className="text-sm text-muted-foreground">Chưa kết nối</div>
                    )}
                  </div>
                </div>
                <Button variant={p.connected ? "outline" : "default"} size="sm">
                  {p.connected ? "Ngắt kết nối" : (
                    <>
                      <LinkIcon className="mr-1.5 size-3.5" />
                      Kết nối
                    </>
                  )}
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>

        {/* AI Preferences */}
        <form onSubmit={submitAiSettings}>
          <Card>
            <CardHeader>
              <CardTitle>Tùy chỉnh AI</CardTitle>
              <CardDescription>Cấu hình cách AI phân tích bình luận</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2">
                <Label>Ngôn ngữ phân tích</Label>
                <Select
                  value={aiForm.data.ai_language}
                  onValueChange={(value) => aiForm.setData("ai_language", value)}
                >
                  <SelectTrigger className="w-[200px]">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="vi">Tiếng Việt</SelectItem>
                    <SelectItem value="en">English</SelectItem>
                    <SelectItem value="auto">Tự động nhận diện</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <Label>Trích xuất SĐT tự động</Label>
                  <p className="text-xs text-muted-foreground">AI tự động tìm và trích xuất số điện thoại từ bình luận</p>
                </div>
                <Switch
                  checked={aiForm.data.auto_extract_phone}
                  onCheckedChange={(checked) => aiForm.setData("auto_extract_phone", checked)}
                />
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <Label>Trích xuất địa chỉ tự động</Label>
                  <p className="text-xs text-muted-foreground">AI tự động tìm địa chỉ giao hàng từ bình luận</p>
                </div>
                <Switch
                  checked={aiForm.data.auto_extract_address}
                  onCheckedChange={(checked) => aiForm.setData("auto_extract_address", checked)}
                />
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <Label>Cảnh báo realtime</Label>
                  <p className="text-xs text-muted-foreground">Nhận cảnh báo khi có nhiều bình luận tiêu cực hoặc câu hỏi chưa trả lời</p>
                </div>
                <Switch
                  checked={aiForm.data.realtime_alerts}
                  onCheckedChange={(checked) => aiForm.setData("realtime_alerts", checked)}
                />
              </div>
              <Button type="submit" disabled={aiForm.processing} className="gap-2">
                {aiForm.processing ? (
                  <LoaderIcon className="size-4 animate-spin" />
                ) : aiForm.recentlySuccessful ? (
                  <CheckIcon className="size-4" />
                ) : null}
                {aiForm.recentlySuccessful ? "Đã lưu" : "Lưu cài đặt AI"}
              </Button>
              {aiForm.errors.ai_language && (
                <p className="text-sm text-destructive">{aiForm.errors.ai_language}</p>
              )}
            </CardContent>
          </Card>
        </form>

        {/* Profile */}
        <form onSubmit={submitProfile}>
          <Card>
            <CardHeader>
              <CardTitle>Thông tin tài khoản</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="name">Tên</Label>
                <Input
                  id="name"
                  value={profileForm.data.name}
                  onChange={(e) => profileForm.setData("name", e.target.value)}
                />
                {profileForm.errors.name && (
                  <p className="text-sm text-destructive">{profileForm.errors.name}</p>
                )}
              </div>
              <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={profileForm.data.email}
                  onChange={(e) => profileForm.setData("email", e.target.value)}
                />
                {profileForm.errors.email && (
                  <p className="text-sm text-destructive">{profileForm.errors.email}</p>
                )}
              </div>
              <Button type="submit" disabled={profileForm.processing} className="gap-2">
                {profileForm.processing ? (
                  <LoaderIcon className="size-4 animate-spin" />
                ) : profileForm.recentlySuccessful ? (
                  <CheckIcon className="size-4" />
                ) : null}
                {profileForm.recentlySuccessful ? "Đã lưu" : "Lưu thay đổi"}
              </Button>
            </CardContent>
          </Card>
        </form>
      </div>
    </AuthenticatedLayout>
  )
}
