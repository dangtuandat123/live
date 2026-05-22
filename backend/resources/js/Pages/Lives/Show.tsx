import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowUpIcon,
    BarChart3Icon,
    BellOffIcon,
    BellRingIcon,
    CheckIcon,
    CircleStopIcon,
    ClipboardCopyIcon,
    ClipboardListIcon,
    ClockIcon,
    CopyIcon,
    DownloadIcon,
    EyeIcon,
    FileTextIcon,
    FlameIcon,
    GiftIcon,
    HeartCrackIcon,
    HeartIcon,
    HelpCircleIcon,
    LightbulbIcon,
    LoaderIcon,
    MessageSquareIcon,
    PackageIcon,
    PhoneIcon,
    PinIcon,
    PinOffIcon,
    RefreshCw,
    SearchIcon,
    Share2Icon,
    ShoppingCartIcon,
    SmileIcon,
    SparklesIcon,
    UserPlusIcon,
    UsersIcon,
    XIcon,
    ZapIcon,
} from 'lucide-react';
import * as React from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Pie,
    PieChart,
    Label as RechartsLabel,
    XAxis,
    YAxis,
} from 'recharts';
import { toast } from 'sonner';

const TIKTOK_EMOJI_MAP: Record<string, string> = {
    laughcry: '😂',
    heart: '❤️',
    smile: '😊',
    cry: '😢',
    lol: '😆',
    shock: '😱',
    wink: '😉',
    cool: '😎',
    angry: '😡',
    yes: '👍',
    surprised: '😲',
    happy: '😊',
    sad: '😢',
    love: '😍',
    cheeky: '😜',
    playful: '😜',
    wow: '😮',
    applause: '👏',
    scream: '😱',
    rage: '😡',
    sweat: '😅',
    thinking: '🤔',
    bored: '😑',
    sleepy: '😴',
    kiss: '😘',
    proud: '😏',
    silly: '😜',
    stunned: '😳',
    neutral: '😐',
    funny: '😂',
    rose: '🌹',
    fire: '🔥',
    clap: '👏',
    thumbsup: '👍',
    crying: '😭',
    thanks: '🙏',
    thankyou: '🙏',
    flower: '🌸',
    gift: '🎁',
    tiktok: '🎵',
    star: '⭐',
    laugh: '😄',
    ok: '👌',
    eyes: '👀',
    wave: '👋',
    crown: '👑',
    slap: '👋',
    cute: '🥰',
    amazing: '😲',
    loveyou: '🥰',
};

function renderCommentText(text: string): string {
    if (!text) return text;
    return text.replace(/\[([a-zA-Z0-9_-]+)\]/g, (match, p1) => {
        const key = p1.toLowerCase();
        return TIKTOK_EMOJI_MAP[key] ?? match;
    });
}

// --- Types ---
interface SessionData {
    id: number;
    name: string;
    platform: string;
    status: string;
    tiktok_username: string;
    tiktok_session_id: string | null;
    duration: string;
    started_at: string | null;
    ended_at: string | null;
    error_message: string | null;
    products: {
        id: number;
        name: string;
        sku: string;
        price: number;
        image: string | null;
    }[];
    ai_insights?: string | null;
    ai_alerts?:
        | {
              type: 'danger' | 'warning' | 'info' | 'success';
              title: string;
              desc: string;
              action: string;
          }[]
        | null;
}

interface StatsData {
    total_views: number;
    total_comments: number;
    total_likes: number;
    total_gifts: number;
    total_follows: number;
    total_shares: number;
    viewer_count: number;
    leads_count: number;
    sentiment_positive: number;
    sentiment_neutral: number;
    sentiment_negative: number;
}

interface CommentData {
    id: number;
    user: string;
    unique_id: string | null;
    avatar_url: string | null;
    text: string;
    time: string;
    event_at: string | null;
    sentiment: string;
    intent_tag: string | null;
    question_tag: string | null;
    product_tag: string | null;
    has_phone: boolean;
    ai_processed: boolean;
    is_pinned?: boolean;
    is_highlighted?: boolean;
    sort_order?: number;
}

interface TopProduct {
    name: string;
    mentions: number;
    sentiment: number;
    questions: number;
}

interface PotentialCustomer {
    id: number;
    name: string;
    phone: string;
    product: string;
    comment: string;
    time: string;
    is_pinned?: boolean;
    is_highlighted?: boolean;
    sort_order?: number;
    qty?: number;
    note?: string;
    status?: string;
}

interface TopQuestion {
    question: string;
    count: number;
    product: string;
}

interface StatsHistoryEntry {
    time: string;
    comments: number;
    viewers: number;
    [key: string]: unknown;
}

interface TopKeyword {
    keyword: string;
    count: number;
}

interface PageProps {
    session: SessionData;
    stats: StatsData | null;
    comments: CommentData[];
    topProducts: TopProduct[];
    potentialCustomers: PotentialCustomer[];
    topQuestions: TopQuestion[];
    statsHistory?: StatsHistoryEntry[];
    potentialCustomersCount: number;
    topKeywords: TopKeyword[];
}

// --- Context for sharing data across sub-components ---
const LiveContext = React.createContext<{
    session: SessionData;
    setSession?: React.Dispatch<React.SetStateAction<SessionData>>;
    stats: StatsData;
    comments: CommentData[];
    topProducts: TopProduct[];
    potentialCustomers: PotentialCustomer[];
    topQuestions: TopQuestion[];
    statsHistory: StatsHistoryEntry[];
    potentialCustomersCount: number;
    topKeywords: TopKeyword[];
    setComments?: React.Dispatch<React.SetStateAction<CommentData[]>>;
    setPotentialCustomers?: React.Dispatch<
        React.SetStateAction<PotentialCustomer[]>
    >;
}>(null!);

function useLiveData() {
    return React.useContext(LiveContext);
}

// --- Sound Alert System ---
function playOrderChime() {
    try {
        const ctx = new AudioContext();
        const now = ctx.currentTime;
        // Pleasant two-tone chime
        const freqs = [523.25, 659.25, 783.99]; // C5, E5, G5
        freqs.forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, now + i * 0.12);
            gain.gain.linearRampToValueAtTime(0.15, now + i * 0.12 + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.12 + 0.4);
            osc.connect(gain).connect(ctx.destination);
            osc.start(now + i * 0.12);
            osc.stop(now + i * 0.12 + 0.5);
        });
        setTimeout(() => ctx.close(), 2000);
    } catch {
        /* AudioContext not available */
    }
}

type OrderAlert = {
    id: number;
    user: string;
    product: string;
    comment: string;
    time: number;
};

function InlineOrderAlert({
    alerts,
    dismiss,
}: {
    alerts: OrderAlert[];
    dismiss: (id: number) => void;
}) {
    const latest = alerts[0];
    const DURATION = 5000; // auto-dismiss after 5s

    React.useEffect(() => {
        const timer = setTimeout(() => dismiss(latest.id), DURATION);
        return () => clearTimeout(timer);
    }, [latest.id, dismiss]);

    return (
        <div
            key={latest.id}
            className="animate-in slide-in-from-top-2 fade-in relative flex w-full max-w-md items-center gap-2.5 overflow-hidden rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 backdrop-blur-sm duration-300"
        >
            {/* Progress bar — synced to DURATION */}
            <div
                key={`progress-${latest.id}`}
                className="absolute bottom-0 left-0 h-[2px] bg-emerald-500/50"
                style={{ animation: `shrink ${DURATION}ms linear forwards` }}
            />

            <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">
                <ShoppingCartIcon className="size-3.5 text-emerald-500" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold text-emerald-400">
                    🛒 Chốt đơn mới!
                </p>
                <p className="text-muted-foreground truncate text-[11px]">
                    <span className="text-foreground font-medium">
                        {latest.user}
                    </span>{' '}
                    — {latest.product}
                </p>
            </div>
            {alerts.length > 1 && (
                <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-[10px] font-bold text-emerald-400 tabular-nums">
                    +{alerts.length - 1}
                </span>
            )}
            <button
                onClick={() => dismiss(latest.id)}
                className="hover:bg-muted shrink-0 rounded p-0.5 transition-colors"
            >
                <XIcon className="text-muted-foreground size-3" />
            </button>
        </div>
    );
}

function useOrderAlerts(soundEnabled: boolean, comments: CommentData[]) {
    const [alerts, setAlerts] = React.useState<OrderAlert[]>([]);
    const alertIdRef = React.useRef(0);
    const seenOrderIdsRef = React.useRef<Set<number>>(new Set());
    const initializedRef = React.useRef(false);

    React.useEffect(() => {
        const orderComments = comments.filter(
            (c) => c.intent_tag === 'Chốt đơn',
        );
        if (!initializedRef.current) {
            // Lần load đầu — đánh dấu tất cả đơn hiện tại, không alert
            orderComments.forEach((c) => seenOrderIdsRef.current.add(c.id));
            initializedRef.current = true;
            return;
        }
        const newOrders = orderComments.filter(
            (c) => !seenOrderIdsRef.current.has(c.id),
        );
        if (newOrders.length > 0) {
            const newAlerts = newOrders.map((c) => ({
                id: ++alertIdRef.current,
                user: c.user,
                product: c.product_tag || c.text,
                comment: c.text,
                time: Date.now(),
            }));
            setAlerts((prev) => [...newAlerts, ...prev].slice(0, 5));
            if (soundEnabled) playOrderChime();
            newOrders.forEach((c) => seenOrderIdsRef.current.add(c.id));
        }
    }, [comments, soundEnabled]);

    const dismiss = React.useCallback((id: number) => {
        setAlerts((prev) => prev.filter((a) => a.id !== id));
    }, []);

    return { alerts, dismiss };
}

// --- Export Utilities ---
function exportLeadsCSV(customers: PotentialCustomer[]) {
    const header = 'Tên,SĐT,Sản phẩm,Nội dung';
    const rows = customers.map((c) =>
        [c.name, c.phone || '', c.product, c.comment]
            .map((v) => `"${v.replace(/"/g, '""')}"`)
            .join(','),
    );
    const csv = [header, ...rows].join('\n');
    const blob = new Blob(['\uFEFF' + csv], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `leads_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function copyLeadsToClipboard(customers: PotentialCustomer[]): string {
    const text = customers
        .map(
            (c, i) =>
                `${i + 1}. ${c.name} | ${c.phone || '—'} | ${c.product} | ${c.comment}`,
        )
        .join('\n');
    navigator.clipboard.writeText(text);
    return text;
}

function InfiniteScrollSentinel({ onLoadMore }: { onLoadMore: () => void }) {
    const ref = React.useRef<HTMLDivElement>(null);
    const [loading, setLoading] = React.useState(false);

    React.useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting && !loading) {
                    setLoading(true);
                    setTimeout(() => {
                        onLoadMore();
                        setLoading(false);
                    }, 400);
                }
            },
            { threshold: 0.1 },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, [onLoadMore, loading]);

    return (
        <div ref={ref} className="flex items-center justify-center py-4">
            <LoaderIcon className="text-muted-foreground size-5 animate-spin" />
            <span className="text-muted-foreground ml-2 text-sm">
                Đang tải...
            </span>
        </div>
    );
}

function FadeScrollArea({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    const scrollRef = React.useRef<HTMLDivElement>(null);
    const [showTopFade, setShowTopFade] = React.useState(false);
    const [showBottomFade, setShowBottomFade] = React.useState(false);

    const updateFades = React.useCallback(() => {
        const el = scrollRef.current;
        if (!el) return;
        setShowTopFade(el.scrollTop > 8);
        setShowBottomFade(el.scrollTop + el.clientHeight < el.scrollHeight - 8);
    }, []);

    React.useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;
        updateFades();
        el.addEventListener('scroll', updateFades, { passive: true });
        const ro = new ResizeObserver(updateFades);
        ro.observe(el);
        return () => {
            el.removeEventListener('scroll', updateFades);
            ro.disconnect();
        };
    }, [updateFades]);

    const scrollToTop = () => {
        scrollRef.current?.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <div className={`relative min-h-0 flex-1 ${className ?? ''}`}>
            <div ref={scrollRef} className="h-full overflow-y-auto">
                {children}
            </div>
            {showTopFade && (
                <div className="from-card pointer-events-none absolute inset-x-0 top-0 z-10 h-6 bg-gradient-to-b to-transparent" />
            )}
            {showBottomFade && (
                <div className="from-card pointer-events-none absolute inset-x-0 bottom-0 z-10 h-6 bg-gradient-to-t to-transparent" />
            )}
            {showTopFade && (
                <button
                    onClick={scrollToTop}
                    className="bg-primary text-primary-foreground hover:bg-primary/90 absolute right-3 bottom-3 z-20 flex size-8 items-center justify-center rounded-full shadow-lg transition-all hover:scale-110 active:scale-95"
                    title="Cuộn lên đầu"
                >
                    <ArrowUpIcon className="size-4" />
                </button>
            )}
        </div>
    );
}

function SentimentBadge({ sentiment }: { sentiment: string }) {
    const config: Record<
        string,
        { label: string; variant: 'default' | 'secondary' | 'destructive' }
    > = {
        positive: { label: 'Tích cực', variant: 'default' },
        neutral: { label: 'Trung lập', variant: 'secondary' },
        negative: { label: 'Tiêu cực', variant: 'destructive' },
    };
    const c = config[sentiment] ?? config.neutral;
    return (
        <Badge variant={c.variant} className="text-xs">
            {c.label}
        </Badge>
    );
}

// --- Sub-components for each tab ---

function CommentsPanel() {
    const { comments: allComments, setComments } = useLiveData();
    const BATCH = 50;
    const [filter, setFilter] = React.useState('all');
    const [search, setSearch] = React.useState('');
    const [visibleCount, setVisibleCount] = React.useState(BATCH);
    const [selectedQuestionTag, setSelectedQuestionTag] = React.useState<
        string | null
    >(null);

    const questionTagsInComments = React.useMemo(() => {
        const tags: { [key: string]: number } = {};
        allComments.forEach((c) => {
            if (c.question_tag) {
                tags[c.question_tag] = (tags[c.question_tag] || 0) + 1;
            } else if (c.intent_tag === 'Hỏi thông tin') {
                tags['Chưa phân loại'] = (tags['Chưa phân loại'] || 0) + 1;
            }
        });
        return Object.entries(tags)
            .map(([tag, count]) => ({ tag, count }))
            .sort((a, b) => b.count - a.count);
    }, [allComments]);

    const [copiedId, setCopiedId] = React.useState<number | null>(null);

    const togglePin = async (id: number) => {
        const comment = allComments.find((c) => c.id === id);
        if (!comment) return;
        const newPinned = !comment.is_pinned;

        if (setComments) {
            setComments((prev) =>
                prev.map((c) =>
                    c.id === id ? { ...c, is_pinned: newPinned } : c,
                ),
            );
        }

        try {
            await fetch(`/api/live-events/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ is_pinned: newPinned }),
            });
        } catch (e) {
            console.error(e);
        }
    };

    const toggleOrder = async (id: number) => {
        const comment = allComments.find((c) => c.id === id);
        if (!comment) return;
        const newHighlighted = !comment.is_highlighted;

        if (setComments) {
            setComments((prev) =>
                prev.map((c) =>
                    c.id === id ? { ...c, is_highlighted: newHighlighted } : c,
                ),
            );
        }

        try {
            await fetch(`/api/live-events/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ is_highlighted: newHighlighted }),
            });
        } catch (e) {
            console.error(e);
        }
    };

    const copyComment = (text: string, id: number) => {
        navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 1500);
    };

    const filtered = allComments.filter((c) => {
        if (filter === 'pinned' && !c.is_pinned) return false;
        if (
            filter === 'order' &&
            !c.is_highlighted &&
            c.intent_tag !== 'Chốt đơn'
        )
            return false;
        if (filter === 'question') {
            if (selectedQuestionTag) {
                if (selectedQuestionTag === 'Chưa phân loại') {
                    if (c.question_tag || c.intent_tag !== 'Hỏi thông tin')
                        return false;
                } else if (c.question_tag !== selectedQuestionTag) {
                    return false;
                }
            } else {
                if (!c.question_tag && c.intent_tag !== 'Hỏi thông tin')
                    return false;
            }
        }
        if (
            filter === 'support' &&
            c.intent_tag !== 'Yêu cầu hỗ trợ' &&
            c.intent_tag !== 'Phản hồi SP'
        )
            return false;
        if (
            (filter === 'positive' || filter === 'negative') &&
            c.sentiment !== filter
        )
            return false;
        if (
            search &&
            !c.text.toLowerCase().includes(search.toLowerCase()) &&
            !c.user.toLowerCase().includes(search.toLowerCase())
        )
            return false;
        return true;
    });

    const visible = filtered.slice(0, visibleCount);
    const hasMore = visibleCount < filtered.length;

    return (
        <Card className="flex h-full flex-col">
            <style>{`
        @keyframes ai-scan {
          0% { background-position: -200% 0; }
          100% { background-position: 200% 0; }
        }
        @keyframes slide-fade-in {
          0% {
            opacity: 0;
            transform: translateY(-10px);
          }
          100% {
            opacity: 1;
            transform: translateY(0);
          }
        }
        .animate-comment-entry {
          animation: slide-fade-in 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
      `}</style>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle>Bình luận Realtime</CardTitle>
                        <CardDescription>
                            Hiển thị {visible.length} / {filtered.length} bình
                            luận (tổng: {allComments.length})
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
                        <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                        <Input
                            placeholder="Tìm trong bình luận..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                setVisibleCount(BATCH);
                            }}
                            className="h-8 pl-9 text-sm"
                        />
                    </div>
                </div>
                <div className="flex flex-wrap gap-1.5 pt-2">
                    {(
                        [
                            {
                                key: 'all',
                                label: 'Tất cả',
                                count: allComments.length,
                            },
                            {
                                key: 'pinned',
                                label: '📌 Ghim',
                                count: allComments.filter((c) => c.is_pinned)
                                    .length,
                            },
                            {
                                key: 'order',
                                label: '🛒 Chốt đơn',
                                count: allComments.filter(
                                    (c) =>
                                        c.intent_tag === 'Chốt đơn' ||
                                        c.is_highlighted,
                                ).length,
                            },
                            {
                                key: 'question',
                                label: '❓ Hỏi',
                                count: allComments.filter(
                                    (c) =>
                                        c.question_tag ||
                                        c.intent_tag === 'Hỏi thông tin',
                                ).length,
                            },
                            {
                                key: 'support',
                                label: '🔔 Phản hồi',
                                count: allComments.filter(
                                    (c) =>
                                        c.intent_tag === 'Yêu cầu hỗ trợ' ||
                                        c.intent_tag === 'Phản hồi SP',
                                ).length,
                            },
                            {
                                key: 'positive',
                                label: 'Tích cực',
                                count: allComments.filter(
                                    (c) => c.sentiment === 'positive',
                                ).length,
                            },
                            {
                                key: 'negative',
                                label: 'Tiêu cực',
                                count: allComments.filter(
                                    (c) => c.sentiment === 'negative',
                                ).length,
                            },
                        ] as const
                    ).map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => {
                                setFilter(tab.key);
                                setSelectedQuestionTag(null);
                                setVisibleCount(BATCH);
                            }}
                            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors ${
                                filter === tab.key
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground border-transparent'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-[10px] leading-none font-semibold tabular-nums ${
                                    filter === tab.key
                                        ? 'bg-primary/20'
                                        : 'bg-muted'
                                }`}
                            >
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>
                {filter === 'question' && questionTagsInComments.length > 0 && (
                    <div className="border-border/50 flex flex-wrap gap-1.5 border-t pt-3">
                        <button
                            onClick={() => setSelectedQuestionTag(null)}
                            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium transition-colors ${
                                selectedQuestionTag === null
                                    ? 'border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                    : 'bg-muted/30 text-muted-foreground hover:bg-muted/50 border border-transparent'
                            }`}
                        >
                            Tất cả câu hỏi
                        </button>
                        {questionTagsInComments.map(({ tag, count }) => (
                            <button
                                key={tag}
                                onClick={() => setSelectedQuestionTag(tag)}
                                className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium transition-colors ${
                                    selectedQuestionTag === tag
                                        ? 'border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                        : 'bg-muted/30 text-muted-foreground hover:bg-muted/50 border border-transparent'
                                }`}
                            >
                                {tag}
                                <span className="text-[9px] opacity-70">
                                    ({count})
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </CardHeader>
            <FadeScrollArea>
                <div className="divide-y px-4">
                    {visible.length === 0 ? (
                        <Empty className="py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    {filter === 'order' ? (
                                        <ShoppingCartIcon />
                                    ) : filter === 'question' ? (
                                        <HelpCircleIcon />
                                    ) : filter === 'support' ? (
                                        <BellRingIcon />
                                    ) : filter === 'pinned' ? (
                                        <PinIcon />
                                    ) : filter === 'positive' ? (
                                        <SmileIcon />
                                    ) : filter === 'negative' ? (
                                        <HeartCrackIcon />
                                    ) : search ? (
                                        <SearchIcon />
                                    ) : (
                                        <MessageSquareIcon />
                                    )}
                                </EmptyMedia>
                                <EmptyTitle>
                                    {filter === 'order'
                                        ? 'Chưa có đơn hàng'
                                        : filter === 'question'
                                          ? 'Chưa có câu hỏi'
                                          : filter === 'support'
                                            ? 'Chưa có phản hồi'
                                            : filter === 'pinned'
                                              ? 'Chưa ghim bình luận nào'
                                              : filter === 'positive'
                                                ? 'Chưa có bình luận tích cực'
                                                : filter === 'negative'
                                                  ? 'Không có bình luận tiêu cực'
                                                  : search
                                                    ? 'Không tìm thấy kết quả'
                                                    : 'Chưa có bình luận'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {filter === 'order'
                                        ? 'Khi có người xem chốt đơn, đơn hàng sẽ hiển thị tại đây.'
                                        : filter === 'question'
                                          ? 'Khi có người hỏi về sản phẩm, câu hỏi sẽ xuất hiện tại đây.'
                                          : filter === 'support'
                                            ? 'Chưa có ai phản hồi hoặc yêu cầu hỗ trợ.'
                                            : filter === 'pinned'
                                              ? 'Nhấn vào icon ghim trên bình luận để ghim lại.'
                                              : filter === 'positive'
                                                ? 'Bình luận tích cực về sản phẩm sẽ hiển thị tại đây.'
                                                : filter === 'negative'
                                                  ? 'Tuyệt vời! Phiên live không có phản hồi tiêu cực. 🎉'
                                                  : search
                                                    ? `Không có bình luận nào khớp với "${search}"`
                                                    : 'Bình luận sẽ xuất hiện realtime khi phiên live đang diễn ra.'}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        visible.map((comment) => {
                            const isPinned = comment.is_pinned;
                            const isOrder =
                                comment.is_highlighted ||
                                comment.intent_tag === 'Chốt đơn';
                            const isAnalyzing = !comment.ai_processed;
                            const sentimentColor = isPinned
                                ? 'border-l-yellow-500 bg-yellow-500/5'
                                : isAnalyzing
                                  ? 'border-l-indigo-500 bg-indigo-500/5 animate-pulse relative overflow-hidden'
                                  : comment.sentiment === 'positive'
                                    ? 'border-l-emerald-500'
                                    : comment.sentiment === 'negative'
                                      ? 'border-l-red-500'
                                      : 'border-l-muted-foreground/30';
                            return (
                                <div
                                    key={comment.id}
                                    className={`group relative flex items-start gap-2.5 border-l-2 py-2.5 pl-3 transition-colors ${sentimentColor} animate-comment-entry`}
                                >
                                    {isAnalyzing && (
                                        <div
                                            className="pointer-events-none absolute inset-0 bg-gradient-to-r from-transparent via-indigo-500/10 to-transparent"
                                            style={{
                                                backgroundSize: '200% 100%',
                                                animation:
                                                    'ai-scan 1.5s infinite linear',
                                            }}
                                        />
                                    )}
                                    <a
                                        href={
                                            comment.unique_id
                                                ? `https://www.tiktok.com/@${comment.unique_id}`
                                                : '#'
                                        }
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="shrink-0"
                                        title={
                                            comment.unique_id
                                                ? `@${comment.unique_id}`
                                                : comment.user
                                        }
                                    >
                                        {comment.avatar_url ? (
                                            <img
                                                src={comment.avatar_url}
                                                alt={comment.user}
                                                className="ring-border size-7 rounded-full object-cover ring-1"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <div className="bg-muted flex size-7 items-center justify-center rounded-full text-xs font-medium">
                                                {comment.user.charAt(0)}
                                            </div>
                                        )}
                                    </a>
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                <a
                                                    href={
                                                        comment.unique_id
                                                            ? `https://www.tiktok.com/@${comment.unique_id}`
                                                            : '#'
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="hover:text-primary text-sm font-medium transition-colors hover:underline"
                                                >
                                                    {comment.user}
                                                </a>
                                                {isAnalyzing ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="animate-pulse gap-1 border border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-normal text-indigo-600 dark:text-indigo-400"
                                                    >
                                                        <SparklesIcon className="size-2.5 animate-spin text-indigo-500" />{' '}
                                                        AI đang phân tích
                                                    </Badge>
                                                ) : (
                                                    <>
                                                        <SentimentBadge
                                                            sentiment={
                                                                comment.sentiment
                                                            }
                                                        />
                                                        {isOrder && (
                                                            <Badge
                                                                variant="default"
                                                                className="bg-emerald-600 px-1.5 py-0 text-[10px]"
                                                            >
                                                                Đơn hàng
                                                            </Badge>
                                                        )}
                                                    </>
                                                )}
                                                {comment.has_phone && (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 px-1.5 py-0 text-[10px]"
                                                    >
                                                        <PhoneIcon className="size-2.5" />
                                                        SĐT
                                                    </Badge>
                                                )}
                                                {isPinned && (
                                                    <PinIcon className="size-3 text-yellow-500" />
                                                )}
                                            </div>
                                            {/* Time + Quick Actions — stacked to prevent layout shift */}
                                            <div className="relative flex shrink-0 items-center">
                                                <span className="text-muted-foreground/50 text-[11px] whitespace-nowrap group-hover:invisible">
                                                    {comment.time}
                                                </span>
                                                <div className="absolute inset-0 flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                    <button
                                                        onClick={() =>
                                                            togglePin(
                                                                comment.id,
                                                            )
                                                        }
                                                        className={`rounded-md p-1.5 transition-colors ${isPinned ? 'bg-yellow-500/10 text-yellow-500' : 'text-muted-foreground hover:bg-muted'}`}
                                                        title={
                                                            isPinned
                                                                ? 'Bỏ ghim'
                                                                : 'Ghim'
                                                        }
                                                    >
                                                        {isPinned ? (
                                                            <PinOffIcon className="size-[15px]" />
                                                        ) : (
                                                            <PinIcon className="size-[15px]" />
                                                        )}
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            toggleOrder(
                                                                comment.id,
                                                            )
                                                        }
                                                        className={`rounded-md p-1.5 transition-colors ${isOrder ? 'bg-emerald-500/10 text-emerald-500' : 'text-muted-foreground hover:bg-muted'}`}
                                                        title={
                                                            isOrder
                                                                ? 'Bỏ đánh dấu'
                                                                : 'Đánh dấu chốt đơn'
                                                        }
                                                    >
                                                        <ShoppingCartIcon className="size-[15px]" />
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            copyComment(
                                                                comment.text,
                                                                comment.id,
                                                            )
                                                        }
                                                        className="text-muted-foreground hover:bg-muted rounded-md p-1.5 transition-colors"
                                                        title="Copy bình luận"
                                                    >
                                                        {copiedId ===
                                                        comment.id ? (
                                                            <CheckIcon className="size-[15px] text-emerald-500" />
                                                        ) : (
                                                            <ClipboardCopyIcon className="size-[15px]" />
                                                        )}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p className="text-muted-foreground text-sm break-words">
                                            {renderCommentText(comment.text)}
                                        </p>
                                        {!isAnalyzing &&
                                            (comment.intent_tag ||
                                                comment.question_tag ||
                                                comment.product_tag) && (
                                                <div className="flex flex-wrap items-center gap-1 pt-0.5">
                                                    {comment.intent_tag &&
                                                        !(
                                                            comment.intent_tag ===
                                                                'Hỏi thông tin' &&
                                                            comment.question_tag
                                                        ) && (
                                                            <span
                                                                className={`inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-medium ${
                                                                    comment.intent_tag ===
                                                                    'Chốt đơn'
                                                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                                        : comment.intent_tag ===
                                                                            'Hỏi thông tin'
                                                                          ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                                                          : comment.intent_tag ===
                                                                              'Phản hồi SP'
                                                                            ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400'
                                                                            : comment.intent_tag ===
                                                                                'Yêu cầu hỗ trợ'
                                                                              ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                                                              : 'bg-gray-500/10 text-gray-600 dark:text-gray-400'
                                                                }`}
                                                            >
                                                                {(() => {
                                                                    const IconComponent =
                                                                        comment.intent_tag ===
                                                                        'Chốt đơn'
                                                                            ? ShoppingCartIcon
                                                                            : comment.intent_tag ===
                                                                                'Hỏi thông tin'
                                                                              ? HelpCircleIcon
                                                                              : comment.intent_tag ===
                                                                                  'Phản hồi SP'
                                                                                ? MessageSquareIcon
                                                                                : comment.intent_tag ===
                                                                                    'Yêu cầu hỗ trợ'
                                                                                  ? AlertTriangleIcon
                                                                                  : MessageSquareIcon;
                                                                    return (
                                                                        <IconComponent className="size-2.5" />
                                                                    );
                                                                })()}
                                                                {
                                                                    comment.intent_tag
                                                                }
                                                            </span>
                                                        )}
                                                    {comment.question_tag && (
                                                        <span className="inline-flex items-center gap-0.5 rounded bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                                            <HelpCircleIcon className="size-2.5" />
                                                            {
                                                                comment.question_tag
                                                            }
                                                        </span>
                                                    )}
                                                    {comment.product_tag && (
                                                        <span className="inline-flex items-center gap-0.5 rounded bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-600 dark:text-blue-400">
                                                            <PackageIcon className="size-2.5" />
                                                            {
                                                                comment.product_tag
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
                {hasMore && (
                    <InfiniteScrollSentinel
                        onLoadMore={() => setVisibleCount((p) => p + BATCH)}
                    />
                )}
            </FadeScrollArea>
        </Card>
    );
}

function ProductsPanel() {
    const { topProducts } = useLiveData();
    return (
        <Card className="flex h-full flex-col">
            <CardHeader>
                <CardTitle>Sản phẩm được nhắc đến</CardTitle>
                <CardDescription>
                    Xếp hạng theo số lượt nhắc trong bình luận (cập nhật
                    realtime)
                </CardDescription>
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
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                #
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Sản phẩm
                            </th>
                            <th className="text-foreground h-10 px-2 text-right font-medium">
                                Lượt nhắc
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Cảm xúc
                            </th>
                            <th className="text-foreground h-10 px-2 text-right font-medium">
                                Câu hỏi
                            </th>
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
                                <tr>
                                    <td colSpan={5}>
                                        <Empty className="py-12">
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <PackageIcon />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    Chưa có sản phẩm
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    Khi người xem nhắc đến sản
                                                    phẩm, thống kê sẽ hiển thị
                                                    tại đây.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </td>
                                </tr>
                            ) : (
                                topProducts.map((product, i) => (
                                    <tr
                                        key={product.name}
                                        className="hover:bg-muted/50 border-b transition-colors"
                                    >
                                        <td className="text-muted-foreground p-2 font-bold">
                                            {i + 1}
                                        </td>
                                        <td className="p-2">
                                            <div className="flex items-center gap-2.5">
                                                <div className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-md text-xs font-bold">
                                                    {product.name.charAt(0)}
                                                </div>
                                                <span className="truncate font-medium">
                                                    {product.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="p-2 text-right">
                                            <div className="inline-flex items-center gap-1">
                                                {product.mentions}
                                            </div>
                                        </td>
                                        <td className="p-2">
                                            <div className="flex items-center gap-2">
                                                <Progress
                                                    value={product.sentiment}
                                                    className="h-2 w-20"
                                                />
                                                <span className="text-muted-foreground text-xs">
                                                    {product.sentiment}%
                                                </span>
                                            </div>
                                        </td>
                                        <td className="p-2 text-right">
                                            {product.questions}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </FadeScrollArea>
        </Card>
    );
}

function QuestionsPanel() {
    const { topQuestions } = useLiveData();
    return (
        <Card className="flex h-full flex-col">
            <CardHeader>
                <CardTitle>Câu hỏi thường gặp</CardTitle>
                <CardDescription>
                    Phân loại và gom nhóm câu hỏi bởi AI
                </CardDescription>
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
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                #
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Câu hỏi
                            </th>
                            <th className="text-foreground h-10 px-2 text-right font-medium">
                                Số lần
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                SP
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Gợi ý trả lời
                            </th>
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
                                <tr>
                                    <td colSpan={5}>
                                        <Empty className="py-12">
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <HelpCircleIcon />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    Chưa có câu hỏi
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    Khi người xem đặt câu hỏi về
                                                    sản phẩm, AI sẽ phân loại và
                                                    hiển thị tại đây.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </td>
                                </tr>
                            ) : (
                                topQuestions.map((q, i) => (
                                    <tr
                                        key={q.question}
                                        className="hover:bg-muted/50 border-b transition-colors"
                                    >
                                        <td className="text-muted-foreground p-2 font-bold">
                                            {i + 1}
                                        </td>
                                        <td className="truncate p-2 font-medium">
                                            {q.question}
                                        </td>
                                        <td className="p-2 text-right">
                                            <Badge variant="secondary">
                                                {q.count}
                                            </Badge>
                                        </td>
                                        <td className="text-muted-foreground truncate p-2 text-sm">
                                            {q.product}
                                        </td>
                                        <td className="p-2">
                                            <span className="text-muted-foreground text-xs">
                                                {q.question === 'Hỏi giá'
                                                    ? 'Nên báo giá trực tiếp'
                                                    : q.question === 'Hỏi size'
                                                      ? 'Nên tư vấn size'
                                                      : q.question === 'Hỏi màu'
                                                        ? 'Nên show màu sắc'
                                                        : q.question ===
                                                            'Hỏi tồn kho'
                                                          ? 'Nên check kho'
                                                          : q.question ===
                                                              'Hỏi ship'
                                                            ? 'Thông báo phí ship'
                                                            : q.question ===
                                                                'Hỏi công dụng'
                                                              ? 'Tư vấn công dụng/lợi ích'
                                                              : q.question ===
                                                                  'Hỏi chất liệu'
                                                                ? 'Nêu rõ chất liệu sản phẩm'
                                                                : q.question ===
                                                                    'Hỏi bảo hành'
                                                                  ? 'Tư vấn chính sách bảo hành'
                                                                  : q.question ===
                                                                      'Hỏi cấu hình'
                                                                    ? 'Nêu rõ cấu hình/thông số kỹ thuật'
                                                                    : q.question ===
                                                                        'Hỏi trả góp'
                                                                      ? 'Tư vấn chính sách trả góp'
                                                                      : q.question ===
                                                                          'Hỏi xuất xứ'
                                                                        ? 'Nêu rõ xuất xứ/thương hiệu'
                                                                        : q.question ===
                                                                            'Hỏi phụ kiện'
                                                                          ? 'Liệt kê phụ kiện đi kèm'
                                                                          : q.question ===
                                                                              'Hỏi tình trạng'
                                                                            ? 'Báo rõ độ mới/tình trạng sản phẩm'
                                                                            : q.question ===
                                                                                'Hỏi quà tặng'
                                                                              ? 'Báo các phần quà/khuyến mãi kèm theo'
                                                                              : 'Tư vấn & hỗ trợ khách hàng'}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </FadeScrollArea>
        </Card>
    );
}

function CustomersPanel() {
    const { potentialCustomers, setPotentialCustomers } = useLiveData();
    const { auth } = usePage().props as unknown as {
        auth: {
            subscription: {
                features?: {
                    export_leads?: boolean;
                };
            } | null;
        };
    };
    const [showUpgradeDialog, setShowUpgradeDialog] = React.useState(false);
    const canExportLeads = auth?.subscription?.features?.export_leads ?? false;

    const [search, setSearch] = React.useState('');
    const [copiedPhone, setCopiedPhone] = React.useState<number | null>(null);
    const [copiedAll, setCopiedAll] = React.useState(false);

    const [orderDialog, setOrderDialog] = React.useState<{
        open: boolean;
        customerIdx: number | null;
    }>({ open: false, customerIdx: null });
    const [orderForm, setOrderForm] = React.useState({
        qty: 1,
        note: '',
        status: 'pending',
    });
    const filtered = potentialCustomers.filter(
        (c) =>
            !search ||
            c.name.toLowerCase().includes(search.toLowerCase()) ||
            c.phone.includes(search) ||
            c.product.toLowerCase().includes(search.toLowerCase()),
    );
    const copyPhone = (phone: string, idx: number) => {
        navigator.clipboard.writeText(phone);
        setCopiedPhone(idx);
        setTimeout(() => setCopiedPhone(null), 1500);
        toast.success(`Đã sao chép số điện thoại: ${phone}`);
    };
    const handleCopyAll = () => {
        if (!canExportLeads) {
            setShowUpgradeDialog(true);
            return;
        }
        copyLeadsToClipboard(filtered);
        setCopiedAll(true);
        setTimeout(() => setCopiedAll(false), 2000);
        toast.success('Đã sao chép danh sách khách hàng tiềm năng!');
    };
    const openOrderDialog = (idx: number) => {
        const customer = filtered[idx];
        setOrderForm({
            qty: customer.qty ?? 1,
            note: customer.note ?? '',
            status: customer.status ?? 'pending',
        });
        setOrderDialog({ open: true, customerIdx: idx });
    };
    const saveOrder = async () => {
        if (orderDialog.customerIdx === null) return;
        const customer = filtered[orderDialog.customerIdx];

        try {
            const res = await fetch(`/api/live-events/${customer.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    qty: orderForm.qty,
                    note: orderForm.note,
                    status: orderForm.status,
                }),
            });
            if (res.ok) {
                if (setPotentialCustomers) {
                    setPotentialCustomers((prev) =>
                        prev.map((c) =>
                            c.id === customer.id
                                ? {
                                      ...c,
                                      qty: orderForm.qty,
                                      note: orderForm.note,
                                      status: orderForm.status,
                                  }
                                : c,
                        ),
                    );
                }
                setOrderDialog({ open: false, customerIdx: null });
                toast.success('Đã lưu đơn hàng thành công!');
            } else {
                toast.error('Có lỗi xảy ra khi lưu đơn hàng.');
            }
        } catch (e) {
            console.error(e);
            toast.error('Có lỗi kết nối mạng.');
        }
    };
    const orderCustomer =
        orderDialog.customerIdx !== null
            ? filtered[orderDialog.customerIdx]
            : null;
    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle>Khách hàng tiềm năng</CardTitle>
                        <CardDescription>
                            Khách chốt đơn hoặc có SĐT/địa chỉ từ bình luận ·{' '}
                            {filtered.length} khách ·{' '}
                            {
                                potentialCustomers.filter(
                                    (c) => c.status && c.status !== '',
                                ).length
                            }{' '}
                            đơn
                        </CardDescription>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 gap-1.5 text-xs"
                            onClick={handleCopyAll}
                        >
                            {copiedAll ? (
                                <CheckIcon className="size-3 text-emerald-500" />
                            ) : (
                                <ClipboardListIcon className="size-3" />
                            )}
                            {copiedAll ? 'Đã copy' : 'Copy tất cả'}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 gap-1.5 text-xs"
                            onClick={() => {
                                if (!canExportLeads) {
                                    setShowUpgradeDialog(true);
                                } else {
                                    exportLeadsCSV(filtered);
                                }
                            }}
                        >
                            <DownloadIcon className="size-3" />
                            Xuất CSV
                        </Button>
                    </div>
                </div>
                <div className="relative max-w-xs pt-2">
                    <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                    <Input
                        placeholder="Tìm theo tên, SĐT, SP..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-8 pl-9 text-sm"
                    />
                </div>
            </CardHeader>
            <div className="px-4">
                <table className="w-full table-fixed text-sm">
                    <colgroup>
                        <col className="w-[20%]" />
                        <col className="w-[13%]" />
                        <col className="w-[15%]" />
                        <col className="w-[16%]" />
                        <col className="w-[24%]" />
                        <col className="w-[12%]" />
                    </colgroup>
                    <thead className="[&_tr]:border-b">
                        <tr className="border-b">
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Tên
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                SĐT
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Thời gian
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                SP quan tâm
                            </th>
                            <th className="text-foreground h-10 px-2 text-left font-medium">
                                Nội dung
                            </th>
                            <th className="text-foreground h-10 px-2 text-center font-medium">
                                Đơn
                            </th>
                        </tr>
                    </thead>
                </table>
            </div>
            <FadeScrollArea>
                <div className="px-4">
                    <table className="w-full table-fixed text-sm">
                        <colgroup>
                            <col className="w-[20%]" />
                            <col className="w-[13%]" />
                            <col className="w-[15%]" />
                            <col className="w-[16%]" />
                            <col className="w-[24%]" />
                            <col className="w-[12%]" />
                        </colgroup>
                        <tbody className="[&_tr:last-child]:border-0">
                            {filtered.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>
                                        <Empty className="py-12">
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <UsersIcon />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    {search
                                                        ? 'Không tìm thấy khách hàng'
                                                        : 'Chưa có khách tiềm năng'}
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    {search
                                                        ? `Không có khách hàng nào khớp với "${search}"`
                                                        : 'Khi người xem để lại SĐT hoặc có ý định mua hàng, thông tin sẽ hiển thị tại đây.'}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </td>
                                </tr>
                            ) : (
                                filtered.map((c, i) => {
                                    return (
                                        <tr
                                            key={i}
                                            className="hover:bg-muted/50 border-b transition-colors"
                                        >
                                            <td
                                                className="truncate p-2 font-medium"
                                                title={c.name}
                                            >
                                                {c.name}
                                            </td>
                                            <td className="p-2">
                                                {c.phone ? (
                                                    <div className="flex items-center gap-1">
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1"
                                                        >
                                                            <PhoneIcon className="size-3" />
                                                            {c.phone}
                                                        </Badge>
                                                        <button
                                                            onClick={() =>
                                                                copyPhone(
                                                                    c.phone,
                                                                    i,
                                                                )
                                                            }
                                                            className="hover:bg-muted shrink-0 rounded p-1 transition-colors"
                                                            title="Copy SĐT"
                                                        >
                                                            {copiedPhone ===
                                                            i ? (
                                                                <CheckIcon className="size-3 text-emerald-500" />
                                                            ) : (
                                                                <CopyIcon className="text-muted-foreground size-3" />
                                                            )}
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className="truncate p-2"
                                                title={c.time}
                                            >
                                                {c.time || (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-2">
                                                {c.product ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="max-w-full truncate"
                                                    >
                                                        {c.product}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className="text-muted-foreground truncate p-2 text-sm"
                                                title={c.comment}
                                            >
                                                {renderCommentText(c.comment)}
                                            </td>
                                            <td className="p-2 text-center">
                                                {c.status && c.status !== '' ? (
                                                    <button
                                                        onClick={() =>
                                                            openOrderDialog(i)
                                                        }
                                                        className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-500 transition-colors hover:bg-emerald-500/20"
                                                    >
                                                        <CheckIcon className="size-2.5" />
                                                        {c.status === 'pending'
                                                            ? 'Chờ'
                                                            : c.status ===
                                                                'confirmed'
                                                              ? 'Xác nhận'
                                                              : 'Ship'}
                                                    </button>
                                                ) : (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-6 gap-1 px-2 text-[10px]"
                                                        onClick={() =>
                                                            openOrderDialog(i)
                                                        }
                                                    >
                                                        <FileTextIcon className="size-2.5" />
                                                        Tạo đơn
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </FadeScrollArea>

            {/* Order Creation Dialog */}
            <Dialog
                open={orderDialog.open}
                onOpenChange={(open) =>
                    setOrderDialog({
                        open,
                        customerIdx: open ? orderDialog.customerIdx : null,
                    })
                }
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FileTextIcon className="size-5" />
                            Tạo đơn hàng
                        </DialogTitle>
                        <DialogDescription>
                            {orderCustomer
                                ? `${orderCustomer.name} — ${orderCustomer.product}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    {orderCustomer && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-3 text-sm">
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">
                                        Khách hàng
                                    </Label>
                                    <p className="font-medium">
                                        {orderCustomer.name}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">
                                        SĐT
                                    </Label>
                                    <p className="font-medium">
                                        {orderCustomer.phone || '— Chưa có'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">
                                        Thời gian
                                    </Label>
                                    <p className="font-medium">
                                        {orderCustomer.time || '— Chưa có'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">
                                        Sản phẩm
                                    </Label>
                                    <p className="font-medium">
                                        {orderCustomer.product}
                                    </p>
                                </div>
                            </div>
                            <Separator />
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="order-qty">Số lượng</Label>
                                    <Input
                                        id="order-qty"
                                        type="number"
                                        min={1}
                                        value={orderForm.qty}
                                        onChange={(e) =>
                                            setOrderForm((f) => ({
                                                ...f,
                                                qty:
                                                    parseInt(e.target.value) ||
                                                    1,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Trạng thái</Label>
                                    <Select
                                        value={orderForm.status}
                                        onValueChange={(v) =>
                                            setOrderForm((f) => ({
                                                ...f,
                                                status: v,
                                            }))
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">
                                                Chờ xác nhận
                                            </SelectItem>
                                            <SelectItem value="confirmed">
                                                Đã xác nhận
                                            </SelectItem>
                                            <SelectItem value="shipping">
                                                Đang giao
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="order-note">Ghi chú</Label>
                                <Input
                                    id="order-note"
                                    placeholder="VD: Ship nhanh, gói quà..."
                                    value={orderForm.note}
                                    onChange={(e) =>
                                        setOrderForm((f) => ({
                                            ...f,
                                            note: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() =>
                                setOrderDialog({
                                    open: false,
                                    customerIdx: null,
                                })
                            }
                        >
                            Hủy
                        </Button>
                        <Button onClick={saveOrder} className="gap-1.5">
                            <CheckIcon className="size-4" />
                            Lưu đơn
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Upgrade Dialog */}
            <Dialog
                open={showUpgradeDialog}
                onOpenChange={setShowUpgradeDialog}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <SparklesIcon className="text-primary size-5" /> Yêu
                            cầu nâng cấp gói dịch vụ
                        </DialogTitle>
                        <DialogDescription>
                            Tính năng xuất danh sách khách hàng tiềm năng (CSV &
                            Copy) chỉ dành cho thành viên sử dụng gói Pro hoặc
                            Enterprise. Vui lòng nâng cấp gói dịch vụ để mở khóa
                            tính năng này.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="flex-col gap-2 sm:flex-row">
                        <Button
                            variant="outline"
                            onClick={() => setShowUpgradeDialog(false)}
                        >
                            Đóng
                        </Button>
                        <Button
                            onClick={() => {
                                setShowUpgradeDialog(false);
                                router.visit('/subscription');
                            }}
                            className="bg-primary text-primary-foreground"
                        >
                            Nâng cấp ngay
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

interface AiInsightsData {
    overview?: string;
    trends?: string;
    recommendations?: string;
}

function parseAiInsights(insights: string | null | undefined): AiInsightsData | null {
    if (!insights) return null;
    try {
        const parsed = JSON.parse(insights);
        if (
            parsed &&
            typeof parsed === 'object' &&
            ('overview' in parsed || 'trends' in parsed || 'recommendations' in parsed)
        ) {
            return parsed as AiInsightsData;
        }
    } catch (e) {
        // Not a JSON string
    }
    return null;
}

function AIInsightsPanel() {
    const {
        session,
        setSession,
        stats,
        topProducts,
        topQuestions,
        potentialCustomers,
    } = useLiveData();

    const [isRefreshing, setIsRefreshing] = React.useState(false);

    const handleRefresh = async () => {
        if (!setSession) return;
        setIsRefreshing(true);
        try {
            const res = await fetch(
                route('lives.refresh-insights', session.id),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') ?? '',
                        Accept: 'application/json',
                    },
                },
            );
            if (res.ok) {
                const data = await res.json();
                setSession((prev) => ({
                    ...prev,
                    ai_insights: data.ai_insights,
                    ai_alerts: data.ai_alerts,
                }));
                toast.success('Đã làm mới tổng kết và cảnh báo AI!');
            } else {
                const errData = await res.json().catch(() => ({}));
                toast.error(errData.error || 'Không thể làm mới dữ liệu AI.');
            }
        } catch (err) {
            console.error(err);
            toast.error('Có lỗi xảy ra khi kết nối máy chủ.');
        } finally {
            setIsRefreshing(false);
        }
    };

    const sentimentTotal =
        stats.sentiment_positive +
        stats.sentiment_neutral +
        stats.sentiment_negative;
    const positivePct =
        sentimentTotal > 0
            ? Math.round((stats.sentiment_positive / sentimentTotal) * 100)
            : 0;
    const negativePct =
        sentimentTotal > 0
            ? Math.round((stats.sentiment_negative / sentimentTotal) * 100)
            : 0;
    const topProduct = topProducts[0];
    const topQuestion = topQuestions[0];
    const conversionRate =
        stats.total_comments > 0
            ? ((stats.leads_count / stats.total_comments) * 100).toFixed(1)
            : '0';

    // Build dynamic alerts from real data
    const dynamicAlerts: {
        icon: React.ComponentType<{ className?: string }>;
        title: string;
        desc: string;
        color: string;
        severity: string;
    }[] = [];

    if (topQuestion && topQuestion.count > 3) {
        dynamicAlerts.push({
            icon: AlertTriangleIcon,
            title: 'Câu hỏi phổ biến',
            desc: `"${topQuestion.question}" được hỏi ${topQuestion.count} lần — nên trả lời ngay.`,
            color: 'amber',
            severity: 'Cao',
        });
    }

    if (topProduct && topProduct.sentiment >= 80) {
        dynamicAlerts.push({
            icon: FlameIcon,
            title: 'Sản phẩm đang hot',
            desc: `"${topProduct.name}" có cảm xúc tích cực ${topProduct.sentiment}% với ${topProduct.mentions} lượt nhắc.`,
            color: 'emerald',
            severity: 'Thông tin',
        });
    }

    if (stats.sentiment_negative >= 3 && negativePct > 10) {
        const isRed = negativePct >= 25;
        dynamicAlerts.push({
            icon: HeartCrackIcon,
            title: 'Cảm xúc tiêu cực tăng',
            desc: `Tỷ lệ tiêu cực hiện tại: ${negativePct}% (${stats.sentiment_negative} bình luận).`,
            color: isRed ? 'red' : 'amber',
            severity: isRed ? 'Cao' : 'Trung bình',
        });
    }

    if (stats.leads_count > 0) {
        dynamicAlerts.push({
            icon: ZapIcon,
            title: 'Khách hàng chốt đơn',
            desc: `${stats.leads_count} khách đã thể hiện ý định mua hàng. Tỷ lệ chuyển đổi: ${conversionRate}%.`,
            color: 'blue',
            severity: 'Trung bình',
        });
    }

    if (potentialCustomers.length > 0) {
        const withPhone = potentialCustomers.filter((c) => c.phone).length;
        dynamicAlerts.push({
            icon: LightbulbIcon,
            title: 'Khách tiềm năng',
            desc: `${potentialCustomers.length} khách tiềm năng${withPhone > 0 ? `, ${withPhone} để lại SĐT` : ''}.`,
            color: 'cyan',
            severity: 'Thông tin',
        });
    }

    if (dynamicAlerts.length === 0) {
        dynamicAlerts.push({
            icon: SparklesIcon,
            title: 'Đang thu thập dữ liệu',
            desc: 'AI đang phân tích bình luận. Cảnh báo sẽ hiện khi có đủ dữ liệu.',
            color: 'blue',
            severity: 'Thông tin',
        });
    }

    const alertTypeConfig: Record<
        'danger' | 'warning' | 'info' | 'success',
        {
            icon: React.ComponentType<{ className?: string }>;
            variant: 'default' | 'destructive';
            alertClass: string;
            actionClass: string;
        }
    > = {
        danger: {
            icon: AlertTriangleIcon,
            variant: 'destructive',
            alertClass: 'border-destructive/20 bg-destructive/5',
            actionClass: 'mt-2 rounded p-2 text-xs font-medium bg-destructive/10 text-destructive dark:text-red-300 border border-destructive/20',
        },
        warning: {
            icon: AlertTriangleIcon,
            variant: 'default',
            alertClass:
                'border-amber-500/30 bg-amber-500/5 text-amber-600 dark:text-amber-400 [&>svg]:text-amber-500',
            actionClass:
                'bg-amber-500/10 text-amber-800 dark:text-amber-300 border border-amber-500/20',
        },
        info: {
            icon: LightbulbIcon,
            variant: 'default',
            alertClass:
                'border-blue-500/30 bg-blue-500/5 text-blue-600 dark:text-blue-400 [&>svg]:text-blue-500',
            actionClass:
                'bg-blue-500/10 text-blue-800 dark:text-blue-300 border border-blue-500/20',
        },
        success: {
            icon: SparklesIcon,
            variant: 'default',
            alertClass:
                'border-emerald-500/30 bg-emerald-500/5 text-emerald-600 dark:text-emerald-400 [&>svg]:text-emerald-500',
            actionClass:
                'bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 border border-emerald-500/20',
        },
    };

    return (
        <div className="grid h-full min-h-0 gap-4 md:grid-cols-2">
            {/* Tổng kết */}
            <Card className="flex min-h-0 flex-col">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <SparklesIcon className="size-5" />
                            Tổng kết AI
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            {sentimentTotal < stats.total_comments && (
                                <Badge
                                    variant="secondary"
                                    className="animate-pulse gap-1 text-[10px] font-normal"
                                >
                                    <LoaderIcon className="size-2.5 animate-spin" />
                                    Đang xử lý{' '}
                                    {stats.total_comments - sentimentTotal} bình
                                    luận...
                                </Badge>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleRefresh}
                                disabled={isRefreshing}
                                className="h-8 gap-1.5 px-2.5"
                            >
                                <RefreshCw
                                    className={`size-3.5 ${isRefreshing ? 'animate-spin' : ''}`}
                                />
                                Làm mới
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <FadeScrollArea>
                    <div className="text-muted-foreground space-y-3 px-4 text-sm">
                        {session.ai_insights ? (
                            (() => {
                                const parsed = parseAiInsights(session.ai_insights);
                                if (parsed) {
                                    return (
                                        <div className="space-y-3">
                                            {parsed.overview && (
                                                <div className="rounded-lg border border-indigo-500/20 bg-indigo-500/5 p-3 shadow-sm transition-all hover:shadow-md">
                                                    <div className="mb-1.5 flex items-center gap-2 font-semibold text-indigo-600 dark:text-indigo-400">
                                                        <SparklesIcon className="size-4" />
                                                        <span>Tổng quan diễn biến</span>
                                                    </div>
                                                    <p className="text-muted-foreground text-sm leading-relaxed whitespace-pre-line">
                                                        {parsed.overview}
                                                    </p>
                                                </div>
                                            )}
                                            {parsed.trends && (
                                                <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 shadow-sm transition-all hover:shadow-md">
                                                    <div className="mb-1.5 flex items-center gap-2 font-semibold text-amber-600 dark:text-amber-400">
                                                        <FlameIcon className="size-4" />
                                                        <span>Xu hướng mua hàng & sản phẩm</span>
                                                    </div>
                                                    <p className="text-muted-foreground text-sm leading-relaxed whitespace-pre-line">
                                                        {parsed.trends}
                                                    </p>
                                                </div>
                                            )}
                                            {parsed.recommendations && (
                                                <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 shadow-sm transition-all hover:shadow-md">
                                                    <div className="mb-1.5 flex items-center gap-2 font-semibold text-emerald-600 dark:text-emerald-400">
                                                        <LightbulbIcon className="size-4" />
                                                        <span>Khuyến nghị cho streamer</span>
                                                    </div>
                                                    <p className="text-muted-foreground text-sm leading-relaxed whitespace-pre-line">
                                                        {parsed.recommendations}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    );
                                }
                                return (
                                    <p className="text-sm whitespace-pre-line">
                                        {session.ai_insights}
                                    </p>
                                );
                            })()
                        ) : sentimentTotal > 0 ? (
                            <>
                                <p>
                                    <strong className="text-foreground">
                                        Cảm xúc tích cực chiếm {positivePct}%
                                    </strong>{' '}
                                    ({stats.sentiment_positive} bình luận tích
                                    cực / {sentimentTotal} tổng).
                                </p>
                                {topProduct && (
                                    <p>
                                        <strong className="text-foreground">
                                            Sản phẩm nổi bật:
                                        </strong>{' '}
                                        "{topProduct.name}" được nhắc nhiều nhất
                                        với {topProduct.mentions} lượt, cảm xúc
                                        tích cực {topProduct.sentiment}%.
                                    </p>
                                )}
                                {topQuestions.length > 0 && (
                                    <p>
                                        <strong className="text-foreground">
                                            Gợi ý:
                                        </strong>{' '}
                                        Nên trả lời câu hỏi về{' '}
                                        {topQuestions
                                            .slice(0, 3)
                                            .map((q) => `"${q.question}"`)
                                            .join(', ')}
                                        .
                                    </p>
                                )}
                                {potentialCustomers.length > 0 && (
                                    <p>
                                        <strong className="text-foreground">
                                            Khách hàng:
                                        </strong>{' '}
                                        {potentialCustomers.length} khách tiềm
                                        năng, tỷ lệ chuyển đổi {conversionRate}%
                                        từ bình luận.
                                    </p>
                                )}
                            </>
                        ) : (
                            <p>
                                AI đang xử lý bình luận. Tổng kết sẽ hiện khi có
                                đủ dữ liệu phân tích.
                            </p>
                        )}
                    </div>
                </FadeScrollArea>
            </Card>

            {/* Cảnh báo */}
            <Card className="flex min-h-0 flex-col">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <BellRingIcon className="size-4" />
                        Cảnh báo AI
                    </CardTitle>
                </CardHeader>
                <FadeScrollArea>
                    <div className="space-y-3 px-4">
                        {session.ai_alerts && session.ai_alerts.length > 0
                            ? session.ai_alerts.map((alert, idx) => {
                                  const c =
                                      alertTypeConfig[alert.type] ??
                                      alertTypeConfig.info;
                                  const Icon = c.icon;
                                  return (
                                      <Alert
                                          key={idx}
                                          variant={c.variant}
                                          className={c.alertClass}
                                      >
                                          <Icon className="size-4" />
                                          <AlertTitle className="text-sm font-semibold">
                                              {alert.title}
                                          </AlertTitle>
                                          <AlertDescription className="text-xs leading-relaxed">
                                              <p>{alert.desc}</p>
                                              {alert.action && (
                                                  <div
                                                      className={`mt-2 rounded p-2 text-xs font-medium ${c.actionClass}`}
                                                  >
                                                      Gợi ý hành động:{' '}
                                                      {alert.action}
                                                  </div>
                                              )}
                                          </AlertDescription>
                                      </Alert>
                                  );
                              })
                            : dynamicAlerts.map((alert) => {
                                  const colorMap: Record<
                                      string,
                                      {
                                          variant: 'default' | 'destructive';
                                          alertClass: string;
                                          badgeVariant: 'default' | 'destructive' | 'secondary' | 'outline';
                                      }
                                  > = {
                                      amber: {
                                          variant: 'default',
                                          alertClass:
                                              'border-amber-500/20 bg-amber-500/5 text-amber-600 dark:text-amber-400 [&>svg]:text-amber-500',
                                          badgeVariant: 'secondary',
                                      },
                                      emerald: {
                                          variant: 'default',
                                          alertClass:
                                              'border-emerald-500/20 bg-emerald-500/5 text-emerald-600 dark:text-emerald-400 [&>svg]:text-emerald-500',
                                          badgeVariant: 'secondary',
                                      },
                                      blue: {
                                          variant: 'default',
                                          alertClass:
                                              'border-blue-500/20 bg-blue-500/5 text-blue-600 dark:text-blue-400 [&>svg]:text-blue-500',
                                          badgeVariant: 'secondary',
                                      },
                                      red: {
                                          variant: 'destructive',
                                          alertClass:
                                              'border-destructive/20 bg-destructive/5',
                                          badgeVariant: 'destructive',
                                      },
                                      cyan: {
                                          variant: 'default',
                                          alertClass:
                                              'border-cyan-500/20 bg-cyan-500/5 text-cyan-600 dark:text-cyan-400 [&>svg]:text-cyan-500',
                                          badgeVariant: 'secondary',
                                      },
                                  };
                                  const c =
                                      colorMap[alert.color] ?? colorMap.blue;
                                  const Icon = alert.icon;
                                  return (
                                      <Alert
                                          key={alert.title}
                                          variant={c.variant}
                                          className={c.alertClass}
                                      >
                                          <Icon className="size-4" />
                                          <AlertTitle className="text-sm font-semibold">
                                              {alert.title}
                                          </AlertTitle>
                                          <AlertDescription className="text-xs leading-relaxed">
                                              <p>{alert.desc}</p>
                                              <Badge
                                                  variant={c.badgeVariant}
                                                  className="mt-1.5 text-[10px] font-medium"
                                              >
                                                  {alert.severity}
                                              </Badge>
                                          </AlertDescription>
                                      </Alert>
                                  );
                              })}
                    </div>
                </FadeScrollArea>
            </Card>
        </div>
    );
}

function StatsPanel() {
    const { stats, topProducts, statsHistory, potentialCustomersCount } =
        useLiveData();

    // Dữ liệu hoạt động — cập nhật từ statsHistory của backend
    const activityData =
        statsHistory && statsHistory.length > 0
            ? statsHistory
            : [
                  {
                      time: 'Hiện tại',
                      comments: stats.total_comments,
                      viewers: stats.viewer_count,
                  },
              ];

    const sentimentData = [
        {
            name: 'positive',
            value: stats.sentiment_positive,
            fill: 'var(--color-positive)',
        },
        {
            name: 'neutral',
            value: stats.sentiment_neutral,
            fill: 'var(--color-neutral)',
        },
        {
            name: 'negative',
            value: stats.sentiment_negative,
            fill: 'var(--color-negative)',
        },
    ];

    const productData = topProducts.slice(0, 6).map((p) => ({
        name: p.name.length > 12 ? p.name.slice(0, 12) + '…' : p.name,
        mentions: p.mentions,
        questions: p.questions,
    }));

    const funnelData = [
        { stage: 'Người xem', value: stats.total_views },
        { stage: 'Bình luận', value: stats.total_comments },
        { stage: 'KH tiềm năng', value: potentialCustomersCount ?? 0 },
        { stage: 'Chốt đơn', value: stats.leads_count },
    ];

    const activityConfig = {
        comments: { label: 'Bình luận', color: 'var(--chart-1)' },
        viewers: { label: 'Người xem', color: 'var(--chart-2)' },
    } satisfies ChartConfig;

    const sentimentConfig = {
        positive: { label: 'Tích cực', color: '#22c55e' },
        neutral: { label: 'Trung lập', color: '#6b7280' },
        negative: { label: 'Tiêu cực', color: '#ef4444' },
    } satisfies ChartConfig;

    const productConfig = {
        mentions: { label: 'Lượt nhắc', color: 'var(--chart-1)' },
        questions: { label: 'Câu hỏi', color: 'var(--chart-4)' },
    } satisfies ChartConfig;

    // funnelConfig not needed — funnel uses custom bars

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex-1 space-y-4 overflow-y-auto">
                {/* KPI Cards Grid */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Thả tim
                            </CardTitle>
                            <HeartIcon className="size-4 animate-pulse text-rose-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tabular-nums">
                                {(stats.total_likes ?? 0).toLocaleString()}
                            </div>
                            <p className="text-muted-foreground mt-1 text-[10px]">
                                Lượt thích từ người xem
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Quà tặng
                            </CardTitle>
                            <GiftIcon className="size-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tabular-nums">
                                {(stats.total_gifts ?? 0).toLocaleString()}
                            </div>
                            <p className="text-muted-foreground mt-1 text-[10px]">
                                Tổng quà tặng gửi đến live
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Follower mới
                            </CardTitle>
                            <UserPlusIcon className="size-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tabular-nums">
                                {(stats.total_follows ?? 0).toLocaleString()}
                            </div>
                            <p className="text-muted-foreground mt-1 text-[10px]">
                                Lượt theo dõi trong phiên live
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                Chia sẻ
                            </CardTitle>
                            <Share2Icon className="size-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tabular-nums">
                                {(stats.total_shares ?? 0).toLocaleString()}
                            </div>
                            <p className="text-muted-foreground mt-1 text-[10px]">
                                Số lượt chia sẻ livestream
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid min-h-full gap-4 md:grid-cols-2">
                    {/* Activity Timeline */}
                    <Card className="flex flex-col md:col-span-2">
                        <CardHeader>
                            <CardTitle>Hoạt động theo thời gian</CardTitle>
                            <CardDescription>
                                Bình luận và lượt xem realtime mỗi 5 phút
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1">
                            <ChartContainer
                                config={activityConfig}
                                className="aspect-auto h-[250px] w-full"
                            >
                                <AreaChart
                                    accessibilityLayer
                                    data={activityData}
                                    margin={{
                                        top: 10,
                                        right: 10,
                                        left: 0,
                                        bottom: 0,
                                    }}
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="time"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        width={55}
                                    />
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <ChartLegend
                                        content={<ChartLegendContent />}
                                    />
                                    <defs>
                                        <linearGradient
                                            id="fillComments"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor="var(--color-comments)"
                                                stopOpacity={0.8}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor="var(--color-comments)"
                                                stopOpacity={0.1}
                                            />
                                        </linearGradient>
                                        <linearGradient
                                            id="fillViewers"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor="var(--color-viewers)"
                                                stopOpacity={0.8}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor="var(--color-viewers)"
                                                stopOpacity={0.1}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <Area
                                        dataKey="viewers"
                                        type="natural"
                                        fill="url(#fillViewers)"
                                        stroke="var(--color-viewers)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        dataKey="comments"
                                        type="natural"
                                        fill="url(#fillComments)"
                                        stroke="var(--color-comments)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    <Card className="flex flex-col">
                        <CardHeader>
                            <CardTitle>Phân bổ cảm xúc</CardTitle>
                            <CardDescription>
                                Tỷ lệ tích cực / trung lập / tiêu cực
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col">
                            <ChartContainer
                                config={sentimentConfig}
                                className="aspect-auto min-h-[160px] w-full flex-1"
                            >
                                <PieChart accessibilityLayer>
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <Pie
                                        data={sentimentData}
                                        dataKey="value"
                                        nameKey="name"
                                        innerRadius={50}
                                        strokeWidth={2}
                                    >
                                        <RechartsLabel
                                            content={({ viewBox }) => {
                                                if (
                                                    viewBox &&
                                                    'cx' in viewBox &&
                                                    'cy' in viewBox
                                                ) {
                                                    const total =
                                                        sentimentData.reduce(
                                                            (s, d) =>
                                                                s + d.value,
                                                            0,
                                                        );
                                                    return (
                                                        <text
                                                            x={viewBox.cx}
                                                            y={viewBox.cy}
                                                            textAnchor="middle"
                                                            dominantBaseline="middle"
                                                        >
                                                            <tspan
                                                                x={viewBox.cx}
                                                                y={
                                                                    (viewBox.cy ||
                                                                        0) - 6
                                                                }
                                                                className="fill-foreground text-2xl font-bold"
                                                            >
                                                                {total}
                                                            </tspan>
                                                            <tspan
                                                                x={viewBox.cx}
                                                                y={
                                                                    (viewBox.cy ||
                                                                        0) + 14
                                                                }
                                                                className="fill-muted-foreground text-xs"
                                                            >
                                                                bình luận
                                                            </tspan>
                                                        </text>
                                                    );
                                                }
                                            }}
                                        />
                                    </Pie>
                                </PieChart>
                            </ChartContainer>
                            {/* Breakdown visible without hover */}
                            <div className="mt-2 grid grid-cols-3 gap-2 border-t pt-2">
                                {sentimentData.map((d) => {
                                    const total = sentimentData.reduce(
                                        (s, item) => s + item.value,
                                        0,
                                    );
                                    const pct = (
                                        (d.value / total) *
                                        100
                                    ).toFixed(0);
                                    const colorMap: Record<string, string> = {
                                        positive: 'bg-[#22c55e]',
                                        neutral: 'bg-[#6b7280]',
                                        negative: 'bg-[#ef4444]',
                                    };
                                    const labelMap: Record<string, string> = {
                                        positive: 'Tích cực',
                                        neutral: 'Trung lập',
                                        negative: 'Tiêu cực',
                                    };
                                    return (
                                        <div
                                            key={d.name}
                                            className="text-center"
                                        >
                                            <div className="text-muted-foreground flex items-center justify-center gap-1.5 text-xs">
                                                <span
                                                    className={`size-2 rounded-full ${colorMap[d.name]}`}
                                                />
                                                {labelMap[d.name]}
                                            </div>
                                            <div className="text-sm font-bold tabular-nums">
                                                {d.value}{' '}
                                                <span className="text-muted-foreground text-xs font-normal">
                                                    ({pct}%)
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Top Products Bar */}
                    <Card className="flex flex-col">
                        <CardHeader>
                            <CardTitle>Top sản phẩm</CardTitle>
                            <CardDescription>
                                Lượt nhắc và câu hỏi theo sản phẩm
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1">
                            <ChartContainer
                                config={productConfig}
                                className="aspect-auto h-full min-h-[200px] w-full"
                            >
                                <BarChart
                                    accessibilityLayer
                                    data={productData}
                                    margin={{
                                        top: 10,
                                        right: 10,
                                        left: 0,
                                        bottom: 0,
                                    }}
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="name"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        tick={{ fontSize: 11 }}
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        width={55}
                                    />
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <ChartLegend
                                        content={<ChartLegendContent />}
                                    />
                                    <Bar
                                        dataKey="mentions"
                                        fill="var(--color-mentions)"
                                        radius={[4, 4, 0, 0]}
                                    />
                                    <Bar
                                        dataKey="questions"
                                        fill="var(--color-questions)"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>

                    {/* Conversion Funnel */}
                    <Card className="flex flex-col md:col-span-2">
                        <CardHeader>
                            <CardTitle>Phễu chuyển đổi</CardTitle>
                            <CardDescription>
                                Từ người xem → bình luận → để lại thông tin →
                                chốt đơn
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex-1">
                            <div className="flex h-full min-h-[120px] items-end justify-around gap-2">
                                {funnelData.map((item, i) => {
                                    const maxVal = funnelData[0].value;
                                    const heightPct = Math.max(
                                        15,
                                        (item.value / maxVal) * 100,
                                    );
                                    const conversionRate =
                                        i > 0
                                            ? (
                                                  (item.value /
                                                      funnelData[i - 1].value) *
                                                  100
                                              ).toFixed(1)
                                            : null;
                                    return (
                                        <div
                                            key={item.stage}
                                            className="flex flex-1 flex-col items-center gap-1.5"
                                        >
                                            {conversionRate && (
                                                <span className="text-muted-foreground text-[10px] font-medium">
                                                    ↓ {conversionRate}%
                                                </span>
                                            )}
                                            <div
                                                className="bg-primary/80 w-full max-w-[80px] rounded-t-md transition-all"
                                                style={{
                                                    height: `${heightPct}px`,
                                                }}
                                            />
                                            <span className="text-lg font-bold tabular-nums">
                                                {item.value.toLocaleString()}
                                            </span>
                                            <span className="text-muted-foreground text-center text-xs">
                                                {item.stage}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

// --- Main Page ---
export default function LivesShow({
    session: initialSession,
    stats: initialStats,
    comments: initialComments,
    topProducts: initialTopProducts,
    potentialCustomers: initialPotentialCustomers,
    topQuestions: initialTopQuestions,
    statsHistory: initialStatsHistory,
    potentialCustomersCount: initialPotentialCustomersCount,
    topKeywords: initialTopKeywords,
}: PageProps) {
    const [soundEnabled, setSoundEnabled] = React.useState(true);
    const [isOffline, setIsOffline] = React.useState(false);
    const [isStopping, setIsStopping] = React.useState(false);

    // Live state — updated via polling
    const [session, setSession] = React.useState(initialSession);
    const [stats, setStats] = React.useState<StatsData>(
        initialStats ?? {
            total_views: 0,
            total_comments: 0,
            total_likes: 0,
            total_gifts: 0,
            total_follows: 0,
            total_shares: 0,
            viewer_count: 0,
            leads_count: 0,
            sentiment_positive: 0,
            sentiment_neutral: 0,
            sentiment_negative: 0,
        },
    );
    const [comments, setComments] = React.useState<CommentData[]>(
        initialComments ?? [],
    );
    const [topProducts, setTopProducts] = React.useState(
        initialTopProducts ?? [],
    );
    const [potentialCustomers, setPotentialCustomers] = React.useState(
        initialPotentialCustomers ?? [],
    );
    const [topQuestions, setTopQuestions] = React.useState(
        initialTopQuestions ?? [],
    );
    const [statsHistory, setStatsHistory] = React.useState<StatsHistoryEntry[]>(
        initialStatsHistory ?? [],
    );
    const [potentialCustomersCount, setPotentialCustomersCount] =
        React.useState(initialPotentialCustomersCount ?? 0);
    const [topKeywords, setTopKeywords] = React.useState<TopKeyword[]>(
        initialTopKeywords ?? [],
    );

    // Order alerts — detect real "Chốt đơn" từ AI data
    const { alerts, dismiss } = useOrderAlerts(soundEnabled, comments);

    // Polling for live updates
    React.useEffect(() => {
        if (session.status !== 'live' && session.status !== 'connecting')
            return;
        if (!session.tiktok_session_id) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(
                    route('lives.fetch-events', session.id),
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                document
                                    .querySelector('meta[name="csrf-token"]')
                                    ?.getAttribute('content') ?? '',
                            Accept: 'application/json',
                        },
                    },
                );
                if (res.ok) {
                    setIsOffline(false);
                    const data = await res.json();
                    if (data.comments) setComments(data.comments);
                    if (data.stats) setStats(data.stats);
                    if (data.topProducts) setTopProducts(data.topProducts);
                    if (data.potentialCustomers)
                        setPotentialCustomers(data.potentialCustomers);
                    if (data.topQuestions) setTopQuestions(data.topQuestions);
                    if (data.statsHistory) setStatsHistory(data.statsHistory);
                    if (data.potentialCustomersCount !== undefined)
                        setPotentialCustomersCount(
                            data.potentialCustomersCount,
                        );
                    if (data.topKeywords) setTopKeywords(data.topKeywords);
                    if (data.status) {
                        setSession((prev) => ({
                            ...prev,
                            status: data.status,
                            error_message:
                                data.status === 'error'
                                    ? (data.error_message ?? prev.error_message)
                                    : prev.error_message,
                            duration: data.duration ?? prev.duration,
                            ai_insights:
                                data.ai_insights !== undefined
                                    ? data.ai_insights
                                    : prev.ai_insights,
                            ai_alerts:
                                data.ai_alerts !== undefined
                                    ? data.ai_alerts
                                    : prev.ai_alerts,
                        }));
                    }
                } else {
                    setIsOffline(true);
                }
            } catch (err) {
                setIsOffline(true);
            }
        }, 5000);

        return () => clearInterval(interval);
    }, [session.id, session.status, session.tiktok_session_id]);

    // Stop session handler
    const handleStop = () => {
        if (confirm('Bạn có chắc chắn muốn kết thúc phiên phân tích?')) {
            setIsStopping(true);
            router.post(
                route('lives.stop', session.id),
                {},
                {
                    onSuccess: () => {
                        toast.success(
                            'Đã kết thúc phiên phân tích thành công!',
                        );
                    },
                    onFinish: () => {
                        setIsStopping(false);
                    },
                },
            );
        }
    };

    // Sentiment calculation
    const sentimentTotal =
        stats.sentiment_positive +
        stats.sentiment_neutral +
        stats.sentiment_negative;
    const sentimentPositivePct =
        sentimentTotal > 0
            ? Math.round((stats.sentiment_positive / sentimentTotal) * 100)
            : 0;
    const sentimentNeutralPct =
        sentimentTotal > 0
            ? Math.round((stats.sentiment_neutral / sentimentTotal) * 100)
            : 0;
    const sentimentNegativePct =
        sentimentTotal > 0
            ? 100 - sentimentPositivePct - sentimentNeutralPct
            : 0;

    return (
        <AuthenticatedLayout>
            <LiveContext.Provider
                value={{
                    session,
                    setSession,
                    stats,
                    comments,
                    topProducts,
                    potentialCustomers,
                    topQuestions,
                    statsHistory,
                    potentialCustomersCount,
                    topKeywords,
                    setComments,
                    setPotentialCustomers,
                }}
            >
                <Head title={`${session.name} — Live`} />
                <div className="flex max-h-svh flex-1 flex-col overflow-hidden">
                    <header className="border-border/40 bg-background/95 sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                        <div className="flex items-center gap-2 px-4">
                            <SidebarTrigger className="-ml-1" />
                            <Separator
                                orientation="vertical"
                                className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                            />
                            <Breadcrumb>
                                <BreadcrumbList>
                                    <BreadcrumbItem className="hidden md:block">
                                        <BreadcrumbLink
                                            href={route('dashboard')}
                                        >
                                            Trang chủ
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator className="hidden md:block" />
                                    <BreadcrumbItem className="hidden md:block">
                                        <BreadcrumbLink
                                            href={route('lives.index')}
                                        >
                                            Phân tích phiên live
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator className="hidden md:block" />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>
                                            {session.name}
                                        </BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                    </header>

                    <div className="flex flex-1 flex-col gap-2 overflow-hidden px-4 pb-4">
                        {/* Offline Warning Banner */}
                        {isOffline && (
                            <div className="bg-destructive/10 border-destructive/20 text-destructive flex shrink-0 animate-pulse items-center gap-2 rounded-lg border px-4 py-2 text-sm">
                                <span className="relative flex size-2 shrink-0">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                                    <span className="relative inline-flex size-2 rounded-full bg-current" />
                                </span>
                                <span>
                                    ⚠️ Mất kết nối tới máy chủ. Đang thử kết nối
                                    lại...
                                </span>
                            </div>
                        )}

                        {/* Session Error Message Banner */}
                        {session.status === 'error' && (
                            <div className="bg-destructive/10 border-destructive/20 text-destructive flex shrink-0 items-center gap-2 rounded-lg border px-4 py-3 text-sm">
                                <AlertTriangleIcon className="text-destructive size-4 shrink-0" />
                                <div className="flex-1">
                                    <p className="text-sm font-semibold">
                                        Phiên phân tích bị lỗi
                                    </p>
                                    <p className="text-xs opacity-90">
                                        {session.error_message ||
                                            'Không thể kết nối TikTok service. Kiểm tra lại kết nối VPS.'}
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Session Header */}
                        <div className="flex items-center justify-between">
                            <div className="shrink-0">
                                <h1 className="text-lg font-bold tracking-tight">
                                    {session.name}
                                </h1>
                                <div className="mt-1 flex items-center gap-2">
                                    <Badge variant="default">TikTok</Badge>
                                    {session.status === 'live' ? (
                                        <Badge className="bg-destructive text-destructive-foreground gap-1.5 px-2.5 py-0.5 text-xs font-semibold shadow-xs">
                                            <span className="relative flex size-2">
                                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
                                                <span className="relative inline-flex size-2 rounded-full bg-white" />
                                            </span>
                                            Đang phát trực tiếp
                                        </Badge>
                                    ) : session.status === 'disconnected' ? (
                                        <Badge className="animate-pulse gap-1.5 border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-500 shadow-xs backdrop-blur-md">
                                            <span className="relative flex size-2">
                                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75" />
                                                <span className="relative inline-flex size-2 rounded-full bg-amber-500" />
                                            </span>
                                            Mất kết nối · Đang thử lại...
                                        </Badge>
                                    ) : session.status === 'connecting' ? (
                                        <Badge className="bg-primary text-primary-foreground gap-1.5 px-2.5 py-0.5 text-xs font-semibold shadow-xs">
                                            <span className="relative flex size-2">
                                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
                                                <span className="relative inline-flex size-2 rounded-full bg-white" />
                                            </span>
                                            Đang kết nối
                                        </Badge>
                                    ) : session.status === 'error' ? (
                                        <Badge className="border border-red-500/20 bg-red-500/10 px-2.5 py-0.5 text-xs font-semibold text-red-500 backdrop-blur-md">
                                            Lỗi
                                        </Badge>
                                    ) : (
                                        <Badge className="border border-white/10 bg-black/40 px-2.5 py-0.5 text-xs font-semibold text-white/90 backdrop-blur-md">
                                            Đã kết thúc
                                        </Badge>
                                    )}
                                    <span className="text-muted-foreground flex items-center gap-1 text-sm">
                                        <ClockIcon className="size-3.5" />
                                        {session.duration}
                                    </span>
                                </div>
                            </div>

                            {/* Center: Latest Order Alert */}
                            <div className="flex min-w-0 flex-1 justify-center px-4">
                                {alerts.length > 0 && (
                                    <InlineOrderAlert
                                        alerts={alerts}
                                        dismiss={dismiss}
                                    />
                                )}
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className={`gap-1.5 ${soundEnabled ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20' : ''}`}
                                    onClick={() =>
                                        setSoundEnabled(!soundEnabled)
                                    }
                                >
                                    {soundEnabled ? (
                                        <BellRingIcon className="size-4" />
                                    ) : (
                                        <BellOffIcon className="size-4" />
                                    )}
                                    {soundEnabled ? 'Thông báo' : 'Tắt tiếng'}
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={handleStop}
                                    disabled={
                                        session.status === 'ended' || isStopping
                                    }
                                >
                                    {isStopping ? (
                                        <LoaderIcon className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <CircleStopIcon className="mr-2 size-4" />
                                    )}
                                    Kết thúc phiên phân tích
                                </Button>
                            </div>
                        </div>

                        {/* 2-Column Layout: Video + KPI (left) | Tabs (right) — stacks on mobile */}
                        <div className="grid min-h-0 flex-1 gap-4 xl:grid-cols-[360px_1fr]">
                            {/* Left Column: Video + KPIs */}
                            <div className="flex flex-col gap-3 overflow-y-auto">
                                {/* Video Embed */}
                                <Card className="gap-0 overflow-hidden py-0">
                                    <CardContent className="p-0">
                                        <div className="bg-muted/50 relative flex aspect-video w-full items-center justify-center overflow-hidden">
                                            {/* Placeholder for video embed iframe */}
                                            <div className="from-primary/5 to-primary/10 absolute inset-0 bg-gradient-to-br" />
                                            <div className="z-10 space-y-2 text-center">
                                                <div className="bg-primary/10 mx-auto flex size-12 items-center justify-center rounded-full">
                                                    <EyeIcon className="text-primary size-6" />
                                                </div>
                                                <p className="text-sm font-medium">
                                                    {session.status === 'live'
                                                        ? 'Đang phát trực tiếp'
                                                        : session.status ===
                                                            'disconnected'
                                                          ? 'Mất kết nối tạm thời · Đang tự động kết nối lại...'
                                                          : session.status ===
                                                              'connecting'
                                                            ? 'Đang kết nối...'
                                                            : 'Phiên đã kết thúc'}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    @{session.tiktok_username}
                                                </p>
                                                {session.status === 'live' && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="gap-1"
                                                    >
                                                        <span className="relative flex size-1.5">
                                                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
                                                            <span className="relative inline-flex size-1.5 rounded-full bg-current" />
                                                        </span>
                                                        {stats.viewer_count.toLocaleString()}{' '}
                                                        đang xem
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        {/* Stats bar at bottom of video card */}
                                        <div className="grid grid-cols-3 divide-x border-t">
                                            <div className="p-2.5 text-center">
                                                <div className="text-lg font-bold">
                                                    {stats.total_views.toLocaleString()}
                                                </div>
                                                <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
                                                    <EyeIcon className="size-3" />
                                                    Tổng lượt xem
                                                </p>
                                            </div>
                                            <div className="p-2.5 text-center">
                                                <div className="text-lg font-bold">
                                                    {stats.total_comments.toLocaleString()}
                                                </div>
                                                <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
                                                    <MessageSquareIcon className="size-3" />
                                                    Bình luận
                                                </p>
                                            </div>
                                            <div className="p-2.5 text-center">
                                                <div className="text-lg font-bold">
                                                    {stats.leads_count}
                                                </div>
                                                <p className="text-muted-foreground flex items-center justify-center gap-1 text-xs">
                                                    <ShoppingCartIcon className="size-3" />
                                                    Chốt đơn
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Phân tích cảm xúc */}
                                <Card>
                                    <CardHeader className="px-3 pt-0 pb-2">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="flex items-center gap-2 text-xs">
                                                <SmileIcon className="size-3.5" />
                                                Phân tích cảm xúc
                                            </CardTitle>
                                            {sentimentTotal <
                                                stats.total_comments && (
                                                <Badge
                                                    variant="secondary"
                                                    className="h-4 animate-pulse gap-1 px-1 py-0 text-[9px] font-normal"
                                                >
                                                    <LoaderIcon className="size-2 animate-spin" />
                                                    Đang xử lý{' '}
                                                    {stats.total_comments -
                                                        sentimentTotal}
                                                    ...
                                                </Badge>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="px-3">
                                        {sentimentTotal === 0 ? (
                                            <div className="text-muted-foreground py-2 text-center text-xs">
                                                Chưa có dữ liệu cảm xúc
                                            </div>
                                        ) : (
                                            <>
                                                <div className="flex items-start gap-3">
                                                    <div className="text-center">
                                                        <div
                                                            className={`text-2xl font-bold ${sentimentNegativePct > 30 ? 'text-red-500' : sentimentPositivePct >= 20 ? 'text-green-500' : 'text-amber-500'}`}
                                                        >
                                                            {sentimentNegativePct >
                                                            30
                                                                ? '😟'
                                                                : sentimentPositivePct >=
                                                                    20
                                                                  ? '😊'
                                                                  : '😐'}
                                                        </div>
                                                        <div className="text-muted-foreground mt-0.5 text-[10px]">
                                                            {sentimentNegativePct >
                                                            30
                                                                ? 'Cần chú ý'
                                                                : sentimentNegativePct ===
                                                                        0 &&
                                                                    sentimentPositivePct >
                                                                        0
                                                                  ? 'Rất tốt!'
                                                                  : sentimentNegativePct ===
                                                                      0
                                                                    ? 'Ổn định'
                                                                    : 'Tích cực'}
                                                        </div>
                                                    </div>
                                                    <div className="flex-1 space-y-1 text-xs">
                                                        <div className="flex items-center justify-between">
                                                            <span className="flex items-center gap-1.5">
                                                                <span className="size-1.5 rounded-full bg-green-500" />
                                                                Tích cực
                                                            </span>
                                                            <span className="font-medium">
                                                                {
                                                                    sentimentPositivePct
                                                                }
                                                                %{' '}
                                                                <span className="text-muted-foreground">
                                                                    (
                                                                    {
                                                                        stats.sentiment_positive
                                                                    }
                                                                    )
                                                                </span>
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span className="flex items-center gap-1.5">
                                                                <span className="size-1.5 rounded-full bg-amber-500" />
                                                                Bình thường
                                                            </span>
                                                            <span className="font-medium">
                                                                {
                                                                    sentimentNeutralPct
                                                                }
                                                                %{' '}
                                                                <span className="text-muted-foreground">
                                                                    (
                                                                    {
                                                                        stats.sentiment_neutral
                                                                    }
                                                                    )
                                                                </span>
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span className="flex items-center gap-1.5">
                                                                <span className="size-1.5 rounded-full bg-red-500" />
                                                                Tiêu cực
                                                            </span>
                                                            <span className="font-medium">
                                                                {
                                                                    sentimentNegativePct
                                                                }
                                                                %{' '}
                                                                <span className="text-muted-foreground">
                                                                    (
                                                                    {
                                                                        stats.sentiment_negative
                                                                    }
                                                                    )
                                                                </span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="bg-muted/30 mt-2 flex h-2 w-full overflow-hidden rounded-full">
                                                    <div
                                                        className="bg-green-500 transition-all duration-500"
                                                        style={{
                                                            width: `${sentimentPositivePct}%`,
                                                        }}
                                                    />
                                                    <div
                                                        className="bg-amber-500 transition-all duration-500"
                                                        style={{
                                                            width: `${sentimentNeutralPct}%`,
                                                        }}
                                                    />
                                                    <div
                                                        className="bg-red-500 transition-all duration-500"
                                                        style={{
                                                            width: `${sentimentNegativePct}%`,
                                                        }}
                                                    />
                                                </div>
                                                {sentimentNegativePct === 0 &&
                                                    sentimentTotal > 0 && (
                                                        <div className="mt-1.5 text-[10px] text-green-600 dark:text-green-400">
                                                            ✨ Không có phản hồi
                                                            tiêu cực — phiên
                                                            live đang diễn ra
                                                            tốt!
                                                        </div>
                                                    )}
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Top Keywords Card */}
                                <Card className="flex-1 overflow-hidden">
                                    <CardHeader className="px-3 pt-0">
                                        <CardTitle className="text-xs">
                                            🔍 Từ khóa được nhắc nhiều
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="min-h-0 flex-1 overflow-hidden px-3">
                                        <div className="relative flex h-full flex-wrap gap-1.5 overflow-hidden">
                                            {topKeywords &&
                                            topKeywords.length > 0 ? (
                                                topKeywords.map((item) => (
                                                    <Badge
                                                        key={`k-${item.keyword}`}
                                                        variant="secondary"
                                                        className="flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium border border-primary/10 text-foreground"
                                                    >
                                                        <span>
                                                            {item.keyword}
                                                        </span>
                                                        <Badge
                                                            variant="outline"
                                                            className="h-4 min-w-4 p-0 px-1 text-[10px] font-bold tabular-nums flex items-center justify-center bg-background/50 border-primary/20 text-muted-foreground"
                                                        >
                                                            {item.count}
                                                        </Badge>
                                                    </Badge>
                                                ))
                                            ) : (
                                                <div className="text-muted-foreground text-xs">
                                                    Chưa có dữ liệu
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Right Column: Tabs */}
                            <Tabs
                                defaultValue="comments"
                                className="flex min-h-0 min-w-0 flex-col"
                            >
                                <TabsList className="flex-wrap">
                                    <TabsTrigger
                                        value="comments"
                                        className="gap-1.5"
                                    >
                                        <MessageSquareIcon className="size-3.5" />
                                        Bình luận
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="products"
                                        className="gap-1.5"
                                    >
                                        <PackageIcon className="size-3.5" />
                                        Sản phẩm
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="questions"
                                        className="gap-1.5"
                                    >
                                        <HelpCircleIcon className="size-3.5" />
                                        Câu hỏi
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="customers"
                                        className="gap-1.5"
                                    >
                                        <UsersIcon className="size-3.5" />
                                        KH tiềm năng
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="stats"
                                        className="gap-1.5"
                                    >
                                        <BarChart3Icon className="size-3.5" />
                                        Thống kê
                                    </TabsTrigger>
                                    <TabsTrigger value="ai" className="gap-1.5">
                                        <SparklesIcon className="size-3.5" />
                                        AI
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent
                                    value="comments"
                                    className="min-h-0 flex-1 overflow-y-auto"
                                >
                                    <CommentsPanel />
                                </TabsContent>
                                <TabsContent
                                    value="products"
                                    className="min-h-0 flex-1 overflow-y-auto"
                                >
                                    <ProductsPanel />
                                </TabsContent>
                                <TabsContent
                                    value="questions"
                                    className="min-h-0 flex-1 overflow-y-auto"
                                >
                                    <QuestionsPanel />
                                </TabsContent>
                                <TabsContent
                                    value="customers"
                                    className="min-h-0 flex-1 overflow-y-auto"
                                >
                                    <CustomersPanel />
                                </TabsContent>
                                <TabsContent
                                    value="stats"
                                    className="min-h-0 flex-1"
                                >
                                    <StatsPanel />
                                </TabsContent>
                                <TabsContent
                                    value="ai"
                                    className="min-h-0 flex-1 overflow-y-auto"
                                >
                                    <AIInsightsPanel />
                                </TabsContent>
                            </Tabs>
                        </div>
                    </div>
                </div>
            </LiveContext.Provider>
        </AuthenticatedLayout>
    );
}
