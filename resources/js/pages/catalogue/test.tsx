import { Seo } from '@/components/seo';
import { CategoryLinks } from '@/components/catalogue/category-links';
import { DetailRow } from '@/components/catalogue/detail-row';
import { AddToCartButton } from '@/components/catalogue/add-to-cart-button';
import { Price } from '@/components/catalogue/price';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { formatTurnaround } from '@/lib/format';
import type { LabTest } from '@/types';
import { t } from '@/lib/i18n';

export default function TestDetail({ test }: { test: LabTest }) {
    return (
        <AppLayout>
            <Seo title={test.name} description={test.description} />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('catalogue.index')} className="text-sm">
                    {t('← All tests & packages')}
                </TextLink>

                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">{test.name}</h1>
                <div className="mt-3 flex flex-wrap items-center gap-4">
                    <Price amount={test.price_amount} className="text-xl" />
                    <AddToCartButton type="test" id={test.id} className="min-w-32" />
                </div>
                {test.description && <p className="mt-4 text-base text-muted">{test.description}</p>}
                <div className="mt-4">
                    <CategoryLinks categories={test.categories} />
                </div>

                <dl className="mt-6 divide-y divide-border rounded-card border border-border bg-surface px-4 md:px-6">
                    <DetailRow label={t('Sample')}>{test.sample_type}</DetailRow>
                    <DetailRow label={t('Reports in')}>{formatTurnaround(test.turnaround_hours)}</DetailRow>
                    <DetailRow label={t('Preparation')}>
                        {test.preparation ? (
                            <span className="whitespace-pre-line">{test.preparation}</span>
                        ) : (
                            t('No special preparation')
                        )}
                    </DetailRow>
                    {test.requires_prescription && (
                        <DetailRow label={t('Prescription')}>{t('Required: upload it at checkout')}</DetailRow>
                    )}
                    <DetailRow label={t('Test code')}>{test.code}</DetailRow>
                </dl>
            </div>
        </AppLayout>
    );
}
