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
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckIcon,
    CreditCardIcon,
    GlobeIcon,
    HelpCircleIcon,
    LoaderIcon,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface PaymentConfigData {
    id?: number;
    name: string;
    prefix: string | null;
    suffix: string | null;
    webhook_url: string | null;
    method: 'POST' | 'GET' | 'PUT';
    params_template: Record<string, unknown> | null;
    headers_template: Record<string, unknown> | null;
    is_active?: boolean;
    bank_name?: string | null;
    bank_id?: string | null;
    account_no?: string | null;
    account_name?: string | null;
    qr_template?: string | null;
}

interface Props {
    config: PaymentConfigData;
    total_revenue: number;
}

interface PaymentConfigForm {
    name: string;
    prefix: string;
    suffix: string;
    webhook_url: string;
    method: string;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    params_template: any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    headers_template: any;
    bank_name: string;
    bank_id: string;
    account_no: string;
    account_name: string;
    qr_template: string;
}

export default function AdminPayments({ config, total_revenue }: Props) {
    // Trạng thái chuỗi JSON cho textarea
    const [paramsJsonStr, setParamsJsonStr] = React.useState(
        config.params_template
            ? JSON.stringify(config.params_template, null, 2)
            : '{\n  "id_user": "{user_id}",\n  "sotien": {amount}\n}',
    );
    const [headersJsonStr, setHeadersJsonStr] = React.useState(
        config.headers_template
            ? JSON.stringify(config.headers_template, null, 2)
            : '{\n  "Content-Type": "application/json",\n  "Accept": "application/json"\n}',
    );

    const [localErrors, setLocalErrors] = React.useState<{
        params_template?: string;
        headers_template?: string;
    }>({});

    const { data, setData, processing, recentlySuccessful, errors } =
        useForm<PaymentConfigForm>({
            name: config.name || 'VietQR Payment Gateway',
            prefix: config.prefix || '',
            suffix: config.suffix || '',
            webhook_url: config.webhook_url || '',
            method: config.method || 'POST',
            params_template: config.params_template || {},
            headers_template: config.headers_template || {},
            bank_name: config.bank_name || '',
            bank_id: config.bank_id || '',
            account_no: config.account_no || '',
            account_name: config.account_name || '',
            qr_template: config.qr_template || '',
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLocalErrors({});

        let parsedParams = {};
        let parsedHeaders = {};

        // Validate & parse Params Template
        if (paramsJsonStr.trim()) {
            try {
                parsedParams = JSON.parse(paramsJsonStr);
                if (typeof parsedParams !== 'object' || parsedParams === null) {
                    setLocalErrors((prev) => ({
                        ...prev,
                        params_template:
                            'Params Template phải là một JSON Object hợp lệ.',
                    }));
                    toast.error(
                        'Vui lòng kiểm tra lại cấu hình Params Template',
                    );
                    return;
                }
            } catch (err) {
                const error = err as Error;
                setLocalErrors((prev) => ({
                    ...prev,
                    params_template: `Lỗi cú pháp JSON: ${error.message}`,
                }));
                toast.error(
                    'Cấu hình Params Template không đúng định dạng JSON',
                );
                return;
            }
        }

        // Validate & parse Headers Template
        if (headersJsonStr.trim()) {
            try {
                parsedHeaders = JSON.parse(headersJsonStr);
                if (
                    typeof parsedHeaders !== 'object' ||
                    parsedHeaders === null
                ) {
                    setLocalErrors((prev) => ({
                        ...prev,
                        headers_template:
                            'Headers Template phải là một JSON Object hợp lệ.',
                    }));
                    toast.error(
                        'Vui lòng kiểm tra lại cấu hình Headers Template',
                    );
                    return;
                }
            } catch (err) {
                const error = err as Error;
                setLocalErrors((prev) => ({
                    ...prev,
                    headers_template: `Lỗi cú pháp JSON: ${error.message}`,
                }));
                toast.error(
                    'Cấu hình Headers Template không đúng định dạng JSON',
                );
                return;
            }
        }

        // Cập nhật dữ liệu đã parse và tiến hành gửi form
        // Để chắc chắn Inertia gửi đúng dữ liệu sau khi setState (bất đồng bộ),
        // chúng ta truyền object trực tiếp vào put() hoặc cập nhật form và submit.
        // Cách an toàn nhất của Inertia useForm là truyền payload trực tiếp trong submit.
        router.put(
            route('admin.payments.update'),
            {
                ...data,
                params_template: parsedParams,
                headers_template: parsedHeaders,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Đã lưu cấu hình thanh toán thành công!');
                },
                onError: () => {
                    toast.error('Có lỗi xảy ra khi lưu cấu hình.');
                },
            },
        );
    }

    return (
        <AdminLayout>
            <Head title="Admin - Cấu hình thanh toán" />
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
                                    Cấu hình thanh toán
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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Cấu hình thanh toán
                        </h1>
                        <p className="text-muted-foreground">
                            Thiết lập tham số VietQR và webhook thông báo về hệ
                            thống VPS
                        </p>
                    </div>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="gap-2"
                    >
                        {processing ? (
                            <LoaderIcon className="size-4 animate-spin" />
                        ) : recentlySuccessful ? (
                            <CheckIcon className="size-4" />
                        ) : null}
                        {recentlySuccessful ? 'Đã lưu' : 'Lưu cấu hình'}
                    </Button>
                </div>

                {/* Doanh thu Card */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="md:col-span-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Tổng doanh thu hệ thống
                            </CardTitle>
                            <CreditCardIcon className="size-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                                {total_revenue.toLocaleString('vi-VN')}đ
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Tổng doanh thu tích lũy từ các lượt đăng ký
                                thành công
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* VietQR Config */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CreditCardIcon className="text-primary size-5" />
                            Cài đặt VietQR
                        </CardTitle>
                        <CardDescription>
                            Cấu hình thông số nội dung chuyển khoản VietQR
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="gateway-name">
                                Tên Cổng Thanh Toán
                            </Label>
                            <Input
                                id="gateway-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Ví dụ: VietQR Cổng 1"
                                required
                            />
                            {errors.name && (
                                <p className="text-destructive text-xs">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">
                                    Tên Ngân hàng thụ hưởng
                                </Label>
                                <Input
                                    id="bank_name"
                                    value={data.bank_name || ''}
                                    onChange={(e) =>
                                        setData('bank_name', e.target.value)
                                    }
                                    placeholder="Ví dụ: MB Bank"
                                    required
                                />
                                {errors.bank_name && (
                                    <p className="text-destructive text-xs">
                                        {errors.bank_name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_id">
                                    Mã BIN Ngân hàng (bank_id)
                                </Label>
                                <Input
                                    id="bank_id"
                                    value={data.bank_id || ''}
                                    onChange={(e) =>
                                        setData('bank_id', e.target.value)
                                    }
                                    placeholder="Ví dụ: 970416"
                                    required
                                />
                                {errors.bank_id && (
                                    <p className="text-destructive text-xs">
                                        {errors.bank_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="account_no">
                                    Số tài khoản thụ hưởng
                                </Label>
                                <Input
                                    id="account_no"
                                    value={data.account_no || ''}
                                    onChange={(e) =>
                                        setData('account_no', e.target.value)
                                    }
                                    placeholder="Ví dụ: 11183041"
                                    required
                                />
                                {errors.account_no && (
                                    <p className="text-destructive text-xs">
                                        {errors.account_no}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="account_name">
                                    Tên chủ tài khoản
                                </Label>
                                <Input
                                    id="account_name"
                                    value={data.account_name || ''}
                                    onChange={(e) =>
                                        setData('account_name', e.target.value)
                                    }
                                    placeholder="Ví dụ: DANG TUAN DAT"
                                    required
                                />
                                {errors.account_name && (
                                    <p className="text-destructive text-xs">
                                        {errors.account_name}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="qr_template">
                                Template URL mã QR (qr_template)
                            </Label>
                            <Input
                                id="qr_template"
                                value={data.qr_template || ''}
                                onChange={(e) =>
                                    setData('qr_template', e.target.value)
                                }
                                placeholder="https://api.vietqr.io/image/{bank_id}-{account_no}-rdXzPHV.jpg?accountName={account_name}&addInfo={Prefix}%20{userId}%20{Suffix}&amount={amount}"
                            />
                            {errors.qr_template && (
                                <p className="text-destructive text-xs">
                                    {errors.qr_template}
                                </p>
                            )}
                            <p className="text-muted-foreground text-xs">
                                URL để render QR Code VietQR động. Sử dụng các
                                thẻ: <code>{`{bank_id}`}</code>,{' '}
                                <code>{`{account_no}`}</code>,{' '}
                                <code>{`{account_name}`}</code>,{' '}
                                <code>{`{amount}`}</code>,{' '}
                                <code>{`{Prefix}`}</code>,{' '}
                                <code>{`{userId}`}</code>,{' '}
                                <code>{`{Suffix}`}</code>.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="prefix">
                                    Mã Prefix (Tiền tố)
                                </Label>
                                <Input
                                    id="prefix"
                                    value={data.prefix || ''}
                                    onChange={(e) =>
                                        setData('prefix', e.target.value)
                                    }
                                    placeholder="Ví dụ: TTGR"
                                />
                                {errors.prefix && (
                                    <p className="text-destructive text-xs">
                                        {errors.prefix}
                                    </p>
                                )}
                                <p className="text-muted-foreground text-xs">
                                    Mã nhận diện bắt đầu nội dung chuyển khoản.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="suffix">
                                    Mã Suffix (Hậu tố)
                                </Label>
                                <Input
                                    id="suffix"
                                    value={data.suffix || ''}
                                    onChange={(e) =>
                                        setData('suffix', e.target.value)
                                    }
                                    placeholder="Ví dụ: NAP"
                                />
                                {errors.suffix && (
                                    <p className="text-destructive text-xs">
                                        {errors.suffix}
                                    </p>
                                )}
                                <p className="text-muted-foreground text-xs">
                                    Mã nhận diện kết thúc nội dung chuyển khoản.
                                </p>
                            </div>
                        </div>

                        <div className="bg-muted/50 border-border/40 text-muted-foreground space-y-1 rounded-lg border p-3 text-xs">
                            <span className="text-foreground mb-1 flex items-center gap-1 font-semibold">
                                <HelpCircleIcon className="text-primary size-3.5" />{' '}
                                Giải thích cú pháp nội dung chuyển khoản:
                            </span>
                            <p>
                                Nội dung chuyển khoản trên QR Code sẽ có định
                                dạng:{' '}
                                <code className="bg-background border-border text-foreground rounded border px-1.5 py-0.5 font-mono font-medium">
                                    {data.prefix || 'PREFIX'} {'{userId}'}{' '}
                                    {data.suffix || 'SUFFIX'}
                                </code>
                            </p>
                            <p>
                                Ví dụ với User ID là{' '}
                                <span className="text-foreground font-semibold">
                                    1132
                                </span>
                                :{' '}
                                <code className="bg-background border-border text-primary rounded border px-1.5 py-0.5 font-mono font-medium">
                                    {data.prefix || 'TTGR'} 1132{' '}
                                    {data.suffix || 'NAP'}
                                </code>
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Webhook VPS Config */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GlobeIcon className="text-primary size-5" />
                            Webhook Forwarding (VPS)
                        </CardTitle>
                        <CardDescription>
                            Cấu hình để hệ thống tự động gọi API tới VPS của bạn
                            khi nhận được tiền
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="webhook-url">Webhook URL</Label>
                            <Input
                                id="webhook-url"
                                type="url"
                                value={data.webhook_url || ''}
                                onChange={(e) =>
                                    setData('webhook_url', e.target.value)
                                }
                                placeholder="https://vps-cua-ban.com/api/payment-callback"
                            />
                            {errors.webhook_url && (
                                <p className="text-destructive text-xs">
                                    {errors.webhook_url}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="method">HTTP Method</Label>
                            <Select
                                value={data.method}
                                onValueChange={(val: 'POST' | 'GET' | 'PUT') =>
                                    setData('method', val)
                                }
                            >
                                <SelectTrigger
                                    id="method"
                                    className="w-[180px]"
                                >
                                    <SelectValue placeholder="Chọn Method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="POST">POST</SelectItem>
                                    <SelectItem value="GET">GET</SelectItem>
                                    <SelectItem value="PUT">PUT</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.method && (
                                <p className="text-destructive text-xs">
                                    {errors.method}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="params-template">
                                    Params Template (JSON)
                                </Label>
                                <span className="text-muted-foreground bg-muted rounded px-1 py-0.5 font-mono text-[10px]">
                                    Placeholders: {'{user_id}'}, {'{amount}'},{' '}
                                    {'{transaction_id}'}
                                </span>
                            </div>
                            <textarea
                                id="params-template"
                                value={paramsJsonStr}
                                onChange={(e) =>
                                    setParamsJsonStr(e.target.value)
                                }
                                rows={6}
                                className="border-input placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder='{\n  "id_user": "{user_id}",\n  "sotien": {amount}\n}'
                            />
                            {(errors.params_template ||
                                localErrors.params_template) && (
                                <p className="text-destructive text-xs">
                                    {errors.params_template ||
                                        localErrors.params_template}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="headers-template">
                                    Headers Template (JSON)
                                </Label>
                                <span className="text-muted-foreground bg-muted rounded px-1 py-0.5 font-mono text-[10px]">
                                    Ví dụ: {'{"X-API-Key": "YOUR_KEY"}'}
                                </span>
                            </div>
                            <textarea
                                id="headers-template"
                                value={headersJsonStr}
                                onChange={(e) =>
                                    setHeadersJsonStr(e.target.value)
                                }
                                rows={4}
                                className="border-input placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder='{\n  "Content-Type": "application/json",\n  "Accept": "application/json"\n}'
                            />
                            {(errors.headers_template ||
                                localErrors.headers_template) && (
                                <p className="text-destructive text-xs">
                                    {errors.headers_template ||
                                        localErrors.headers_template}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
