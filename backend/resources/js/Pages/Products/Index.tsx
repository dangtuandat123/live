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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
} from "lucide-react"
import * as React from "react"

const mockProducts = [
  {
    id: "1",
    name: "Áo thun basic cotton",
    sku: "AT-001",
    price: 189000,
    category: "Áo",
    keywords: ["áo thun", "áo phông", "basic tee", "áo cotton"],
  },
  {
    id: "2",
    name: "Quần jean slim fit",
    sku: "QJ-002",
    price: 450000,
    category: "Quần",
    keywords: ["quần jean", "quần bò", "slim fit", "quần dài"],
  },
  {
    id: "3",
    name: "Váy hoa mùa hè",
    sku: "VH-003",
    price: 320000,
    category: "Váy",
    keywords: ["váy hoa", "váy mùa hè", "đầm hoa", "dress"],
  },
  {
    id: "4",
    name: "Túi xách da PU",
    sku: "TX-004",
    price: 280000,
    category: "Phụ kiện",
    keywords: ["túi xách", "túi da", "bag", "ví"],
  },
  {
    id: "5",
    name: "Giày sneaker trắng",
    sku: "GS-005",
    price: 520000,
    category: "Giày dép",
    keywords: ["giày sneaker", "giày trắng", "giày thể thao", "sneaker"],
  },
  {
    id: "6",
    name: "Kính mát thời trang",
    sku: "KM-006",
    price: 150000,
    category: "Phụ kiện",
    keywords: ["kính mát", "kính râm", "sunglasses"],
  },
]

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
            <Label>Keywords / Alias (AI dùng để nhận diện từ comment)</Label>
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
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
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
            description="Thêm sản phẩm vào catalog. AI sẽ dùng tên và keywords để nhận diện sản phẩm từ bình luận."
          />
        </div>

        {/* Search & Stats */}
        <div className="flex items-center gap-4">
          <div className="relative flex-1 max-w-sm">
            <SearchIcon className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Tìm theo tên, SKU, keywords..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
          <Badge variant="outline" className="gap-1.5">
            <PackageIcon className="size-3.5" />
            {mockProducts.length} sản phẩm
          </Badge>
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
                  <TableHead>Keywords / Alias</TableHead>
                  <TableHead className="text-right">Thao tác</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((product) => (
                  <TableRow key={product.id}>
                    <TableCell className="font-medium">
                      {product.name}
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
                      <div className="flex justify-end gap-1">
                        <ProductFormDialog
                          trigger={
                            <Button variant="ghost" size="icon">
                              <PencilIcon className="size-4" />
                            </Button>
                          }
                          title="Sửa sản phẩm"
                          description="Cập nhật thông tin và keywords cho sản phẩm."
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
