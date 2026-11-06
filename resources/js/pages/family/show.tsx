import { Head, router } from '@inertiajs/react';
import { Loader2, X } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useState } from 'react';
import { BottomNav } from '@/components/bottom-nav';
import HeadTitle from '@/components/head-title';
import { JoinDrawer } from '@/components/organisms/join/join-drawer';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { HouseholdRef } from '@/types';

type Member = {
    id: number;
    name: string;
    role: 'owner' | 'editor' | 'viewer';
    you: boolean;
    joined_at: string;
};

type Props = {
    household: HouseholdRef;
    canManage: boolean;
    canEdit: boolean;
    invite: { code: string; url: string; expires_at: string } | null;
    members: Member[];
};

const secondsLeft = (iso?: string) =>
    iso
        ? Math.max(0, Math.floor((new Date(iso).getTime() - Date.now()) / 1000))
        : 0;
const clock = (s: number) =>
    `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;

function useSeconds(iso?: string) {
    const [s, setS] = useState(secondsLeft(iso));

    useEffect(() => {
        setS(secondsLeft(iso));
        const id = setInterval(() => setS(secondsLeft(iso)), 1000);

        return () => clearInterval(id);
    }, [iso]);

    return s;
}

export default function Family({
    household,
    canManage,
    invite,
    members,
}: Props) {
    const base = `/h/${household.id}`;
    const seconds = useSeconds(invite?.expires_at);
    const live = !!invite && seconds > 0;

    // Copy & Share action states
    const [copied, setCopied] = useState(false);
    const [shared, setShared] = useState(false);
    const [shareText, setShareText] = useState('Share link');

    const [isGeneratingCode, setIsGeneratingCode] = useState(false);

    // State for join drawer & deletion alert dialog
    const [isJoinOpen, setIsJoinOpen] = useState(false);
    const [selectedMember, setSelectedMember] = useState<Member | null>(null);

    const newCode = () => {
        setIsGeneratingCode(true);
        router.post(
            `${base}/family/invite`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTimeout(() => {
                        setIsGeneratingCode(false);
                    }, 1000);
                },
                onError: () => {
                    setIsGeneratingCode(false);
                },
            },
        );
    };

    const copy = async () => {
        if (!invite) return;

        await navigator.clipboard.writeText(invite.code);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const share = async () => {
        if (!invite) return;

        if (navigator.share) {
            try {
                await navigator.share({
                    title: 'Join our budget',
                    text: `Pair code ${invite.code}`,
                    url: invite.url,
                });
                setShareText('Shared');
                setShared(true);
                setTimeout(() => {
                    setShared(false);
                    setShareText('Share link');
                }, 1500);
            } catch {
                // User canceled or share failed
            }
        } else {
            await navigator.clipboard.writeText(invite.url);
            setShareText('Copied link');
            setShared(true);
            setTimeout(() => {
                setShared(false);
                setShareText('Share link');
            }, 1500);
        }
    };

    const confirmRemove = () => {
        if (!selectedMember) return;

        router.delete(`${base}/family/members/${selectedMember.id}`, {
            preserveScroll: true,
            onFinish: () => setSelectedMember(null),
        });
    };

    return (
        <>
            <Head title="Family" />

            <HeadTitle
                left={<h1 className="text-xl font-semibold">Family</h1>}
            />

            {canManage && (
                <div className="rounded-xl border p-4 text-center">
                    {live ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                Scan to join this budget
                            </p>
                            <div className="relative my-3 flex items-center justify-center">
                                <QRCodeSVG
                                    value={invite!.url}
                                    size={156}
                                    className={
                                        isGeneratingCode
                                            ? 'opacity-40 transition-opacity duration-200'
                                            : ''
                                    }
                                />
                                {isGeneratingCode && (
                                    <div className="absolute flex items-center justify-center">
                                        <Loader2 className="size-8 animate-spin text-primary" />
                                    </div>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                or enter pair code
                            </p>
                            <p className="my-1 font-mono text-3xl font-bold tracking-widest">
                                {invite!.code}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Expires in {clock(seconds)}
                            </p>
                            <div className="mt-4 grid grid-cols-2 gap-2">
                                <Button variant="outline" onClick={copy}>
                                    {copied ? 'Copied' : 'Copy code'}
                                </Button>
                                <Button
                                    variant={shared ? 'outline' : 'default'}
                                    onClick={share}
                                >
                                    {shareText}
                                </Button>
                            </div>
                            <button
                                className="mt-3 inline-flex items-center gap-1.5 text-sm text-muted-foreground underline disabled:opacity-50"
                                onClick={newCode}
                                disabled={isGeneratingCode}
                            >
                                {isGeneratingCode && (
                                    <Loader2 className="size-3.5 animate-spin" />
                                )}
                                Make a new code
                            </button>
                        </>
                    ) : (
                        <>
                            <p className="font-semibold">
                                {invite
                                    ? 'The code expired'
                                    : 'Invite your family'}
                            </p>
                            <p className="mb-4 text-sm text-muted-foreground">
                                A code works once and lasts 10 minutes.
                            </p>
                            <Button
                                onClick={newCode}
                                disabled={isGeneratingCode}
                            >
                                {isGeneratingCode && (
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                )}
                                Create pair code
                            </Button>
                        </>
                    )}
                </div>
            )}

            <h2 className="mt-2 text-base font-semibold">Members</h2>
            {members.map((m) => (
                <div key={m.id} className="flex items-center gap-3">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                        {m.name.charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-semibold">
                            {m.name}
                            {m.you && ' (you)'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {m.role === 'owner'
                                ? 'Owner'
                                : `Joined ${new Date(m.joined_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`}
                        </span>
                    </span>

                    {canManage && m.role !== 'owner' ? (
                        <Select
                            value={m.role}
                            onValueChange={(role) =>
                                router.patch(
                                    `${base}/family/members/${m.id}`,
                                    { role },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <SelectTrigger className="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="editor">Can edit</SelectItem>
                                <SelectItem value="viewer">
                                    View only
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    ) : (
                        <Badge variant="outline">
                            {m.role === 'viewer' ? 'View only' : 'Can edit'}
                        </Badge>
                    )}

                    {((canManage && m.role !== 'owner') ||
                        (m.you && m.role !== 'owner')) && (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={
                                m.you ? 'Leave household' : `Remove ${m.name}`
                            }
                            onClick={() => setSelectedMember(m)}
                        >
                            <X className="size-4" />
                        </Button>
                    )}
                </div>
            ))}

            <Button variant="outline" onClick={() => setIsJoinOpen(true)}>
                Join with a code instead
            </Button>

            <BottomNav householdId={household.id} active="family" />

            {/* Join Drawer */}
            <JoinDrawer open={isJoinOpen} onOpenChange={setIsJoinOpen} />

            {/* Member Deletion / Leave Alert Dialog */}
            <AlertDialog
                open={!!selectedMember}
                onOpenChange={(open) => {
                    if (!open) setSelectedMember(null);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {selectedMember?.you
                                ? 'Leave household?'
                                : 'Remove member?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {selectedMember?.you
                                ? 'Are you sure you want to leave this household? You will lose access to its budget records.'
                                : `Are you sure you want to remove ${selectedMember?.name} from this household?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmRemove}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {selectedMember?.you ? 'Leave' : 'Remove'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
