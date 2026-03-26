import { Transition } from '@headlessui/react';
import type { ReactNode } from 'react';

type Props = {
    show: boolean;
    direction: 'forward' | 'backward';
    children: ReactNode;
};

export function LoanRequestAnimatedStep({ show, direction, children }: Props) {
    const enterFrom =
        direction === 'forward'
            ? 'opacity-0 translate-x-4'
            : 'opacity-0 -translate-x-4';
    const leaveTo =
        direction === 'forward'
            ? 'opacity-0 -translate-x-4'
            : 'opacity-0 translate-x-4';

    return (
        <Transition
            appear
            show={show}
            enter="transition motion-safe:duration-200 motion-safe:ease-out motion-reduce:transition-none"
            enterFrom={enterFrom}
            enterTo="opacity-100 translate-x-0"
            leave="transition motion-safe:duration-150 motion-safe:ease-in motion-reduce:transition-none"
            leaveFrom="opacity-100 translate-x-0"
            leaveTo={leaveTo}
        >
            <div className="space-y-6">{children}</div>
        </Transition>
    );
}
