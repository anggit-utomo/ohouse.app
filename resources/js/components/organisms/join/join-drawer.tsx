import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialCode?: string;
};

export function JoinDrawer({ open, onOpenChange, initialCode = '' }: Props) {
    const form = useForm({ code: initialCode });
    const [isAlertOpen, setIsAlertOpen] = useState(false);

    useEffect(() => {
        if (open) {
            form.setData('code', initialCode);
            form.clearErrors();
        }
    }, [open, initialCode]);

    const forceClose = () => {
        setIsAlertOpen(false);
        onOpenChange(false);
        form.reset();
        form.clearErrors();
    };

    const handleAttemptClose = () => {
        if (form.isDirty && form.data.code.trim() !== '') {
            setIsAlertOpen(true);
        } else {
            forceClose();
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.post('/join', {
            replace: true,
            onSuccess: () => forceClose(),
        });
    };

    return (
        <>
            <Drawer
                open={open}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        handleAttemptClose();
                    } else {
                        onOpenChange(true);
                    }
                }}
            >
                <DrawerContent>
                    <div className="mx-auto w-full max-w-sm">
                        <form onSubmit={submit}>
                            <DrawerHeader>
                                <DrawerTitle>Join a family budget</DrawerTitle>
                                <DrawerDescription>
                                    Type the pair code your partner shows you to
                                    join their household.
                                </DrawerDescription>
                            </DrawerHeader>

                            <div className="grid gap-2 px-4">
                                <Label htmlFor="join-code">Pair code</Label>
                                <Input
                                    id="join-code"
                                    autoCapitalize="characters"
                                    autoComplete="off"
                                    placeholder="KTR-4829"
                                    className="h-14 rounded-md border bg-background px-3 text-center font-mono text-2xl font-bold tracking-widest"
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                {form.errors.code && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.code}
                                    </p>
                                )}
                            </div>

                            <DrawerFooter className="py-6">
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            form.data.code.trim() === ''
                                        }
                                    >
                                        {form.processing ? (
                                            <div className="flex items-center gap-2">
                                                <Spinner /> Joining...
                                            </div>
                                        ) : (
                                            'Join budget'
                                        )}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleAttemptClose}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </DrawerFooter>
                        </form>
                    </div>
                </DrawerContent>
            </Drawer>

            <AlertDialog open={isAlertOpen} onOpenChange={setIsAlertOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Discard pair code?</AlertDialogTitle>
                        <AlertDialogDescription>
                            You entered a pair code. Leaving now will clear your
                            input.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Continue Editing</AlertDialogCancel>
                        <AlertDialogAction onClick={forceClose}>
                            Discard
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
