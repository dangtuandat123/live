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
} from "lucide-react"

const platforms = [
  { name: "TikTok", connected: true, account: "@shopthoitrang_abc" },
]

export default function SettingsIndex() {
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
                <BreadcrumbPage>Cài đặt</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-6 p-4 pt-0 max-w-3xl">
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
        <Card>
          <CardHeader>
            <CardTitle>Tùy chỉnh AI</CardTitle>
            <CardDescription>Cấu hình cách AI phân tích bình luận</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label>Ngôn ngữ phân tích</Label>
              <Select defaultValue="vi">
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
              <Switch defaultChecked />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Trích xuất địa chỉ tự động</Label>
                <p className="text-xs text-muted-foreground">AI tự động tìm địa chỉ giao hàng từ bình luận</p>
              </div>
              <Switch defaultChecked />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <Label>Cảnh báo realtime</Label>
                <p className="text-xs text-muted-foreground">Nhận cảnh báo khi có nhiều bình luận tiêu cực hoặc câu hỏi chưa trả lời</p>
              </div>
              <Switch defaultChecked />
            </div>
          </CardContent>
        </Card>

        {/* Từ khóa chốt đơn */}
        <Card>
          <CardHeader>
            <CardTitle>Từ khóa chốt đơn mặc định</CardTitle>
            <CardDescription>
              Các từ khóa này sẽ được áp dụng mặc định cho mọi phiên live mới
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex flex-wrap gap-1.5">
              {["mua", "chốt", "ship", "giá", "size", "đặt hàng", "order", "lấy", "giao"].map((kw) => (
                <Badge key={kw} variant="secondary">{kw}</Badge>
              ))}
            </div>
            <div className="flex gap-2">
              <Input placeholder="Thêm từ khóa mới..." className="max-w-xs" />
              <Button variant="secondary">Thêm</Button>
            </div>
          </CardContent>
        </Card>

        {/* Profile */}
        <Card>
          <CardHeader>
            <CardTitle>Thông tin tài khoản</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="name">Tên</Label>
              <Input id="name" defaultValue="Nguyễn Văn A" />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" defaultValue="seller@example.com" />
            </div>
            <Button>Lưu thay đổi</Button>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
