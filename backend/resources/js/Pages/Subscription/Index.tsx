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
import { Progress } from "@/components/ui/progress"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface PackageFeatures {
  limit_streams: number
  max_duration_hours: number
  ai_credits: number
  audio_analysis: boolean
  export_leads: boolean
}

interface SubscriptionPackage {
  id: number
  name: string
  price: number
  duration_days: number
  features: PackageFeatures | null
}

interface ActiveSubscription {
  package_id: number
  package_name: string
  expires_at: string
  used_ai_credits: number
  features: PackageFeatures
}

interface Transaction {
  id: number
  transaction_id: string
  package_name: string
  amount: number
  status: string
  created_at: string
}

interface Props {
  packages: SubscriptionPackage[]
  activeSubscription: ActiveSubscription | null
  transactions: Transaction[]
  auth: {
    user: {
      id: number
      name: string
      email: string
    }
  }
}

export default function SubscriptionIndex({ packages = [], activeSubscription, transactions = [], auth }: Props) {
  const [selectedPkg, setSelectedPkg] = React.useState<SubscriptionPackage | null>(null)
  const [isCheckoutOpen, setIsCheckoutOpen] = React.useState(false)
  const [loadingCheckout, setLoadingCheckout] = React.useState(false)
  const [checkoutData, setCheckoutData] = React.useState<{
    transaction_id: string
    vietqr_url: string | null
  } | null>(null)

  const [copied, setCopied] = React.useState(false)
  const [isCheckingPayment, setIsCheckingPayment] = React.useState(false)
  const [timeLeft, setTimeLeft] = React.useState<number>(600)

  React.useEffect(() => {
    if (isCheckoutOpen) {
      setTimeLeft(600)
    }
  }, [isCheckoutOpen])

  React.useEffect(() => {
    let timerId: any;
    if (isCheckoutOpen && timeLeft > 0) {
      timerId = setInterval(() => {
        setTimeLeft((prev) => prev - 1)
      }, 1000)
    }
    return () => {
      if (timerId) clearInterval(timerId)
    }
  }, [isCheckoutOpen, timeLeft])

  React.useEffect(() => {
    let intervalId: any;

    if (isCheckoutOpen && selectedPkg && checkoutData?.vietqr_url && timeLeft > 0) {
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
  }, [isCheckoutOpen, selectedPkg, checkoutData, timeLeft > 0])

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
          <CardContent className="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div className="space-y-1 flex-1">
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

            {/* AI Credits Usage progress */}
            <div className="w-full md:w-80 space-y-2 bg-background/50 p-4 rounded-xl border border-border/40">
              <div className="flex justify-between items-center text-sm">
                <span className="font-medium text-muted-foreground">AI Credits:</span>
                <span className="font-bold text-foreground">
                  {(activeSubscription?.used_ai_credits ?? 0).toLocaleString()} / {activeSubscription?.features?.ai_credits === -1 ? "Vô hạn" : (activeSubscription?.features?.ai_credits ?? 1000).toLocaleString()}
                </span>
              </div>
              {activeSubscription?.features?.ai_credits === -1 ? (
                <div className="h-2 w-full rounded-full bg-primary/20 overflow-hidden">
                  <div className="h-full bg-primary w-full" />
                </div>
              ) : (
                <Progress
                  value={Math.min(100, Math.max(0, ((activeSubscription?.used_ai_credits ?? 0) / (activeSubscription?.features?.ai_credits ?? 1000)) * 100))}
                  className="h-2"
                />
              )}
            </div>
            
            {activeSubscription && (
              <div className="flex items-center gap-2 text-xs text-muted-foreground bg-background/50 px-3 py-2 rounded-lg border border-border/40 w-full md:w-auto max-w-xs">
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
                      {pkg.features ? (
                        <>
                          <li className="flex items-start gap-2">
                            <CheckIcon className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            <span>{pkg.features.limit_streams === -1 ? "Vô hạn phiên livestream" : `Tối đa ${pkg.features.limit_streams} phiên livestream`}</span>
                          </li>
                          <li className="flex items-start gap-2">
                            <CheckIcon className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            <span>Thời lượng tối đa {pkg.features.max_duration_hours} giờ / phiên</span>
                          </li>
                          <li className="flex items-start gap-2">
                            <CheckIcon className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            <span>{pkg.features.ai_credits.toLocaleString()} AI Credits</span>
                          </li>
                          <li className="flex items-start gap-2">
                            {pkg.features.audio_analysis ? (
                              <CheckIcon className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            ) : (
                              <span className="size-4 text-red-500 shrink-0 mt-0.5 font-bold flex items-center justify-center">×</span>
                            )}
                            <span className={pkg.features.audio_analysis ? "" : "line-through text-muted-foreground/60"}>Phân tích âm thanh nâng cao</span>
                          </li>
                          <li className="flex items-start gap-2">
                            {pkg.features.export_leads ? (
                              <CheckIcon className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                            ) : (
                              <span className="size-4 text-red-500 shrink-0 mt-0.5 font-bold flex items-center justify-center">×</span>
                            )}
                            <span className={pkg.features.export_leads ? "" : "line-through text-muted-foreground/60"}>Xuất danh sách lead (CSV)</span>
                          </li>
                        </>
                      ) : (
                        <li className="flex items-start gap-2 italic">
                          <span>Đầy đủ tính năng phân tích live cơ bản</span>
                        </li>
                      )}
                    </ul>
                  </div>
                </CardContent>
                <CardFooter className="p-6">
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

        {/* Bảng so sánh tính năng */}
        <div className="space-y-4 pt-4">
          <h2 className="text-xl font-bold text-foreground">So sánh tính năng</h2>
          <Card className="border border-border/60 shadow-sm overflow-hidden bg-background/60">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-1/3">Tính năng</TableHead>
                  {packages.map((pkg) => (
                    <TableHead key={pkg.id} className="text-center font-bold">
                      {pkg.name}
                    </TableHead>
                  ))}
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow>
                  <TableCell className="font-medium text-foreground">Số livestream đồng thời</TableCell>
                  {packages.map((pkg) => (
                    <TableCell key={pkg.id} className="text-center">
                      {pkg.features?.limit_streams === -1 ? "Vô hạn" : `${pkg.features?.limit_streams ?? 0} luồng`}
                    </TableCell>
                  ))}
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium text-foreground">Thời lượng tối đa / phiên</TableCell>
                  {packages.map((pkg) => (
                    <TableCell key={pkg.id} className="text-center">
                      {pkg.features?.max_duration_hours ?? 0} giờ
                    </TableCell>
                  ))}
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium text-foreground">AI Credits</TableCell>
                  {packages.map((pkg) => (
                    <TableCell key={pkg.id} className="text-center">
                      {pkg.features?.ai_credits?.toLocaleString() ?? 0}
                    </TableCell>
                  ))}
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium text-foreground">Phân tích âm thanh nâng cao</TableCell>
                  {packages.map((pkg) => (
                    <TableCell key={pkg.id} className="text-center">
                      {pkg.features?.audio_analysis ? (
                        <CheckIcon className="size-5 text-emerald-500 mx-auto" />
                      ) : (
                        <span className="text-red-500 text-lg">×</span>
                      )}
                    </TableCell>
                  ))}
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium text-foreground">Xuất danh sách lead (CSV)</TableCell>
                  {packages.map((pkg) => (
                    <TableCell key={pkg.id} className="text-center">
                      {pkg.features?.export_leads ? (
                        <CheckIcon className="size-5 text-emerald-500 mx-auto" />
                      ) : (
                        <span className="text-red-500 text-lg">×</span>
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              </TableBody>
            </Table>
          </Card>
        </div>

        {/* Lịch sử giao dịch */}
        <div className="space-y-4 pt-4">
          <h2 className="text-xl font-bold text-foreground">Lịch sử giao dịch</h2>
          <Card className="border border-border/60 shadow-sm overflow-hidden bg-background/60">
            {transactions.length > 0 ? (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Mã giao dịch</TableHead>
                    <TableHead>Tên gói</TableHead>
                    <TableHead>Số tiền</TableHead>
                    <TableHead>Trạng thái</TableHead>
                    <TableHead>Ngày tạo</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {transactions.map((tx) => (
                    <TableRow key={tx.id}>
                      <TableCell className="font-mono font-medium text-foreground">{tx.transaction_id}</TableCell>
                      <TableCell>{tx.package_name}</TableCell>
                      <TableCell className="tabular-nums font-semibold">{formatMoney(tx.amount)}</TableCell>
                      <TableCell>
                        {tx.status === "success" && (
                          <Badge className="bg-emerald-500 text-white hover:bg-emerald-600">Thành công</Badge>
                        )}
                        {tx.status === "pending" && (
                          <Badge variant="outline" className="text-amber-600 border-amber-500 bg-amber-50/50 hover:bg-amber-100/50">Chờ xử lý</Badge>
                        )}
                        {tx.status === "failed" && (
                          <Badge variant="destructive">Thất bại</Badge>
                        )}
                        {tx.status !== "success" && tx.status !== "pending" && tx.status !== "failed" && (
                          <Badge variant="secondary">{tx.status}</Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-muted-foreground">{tx.created_at}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <div className="text-center py-6 text-muted-foreground text-sm">
                Không có lịch sử giao dịch nào.
              </div>
            )}
          </Card>
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
                  className={`w-full h-full object-contain ${timeLeft === 0 ? "filter grayscale opacity-30" : ""}`}
                />
              </div>

              {timeLeft > 0 ? (
                <div className="flex items-center gap-2 text-sm font-semibold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full border border-amber-200 animate-pulse">
                  <Loader2Icon className="size-4 animate-spin text-amber-500" />
                  <span>Đang chờ chuyển khoản... ({Math.floor(timeLeft / 60).toString().padStart(2, "0")}:{(timeLeft % 60).toString().padStart(2, "0")})</span>
                </div>
              ) : (
                <div className="flex items-center gap-2 text-sm font-semibold text-red-600 bg-red-50 px-3 py-1.5 rounded-full border border-red-200">
                  <AlertCircleIcon className="size-4 shrink-0" />
                  <span>Mã thanh toán đã hết hạn (10 phút). Vui lòng đóng và thực hiện lại giao dịch.</span>
                </div>
              )}

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
                      disabled={timeLeft === 0}
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
              disabled={isCheckingPayment || timeLeft === 0}
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
