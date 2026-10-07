import { show as staffMemberShow } from '@/actions/App/Http/Controllers/Spa/Staff/MembersController';
import { MembersDirectoryPage } from '@/components/member/members-directory-page';
import { useStaffMembers } from '@/hooks/staff/use-members';
import { index as staffMembersIndex } from '@/routes/staff/members';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Members',
        href: staffMembersIndex().url,
    },
];

const memberHref = (memberId: string) => staffMemberShow(memberId).url;

export default function StaffMembersPage() {
    return (
        <MembersDirectoryPage
            breadcrumbs={breadcrumbs}
            useMemberList={useStaffMembers}
            memberHref={memberHref}
        />
    );
}
