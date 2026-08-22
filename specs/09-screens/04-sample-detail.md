# Screen — Sample Detail and Chain

**Route:** `/samples/{reference}`
**Who:** `sample.view`
**Data:** `GET /samples/{id}`, `GET /samples/{id}/chain`

---

## Why the chain is the screen

The client chose that a modification creates a **new sample** linked to the old
one, rather than a revision inside one record. `[CONFIRMED]` A24

That is a clean data decision, but it means the answer to *"how many attempts did
this colour take?"* is no longer visible in a single record. This screen is where
we give that answer back. `[PROPOSED]` B2

---

## Layout

### Chain panel — always at the top

```
Requirement:  colour "warm sand" · texture fine · New Giza reception
3 attempts · 11 days from first request to approval

 ●  SO9577-S3   attempt 3   APPROVED     formula SO9577-S3-F1     4 Sep
 ○  SO9577-S2   attempt 2   rejected     "too grey in daylight"   1 Sep
 ○  SO9577-S1   attempt 1   rejected     "too dark"              25 Aug
```

- Newest first. The one being viewed is marked.
- Each row links to its own full record: photos, formula, hours, who made it.
- Rejection reasons are shown in the list, not hidden behind a click. They are
  the reason the next attempt exists.
- Four attempts or more shows a marker. `[PROPOSED]` workflow 03 §4 — in Phase 2
  this becomes the cost alert.

The elapsed-time line matters to management: repeated attempts cost days as well
as money, and days are measurable in Phase 1 even though money is not.

### Detail of the selected sample

**Requirement** — client, project or a clear *Pre-sale* marker `[CONFIRMED]` A21,
colour, texture, client reference, size, finish requirement, requested by,
requested date, needed by.

**Photos** — the client's reference photo and the photo of what the workshop
actually made, side by side. This comparison is what a rejection conversation is
about.

**Formula** — reference, version, the text or the scanned tinting sheet, who
authored it and when, who registered it and when. The author and the registrar
are shown as two separate people, because they are. `[CONFIRMED]` A27

If Reception recorded a transcription correction, the original and the correction
are both shown, never one replacing the other. Workflow 04 §4.2

**Approvals** — the manager approval with the approver and the date, and the
client decision with the signatory's name, the date **written on the form**, and
the signed scan. `[CONFIRMED]` A23

**Hours** — workshop and tinting time spent on this attempt, from `time_entries`.
This is the raw material for Phase 2's sample cost.

**Activity** — the sample's event timeline.

---

## Actions

| Action | Permission | Shown when |
|---|---|---|
| Print approval form | `sample.record_client_decision` | ready for client decision |
| Record the client's decision | `sample.record_client_decision` | ready for client decision |
| Request a modification | `sample.request_modification` | the client rejected it |
| Attach to a project | `sample.create` | it is a pre-sale sample with no project |
| Cancel | `sample.cancel` | not yet approved |

**Attach to a project** exists because a pre-sale sample often precedes the deal.
`[CONFIRMED]` A21. Attaching sets `project_id` and **does not** rewrite the
reference — the old reference is already written on the physical sample in the
workshop. `03-data-model.md` §11

---

## States

| State | Behaviour |
|---|---|
| Superseded | A banner: "Replaced by SO9577-S3" with a link. Read-only |
| Pre-sale | A visible marker everywhere the sample appears, including the approver's task. It is the decision they are actually making |
| Awaiting formula | The formula block shows what is missing and which department owns it |
| Rejected, no modification yet | A prompt to request one, if the viewer may |
