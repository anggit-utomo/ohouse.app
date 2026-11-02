import { cn } from '@/lib/utils';
import React from 'react';

interface HeadTitleProps {
    left?: React.ReactNode | string;
    right?: React.ReactNode | string;
    className?: string;
}

export default function HeadTitle({
    left,
    right,
    className = '',
}: HeadTitleProps) {
    return (
        <div className={cn('flex items-center justify-between', className)}>
            <div className="flex flex-col gap-0.5">{left}</div>
            <div className="flex -space-x-2">{right}</div>
        </div>
    );
}
