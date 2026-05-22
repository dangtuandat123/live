import { Button } from '@/components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Xác thực email" />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold">Xác thực email</h1>
                    <p className="text-muted-foreground mt-2 text-sm text-balance">
                        Cảm ơn bạn đã đăng ký! Trước khi bắt đầu, vui lòng xác
                        thực địa chỉ email của bạn bằng cách nhấp vào liên kết
                        chúng tôi vừa gửi qua email.
                    </p>
                </div>
                {status === 'verification-link-sent' && (
                    <div className="text-center text-sm font-medium text-green-600">
                        Liên kết xác thực mới đã được gửi đến email bạn đã cung
                        cấp khi đăng ký.
                    </div>
                )}
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        Gửi lại email xác thực
                    </Button>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-muted-foreground hover:text-foreground text-center text-sm underline underline-offset-4"
                    >
                        Đăng xuất
                    </Link>
                </form>
            </div>
        </GuestLayout>
    );
}
