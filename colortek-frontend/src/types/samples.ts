export type SampleStatus = "draft"|"pending_manager_approval"|"rejected_by_manager"|"in_workshop"|"awaiting_formula_registration"|"ready_for_client_approval"|"approved"|"rejected_by_client"|"superseded"|"cancelled";
export type FormulaStatus = "draft"|"registered"|"approved"|"superseded";
export interface AttachmentSummary { id:number; type:string; filename:string; url?:string; mime_type?:string; }
export interface PersonSummary { id:number; name:string; }
export interface EmployeeSummary { id:number; name:string; department?:string|null; }
export interface SampleApproval { id:number; type:"manager"|"client"; decision:"approved"|"rejected"; decided_at:string; decided_by?:PersonSummary|null; client_signatory_name?:string|null; comments?:string|null; attachment?:AttachmentSummary|null; }
export interface FormulaCorrection { original:string; correction:string; corrected_at:string; corrected_by?:PersonSummary|null; }
export interface Formula { id:number; reference:string; sample_id:number; version:number; body?:string|null; status:FormulaStatus; author_employee?:EmployeeSummary|null; author_user?:PersonSummary|null; authored_at?:string|null; registered_by?:PersonSummary|null; registered_at?:string|null; formula_sheet?:AttachmentSummary|null; corrections?:FormulaCorrection[]; }
export interface Sample { id:number; reference:string; client_id:number; client?:{id:number;name:string}|null; project?:{id:number;reference:string;name:string}|null; attempt_number:number; requested_by?:PersonSummary|null; requested_at:string; needed_by?:string|null; color:string; texture?:string|null; client_reference?:string|null; size?:string|null; finish_requirement?:string|null; status:SampleStatus; is_presale:boolean; formula?:Formula|null; approvals?:SampleApproval[]; reference_photo?:AttachmentSummary|null; sample_photo?:AttachmentSummary|null; superseded_by?:{id:number;reference:string}|null; hours?:{workshop_minutes?:number;tinting_minutes?:number}; }
export interface SampleChainEntry { id:number; reference:string; attempt_number:number; status:SampleStatus; rejection_reason?:string|null; formula_reference?:string|null; decided_at?:string|null; created_at?:string; }
export interface SampleChain { requirement_summary:string; attempt_count:number; elapsed_days?:number; entries:SampleChainEntry[]; }
export interface SampleSubjectContext { type:"sample"; id:number; reference:string; color:string; texture?:string|null; client_reference?:string|null; size?:string|null; finish_requirement?:string|null; is_presale:boolean; attempt_number:number; status:SampleStatus; parent_sample?:{reference:string;rejection_reason?:string|null}|null; reference_photo?:AttachmentSummary|null; formula?:Formula|null; previous_formula?:Formula|null; }
export interface StartSamplePayload { client_id:number; project_id?:number|null; color:string; texture?:string|null; client_reference?:string|null; size?:string|null; finish_requirement?:string|null; needed_by?:string|null; notes?:string|null; }
export interface ModificationRequestPayload { modification_reason:string; color?:string|null; texture?:string|null; client_reference?:string|null; size?:string|null; finish_requirement?:string|null; needed_by?:string|null; }
export interface ClientDecisionPayload { decision:"approved"|"rejected"; client_signatory_name:string; decided_at:string; comments?:string|null; attachment_ids:number[]; }
export interface AuthorFormulaPayload { body?:string|null; author_employee_id:number; authored_at:string; notes?:string|null; attachment_ids?:number[]; }
export interface RegisterFormulaPayload { confirm_matches_sheet:boolean; corrections?:string|null; notes?:string|null; }
export interface PatchFormulaPayload { body?:string|null; notes?:string|null; }
function isRecord(v:unknown):v is Record<string,unknown>{return typeof v==="object"&&v!==null}
export function isSample(v:unknown):v is Sample{return isRecord(v)&&typeof v.id==="number"&&typeof v.reference==="string"&&typeof v.color==="string"}
export function isSampleChain(v:unknown):v is SampleChain{return isRecord(v)&&typeof v.requirement_summary==="string"&&Array.isArray(v.entries)}
export function isSampleSubjectContext(v:unknown):v is SampleSubjectContext{return isRecord(v)&&v.type==="sample"&&typeof v.id==="number"}
