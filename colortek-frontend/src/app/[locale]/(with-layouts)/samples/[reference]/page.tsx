import SampleDetailView from "@/components/samples/sample-detail-view";
export default async function Page({params}:{params:Promise<{reference:string}>}){const {reference}=await params;return <SampleDetailView reference={decodeURIComponent(reference)}/>;}
