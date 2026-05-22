import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
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
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertCircleIcon,
    ArrowRightIcon,
    CheckCircle2Icon,
    CheckIcon,
    CopyIcon,
    CreditCardIcon,
    InfoIcon,
    Loader2Icon,
    SparklesIcon,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface PackageFeatures {
    limit_streams: number;
    max_duration_hours: number;
    ai_credits: number;
    audio_analysis: boolean;
    export_leads: boolean;
}

interface SubscriptionPackage {
    id: number;
    name: string;
    price: number;
    duration_days: number;
    features: PackageFeatures | null;
    features_list?: string[];
}

interface ActiveSubscription {
    package_id: number;
    package_name: string;
    expires_at: string;
    used_ai_credits: number;
    features: PackageFeatures;
}

interface Transaction {
    id: number;
    transaction_id: string;
    package_name: string;
    amount: number;
    status: string;
    created_at: string;
}

interface Props {
    packages: SubscriptionPackage[];
    activeSubscription: ActiveSubscription | null;
    transactions: Transaction[];
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
}

export default function SubscriptionIndex({
    packages = [],
    activeSubscription,
    transactions = [],
    auth,
}: Props) {
    const [selectedPkg, setSelectedPkg] =
        React.useState<SubscriptionPackage | null>(null);
    const [isCheckoutOpen, setIsCheckoutOpen] = React.useState(false);
    const [loadingCheckout, setLoadingCheckout] = React.useState(false);
    const [checkoutData, setCheckoutData] = React.useState<{
        transaction_id: string;
        vietqr_url: string | null;
        beneficiary_bank?: string;
        beneficiary_account?: string;
        beneficiary_name?: string;
    } | null>(null);

    const [copied, setCopied] = React.useState(false);
    const [isCheckingPayment, setIsCheckingPayment] = React.useState(false);
    const [timeLeft, setTimeLeft] = React.useState<number>(600);

    React.useEffect(() => {
        if (isCheckoutOpen) {
            setTimeLeft(600);
        }
    }, [isCheckoutOpen]);

    React.useEffect(() => {
        let timerId: ReturnType<typeof setInterval> | undefined;
        if (isCheckoutOpen && timeLeft > 0) {
            timerId = setInterval(() => {
                setTimeLeft((prev) => prev - 1);
            }, 1000);
        }
        return () => {
            if (timerId) clearInterval(timerId);
        };
    }, [isCheckoutOpen, timeLeft]);

    React.useEffect(() => {
        let intervalId: ReturnType<typeof setInterval> | undefined;
        const isTimeActive = timeLeft > 0;

        if (
            isCheckoutOpen &&
            selectedPkg &&
            checkoutData?.vietqr_url &&
            isTimeActive
        ) {
            intervalId = setInterval(async () => {
                try {
                    const response = await axios.get(
                        '/api/subscription/status',
                    );
                    const { active, package_id } = response.data as {
                        active: boolean;
                        package_id: number | null;
                    };

                    if (active && package_id === selectedPkg.id) {
                        setIsCheckoutOpen(false);
                        toast.success(
                            `Đã kích hoạt gói ${selectedPkg.name} thành công!`,
                        );
                        router.reload({ only: ['activeSubscription'] });
                    }
                } catch (error) {
                    console.error('Error polling subscription status:', error);
                }
            }, 5000);
        }

        return () => {
            if (intervalId) {
                clearInterval(intervalId);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isCheckoutOpen, selectedPkg, checkoutData, timeLeft]);

    // Định dạng số tiền
    function formatMoney(amount: number) {
        if (amount === 0) return 'Miễn phí';
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
        }).format(amount);
    }

    // Xử lý khi nhấn nút Đăng ký gói
    async function handleSelectPackage(pkg: SubscriptionPackage) {
        setSelectedPkg(pkg);
        setLoadingCheckout(true);
        setCheckoutData(null);

        try {
            // Gọi API tạo transaction & checkout
            const response = await axios.post('/api/subscription/checkout', {
                package_id: pkg.id,
            });

            const data = response.data;
            setCheckoutData(data);

            if (pkg.price === 0 || !data.vietqr_url) {
                // Gói Free kích hoạt ngay lập tức
                toast.success(`Đã kích hoạt gói ${pkg.name} thành công!`);
                // Tải lại trang để cập nhật thông tin activeSubscription
                router.reload({ only: ['activeSubscription'] });
            } else {
                // Mở modal thanh toán với QR
                setIsCheckoutOpen(true);
            }
        } catch (err) {
            console.error(err);
            const error = err as { response?: { data?: { message?: string } } };
            const msg =
                error.response?.data?.message ||
                'Không thể khởi tạo thanh toán. Vui lòng thử lại sau.';
            toast.error(msg);
        } finally {
            setLoadingCheckout(false);
        }
    }

    // Nội dung chuyển khoản
    const transferContent = React.useMemo(() => {
        if (!checkoutData || !selectedPkg) return '';
        // Trích xuất addInfo từ vietqr_url để hiển thị chính xác cho user copy
        try {
            const url = new URL(checkoutData.vietqr_url || '');
            const addInfo = url.searchParams.get('addInfo');
            return addInfo
                ? decodeURIComponent(addInfo)
                : `TTGR ${auth.user.id} NAP`;
        } catch {
            return `TTGR ${auth.user.id} NAP`;
        }
    }, [checkoutData, selectedPkg, auth.user.id]);

    // Copy nội dung chuyển khoản
    function handleCopyContent() {
        if (!transferContent) return;
        navigator.clipboard.writeText(transferContent);
        setCopied(true);
        toast.success('Đã sao chép nội dung chuyển khoản!');
        setTimeout(() => setCopied(false), 2000);
    }

    // Tải lại trang sau khi thanh toán xong
    async function handleConfirmPaid() {
        if (!selectedPkg) return;
        setIsCheckingPayment(true);
        try {
            const response = await axios.get('/api/subscription/status');
            const { active, package_id } = response.data;

            if (active && package_id === selectedPkg.id) {
                setIsCheckoutOpen(false);
                toast.success(
                    `Đã kích hoạt gói ${selectedPkg.name} thành công!`,
                );
                router.reload({ only: ['activeSubscription'] });
            } else {
                toast.warning(
                    'Hệ thống chưa nhận được thanh toán. Vui lòng đợi trong giây lát hoặc kiểm tra lại thông tin chuyển khoản.',
                );
            }
        } catch (err) {
            console.error(err);
            toast.error('Không thể kết nối đến hệ thống kiểm tra thanh toán.');
        } finally {
            setIsCheckingPayment(false);
        }
    }

    return (
        <AuthenticatedLayout>
            <Head title="Gói dịch vụ & Đăng ký" />
            <header className="border-border/40 bg-background/95 sticky top-0 z-40 flex h-16 shrink-0 items-center gap-2 border-b backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                <div className="flex items-center gap-2 px-4">
                    <SidebarTrigger className="-ml-1" />
                    <Separator
                        orientation="vertical"
                        className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                    />
                    <Breadcrumb>
                        <BreadcrumbList>
                            <BreadcrumbItem>
                                <BreadcrumbPage>Gói dịch vụ</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>
            </header>

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6">
                {/* Tiêu đề */}
                <div>
                    <h1 className="from-primary to-primary/70 bg-gradient-to-r bg-clip-text text-3xl font-extrabold tracking-tight text-transparent">
                        Gói dịch vụ & Đăng ký
                    </h1>
                    <p className="text-muted-foreground mt-1">
                        Nâng cấp tài khoản để sử dụng đầy đủ các tính năng phân
                        tích Livestream cao cấp bằng AI
                    </p>
                </div>

                {/* Trạng thái gói hiện tại */}
                <Card className="border-primary/20 bg-primary/5 relative overflow-hidden border shadow-md backdrop-blur-sm">
                    <div className="absolute top-0 right-0 p-4 opacity-10">
                        <SparklesIcon className="text-primary size-24" />
                    </div>
                    <CardContent className="flex flex-col justify-between gap-6 p-6 md:flex-row md:items-center">
                        <div className="flex-1 space-y-1">
                            <span className="text-primary text-xs font-semibold tracking-wider uppercase">
                                Gói đăng ký hiện tại
                            </span>
                            <div className="flex items-center gap-2">
                                <h3 className="text-foreground text-2xl font-bold">
                                    {activeSubscription
                                        ? activeSubscription.package_name
                                        : 'Free (Mặc định)'}
                                </h3>
                                <Badge
                                    variant={
                                        activeSubscription
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className={
                                        activeSubscription
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground'
                                    }
                                >
                                    {activeSubscription
                                        ? 'Hoạt động'
                                        : 'Miễn phí'}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {activeSubscription ? (
                                    <>
                                        Hết hạn vào:{' '}
                                        <span className="text-foreground font-semibold">
                                            {activeSubscription.expires_at}
                                        </span>
                                    </>
                                ) : (
                                    'Tài khoản đang sử dụng các tính năng cơ bản của gói Free.'
                                )}
                            </p>
                        </div>

                        {/* AI Credits Usage progress */}
                        <div className="bg-background/50 border-border/40 w-full space-y-2 rounded-xl border p-4 md:w-80">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground font-medium">
                                    AI Credits:
                                </span>
                                <span className="text-foreground font-bold">
                                    {(
                                        activeSubscription?.used_ai_credits ?? 0
                                    ).toLocaleString()}{' '}
                                    /{' '}
                                    {activeSubscription?.features
                                        ?.ai_credits === -1
                                        ? 'Vô hạn'
                                        : (
                                              activeSubscription?.features
                                                  ?.ai_credits ?? 1000
                                          ).toLocaleString()}
                                </span>
                            </div>
                            {activeSubscription?.features?.ai_credits === -1 ? (
                                <div className="bg-primary/20 h-2 w-full overflow-hidden rounded-full">
                                    <div className="bg-primary h-full w-full" />
                                </div>
                            ) : (
                                <Progress
                                    value={Math.min(
                                        100,
                                        Math.max(
                                            0,
                                            ((activeSubscription?.used_ai_credits ??
                                                0) /
                                                (activeSubscription?.features
                                                    ?.ai_credits ?? 1000)) *
                                                100,
                                        ),
                                    )}
                                    className="h-2"
                                />
                            )}
                        </div>

                        {activeSubscription && (
                            <div className="text-muted-foreground bg-background/50 border-border/40 flex w-full max-w-xs items-center gap-2 rounded-lg border px-3 py-2 text-xs md:w-auto">
                                <InfoIcon className="text-primary size-4 shrink-0" />
                                <span>
                                    Gói dịch vụ sẽ tự động hết hạn, bạn có thể
                                    gia hạn bất cứ lúc nào bằng cách mua thêm
                                    gói.
                                </span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Bảng giá (Pricing Cards) */}
                <div className="grid grid-cols-1 gap-6 pt-2 md:grid-cols-3">
                    {packages.map((pkg) => {
                        const isCurrent = activeSubscription
                            ? activeSubscription.package_id === pkg.id
                            : pkg.price === 0;

                        const isFree = pkg.price === 0;

                        return (
                            <Card
                                key={pkg.id}
                                className={`flex flex-col border transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl ${
                                    isCurrent
                                        ? 'border-primary ring-primary/20 bg-background shadow-lg ring-1'
                                        : 'border-border/60 hover:border-primary/40 bg-background/60 shadow-sm'
                                }`}
                            >
                                <CardHeader className="p-6 pb-4">
                                    {isCurrent && (
                                        <Badge className="bg-primary text-primary-foreground hover:bg-primary/95 mb-2 w-fit">
                                            Gói hiện tại
                                        </Badge>
                                    )}
                                    <CardTitle className="text-xl font-bold tracking-tight">
                                        {pkg.name}
                                    </CardTitle>
                                    <CardDescription>
                                        Hiệu lực trong {pkg.duration_days} ngày
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col gap-6 p-6 pt-0">
                                    {/* Giá tiền */}
                                    <div className="text-foreground flex items-baseline">
                                        <span className="text-3xl font-extrabold tracking-tight">
                                            {isFree
                                                ? '0đ'
                                                : formatMoney(
                                                      pkg.price,
                                                  ).replace('₫', '')}
                                        </span>
                                        <span className="text-muted-foreground ml-1 text-sm font-semibold">
                                            {isFree ? '' : 'đ'} /{' '}
                                            {pkg.duration_days} ngày
                                        </span>
                                    </div>

                                    {/* Tính năng */}
                                    <div className="flex-1 space-y-2.5">
                                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Tính năng đi kèm:
                                        </span>
                                        <ul className="text-muted-foreground space-y-2 text-sm">
                                            {pkg.features_list && pkg.features_list.length > 0 ? (
                                                pkg.features_list.map((feature, idx) => (
                                                    <li key={idx} className="flex items-start gap-2">
                                                        <CheckIcon className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                                        <span>{feature}</span>
                                                    </li>
                                                ))
                                            ) : (
                                                <li className="flex items-start gap-2 italic">
                                                    <span>
                                                        Đầy đủ tính năng phân
                                                        tích live cơ bản
                                                    </span>
                                                </li>
                                            )}
                                        </ul>
                                    </div>
                                </CardContent>
                                <CardFooter className="p-6">
                                    <Button
                                        onClick={() => handleSelectPackage(pkg)}
                                        disabled={
                                            loadingCheckout ||
                                            (isCurrent && isFree)
                                        }
                                        className="w-full gap-1 font-medium shadow-sm"
                                        variant={
                                            isCurrent ? 'outline' : 'default'
                                        }
                                    >
                                        {loadingCheckout &&
                                        selectedPkg?.id === pkg.id ? (
                                            <Loader2Icon className="size-4 animate-spin" />
                                        ) : isCurrent ? (
                                            isFree ? (
                                                'Đang sử dụng'
                                            ) : (
                                                'Mua thêm thời hạn'
                                            )
                                        ) : (
                                            'Đăng ký ngay'
                                        )}
                                        {!isCurrent && (
                                            <ArrowRightIcon className="ml-1 size-4" />
                                        )}
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>

                {/* Bảng so sánh tính năng */}
                <div className="space-y-4 pt-4">
                    <h2 className="text-foreground text-xl font-bold">
                        So sánh tính năng
                    </h2>
                    <Card className="border-border/60 bg-background/60 overflow-hidden border shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-1/3">
                                        Tính năng
                                    </TableHead>
                                    {packages.map((pkg) => (
                                        <TableHead
                                            key={pkg.id}
                                            className="text-center font-bold"
                                        >
                                            {pkg.name}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow>
                                    <TableCell className="text-foreground font-medium">
                                        Số livestream đồng thời
                                    </TableCell>
                                    {packages.map((pkg) => (
                                        <TableCell
                                            key={pkg.id}
                                            className="text-center"
                                        >
                                            {pkg.features?.limit_streams === -1
                                                ? 'Vô hạn'
                                                : `${pkg.features?.limit_streams ?? 0} luồng`}
                                        </TableCell>
                                    ))}
                                </TableRow>
                                <TableRow>
                                    <TableCell className="text-foreground font-medium">
                                        Thời lượng tối đa / phiên
                                    </TableCell>
                                    {packages.map((pkg) => (
                                        <TableCell
                                            key={pkg.id}
                                            className="text-center"
                                        >
                                            {pkg.features
                                                ?.max_duration_hours === -1
                                                ? 'Vô hạn'
                                                : `${pkg.features?.max_duration_hours ?? 0} giờ`}
                                        </TableCell>
                                    ))}
                                </TableRow>
                                <TableRow>
                                    <TableCell className="text-foreground font-medium">
                                        AI Credits
                                    </TableCell>
                                    {packages.map((pkg) => (
                                        <TableCell
                                            key={pkg.id}
                                            className="text-center"
                                        >
                                            {pkg.features?.ai_credits === -1
                                                ? 'Vô hạn'
                                                : (pkg.features?.ai_credits?.toLocaleString() ??
                                                  0)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                                <TableRow>
                                    <TableCell className="text-foreground font-medium">
                                        Phân tích âm thanh nâng cao
                                    </TableCell>
                                    {packages.map((pkg) => (
                                        <TableCell
                                            key={pkg.id}
                                            className="text-center"
                                        >
                                            {pkg.features?.audio_analysis ? (
                                                <CheckIcon className="mx-auto size-5 text-emerald-500" />
                                            ) : (
                                                <span className="text-lg text-red-500">
                                                    ×
                                                </span>
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                                <TableRow>
                                    <TableCell className="text-foreground font-medium">
                                        Xuất danh sách lead (CSV)
                                    </TableCell>
                                    {packages.map((pkg) => (
                                        <TableCell
                                            key={pkg.id}
                                            className="text-center"
                                        >
                                            {pkg.features?.export_leads ? (
                                                <CheckIcon className="mx-auto size-5 text-emerald-500" />
                                            ) : (
                                                <span className="text-lg text-red-500">
                                                    ×
                                                </span>
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableBody>
                        </Table>
                    </Card>
                </div>

                {/* Lịch sử giao dịch */}
                <div className="space-y-4 pt-4">
                    <h2 className="text-foreground text-xl font-bold">
                        Lịch sử giao dịch
                    </h2>
                    <Card className="border-border/60 bg-background/60 overflow-hidden border shadow-sm">
                        {transactions.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Mã giao dịch</TableHead>
                                        <TableHead>Tên gói</TableHead>
                                        <TableHead>Số tiền</TableHead>
                                        <TableHead>Trạng thái</TableHead>
                                        <TableHead>Ngày tạo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.map((tx) => (
                                        <TableRow key={tx.id}>
                                            <TableCell className="text-foreground font-mono font-medium">
                                                {tx.transaction_id}
                                            </TableCell>
                                            <TableCell>
                                                {tx.package_name}
                                            </TableCell>
                                            <TableCell className="font-semibold tabular-nums">
                                                {formatMoney(tx.amount)}
                                            </TableCell>
                                            <TableCell>
                                                {tx.status === 'success' && (
                                                    <Badge className="border border-emerald-500/20 bg-emerald-500/10 font-medium text-emerald-600 hover:bg-emerald-500/20 hover:text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400 dark:hover:text-emerald-300">
                                                        Thành công
                                                    </Badge>
                                                )}
                                                {tx.status === 'pending' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border border-amber-500/20 bg-amber-500/10 font-medium text-amber-600 hover:bg-amber-500/20 hover:text-amber-700 dark:border-amber-500/30 dark:text-amber-400 dark:hover:text-amber-300"
                                                    >
                                                        Chờ xử lý
                                                    </Badge>
                                                )}
                                                {tx.status === 'failed' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border border-red-500/20 bg-red-500/10 font-medium text-red-600 hover:bg-red-500/20 hover:text-red-700 dark:border-red-500/30 dark:text-red-400 dark:hover:text-red-300"
                                                    >
                                                        Thất bại
                                                    </Badge>
                                                )}
                                                {tx.status !== 'success' &&
                                                    tx.status !== 'pending' &&
                                                    tx.status !== 'failed' && (
                                                        <Badge variant="secondary">
                                                            {tx.status}
                                                        </Badge>
                                                    )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {tx.created_at}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-muted-foreground py-6 text-center text-sm">
                                Không có lịch sử giao dịch nào.
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {/* CHECKOUT MODAL CHO GÓI TRẢ PHÍ */}
            <Dialog open={isCheckoutOpen} onOpenChange={setIsCheckoutOpen}>
                <DialogContent className="flex max-h-[85vh] max-w-md flex-col gap-0 overflow-hidden p-0 sm:max-h-[90vh]">
                    <DialogHeader className="border-border/40 shrink-0 border-b p-4 pr-10 pb-3">
                        <DialogTitle className="flex items-center gap-2 text-xl font-bold">
                            <CreditCardIcon className="text-primary size-5" />{' '}
                            Thanh toán gói dịch vụ
                        </DialogTitle>
                        <DialogDescription>
                            Vui lòng quét mã VietQR dưới đây để tiến hành chuyển
                            khoản mua gói dịch vụ{' '}
                            <span className="text-foreground font-semibold">
                                "{selectedPkg?.name}"
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 space-y-3 overflow-y-auto p-4">
                        {checkoutData?.vietqr_url && (
                            <div className="flex flex-col items-center gap-3">
                                <div className="text-center space-y-1">
                                    <h4 className="font-bold text-base text-foreground">Chuyển khoản QR</h4>
                                    <p className="text-xs text-muted-foreground">Vui lòng quét mã QR dưới đây để thực hiện thanh toán</p>
                                </div>
                                {/* VietQR Code Frame */}
                                <div className="border-border/40 group relative flex aspect-square max-h-[155px] max-w-[155px] items-center justify-center overflow-hidden rounded-2xl border bg-white p-2 shadow-md">
                                    <img
                                        src={checkoutData.vietqr_url}
                                        alt="VietQR Code"
                                        className={`h-auto max-h-[155px] max-w-[155px] rounded border object-contain ${timeLeft === 0 ? 'opacity-30 grayscale filter' : ''}`}
                                    />
                                </div>

                                {timeLeft > 0 ? (
                                    <div className="flex animate-pulse items-center gap-2 rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 text-sm font-medium text-amber-600 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-400">
                                        <Loader2Icon className="size-4 animate-spin text-amber-500 dark:text-amber-400" />
                                        <span>
                                            Đang chờ chuyển khoản... (
                                            {Math.floor(timeLeft / 60)
                                                .toString()
                                                .padStart(2, '0')}
                                            :
                                            {(timeLeft % 60)
                                                .toString()
                                                .padStart(2, '0')}
                                            )
                                        </span>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2 rounded-full border border-red-500/20 bg-red-500/10 px-3 py-1.5 text-sm font-medium text-red-600 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-400">
                                        <AlertCircleIcon className="size-4 shrink-0 text-red-500 dark:text-red-400" />
                                        <span>
                                            Mã thanh toán đã hết hạn (10 phút).
                                            Vui lòng đóng và thực hiện lại giao
                                            dịch.
                                        </span>
                                    </div>
                                )}

                                {/* Thông tin chuyển khoản */}
                                {(!checkoutData?.beneficiary_bank || !checkoutData?.beneficiary_account || !checkoutData?.beneficiary_name) ? (
                                    <div className="flex gap-2 rounded-lg border border-red-500/20 bg-red-500/5 p-3.5 text-sm text-red-600">
                                        <AlertCircleIcon className="mt-0.5 size-4 shrink-0 text-red-500" />
                                        <p>
                                            <span className="font-semibold">Lưu ý:</span> Không tìm thấy thông tin tài khoản ngân hàng thụ hưởng. Vui lòng liên hệ Admin để cấu hình thanh toán.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="bg-muted/50 border-border/40 w-full space-y-2 rounded-xl border p-3.5 text-sm">
                                        <div className="border-border/40 flex items-center justify-between border-b py-1">
                                            <span className="text-muted-foreground">
                                                Ngân hàng:
                                            </span>
                                            <span className="text-foreground font-semibold">
                                                {checkoutData.beneficiary_bank}
                                            </span>
                                        </div>
                                        <div className="border-border/40 flex items-center justify-between border-b py-1">
                                            <span className="text-muted-foreground">
                                                Số tài khoản:
                                            </span>
                                            <span className="text-foreground font-semibold">
                                                {checkoutData.beneficiary_account}
                                            </span>
                                        </div>
                                        <div className="border-border/40 flex items-center justify-between border-b py-1">
                                            <span className="text-muted-foreground">
                                                Chủ tài khoản:
                                            </span>
                                            <span className="text-foreground font-semibold">
                                                {checkoutData.beneficiary_name}
                                            </span>
                                        </div>
                                        <div className="border-border/40 flex items-center justify-between border-b py-1">
                                            <span className="text-muted-foreground">
                                                Số tiền:
                                            </span>
                                            <span className="text-primary font-bold tabular-nums">
                                                {formatMoney(
                                                    selectedPkg?.price || 0,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-1">
                                            <span className="text-muted-foreground">
                                                Nội dung chuyển khoản:
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <code className="bg-background border-border text-primary rounded border px-2 py-0.5 font-mono text-xs font-bold">
                                                    {transferContent}
                                                </code>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="hover:bg-background/80 size-7"
                                                    onClick={handleCopyContent}
                                                    disabled={timeLeft === 0}
                                                >
                                                    {copied ? (
                                                        <CheckCircle2Icon className="size-3.5 text-emerald-500" />
                                                    ) : (
                                                        <CopyIcon className="text-muted-foreground size-3.5" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Hướng dẫn an toàn */}
                                <div className="text-muted-foreground flex gap-2 rounded-lg border border-amber-500/20 bg-amber-500/5 p-2.5 text-xs">
                                    <AlertCircleIcon className="mt-0.5 size-4 shrink-0 text-amber-500" />
                                    <p>
                                        <span className="text-foreground font-semibold">
                                            Lưu ý quan trọng:
                                        </span>{' '}
                                        Chuyển đúng số tiền và nội dung chuyển
                                        khoản ở trên để hệ thống tự động nhận
                                        diện giao dịch và kích hoạt gói đăng ký
                                        tức thì.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <DialogFooter className="bg-muted/50 m-0 flex shrink-0 flex-col-reverse gap-2 rounded-b-xl border-t p-4 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsCheckoutOpen(false)}
                            className="w-full sm:w-auto"
                        >
                            Quay lại
                        </Button>
                        <Button
                            type="button"
                            onClick={handleConfirmPaid}
                            disabled={isCheckingPayment || timeLeft === 0}
                            className="bg-primary text-primary-foreground w-full sm:w-auto"
                        >
                            {isCheckingPayment ? (
                                <>
                                    <Loader2Icon className="mr-2 h-4 w-4 animate-spin" />
                                    Đang kiểm tra...
                                </>
                            ) : (
                                'Tôi đã chuyển tiền'
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
