"use client";
import { useAuth } from "@/context/auth-context";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { SampleChainPanel } from "@/components/samples/sample-chain-panel";
import { SampleDetailSections } from "@/components/samples/sample-detail-sections";
import { queryKeys } from "@/lib/queryKeys";
import { downloadApprovalForm, getSample, getSampleChain, triggerPdfDownload } from "@/services/samples";
import { formatSampleStatusLabel, sampleStatusBadgeColor } from "@/utils/sample-formatters";
import { useMutation, useQuery } from "@tanstack/react-query";
import Link from "next/link";
export default function SampleDetailView({reference}:{reference:string}){const {user}=useAuth();const perms=new Set(user?.permissions??[]);const sq=useQuery({queryKey:queryKeys.samples.detail(reference),queryFn:()=>getSample(reference)});const cq=useQuery({queryKey:queryKeys.samples.chain(reference),queryFn:()=>getSampleChain(reference),enabled:sq.isSuccess});const pm=useMutation({mutationFn:()=>downloadApprovalForm(reference),onSuccess:(b)=>triggerPdfDownload(b,`${reference}-approval.pdf`)});if(sq.isLoading)return<Skeleton className="h-40"/>;if(sq.isError||!sq.data)return<Alert status="error"><AlertTitle>Not found</AlertTitle></Alert>;const s=sq.data;const canPrint=perms.has("sample.record_client_decision")&&s.status==="ready_for_client_approval";return(<div className="px-4 pt-6 lg:px-6"><Link href="/samples" className="text-sm">← Samples</Link>{s.superseded_by?<Alert status="warning" className="my-4"><AlertDescription>Replaced by <Link href={`/samples/${s.superseded_by.reference}`}>{s.superseded_by.reference}</Link></AlertDescription></Alert>:null}<Card className="my-4"><CardHeader><CardTitle>{s.reference}</CardTitle><CardDescription>{s.color}{s.is_presale?" · Pre-sale":""}</CardDescription><Badge color={sampleStatusBadgeColor(s.status)}>{formatSampleStatusLabel(s.status)}</Badge></CardHeader></Card>{cq.data?<SampleChainPanel chain={cq.data} currentReference={reference}/>:null}<SampleDetailSections sample={s}/>{canPrint?<Button className="mt-4" onPress={()=>pm.mutate()}>{pm.isPending?"…":"Print approval form"}</Button>:null}</div>);}