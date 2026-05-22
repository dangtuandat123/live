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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Switch } from '@/components/ui/switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/types';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    CheckCircle2Icon,
    CheckIcon,
    CrownIcon,
    LinkIcon,
    LoaderIcon,
    SparklesIcon,
    XCircleIcon,
} from 'lucide-react';

interface SettingsData {
    ai_language: string;
    auto_extract_phone: boolean;
    auto_extract_address: boolean;
    realtime_alerts: boolean;
    tiktok_username?: string | null;
}

interface Props extends PageProps {
    settings: SettingsData;
    activeStreamsCount: number;
    totalSessionsInCycle: number;
}

export default function SettingsIndex({ settings, activeStreamsCount, totalSessionsInCycle }: Props) {
    const { auth } = usePage<Props>().props;
    const [connectOpen, setConnectOpen] = useState(false);

    // AI Settings Form
    const aiForm = useForm({
        ai_language: settings.ai_language,
        auto_extract_phone: settings.auto_extract_phone,
        auto_extract_address: settings.auto_extract_address,
        realtime_alerts: settings.realtime_alerts,
    });

    // Profile Form
    const profileForm = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    // TikTok Connect Form
    const tiktokForm = useForm({
        tiktok_username: '',
    });

    function submitAiSettings(e: React.FormEvent) {
        e.preventDefault();
        aiForm.put(route('settings.update-ai'), { preserveScroll: true });
    }

    function submitProfile(e: React.FormEvent) {
        e.preventDefault();
        profileForm.put(route('settings.update-profile'), {
            preserveScroll: true,
        });
    }

    function submitConnectTikTok(e: React.FormEvent) {
        e.preventDefault();
        tiktokForm.post(route('settings.tiktok.connect'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setConnectOpen(false);
                tiktokForm.reset();
            },
        });
    }

    function handleDisconnectTikTok() {
        if (confirm('Bạn có chắc chắn muốn ngắt kết nối tài khoản TikTok?')) {
            router.post(route('settings.tiktok.disconnect'), {}, {
                preserveScroll: true,
                preserveState: true,
            });
        }
    }

    const tiktokUsername = settings.tiktok_username;
    const isConnected = !!tiktokUsername;
    const platforms = [
        { name: 'TikTok', connected: isConnected, account: tiktokUsername || 'Chưa kết nối' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Cài đặt" />
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
                                <BreadcrumbPage>Cài đặt</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Cài đặt
                    </h1>
                    <p className="text-muted-foreground">
                        Quản lý tài khoản, kết nối nền tảng và tùy chỉnh AI
                    </p>
                </div>

                {/* Subscription */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CrownIcon className="size-5 text-yellow-500" />
                            Gói đăng ký
                        </CardTitle>
                        <CardDescription>
                            Quản lý gói sử dụng của bạn
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-semibold">
                                        {auth.subscription?.package_name ?? 'Free'}
                                    </span>
                                    {auth.subscription?.active && (
                                        <Badge>Đang sử dụng</Badge>
                                    )}
                                </div>
                                <div className="text-muted-foreground mt-2 text-sm space-y-1">
                                    <div>• Số luồng Live đồng thời: {auth.subscription?.features?.limit_streams === -1 ? 'Không giới hạn' : auth.subscription?.features?.limit_streams ?? 1}</div>
                                    <div>• Thời lượng live tối đa: {auth.subscription?.features?.max_duration_hours ?? 1} giờ</div>
                                    <div>• Tín dụng AI: {(auth.subscription?.features?.ai_credits ?? 1000).toLocaleString('vi-VN')} credits</div>
                                    <div>• Phân tích âm thanh: {auth.subscription?.features?.audio_analysis ? 'Có' : 'Không'}</div>
                                    <div>• Xuất danh sách khách hàng tiềm năng (Leads): {auth.subscription?.features?.export_leads ? 'Có' : 'Không'}</div>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-2xl font-bold">
                                    {auth.subscription ? (auth.subscription.price === 0 ? 'Miễn phí' : `${(auth.subscription.price ?? 0).toLocaleString('vi-VN')}đ`) : '0đ'}
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    /{auth.subscription?.duration_days ?? 30} ngày
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Số livestream đang hoạt động / tối đa của gói
                            </span>
                            <span className="font-medium">
                                {activeStreamsCount} / {auth.subscription?.features?.limit_streams === -1 ? '∞' : auth.subscription?.features?.limit_streams ?? 1}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Tổng số phiên live đã thực hiện trong chu kỳ
                            </span>
                            <span className="font-medium">
                                {totalSessionsInCycle} phiên
                            </span>
                        </div>
                        <Button
                            variant="outline"
                            className="gap-2"
                            onClick={() => router.get(route('subscription.index'))}
                        >
                            <SparklesIcon className="size-4" />
                            Nâng cấp gói dịch vụ
                        </Button>
                    </CardContent>
                </Card>

                {/* Platform Connections */}
                <Card>
                    <CardHeader>
                        <CardTitle>Kết nối nền tảng</CardTitle>
                        <CardDescription>
                            Kết nối tài khoản bán hàng để AI thu thập dữ liệu
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {platforms.map((p) => (
                            <div
                                key={p.name}
                                className="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-3">
                                    {p.connected ? (
                                        <CheckCircle2Icon className="size-5 text-green-500" />
                                    ) : (
                                        <XCircleIcon className="text-muted-foreground size-5" />
                                    )}
                                    <div>
                                        <div className="font-medium">
                                            {p.name}
                                        </div>
                                        {p.connected ? (
                                            <div className="text-muted-foreground text-sm">
                                                {p.account}
                                            </div>
                                        ) : (
                                            <div className="text-muted-foreground text-sm">
                                                Chưa kết nối
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <Button
                                    variant={p.connected ? 'outline' : 'default'}
                                    size="sm"
                                    onClick={p.connected ? handleDisconnectTikTok : () => setConnectOpen(true)}
                                    type="button"
                                >
                                    {p.connected ? (
                                        'Ngắt kết nối'
                                    ) : (
                                        <>
                                            <LinkIcon className="mr-1.5 size-3.5" />
                                            Kết nối
                                        </>
                                    )}
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {/* AI Preferences */}
                <form onSubmit={submitAiSettings}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Tùy chỉnh AI</CardTitle>
                            <CardDescription>
                                Cấu hình cách AI phân tích bình luận
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Ngôn ngữ phân tích</Label>
                                <Select
                                    value={aiForm.data.ai_language}
                                    onValueChange={(value) =>
                                        aiForm.setData('ai_language', value)
                                    }
                                >
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="vi">
                                            Tiếng Việt
                                        </SelectItem>
                                        <SelectItem value="en">
                                            English
                                        </SelectItem>
                                        <SelectItem value="auto">
                                            Tự động nhận diện
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>Trích xuất SĐT tự động</Label>
                                    <p className="text-muted-foreground text-xs">
                                        AI tự động tìm và trích xuất số điện
                                        thoại từ bình luận
                                    </p>
                                </div>
                                <Switch
                                    checked={aiForm.data.auto_extract_phone}
                                    onCheckedChange={(checked) =>
                                        aiForm.setData(
                                            'auto_extract_phone',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>Trích xuất địa chỉ tự động</Label>
                                    <p className="text-muted-foreground text-xs">
                                        AI tự động tìm địa chỉ giao hàng từ bình
                                        luận
                                    </p>
                                </div>
                                <Switch
                                    checked={aiForm.data.auto_extract_address}
                                    onCheckedChange={(checked) =>
                                        aiForm.setData(
                                            'auto_extract_address',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>Cảnh báo realtime</Label>
                                    <p className="text-muted-foreground text-xs">
                                        Nhận cảnh báo khi có nhiều bình luận
                                        tiêu cực hoặc câu hỏi chưa trả lời
                                    </p>
                                </div>
                                <Switch
                                    checked={aiForm.data.realtime_alerts}
                                    onCheckedChange={(checked) =>
                                        aiForm.setData(
                                            'realtime_alerts',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={aiForm.processing}
                                className="gap-2"
                            >
                                {aiForm.processing ? (
                                    <LoaderIcon className="size-4 animate-spin" />
                                ) : aiForm.recentlySuccessful ? (
                                    <CheckIcon className="size-4" />
                                ) : null}
                                {aiForm.recentlySuccessful
                                    ? 'Đã lưu'
                                    : 'Lưu cài đặt AI'}
                            </Button>
                            {aiForm.errors.ai_language && (
                                <p className="text-destructive text-sm">
                                    {aiForm.errors.ai_language}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </form>

                {/* Profile */}
                <form onSubmit={submitProfile}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Thông tin tài khoản</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Tên</Label>
                                <Input
                                    id="name"
                                    value={profileForm.data.name}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                />
                                {profileForm.errors.name && (
                                    <p className="text-destructive text-sm">
                                        {profileForm.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={profileForm.data.email}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {profileForm.errors.email && (
                                    <p className="text-destructive text-sm">
                                        {profileForm.errors.email}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={profileForm.processing}
                                className="gap-2"
                            >
                                {profileForm.processing ? (
                                    <LoaderIcon className="size-4 animate-spin" />
                                ) : profileForm.recentlySuccessful ? (
                                    <CheckIcon className="size-4" />
                                ) : null}
                                {profileForm.recentlySuccessful
                                    ? 'Đã lưu'
                                    : 'Lưu thay đổi'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <Dialog open={connectOpen} onOpenChange={setConnectOpen}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={submitConnectTikTok}>
                        <DialogHeader>
                            <DialogTitle>Kết nối tài khoản TikTok</DialogTitle>
                            <DialogDescription>
                                Nhập username kênh TikTok của bạn (ví dụ: @username hoặc username) để AI bắt đầu đồng bộ dữ liệu.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="tiktok_username">Tên người dùng TikTok</Label>
                                <Input
                                    id="tiktok_username"
                                    value={tiktokForm.data.tiktok_username}
                                    onChange={(e) =>
                                        tiktokForm.setData('tiktok_username', e.target.value)
                                    }
                                    placeholder="Ví dụ: @kenhcuaban"
                                    required
                                />
                                {tiktokForm.errors.tiktok_username && (
                                    <span className="text-destructive text-xs">
                                        {tiktokForm.errors.tiktok_username}
                                    </span>
                                )}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setConnectOpen(false)}
                            >
                                Hủy
                            </Button>
                            <Button type="submit" disabled={tiktokForm.processing}>
                                {tiktokForm.processing ? 'Đang kết nối...' : 'Kết nối'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
