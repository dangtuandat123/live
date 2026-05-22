import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Trash2, LoaderIcon } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

export default function DeleteUserForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

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
                    <CardTitle className="flex items-center gap-2 text-destructive">
                        <AlertTriangle className="size-5 text-destructive" />
                        Khu vực nguy hiểm
                    </CardTitle>
                    <CardDescription className="text-destructive/80">
                        Xóa vĩnh viễn tài khoản và toàn bộ dữ liệu liên quan.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-sm text-destructive/95">
                        Khi tài khoản của bạn bị xóa, tất cả dữ liệu sẽ bị xóa vĩnh viễn. Vui lòng tải xuống mọi dữ liệu bạn muốn giữ lại trước khi xóa tài khoản.
                    </p>
                    <Button variant="destructive" onClick={confirmDelete} className="gap-2">
                        <Trash2 className="size-4" />
                        Xóa tài khoản
                    </Button>
                </CardContent>
            </Card>

            <Dialog open={confirming} onOpenChange={(open) => !open && closeModal()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-semibold text-destructive">
                            Xác nhận xóa tài khoản?
                        </DialogTitle>
                        <DialogDescription>
                            Tất cả dữ liệu của bạn sẽ bị xóa vĩnh viễn. Vui lòng nhập mật khẩu của bạn để xác nhận hành động này.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="delete-password">Mật khẩu của bạn</Label>
                            <Input
                                id="delete-password"
                                type="password"
                                ref={passwordInput}
                                required
                                placeholder="Nhập mật khẩu để xác nhận"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            {errors.password && (
                                <p className="text-sm text-destructive font-medium">{errors.password}</p>
                            )}
                        </div>

                        <DialogFooter className="mt-4">
                            <Button variant="outline" type="button" onClick={closeModal} disabled={processing}>
                                Hủy bỏ
                            </Button>
                            <Button variant="destructive" type="submit" disabled={processing} className="gap-2">
                                {processing && <LoaderIcon className="size-4 animate-spin" />}
                                Xóa vĩnh viễn tài khoản
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
