import { Slot } from '@radix-ui/react-slot';
import { render } from '@testing-library/react';
import React from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { SidebarMenuButton } from '@/components/ui/sidebar';

test('SidebarMenuButton hides label when sidebar collapsed', () => {
    // Render with SidebarProvider forcing collapsed state
    const { container } = render(
        <SidebarProvider defaultOpen={false}>
            <SidebarMenuButton asChild tooltip="Test">
                <Slot>
                    <span>Label</span>
                </Slot>
            </SidebarMenuButton>
        </SidebarProvider>,
    );
    // The span with label should not be in the document
    expect(container.querySelector('span')).toBeNull();
});
