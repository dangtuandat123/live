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
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
                features?: {
                    limit_streams?: number;
                };
            } | null;
        };
    };

    const limitStreams = auth?.subscription?.features?.limit_streams ?? 1;
    const isGated = limitStreams !== -1 && active_streams_count >= limitStreams;

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

                {isGated && (
                    <div className="border-destructive/20 bg-destructive/10 text-destructive rounded-lg border p-4">
                        <div className="flex items-center gap-3">
                            <div className="bg-destructive/20 shrink-0 rounded-full p-2">
                                <XIcon className="text-destructive h-5 w-5" />
                            </div>
                            <div>
                                <h4 className="text-sm font-semibold">
                                    Đã đạt giới hạn số lượng livestream hoạt
                                    động
                                </h4>
                                <p className="text-destructive/80 mt-1 text-xs">
                                    Gói đăng ký hiện tại của bạn giới hạn tối đa{' '}
                                    {limitStreams} phiên livestream hoạt động
                                    đồng thời. Vui lòng kết thúc một phiên
                                    livestream hiện tại trước khi tạo mới hoặc
                                    nâng cấp gói dịch vụ.
                                </p>
                            </div>
                        </div>
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
                                            onClick={() =>
                                                toggleProduct(product.id)
                                            }
                                        >
                                            <TableCell>
                                                <Checkbox
                                                    checked={form.data.product_ids.includes(
                                                        product.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleProduct(
                                                            product.id,
                                                        )
                                                    }
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
