import { Progress as ProgressPrimitive } from 'radix-ui';
import * as React from 'react';

import { cn } from '@/lib/utils';

interface ProgressProps extends React.ComponentProps<
    typeof ProgressPrimitive.Root
> {
    indicatorClassName?: string;
}

function Progress({
    className,
    value,
    indicatorClassName,
    ...props
}: ProgressProps) {
    return (
        <ProgressPrimitive.Root
            data-slot="progress"
            className={cn(
                'bg-muted relative flex h-1 w-full items-center overflow-x-hidden rounded-full',
                className,
            )}
            {...props}
        >
            <ProgressPrimitive.Indicator
                data-slot="progress-indicator"
                className={cn(
                    'bg-primary size-full flex-1 transition-all',
                    indicatorClassName,
                )}
                style={{ transform: `translateX(-${100 - (value || 0)}%)` }}
            />
        </ProgressPrimitive.Root>
    );
}

export { Progress };
