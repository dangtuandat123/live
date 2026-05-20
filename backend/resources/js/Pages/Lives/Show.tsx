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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { ScrollArea } from "@/components/ui/scroll-area"
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
  EyeIcon,
  MessageSquareIcon,
  SmileIcon,
  PhoneIcon,
  TrendingUpIcon,
  ClockIcon,
  CircleStopIcon,
  UsersIcon,
  HelpCircleIcon,
  PackageIcon,
  SparklesIcon,
} from "lucide-react"

const mockComments = [
  { id: 1, user: "Nguyễn Thị Lan", text: "Áo thun này bao nhiêu tiền chị ơi?", time: "2 giây trước", sentiment: "positive", hasPhone: false },
  { id: 2, user: "Trần Văn Minh", text: "Mình muốn mua quần jean size 30, ship về HCM. SĐT 0901234567", time: "5 giây trước", sentiment: "positive", hasPhone: true },
  { id: 3, user: "Lê Hồng Phúc", text: "Chất lượng vải thế nào ạ? Có bị xù lông không?", time: "12 giây trước", sentiment: "neutral", hasPhone: false },
  { id: 4, user: "Phạm Thị Mai", text: "Váy hoa đẹp quá! Cho mình 1 cái size M nhé. Mình ở 123 Lê Lợi Q1", time: "18 giây trước", sentiment: "positive", hasPhone: false },
  { id: 5, user: "Hoàng Đức Long", text: "Giá đắt quá, shop khác bán rẻ hơn", time: "25 giây trước", sentiment: "negative", hasPhone: false },
  { id: 6, user: "Vũ Thị Hà", text: "Mua 2 cái được giảm giá không shop?", time: "30 giây trước", sentiment: "positive", hasPhone: false },
  { id: 7, user: "Đỗ Minh Tuấn", text: "Túi xách có mấy màu vậy chị? 0987654321", time: "35 giây trước", sentiment: "positive", hasPhone: true },
  { id: 8, user: "Ngô Thanh Huyền", text: "Kính mát có chống UV không ạ?", time: "42 giây trước", sentiment: "neutral", hasPhone: false },
  { id: 9, user: "Bùi Văn Sơn", text: "Ship tỉnh mất mấy ngày shop ơi?", time: "50 giây trước", sentiment: "neutral", hasPhone: false },
  { id: 10, user: "Trịnh Thị Ngọc", text: "Chốt đơn áo thun trắng size L! Mình ở Đà Nẵng", time: "55 giây trước", sentiment: "positive", hasPhone: false },
]

const topProducts = [
  { name: "Áo thun basic cotton", mentions: 342, sentiment: 85, questions: 45 },
  { name: "Váy hoa mùa hè", mentions: 287, sentiment: 92, questions: 32 },
  { name: "Quần jean slim fit", mentions: 198, sentiment: 78, questions: 28 },
  { name: "Túi xách da PU", mentions: 156, sentiment: 88, questions: 19 },
  { name: "Giày sneaker trắng", mentions: 124, sentiment: 71, questions: 22 },
]

const topQuestions = [
  { question: "Giá bao nhiêu?", count: 89, product: "Chung" },
  { question: "Có size nào?", count: 67, product: "Áo thun, Quần jean" },
  { question: "Ship mất mấy ngày?", count: 54, product: "Chung" },
  { question: "Có bảo hành không?", count: 38, product: "Giày sneaker" },
  { question: "Mua 2 giảm giá không?", count: 31, product: "Chung" },
  { question: "Chất liệu gì?", count: 28, product: "Áo thun, Váy hoa" },
]

const potentialCustomers = [
  { name: "Trần Văn Minh", phone: "0901234567", address: "HCM", product: "Quần jean slim fit", comment: "Mua size 30, ship HCM" },
  { name: "Đỗ Minh Tuấn", phone: "0987654321", address: "", product: "Túi xách da PU", comment: "Hỏi mấy màu" },
  { name: "Phạm Thị Mai", phone: "", address: "123 Lê Lợi Q1", product: "Váy hoa mùa hè", comment: "Mua size M" },
  { name: "Trịnh Thị Ngọc", phone: "", address: "Đà Nẵng", product: "Áo thun basic cotton", comment: "Chốt đơn size L" },
]

function SentimentBadge({ sentiment }: { sentiment: string }) {
  const config: Record<string, { label: string; variant: "default" | "secondary" | "outline" | "destructive" }> = {
    positive: { label: "Tích cực", variant: "default" },
    neutral: { label: "Trung lập", variant: "secondary" },
    negative: { label: "Tiêu cực", variant: "destructive" },
  }
  const c = config[sentiment] ?? config.neutral
  return <Badge variant={c.variant} className="text-xs">{c.label}</Badge>
}

export default function LivesShow() {
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
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href={route("lives.index")}>Phiên Live</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Flash Sale Mùa Hè</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
        {/* Session Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div>
              <h1 className="text-2xl font-bold tracking-tight">Flash Sale Mùa Hè</h1>
              <div className="flex items-center gap-2 mt-1">
                <Badge variant="default">Facebook</Badge>
                <Badge variant="destructive" className="gap-1">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                  </span>
                  Đang Live
                </Badge>
                <span className="flex items-center gap-1 text-sm text-muted-foreground">
                  <ClockIcon className="size-3.5" />
                  1h 23m
                </span>
              </div>
            </div>
          </div>
          <Button variant="destructive">
            <CircleStopIcon className="mr-2 size-4" />
            Kết thúc phiên
          </Button>
        </div>

        {/* Realtime KPIs */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Lượt xem</CardTitle>
              <EyeIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">3,247</div>
              <p className="text-xs text-muted-foreground flex items-center gap-1">
                <TrendingUpIcon className="size-3 text-green-500" />
                +127 trong 5 phút qua
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Bình luận</CardTitle>
              <MessageSquareIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">523</div>
              <p className="text-xs text-muted-foreground">~8.2 comment/phút</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Sentiment</CardTitle>
              <SmileIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">78%</div>
              <p className="text-xs text-muted-foreground">tích cực</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">KH tiềm năng</CardTitle>
              <PhoneIcon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">12</div>
              <p className="text-xs text-muted-foreground">đã để lại SĐT/địa chỉ</p>
            </CardContent>
          </Card>
        </div>

        {/* Main Content Tabs */}
        <Tabs defaultValue="comments" className="flex-1">
          <TabsList>
            <TabsTrigger value="comments" className="gap-1.5">
              <MessageSquareIcon className="size-4" />
              Bình luận
            </TabsTrigger>
            <TabsTrigger value="products" className="gap-1.5">
              <PackageIcon className="size-4" />
              Sản phẩm
            </TabsTrigger>
            <TabsTrigger value="questions" className="gap-1.5">
              <HelpCircleIcon className="size-4" />
              Câu hỏi
            </TabsTrigger>
            <TabsTrigger value="customers" className="gap-1.5">
              <UsersIcon className="size-4" />
              KH tiềm năng
            </TabsTrigger>
            <TabsTrigger value="ai" className="gap-1.5">
              <SparklesIcon className="size-4" />
              AI Insights
            </TabsTrigger>
          </TabsList>

          {/* Comments Tab */}
          <TabsContent value="comments">
            <Card>
              <CardHeader>
                <CardTitle>Bình luận Realtime</CardTitle>
                <CardDescription>Feed bình luận với phân tích cảm xúc tự động bởi AI</CardDescription>
              </CardHeader>
              <CardContent>
                <ScrollArea className="h-[500px] pr-4">
                  <div className="space-y-3">
                    {mockComments.map((comment) => (
                      <div
                        key={comment.id}
                        className="flex items-start gap-3 rounded-lg border p-3"
                      >
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                          {comment.user.charAt(0)}
                        </div>
                        <div className="flex-1 space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{comment.user}</span>
                            <SentimentBadge sentiment={comment.sentiment} />
                            {comment.hasPhone && (
                              <Badge variant="outline" className="gap-1 text-xs">
                                <PhoneIcon className="size-3" />
                                SĐT
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground">{comment.text}</p>
                          <p className="text-xs text-muted-foreground">{comment.time}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                </ScrollArea>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Products Tab */}
          <TabsContent value="products">
            <Card>
              <CardHeader>
                <CardTitle>Sản phẩm được nhắc đến</CardTitle>
                <CardDescription>Xếp hạng sản phẩm theo số lượt nhắc đến trong bình luận</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>#</TableHead>
                      <TableHead>Sản phẩm</TableHead>
                      <TableHead className="text-right">Lượt nhắc</TableHead>
                      <TableHead>Sentiment</TableHead>
                      <TableHead className="text-right">Câu hỏi</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {topProducts.map((product, i) => (
                      <TableRow key={product.name}>
                        <TableCell className="font-bold text-muted-foreground">{i + 1}</TableCell>
                        <TableCell className="font-medium">{product.name}</TableCell>
                        <TableCell className="text-right">{product.mentions}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <Progress value={product.sentiment} className="h-2 w-20" />
                            <span className="text-xs text-muted-foreground">{product.sentiment}%</span>
                          </div>
                        </TableCell>
                        <TableCell className="text-right">{product.questions}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Questions Tab */}
          <TabsContent value="questions">
            <Card>
              <CardHeader>
                <CardTitle>Câu hỏi thường gặp</CardTitle>
                <CardDescription>Câu hỏi được hỏi nhiều nhất trong bình luận, phân loại bởi AI</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>#</TableHead>
                      <TableHead>Câu hỏi</TableHead>
                      <TableHead className="text-right">Số lần hỏi</TableHead>
                      <TableHead>Sản phẩm liên quan</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {topQuestions.map((q, i) => (
                      <TableRow key={q.question}>
                        <TableCell className="font-bold text-muted-foreground">{i + 1}</TableCell>
                        <TableCell className="font-medium">{q.question}</TableCell>
                        <TableCell className="text-right">
                          <Badge variant="secondary">{q.count}</Badge>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{q.product}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Customers Tab */}
          <TabsContent value="customers">
            <Card>
              <CardHeader>
                <CardTitle>Khách hàng tiềm năng</CardTitle>
                <CardDescription>Khách hàng đã để lại thông tin liên hệ (SĐT, địa chỉ) trong bình luận</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Tên</TableHead>
                      <TableHead>SĐT</TableHead>
                      <TableHead>Địa chỉ</TableHead>
                      <TableHead>Sản phẩm quan tâm</TableHead>
                      <TableHead>Nội dung</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {potentialCustomers.map((customer, i) => (
                      <TableRow key={i}>
                        <TableCell className="font-medium">{customer.name}</TableCell>
                        <TableCell>
                          {customer.phone ? (
                            <Badge variant="outline" className="gap-1">
                              <PhoneIcon className="size-3" />
                              {customer.phone}
                            </Badge>
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell>{customer.address || <span className="text-muted-foreground">—</span>}</TableCell>
                        <TableCell>
                          <Badge variant="secondary">{customer.product}</Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground max-w-[200px] truncate">
                          {customer.comment}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          {/* AI Insights Tab */}
          <TabsContent value="ai">
            <div className="grid gap-4 md:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <SparklesIcon className="size-5" />
                    Tóm tắt AI
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm text-muted-foreground">
                  <p>
                    <strong className="text-foreground">Phiên live đang diễn ra tốt</strong> với tỷ lệ tương tác cao.
                    Sản phẩm "Áo thun basic cotton" được quan tâm nhiều nhất với 342 lượt nhắc.
                  </p>
                  <p>
                    <strong className="text-foreground">Cảm xúc tích cực chiếm 78%</strong>, chủ yếu liên quan đến chất lượng
                    sản phẩm và giá cả hợp lý. Một số phản hồi tiêu cực về giá cao hơn so với shop khác.
                  </p>
                  <p>
                    <strong className="text-foreground">Gợi ý:</strong> Nên trả lời câu hỏi về "size" và "ship" vì đây là
                    2 câu hỏi nhiều nhất. Có thể đưa ra combo giảm giá vì nhiều khách hỏi về mua nhiều.
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Cảnh báo AI</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3">
                    <p className="text-sm font-medium text-yellow-600">⚠️ Câu hỏi chưa trả lời</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      28 câu hỏi về "chất liệu" chưa được trả lời trong 5 phút qua.
                    </p>
                  </div>
                  <div className="rounded-lg border border-green-500/20 bg-green-500/5 p-3">
                    <p className="text-sm font-medium text-green-600">✅ Sản phẩm hot</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      "Váy hoa mùa hè" có sentiment 92% — sản phẩm bán chạy nhất phiên này.
                    </p>
                  </div>
                  <div className="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3">
                    <p className="text-sm font-medium text-blue-600">💡 Cơ hội upsell</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      31 khách hỏi "mua 2 giảm giá không" — nên tạo combo giảm giá ngay.
                    </p>
                  </div>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </AuthenticatedLayout>
  )
}
