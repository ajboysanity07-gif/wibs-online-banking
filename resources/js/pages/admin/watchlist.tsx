import { MembersDirectoryPage } from '@/components/member/members-directory-page';
import { useMembers } from '@/hooks/admin/use-members';
import { show as showMember } from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Members',
        href: membersIndex().url,
    },
];

const memberHref = (memberId: string) => showMember(memberId).url;

export default function MembersPage() {
    return (
        <MembersDirectoryPage
            breadcrumbs={breadcrumbs}
            useMemberList={useMembers}
            memberHref={memberHref}
        />
    );
}
