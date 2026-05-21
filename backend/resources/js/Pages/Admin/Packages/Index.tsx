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
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  PackageIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  CheckCircle2Icon,
  LoaderIcon,
  PlusCircleIcon,
  XIcon,
} from "lucide-react"
import * as React from "react"
import { toast } from "sonner"

interface SubscriptionPackage {
  id: number
  name: string
  price: number
  duration_days: number
  features: string[] | null
  created_at?: string
  updated_at?: string
}

interface Props {
  packages: SubscriptionPackage[]
  errors?: Record<string, string>
}

export default function AdminPackages({ packages = [] }: Props) {
  const [isCreateOpen, setIsCreateOpen] = React.useState(false)
  const [isEditOpen, setIsEditOpen] = React.useState(false)
  const [isDeleteOpen, setIsDeleteOpen] = React.useState(false)
  const [selectedPackage, setSelectedPackage] = React.useState<SubscriptionPackage | null>(null)
  
  // Tính năng động cho form thêm/sửa
  const [featuresList, setFeaturesList] = React.useState<string[]>([])

  // Form tạo mới
  const createForm = useForm({
    name: "",
    price: 0,
    duration_days: 30,
    features: [] as string[],
  })

  // Form chỉnh sửa
  const editForm = useForm({
    name: "",
    price: 0,
    duration_days: 30,
    features: [] as string[],
  })

  // Form xóa
  const deleteForm = useForm()

  // Mở Dialog thêm mới
  function handleOpenCreate() {
    createForm.reset()
    setFeaturesList([""])
    setIsCreateOpen(true)
  }

  // Mở Dialog sửa
  function handleOpenEdit(pkg: SubscriptionPackage) {
    setSelectedPackage(pkg)
    editForm.setData({
      name: pkg.name,
      price: pkg.price,
      duration_days: pkg.duration_days,
      features: pkg.features || [],
    })
    setFeaturesList(pkg.features && pkg.features.length > 0 ? [...pkg.features] : [""])
    setIsEditOpen(true)
  }

  // Mở Dialog xóa
  function handleOpenDelete(pkg: SubscriptionPackage) {
    setSelectedPackage(pkg)
    setIsDeleteOpen(true)
  }

  // Quản lý tính năng động
  function handleAddFeatureInput() {
    setFeaturesList([...featuresList, ""])
  }

  function handleRemoveFeatureInput(index: number) {
    const list = [...featuresList]
    list.splice(index, 1)
    setFeaturesList(list.length === 0 ? [""] : list)
  }

  function handleFeatureChange(index: number, value: string) {
    const list = [...featuresList]
    list[index] = value
    setFeaturesList(list)
  }

  // Submit thêm mới
  function handleCreateSubmit(e: React.FormEvent) {
    e.preventDefault()
    // Lọc bỏ tính năng rỗng
    const cleanedFeatures = featuresList.filter(f => f.trim() !== "")
    createForm.setData("features", cleanedFeatures)

    // Gọi Inertia post
    createForm.post(route("admin.packages.store"), {
      onSuccess: () => {
        setIsCreateOpen(false)
        createForm.reset()
        toast.success("Đã tạo gói dịch vụ mới thành công!")
      },
      onError: () => {
        toast.error("Có lỗi xảy ra khi tạo gói dịch vụ.")
      }
    })
  }

  // Submit chỉnh sửa
  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!selectedPackage) return
    const cleanedFeatures = featuresList.filter(f => f.trim() !== "")
    
    // Gọi Inertia put
    // Inertia useForm put() không hỗ trợ truyền data trực tiếp qua option dễ dàng như post(),
    // do đó chúng ta cần gán dữ liệu vào form trước khi put
    editForm.setData("features", cleanedFeatures)

    // Cách an toàn để submit với setData động: sử dụng callback submit hoặc submit thẳng
    // sau khi setData ở step trước
    editForm.put(route("admin.packages.update", { package: selectedPackage.id }), {
      onSuccess: () => {
        setIsEditOpen(false)
        toast.success("Đã cập nhật gói dịch vụ thành công!")
      },
      onError: () => {
        toast.error("Có lỗi xảy ra khi cập nhật gói dịch vụ.")
      }
    })
  }

  // Cập nhật features của editForm khi featuresList thay đổi
  React.useEffect(() => {
    if (isEditOpen) {
      editForm.setData("features", featuresList.filter(f => f.trim() !== ""))
    }
  }, [featuresList, isEditOpen])

  React.useEffect(() => {
    if (isCreateOpen) {
      createForm.setData("features", featuresList.filter(f => f.trim() !== ""))
    }
  }, [featuresList, isCreateOpen])

  // Submit xóa
  function handleDeleteSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!selectedPackage) return

    deleteForm.delete(route("admin.packages.destroy", { package: selectedPackage.id }), {
      onSuccess: () => {
        setIsDeleteOpen(false)
        toast.success("Đã xóa gói dịch vụ thành công!")
      },
      onError: (err) => {
        // Lấy thông báo lỗi được trả về từ Session Flash errors
        const errMsg = err.error || "Không thể xóa gói dịch vụ này."
        toast.error(errMsg)
      }
    })
  }

  // Định dạng số tiền
  function formatMoney(amount: number) {
    if (amount === 0) return "Miễn phí"
    return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(amount)
  }

  return (
    <AdminLayout>
      <Head title="Admin - Quản lý gói dịch vụ" />
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
                <BreadcrumbPage>Quản lý gói dịch vụ</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="mx-auto w-full max-w-6xl flex flex-1 flex-col gap-6 p-4 pt-0">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Quản lý gói dịch vụ</h1>
            <p className="text-muted-foreground">Thiết lập các gói dịch vụ đăng ký cho người dùng</p>
          </div>
          <Button onClick={handleOpenCreate} className="gap-1 bg-primary text-primary-foreground hover:bg-primary/95 shadow-sm">
            <PlusIcon className="size-4" />
            Thêm gói dịch vụ
          </Button>
        </div>

        <Card>
          <CardHeader className="px-6 py-4">
            <CardTitle className="flex items-center gap-2 text-lg">
              <PackageIcon className="size-5 text-primary" />
              Danh sách gói dịch vụ ({packages.length})
            </CardTitle>
            <CardDescription>Cung cấp các gói miễn phí và trả phí linh hoạt</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
            {packages.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 px-4 text-center">
                <PackageIcon className="size-12 text-muted-foreground/40 mb-4" />
                <h3 className="font-semibold text-lg">Chưa có gói dịch vụ nào</h3>
                <p className="text-muted-foreground text-sm max-w-xs mt-1">Hãy tạo gói dịch vụ đầu tiên để bắt đầu kinh doanh.</p>
                <Button onClick={handleOpenCreate} className="mt-4 gap-1">
                  <PlusIcon className="size-4" /> Tạo gói dịch vụ
                </Button>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-[200px]">Tên gói</TableHead>
                      <TableHead>Giá dịch vụ</TableHead>
                      <TableHead>Thời hạn (ngày)</TableHead>
                      <TableHead className="max-w-[350px]">Tính năng nổi bật</TableHead>
                      <TableHead className="text-right w-[150px] pr-6">Hành động</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {packages.map((pkg) => (
                      <TableRow key={pkg.id}>
                        <TableCell className="font-semibold text-foreground">{pkg.name}</TableCell>
                        <TableCell className="font-medium">
                          {pkg.price === 0 ? (
                            <Badge variant="outline" className="text-emerald-500 border-emerald-500/30 bg-emerald-500/5">Miễn phí</Badge>
                          ) : (
                            <span className="text-foreground font-semibold">{formatMoney(pkg.price)}</span>
                          )}
                        </TableCell>
                        <TableCell className="text-muted-foreground">{pkg.duration_days} ngày</TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1.5 max-w-[400px]">
                            {pkg.features && pkg.features.length > 0 ? (
                              pkg.features.map((feat, idx) => (
                                <Badge key={idx} variant="secondary" className="text-[11px] font-normal py-0.5">
                                  {feat}
                                </Badge>
                              ))
                            ) : (
                              <span className="text-xs text-muted-foreground italic">Không có tính năng ghi nhận</span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="text-right pr-6">
                          <div className="flex justify-end gap-2">
                            <Button
                              onClick={() => handleOpenEdit(pkg)}
                              variant="ghost"
                              size="icon"
                              className="size-8 text-muted-foreground hover:text-foreground"
                            >
                              <PencilIcon className="size-3.5" />
                            </Button>
                            <Button
                              onClick={() => handleOpenDelete(pkg)}
                              variant="ghost"
                              size="icon"
                              className="size-8 text-destructive hover:bg-destructive/10"
                            >
                              <TrashIcon className="size-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* MODAL THÊM MỚI GÓI DỊCH VỤ */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="max-w-lg">
          <form onSubmit={handleCreateSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Thêm gói dịch vụ mới</DialogTitle>
              <DialogDescription>
                Tạo gói dịch vụ mới để cung cấp cho người dùng đăng ký.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-2">
              <div className="grid gap-2">
                <Label htmlFor="create-name">Tên gói</Label>
                <Input
                  id="create-name"
                  value={createForm.data.name}
                  onChange={(e) => createForm.setData("name", e.target.value)}
                  placeholder="Ví dụ: Pro, Business, VIP..."
                  required
                />
                {createForm.errors.name && <p className="text-xs text-destructive">{createForm.errors.name}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="create-price">Giá tiền (VNĐ)</Label>
                  <Input
                    id="create-price"
                    type="number"
                    min="0"
                    value={createForm.data.price}
                    onChange={(e) => createForm.setData("price", parseInt(e.target.value) || 0)}
                    required
                  />
                  {createForm.errors.price && <p className="text-xs text-destructive">{createForm.errors.price}</p>}
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="create-duration">Thời hạn (Ngày)</Label>
                  <Input
                    id="create-duration"
                    type="number"
                    min="1"
                    value={createForm.data.duration_days}
                    onChange={(e) => createForm.setData("duration_days", parseInt(e.target.value) || 30)}
                    required
                  />
                  {createForm.errors.duration_days && <p className="text-xs text-destructive">{createForm.errors.duration_days}</p>}
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label>Tính năng đi kèm</Label>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={handleAddFeatureInput}
                    className="h-7 text-xs text-primary gap-1"
                  >
                    <PlusCircleIcon className="size-3.5" /> Thêm dòng
                  </Button>
                </div>

                <div className="max-h-[160px] overflow-y-auto space-y-2 pr-1">
                  {featuresList.map((feature, idx) => (
                    <div key={idx} className="flex gap-2">
                      <Input
                        value={feature}
                        onChange={(e) => handleFeatureChange(idx, e.target.value)}
                        placeholder={`Tính năng ${idx + 1}`}
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => handleRemoveFeatureInput(idx)}
                        className="size-9 shrink-0 text-muted-foreground hover:text-foreground"
                      >
                        <XIcon className="size-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>Hủy</Button>
              <Button type="submit" disabled={createForm.processing}>
                {createForm.processing && <LoaderIcon className="size-4 animate-spin mr-1" />}
                Thêm mới
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* MODAL CHỈNH SỬA GÓI DỊCH VỤ */}
      <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
        <DialogContent className="max-w-lg">
          <form onSubmit={handleEditSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Chỉnh sửa gói dịch vụ</DialogTitle>
              <DialogDescription>
                Cập nhật thông tin gói dịch vụ đã chọn.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-2">
              <div className="grid gap-2">
                <Label htmlFor="edit-name">Tên gói</Label>
                <Input
                  id="edit-name"
                  value={editForm.data.name}
                  onChange={(e) => editForm.setData("name", e.target.value)}
                  placeholder="Ví dụ: Pro, Business..."
                  required
                />
                {editForm.errors.name && <p className="text-xs text-destructive">{editForm.errors.name}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="edit-price">Giá tiền (VNĐ)</Label>
                  <Input
                    id="edit-price"
                    type="number"
                    min="0"
                    value={editForm.data.price}
                    onChange={(e) => editForm.setData("price", parseInt(e.target.value) || 0)}
                    required
                  />
                  {editForm.errors.price && <p className="text-xs text-destructive">{editForm.errors.price}</p>}
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="edit-duration">Thời hạn (Ngày)</Label>
                  <Input
                    id="edit-duration"
                    type="number"
                    min="1"
                    value={editForm.data.duration_days}
                    onChange={(e) => editForm.setData("duration_days", parseInt(e.target.value) || 30)}
                    required
                  />
                  {editForm.errors.duration_days && <p className="text-xs text-destructive">{editForm.errors.duration_days}</p>}
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label>Tính năng đi kèm</Label>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={handleAddFeatureInput}
                    className="h-7 text-xs text-primary gap-1"
                  >
                    <PlusCircleIcon className="size-3.5" /> Thêm dòng
                  </Button>
                </div>

                <div className="max-h-[160px] overflow-y-auto space-y-2 pr-1">
                  {featuresList.map((feature, idx) => (
                    <div key={idx} className="flex gap-2">
                      <Input
                        value={feature}
                        onChange={(e) => handleFeatureChange(idx, e.target.value)}
                        placeholder={`Tính năng ${idx + 1}`}
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => handleRemoveFeatureInput(idx)}
                        className="size-9 shrink-0 text-muted-foreground hover:text-foreground"
                      >
                        <XIcon className="size-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsEditOpen(false)}>Hủy</Button>
              <Button type="submit" disabled={editForm.processing}>
                {editForm.processing && <LoaderIcon className="size-4 animate-spin mr-1" />}
                Cập nhật
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* MODAL XÁC NHẬN XÓA GÓI DỊCH VỤ */}
      <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
        <DialogContent className="max-w-md">
          <form onSubmit={handleDeleteSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle className="text-destructive flex items-center gap-1.5">
                <TrashIcon className="size-5" /> Xác nhận xóa gói dịch vụ
              </DialogTitle>
              <DialogDescription>
                Bạn có chắc chắn muốn xóa gói dịch vụ <span className="font-semibold text-foreground">"{selectedPackage?.name}"</span>?
              </DialogDescription>
            </DialogHeader>

            <div className="p-3 bg-destructive/10 text-destructive text-xs rounded border border-destructive/20 space-y-1">
              <span className="font-bold">Cảnh báo:</span>
              <p>Hành động này không thể hoàn tác. Bạn chỉ có thể xóa gói dịch vụ nếu chưa có bất kỳ người dùng nào đăng ký hoặc chưa có lịch sử giao dịch liên quan đến gói này.</p>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsDeleteOpen(false)}>Hủy</Button>
              <Button type="submit" variant="destructive" disabled={deleteForm.processing}>
                {deleteForm.processing && <LoaderIcon className="size-4 animate-spin mr-1" />}
                Xác nhận xóa
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AdminLayout>
  )
}
