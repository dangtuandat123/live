import AdminLayout from "@/Layouts/AdminLayout"
import { Head, useForm, router } from "@inertiajs/react"
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  CreditCardIcon,
  GlobeIcon,
  LoaderIcon,
  CheckIcon,
  HelpCircleIcon,
} from "lucide-react"
import * as React from "react"
import { toast } from "sonner"

interface PaymentConfigData {
  id?: number
  name: string
  prefix: string | null
  suffix: string | null
  webhook_url: string | null
  method: "POST" | "GET" | "PUT"
  params_template: Record<string, any> | null
  headers_template: Record<string, any> | null
  is_active?: boolean
}

interface Props {
  config: PaymentConfigData
}

export default function AdminPayments({ config }: Props) {
  // Trạng thái chuỗi JSON cho textarea
  const [paramsJsonStr, setParamsJsonStr] = React.useState(
    config.params_template ? JSON.stringify(config.params_template, null, 2) : "{\n  \"id_user\": \"{user_id}\",\n  \"sotien\": {amount}\n}"
  )
  const [headersJsonStr, setHeadersJsonStr] = React.useState(
    config.headers_template ? JSON.stringify(config.headers_template, null, 2) : "{\n  \"Content-Type\": \"application/json\",\n  \"Accept\": \"application/json\"\n}"
  )

  const [localErrors, setLocalErrors] = React.useState<{
    params_template?: string
    headers_template?: string
  }>({})

  const { data, setData, put, processing, recentlySuccessful, errors } = useForm({
    name: config.name || "VietQR Payment Gateway",
    prefix: config.prefix || "",
    suffix: config.suffix || "",
    webhook_url: config.webhook_url || "",
    method: config.method || "POST",
    params_template: config.params_template || {},
    headers_template: config.headers_template || {},
  })

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLocalErrors({})

    let parsedParams = {}
    let parsedHeaders = {}

    // Validate & parse Params Template
    if (paramsJsonStr.trim()) {
      try {
        parsedParams = JSON.parse(paramsJsonStr)
        if (typeof parsedParams !== "object" || parsedParams === null) {
          setLocalErrors(prev => ({ ...prev, params_template: "Params Template phải là một JSON Object hợp lệ." }))
          toast.error("Vui lòng kiểm tra lại cấu hình Params Template")
          return
        }
      } catch (err: any) {
        setLocalErrors(prev => ({ ...prev, params_template: `Lỗi cú pháp JSON: ${err.message}` }))
        toast.error("Cấu hình Params Template không đúng định dạng JSON")
        return
      }
    }

    // Validate & parse Headers Template
    if (headersJsonStr.trim()) {
      try {
        parsedHeaders = JSON.parse(headersJsonStr)
        if (typeof parsedHeaders !== "object" || parsedHeaders === null) {
          setLocalErrors(prev => ({ ...prev, headers_template: "Headers Template phải là một JSON Object hợp lệ." }))
          toast.error("Vui lòng kiểm tra lại cấu hình Headers Template")
          return
        }
      } catch (err: any) {
        setLocalErrors(prev => ({ ...prev, headers_template: `Lỗi cú pháp JSON: ${err.message}` }))
        toast.error("Cấu hình Headers Template không đúng định dạng JSON")
        return
      }
    }

    // Cập nhật dữ liệu đã parse và tiến hành gửi form
    // Để chắc chắn Inertia gửi đúng dữ liệu sau khi setState (bất đồng bộ),
    // chúng ta truyền object trực tiếp vào put() hoặc cập nhật form và submit.
    // Cách an toàn nhất của Inertia useForm là truyền payload trực tiếp trong submit.
    router.put(route("admin.payments.update"), {
      ...data,
      params_template: parsedParams,
      headers_template: parsedHeaders,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Đã lưu cấu hình thanh toán thành công!")
      },
      onError: () => {
        toast.error("Có lỗi xảy ra khi lưu cấu hình.")
      }
    })
  }

  return (
    <AdminLayout>
      <Head title="Admin - Cấu hình thanh toán" />
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
                <BreadcrumbPage>Cấu hình thanh toán</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <form onSubmit={handleSubmit} className="mx-auto w-full max-w-4xl flex flex-1 flex-col gap-6 p-4 pt-0">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Cấu hình thanh toán</h1>
            <p className="text-muted-foreground">Thiết lập tham số VietQR và webhook thông báo về hệ thống VPS</p>
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

        {/* VietQR Config */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CreditCardIcon className="size-5 text-primary" />
              Cài đặt VietQR
            </CardTitle>
            <CardDescription>Cấu hình thông số nội dung chuyển khoản VietQR</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="gateway-name">Tên Cổng Thanh Toán</Label>
              <Input
                id="gateway-name"
                value={data.name}
                onChange={(e) => setData("name", e.target.value)}
                placeholder="Ví dụ: VietQR Cổng 1"
                required
              />
              {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label htmlFor="prefix">Mã Prefix (Tiền tố)</Label>
                <Input
                  id="prefix"
                  value={data.prefix || ""}
                  onChange={(e) => setData("prefix", e.target.value)}
                  placeholder="Ví dụ: TTGR"
                />
                {errors.prefix && <p className="text-xs text-destructive">{errors.prefix}</p>}
                <p className="text-xs text-muted-foreground">Mã nhận diện bắt đầu nội dung chuyển khoản.</p>
              </div>

              <div className="grid gap-2">
                <Label htmlFor="suffix">Mã Suffix (Hậu tố)</Label>
                <Input
                  id="suffix"
                  value={data.suffix || ""}
                  onChange={(e) => setData("suffix", e.target.value)}
                  placeholder="Ví dụ: NAP"
                />
                {errors.suffix && <p className="text-xs text-destructive">{errors.suffix}</p>}
                <p className="text-xs text-muted-foreground">Mã nhận diện kết thúc nội dung chuyển khoản.</p>
              </div>
            </div>

            <div className="p-3 bg-muted/50 rounded-lg border border-border/40 text-xs text-muted-foreground space-y-1">
              <span className="font-semibold text-foreground flex items-center gap-1 mb-1">
                <HelpCircleIcon className="size-3.5 text-primary" /> Giải thích cú pháp nội dung chuyển khoản:
              </span>
              <p>Nội dung chuyển khoản trên QR Code sẽ có định dạng: <code className="bg-background px-1.5 py-0.5 rounded border border-border text-foreground font-mono font-medium">{data.prefix || "PREFIX"} {"{userId}"} {data.suffix || "SUFFIX"}</code></p>
              <p>Ví dụ với User ID là <span className="font-semibold text-foreground">1132</span>: <code className="bg-background px-1.5 py-0.5 rounded border border-border text-primary font-mono font-medium">{(data.prefix || "TTGR")} 1132 {(data.suffix || "NAP")}</code></p>
            </div>
          </CardContent>
        </Card>

        {/* Webhook VPS Config */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <GlobeIcon className="size-5 text-primary" />
              Webhook Forwarding (VPS)
            </CardTitle>
            <CardDescription>Cấu hình để hệ thống tự động gọi API tới VPS của bạn khi nhận được tiền</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Label htmlFor="webhook-url">Webhook URL</Label>
              <Input
                id="webhook-url"
                type="url"
                value={data.webhook_url || ""}
                onChange={(e) => setData("webhook_url", e.target.value)}
                placeholder="https://vps-cua-ban.com/api/payment-callback"
              />
              {errors.webhook_url && <p className="text-xs text-destructive">{errors.webhook_url}</p>}
            </div>

            <div className="grid gap-2">
              <Label htmlFor="method">HTTP Method</Label>
              <Select
                value={data.method}
                onValueChange={(val: "POST" | "GET" | "PUT") => setData("method", val)}
              >
                <SelectTrigger id="method" className="w-[180px]">
                  <SelectValue placeholder="Chọn Method" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="POST">POST</SelectItem>
                  <SelectItem value="GET">GET</SelectItem>
                  <SelectItem value="PUT">PUT</SelectItem>
                </SelectContent>
              </Select>
              {errors.method && <p className="text-xs text-destructive">{errors.method}</p>}
            </div>

            <div className="grid gap-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="params-template">Params Template (JSON)</Label>
                <span className="text-[10px] text-muted-foreground font-mono bg-muted px-1 py-0.5 rounded">Placeholders: {"{user_id}"}, {"{amount}"}, {"{transaction_id}"}</span>
              </div>
              <textarea
                id="params-template"
                value={paramsJsonStr}
                onChange={(e) => setParamsJsonStr(e.target.value)}
                rows={6}
                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 font-mono"
                placeholder='{\n  "id_user": "{user_id}",\n  "sotien": {amount}\n}'
              />
              {(errors.params_template || localErrors.params_template) && (
                <p className="text-xs text-destructive">{errors.params_template || localErrors.params_template}</p>
              )}
            </div>

            <div className="grid gap-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="headers-template">Headers Template (JSON)</Label>
                <span className="text-[10px] text-muted-foreground font-mono bg-muted px-1 py-0.5 rounded">Ví dụ: {"{\"X-API-Key\": \"YOUR_KEY\"}"}</span>
              </div>
              <textarea
                id="headers-template"
                value={headersJsonStr}
                onChange={(e) => setHeadersJsonStr(e.target.value)}
                rows={4}
                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 font-mono"
                placeholder='{\n  "Content-Type": "application/json",\n  "Accept": "application/json"\n}'
              />
              {(errors.headers_template || localErrors.headers_template) && (
                <p className="text-xs text-destructive">{errors.headers_template || localErrors.headers_template}</p>
              )}
            </div>
          </CardContent>
        </Card>
      </form>
    </AdminLayout>
  )
}
