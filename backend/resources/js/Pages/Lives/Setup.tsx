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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LoaderIcon, VideoIcon, XIcon } from 'lucide-react';
import * as React from 'react';

interface Product {
    id: number;
    name: string;
    sku: string;
    price: number;
}

interface Props {
    products: Product[];
    active_streams_count: number;
}

export default function LivesSetup({
    products,
    active_streams_count = 0,
}: Props) {
    const { auth } = usePage().props as unknown as {
        auth: {
            subscription: {
                package_name: string;
                used_ai_credits: number;
                features?: {
                    limit_streams?: number;
                    max_duration_hours?: number;
                    ai_credits?: number;
                };
            } | null;
        };
    };

    const limitStreams = auth?.subscription?.features?.limit_streams ?? 1;
    const maxDurationHours =
        auth?.subscription?.features?.max_duration_hours ?? -1;
    const usedCredits = auth?.subscription?.used_ai_credits ?? 0;
    const limitCredits = auth?.subscription?.features?.ai_credits ?? -1;

    const isCreditsExhausted =
        limitCredits !== -1 && usedCredits >= limitCredits;
    const isStreamGated =
        limitStreams !== -1 && active_streams_count >= limitStreams;
    const isGated = isStreamGated || isCreditsExhausted;

    const form = useForm({
        name: '',
        tiktok_username: '',
        product_ids: [] as number[],
    });

    function toggleProduct(id: number) {
        const current = form.data.product_ids;
        form.setData(
            'product_ids',
            current.includes(id)
                ? current.filter((p) => p !== id)
                : [...current, id],
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('lives.store'));
    }

    return (
        <AuthenticatedLayout>
            <Head title="Tạo phân tích phiên live" />
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
                            <BreadcrumbItem className="hidden md:block">
                                <BreadcrumbLink href={route('lives.index')}>
                                    Phân tích phiên live
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator className="hidden md:block" />
                            <BreadcrumbItem>
                                <BreadcrumbPage>
                                    Tạo phân tích phiên live mới
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <form
                onSubmit={handleSubmit}
                className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-6"
            >
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Tạo phân tích phiên live mới
                    </h1>
                    <p className="text-muted-foreground">
                        Thiết lập thông tin để AI bắt đầu phân tích phiên live
                    </p>
                </div>

                {/* Subscription Limits Summary Card */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-semibold">
                            Gói dịch vụ & Giới hạn của bạn
                        </CardTitle>
                        <CardDescription>
                            Tổng quan về tài nguyên đã sử dụng trong chu kỳ hiện
                            tại
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <div className="bg-muted/20 rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs font-medium">
                                Phiên live đồng thời
                            </div>
                            <div className="mt-1 text-lg font-bold">
                                {active_streams_count} /{' '}
                                {limitStreams === -1 ? 'Vô hạn' : limitStreams}
                            </div>
                            <div className="text-muted-foreground mt-0.5 text-[10px]">
                                {limitStreams === -1
                                    ? 'Không giới hạn'
                                    : `Còn lại: ${Math.max(0, limitStreams - active_streams_count)} phiên`}
                            </div>
                        </div>
                        <div className="bg-muted/20 rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs font-medium">
                                Tín dụng AI
                            </div>
                            <div className="mt-1 text-lg font-bold">
                                {usedCredits.toLocaleString()} /{' '}
                                {limitCredits === -1
                                    ? 'Vô hạn'
                                    : limitCredits.toLocaleString()}
                            </div>
                            <div className="text-muted-foreground mt-0.5 text-[10px]">
                                {limitCredits === -1
                                    ? 'Không giới hạn'
                                    : `Còn lại: ${Math.max(0, limitCredits - usedCredits).toLocaleString()} credits`}
                            </div>
                        </div>
                        <div className="bg-muted/20 rounded-lg border p-3">
                            <div className="text-muted-foreground text-xs font-medium">
                                Thời lượng tối đa / phiên
                            </div>
                            <div className="mt-1 text-lg font-bold">
                                {maxDurationHours === -1
                                    ? 'Vô hạn'
                                    : `${maxDurationHours} giờ`}
                            </div>
                            <div className="text-muted-foreground mt-0.5 text-[10px]">
                                Giới hạn thời gian của mỗi phiên live
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {isGated && (
                    <div className="border-destructive/20 bg-destructive/10 text-destructive flex flex-col justify-between gap-4 rounded-lg border p-4 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <div className="bg-destructive/20 mt-0.5 shrink-0 rounded-full p-2">
                                <XIcon className="text-destructive h-5 w-5" />
                            </div>
                            <div>
                                <h4 className="text-sm font-semibold">
                                    Không thể tạo phiên live mới
                                </h4>
                                <p className="text-destructive/85 mt-1 text-xs font-medium">
                                    {isCreditsExhausted
                                        ? 'Bạn đã hết tín dụng AI. Vui lòng mua thêm tín dụng hoặc nâng cấp gói dịch vụ để bắt đầu phiên phân tích mới.'
                                        : 'Bạn đã hết lượt tạo phiên hôm nay. Vui lòng nâng cấp gói dịch vụ để tiếp tục tạo thêm phiên livestream.'}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => router.visit('/subscription')}
                            className="w-full shrink-0 shadow-sm sm:w-auto"
                        >
                            Nâng cấp gói dịch vụ
                        </Button>
                    </div>
                )}

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
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                disabled={isGated}
                            />
                            {form.errors.name && (
                                <p className="text-destructive text-sm">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label>Nền tảng</Label>
                                <Input
                                    value="TikTok"
                                    disabled
                                    className="bg-muted"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="live-url">
                                    Username TikTok
                                </Label>
                                <Input
                                    id="live-url"
                                    placeholder="VD: username (không cần @)"
                                    value={form.data.tiktok_username}
                                    onChange={(e) =>
                                        form.setData(
                                            'tiktok_username',
                                            e.target.value,
                                        )
                                    }
                                    disabled={isGated}
                                />
                                {form.errors.tiktok_username && (
                                    <p className="text-destructive text-sm">
                                        {form.errors.tiktok_username}
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Product Selection */}
                <Card>
                    <CardHeader>
                        <CardTitle>Chọn sản phẩm từ catalog</CardTitle>
                        <CardDescription>
                            AI sẽ dùng danh sách sản phẩm này để nhận diện khi
                            phân tích bình luận. Đã chọn{' '}
                            <Badge variant="secondary">
                                {form.data.product_ids.length}
                            </Badge>{' '}
                            sản phẩm.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {products.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12"></TableHead>
                                        <TableHead>Sản phẩm</TableHead>
                                        <TableHead>SKU</TableHead>
                                        <TableHead className="text-right">
                                            Giá
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.map((product) => (
                                        <TableRow
                                            key={product.id}
                                            className="cursor-pointer"
                                            onClick={() => {
                                                if (!isGated) {
                                                    toggleProduct(product.id);
                                                }
                                            }}
                                        >
                                            <TableCell>
                                                <Checkbox
                                                    checked={form.data.product_ids.includes(
                                                        product.id,
                                                    )}
                                                    onCheckedChange={() => {
                                                        if (!isGated) {
                                                            toggleProduct(
                                                                product.id,
                                                            );
                                                        }
                                                    }}
                                                    disabled={isGated}
                                                />
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {product.name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {product.sku}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {product.price.toLocaleString(
                                                    'vi-VN',
                                                )}
                                                đ
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                Chưa có sản phẩm nào.{' '}
                                <Link
                                    href={route('products.index')}
                                    className="text-primary underline"
                                >
                                    Thêm sản phẩm
                                </Link>{' '}
                                trước.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Submit */}
                <div className="flex items-center gap-3">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={form.processing || isGated}
                    >
                        {form.processing ? (
                            <LoaderIcon className="mr-2 size-4 animate-spin" />
                        ) : (
                            <VideoIcon className="mr-2 size-4" />
                        )}
                        {isGated ? 'Đã đạt giới hạn gói' : 'Bắt đầu phân tích'}
                    </Button>
                    <Button variant="outline" size="lg" asChild>
                        <Link href={route('lives.index')}>Hủy</Link>
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
