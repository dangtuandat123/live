import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, LoaderIcon, Trash2 } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({ password: '' });

    const confirmDelete = () => setConfirming(true);

    const closeModal = () => {
        setConfirming(false);
        clearErrors();
        reset();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => {
                setTimeout(() => {
                    passwordInput.current?.focus();
                }, 100);
            },
            onFinish: () => reset(),
        });
    };

    return (
        <>
            <Card className="border-destructive/30 bg-destructive/5 dark:bg-destructive/10">
                <CardHeader>
                    <CardTitle className="text-destructive flex items-center gap-2">
                        <AlertTriangle className="text-destructive size-5" />
                        Khu vực nguy hiểm
                    </CardTitle>
                    <CardDescription className="text-destructive/80">
                        Xóa vĩnh viễn tài khoản và toàn bộ dữ liệu liên quan.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-destructive/95 text-sm">
                        Khi tài khoản của bạn bị xóa, tất cả dữ liệu sẽ bị xóa
                        vĩnh viễn. Vui lòng tải xuống mọi dữ liệu bạn muốn giữ
                        lại trước khi xóa tài khoản.
                    </p>
                    <Button
                        variant="destructive"
                        onClick={confirmDelete}
                        className="gap-2"
                    >
                        <Trash2 className="size-4" />
                        Xóa tài khoản
                    </Button>
                </CardContent>
            </Card>

            <Dialog
                open={confirming}
                onOpenChange={(open) => !open && closeModal()}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-destructive text-lg font-semibold">
                            Xác nhận xóa tài khoản?
                        </DialogTitle>
                        <DialogDescription>
                            Tất cả dữ liệu của bạn sẽ bị xóa vĩnh viễn. Vui lòng
                            nhập mật khẩu của bạn để xác nhận hành động này.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="delete-password">
                                Mật khẩu của bạn
                            </Label>
                            <Input
                                id="delete-password"
                                type="password"
                                ref={passwordInput}
                                required
                                placeholder="Nhập mật khẩu để xác nhận"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                            {errors.password && (
                                <p className="text-destructive text-sm font-medium">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="mt-4">
                            <Button
                                variant="outline"
                                type="button"
                                onClick={closeModal}
                                disabled={processing}
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                                className="gap-2"
                            >
                                {processing && (
                                    <LoaderIcon className="size-4 animate-spin" />
                                )}
                                Xóa vĩnh viễn tài khoản
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
