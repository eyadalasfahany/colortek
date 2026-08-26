import SiteVisitForm from "@/components/site/site-visit-form";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <SiteVisitForm visitId={Number(id)} />;
}
