import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle, EmptyDescription } from "@/components/ui/empty"
import { Head, router, usePage } from "@inertiajs/react"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Progress } from "@/components/ui/progress"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ChartContainer, ChartTooltip, ChartTooltipContent, ChartLegend, ChartLegendContent, type ChartConfig } from "@/components/ui/chart"
import { Area, AreaChart, Bar, BarChart, CartesianGrid, XAxis, YAxis, Pie, PieChart, Label as RechartsLabel } from "recharts"
import {
  EyeIcon, MessageSquareIcon, SmileIcon, PhoneIcon, TrendingUpIcon, TrendingDownIcon, ArrowUpIcon, ShoppingCartIcon,
  ClockIcon, CircleStopIcon, UsersIcon, HelpCircleIcon, PackageIcon, BarChart3Icon,
  SparklesIcon, SearchIcon, LoaderIcon, MinusIcon, CopyIcon, CheckIcon,
  BellRingIcon, BellOffIcon, DownloadIcon, ClipboardListIcon, XIcon,
  AlertTriangleIcon, FlameIcon, LightbulbIcon, HeartCrackIcon, ZapIcon,
  PinIcon, PinOffIcon, ClipboardCopyIcon, FileTextIcon, MapPinIcon, TruckIcon,
} from "lucide-react"
import * as React from "react"

// --- Types ---
interface SessionData {
  id: number
  name: string
  platform: string
  status: string
  tiktok_username: string
  tiktok_session_id: string | null
  duration: string
  started_at: string | null
  ended_at: string | null
  error_message: string | null
  products: { id: number; name: string; sku: string; price: number; image: string | null }[]
  keywords: string[]
}

interface StatsData {
  total_views: number
  total_comments: number
  total_likes: number
  total_gifts: number
  total_follows: number
  total_shares: number
  viewer_count: number
  leads_count: number
  sentiment_positive: number
  sentiment_neutral: number
  sentiment_negative: number
}

interface CommentData {
  id: number
  user: string
  unique_id: string | null
  avatar_url: string | null
  text: string
  time: string
  event_at: string | null
  sentiment: string
  intent_tag: string | null
  question_tag: string | null
  product_tag: string | null
  has_phone: boolean
}

interface TopProduct {
  name: string
  mentions: number
  sentiment: number
  questions: number
}

interface PotentialCustomer {
  name: string
  phone: string
  product: string
  comment: string
  time: string
}

interface TopQuestion {
  question: string
  count: number
  product: string
}

interface PageProps {
  session: SessionData
  stats: StatsData | null
  comments: CommentData[]
  topProducts: TopProduct[]
  potentialCustomers: PotentialCustomer[]
  topQuestions: TopQuestion[]
}

// --- Context for sharing data across sub-components ---
const LiveContext = React.createContext<{
  session: SessionData
  stats: StatsData
  comments: CommentData[]
  topProducts: TopProduct[]
  potentialCustomers: PotentialCustomer[]
  topQuestions: TopQuestion[]
}>(null!)

function useLiveData() {
  return React.useContext(LiveContext)
}

// --- Sound Alert System ---
function playOrderChime() {
  try {
    const ctx = new AudioContext()
    const now = ctx.currentTime
    // Pleasant two-tone chime
    const freqs = [523.25, 659.25, 783.99] // C5, E5, G5
    freqs.forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.type = "sine"
      osc.frequency.value = freq
      gain.gain.setValueAtTime(0, now + i * 0.12)
      gain.gain.linearRampToValueAtTime(0.15, now + i * 0.12 + 0.05)
      gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.12 + 0.4)
      osc.connect(gain).connect(ctx.destination)
      osc.start(now + i * 0.12)
      osc.stop(now + i * 0.12 + 0.5)
    })
    setTimeout(() => ctx.close(), 2000)
  } catch { /* AudioContext not available */ }
}

type OrderAlert = {
  id: number
  user: string
  product: string
  comment: string
  time: number
}

function InlineOrderAlert({ alerts, dismiss }: { alerts: OrderAlert[]; dismiss: (id: number) => void }) {
  const latest = alerts[0]
  const DURATION = 5000 // auto-dismiss after 5s

  React.useEffect(() => {
    const timer = setTimeout(() => dismiss(latest.id), DURATION)
    return () => clearTimeout(timer)
  }, [latest.id, dismiss])

  return (
    <div key={latest.id} className="animate-in slide-in-from-top-2 fade-in duration-300 flex items-center gap-2.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 backdrop-blur-sm px-3 py-1.5 max-w-md w-full relative overflow-hidden">
      {/* Progress bar — synced to DURATION */}
      <div
        key={`progress-${latest.id}`}
        className="absolute bottom-0 left-0 h-[2px] bg-emerald-500/50"
        style={{ animation: `shrink ${DURATION}ms linear forwards` }}
      />

      <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">
        <ShoppingCartIcon className="size-3.5 text-emerald-500" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-semibold text-emerald-400">🛒 Chốt đơn mới!</p>
        <p className="text-[11px] text-muted-foreground truncate">
          <span className="font-medium text-foreground">{latest.user}</span> — {latest.product}
        </p>
      </div>
      {alerts.length > 1 && (
        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-[10px] font-bold text-emerald-400 tabular-nums">
          +{alerts.length - 1}
        </span>
      )}
      <button onClick={() => dismiss(latest.id)} className="shrink-0 rounded p-0.5 hover:bg-muted transition-colors">
        <XIcon className="size-3 text-muted-foreground" />
      </button>
    </div>
  )
}

function useOrderAlerts(soundEnabled: boolean, comments: CommentData[]) {
  const [alerts, setAlerts] = React.useState<OrderAlert[]>([])
  const alertIdRef = React.useRef(0)
  const seenOrderIdsRef = React.useRef<Set<number>>(new Set())
  const initializedRef = React.useRef(false)

  React.useEffect(() => {
    const orderComments = comments.filter(c => c.intent_tag === "Chốt đơn")
    if (!initializedRef.current) {
      // Lần load đầu — đánh dấu tất cả đơn hiện tại, không alert
      orderComments.forEach(c => seenOrderIdsRef.current.add(c.id))
      initializedRef.current = true
      return
    }
    const newOrders = orderComments.filter(c => !seenOrderIdsRef.current.has(c.id))
    if (newOrders.length > 0) {
      const newAlerts = newOrders.map(c => ({
        id: ++alertIdRef.current,
        user: c.user,
        product: c.product_tag || c.text,
        comment: c.text,
        time: Date.now(),
      }))
      setAlerts(prev => [...newAlerts, ...prev].slice(0, 5))
      if (soundEnabled) playOrderChime()
      newOrders.forEach(c => seenOrderIdsRef.current.add(c.id))
    }
  }, [comments, soundEnabled])

  const dismiss = React.useCallback((id: number) => {
    setAlerts(prev => prev.filter(a => a.id !== id))
  }, [])

  return { alerts, dismiss }
}

// --- Export Utilities ---
function exportLeadsCSV(customers: PotentialCustomer[]) {
  const header = "Tên,SĐT,Sản phẩm,Nội dung"
  const rows = customers.map((c) =>
    [c.name, c.phone || "", c.product, c.comment]
      .map((v) => `"${v.replace(/"/g, '""')}"`)
      .join(",")
  )
  const csv = [header, ...rows].join("\n")
  const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" })
  const url = URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = `leads_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function copyLeadsToClipboard(customers: PotentialCustomer[]): string {
  const text = customers
    .map((c, i) => `${i + 1}. ${c.name} | ${c.phone || "—"} | ${c.product} | ${c.comment}`)
    .join("\n")
  navigator.clipboard.writeText(text)
  return text
}




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
  const { comments: allComments } = useLiveData()
  const BATCH = 50
  const [filter, setFilter] = React.useState("all")
  const [search, setSearch] = React.useState("")
  const [visibleCount, setVisibleCount] = React.useState(BATCH)
  const [pinnedIds, setPinnedIds] = React.useState<Set<number>>(new Set())
  const [markedOrderIds, setMarkedOrderIds] = React.useState<Set<number>>(new Set())
  const [copiedId, setCopiedId] = React.useState<number | null>(null)

  const togglePin = (id: number) => {
    setPinnedIds((prev) => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }
  const toggleOrder = (id: number) => {
    setMarkedOrderIds((prev) => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }
  const copyComment = (text: string, id: number) => {
    navigator.clipboard.writeText(text)
    setCopiedId(id)
    setTimeout(() => setCopiedId(null), 1500)
  }

  const filtered = allComments.filter((c) => {
    if (filter === "pinned" && !pinnedIds.has(c.id)) return false
    if (filter === "order" && !markedOrderIds.has(c.id) && c.intent_tag !== "Chốt đơn") return false
    if (filter === "question" && !c.question_tag && c.intent_tag !== "Hỏi thông tin") return false
    if (filter === "support" && c.intent_tag !== "Yêu cầu hỗ trợ" && c.intent_tag !== "Phản hồi SP") return false
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
            { key: "pinned", label: "📌 Ghim", count: pinnedIds.size },
            { key: "order", label: "🛒 Chốt đơn", count: allComments.filter(c => c.intent_tag === "Chốt đơn" || markedOrderIds.has(c.id)).length },
            { key: "question", label: "❓ Hỏi", count: allComments.filter(c => c.question_tag || c.intent_tag === "Hỏi thông tin").length },
            { key: "support", label: "🔔 Phản hồi", count: allComments.filter(c => c.intent_tag === "Yêu cầu hỗ trợ" || c.intent_tag === "Phản hồi SP").length },
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
          <div className="divide-y px-4">
            {visible.length === 0 ? (
              <Empty className="py-12">
                <EmptyHeader>
                  <EmptyMedia variant="icon">
                    {filter === "order" ? <ShoppingCartIcon /> :
                     filter === "question" ? <HelpCircleIcon /> :
                     filter === "support" ? <BellRingIcon /> :
                     filter === "pinned" ? <PinIcon /> :
                     filter === "positive" ? <SmileIcon /> :
                     filter === "negative" ? <HeartCrackIcon /> :
                     search ? <SearchIcon /> :
                     <MessageSquareIcon />}
                  </EmptyMedia>
                  <EmptyTitle>
                    {filter === "order" ? "Chưa có đơn hàng" :
                     filter === "question" ? "Chưa có câu hỏi" :
                     filter === "support" ? "Chưa có phản hồi" :
                     filter === "pinned" ? "Chưa ghim bình luận nào" :
                     filter === "positive" ? "Chưa có bình luận tích cực" :
                     filter === "negative" ? "Không có bình luận tiêu cực" :
                     search ? "Không tìm thấy kết quả" :
                     "Chưa có bình luận"}
                  </EmptyTitle>
                  <EmptyDescription>
                    {filter === "order" ? "Khi có người xem chốt đơn, đơn hàng sẽ hiển thị tại đây." :
                     filter === "question" ? "Khi có người hỏi về sản phẩm, câu hỏi sẽ xuất hiện tại đây." :
                     filter === "support" ? "Chưa có ai phản hồi hoặc yêu cầu hỗ trợ." :
                     filter === "pinned" ? "Nhấn vào icon ghim trên bình luận để ghim lại." :
                     filter === "positive" ? "Bình luận tích cực về sản phẩm sẽ hiển thị tại đây." :
                     filter === "negative" ? "Tuyệt vời! Phiên live không có phản hồi tiêu cực. 🎉" :
                     search ? `Không có bình luận nào khớp với "${search}"` :
                     "Bình luận sẽ xuất hiện realtime khi phiên live đang diễn ra."}
                  </EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : visible.map((comment) => {
              const isPinned = pinnedIds.has(comment.id)
              const isOrder = markedOrderIds.has(comment.id) || comment.intent_tag === "Chốt đơn"
              const sentimentColor = isPinned ? "border-l-yellow-500 bg-yellow-500/5" : comment.sentiment === "positive" ? "border-l-emerald-500" : comment.sentiment === "negative" ? "border-l-red-500" : "border-l-muted-foreground/30"
              return (
                <div key={comment.id} className={`group relative flex items-start gap-2.5 border-l-2 py-2.5 pl-3 transition-colors ${sentimentColor}`}>
                  <a
                    href={comment.unique_id ? `https://www.tiktok.com/@${comment.unique_id}` : '#'}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="shrink-0"
                    title={comment.unique_id ? `@${comment.unique_id}` : comment.user}
                  >
                    {comment.avatar_url ? (
                      <img src={comment.avatar_url} alt={comment.user} className="size-7 rounded-full object-cover ring-1 ring-border" loading="lazy" />
                    ) : (
                      <div className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium">
                        {comment.user.charAt(0)}
                      </div>
                    )}
                  </a>
                  <div className="flex-1 min-w-0 space-y-0.5">
                    <div className="flex items-center justify-between gap-2">
                      <div className="flex items-center gap-1.5 min-w-0 flex-wrap">
                        <a
                          href={comment.unique_id ? `https://www.tiktok.com/@${comment.unique_id}` : '#'}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-sm font-medium hover:underline hover:text-primary transition-colors"
                        >{comment.user}</a>
                        <SentimentBadge sentiment={comment.sentiment} />
                        {comment.has_phone && (
                          <Badge variant="outline" className="gap-1 text-[10px] px-1.5 py-0"><PhoneIcon className="size-2.5" />SĐT</Badge>
                        )}
                        {isPinned && <PinIcon className="size-3 text-yellow-500" />}
                        {isOrder && <Badge variant="default" className="text-[10px] px-1.5 py-0 bg-emerald-600">Đơn hàng</Badge>}
                      </div>
                      {/* Time + Quick Actions — stacked to prevent layout shift */}
                      <div className="relative shrink-0 flex items-center">
                        <span className="text-[11px] text-muted-foreground/50 whitespace-nowrap group-hover:invisible">{comment.time}</span>
                        <div className="absolute inset-0 flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          <button
                            onClick={() => togglePin(comment.id)}
                            className={`rounded-md p-1.5 transition-colors ${isPinned ? "text-yellow-500 bg-yellow-500/10" : "text-muted-foreground hover:bg-muted"}`}
                            title={isPinned ? "Bỏ ghim" : "Ghim"}
                          >
                            {isPinned ? <PinOffIcon className="size-[15px]" /> : <PinIcon className="size-[15px]" />}
                          </button>
                          <button
                            onClick={() => toggleOrder(comment.id)}
                            className={`rounded-md p-1.5 transition-colors ${isOrder ? "text-emerald-500 bg-emerald-500/10" : "text-muted-foreground hover:bg-muted"}`}
                            title={isOrder ? "Bỏ đánh dấu" : "Đánh dấu chốt đơn"}
                          >
                            <ShoppingCartIcon className="size-[15px]" />
                          </button>
                          <button
                            onClick={() => copyComment(comment.text, comment.id)}
                            className="rounded-md p-1.5 text-muted-foreground hover:bg-muted transition-colors"
                            title="Copy bình luận"
                          >
                            {copiedId === comment.id ? <CheckIcon className="size-[15px] text-emerald-500" /> : <ClipboardCopyIcon className="size-[15px]" />}
                          </button>
                        </div>
                      </div>
                    </div>
                    <p className="text-sm text-muted-foreground break-words">{comment.text}</p>
                    {(comment.intent_tag || comment.question_tag || comment.product_tag) && (
                      <div className="flex items-center gap-1 flex-wrap pt-0.5">
                        {comment.intent_tag && (
                          <span className={`inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-medium ${
                            comment.intent_tag === "Chốt đơn" ? "text-emerald-600 dark:text-emerald-400 bg-emerald-500/10" :
                            comment.intent_tag === "Hỏi thông tin" ? "text-blue-600 dark:text-blue-400 bg-blue-500/10" :
                            comment.intent_tag === "Phản hồi SP" ? "text-purple-600 dark:text-purple-400 bg-purple-500/10" :
                            comment.intent_tag === "Yêu cầu hỗ trợ" ? "text-red-600 dark:text-red-400 bg-red-500/10" :
                            "text-gray-600 dark:text-gray-400 bg-gray-500/10"
                          }`}>
                            <ShoppingCartIcon className="size-2.5" />{comment.intent_tag}
                          </span>
                        )}
                        {comment.question_tag && (
                          <span className="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-medium text-amber-600 dark:text-amber-400 bg-amber-500/10">
                            <HelpCircleIcon className="size-2.5" />{comment.question_tag}
                          </span>
                        )}
                        {comment.product_tag && (
                          <span className="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-medium text-blue-600 dark:text-blue-400 bg-blue-500/10">
                            <PackageIcon className="size-2.5" />{comment.product_tag}
                          </span>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
          {hasMore && <InfiniteScrollSentinel onLoadMore={() => setVisibleCount((p) => p + BATCH)} />}
      </FadeScrollArea>
    </Card>
  )
}

function ProductsPanel() {
  const { topProducts } = useLiveData()
  return (
    <Card className="h-full flex flex-col">
      <CardHeader>
        <CardTitle>Sản phẩm được nhắc đến</CardTitle>
        <CardDescription>Xếp hạng theo số lượt nhắc trong bình luận (cập nhật realtime)</CardDescription>
      </CardHeader>
      <div className="px-4">
        <table className="w-full table-fixed text-sm">
          <colgroup>
            <col className="w-[6%]" />
            <col className="w-[40%]" />
            <col className="w-[16%]" />
            <col className="w-[24%]" />
            <col className="w-[14%]" />
          </colgroup>
          <thead className="[&_tr]:border-b">
            <tr className="border-b">
              <th className="h-10 px-2 text-left font-medium text-foreground">#</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Sản phẩm</th>
              <th className="h-10 px-2 text-right font-medium text-foreground">Lượt nhắc</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Cảm xúc</th>
              <th className="h-10 px-2 text-right font-medium text-foreground">Câu hỏi</th>
            </tr>
          </thead>
        </table>
      </div>
      <FadeScrollArea>
        <div className="px-4">
          <table className="w-full table-fixed text-sm">
            <colgroup>
              <col className="w-[6%]" />
              <col className="w-[40%]" />
              <col className="w-[16%]" />
              <col className="w-[24%]" />
              <col className="w-[14%]" />
            </colgroup>
            <tbody className="[&_tr:last-child]:border-0">
              {topProducts.length === 0 ? (
                <tr><td colSpan={5}>
                  <Empty className="py-12">
                    <EmptyHeader>
                      <EmptyMedia variant="icon"><PackageIcon /></EmptyMedia>
                      <EmptyTitle>Chưa có sản phẩm</EmptyTitle>
                      <EmptyDescription>Khi người xem nhắc đến sản phẩm, thống kê sẽ hiển thị tại đây.</EmptyDescription>
                    </EmptyHeader>
                  </Empty>
                </td></tr>
              ) : topProducts.map((product, i) => (
                <tr key={product.name} className="border-b transition-colors hover:bg-muted/50">
                  <td className="p-2 font-bold text-muted-foreground">{i + 1}</td>
                  <td className="p-2">
                    <div className="flex items-center gap-2.5">
                      <div className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-xs font-bold text-primary">{product.name.charAt(0)}</div>
                      <span className="font-medium truncate">{product.name}</span>
                    </div>
                  </td>
                  <td className="p-2 text-right">
                    <div className="inline-flex items-center gap-1">
                      {product.mentions}
                    </div>
                  </td>
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
  const { topQuestions } = useLiveData()
  const [copiedIdx, setCopiedIdx] = React.useState<number | null>(null)
  const copyAnswer = (text: string, idx: number) => {
    navigator.clipboard.writeText(text)
    setCopiedIdx(idx)
    setTimeout(() => setCopiedIdx(null), 1500)
  }
  return (
    <Card className="h-full flex flex-col">
      <CardHeader>
        <CardTitle>Câu hỏi thường gặp</CardTitle>
        <CardDescription>Phân loại và gom nhóm câu hỏi bởi AI</CardDescription>
      </CardHeader>
      <div className="px-4">
        <table className="w-full table-fixed text-sm">
          <colgroup>
            <col className="w-[6%]" />
            <col className="w-[28%]" />
            <col className="w-[10%]" />
            <col className="w-[20%]" />
            <col className="w-[36%]" />
          </colgroup>
          <thead className="[&_tr]:border-b">
            <tr className="border-b">
              <th className="h-10 px-2 text-left font-medium text-foreground">#</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Câu hỏi</th>
              <th className="h-10 px-2 text-right font-medium text-foreground">Số lần</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">SP</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Gợi ý trả lời</th>
            </tr>
          </thead>
        </table>
      </div>
      <FadeScrollArea>
        <div className="px-4">
          <table className="w-full table-fixed text-sm">
            <colgroup>
              <col className="w-[6%]" />
              <col className="w-[28%]" />
              <col className="w-[10%]" />
              <col className="w-[20%]" />
              <col className="w-[36%]" />
            </colgroup>
            <tbody className="[&_tr:last-child]:border-0">
              {topQuestions.length === 0 ? (
                <tr><td colSpan={5}>
                  <Empty className="py-12">
                    <EmptyHeader>
                      <EmptyMedia variant="icon"><HelpCircleIcon /></EmptyMedia>
                      <EmptyTitle>Chưa có câu hỏi</EmptyTitle>
                      <EmptyDescription>Khi người xem đặt câu hỏi về sản phẩm, AI sẽ phân loại và hiển thị tại đây.</EmptyDescription>
                    </EmptyHeader>
                  </Empty>
                </td></tr>
              ) : topQuestions.map((q, i) => (
                <tr key={q.question} className="border-b transition-colors hover:bg-muted/50">
                  <td className="p-2 font-bold text-muted-foreground">{i + 1}</td>
                  <td className="p-2 font-medium truncate">{q.question}</td>
                  <td className="p-2 text-right"><Badge variant="secondary">{q.count}</Badge></td>
                  <td className="p-2 text-muted-foreground text-sm truncate">{q.product}</td>
                  <td className="p-2">
                    <span className="text-xs text-muted-foreground">
                      {q.question === "Hỏi giá" ? "Nên báo giá trực tiếp" :
                       q.question === "Hỏi size" ? "Nên tư vấn size" :
                       q.question === "Hỏi màu" ? "Nên show màu sắc" :
                       q.question === "Hỏi tồn kho" ? "Nên check kho" :
                       q.question === "Hỏi ship" ? "Thông báo phí ship" :
                       `${q.count} lần`}
                    </span>
                  </td>
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
  const { potentialCustomers } = useLiveData()
  const [search, setSearch] = React.useState("")
  const [copiedPhone, setCopiedPhone] = React.useState<number | null>(null)
  const [copiedAll, setCopiedAll] = React.useState(false)
  const [orders, setOrders] = React.useState<Record<number, { status: string; note: string; qty: number }>>({})
  const [orderDialog, setOrderDialog] = React.useState<{ open: boolean; customerIdx: number | null }>({ open: false, customerIdx: null })
  const [orderForm, setOrderForm] = React.useState({ qty: 1, note: "", status: "pending" })
  const filtered = potentialCustomers.filter((c) =>
    !search || c.name.toLowerCase().includes(search.toLowerCase()) || c.phone.includes(search) || c.product.toLowerCase().includes(search.toLowerCase())
  )
  const copyPhone = (phone: string, idx: number) => {
    navigator.clipboard.writeText(phone)
    setCopiedPhone(idx)
    setTimeout(() => setCopiedPhone(null), 1500)
  }
  const handleCopyAll = () => {
    copyLeadsToClipboard(filtered)
    setCopiedAll(true)
    setTimeout(() => setCopiedAll(false), 2000)
  }
  const openOrderDialog = (idx: number) => {
    const existing = orders[idx]
    setOrderForm({ qty: existing?.qty ?? 1, note: existing?.note ?? "", status: existing?.status ?? "pending" })
    setOrderDialog({ open: true, customerIdx: idx })
  }
  const saveOrder = () => {
    if (orderDialog.customerIdx === null) return
    setOrders((prev) => ({ ...prev, [orderDialog.customerIdx!]: { ...orderForm } }))
    setOrderDialog({ open: false, customerIdx: null })
  }
  const orderCustomer = orderDialog.customerIdx !== null ? filtered[orderDialog.customerIdx] : null
  return (
    <Card className="h-full flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>Khách hàng tiềm năng</CardTitle>
            <CardDescription>Trích xuất SĐT/địa chỉ từ bình luận · {filtered.length} khách · {Object.keys(orders).length} đơn</CardDescription>
          </div>
          <div className="flex items-center gap-1.5">
            <Button variant="outline" size="sm" className="h-7 gap-1.5 text-xs" onClick={handleCopyAll}>
              {copiedAll ? <CheckIcon className="size-3 text-emerald-500" /> : <ClipboardListIcon className="size-3" />}
              {copiedAll ? "Đã copy" : "Copy tất cả"}
            </Button>
            <Button variant="outline" size="sm" className="h-7 gap-1.5 text-xs" onClick={() => exportLeadsCSV(filtered)}>
              <DownloadIcon className="size-3" />
              Xuất CSV
            </Button>
          </div>
        </div>
        <div className="relative max-w-xs pt-2">
          <SearchIcon className="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input placeholder="Tìm theo tên, SĐT, SP..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 h-8 text-sm" />
        </div>
      </CardHeader>
      <div className="px-4">
        <table className="w-full table-fixed text-sm">
          <colgroup>
            <col className="w-[16%]" />
            <col className="w-[16%]" />
            <col className="w-[14%]" />
            <col className="w-[18%]" />
            <col className="w-[22%]" />
            <col className="w-[14%]" />
          </colgroup>
          <thead className="[&_tr]:border-b">
            <tr className="border-b">
              <th className="h-10 px-2 text-left font-medium text-foreground">Tên</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">SĐT</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Thời gian</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">SP quan tâm</th>
              <th className="h-10 px-2 text-left font-medium text-foreground">Nội dung</th>
              <th className="h-10 px-2 text-center font-medium text-foreground">Đơn</th>
            </tr>
          </thead>
        </table>
      </div>
      <FadeScrollArea>
        <div className="px-4">
          <table className="w-full table-fixed text-sm">
            <colgroup>
              <col className="w-[16%]" />
              <col className="w-[16%]" />
              <col className="w-[14%]" />
              <col className="w-[18%]" />
              <col className="w-[22%]" />
              <col className="w-[14%]" />
            </colgroup>
            <tbody className="[&_tr:last-child]:border-0">
              {filtered.length === 0 ? (
                <tr><td colSpan={6}>
                  <Empty className="py-12">
                    <EmptyHeader>
                      <EmptyMedia variant="icon"><UsersIcon /></EmptyMedia>
                      <EmptyTitle>{search ? "Không tìm thấy khách hàng" : "Chưa có khách tiềm năng"}</EmptyTitle>
                      <EmptyDescription>
                        {search ? `Không có khách hàng nào khớp với "${search}"` : "Khi người xem để lại SĐT hoặc có ý định mua hàng, thông tin sẽ hiển thị tại đây."}
                      </EmptyDescription>
                    </EmptyHeader>
                  </Empty>
                </td></tr>
              ) : filtered.map((c, i) => {
                const order = orders[i]
                return (
                  <tr key={i} className="border-b transition-colors hover:bg-muted/50">
                    <td className="p-2 font-medium truncate">{c.name}</td>
                    <td className="p-2">
                      {c.phone ? (
                        <div className="flex items-center gap-1">
                          <Badge variant="outline" className="gap-1"><PhoneIcon className="size-3" />{c.phone}</Badge>
                          <button onClick={() => copyPhone(c.phone, i)} className="shrink-0 rounded p-1 hover:bg-muted transition-colors" title="Copy SĐT">
                            {copiedPhone === i ? <CheckIcon className="size-3 text-emerald-500" /> : <CopyIcon className="size-3 text-muted-foreground" />}
                          </button>
                        </div>
                      ) : <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="p-2 truncate">{c.time || <span className="text-muted-foreground">—</span>}</td>
                    <td className="p-2">{c.product ? <Badge variant="secondary" className="truncate max-w-full">{c.product}</Badge> : <span className="text-muted-foreground">—</span>}</td>
                    <td className="p-2 text-sm text-muted-foreground truncate">{c.comment}</td>
                    <td className="p-2 text-center">
                      {order ? (
                        <button onClick={() => openOrderDialog(i)} className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 transition-colors">
                          <CheckIcon className="size-2.5" />
                          {order.status === "pending" ? "Chờ" : order.status === "confirmed" ? "Xác nhận" : "Ship"}
                        </button>
                      ) : (
                        <Button variant="outline" size="sm" className="h-6 text-[10px] px-2 gap-1" onClick={() => openOrderDialog(i)}>
                          <FileTextIcon className="size-2.5" />Tạo đơn
                        </Button>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </FadeScrollArea>

      {/* Order Creation Dialog */}
      <Dialog open={orderDialog.open} onOpenChange={(open) => setOrderDialog({ open, customerIdx: open ? orderDialog.customerIdx : null })}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2"><FileTextIcon className="size-5" />Tạo đơn hàng</DialogTitle>
            <DialogDescription>
              {orderCustomer ? `${orderCustomer.name} — ${orderCustomer.product}` : ""}
            </DialogDescription>
          </DialogHeader>
          {orderCustomer && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Khách hàng</Label>
                  <p className="font-medium">{orderCustomer.name}</p>
                </div>
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">SĐT</Label>
                  <p className="font-medium">{orderCustomer.phone || "— Chưa có"}</p>
                </div>
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Thời gian</Label>
                  <p className="font-medium">{orderCustomer.time || "— Chưa có"}</p>
                </div>
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Sản phẩm</Label>
                  <p className="font-medium">{orderCustomer.product}</p>
                </div>
              </div>
              <Separator />
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label htmlFor="order-qty">Số lượng</Label>
                  <Input id="order-qty" type="number" min={1} value={orderForm.qty} onChange={(e) => setOrderForm((f) => ({ ...f, qty: parseInt(e.target.value) || 1 }))} />
                </div>
                <div className="space-y-1.5">
                  <Label>Trạng thái</Label>
                  <Select value={orderForm.status} onValueChange={(v) => setOrderForm((f) => ({ ...f, status: v }))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="pending">Chờ xác nhận</SelectItem>
                      <SelectItem value="confirmed">Đã xác nhận</SelectItem>
                      <SelectItem value="shipping">Đang giao</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="order-note">Ghi chú</Label>
                <Input id="order-note" placeholder="VD: Ship nhanh, gói quà..." value={orderForm.note} onChange={(e) => setOrderForm((f) => ({ ...f, note: e.target.value }))} />
              </div>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setOrderDialog({ open: false, customerIdx: null })}>Hủy</Button>
            <Button onClick={saveOrder} className="gap-1.5"><CheckIcon className="size-4" />Lưu đơn</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  )
}

function AIInsightsPanel() {
  const { stats, topProducts, topQuestions, potentialCustomers, comments } = useLiveData()

  const sentimentTotal = stats.sentiment_positive + stats.sentiment_neutral + stats.sentiment_negative
  const positivePct = sentimentTotal > 0 ? Math.round((stats.sentiment_positive / sentimentTotal) * 100) : 0
  const negativePct = sentimentTotal > 0 ? Math.round((stats.sentiment_negative / sentimentTotal) * 100) : 0
  const topProduct = topProducts[0]
  const topQuestion = topQuestions[0]
  const conversionRate = stats.total_comments > 0 ? ((stats.leads_count / stats.total_comments) * 100).toFixed(1) : "0"

  // Build dynamic alerts from real data
  const dynamicAlerts: { icon: any; title: string; desc: string; color: string; severity: string }[] = []

  if (topQuestion && topQuestion.count > 3) {
    dynamicAlerts.push({
      icon: AlertTriangleIcon,
      title: "Câu hỏi phổ biến",
      desc: `"${topQuestion.question}" được hỏi ${topQuestion.count} lần — nên trả lời ngay.`,
      color: "amber",
      severity: "Cao",
    })
  }

  if (topProduct && topProduct.sentiment >= 80) {
    dynamicAlerts.push({
      icon: FlameIcon,
      title: "Sản phẩm đang hot",
      desc: `"${topProduct.name}" có cảm xúc tích cực ${topProduct.sentiment}% với ${topProduct.mentions} lượt nhắc.`,
      color: "emerald",
      severity: "Thông tin",
    })
  }

  if (negativePct > 10) {
    dynamicAlerts.push({
      icon: HeartCrackIcon,
      title: "Cảm xúc tiêu cực tăng",
      desc: `Tỷ lệ tiêu cực hiện tại: ${negativePct}% (${stats.sentiment_negative} bình luận).`,
      color: "red",
      severity: "Cao",
    })
  }

  if (stats.leads_count > 0) {
    dynamicAlerts.push({
      icon: ZapIcon,
      title: "Khách hàng chốt đơn",
      desc: `${stats.leads_count} khách đã thể hiện ý định mua hàng. Tỷ lệ chuyển đổi: ${conversionRate}%.`,
      color: "blue",
      severity: "Trung bình",
    })
  }

  if (potentialCustomers.length > 0) {
    const withPhone = potentialCustomers.filter(c => c.phone).length
    dynamicAlerts.push({
      icon: LightbulbIcon,
      title: "Khách tiềm năng",
      desc: `${potentialCustomers.length} khách tiềm năng${withPhone > 0 ? `, ${withPhone} để lại SĐT` : ""}.`,
      color: "cyan",
      severity: "Thông tin",
    })
  }

  if (dynamicAlerts.length === 0) {
    dynamicAlerts.push({
      icon: SparklesIcon,
      title: "Đang thu thập dữ liệu",
      desc: "AI đang phân tích bình luận. Cảnh báo sẽ hiện khi có đủ dữ liệu.",
      color: "blue",
      severity: "Thông tin",
    })
  }

  return (
    <div className="grid gap-4 md:grid-cols-2 h-full min-h-0">
      {/* Tổng kết */}
      <Card className="flex flex-col min-h-0">
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><SparklesIcon className="size-5" />Tổng kết AI</CardTitle>
        </CardHeader>
        <FadeScrollArea>
          <div className="space-y-3 px-4 text-sm text-muted-foreground">
            {sentimentTotal > 0 ? (
              <>
                <p><strong className="text-foreground">Cảm xúc tích cực chiếm {positivePct}%</strong> ({stats.sentiment_positive} bình luận tích cực / {sentimentTotal} tổng).</p>
                {topProduct && (
                  <p><strong className="text-foreground">Sản phẩm nổi bật:</strong> "{topProduct.name}" được nhắc nhiều nhất với {topProduct.mentions} lượt, cảm xúc tích cực {topProduct.sentiment}%.</p>
                )}
                {topQuestions.length > 0 && (
                  <p><strong className="text-foreground">Gợi ý:</strong> Nên trả lời câu hỏi về {topQuestions.slice(0, 3).map(q => `"${q.question}"`).join(", ")}.</p>
                )}
                {potentialCustomers.length > 0 && (
                  <p><strong className="text-foreground">Khách hàng:</strong> {potentialCustomers.length} khách tiềm năng, tỷ lệ chuyển đổi {conversionRate}% từ bình luận.</p>
                )}
              </>
            ) : (
              <p>AI đang xử lý bình luận. Tổng kết sẽ hiện khi có đủ dữ liệu phân tích.</p>
            )}
          </div>
        </FadeScrollArea>
      </Card>

      {/* Cảnh báo */}
      <Card className="flex flex-col min-h-0">
        <CardHeader><CardTitle className="flex items-center gap-2"><BellRingIcon className="size-4" />Cảnh báo AI</CardTitle></CardHeader>
        <FadeScrollArea>
          <div className="space-y-2 px-4">
            {dynamicAlerts.map((alert) => {
              const colorMap: Record<string, { icon: string; border: string; bg: string; badge: string; badgeBg: string }> = {
                amber:   { icon: "text-amber-500",   border: "border-l-amber-500",   bg: "hover:bg-amber-500/5",   badge: "text-amber-600",   badgeBg: "bg-amber-500/10" },
                emerald: { icon: "text-emerald-500", border: "border-l-emerald-500", bg: "hover:bg-emerald-500/5", badge: "text-emerald-600", badgeBg: "bg-emerald-500/10" },
                blue:    { icon: "text-blue-500",    border: "border-l-blue-500",    bg: "hover:bg-blue-500/5",    badge: "text-blue-600",    badgeBg: "bg-blue-500/10" },
                red:     { icon: "text-red-500",     border: "border-l-red-500",     bg: "hover:bg-red-500/5",     badge: "text-red-600",     badgeBg: "bg-red-500/10" },
                cyan:    { icon: "text-cyan-500",    border: "border-l-cyan-500",    bg: "hover:bg-cyan-500/5",    badge: "text-cyan-600",    badgeBg: "bg-cyan-500/10" },
              }
              const c = colorMap[alert.color] ?? colorMap.blue
              const Icon = alert.icon
              return (
                <div key={alert.title} className={`flex items-start gap-3 rounded-lg border-l-[3px] ${c.border} p-3 transition-colors ${c.bg}`}>
                  <div className={`flex size-8 shrink-0 items-center justify-center rounded-md bg-muted/50 ${c.icon}`}>
                    <Icon className="size-4" />
                  </div>
                  <div className="flex-1 min-w-0 space-y-0.5">
                    <span className="text-sm font-semibold">{alert.title}</span>
                    <p className="text-xs text-muted-foreground leading-relaxed">{alert.desc}</p>
                    <span className={`inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium ${c.badge} ${c.badgeBg}`}>
                      {alert.severity}
                    </span>
                  </div>
                </div>
              )
            })}
          </div>
        </FadeScrollArea>
      </Card>
    </div>
  )
}

function StatsPanel() {
  const { stats, topProducts, potentialCustomers } = useLiveData()

  // Dữ liệu hoạt động — tóm tắt từ thống kê thật (chưa có time-series từ backend)
  const activityData = [
    { time: "Hiện tại", comments: stats.total_comments, viewers: stats.total_views },
  ]

  const sentimentData = [
    { name: "positive", value: stats.sentiment_positive, fill: "var(--color-positive)" },
    { name: "neutral", value: stats.sentiment_neutral, fill: "var(--color-neutral)" },
    { name: "negative", value: stats.sentiment_negative, fill: "var(--color-negative)" },
  ]

  const productData = topProducts.slice(0, 6).map((p) => ({
    name: p.name.length > 12 ? p.name.slice(0, 12) + "…" : p.name,
    mentions: p.mentions,
    questions: p.questions,
  }))

  const funnelData = [
    { stage: "Người xem", value: stats.total_views },
    { stage: "Bình luận", value: stats.total_comments },
    { stage: "Có SĐT/ĐC", value: potentialCustomers.length },
    { stage: "Chốt đơn", value: stats.leads_count },
  ]

  const activityConfig = {
    comments: { label: "Bình luận", color: "var(--chart-1)" },
    viewers: { label: "Người xem", color: "var(--chart-2)" },
  } satisfies ChartConfig

  const sentimentConfig = {
    positive: { label: "Tích cực", color: "#22c55e" },
    neutral: { label: "Trung lập", color: "#6b7280" },
    negative: { label: "Tiêu cực", color: "#ef4444" },
  } satisfies ChartConfig

  const productConfig = {
    mentions: { label: "Lượt nhắc", color: "var(--chart-1)" },
    questions: { label: "Câu hỏi", color: "var(--chart-4)" },
  } satisfies ChartConfig

  // funnelConfig not needed — funnel uses custom bars

  return (
    <div className="h-full flex flex-col min-h-0">
      <div className="flex-1 overflow-y-auto">
        <div className="grid gap-4 md:grid-cols-2 min-h-full">
          {/* Activity Timeline */}
          <Card className="md:col-span-2 flex flex-col">
        <CardHeader>
          <CardTitle>Hoạt động theo thời gian</CardTitle>
          <CardDescription>Bình luận và lượt xem realtime mỗi 5 phút</CardDescription>
        </CardHeader>
        <CardContent className="flex-1">
          <ChartContainer config={activityConfig} className="aspect-auto h-[250px] w-full">
            <AreaChart accessibilityLayer data={activityData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
              <CartesianGrid vertical={false} />
              <XAxis dataKey="time" tickLine={false} axisLine={false} tickMargin={8} />
              <YAxis tickLine={false} axisLine={false} tickMargin={8} width={35} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <ChartLegend content={<ChartLegendContent />} />
              <defs>
                <linearGradient id="fillComments" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="var(--color-comments)" stopOpacity={0.8} />
                  <stop offset="95%" stopColor="var(--color-comments)" stopOpacity={0.1} />
                </linearGradient>
                <linearGradient id="fillViewers" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="var(--color-viewers)" stopOpacity={0.8} />
                  <stop offset="95%" stopColor="var(--color-viewers)" stopOpacity={0.1} />
                </linearGradient>
              </defs>
              <Area dataKey="viewers" type="natural" fill="url(#fillViewers)" stroke="var(--color-viewers)" strokeWidth={2} />
              <Area dataKey="comments" type="natural" fill="url(#fillComments)" stroke="var(--color-comments)" strokeWidth={2} />
            </AreaChart>
          </ChartContainer>
        </CardContent>
      </Card>

      <Card className="flex flex-col">
        <CardHeader>
          <CardTitle>Phân bổ cảm xúc</CardTitle>
          <CardDescription>Tỷ lệ tích cực / trung lập / tiêu cực</CardDescription>
        </CardHeader>
        <CardContent className="flex-1 flex flex-col">
          <ChartContainer config={sentimentConfig} className="aspect-auto flex-1 min-h-[160px] w-full">
            <PieChart accessibilityLayer>
              <ChartTooltip content={<ChartTooltipContent />} />
              <Pie data={sentimentData} dataKey="value" nameKey="name" innerRadius={50} strokeWidth={2}>
                <RechartsLabel content={({ viewBox }) => {
                  if (viewBox && "cx" in viewBox && "cy" in viewBox) {
                    const total = sentimentData.reduce((s, d) => s + d.value, 0)
                    return (
                      <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                        <tspan x={viewBox.cx} y={(viewBox.cy || 0) - 6} className="fill-foreground text-2xl font-bold">{total}</tspan>
                        <tspan x={viewBox.cx} y={(viewBox.cy || 0) + 14} className="fill-muted-foreground text-xs">bình luận</tspan>
                      </text>
                    )
                  }
                }} />
              </Pie>
            </PieChart>
          </ChartContainer>
          {/* Breakdown visible without hover */}
          <div className="grid grid-cols-3 gap-2 pt-2 border-t mt-2">
            {sentimentData.map((d) => {
              const total = sentimentData.reduce((s, item) => s + item.value, 0)
              const pct = ((d.value / total) * 100).toFixed(0)
              const colorMap: Record<string, string> = { positive: "bg-[#22c55e]", neutral: "bg-[#6b7280]", negative: "bg-[#ef4444]" }
              const labelMap: Record<string, string> = { positive: "Tích cực", neutral: "Trung lập", negative: "Tiêu cực" }
              return (
                <div key={d.name} className="text-center">
                  <div className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                    <span className={`size-2 rounded-full ${colorMap[d.name]}`} />
                    {labelMap[d.name]}
                  </div>
                  <div className="text-sm font-bold tabular-nums">{d.value} <span className="text-xs font-normal text-muted-foreground">({pct}%)</span></div>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* Top Products Bar */}
      <Card className="flex flex-col">
        <CardHeader>
          <CardTitle>Top sản phẩm</CardTitle>
          <CardDescription>Lượt nhắc và câu hỏi theo sản phẩm</CardDescription>
        </CardHeader>
        <CardContent className="flex-1">
          <ChartContainer config={productConfig} className="aspect-auto h-full min-h-[200px] w-full">
            <BarChart accessibilityLayer data={productData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
              <CartesianGrid vertical={false} />
              <XAxis dataKey="name" tickLine={false} axisLine={false} tickMargin={8} tick={{ fontSize: 11 }} />
              <YAxis tickLine={false} axisLine={false} tickMargin={8} width={35} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <ChartLegend content={<ChartLegendContent />} />
              <Bar dataKey="mentions" fill="var(--color-mentions)" radius={[4, 4, 0, 0]} />
              <Bar dataKey="questions" fill="var(--color-questions)" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ChartContainer>
        </CardContent>
      </Card>

      {/* Conversion Funnel */}
      <Card className="md:col-span-2 flex flex-col">
        <CardHeader>
          <CardTitle>Phễu chuyển đổi</CardTitle>
          <CardDescription>Từ người xem → bình luận → để lại thông tin → chốt đơn</CardDescription>
        </CardHeader>
        <CardContent className="flex-1">
          <div className="flex items-end justify-around gap-2 h-full min-h-[120px]">
            {funnelData.map((item, i) => {
              const maxVal = funnelData[0].value
              const heightPct = Math.max(15, (item.value / maxVal) * 100)
              const conversionRate = i > 0 ? ((item.value / funnelData[i - 1].value) * 100).toFixed(1) : null
              return (
                <div key={item.stage} className="flex flex-col items-center gap-1.5 flex-1">
                  {conversionRate && (
                    <span className="text-[10px] font-medium text-muted-foreground">↓ {conversionRate}%</span>
                  )}
                  <div className="w-full max-w-[80px] rounded-t-md bg-primary/80 transition-all" style={{ height: `${heightPct}px` }} />
                  <span className="text-lg font-bold tabular-nums">{item.value.toLocaleString()}</span>
                  <span className="text-xs text-muted-foreground text-center">{item.stage}</span>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>
        </div>
      </div>
    </div>
  )
}

// --- Main Page ---
export default function LivesShow({ session: initialSession, stats: initialStats, comments: initialComments, topProducts: initialTopProducts, potentialCustomers: initialPotentialCustomers, topQuestions: initialTopQuestions }: PageProps) {
  const [soundEnabled, setSoundEnabled] = React.useState(true)
  const [isOffline, setIsOffline] = React.useState(false)

  // Live state — updated via polling
  const [session, setSession] = React.useState(initialSession)
  const [stats, setStats] = React.useState<StatsData>(initialStats ?? {
    total_views: 0, total_comments: 0, total_likes: 0, total_gifts: 0,
    total_follows: 0, total_shares: 0, viewer_count: 0, leads_count: 0,
    sentiment_positive: 0, sentiment_neutral: 0, sentiment_negative: 0,
  })
  const [comments, setComments] = React.useState<CommentData[]>(initialComments ?? [])
  const [topProducts, setTopProducts] = React.useState(initialTopProducts ?? [])
  const [potentialCustomers, setPotentialCustomers] = React.useState(initialPotentialCustomers ?? [])
  const [topQuestions, setTopQuestions] = React.useState(initialTopQuestions ?? [])

  // Order alerts — detect real "Chốt đơn" từ AI data
  const { alerts, dismiss } = useOrderAlerts(soundEnabled, comments)

  // Polling for live updates
  React.useEffect(() => {
    if (session.status !== 'live' && session.status !== 'connecting') return
    if (!session.tiktok_session_id) return

    const interval = setInterval(async () => {
      try {
        const res = await fetch(route('lives.fetch-events', session.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            'Accept': 'application/json',
          },
        })
        if (res.ok) {
          setIsOffline(false)
          const data = await res.json()
          if (data.comments) setComments(data.comments)
          if (data.stats) setStats(data.stats)
          if (data.topProducts) setTopProducts(data.topProducts)
          if (data.potentialCustomers) setPotentialCustomers(data.potentialCustomers)
          if (data.topQuestions) setTopQuestions(data.topQuestions)
          if (data.status) {
            setSession(prev => ({
              ...prev,
              status: data.status,
              error_message: data.status === 'error' ? (data.error_message ?? prev.error_message) : prev.error_message,
              duration: data.duration ?? prev.duration
            }))
          }
        } else {
          setIsOffline(true)
        }
      } catch (err) {
        setIsOffline(true)
      }
    }, 3000)

    return () => clearInterval(interval)
  }, [session.id, session.status, session.tiktok_session_id])

  // Stop session handler
  const handleStop = () => {
    if (confirm('Bạn có chắc chắn muốn kết thúc phiên phân tích?')) {
      router.post(route('lives.stop', session.id))
    }
  }

  // Sentiment calculation
  const sentimentTotal = stats.sentiment_positive + stats.sentiment_neutral + stats.sentiment_negative
  const sentimentPositivePct = sentimentTotal > 0 ? Math.round((stats.sentiment_positive / sentimentTotal) * 100) : 0
  const sentimentNeutralPct = sentimentTotal > 0 ? Math.round((stats.sentiment_neutral / sentimentTotal) * 100) : 0
  const sentimentNegativePct = sentimentTotal > 0 ? 100 - sentimentPositivePct - sentimentNeutralPct : 0

  return (
    <AuthenticatedLayout>
      <LiveContext.Provider value={{ session, stats, comments, topProducts, potentialCustomers, topQuestions }}>
      <Head title={`${session.name} — Live`} />
      <div className="flex flex-1 max-h-svh flex-col overflow-hidden">
      <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 border-b border-border/40 bg-background/95 backdrop-blur-md sticky top-0 z-40">
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
                <BreadcrumbLink href={route("lives.index")}>Phân tích phiên live</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>{session.name}</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 flex-col gap-2 px-4 pb-4 overflow-hidden">
        {/* Offline Warning Banner */}
        {isOffline && (
          <div className="flex items-center gap-2 px-4 py-2 text-sm bg-destructive/10 border border-destructive/20 text-destructive rounded-lg animate-pulse shrink-0">
            <span className="relative flex size-2 shrink-0">
              <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
              <span className="relative inline-flex size-2 rounded-full bg-current" />
            </span>
            <span>⚠️ Mất kết nối tới máy chủ. Đang thử kết nối lại...</span>
          </div>
        )}

        {/* Session Error Message Banner */}
        {session.status === 'error' && (
          <div className="flex items-center gap-2 px-4 py-3 text-sm bg-destructive/10 border border-destructive/20 text-destructive rounded-lg shrink-0">
            <AlertTriangleIcon className="size-4 shrink-0 text-destructive" />
            <div className="flex-1">
              <p className="font-semibold text-sm">Phiên phân tích bị lỗi</p>
              <p className="text-xs opacity-90">{session.error_message || 'Không thể kết nối TikTok service. Kiểm tra lại kết nối VPS.'}</p>
            </div>
          </div>
        )}

        {/* Session Header */}
        <div className="flex items-center justify-between">
          <div className="shrink-0">
            <h1 className="text-lg font-bold tracking-tight">{session.name}</h1>
            <div className="flex items-center gap-2 mt-1">
              <Badge variant="default">TikTok</Badge>
              {session.status === 'live' ? (
                <Badge variant="destructive" className="gap-1">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                  </span>
                  Đang Live
                </Badge>
              ) : session.status === 'disconnected' ? (
                <Badge className="bg-amber-500 hover:bg-amber-600 text-white gap-1 animate-pulse">
                  <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                  </span>
                  Mất kết nối · Đang thử lại...
                </Badge>
              ) : session.status === 'connecting' ? (
                <Badge variant="secondary" className="gap-1">Đang kết nối...</Badge>
              ) : session.status === 'error' ? (
                <Badge variant="destructive">Lỗi</Badge>
              ) : (
                <Badge variant="secondary">Đã kết thúc</Badge>
              )}
              <span className="flex items-center gap-1 text-sm text-muted-foreground">
                <ClockIcon className="size-3.5" />{session.duration}
              </span>
            </div>
          </div>

          {/* Center: Latest Order Alert */}
          <div className="flex-1 flex justify-center px-4 min-w-0">
            {alerts.length > 0 && <InlineOrderAlert alerts={alerts} dismiss={dismiss} />}
          </div>

          <div className="flex items-center gap-2 shrink-0">
            <Button
              variant="outline"
              size="sm"
              className={`gap-1.5 ${soundEnabled ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20" : ""}`}
              onClick={() => setSoundEnabled(!soundEnabled)}
            >
              {soundEnabled ? <BellRingIcon className="size-4" /> : <BellOffIcon className="size-4" />}
              {soundEnabled ? "Thông báo" : "Tắt tiếng"}
            </Button>
            <Button variant="destructive" size="sm" onClick={handleStop} disabled={session.status === 'ended'}>
              <CircleStopIcon className="mr-2 size-4" />Kết thúc phiên phân tích
            </Button>
          </div>
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
                    <p className="text-sm font-medium">
                      {session.status === 'live'
                        ? 'Đang phát trực tiếp'
                        : session.status === 'disconnected'
                        ? 'Mất kết nối tạm thời · Đang tự động kết nối lại...'
                        : session.status === 'connecting'
                        ? 'Đang kết nối...'
                        : 'Phiên đã kết thúc'}
                    </p>
                    <p className="text-xs text-muted-foreground">@{session.tiktok_username}</p>
                    {session.status === 'live' && (
                      <Badge variant="destructive" className="gap-1">
                        <span className="relative flex size-1.5">
                          <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                          <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                        </span>
                        {stats.viewer_count.toLocaleString()} đang xem
                      </Badge>
                    )}
                  </div>
                </div>
                {/* Stats bar at bottom of video card */}
                <div className="grid grid-cols-3 divide-x border-t">
                  <div className="p-2.5 text-center">
                    <div className="text-lg font-bold">{stats.total_views.toLocaleString()}</div>
                    <p className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <EyeIcon className="size-3" />Lượt xem
                    </p>
                  </div>
                  <div className="p-2.5 text-center">
                    <div className="text-lg font-bold">{stats.total_comments.toLocaleString()}</div>
                    <p className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <MessageSquareIcon className="size-3" />Bình luận
                    </p>
                  </div>
                  <div className="p-2.5 text-center">
                    <div className="text-lg font-bold">{stats.leads_count}</div>
                    <p className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <PhoneIcon className="size-3" />KH tiềm năng
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Phân tích cảm xúc */}
            <Card>
              <CardHeader className="px-3 pt-0">
                <CardTitle className="flex items-center gap-2 text-xs">
                  <SmileIcon className="size-3.5" />
                  Phân tích cảm xúc
                </CardTitle>
              </CardHeader>
              <CardContent className="px-3">
                {sentimentTotal === 0 ? (
                  <div className="text-xs text-muted-foreground text-center py-2">Chưa có dữ liệu cảm xúc</div>
                ) : (
                  <>
                    <div className="flex items-start gap-3">
                      <div className="text-center">
                        <div className={`text-2xl font-bold ${sentimentNegativePct > 30 ? 'text-red-500' : sentimentPositivePct >= 20 ? 'text-green-500' : 'text-amber-500'}`}>
                          {sentimentNegativePct > 30 ? '😟' : sentimentPositivePct >= 20 ? '😊' : '😐'}
                        </div>
                        <div className="text-[10px] text-muted-foreground mt-0.5">
                          {sentimentNegativePct > 30 ? 'Cần chú ý' : sentimentNegativePct === 0 && sentimentPositivePct > 0 ? 'Rất tốt!' : sentimentNegativePct === 0 ? 'Ổn định' : 'Tích cực'}
                        </div>
                      </div>
                      <div className="flex-1 space-y-1 text-xs">
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-green-500" />Tích cực</span>
                          <span className="font-medium">{sentimentPositivePct}% <span className="text-muted-foreground">({stats.sentiment_positive})</span></span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-amber-500" />Bình thường</span>
                          <span className="font-medium">{sentimentNeutralPct}% <span className="text-muted-foreground">({stats.sentiment_neutral})</span></span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5"><span className="size-1.5 rounded-full bg-red-500" />Tiêu cực</span>
                          <span className="font-medium">{sentimentNegativePct}% <span className="text-muted-foreground">({stats.sentiment_negative})</span></span>
                        </div>
                      </div>
                    </div>
                    <div className="mt-2 flex h-2 w-full overflow-hidden rounded-full bg-muted/30">
                      <div className="bg-green-500 transition-all duration-500" style={{ width: `${sentimentPositivePct}%` }} />
                      <div className="bg-amber-500 transition-all duration-500" style={{ width: `${sentimentNeutralPct}%` }} />
                      <div className="bg-red-500 transition-all duration-500" style={{ width: `${sentimentNegativePct}%` }} />
                    </div>
                    {sentimentNegativePct === 0 && sentimentTotal > 0 && (
                      <div className="mt-1.5 text-[10px] text-green-600 dark:text-green-400">✨ Không có phản hồi tiêu cực — phiên live đang diễn ra tốt!</div>
                    )}
                  </>
                )}
              </CardContent>
            </Card>

            {/* Top Keywords Card */}
            <Card className="flex-1 overflow-hidden">
              <CardHeader className="px-3 pt-0">
                <CardTitle className="text-xs">🔍 Từ khóa được nhắc nhiều</CardTitle>
              </CardHeader>
              <CardContent className="px-3 flex-1 min-h-0 overflow-hidden">
                <div className="flex flex-wrap gap-1.5 overflow-hidden h-full relative">
                  {topQuestions.length > 0 || topProducts.length > 0 ? (
                    <>
                      {topQuestions.map((item) => (
                        <div key={`q-${item.question}`} className="flex items-center gap-1 rounded-md bg-amber-500/10 text-amber-700 dark:text-amber-400 px-2 py-0.5 text-xs">
                          <span>{item.question}</span>
                          <span className="font-bold tabular-nums">{item.count}</span>
                        </div>
                      ))}
                      {topProducts.slice(0, 6).map((item) => (
                        <div key={`p-${item.name}`} className="flex items-center gap-1 rounded-md bg-blue-500/10 text-blue-700 dark:text-blue-400 px-2 py-0.5 text-xs">
                          <span>{item.name}</span>
                          <span className="font-bold tabular-nums">{item.mentions}</span>
                        </div>
                      ))}
                    </>
                  ) : (
                    <div className="text-xs text-muted-foreground">Chưa có dữ liệu</div>
                  )}
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
              <TabsTrigger value="stats" className="gap-1.5"><BarChart3Icon className="size-3.5" />Thống kê</TabsTrigger>
              <TabsTrigger value="ai" className="gap-1.5"><SparklesIcon className="size-3.5" />AI</TabsTrigger>
            </TabsList>
            <TabsContent value="comments" className="flex-1 min-h-0 overflow-y-auto"><CommentsPanel /></TabsContent>
            <TabsContent value="products" className="flex-1 min-h-0 overflow-y-auto"><ProductsPanel /></TabsContent>
            <TabsContent value="questions" className="flex-1 min-h-0 overflow-y-auto"><QuestionsPanel /></TabsContent>
            <TabsContent value="customers" className="flex-1 min-h-0 overflow-y-auto"><CustomersPanel /></TabsContent>
            <TabsContent value="stats" className="flex-1 min-h-0"><StatsPanel /></TabsContent>
            <TabsContent value="ai" className="flex-1 min-h-0 overflow-y-auto"><AIInsightsPanel /></TabsContent>
          </Tabs>
        </div>
      </div>
      </div>
      </LiveContext.Provider>
    </AuthenticatedLayout>
  )
}
