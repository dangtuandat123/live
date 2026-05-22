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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import {
    LoaderIcon,
    PackageIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface PackageFeatures {
    limit_streams?: number;
    max_duration_hours?: number;
    ai_credits?: number;
    audio_analysis?: boolean;
    export_leads?: boolean;
}

interface SubscriptionPackage {
    id: number;
    name: string;
    price: number;
    duration_days: number;
    features: PackageFeatures | string[] | null;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    packages: SubscriptionPackage[];
    errors?: Record<string, string>;
}

export default function AdminPackages({ packages = [] }: Props) {
    const [isCreateOpen, setIsCreateOpen] = React.useState(false);
    const [isEditOpen, setIsEditOpen] = React.useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = React.useState(false);
    const [selectedPackage, setSelectedPackage] =
        React.useState<SubscriptionPackage | null>(null);

    // Form tạo mới
    const createForm = useForm({
        name: '',
        price: 0,
        duration_days: 30,
        features: {
            limit_streams: 1,
            max_duration_hours: 1,
            ai_credits: 1000,
            audio_analysis: false,
            export_leads: false,
        } as PackageFeatures,
    });

    // Form chỉnh sửa
    const editForm = useForm({
        name: '',
        price: 0,
        duration_days: 30,
        features: {
            limit_streams: 1,
            max_duration_hours: 1,
            ai_credits: 1000,
            audio_analysis: false,
            export_leads: false,
        } as PackageFeatures,
    });

    // Form xóa
    const deleteForm = useForm();

    // Mở Dialog thêm mới
    function handleOpenCreate() {
        createForm.reset();
        createForm.setData('features', {
            limit_streams: 1,
            max_duration_hours: 1,
            ai_credits: 1000,
            audio_analysis: false,
            export_leads: false,
        });
        setIsCreateOpen(true);
    }

    // Mở Dialog sửa
    function handleOpenEdit(pkg: SubscriptionPackage) {
        setSelectedPackage(pkg);

        let parsedFeatures: PackageFeatures = {
            limit_streams: 1,
            max_duration_hours: 1,
            ai_credits: 1000,
            audio_analysis: false,
            export_leads: false,
        };

        if (pkg.features && !Array.isArray(pkg.features)) {
            parsedFeatures = {
                limit_streams:
                    (pkg.features as PackageFeatures).limit_streams ?? 1,
                max_duration_hours:
                    (pkg.features as PackageFeatures).max_duration_hours ?? 1,
                ai_credits:
                    (pkg.features as PackageFeatures).ai_credits ?? 1000,
                audio_analysis: !!(pkg.features as PackageFeatures)
                    .audio_analysis,
                export_leads: !!(pkg.features as PackageFeatures).export_leads,
            };
        }

        editForm.setData({
            name: pkg.name,
            price: pkg.price,
            duration_days: pkg.duration_days,
            features: parsedFeatures,
        });
        setIsEditOpen(true);
    }

    // Mở Dialog xóa
    function handleOpenDelete(pkg: SubscriptionPackage) {
        setSelectedPackage(pkg);
        setIsDeleteOpen(true);
    }

    // Submit thêm mới
    function handleCreateSubmit(e: React.FormEvent) {
        e.preventDefault();
        createForm.post(route('admin.packages.store'), {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                toast.success('Đã tạo gói dịch vụ mới thành công!');
            },
            onError: () => {
                toast.error('Có lỗi xảy ra khi tạo gói dịch vụ.');
            },
        });
    }

    // Submit chỉnh sửa
    function handleEditSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedPackage) return;

        editForm.put(
            route('admin.packages.update', { package: selectedPackage.id }),
            {
                onSuccess: () => {
                    setIsEditOpen(false);
                    toast.success('Đã cập nhật gói dịch vụ thành công!');
                },
                onError: () => {
                    toast.error('Có lỗi xảy ra khi cập nhật gói dịch vụ.');
                },
            },
        );
    }

    // Submit xóa
    function handleDeleteSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedPackage) return;

        deleteForm.delete(
            route('admin.packages.destroy', { package: selectedPackage.id }),
            {
                onSuccess: () => {
                    setIsDeleteOpen(false);
                    toast.success('Đã xóa gói dịch vụ thành công!');
                },
                onError: (err) => {
                    const errMsg =
                        (err as Record<string, string>).error ||
                        'Không thể xóa gói dịch vụ này.';
                    toast.error(errMsg);
                },
            },
        );
    }

    // Định dạng số tiền
    function formatMoney(amount: number) {
        if (amount === 0) return 'Miễn phí';
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
        }).format(amount);
    }

    return (
        <AdminLayout>
            <Head title="Admin - Quản lý gói dịch vụ" />
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
                                <BreadcrumbLink href={route('admin.dashboard')}>
                                    Admin
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator className="hidden md:block" />
                            <BreadcrumbItem>
                                <BreadcrumbPage>
                                    Quản lý gói dịch vụ
                                </BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Quản lý gói dịch vụ
                        </h1>
                        <p className="text-muted-foreground">
                            Thiết lập các gói dịch vụ đăng ký cho người dùng
                        </p>
                    </div>
                    <Button
                        onClick={handleOpenCreate}
                        className="bg-primary text-primary-foreground hover:bg-primary/95 gap-1 shadow-sm"
                    >
                        <PlusIcon className="size-4" />
                        Thêm gói dịch vụ
                    </Button>
                </div>

                <Card>
                    <CardHeader className="px-6 py-4">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <PackageIcon className="text-primary size-5" />
                            Danh sách gói dịch vụ ({packages.length})
                        </CardTitle>
                        <CardDescription>
                            Cung cấp các gói miễn phí và trả phí linh hoạt
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {packages.length === 0 ? (
                            <div className="flex flex-col items-center justify-center px-4 py-12 text-center">
                                <PackageIcon className="text-muted-foreground/40 mb-4 size-12" />
                                <h3 className="text-lg font-semibold">
                                    Chưa có gói dịch vụ nào
                                </h3>
                                <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                    Hãy tạo gói dịch vụ đầu tiên để bắt đầu kinh
                                    doanh.
                                </p>
                                <Button
                                    onClick={handleOpenCreate}
                                    className="mt-4 gap-1"
                                >
                                    <PlusIcon className="size-4" /> Tạo gói dịch
                                    vụ
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[200px]">
                                                Tên gói
                                            </TableHead>
                                            <TableHead>Giá dịch vụ</TableHead>
                                            <TableHead>
                                                Thời hạn (ngày)
                                            </TableHead>
                                            <TableHead className="max-w-[350px]">
                                                Tính năng nổi bật
                                            </TableHead>
                                            <TableHead className="w-[150px] pr-6 text-right">
                                                Hành động
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {packages.map((pkg) => (
                                            <TableRow key={pkg.id}>
                                                <TableCell className="text-foreground font-semibold">
                                                    {pkg.name}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {pkg.price === 0 ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-emerald-500/30 bg-emerald-500/5 text-emerald-500"
                                                        >
                                                            Miễn phí
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-foreground font-semibold">
                                                            {formatMoney(
                                                                pkg.price,
                                                            )}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {pkg.duration_days} ngày
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex max-w-[400px] flex-wrap gap-1.5">
                                                        {pkg.features ? (
                                                            Array.isArray(
                                                                pkg.features,
                                                            ) ? (
                                                                pkg.features.map(
                                                                    (
                                                                        feat,
                                                                        idx,
                                                                    ) => (
                                                                        <Badge
                                                                            key={
                                                                                idx
                                                                            }
                                                                            variant="secondary"
                                                                            className="py-0.5 text-[11px] font-normal"
                                                                        >
                                                                            {
                                                                                feat
                                                                            }
                                                                        </Badge>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <>
                                                                    {(
                                                                        pkg.features as PackageFeatures
                                                                    )
                                                                        .limit_streams !==
                                                                        undefined && (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="py-0.5 text-[11px] font-normal"
                                                                        >
                                                                            Stream:{' '}
                                                                            {(
                                                                                pkg.features as PackageFeatures
                                                                            )
                                                                                .limit_streams ===
                                                                            -1
                                                                                ? 'Vô hạn'
                                                                                : `${(pkg.features as PackageFeatures).limit_streams} luồng`}
                                                                        </Badge>
                                                                    )}
                                                                    {(
                                                                        pkg.features as PackageFeatures
                                                                    )
                                                                        .max_duration_hours !==
                                                                        undefined && (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="py-0.5 text-[11px] font-normal"
                                                                        >
                                                                            Thời
                                                                            lượng:{' '}
                                                                            {(
                                                                                pkg.features as PackageFeatures
                                                                            )
                                                                                .max_duration_hours ===
                                                                            -1
                                                                                ? 'Vô hạn'
                                                                                : `${(pkg.features as PackageFeatures).max_duration_hours}h`}
                                                                        </Badge>
                                                                    )}
                                                                    {(
                                                                        pkg.features as PackageFeatures
                                                                    )
                                                                        .ai_credits !==
                                                                        undefined && (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="py-0.5 text-[11px] font-normal"
                                                                        >
                                                                            Credits:{' '}
                                                                            {(
                                                                                pkg.features as PackageFeatures
                                                                            )
                                                                                .ai_credits ===
                                                                            -1
                                                                                ? 'Vô hạn'
                                                                                : (
                                                                                      pkg.features as PackageFeatures
                                                                                  ).ai_credits?.toLocaleString()}
                                                                        </Badge>
                                                                    )}
                                                                    {(
                                                                        pkg.features as PackageFeatures
                                                                    )
                                                                        .audio_analysis !==
                                                                        undefined && (
                                                                        <Badge
                                                                            variant={
                                                                                (
                                                                                    pkg.features as PackageFeatures
                                                                                )
                                                                                    .audio_analysis
                                                                                    ? 'default'
                                                                                    : 'outline'
                                                                            }
                                                                            className={`py-0.5 text-[11px] font-normal ${(pkg.features as PackageFeatures).audio_analysis ? 'border-transparent bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20' : 'text-muted-foreground'}`}
                                                                        >
                                                                            Âm
                                                                            thanh:{' '}
                                                                            {(
                                                                                pkg.features as PackageFeatures
                                                                            )
                                                                                .audio_analysis
                                                                                ? 'Có'
                                                                                : 'Không'}
                                                                        </Badge>
                                                                    )}
                                                                    {(
                                                                        pkg.features as PackageFeatures
                                                                    )
                                                                        .export_leads !==
                                                                        undefined && (
                                                                        <Badge
                                                                            variant={
                                                                                (
                                                                                    pkg.features as PackageFeatures
                                                                                )
                                                                                    .export_leads
                                                                                    ? 'default'
                                                                                    : 'outline'
                                                                            }
                                                                            className={`py-0.5 text-[11px] font-normal ${(pkg.features as PackageFeatures).export_leads ? 'border-transparent bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20' : 'text-muted-foreground'}`}
                                                                        >
                                                                            Xuất
                                                                            leads:{' '}
                                                                            {(
                                                                                pkg.features as PackageFeatures
                                                                            )
                                                                                .export_leads
                                                                                ? 'Có'
                                                                                : 'Không'}
                                                                        </Badge>
                                                                    )}
                                                                </>
                                                            )
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs italic">
                                                                Không có tính
                                                                năng ghi nhận
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            onClick={() =>
                                                                handleOpenEdit(
                                                                    pkg,
                                                                )
                                                            }
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-foreground size-8"
                                                        >
                                                            <PencilIcon className="size-3.5" />
                                                        </Button>
                                                        <Button
                                                            onClick={() =>
                                                                handleOpenDelete(
                                                                    pkg,
                                                                )
                                                            }
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive hover:bg-destructive/10 size-8"
                                                        >
                                                            <TrashIcon className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* MODAL THÊM MỚI GÓI DỊCH VỤ */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-lg">
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Thêm gói dịch vụ mới</DialogTitle>
                            <DialogDescription>
                                Tạo gói dịch vụ mới để cung cấp cho người dùng
                                đăng ký.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-2">
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Tên gói</Label>
                                <Input
                                    id="create-name"
                                    value={createForm.data.name}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ví dụ: Pro, Business, VIP..."
                                    required
                                />
                                {createForm.errors.name && (
                                    <p className="text-destructive text-xs">
                                        {createForm.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="create-price">
                                        Giá tiền (VNĐ)
                                    </Label>
                                    <Input
                                        id="create-price"
                                        type="number"
                                        min="0"
                                        value={createForm.data.price}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'price',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                        required
                                    />
                                    {createForm.errors.price && (
                                        <p className="text-destructive text-xs">
                                            {createForm.errors.price}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-duration">
                                        Thời hạn (Ngày)
                                    </Label>
                                    <Input
                                        id="create-duration"
                                        type="number"
                                        min="1"
                                        value={createForm.data.duration_days}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'duration_days',
                                                parseInt(e.target.value) || 30,
                                            )
                                        }
                                        required
                                    />
                                    {createForm.errors.duration_days && (
                                        <p className="text-destructive text-xs">
                                            {createForm.errors.duration_days}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-4 border-t pt-4">
                                <h4 className="text-foreground text-sm font-medium">
                                    Cấu hình giới hạn & tính năng
                                </h4>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-limit-streams">
                                            Số luồng stream đồng thời
                                        </Label>
                                        <Input
                                            id="create-limit-streams"
                                            type="number"
                                            min="-1"
                                            value={
                                                createForm.data.features
                                                    ?.limit_streams ?? 1
                                            }
                                            onChange={(e) =>
                                                createForm.setData('features', {
                                                    ...createForm.data.features,
                                                    limit_streams:
                                                        e.target.value === ''
                                                            ? 1
                                                            : parseInt(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                })
                                            }
                                            required
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="create-max-duration">
                                            Thời lượng live tối đa (giờ)
                                        </Label>
                                        <Input
                                            id="create-max-duration"
                                            type="number"
                                            min="-1"
                                            value={
                                                createForm.data.features
                                                    ?.max_duration_hours ?? 1
                                            }
                                            onChange={(e) =>
                                                createForm.setData('features', {
                                                    ...createForm.data.features,
                                                    max_duration_hours:
                                                        e.target.value === ''
                                                            ? 1
                                                            : parseInt(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-ai-credits">
                                        Số AI credits
                                    </Label>
                                    <Input
                                        id="create-ai-credits"
                                        type="number"
                                        min="-1"
                                        value={
                                            createForm.data.features
                                                ?.ai_credits ?? 0
                                        }
                                        onChange={(e) =>
                                            createForm.setData('features', {
                                                ...createForm.data.features,
                                                ai_credits:
                                                    e.target.value === ''
                                                        ? 0
                                                        : parseInt(
                                                              e.target.value,
                                                          ),
                                            })
                                        }
                                        required
                                    />
                                </div>

                                <div className="space-y-3 pt-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="create-audio-analysis"
                                            checked={
                                                !!createForm.data.features
                                                    ?.audio_analysis
                                            }
                                            onCheckedChange={(checked) =>
                                                createForm.setData('features', {
                                                    ...createForm.data.features,
                                                    audio_analysis: !!checked,
                                                })
                                            }
                                        />
                                        <Label
                                            htmlFor="create-audio-analysis"
                                            className="cursor-pointer font-normal select-none"
                                        >
                                            Phân tích âm thanh AI
                                            (audio_analysis)
                                        </Label>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="create-export-leads"
                                            checked={
                                                !!createForm.data.features
                                                    ?.export_leads
                                            }
                                            onCheckedChange={(checked) =>
                                                createForm.setData('features', {
                                                    ...createForm.data.features,
                                                    export_leads: !!checked,
                                                })
                                            }
                                        />
                                        <Label
                                            htmlFor="create-export-leads"
                                            className="cursor-pointer font-normal select-none"
                                        >
                                            Xuất file danh sách SĐT
                                            (export_leads)
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Hủy
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                {createForm.processing && (
                                    <LoaderIcon className="mr-1 size-4 animate-spin" />
                                )}
                                Thêm mới
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL CHỈNH SỬA GÓI DỊCH VỤ */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="max-w-lg">
                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Chỉnh sửa gói dịch vụ</DialogTitle>
                            <DialogDescription>
                                Cập nhật thông tin gói dịch vụ đã chọn.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-2">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Tên gói</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    placeholder="Ví dụ: Pro, Business..."
                                    required
                                />
                                {editForm.errors.name && (
                                    <p className="text-destructive text-xs">
                                        {editForm.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-price">
                                        Giá tiền (VNĐ)
                                    </Label>
                                    <Input
                                        id="edit-price"
                                        type="number"
                                        min="0"
                                        value={editForm.data.price}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'price',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                        required
                                    />
                                    {editForm.errors.price && (
                                        <p className="text-destructive text-xs">
                                            {editForm.errors.price}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-duration">
                                        Thời hạn (Ngày)
                                    </Label>
                                    <Input
                                        id="edit-duration"
                                        type="number"
                                        min="1"
                                        value={editForm.data.duration_days}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'duration_days',
                                                parseInt(e.target.value) || 30,
                                            )
                                        }
                                        required
                                    />
                                    {editForm.errors.duration_days && (
                                        <p className="text-destructive text-xs">
                                            {editForm.errors.duration_days}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-4 border-t pt-4">
                                <h4 className="text-foreground text-sm font-medium">
                                    Cấu hình giới hạn & tính năng
                                </h4>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-limit-streams">
                                            Số luồng stream đồng thời
                                        </Label>
                                        <Input
                                            id="edit-limit-streams"
                                            type="number"
                                            min="-1"
                                            value={
                                                editForm.data.features
                                                    ?.limit_streams ?? 1
                                            }
                                            onChange={(e) =>
                                                editForm.setData('features', {
                                                    ...editForm.data.features,
                                                    limit_streams:
                                                        e.target.value === ''
                                                            ? 1
                                                            : parseInt(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                })
                                            }
                                            required
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-max-duration">
                                            Thời lượng live tối đa (giờ)
                                        </Label>
                                        <Input
                                            id="edit-max-duration"
                                            type="number"
                                            min="-1"
                                            value={
                                                editForm.data.features
                                                    ?.max_duration_hours ?? 1
                                            }
                                            onChange={(e) =>
                                                editForm.setData('features', {
                                                    ...editForm.data.features,
                                                    max_duration_hours:
                                                        e.target.value === ''
                                                            ? 1
                                                            : parseInt(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-ai-credits">
                                        Số AI credits
                                    </Label>
                                    <Input
                                        id="edit-ai-credits"
                                        type="number"
                                        min="-1"
                                        value={
                                            editForm.data.features
                                                ?.ai_credits ?? 0
                                        }
                                        onChange={(e) =>
                                            editForm.setData('features', {
                                                ...editForm.data.features,
                                                ai_credits:
                                                    e.target.value === ''
                                                        ? 0
                                                        : parseInt(
                                                              e.target.value,
                                                          ),
                                            })
                                        }
                                        required
                                    />
                                </div>

                                <div className="space-y-3 pt-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="edit-audio-analysis"
                                            checked={
                                                !!editForm.data.features
                                                    ?.audio_analysis
                                            }
                                            onCheckedChange={(checked) =>
                                                editForm.setData('features', {
                                                    ...editForm.data.features,
                                                    audio_analysis: !!checked,
                                                })
                                            }
                                        />
                                        <Label
                                            htmlFor="edit-audio-analysis"
                                            className="cursor-pointer font-normal select-none"
                                        >
                                            Phân tích âm thanh AI
                                            (audio_analysis)
                                        </Label>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="edit-export-leads"
                                            checked={
                                                !!editForm.data.features
                                                    ?.export_leads
                                            }
                                            onCheckedChange={(checked) =>
                                                editForm.setData('features', {
                                                    ...editForm.data.features,
                                                    export_leads: !!checked,
                                                })
                                            }
                                        />
                                        <Label
                                            htmlFor="edit-export-leads"
                                            className="cursor-pointer font-normal select-none"
                                        >
                                            Xuất file danh sách SĐT
                                            (export_leads)
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsEditOpen(false)}
                            >
                                Hủy
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                {editForm.processing && (
                                    <LoaderIcon className="mr-1 size-4 animate-spin" />
                                )}
                                Cập nhật
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* MODAL XÁC NHẬN XÓA GÓI DỊCH VỤ */}
            <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <DialogContent className="max-w-md">
                    <form onSubmit={handleDeleteSubmit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle className="text-destructive flex items-center gap-1.5">
                                <TrashIcon className="size-5" /> Xác nhận xóa
                                gói dịch vụ
                            </DialogTitle>
                            <DialogDescription>
                                Bạn có chắc chắn muốn xóa gói dịch vụ{' '}
                                <span className="text-foreground font-semibold">
                                    "{selectedPackage?.name}"
                                </span>
                                ?
                            </DialogDescription>
                        </DialogHeader>

                        <div className="bg-destructive/10 text-destructive border-destructive/20 space-y-1 rounded border p-3 text-xs">
                            <span className="font-bold">Cảnh báo:</span>
                            <p>
                                Hành động này không thể hoàn tác. Bạn chỉ có thể
                                xóa gói dịch vụ nếu chưa có bất kỳ người dùng
                                nào đăng ký hoặc chưa có lịch sử giao dịch liên
                                quan đến gói này.
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsDeleteOpen(false)}
                            >
                                Hủy
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={deleteForm.processing}
                            >
                                {deleteForm.processing && (
                                    <LoaderIcon className="mr-1 size-4 animate-spin" />
                                )}
                                Xác nhận xóa
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
