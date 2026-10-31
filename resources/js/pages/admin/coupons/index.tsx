import { DeleteButton } from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { TicketPercent } from 'lucide-react';

interface CouponRow {
    id: number;
    code: string;
    description: string | null;
    discount_label: string;
    starts_on: string | null;
    ends_on: string | null;
    usage_limit: number | null;
    uses: number;
    is_active: boolean;
}

const newButtonClass =
    'inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

function validity(coupon: CouponRow): string {
    const day = (date: string) => formatDate(date, { day: 'numeric', month: 'short', year: 'numeric' });
    if (coupon.starts_on && coupon.ends_on) return `${day(coupon.starts_on)} – ${day(coupon.ends_on)}`;
    if (coupon.starts_on) return `From ${day(coupon.starts_on)}`;
    if (coupon.ends_on) return `Until ${day(coupon.ends_on)}`;

    return 'No end date';
}

export default function CouponsIndex({ coupons }: { coupons: CouponRow[] }) {
    return (
        <AdminLayout
            title="Coupons"
            actions={
                <Link href={route('admin.coupons.create')} className={newButtonClass}>
                    New coupon
                </Link>
            }
        >
            {coupons.length === 0 ? (
                <div className="flex max-w-3xl flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <TicketPercent className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No coupons yet.</p>
                </div>
            ) : (
                <ul className="max-w-3xl divide-y divide-border rounded-card border border-border bg-surface">
                    {coupons.map((coupon) => (
                        <li key={coupon.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <TextLink href={route('admin.coupons.edit', coupon.id)} className="font-mono">
                                        {coupon.code}
                                    </TextLink>
                                    {coupon.is_active ? <Badge tone="success">On</Badge> : <Badge>Off</Badge>}
                                </div>
                                <p className="text-sm text-muted">
                                    {coupon.discount_label} · {validity(coupon)} · used {coupon.uses}
                                    {coupon.usage_limit !== null && ` of ${coupon.usage_limit}`}
                                </p>
                                {coupon.description && <p className="text-sm text-muted">{coupon.description}</p>}
                            </div>
                            <DeleteButton
                                href={route('admin.coupons.destroy', coupon.id)}
                                itemName={coupon.code}
                                itemType="coupon"
                                description="Used coupons can't be deleted; switch them off instead."
                            />
                        </li>
                    ))}
                </ul>
            )}
        </AdminLayout>
    );
}
