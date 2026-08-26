import { Breadcrumbs } from "@/components/tailgrids/core/breadcrumbs";

export default function AdminPageHeader({
  title,
  description,
  trail,
}: {
  title: string;
  description?: string;
  trail: Array<{ href: string; label: string }>;
}) {
  return (
    <div className="flex flex-col gap-3 px-2 pt-6 lg:px-6">
      <Breadcrumbs dividerType="chevron" items={trail} />
      <div>
        <h1 className="text-[28px] font-medium leading-8 text-text-primary">{title}</h1>
        {description ? (
          <p className="mt-1 max-w-3xl text-sm text-text-secondary">{description}</p>
        ) : null}
      </div>
    </div>
  );
}
