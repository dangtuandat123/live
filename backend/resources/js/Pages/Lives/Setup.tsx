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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Checkbox } from "@/components/ui/checkbox"
import { XIcon, VideoIcon } from "lucide-react"
import * as React from "react"
import { Link } from "@inertiajs/react"

const catalogProducts = [
  { id: "1", name: "Áo thun basic cotton", sku: "AT-001", price: 189000 },
  { id: "2", name: "Quần jean slim fit", sku: "QJ-002", price: 450000 },
  { id: "3", name: "Váy hoa mùa hè", sku: "VH-003", price: 320000 },
  { id: "4", name: "Túi xách da PU", sku: "TX-004", price: 280000 },
  { id: "5", name: "Giày sneaker trắng", sku: "GS-005", price: 520000 },
  { id: "6", name: "Kính mát thời trang", sku: "KM-006", price: 150000 },
]

export default function LivesSetup() {
  const [selectedProducts, setSelectedProducts] = React.useState<string[]>([
    "1",
    "3",
  ])
  const [keywords, setKeywords] = React.useState<string[]>([
    "mua",
    "chốt",
    "ship",
    "giá",
    "size",
  ])
  const [keywordInput, setKeywordInput] = React.useState("")

  function toggleProduct(id: string) {
    setSelectedProducts((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    )
  }

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
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href={route("lives.index")}>
                  Phân tích phiên live
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Tạo phiên mới</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-6 p-4 pt-0 max-w-3xl">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold tracking-tight">
            Tạo phiên Live mới
          </h1>
          <p className="text-muted-foreground">
            Thiết lập thông tin phiên live để AI bắt đầu phân tích
          </p>
        </div>

        {/* Basic Info */}
        <Card>
          <CardHeader>
            <CardTitle>Thông tin cơ bản</CardTitle>
            <CardDescription>
              Nhập thông tin phiên livestream cần phân tích
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4">
            <div className="grid gap-2">
              <Label htmlFor="session-name">Tên phiên live</Label>
              <Input
                id="session-name"
                placeholder="VD: Flash Sale Mùa Hè - 20/05"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label>Nền tảng</Label>
                <Input value="TikTok" disabled className="bg-muted" />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="live-url">URL / ID livestream</Label>
                <Input
                  id="live-url"
                  placeholder="Paste link hoặc ID livestream"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Product Selection */}
        <Card>
          <CardHeader>
            <CardTitle>Chọn sản phẩm từ catalog</CardTitle>
            <CardDescription>
              AI sẽ dùng danh sách sản phẩm này để nhận diện khi phân tích bình
              luận. Đã chọn{" "}
              <Badge variant="secondary">{selectedProducts.length}</Badge> sản
              phẩm.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-12"></TableHead>
                  <TableHead>Sản phẩm</TableHead>
                  <TableHead>SKU</TableHead>
                  <TableHead className="text-right">Giá</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {catalogProducts.map((product) => (
                  <TableRow
                    key={product.id}
                    className="cursor-pointer"
                    onClick={() => toggleProduct(product.id)}
                  >
                    <TableCell>
                      <Checkbox
                        checked={selectedProducts.includes(product.id)}
                        onCheckedChange={() => toggleProduct(product.id)}
                      />
                    </TableCell>
                    <TableCell className="font-medium">
                      {product.name}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {product.sku}
                    </TableCell>
                    <TableCell className="text-right">
                      {product.price.toLocaleString("vi-VN")}đ
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Keywords */}
        <Card>
          <CardHeader>
            <CardTitle>Từ khóa theo dõi</CardTitle>
            <CardDescription>
              Các từ khóa bán hàng mà AI sẽ ưu tiên phát hiện trong bình luận
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3">
            <div className="flex gap-2">
              <Input
                placeholder="Nhập từ khóa rồi Enter..."
                value={keywordInput}
                onChange={(e) => setKeywordInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault()
                    addKeyword()
                  }
                }}
              />
              <Button variant="secondary" onClick={addKeyword}>
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
          </CardContent>
        </Card>

        {/* Submit */}
        <div className="flex items-center gap-3">
          <Button size="lg">
            <VideoIcon className="mr-2 size-4" />
            Bắt đầu phân tích
          </Button>
          <Button variant="outline" size="lg" asChild>
            <Link href={route("lives.index")}>Hủy</Link>
          </Button>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
