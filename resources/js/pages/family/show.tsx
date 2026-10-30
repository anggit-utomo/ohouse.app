import { Head, Link, router } from '@inertiajs/react';
import { Settings, X } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useState } from 'react';
import { BottomNav } from '@/components/bottom-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Member = {
    id: number;
    name: string;
    role: 'owner' | 'editor' | 'viewer';
    you: boolean;
    joined_at: string;
};

type Props = {
    household: { id: number };
    canManage: boolean;
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
    const [copied, setCopied] = useState(false);

    const newCode = () =>
        router.post(`${base}/family/invite`, {}, { preserveScroll: true });

    const copy = async () => {
        await navigator.clipboard.writeText(invite!.code);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const share = () =>
        navigator.share
            ? navigator
                  .share({
                      title: 'Join our budget',
                      text: `Pair code ${invite!.code}`,
                      url: invite!.url,
                  })
                  .catch(() => undefined)
            : navigator.clipboard.writeText(invite!.url);

    const remove = (m: Member) => {
        const message = m.you
            ? 'Leave this household?'
            : `Remove ${m.name} from the household?`;
        if (confirm(message))
            router.delete(`${base}/family/members/${m.id}`, {
                preserveScroll: true,
            });
    };

    return (
        <>
            <Head title="Family" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-28">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Family</h1>
                    <Link href={`${base}/settings`} aria-label="Settings">
                        <Settings className="size-5" />
                    </Link>
                </div>

                {canManage && (
                    <div className="rounded-xl border p-4 text-center">
                        {live ? (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    Scan to join this budget
                                </p>
                                <div className="my-3 flex justify-center">
                                    <QRCodeSVG value={invite!.url} size={156} />
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
                                    <Button onClick={share}>Share link</Button>
                                </div>
                                <button
                                    className="mt-3 text-sm text-muted-foreground underline"
                                    onClick={newCode}
                                >
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
                                <Button onClick={newCode}>
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
                                    <SelectItem value="editor">
                                        Can edit
                                    </SelectItem>
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
                                    m.you
                                        ? 'Leave household'
                                        : `Remove ${m.name}`
                                }
                                onClick={() => remove(m)}
                            >
                                <X className="size-4" />
                            </Button>
                        )}
                    </div>
                ))}

                <Button variant="outline" asChild>
                    <Link href="/join">Join with a code instead</Link>
                </Button>
            </div>

            <BottomNav householdId={household.id} active="family" />
        </>
    );
}
