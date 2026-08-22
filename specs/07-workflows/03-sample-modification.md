# Workflow 03 — Sample Modification

**Template code:** `sample_modification`
**Scope:** creates a **new sample** linked to the rejected one. `[CONFIRMED]` A24

---

## 1. The decision the client made, and why it matters

The vision document (section 13) described revisions **inside** one sample
record: `S-001 Revision 1`, `S-001 Revision 2`. The client chose differently:
each modification is a **new sample record** with a link to its parent.

Both models preserve history. The difference is where the history lives.

What we must not lose by choosing the flat model is the ability to answer *"how
many attempts did this take?"*. Three columns solve it: `parent_sample_id`,
`root_sample_id` and `attempt_number`. `[PROPOSED]` B2

- `parent_sample_id` — the sample this one replaces
- `root_sample_id` — the first sample in the chain, copied down every generation
- `attempt_number` — 1, 2, 3… within the chain

So *"how many attempts for this requirement"* is
`SELECT COUNT(*) WHERE root_sample_id = ?`, a single indexed query, not a
recursive walk. The UI renders the chain as one thread. `[PROPOSED]` B2

---

## 2. The flow

```
Sample SO9577-S1  →  client rejects, with a reason
   │
[Sales] Create modification request
   │  copies the original requirement, records what must change
   ▼
   creates sample SO9577-S2
     parent_sample_id = S1
     root_sample_id   = S1
     attempt_number   = 2
     S1.status        = superseded
   ▼
[Reception] Review and forward
   ▼
[Approver] Manager approval          ← still always required
   ▼
[Workshop] Make the sample           ← sees the parent and the rejection reason
   ▼
[Tinting] New formula version        ← workflow 04
   ▼
[Reception] Register the formula
   ▼
[Sales] Get the client decision
```

From `reception_review_sample_request` onward this is the **same** template as
workflow 02. The modification workflow only exists to create the new sample
correctly and carry the reason forward. Duplicating the whole chain would mean
two copies of the same flow drifting apart.

---

## 3. Task definition

### `sales_create_modification_request`

| | |
|---|---|
| Department | `sales` |
| Permission | `sample.request_modification` |
| Timer | no |
| Available when | the parent sample is `rejected_by_client` |

**Form:**

| Key | Type | Required | Notes |
|---|---|---|---|
| `modification_reason` | textarea | **yes** | What the client wants changed, in their words |
| `color` | text | yes | Prefilled from the parent, editable |
| `texture` | text | no | Prefilled |
| `client_reference` | text | no | Prefilled |
| `size` | text | no | Prefilled |
| `finish_requirement` | textarea | no | Prefilled |
| `needed_by` | date | no | |

Prefilling from the parent is not a convenience. It is what stops the workshop
receiving a request that has silently lost the texture requirement because
somebody retyped it.

**Attachments:** `sample_photo` — optional new reference from the client.

**Business rules on complete:**

1. Create the new `samples` row with the parent link, the root, and
   `attempt_number = parent.attempt_number + 1`.
2. Reference: next `S` number on the project, e.g. `SO9577-S2`. The parent keeps
   its own reference forever. `[PROPOSED]` B1
3. Parent status → `superseded`. The parent record is **never edited** beyond
   this status change. `[CONFIRMED]` A24
4. Copy `client_id`, `project_id`, `is_presale` from the parent.
5. Start a new `sample_request` workflow instance on the new sample, entering at
   `reception_review_sample_request` — Sales has already done the Sales step.
6. Activity event `sample.modification_requested`, severity `warning`. Warning,
   not info: a repeat attempt costs the company money and management should see
   it in the feed.

---

## 4. Escalation on repeated attempts `[PROPOSED]`

When `attempt_number` reaches 4, the sample request is flagged in the feed and on
the project card as a repeated-attempt case, and the manager approval task is
raised to `high` priority.

Nothing is blocked. In Phase 2, when sample cost exists, this same signal becomes
the cost alert the vision document asked for in section 42.

The threshold of 4 is `[PROPOSED]`, stored in settings, and should be confirmed.

---

## 5. What the UI must show

On the sample screen, the chain is one panel, newest first:

```
Requirement: colour "warm sand", texture fine   —   3 attempts

  ● SO9577-S3   attempt 3   approved      formula SO9577-S3-F1
  ○ SO9577-S2   attempt 2   rejected      "too grey in daylight"
  ○ SO9577-S1   attempt 1   rejected      "too dark"
```

Each row links to the full sample, its formula, its photos and its hours. The
count of attempts is on the project card too, because it is one of the questions
the client said management most wants answered.

---

## 6. Test scenarios

1. A modification can only be created from a sample the client rejected.
2. The new sample has the correct parent, root and attempt number.
3. A third-generation sample keeps `root_sample_id` pointing at the first, not
   at its parent.
4. The parent is marked `superseded` and nothing else about it changes.
5. Requirement fields are prefilled from the parent.
6. The new instance enters at the Reception step, not at the Sales step.
7. Manager approval is still created — modification does not skip it.
8. The workshop task shows the parent reference and the rejection reason.
9. Counting attempts by `root_sample_id` returns the right number for a chain of
   three.
10. Reaching attempt 4 raises the approval priority and flags the feed.
