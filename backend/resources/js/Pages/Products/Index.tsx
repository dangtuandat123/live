import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
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

// --- Mock Data ---

const mockProducts = [
  {
    id: "1",
    name: "Áo thun basic cotton",
    sku: "AT-001",
    price: 189000,
    category: "Áo",
    keywords: ["áo thun", "áo phông", "basic tee", "áo cotton"],
    mentions: 342,
    prevMentions: 280,
    isLive: true,
    image: "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=80&h=80&fit=crop&auto=format",
  },
  {
    id: "2",
    name: "Quần jean slim fit",
    sku: "QJ-002",
    price: 450000,
    category: "Quần",
    keywords: ["quần jean", "quần bò", "slim fit", "quần dài"],
    mentions: 195,
    prevMentions: 210,
    isLive: false,
    image: "https://images.unsplash.com/photo-1542272604-787c3835535d?w=80&h=80&fit=crop&auto=format",
  },
  {
    id: "3",
    name: "Váy hoa mùa hè",
    sku: "VH-003",
    price: 320000,
    category: "Váy",
    keywords: ["váy hoa", "váy mùa hè", "đầm hoa", "dress"],
    mentions: 278,
    prevMentions: 195,
    isLive: true,
    image: "https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=80&h=80&fit=crop&auto=format",
  },
  {
    id: "4",
    name: "Túi xách da PU",
    sku: "TX-004",
    price: 280000,
    category: "Phụ kiện",
    keywords: ["túi xách", "túi da", "bag", "ví"],
    mentions: 156,
    prevMentions: 160,
    isLive: false,
    image: "https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=80&h=80&fit=crop&auto=format",
  },
  {
    id: "5",
    name: "Giày sneaker trắng",
    sku: "GS-005",
    price: 520000,
    category: "Giày dép",
    keywords: ["giày sneaker", "giày trắng", "giày thể thao", "sneaker"],
    mentions: 98,
    prevMentions: 65,
    isLive: false,
    image: "https://images.unsplash.com/photo-1549298916-b41d501d3772?w=80&h=80&fit=crop&auto=format",
  },
  {
    id: "6",
    name: "Kính mát thời trang",
    sku: "KM-006",
    price: 150000,
    category: "Phụ kiện",
    keywords: ["kính mát", "kính râm", "sunglasses"],
    mentions: 43,
    prevMentions: 50,
    isLive: false,
    image: "https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=80&h=80&fit=crop&auto=format",
  },
]

const maxMentions = Math.max(...mockProducts.map((p) => p.mentions))
const totalMentions = mockProducts.reduce((sum, p) => sum + p.mentions, 0)
const topProduct = mockProducts.reduce((top, p) => (p.mentions > top.mentions ? p : top), mockProducts[0])

// --- Components ---

function ProductFormDialog({
  trigger,
  title,
  description,
}: {
  trigger: React.ReactNode
  title: string
  description: string
}) {
  const [keywords, setKeywords] = React.useState<string[]>(["áo thun", "basic"])
  const [keywordInput, setKeywordInput] = React.useState("")

  function addKeyword() {
    const trimmed = keywordInput.trim()
    if (trimmed && !keywords.includes(trimmed)) {
      setKeywords([...keywords, trimmed])
      setKeywordInput("")
    }
  }

  function removeKeyword(kw: string) {
    setKeywords(keywords.filter((k) => k !== kw))
  }

  return (
    <Dialog>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 py-4">
          <div className="grid gap-2">
            <Label htmlFor="product-name">Tên sản phẩm</Label>
            <Input id="product-name" placeholder="VD: Áo thun basic cotton" />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="grid gap-2">
              <Label htmlFor="product-sku">Mã SKU</Label>
              <Input id="product-sku" placeholder="VD: AT-001" />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="product-price">Giá (VNĐ)</Label>
              <Input
                id="product-price"
                type="number"
                placeholder="VD: 189000"
              />
            </div>
          </div>
          <div className="grid gap-2">
            <Label>Danh mục</Label>
            <Select>
              <SelectTrigger>
                <SelectValue placeholder="Chọn danh mục" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="ao">Áo</SelectItem>
                <SelectItem value="quan">Quần</SelectItem>
                <SelectItem value="vay">Váy</SelectItem>
                <SelectItem value="giay">Giày dép</SelectItem>
                <SelectItem value="phukien">Phụ kiện</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="grid gap-2">
            <Label>Mô tả ngắn</Label>
            <Input placeholder="Mô tả ngắn gọn về sản phẩm cho AI nhận diện" />
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
            <div className="flex flex-wrap gap-1.5">
              {keywords.map((kw) => (
                <Badge key={kw} variant="secondary" className="gap-1">
                  {kw}
                  <button
                    type="button"
                    onClick={() => removeKeyword(kw)}
                    className="rounded-full hover:bg-muted"
                  >
                    <XIcon className="size-3" />
                  </button>
                </Badge>
              ))}
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button type="submit">Lưu sản phẩm</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

// --- Main ---

export default function ProductsIndex() {
  const [search, setSearch] = React.useState("")

  const filtered = mockProducts.filter(
    (p) =>
      p.name.toLowerCase().includes(search.toLowerCase()) ||
      p.sku.toLowerCase().includes(search.toLowerCase()) ||
      p.keywords.some((k) => k.toLowerCase().includes(search.toLowerCase()))
  )

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

      <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Sản phẩm</h1>
            <p className="text-muted-foreground">
              Quản lý catalog sản phẩm để AI nhận diện trong phiên live
            </p>
          </div>
          <ProductFormDialog
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
              <div className="text-2xl font-bold">{mockProducts.length}</div>
              <p className="text-xs text-muted-foreground">
                {mockProducts.filter((p) => p.isLive).length} đang dùng trong phiên live
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">SP được nhắc nhiều nhất</CardTitle>
              <TrendingUpIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold truncate">{topProduct.name}</div>
              <p className="text-xs text-muted-foreground">
                {topProduct.mentions} lượt nhắc tuần này
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
              placeholder="Tìm theo tên, SKU, từ khóa..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
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
                {filtered.map((product) => (
                  <TableRow key={product.id}>
                    <TableCell className="font-medium">
                      <div className="flex items-center gap-3">
                        <img
                          src={product.image}
                          alt={product.name}
                          className="h-10 w-10 rounded-md object-cover bg-muted border"
                        />
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
                      <Badge variant="outline">{product.category}</Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      {product.price.toLocaleString("vi-VN")}đ
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {product.keywords.slice(0, 3).map((kw) => (
                          <Badge key={kw} variant="secondary" className="text-xs">
                            {kw}
                          </Badge>
                        ))}
                        {product.keywords.length > 3 && (
                          <Badge variant="secondary" className="text-xs">
                            +{product.keywords.length - 3}
                          </Badge>
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
                          trigger={
                            <Button variant="ghost" size="icon">
                              <PencilIcon className="size-4" />
                            </Button>
                          }
                          title="Sửa sản phẩm"
                          description="Cập nhật thông tin và từ khóa cho sản phẩm."
                        />
                        <Button variant="ghost" size="icon">
                          <Trash2Icon className="size-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
