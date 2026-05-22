import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    ClockIcon,
    EyeIcon,
    LoaderIcon,
    MessageSquareIcon,
    PackageIcon,
    PlusIcon,
    RadioIcon,
    SearchIcon,
    Trash2Icon,
    UsersIcon,
    VideoIcon,
} from 'lucide-react';
import * as React from 'react';

// --- Types ---

interface Session {
    id: number;
    name: string;
    status: string;
    comments: number;
    views: number;
    viewer_count: number;
    leads: number;
    sentiment: number;
    duration: string;
    products: number;
    date: string;
    thumbnail: string | null;
}

interface PaginatedSessions {
    data: Session[];
    current_page: number;
    last_page: number;
    total: number;
}

interface KPI {
    total_sessions: number;
    live_count: number;
    live_views: number;
    total_views: number;
    total_comments: number;
}

interface Props {
    sessions: PaginatedSessions;
    kpi: KPI;
    filters: { search: string | null; status: string | null };
}

export default function LivesIndex({ sessions, kpi, filters }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = React.useState(
        filters.status ?? 'all',
    );
    const [deletingSession, setDeletingSession] =
        React.useState<Session | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    // Debounced search
    const searchTimerRef =
        React.useRef<ReturnType<typeof setTimeout>>(undefined);
    const applyFilters = (newSearch?: string, newStatus?: string) => {
        const params: Record<string, string> = {};
        const s = newSearch ?? search;
        const st = newStatus ?? statusFilter;
        if (s) params.search = s;
        if (st && st !== 'all') params.status = st;
        router.get(route('lives.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearch = (value: string) => {
        setSearch(value);
        clearTimeout(searchTimerRef.current);
        searchTimerRef.current = setTimeout(() => applyFilters(value), 400);
    };

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        applyFilters(undefined, value);
    };

    const paginated = sessions.data;

    return (
        <AuthenticatedLayout>
            <Head title="Phân tích phiên live" />
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
                                <BreadcrumbLink href={route('dashboard')}>
                                    Trang chủ
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator className="hidden md:block" />
                            <BreadcrumbItem>
                                <BreadcrumbPage>
                                    Phân tích phiên live
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Phân tích phiên live
                        </h1>
                        <p className="text-muted-foreground">
                            Quản lý và theo dõi tất cả phiên livestream TikTok
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('lives.create')}>
                            <PlusIcon className="mr-2 size-4" />
                            Tạo phân tích phiên live
                        </Link>
                    </Button>
                </div>

                {/* KPI Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Tổng phiên
                            </CardTitle>
                            <VideoIcon className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {kpi.total_sessions}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {kpi.live_count > 0
                                    ? `${kpi.live_count} đang live`
                                    : 'Không có phiên live'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Đang Live
                            </CardTitle>
                            <RadioIcon className="size-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-500">
                                {kpi.live_count}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {kpi.live_count > 0
                                    ? `${kpi.live_views.toLocaleString()} người xem`
                                    : '—'}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Tổng lượt xem
                            </CardTitle>
                            <EyeIcon className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {kpi.total_views.toLocaleString()}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Từ tất cả phiên
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Tổng bình luận
                            </CardTitle>
                            <MessageSquareIcon className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {kpi.total_comments.toLocaleString()}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Từ tất cả phiên
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative max-w-sm flex-1">
                        <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Tìm phiên live..."
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={statusFilter}
                        onValueChange={handleStatusChange}
                    >
                        <SelectTrigger className="w-[160px]">
                            <SelectValue placeholder="Trạng thái" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem value="live">Đang Live</SelectItem>
                            <SelectItem value="ended">Đã kết thúc</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Session Cards Grid */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    {paginated.map((session) => (
                        <div
                            key={session.id}
                            onClick={() =>
                                router.visit(route('lives.show', session.id))
                            }
                            className="group block cursor-pointer"
                        >
                            <div className="bg-card hover:border-primary/30 relative overflow-hidden rounded-xl border transition-all hover:shadow-lg">
                                {/* Portrait Thumbnail */}
                                <div className="bg-muted relative aspect-[9/16] overflow-hidden">
                                    {session.thumbnail ? (
                                        <img
                                            src={session.thumbnail}
                                            alt={session.name}
                                            className="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        />
                                    ) : (
                                        <div className="from-primary/10 to-primary/5 absolute inset-0 flex items-center justify-center bg-gradient-to-br">
                                            <VideoIcon className="text-muted-foreground/50 size-8" />
                                        </div>
                                    )}
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />

                                    {/* Status badge */}
                                    <div className="absolute top-2 left-2">
                                        {session.status === 'live' ? (
                                            <Badge className="bg-destructive text-destructive-foreground gap-1.5 px-2 py-0.5 text-[10px] font-semibold shadow-xs">
                                                <span className="relative flex size-1.5">
                                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
                                                    <span className="relative inline-flex size-1.5 rounded-full bg-white" />
                                                </span>
                                                Đang phát trực tiếp
                                            </Badge>
                                        ) : session.status === 'connecting' ? (
                                            <Badge className="bg-primary text-primary-foreground gap-1.5 px-2 py-0.5 text-[10px] font-semibold shadow-xs">
                                                <span className="relative flex size-1.5">
                                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75" />
                                                    <span className="relative inline-flex size-1.5 rounded-full bg-white" />
                                                </span>
                                                Đang kết nối
                                            </Badge>
                                        ) : session.status ===
                                          'disconnected' ? (
                                            <Badge className="animate-pulse gap-1.5 border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-500 shadow-xs backdrop-blur-md">
                                                <span className="relative flex size-1.5">
                                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75" />
                                                    <span className="relative inline-flex size-1.5 rounded-full bg-amber-500" />
                                                </span>
                                                MẤT KẾT NỐI
                                            </Badge>
                                        ) : session.status === 'error' ? (
                                            <Badge className="border border-red-500/20 bg-red-500/10 px-2 py-0.5 text-[10px] font-semibold text-red-500 backdrop-blur-md">
                                                LỖI
                                            </Badge>
                                        ) : (
                                            <Badge className="border border-white/10 bg-black/40 px-2 py-0.5 text-[10px] font-semibold text-white/90 backdrop-blur-md">
                                                ĐÃ KẾT THÚC
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Duration badge */}
                                    <div className="absolute top-2 right-2">
                                        <span className="inline-flex items-center gap-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">
                                            <ClockIcon className="size-2.5" />
                                            {session.duration}
                                        </span>
                                    </div>

                                    {/* Bottom info overlay */}
                                    <div className="absolute right-0 bottom-0 left-0 p-3">
                                        <h3 className="mb-1.5 line-clamp-2 text-sm leading-tight font-semibold text-white">
                                            {session.name}
                                        </h3>
                                        <div className="flex items-center gap-2.5 text-[11px] text-white/80">
                                            {session.status === 'live' ? (
                                                <span className="flex items-center gap-1 font-semibold text-red-400">
                                                    <EyeIcon className="size-3 animate-pulse text-red-500" />
                                                    {(
                                                        session.viewer_count ??
                                                        0
                                                    ).toLocaleString()}
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-1">
                                                    <EyeIcon className="size-3" />
                                                    {session.views.toLocaleString()}
                                                </span>
                                            )}
                                            <span className="flex items-center gap-1">
                                                <MessageSquareIcon className="size-3" />
                                                {session.comments.toLocaleString()}
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <PackageIcon className="size-3" />
                                                {session.products}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Bottom bar */}
                                <div className="flex items-center justify-between px-3 py-1.5">
                                    <span className="text-muted-foreground text-xs">
                                        {session.date}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <div className="text-muted-foreground flex items-center gap-1.5">
                                            <UsersIcon className="size-3" />
                                            <span className="text-foreground text-xs font-medium">
                                                {session.leads}
                                            </span>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-muted-foreground hover:text-destructive hover:bg-destructive/10 size-7 rounded-md"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setDeletingSession(session);
                                            }}
                                        >
                                            <Trash2Icon className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Empty state */}
                {paginated.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <VideoIcon className="text-muted-foreground/30 mb-4 size-12" />
                        <h3 className="text-lg font-semibold">
                            Chưa có phiên live nào
                        </h3>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Tạo phiên phân tích đầu tiên để bắt đầu
                        </p>
                        <Button asChild className="mt-4">
                            <Link href={route('lives.create')}>
                                <PlusIcon className="mr-2 size-4" />
                                Tạo phân tích
                            </Link>
                        </Button>
                    </div>
                )}

                {/* Pagination */}
                {sessions.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2 pt-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={sessions.current_page <= 1}
                            onClick={() => {
                                const params: Record<string, string | number> =
                                    {
                                        page: sessions.current_page - 1,
                                    };
                                if (search) params.search = search;
                                if (statusFilter && statusFilter !== 'all')
                                    params.status = statusFilter;
                                router.get(route('lives.index'), params, {
                                    preserveState: true,
                                });
                            }}
                        >
                            <ChevronLeftIcon className="mr-1 size-4" />
                            Trước
                        </Button>
                        <span className="text-muted-foreground px-3 text-sm">
                            Trang {sessions.current_page} / {sessions.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                sessions.current_page >= sessions.last_page
                            }
                            onClick={() => {
                                const params: Record<string, string | number> =
                                    {
                                        page: sessions.current_page + 1,
                                    };
                                if (search) params.search = search;
                                if (statusFilter && statusFilter !== 'all')
                                    params.status = statusFilter;
                                router.get(route('lives.index'), params, {
                                    preserveState: true,
                                });
                            }}
                        >
                            Sau
                            <ChevronRightIcon className="ml-1 size-4" />
                        </Button>
                    </div>
                )}
            </div>

            {/* Dialog Xác nhận Xóa */}
            <Dialog
                open={deletingSession !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingSession(null);
                        setIsDeleting(false);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Xóa phiên phân tích?</DialogTitle>
                        <DialogDescription>
                            Bạn có chắc chắn muốn xóa phiên phân tích{' '}
                            <strong className="text-foreground">
                                "{deletingSession?.name}"
                            </strong>
                            ? Hành động này sẽ xóa vĩnh viễn toàn bộ bình luận,
                            phân tích AI và thống kê liên quan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            variant="outline"
                            disabled={isDeleting}
                            onClick={() => {
                                setDeletingSession(null);
                                setIsDeleting(false);
                            }}
                        >
                            Hủy
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={isDeleting}
                            onClick={() => {
                                if (deletingSession) {
                                    setIsDeleting(true);
                                    router.delete(
                                        route(
                                            'lives.destroy',
                                            deletingSession.id,
                                        ),
                                        {
                                            onSuccess: () => {
                                                setDeletingSession(null);
                                            },
                                            onFinish: () => {
                                                setIsDeleting(false);
                                            },
                                        },
                                    );
                                }
                            }}
                        >
                            {isDeleting && (
                                <LoaderIcon className="mr-2 size-4 animate-spin" />
                            )}
                            Xác nhận xóa
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
