import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Progress } from "@/components/ui/progress"
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
  DialogTrigger,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import {
  PlusIcon,
  SearchIcon,
  PencilIcon,
  Trash2Icon,
  PackageIcon,
  XIcon,
  TrendingUpIcon,
  MessageSquareIcon,
  ArrowUpIcon,
  ArrowDownIcon,
} from "lucide-react"
import * as React from "react"

// --- Types ---

interface Product {
  id: number
  name: string
  sku: string
  price: number
  category: string | null
  image: string | null
  keywords: string[]
  mentions: number
  prevMentions: number
  isLive: boolean
}

interface Props {
  products: Product[]
  categories: string[]
  filters: {
    search: string | null
    category: string | null
  }
}

// --- Components ---

interface ProductFormDialogProps {
  product?: Product
  categories: string[]
  trigger: React.ReactNode
  title: string
  description: string
}

function ProductFormDialog({
  product,
  categories,
  trigger,
  title,
  description,
}: ProductFormDialogProps) {
  const [open, setOpen] = React.useState(false)
  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: product?.name ?? "",
    sku: product?.sku ?? "",
    price: product?.price ?? 0,
    category: product?.category ?? "",
    image: product?.image ?? "",
    keywords: product?.keywords ?? [],
  })

  const [keywordInput, setKeywordInput] = React.useState("")

  function addKeyword() {
    const trimmed = keywordInput.trim()
    if (trimmed && !data.keywords.includes(trimmed)) {
      setData("keywords", [...data.keywords, trimmed])
      setKeywordInput("")
    }
  }

  function removeKeyword(kw: string) {
    setData("keywords", data.keywords.filter((k) => k !== kw))
  }

  React.useEffect(() => {
    if (open) {
      setData({
        name: product?.name ?? "",
        sku: product?.sku ?? "",
        price: product?.price ?? 0,
        category: product?.category ?? "",
        image: product?.image ?? "",
        keywords: product?.keywords ?? [],
      })
      setKeywordInput("")
    }
  }, [open, product])

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (product) {
      put(route("products.update", product.id), {
        onSuccess: () => setOpen(false),
      })
    } else {
      post(route("products.store"), {
        onSuccess: () => {
          setOpen(false)
          reset()
        },
      })
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{description}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid gap-2">
              <Label htmlFor="product-name">Tên sản phẩm *</Label>
              <Input
                id="product-name"
                value={data.name}
                onChange={(e) => setData("name", e.target.value)}
                placeholder="VD: Áo thun basic cotton"
                required
              />
              {errors.name && <span className="text-destructive text-xs">{errors.name}</span>}
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label htmlFor="product-sku">Mã SKU *</Label>
                <Input
                  id="product-sku"
                  value={data.sku}
                  onChange={(e) => setData("sku", e.target.value)}
                  placeholder="VD: AT-001"
                  required
                />
                {errors.sku && <span className="text-destructive text-xs">{errors.sku}</span>}
              </div>
              <div className="grid gap-2">
                <Label htmlFor="product-price">Giá (VNĐ) *</Label>
                <Input
                  id="product-price"
                  type="number"
                  value={data.price}
                  onChange={(e) => setData("price", parseInt(e.target.value) || 0)}
                  placeholder="VD: 189000"
                  required
                />
                {errors.price && <span className="text-destructive text-xs">{errors.price}</span>}
              </div>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="product-category">Danh mục</Label>
              <Input
                id="product-category"
                value={data.category}
                onChange={(e) => setData("category", e.target.value)}
                placeholder="VD: Áo, Quần, Váy, Phụ kiện..."
                list="existing-categories"
              />
              <datalist id="existing-categories">
                {categories.map((c) => (
                  <option key={c} value={c} />
                ))}
              </datalist>
              {errors.category && <span className="text-destructive text-xs">{errors.category}</span>}
            </div>
            <div className="grid gap-2">
              <Label htmlFor="product-image">URL hình ảnh</Label>
              <Input
                id="product-image"
                value={data.image}
                onChange={(e) => setData("image", e.target.value)}
                placeholder="VD: https://..."
              />
              {errors.image && <span className="text-destructive text-xs">{errors.image}</span>}
            </div>
            <div className="grid gap-2">
              <Label>Từ khóa / Tên gọi khác (AI dùng để nhận diện từ bình luận)</Label>
              <div className="flex gap-2">
                <Input
                  placeholder="Nhập từ khóa rồi Enter"
                  value={keywordInput}
                  onChange={(e) => setKeywordInput(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault()
                      addKeyword()
                    }
                  }}
                />
                <Button type="button" variant="secondary" onClick={addKeyword}>
                  Thêm
                </Button>
              </div>
              <div className="flex flex-wrap gap-1.5 mt-1">
                {data.keywords.map((kw) => (
                  <Badge key={kw} variant="secondary" className="gap-1">
                    {kw}
                    <button
                      type="button"
                      onClick={() => removeKeyword(kw)}
                      className="rounded-full hover:bg-muted animate-in fade-in zoom-in duration-200"
                    >
                      <XIcon className="size-3" />
                    </button>
                  </Badge>
                ))}
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              {processing ? "Đang lưu..." : "Lưu sản phẩm"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

// --- Main ---

export default function ProductsIndex({ products = [], categories = [], filters }: Props) {
  const [search, setSearch] = React.useState(filters.search ?? "")

  const searchTimerRef = React.useRef<ReturnType<typeof setTimeout>>(undefined)
  const applyFilters = (newSearch?: string) => {
    const params: Record<string, string> = {}
    const s = newSearch ?? search
    if (s) params.search = s
    router.get(route("products.index"), params, { preserveState: true, replace: true })
  }

  const handleSearch = (value: string) => {
    setSearch(value)
    clearTimeout(searchTimerRef.current)
    searchTimerRef.current = setTimeout(() => applyFilters(value), 400)
  }

  function handleDelete(id: number) {
    if (confirm("Bạn có chắc chắn muốn xóa sản phẩm này?")) {
      router.delete(route("products.destroy", id))
    }
  }

  const maxMentions = Math.max(...products.map((p) => p.mentions), 1)
  const totalMentions = products.reduce((sum, p) => sum + p.mentions, 0)
  const topProduct = products.reduce((top, p) => (p.mentions > (top?.mentions ?? 0) ? p : top), products[0] || null)

  return (
    <AuthenticatedLayout>
      <Head title="Sản phẩm" />
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
        <div className="flex items-center gap-2 px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator
            orientation="vertical"
            className="mr-2 data-vertical:h-4 data-vertical:self-auto"
          />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href={route("dashboard")}>
                  Trang chủ
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Sản phẩm</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-4">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Sản phẩm</h1>
            <p className="text-muted-foreground">
              Quản lý catalog sản phẩm để AI nhận diện trong phiên live
            </p>
          </div>
          <ProductFormDialog
            categories={categories}
            trigger={
              <Button>
                <PlusIcon className="mr-2 size-4" />
                Thêm sản phẩm
              </Button>
            }
            title="Thêm sản phẩm mới"
            description="Thêm sản phẩm vào catalog. AI sẽ dùng tên và từ khóa để nhận diện sản phẩm từ bình luận."
          />
        </div>

        {/* KPI Cards */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng sản phẩm</CardTitle>
              <PackageIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{products.length}</div>
              <p className="text-xs text-muted-foreground">
                {products.filter((p) => p.isLive).length} đang dùng trong phiên live
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">SP được nhắc nhiều nhất</CardTitle>
              <TrendingUpIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold truncate">
                {topProduct ? topProduct.name : "Chưa có sản phẩm"}
              </div>
              <p className="text-xs text-muted-foreground">
                {topProduct ? `${topProduct.mentions} lượt nhắc` : "—"}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Tổng lượt nhắc</CardTitle>
              <MessageSquareIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{totalMentions.toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">
                Từ bình luận trong tất cả phiên live
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Search */}
        <div className="flex items-center gap-4">
          <div className="relative flex-1 max-w-sm">
            <SearchIcon className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Tìm theo tên hoặc SKU..."
              value={search}
              onChange={(e) => handleSearch(e.target.value)}
              className="pl-9"
            />
          </div>
        </div>

        {/* Products Table */}
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tên sản phẩm</TableHead>
                  <TableHead>SKU</TableHead>
                  <TableHead>Danh mục</TableHead>
                  <TableHead className="text-right">Giá</TableHead>
                  <TableHead>Từ khóa</TableHead>
                  <TableHead className="text-right">Lượt nhắc</TableHead>
                  <TableHead className="text-right">Thao tác</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {products.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                      Không tìm thấy sản phẩm nào
                    </TableCell>
                  </TableRow>
                ) : (
                  products.map((product) => (
                    <TableRow key={product.id}>
                      <TableCell className="font-medium">
                        <div className="flex items-center gap-3">
                          {product.image ? (
                            <img
                              src={product.image}
                              alt={product.name}
                              className="h-10 w-10 rounded-md object-cover bg-muted border"
                            />
                          ) : (
                            <div className="h-10 w-10 rounded-md bg-muted border flex items-center justify-center">
                              <PackageIcon className="size-5 text-muted-foreground/50" />
                            </div>
                          )}
                          <div>
                            <div className="flex items-center gap-2">
                              <span>{product.name}</span>
                              {product.isLive && (
                                <Badge variant="destructive" className="gap-1 text-[10px] px-1.5 py-0">
                                  <span className="relative flex size-1.5">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                                    <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                                  </span>
                                  Live
                                </Badge>
                              )}
                            </div>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {product.sku}
                      </TableCell>
                      <TableCell>
                        {product.category ? (
                          <Badge variant="outline">{product.category}</Badge>
                        ) : (
                          <span className="text-muted-foreground text-xs">—</span>
                        )}
                      </TableCell>
                      <TableCell className="text-right font-mono text-sm">
                        {product.price.toLocaleString("vi-VN")}đ
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1 max-w-[240px]">
                          {product.keywords && product.keywords.slice(0, 3).map((kw) => (
                            <Badge key={kw} variant="secondary" className="text-xs">
                              {kw}
                            </Badge>
                          ))}
                          {product.keywords && product.keywords.length > 3 && (
                            <Badge variant="secondary" className="text-xs">
                              +{product.keywords.length - 3}
                            </Badge>
                          )}
                          {(!product.keywords || product.keywords.length === 0) && (
                            <span className="text-muted-foreground text-xs">—</span>
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Progress value={(product.mentions / maxMentions) * 100} className="h-1.5 w-16" />
                          <span className="text-sm font-medium tabular-nums w-8 text-right">{product.mentions}</span>
                          {product.mentions >= product.prevMentions ? (
                            <ArrowUpIcon className="size-3 text-green-500" />
                          ) : (
                            <ArrowDownIcon className="size-3 text-red-500" />
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          <ProductFormDialog
                            product={product}
                            categories={categories}
                            trigger={
                              <Button variant="ghost" size="icon">
                                <PencilIcon className="size-4" />
                              </Button>
                            }
                            title="Sửa sản phẩm"
                            description="Cập nhật thông tin và từ khóa cho sản phẩm."
                          />
                          <Button variant="ghost" size="icon" onClick={() => handleDelete(product.id)}>
                            <Trash2Icon className="size-4 text-destructive" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
