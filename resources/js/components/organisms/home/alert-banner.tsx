import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link } from '@inertiajs/react';

interface AlertBannerProps {
    base: string;
    banner: { days: number } | null;
}

export default function AlertBanner({ base, banner }: AlertBannerProps) {
    return (
        <Card className="bg-yellow-50">
            <CardContent>
                <div className="flex items-center justify-between">
                    <div>
                        <p className="font-semibold">
                            This period has run {banner?.days} days
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Got paid? Start the new period.
                        </p>
                    </div>
                    <Button
                        size="sm"
                        variant="default"
                        render={<Link href={`${base}/close`} />}
                    >
                        Start new
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
