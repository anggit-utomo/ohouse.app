import React from 'react';

interface HeadTitleProps {
    left?: React.ReactNode | string;
    right?: React.ReactNode | string;
}

export default function HeadTitle({ left, right }: HeadTitleProps) {
    return (
        <div className="flex items-center justify-between">
            <div className="flex flex-col gap-0.5">{left}</div>
            <div className="flex -space-x-2">{right}</div>
        </div>
    );
}
