import ProjectDetailView from "@/components/projects/project-detail-view";

export default async function ProjectDetailPage({
  params,
}: {
  params: Promise<{ reference: string }>;
}) {
  const { reference } = await params;
  return <ProjectDetailView reference={reference} />;
}
