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
import { Link, useForm, usePage } from '@inertiajs/react';
import { CheckIcon, LoaderIcon, User } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit}>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <User className="text-primary size-5" />
                        Thông tin cá nhân
                    </CardTitle>
                    <CardDescription>
                        Cập nhật tên và địa chỉ email của bạn.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Họ và tên</Label>
                        <Input
                            id="name"
                            required
                            autoFocus
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && (
                            <p className="text-destructive text-sm font-medium">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && (
                            <p className="text-destructive text-sm font-medium">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-4 text-sm text-yellow-600 dark:text-yellow-400">
                            Email của bạn chưa được xác thực.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="font-medium underline underline-offset-4 hover:text-yellow-700 dark:hover:text-yellow-300"
                            >
                                Nhấn vào đây để gửi lại email xác thực.
                            </Link>
                            {status === 'verification-link-sent' && (
                                <span className="mt-2 block font-medium text-green-600 dark:text-green-400">
                                    Liên kết xác thực mới đã được gửi đến email
                                    của bạn.
                                </span>
                            )}
                        </div>
                    )}

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
                        {recentlySuccessful ? 'Đã lưu' : 'Lưu thay đổi'}
                    </Button>
                </CardContent>
            </Card>
        </form>
    );
}
