import { CurrencyMissingNotice } from '@/components/admin/currency-note';
import AdminLayout from '@/layouts/admin-layout';
import { LayoutDashboard } from 'lucide-react';

export default function Dashboard() {
    return (
        <AdminLayout title="Dashboard">
            <div className="space-y-6">
                <CurrencyMissingNotice />

                {/* Empty state until dashboard widgets (orders, revenue) are built. */}
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <LayoutDashboard className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">
                        Manage tests, packages and settings from the menu. Order stats will appear here.
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}
