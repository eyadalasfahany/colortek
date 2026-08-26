"use client";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getSamples } from "@/services/samples";
import type { Sample, SampleStatus } from "@/types/samples";
import { formatSampleStatusLabel, hasRepeatAttemptAlert, sampleStatusBadgeColor } from "@/utils/sample-formatters";
import { useQuery } from "@tanstack/react-query";
import { Link } from "@/i18n/navigation";
const GROUPS: Array<{key:SampleStatus;label:string}>=[{key:"pending_manager_approval",label:"Awaiting manager approval"},{key:"in_workshop",label:"In workshop"},{key:"awaiting_formula_registration",label:"Awaiting formula registration"},{key:"ready_for_client_approval",label:"Awaiting client decision"},{key:"approved",label:"Approved"},{key:"rejected_by_client",label:"Rejected"}];
export default function SampleListPage(){const q=useQuery({queryKey:queryKeys.samples.list(),queryFn:()=>getSamples({per_page:100})});return(<div className="px-4 pt-6 lg:px-6"><h1 className="text-2xl font-semibold">Samples</h1>{q.isLoading?<Skeleton className="mt-4 h-40"/>:null}{q.isError?<Alert status="error" className="mt-4"><AlertTitle>Error</AlertTitle><AlertDescription>{q.error instanceof Error?q.error.message:"Failed"}</AlertDescription></Alert>:null}{q.isSuccess?<Board samples={q.data.data}/>:null}</div>);}
function Board({samples}:{samples:Sample[]}){if(!samples.length)return<Card className="mt-4"><CardDescription>No samples yet.</CardDescription></Card>;return<div className="mt-4 grid gap-4 xl:grid-cols-2">{GROUPS.map(g=>{const items=samples.filter(s=>s.status===g.key);if(!items.length)return null;return<Card key={g.key}><CardTitle>{g.label}</CardTitle><ul className="mt-3 space-y-2">{items.map(s=><li key={s.id}><Link href={`/samples/${s.reference}`}><Card><CardHeader><CardTitle className="text-base">{s.reference}</CardTitle><CardDescription>{s.color}</CardDescription><Badge color={sampleStatusBadgeColor(s.status)} size="sm">{formatSampleStatusLabel(s.status)}</Badge>{hasRepeatAttemptAlert(s.attempt_number)?<Badge color="warning" size="sm">Attempt {s.attempt_number}</Badge>:null}</CardHeader></Card></Link></li>)}</ul></Card>})}</div>;}
