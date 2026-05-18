import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Separator } from '@/components/ui/separator';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AuthenticatedLayout>
            <Head title="Hồ sơ" />
            <div className="flex flex-1 flex-col gap-6 p-6 pt-0">
                <div className="flex flex-col gap-0.5">
                    <h1 className="text-2xl font-bold tracking-tight">Hồ sơ</h1>
                    <p className="text-muted-foreground">Quản lý thông tin tài khoản của bạn.</p>
                </div>
                <Separator />
                <div className="flex flex-col gap-8 max-w-2xl">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    <Separator />
                    <UpdatePasswordForm />
                    <Separator />
                    <DeleteUserForm />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
