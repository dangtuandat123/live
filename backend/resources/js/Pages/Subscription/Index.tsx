import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head, router } from "@inertiajs/react"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  CheckIcon,
  CreditCardIcon,
  SparklesIcon,
  ArrowRightIcon,
  CopyIcon,
  CheckCircle2Icon,
  Loader2Icon,
  AlertCircleIcon,
  InfoIcon,
} from "lucide-react"
import * as React from "react"
import { toast } from "sonner"
import axios from "axios"

interface SubscriptionPackage {
  id: number
  name: string
  price: number
  duration_days: number
  features: string[] | null
}

interface ActiveSubscription {
  package_id: number
  package_name: string
  expires_at: string
}

interface Props {
  packages: SubscriptionPackage[]
  activeSubscription: ActiveSubscription | null
  auth: {
    user: {
      id: number
      name: string
      email: string
    }
  }
}

export default function SubscriptionIndex({ packages = [], activeSubscription, auth }: Props) {
  const [selectedPkg, setSelectedPkg] = React.useState<SubscriptionPackage | null>(null)
  const [isCheckoutOpen, setIsCheckoutOpen] = React.useState(false)
  const [loadingCheckout, setLoadingCheckout] = React.useState(false)
  const [checkoutData, setCheckoutData] = React.useState<{
    transaction_id: string
    vietqr_url: string | null
  } | null>(null)

  const [copied, setCopied] = React.useState(false)
  const [isCheckingPayment, setIsCheckingPayment] = React.useState(false)

  React.useEffect(() => {
    let intervalId: any;

    if (isCheckoutOpen && selectedPkg && checkoutData?.vietqr_url) {
      intervalId = setInterval(async () => {
        try {
          const response = await axios.get("/api/subscription/status")
          const { active, package_id } = response.data

          if (active && package_id === selectedPkg.id) {
            setIsCheckoutOpen(false)
            toast.success(`Đã kích hoạt gói ${selectedPkg.name} thành công!`)
            router.reload({ only: ["activeSubscription"] })
          }
        } catch (error) {
          console.error("Error polling subscription status:", error)
        }
      }, 5000)
    }

    return () => {
      if (intervalId) {
        clearInterval(intervalId)
      }
    }
  }, [isCheckoutOpen, selectedPkg, checkoutData])

  // Định dạng số tiền
  function formatMoney(amount: number) {
    if (amount === 0) return "Miễn phí"
    return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(amount)
  }

  // Xử lý khi nhấn nút Đăng ký gói
  async function handleSelectPackage(pkg: SubscriptionPackage) {
    setSelectedPkg(pkg)
    setLoadingCheckout(true)
    setCheckoutData(null)

    try {
      // Gọi API tạo transaction & checkout
      const response = await axios.post("/api/subscription/checkout", {
        package_id: pkg.id
      })

      const data = response.data
      setCheckoutData(data)

      if (pkg.price === 0 || !data.vietqr_url) {
        // Gói Free kích hoạt ngay lập tức
        toast.success(`Đã kích hoạt gói ${pkg.name} thành công!`)
        // Tải lại trang để cập nhật thông tin activeSubscription
        router.reload({ only: ["activeSubscription"] })
      } else {
        // Mở modal thanh toán với QR
        setIsCheckoutOpen(true)
      }
    } catch (err: any) {
      console.error(err)
      const msg = err.response?.data?.message || "Không thể khởi tạo thanh toán. Vui lòng thử lại sau."
      toast.error(msg)
    } finally {
      setLoadingCheckout(false)
    }
  }

  // Nội dung chuyển khoản
  const transferContent = React.useMemo(() => {
    if (!checkoutData || !selectedPkg) return ""
    // Trích xuất addInfo từ vietqr_url để hiển thị chính xác cho user copy
    try {
      const url = new URL(checkoutData.vietqr_url || "")
      const addInfo = url.searchParams.get("addInfo")
      return addInfo ? decodeURIComponent(addInfo) : `TTGR ${auth.user.id} NAP`
    } catch {
      return `TTGR ${auth.user.id} NAP`
    }
  }, [checkoutData, selectedPkg, auth.user.id])

  // Copy nội dung chuyển khoản
  function handleCopyContent() {
    if (!transferContent) return
    navigator.clipboard.writeText(transferContent)
    setCopied(true)
    toast.success("Đã sao chép nội dung chuyển khoản!")
    setTimeout(() => setCopied(false), 2000)
  }

  // Tải lại trang sau khi thanh toán xong
  async function handleConfirmPaid() {
    if (!selectedPkg) return
    setIsCheckingPayment(true)
    try {
      const response = await axios.get("/api/subscription/status")
      const { active, package_id } = response.data

      if (active && package_id === selectedPkg.id) {
        setIsCheckoutOpen(false)
        toast.success(`Đã kích hoạt gói ${selectedPkg.name} thành công!`)
        router.reload({ only: ["activeSubscription"] })
      } else {
        toast.warning("Hệ thống chưa nhận được thanh toán. Vui lòng đợi trong giây lát hoặc kiểm tra lại thông tin chuyển khoản.")
      }
    } catch (err: any) {
      console.error(err)
      toast.error("Không thể kết nối đến hệ thống kiểm tra thanh toán.")
    } finally {
      setIsCheckingPayment(false)
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Gói dịch vụ & Đăng ký" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
        <div className="flex items-center gap-2 px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator orientation="vertical" className="mr-2 data-vertical:h-4 data-vertical:self-auto" />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <BreadcrumbPage>Gói dịch vụ</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-6 p-6 max-w-6xl mx-auto w-full">
        {/* Tiêu đề */}
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-primary to-primary/70 bg-clip-text text-transparent">Gói dịch vụ & Đăng ký</h1>
          <p className="text-muted-foreground mt-1">Nâng cấp tài khoản để sử dụng đầy đủ các tính năng phân tích Livestream cao cấp bằng AI</p>
        </div>

        {/* Trạng thái gói hiện tại */}
        <Card className="border border-primary/20 bg-primary/5 shadow-md relative overflow-hidden backdrop-blur-sm">
          <div className="absolute top-0 right-0 p-4 opacity-10">
            <SparklesIcon className="size-24 text-primary" />
          </div>
          <CardContent className="p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="space-y-1">
              <span className="text-xs font-semibold text-primary uppercase tracking-wider">Gói đăng ký hiện tại</span>
              <div className="flex items-center gap-2">
                <h3 className="text-2xl font-bold text-foreground">
                  {activeSubscription ? activeSubscription.package_name : "Free (Mặc định)"}
                </h3>
                <Badge variant={activeSubscription ? "default" : "outline"} className={activeSubscription ? "bg-primary text-primary-foreground" : "text-muted-foreground"}>
                  {activeSubscription ? "Hoạt động" : "Miễn phí"}
                </Badge>
              </div>
              <p className="text-sm text-muted-foreground">
                {activeSubscription ? (
                  <>Hết hạn vào: <span className="font-semibold text-foreground">{activeSubscription.expires_at}</span></>
                ) : (
                  "Tài khoản đang sử dụng các tính năng cơ bản của gói Free."
                )}
              </p>
            </div>
            
            {activeSubscription && (
              <div className="flex items-center gap-2 text-xs text-muted-foreground bg-background/50 px-3 py-2 rounded-lg border border-border/40">
                <InfoIcon className="size-4 text-primary shrink-0" />
                <span>Gói dịch vụ sẽ tự động hết hạn, bạn có thể gia hạn bất cứ lúc nào bằng cách mua thêm gói.</span>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Bảng giá (Pricing Cards) */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
          {packages.map((pkg) => {
            const isCurrent = activeSubscription
              ? activeSubscription.package_id === pkg.id
              : pkg.price === 0;

            const isFree = pkg.price === 0;

            return (
              <Card
                key={pkg.id}
                className={`flex flex-col transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl border ${
                  isCurrent
                    ? "border-primary shadow-lg ring-1 ring-primary/20 bg-background"
                    : "border-border/60 shadow-sm hover:border-primary/40 bg-background/60"
                }`}
              >
                <CardHeader className="p-6 pb-4">
                  {isCurrent && (
                    <Badge className="w-fit mb-2 bg-primary text-primary-foreground hover:bg-primary/95">Gói hiện tại</Badge>
                  )}
                  <CardTitle className="text-xl font-bold tracking-tight">{pkg.name}</CardTitle>
                  <CardDescription>Hiệu lực trong {pkg.duration_days} ngày</CardDescription>
                </CardHeader>
                <CardContent className="p-6 pt-0 flex-1 flex flex-col gap-6">
                  {/* Giá tiền */}
                  <div className="flex items-baseline text-foreground">
                    <span className="text-3xl font-extrabold tracking-tight">
                      {isFree ? "0đ" : formatMoney(pkg.price).replace("₫", "")}
                    </span>
                    <span className="text-sm font-semibold text-muted-foreground ml-1">
                      {isFree ? "" : "đ"} / {pkg.duration_days} ngày
                    </span>
                  </div>

                  {/* Tính năng */}
                  <div className="space-y-2.5 flex-1">
                    <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tính năng đi kèm:</span>
                    <ul className="space-y-2 text-sm text-muted-foreground">
                      {pkg.features && pkg.features.length > 0 ? (
                        pkg.features.map((feat, idx) => (
                          <li key={idx} className="flex items-start gap-2">
                            <CheckIcon className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            <span>{feat}</span>
                          </li>
                        ))
                      ) : (
                        <li className="flex items-start gap-2 italic">
                          <span>Đầy đủ tính năng phân tích live cơ bản</span>
                        </li>
                      )}
                    </ul>
                  </div>
                </CardContent>
                <CardFooter className="p-6 pt-0">
                  <Button
                    onClick={() => handleSelectPackage(pkg)}
                    disabled={loadingCheckout || (isCurrent && isFree)}
                    className="w-full gap-1 shadow-sm font-medium"
                    variant={isCurrent ? "outline" : "default"}
                  >
                    {loadingCheckout && selectedPkg?.id === pkg.id ? (
                      <Loader2Icon className="size-4 animate-spin" />
                    ) : isCurrent ? (
                      isFree ? "Đang sử dụng" : "Mua thêm thời hạn"
                    ) : (
                      "Đăng ký ngay"
                    )}
                    {!isCurrent && <ArrowRightIcon className="size-4 ml-1" />}
                  </Button>
                </CardFooter>
              </Card>
            )
          })}
        </div>
      </div>

      {/* CHECKOUT MODAL CHO GÓI TRẢ PHÍ */}
      <Dialog open={isCheckoutOpen} onOpenChange={setIsCheckoutOpen}>
        <DialogContent className="max-w-md p-6 overflow-hidden">
          <DialogHeader>
            <DialogTitle className="text-xl font-bold flex items-center gap-2">
              <CreditCardIcon className="size-5 text-primary" /> Thanh toán gói dịch vụ
            </DialogTitle>
            <DialogDescription>
              Vui lòng quét mã VietQR dưới đây để tiến hành chuyển khoản mua gói dịch vụ <span className="font-semibold text-foreground">"{selectedPkg?.name}"</span>.
            </DialogDescription>
          </DialogHeader>

          {checkoutData?.vietqr_url && (
            <div className="flex flex-col items-center gap-4 py-4">
              {/* VietQR Code Frame */}
              <div className="relative p-3 bg-white rounded-2xl shadow-md border border-border/40 flex items-center justify-center max-w-[240px] aspect-square overflow-hidden group">
                <img
                  src={checkoutData.vietqr_url}
                  alt="VietQR Code"
                  className="w-full h-full object-contain"
                />
              </div>

              {/* Thông tin chuyển khoản */}
              <div className="w-full space-y-3 bg-muted/50 p-4 rounded-xl border border-border/40 text-sm">
                <div className="flex justify-between items-center py-1 border-b border-border/40">
                  <span className="text-muted-foreground">Ngân hàng:</span>
                  <span className="font-semibold text-foreground">MB Bank</span>
                </div>
                <div className="flex justify-between items-center py-1 border-b border-border/40">
                  <span className="text-muted-foreground">Chủ tài khoản:</span>
                  <span className="font-semibold text-foreground">DANG TUAN DAT</span>
                </div>
                <div className="flex justify-between items-center py-1 border-b border-border/40">
                  <span className="text-muted-foreground">Số tiền:</span>
                  <span className="font-bold text-primary tabular-nums">{formatMoney(selectedPkg?.price || 0)}</span>
                </div>
                <div className="flex justify-between items-center py-1">
                  <span className="text-muted-foreground">Nội dung chuyển khoản:</span>
                  <div className="flex items-center gap-1">
                    <code className="bg-background px-2 py-0.5 rounded border border-border text-xs text-primary font-mono font-bold">
                      {transferContent}
                    </code>
                    <Button
                      size="icon"
                      variant="ghost"
                      className="size-7 hover:bg-background/80"
                      onClick={handleCopyContent}
                    >
                      {copied ? (
                        <CheckCircle2Icon className="size-3.5 text-emerald-500" />
                      ) : (
                        <CopyIcon className="size-3.5 text-muted-foreground" />
                      )}
                    </Button>
                  </div>
                </div>
              </div>

              {/* Hướng dẫn an toàn */}
              <div className="flex gap-2 text-xs text-muted-foreground bg-amber-500/5 border border-amber-500/20 p-3 rounded-lg">
                <AlertCircleIcon className="size-4 text-amber-500 shrink-0 mt-0.5" />
                <p>
                  <span className="font-semibold text-foreground">Lưu ý quan trọng:</span> Chuyển đúng số tiền và nội dung chuyển khoản ở trên để hệ thống tự động nhận diện giao dịch và kích hoạt gói đăng ký tức thì.
                </p>
              </div>
            </div>
          )}

          <DialogFooter className="flex-col sm:flex-row gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setIsCheckoutOpen(false)}
              className="w-full sm:w-auto"
            >
              Quay lại
            </Button>
            <Button
              type="button"
              onClick={handleConfirmPaid}
              disabled={isCheckingPayment}
              className="w-full sm:w-auto bg-primary text-primary-foreground"
            >
              {isCheckingPayment ? (
                <>
                  <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                  Đang kiểm tra...
                </>
              ) : (
                "Tôi đã chuyển tiền"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}
