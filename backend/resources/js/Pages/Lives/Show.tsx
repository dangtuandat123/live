import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Head } from "@inertiajs/react"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Progress } from "@/components/ui/progress"
import { Input } from "@/components/ui/input"
import {
  EyeIcon, MessageSquareIcon, SmileIcon, PhoneIcon, TrendingUpIcon, ArrowUpIcon,
  ClockIcon, CircleStopIcon, UsersIcon, HelpCircleIcon, PackageIcon,
  SparklesIcon, SearchIcon, LoaderIcon,
} from "lucide-react"
import * as React from "react"

// --- Mock Data ---
function generateComments(count: number) {
  const names = ["Nguyễn Thị Lan","Trần Văn Minh","Lê Hồng Phúc","Phạm Thị Mai","Hoàng Đức Long","Vũ Thị Hà","Đỗ Minh Tuấn","Ngô Thanh Huyền","Bùi Văn Sơn","Trịnh Thị Ngọc","Lý Quốc Bảo","Đặng Thu Hương","Cao Minh Đức","Tô Thanh Tâm","Hồ Ngọc Anh"]
  const texts = [
    "Áo thun này bao nhiêu tiền chị ơi?","Mình muốn mua quần jean size 30, ship về HCM. SĐT 0901234567",
    "Chất lượng vải thế nào ạ?","Váy hoa đẹp quá! Cho mình 1 cái size M nhé","Giá đắt quá, shop khác rẻ hơn",
    "Mua 2 cái được giảm giá không?","Túi xách có mấy màu? 0987654321","Kính mát chống UV không ạ?",
    "Ship tỉnh mất mấy ngày?","Chốt đơn áo thun trắng size L! Đà Nẵng","Có size XXL không shop?",
    "Hàng có sẵn không hay phải đợi?","Màu đen còn không ạ?","Đẹp quá, cho mình 2 cái nhé",
    "Chất liệu cotton 100% hả shop?","Giày size 42 còn không?","Mình ở Hà Nội ship mấy ngày?",
  ]
  const sentiments: ("positive"|"neutral"|"negative")[] = ["positive","positive","positive","neutral","negative","positive","neutral"]
  return Array.from({length: count}, (_, i) => ({
    id: i + 1,
    user: names[i % names.length],
    text: texts[i % texts.length],
    time: `${Math.max(1, i * 3)} giây trước`,
    sentiment: sentiments[i % sentiments.length],
    hasPhone: i % 7 === 1 || i % 11 === 6,
  }))
}

const allComments = generateComments(200)

const topProducts = [
  { name: "Áo thun basic cotton", image: "https://picsum.photos/seed/tshirt/80/80", mentions: 342, sentiment: 85, questions: 45 },
  { name: "Váy hoa mùa hè", image: "https://picsum.photos/seed/dress/80/80", mentions: 287, sentiment: 92, questions: 32 },
  { name: "Quần jean slim fit", image: "https://picsum.photos/seed/jeans/80/80", mentions: 198, sentiment: 78, questions: 28 },
  { name: "Túi xách da PU", image: "https://picsum.photos/seed/bag/80/80", mentions: 156, sentiment: 88, questions: 19 },
  { name: "Giày sneaker trắng", image: "https://picsum.photos/seed/sneaker/80/80", mentions: 124, sentiment: 71, questions: 22 },
  { name: "Kính mát thời trang", image: "https://picsum.photos/seed/glasses/80/80", mentions: 112, sentiment: 80, questions: 15 },
  { name: "Nón bucket unisex", image: "https://picsum.photos/seed/hat/80/80", mentions: 98, sentiment: 75, questions: 12 },
  { name: "Dây chuyền bạc", image: "https://picsum.photos/seed/necklace/80/80", mentions: 87, sentiment: 90, questions: 10 },
  { name: "Balo du lịch", image: "https://picsum.photos/seed/backpack/80/80", mentions: 76, sentiment: 82, questions: 8 },
  { name: "Đồng hồ thông minh", image: "https://picsum.photos/seed/watch/80/80", mentions: 65, sentiment: 88, questions: 14 },
  { name: "Áo khoác dù", image: "https://picsum.photos/seed/jacket/80/80", mentions: 54, sentiment: 73, questions: 9 },
  { name: "Sandal quai hậu", image: "https://picsum.photos/seed/sandal/80/80", mentions: 48, sentiment: 79, questions: 7 },
  { name: "Ví cầm tay nam", image: "https://picsum.photos/seed/wallet/80/80", mentions: 42, sentiment: 85, questions: 5 },
  { name: "Thắt lưng da bò", image: "https://picsum.photos/seed/belt/80/80", mentions: 38, sentiment: 77, questions: 6 },
  { name: "Mũ lưỡi trai", image: "https://picsum.photos/seed/cap/80/80", mentions: 31, sentiment: 81, questions: 4 },
]

const topQuestions = [
  { question: "Giá bao nhiêu?", count: 89, product: "Chung" },
  { question: "Có size nào?", count: 67, product: "Áo thun, Quần jean" },
  { question: "Ship mất mấy ngày?", count: 54, product: "Chung" },
  { question: "Có bảo hành không?", count: 38, product: "Giày sneaker" },
  { question: "Mua 2 giảm giá không?", count: 31, product: "Chung" },
  { question: "Chất liệu gì?", count: 28, product: "Áo thun, Váy hoa" },
  { question: "Có màu khác không?", count: 25, product: "Túi xách da PU" },
  { question: "Mặc có nóng không?", count: 22, product: "Áo thun basic cotton" },
  { question: "Đổi trả được không?", count: 20, product: "Chung" },
  { question: "Có COD không?", count: 18, product: "Chung" },
  { question: "Size chart ở đâu?", count: 16, product: "Quần jean, Váy hoa" },
  { question: "Hàng Việt Nam hay TQ?", count: 14, product: "Áo thun basic cotton" },
  { question: "Giặt máy được không?", count: 12, product: "Váy hoa mùa hè" },
  { question: "Có hộp đựng không?", count: 10, product: "Giày sneaker" },
  { question: "Combo mua 3 giá sao?", count: 9, product: "Chung" },
]

const potentialCustomers = [
  { name: "Trần Văn Minh", phone: "0901234567", address: "HCM", product: "Quần jean slim fit", comment: "Mua size 30, ship HCM" },
  { name: "Đỗ Minh Tuấn", phone: "0987654321", address: "", product: "Túi xách da PU", comment: "Hỏi mấy màu" },
  { name: "Phạm Thị Mai", phone: "", address: "123 Lê Lợi Q1", product: "Váy hoa mùa hè", comment: "Mua size M" },
  { name: "Trịnh Thị Ngọc", phone: "", address: "Đà Nẵng", product: "Áo thun basic cotton", comment: "Chốt đơn size L" },
  { name: "Lý Quốc Bảo", phone: "0912345678", address: "Bình Dương", product: "Giày sneaker trắng", comment: "Mua size 42" },
  { name: "Đặng Thu Hương", phone: "", address: "Cần Thơ", product: "Váy hoa mùa hè", comment: "Mua 2 cái size S" },
  { name: "Cao Minh Đức", phone: "0978123456", address: "Hà Nội", product: "Áo thun basic cotton", comment: "Chốt 3 cái size L" },
  { name: "Ngô Thanh Tùng", phone: "0933456789", address: "Hải Phòng", product: "Kính mát thời trang", comment: "Lấy 2 cái ship Hải Phòng" },
  { name: "Vũ Thị Lan", phone: "", address: "Nghệ An", product: "Nón bucket unisex", comment: "Cho mình 1 cái màu đen" },
  { name: "Hoàng Đức Long", phone: "0966789012", address: "HCM", product: "Balo du lịch", comment: "Mua 1 cái, có giảm giá không?" },
  { name: "Bùi Minh Châu", phone: "", address: "Bình Định", product: "Dây chuyền bạc", comment: "Hỏi có hộp tặng không" },
  { name: "Đinh Văn Hải", phone: "0945678901", address: "Long An", product: "Đồng hồ thông minh", comment: "Chốt 1 cái ship Long An" },
  { name: "Lê Thị Hồng", phone: "", address: "Quảng Ninh", product: "Sandal quai hậu", comment: "Size 37 còn không?" },
  { name: "Phan Quốc Việt", phone: "0923456789", address: "Đắk Lắk", product: "Áo khoác dù", comment: "Mua size XL màu xanh" },
  { name: "Trương Thị Yến", phone: "", address: "Huế", product: "Ví cầm tay nam", comment: "Lấy 1 cái tặng chồng" },
]

function InfiniteScrollSentinel({ onLoadMore }: { onLoadMore: () => void }) {
  const ref = React.useRef<HTMLDivElement>(null)
  const [loading, setLoading] = React.useState(false)

  React.useEffect(() => {
    const el = ref.current
    if (!el) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && !loading) {
          setLoading(true)
          setTimeout(() => {
            onLoadMore()
            setLoading(false)
          }, 400)
        }
      },
      { threshold: 0.1 }
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [onLoadMore, loading])

  return (
    <div ref={ref} className="flex items-center justify-center py-4">
      <LoaderIcon className="size-5 animate-spin text-muted-foreground" />
      <span className="ml-2 text-sm text-muted-foreground">Đang tải...</span>
    </div>
  )
}

function FadeScrollArea({ children, className }: { children: React.ReactNode; className?: string }) {
  const scrollRef = React.useRef<HTMLDivElement>(null)
  const [showTopFade, setShowTopFade] = React.useState(false)
  const [showBottomFade, setShowBottomFade] = React.useState(false)

  const updateFades = React.useCallback(() => {
    const el = scrollRef.current
    if (!el) return
    setShowTopFade(el.scrollTop > 8)
    setShowBottomFade(el.scrollTop + el.clientHeight < el.scrollHeight - 8)
  }, [])

  React.useEffect(() => {
    const el = scrollRef.current
    if (!el) return
    updateFades()
    el.addEventListener("scroll", updateFades, { passive: true })
    const ro = new ResizeObserver(updateFades)
    ro.observe(el)
    return () => { el.removeEventListener("scroll", updateFades); ro.disconnect() }
  }, [updateFades])

  const scrollToTop = () => {
    scrollRef.current?.scrollTo({ top: 0, behavior: "smooth" })
  }

  return (
    <div className={`relative flex-1 min-h-0 ${className ?? ""}`}>
      <div ref={scrollRef} className="h-full overflow-y-auto">
        {children}
      </div>
      {showTopFade && (
        <div className="pointer-events-none absolute inset-x-0 top-0 h-6 bg-gradient-to-b from-card to-transparent z-10" />
      )}
      {showBottomFade && (
        <div className="pointer-events-none absolute inset-x-0 bottom-0 h-6 bg-gradient-to-t from-card to-transparent z-10" />
      )}
      {showTopFade && (
        <button
          onClick={scrollToTop}
          className="absolute bottom-3 right-3 z-20 flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-all hover:bg-primary/90 hover:scale-110 active:scale-95"
          title="Cuộn lên đầu"
        >
          <ArrowUpIcon className="size-4" />
        </button>
      )}
    </div>
  )
}

function SentimentBadge({ sentiment }: { sentiment: string }) {
  const config: Record<string, { label: string; variant: "default" | "secondary" | "destructive" }> = {
    positive: { label: "Tích cực", variant: "default" },
    neutral: { label: "Trung lập", variant: "secondary" },
    negative: { label: "Tiêu cực", variant: "destructive" },
  }
  const c = config[sentiment] ?? config.neutral
  return <Badge variant={c.variant} className="text-xs">{c.label}</Badge>
}

// --- Sub-components for each tab ---

function CommentsPanel() {
  const BATCH = 50
  const [filter, setFilter] = React.useState("all")
  const [search, setSearch] = React.useState("")
  const [visibleCount, setVisibleCount] = React.useState(BATCH)

  const filtered = allComments.filter((c) => {
    if (filter === "question" && !c.text.includes("?")) return false
    if ((filter === "positive" || filter === "negative") && c.sentiment !== filter) return false
    if (search && !c.text.toLowerCase().includes(search.toLowerCase()) && !c.user.toLowerCase().includes(search.toLowerCase())) return false
    return true
  })

  const visible = filtered.slice(0, visibleCount)
  const hasMore = visibleCount < filtered.length

  return (
    <Card className="h-full flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>Bình luận Realtime</CardTitle>
            <CardDescription>
              Hiển thị {visible.length} / {filtered.length} bình luận (tổng: {allComments.length})
            </CardDescription>
          </div>
          <Badge variant="destructive" className="gap-1">
            <span className="relative flex size-2">
              <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
              <span className="relative inline-flex size-2 rounded-full bg-current" />
            </span>
            Live
          </Badge>
        </div>
        <div className="flex items-center gap-2 pt-2">
          <div className="relative flex-1">
            <SearchIcon className="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input placeholder="Tìm trong bình luận..." value={search} onChange={(e) => { setSearch(e.target.value); setVisibleCount(BATCH) }} className="pl-9 h-8 text-sm" />
          </div>
        </div>
        <div className="flex flex-wrap gap-1.5 pt-2">
          {([
            { key: "all", label: "Tất cả", count: allComments.length },
            { key: "question", label: "Hỏi", count: allComments.filter(c => c.text.includes("?")).length },
            { key: "positive", label: "Tích cực", count: allComments.filter(c => c.sentiment === "positive").length },
            { key: "negative", label: "Tiêu cực", count: allComments.filter(c => c.sentiment === "negative").length },
          ] as const).map((tab) => (
            <button
              key={tab.key}
              onClick={() => { setFilter(tab.key); setVisibleCount(BATCH) }}
              className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors ${
                filter === tab.key
                  ? "border-primary bg-primary/10 text-primary"
                  : "border-transparent bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground"
              }`}
            >
              {tab.label}
              <span className={`tabular-nums rounded-full px-1.5 py-0.5 text-[10px] leading-none font-semibold ${
                filter === tab.key ? "bg-primary/20" : "bg-muted"
              }`}>
                {tab.count}
              </span>
            </button>
          ))}
        </div>
      </CardHeader>
      <FadeScrollArea>
          <div className="space-y-2 px-4">
            {visible.map((comment) => (
              <div key={comment.id} className="flex items-start gap-2.5 rounded-lg border p-2.5">
                <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                  {comment.user.charAt(0)}
                </div>
                <div className="flex-1 min-w-0 space-y-0.5">
                  <div className="flex items-center gap-1.5 flex-wrap">
                    <span className="text-sm font-medium">{comment.user}</span>
                    <SentimentBadge sentiment={comment.sentiment} />
                    {comment.hasPhone && (
                      <Badge variant="outline" className="gap-1 text-xs"><PhoneIcon className="size-3" />SĐT</Badge>
                    )}
                  </div>
                  <p className="text-sm text-muted-foreground break-words">{comment.text}</p>
                  <p className="text-xs text-muted-foreground/60">{comment.time}</p>
                </div>
              </div>
            ))}
          </div>
          {hasMore && <InfiniteScrollSentinel onLoadMore={() => setVisibleCount((p) => p + BATCH)} />}
      </FadeScrollArea>
    </Card>
  )
}

function ProductsPanel() {
  return (
    <Card className="h-full flex flex-col">
      <CardHeader>
        <CardTitle>Sản phẩm được nhắc đến</CardTitle>
        <CardDescription>Xếp hạng theo số lượt nhắc trong bình luận (cập nhật realtime)</CardDescription>
      </CardHeader>
      <div className="px-4">
        <table className="w-full text-sm">
          <thead className="[&_tr]:border-b">
            <tr className="border-b">
              <th className="h-10 w-10 px-2 text-left font-medium text-foreground">#</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Sản phẩm</th>
              <th className="h-10 px-2 text-right font-medium text-foreground">Lượt nhắc</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Sentiment</th>
              <th className="h-10 px-2 text-right font-medium text-foreground">Câu hỏi</th>
            </tr>
          </thead>
        </table>
      </div>
      <FadeScrollArea>
        <div className="px-4">
          <table className="w-full text-sm">
            <tbody className="[&_tr:last-child]:border-0">
              {topProducts.map((product, i) => (
                <tr key={product.name} className="border-b transition-colors hover:bg-muted/50">
                  <td className="w-10 p-2 font-bold text-muted-foreground">{i + 1}</td>
                  <td className="p-2">
                    <div className="flex items-center gap-2.5">
                      <img src={product.image} alt={product.name} className="size-9 rounded-md object-cover" />
                      <span className="font-medium">{product.name}</span>
                    </div>
                  </td>
                  <td className="p-2 text-right">{product.mentions}</td>
                  <td className="p-2">
                    <div className="flex items-center gap-2">
                      <Progress value={product.sentiment} className="h-2 w-20" />
                      <span className="text-xs text-muted-foreground">{product.sentiment}%</span>
                    </div>
                  </td>
                  <td className="p-2 text-right">{product.questions}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </FadeScrollArea>
    </Card>
  )
}

function QuestionsPanel() {
  return (
    <Card className="h-full flex flex-col">
      <CardHeader>
        <CardTitle>Câu hỏi thường gặp</CardTitle>
        <CardDescription>Phân loại và gom nhóm câu hỏi bởi AI</CardDescription>
      </CardHeader>
      <div className="px-4">
        <table className="w-full text-sm">
          <thead className="[&_tr]:border-b">
            <tr className="border-b">
              <th className="h-10 w-10 px-2 text-left font-medium text-foreground">#</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Câu hỏi</th>
              <th className="h-10 px-2 text-right font-medium text-foreground">Số lần</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">SP liên quan</th>
            </tr>
          </thead>
        </table>
      </div>
      <FadeScrollArea>
        <div className="px-4">
          <table className="w-full text-sm">
            <tbody className="[&_tr:last-child]:border-0">
              {topQuestions.map((q, i) => (
                <tr key={q.question} className="border-b transition-colors hover:bg-muted/50">
                  <td className="w-10 p-2 font-bold text-muted-foreground">{i + 1}</td>
                  <td className="p-2 font-medium">{q.question}</td>
                  <td className="p-2 text-right"><Badge variant="secondary">{q.count}</Badge></td>
                  <td className="p-2 text-muted-foreground text-sm">{q.product}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </FadeScrollArea>
    </Card>
  )
}

function CustomersPanel() {
  const [search, setSearch] = React.useState("")
  const filtered = potentialCustomers.filter((c) =>
    !search || c.name.toLowerCase().includes(search.toLowerCase()) || c.phone.includes(search) || c.product.toLowerCase().includes(search.toLowerCase())
  )
  return (
    <Card className="h-full flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>Khách hàng tiềm năng</CardTitle>
            <CardDescription>Trích xuất SĐT/địa chỉ từ bình luận · {filtered.length} khách</CardDescription>
          </div>
        </div>
        <div className="relative max-w-xs pt-2">
          <SearchIcon className="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input placeholder="Tìm theo tên, SĐT, SP..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 h-8 text-sm" />
        </div>
      </CardHeader>
      <div className="px-4">
        <table className="w-full text-sm">
          <thead className="[&_tr]:border-b">
            <tr className="border-b">
              <th className="h-10 px-2 text-left font-medium text-foreground">Tên</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">SĐT</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Địa chỉ</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">SP quan tâm</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Nội dung</th>
            </tr>
          </thead>
        </table>
      </div>
      <FadeScrollArea>
        <div className="px-4">
          <table className="w-full text-sm">
            <tbody className="[&_tr:last-child]:border-0">
              {filtered.map((c, i) => (
                <tr key={i} className="border-b transition-colors hover:bg-muted/50">
                  <td className="p-2 font-medium">{c.name}</td>
                  <td className="p-2">
                    {c.phone ? <Badge variant="outline" className="gap-1"><PhoneIcon className="size-3" />{c.phone}</Badge> : <span className="text-muted-foreground">—</span>}
                  </td>
                  <td className="p-2">{c.address || <span className="text-muted-foreground">—</span>}</td>
                  <td className="p-2"><Badge variant="secondary">{c.product}</Badge></td>
                  <td className="p-2 text-sm text-muted-foreground max-w-[180px] truncate">{c.comment}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </FadeScrollArea>
    </Card>
  )
}

function AIInsightsPanel() {
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><SparklesIcon className="size-5" />Tóm tắt AI</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-muted-foreground">
          <p><strong className="text-foreground">Phiên live đang diễn ra tốt</strong> với tỷ lệ tương tác cao. Sản phẩm "Áo thun basic cotton" được quan tâm nhiều nhất với 342 lượt nhắc.</p>
          <p><strong className="text-foreground">Cảm xúc tích cực chiếm 78%</strong>, chủ yếu liên quan đến chất lượng sản phẩm và giá cả hợp lý.</p>
          <p><strong className="text-foreground">Gợi ý:</strong> Nên trả lời câu hỏi về "size" và "ship". Có thể đưa ra combo giảm giá vì nhiều khách hỏi về mua nhiều.</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle>Cảnh báo AI</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3">
            <p className="text-sm font-medium text-yellow-600">⚠️ Câu hỏi chưa trả lời</p>
            <p className="text-xs text-muted-foreground mt-1">28 câu hỏi về "chất liệu" chưa được trả lời trong 5 phút qua.</p>
          </div>
          <div className="rounded-lg border border-green-500/20 bg-green-500/5 p-3">
            <p className="text-sm font-medium text-green-600">✅ Sản phẩm hot</p>
            <p className="text-xs text-muted-foreground mt-1">"Váy hoa mùa hè" có sentiment 92% — bán chạy nhất phiên này.</p>
          </div>
          <div className="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3">
            <p className="text-sm font-medium text-blue-600">💡 Cơ hội upsell</p>
            <p className="text-xs text-muted-foreground mt-1">31 khách hỏi "mua 2 giảm giá không" — nên tạo combo giảm giá ngay.</p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

// --- Main Page ---
export default function LivesShow() {
  return (
    <AuthenticatedLayout>
      <Head title="Flash Sale Mùa Hè — Live" />
      <div className="flex flex-1 max-h-svh flex-col overflow-hidden">
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

      <div className="flex flex-1 flex-col gap-2 px-4 pb-4 overflow-hidden">
        {/* Session Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-lg font-bold tracking-tight">Flash Sale Mùa Hè</h1>
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
                <ClockIcon className="size-3.5" />1h 23m
              </span>
            </div>
          </div>
          <Button variant="destructive"><CircleStopIcon className="mr-2 size-4" />Kết thúc phiên</Button>
        </div>

        {/* 2-Column Layout: Video + KPI (left) | Tabs (right) — stacks on mobile */}
        <div className="grid gap-4 xl:grid-cols-[360px_1fr] flex-1 min-h-0">
          {/* Left Column: Video + KPIs */}
          <div className="flex flex-col gap-3 overflow-y-auto">
            {/* Video Embed */}
            <Card className="overflow-hidden py-0 gap-0">
              <CardContent className="p-0">
                <div className="aspect-video w-full bg-muted/50 flex items-center justify-center relative overflow-hidden">
                  {/* Placeholder for video embed iframe */}
                  <div className="absolute inset-0 bg-gradient-to-br from-primary/5 to-primary/10" />
                  <div className="text-center z-10 space-y-2">
                    <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10">
                      <EyeIcon className="size-6 text-primary" />
                    </div>
                    <p className="text-sm font-medium">Đang phát trực tiếp</p>
                    <p className="text-xs text-muted-foreground">Video embed từ Facebook Live</p>
                    <Badge variant="destructive" className="gap-1">
                      <span className="relative flex size-1.5">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                        <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                      </span>
                      3,247 đang xem
                    </Badge>
                  </div>
                </div>
                {/* Stats bar at bottom of video card */}
                <div className="grid grid-cols-3 divide-x border-t">
                  <div className="p-2.5 text-center">
                    <div className="text-lg font-bold">3,247</div>
                    <p className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <EyeIcon className="size-3" />Lượt xem
                    </p>
                  </div>
                  <div className="p-2.5 text-center">
                    <div className="text-lg font-bold">523</div>
                    <p className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <MessageSquareIcon className="size-3" />Bình luận
                    </p>
                  </div>
                  <div className="p-2.5 text-center">
                    <div className="text-lg font-bold">12</div>
                    <p className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <PhoneIcon className="size-3" />KH tiềm năng
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Sentiment AI Card */}
            <Card size="sm">
              <CardHeader className="px-3 pt-0">
                <CardTitle className="flex items-center gap-2 text-xs">
                  <SmileIcon className="size-3.5" />
                  Cảm xúc AI
                </CardTitle>
              </CardHeader>
              <CardContent className="px-3">
                <div className="flex items-start gap-3">
                  <div>
                    <div className="text-2xl font-bold text-green-500">78%</div>
                    <p className="flex items-center gap-1 text-xs text-muted-foreground">
                      <TrendingUpIcon className="size-3 text-green-500" />+12.5%
                    </p>
                  </div>
                  <div className="flex-1 space-y-1 text-xs">
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-green-500" />Tích cực</span>
                      <span className="font-medium">78%</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-amber-500" />Trung lập</span>
                      <span className="font-medium">15%</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-red-500" />Tiêu cực</span>
                      <span className="font-medium">7%</span>
                    </div>
                  </div>
                </div>
                <div className="mt-2 flex h-2 w-full overflow-hidden rounded-full">
                  <div className="bg-green-500" style={{ width: "78%" }} />
                  <div className="bg-amber-500" style={{ width: "15%" }} />
                  <div className="bg-red-500" style={{ width: "7%" }} />
                </div>
              </CardContent>
            </Card>

            {/* Top Keywords Card */}
            <Card size="sm" className="flex-1 overflow-hidden">
              <CardHeader className="px-3 pt-0">
                <CardTitle className="text-xs">🔍 Từ khóa được nhắc nhiều</CardTitle>
              </CardHeader>
              <CardContent className="px-3 flex-1 min-h-0 overflow-hidden">
                <div className="flex flex-wrap gap-1.5 overflow-hidden h-full relative">
                  {[
                    { keyword: "size", count: 154 },
                    { keyword: "màu sắc", count: 138 },
                    { keyword: "giá", count: 132 },
                    { keyword: "freeship", count: 125 },
                    { keyword: "bảo hành", count: 120 },
                    { keyword: "độ bền", count: 112 },
                    { keyword: "so sánh", count: 109 },
                    { keyword: "chất liệu", count: 99 },
                    { keyword: "tư vấn", count: 95 },
                    { keyword: "giao hỏa tốc", count: 92 },
                  ].map((item) => (
                    <div key={item.keyword} className="flex items-center gap-1 rounded-md bg-muted/60 px-2 py-0.5 text-xs">
                      <span>{item.keyword}</span>
                      <span className="font-bold tabular-nums">{item.count}</span>
                    </div>
                  ))}
                  <div className="flex items-center rounded-md bg-muted/40 px-2 py-0.5 text-xs text-muted-foreground">...</div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Right Column: Tabs */}
          <Tabs defaultValue="comments" className="min-w-0 flex flex-col min-h-0">
            <TabsList className="flex-wrap">
              <TabsTrigger value="comments" className="gap-1.5"><MessageSquareIcon className="size-3.5" />Bình luận</TabsTrigger>
              <TabsTrigger value="products" className="gap-1.5"><PackageIcon className="size-3.5" />Sản phẩm</TabsTrigger>
              <TabsTrigger value="questions" className="gap-1.5"><HelpCircleIcon className="size-3.5" />Câu hỏi</TabsTrigger>
              <TabsTrigger value="customers" className="gap-1.5"><UsersIcon className="size-3.5" />KH tiềm năng</TabsTrigger>
              <TabsTrigger value="ai" className="gap-1.5"><SparklesIcon className="size-3.5" />AI</TabsTrigger>
            </TabsList>
            <TabsContent value="comments" className="flex-1 min-h-0 overflow-y-auto"><CommentsPanel /></TabsContent>
            <TabsContent value="products" className="flex-1 min-h-0 overflow-y-auto"><ProductsPanel /></TabsContent>
            <TabsContent value="questions" className="flex-1 min-h-0 overflow-y-auto"><QuestionsPanel /></TabsContent>
            <TabsContent value="customers" className="flex-1 min-h-0 overflow-y-auto"><CustomersPanel /></TabsContent>
            <TabsContent value="ai" className="flex-1 min-h-0 overflow-y-auto"><AIInsightsPanel /></TabsContent>
          </Tabs>
        </div>
      </div>
      </div>
    </AuthenticatedLayout>
  )
}
