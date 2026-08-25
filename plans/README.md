# Implementation Plans — Colortek Phase 1

Phase 1 is too large for one plan. It is split into six, each producing working,
testable software on its own. Build them in order.

| # | Plan | Produces | Status |
|---|---|---|---|
| 1 | [2026-08-22-phase1-spine.md](2026-08-22-phase1-spine.md) | Laravel API with auth, the working calendar, the task lifecycle and the workflow engine. An end-to-end test proves a completed task creates its successor in the right queue | **done** |
| 2 | [2026-08-25-phase2-payment-flow.md](2026-08-25-phase2-payment-flow.md) | The payment → journal → accounting workflow, plus Queue, My Tasks and Task Detail in Next.js. The first thing anyone can watch working | **done** |
| 3 | [2026-08-26-phase3-samples-and-formula.md](2026-08-26-phase3-samples-and-formula.md) | Sample request, manager approval, workshop, parallel tinting branch, formula registration, printed client approval form, sample chain UI | **draft** |
| 4 | [2026-08-26-phase4-site-visit-and-readiness.md](2026-08-26-phase4-site-visit-and-readiness.md) | The measurement sheet, the condition statement, readiness blocking, corrective actions, re-inspection, offline drafts | **draft** |
| 5 | [2026-08-26-phase5-live-layer.md](2026-08-26-phase5-live-layer.md) | Activity stream, SSE, notifications, control room, project detail, dashboards | **draft** |
| 6 | [2026-08-26-phase6-admin.md](2026-08-26-phase6-admin.md) | Calendar and holidays, roles and permissions, workflow templates, checklist items, the three failure lists | **draft** |

Each plan is written for an agent with no prior context. Every task names its
files, its spec sections, and a check that can be run and verified.

Write plan N+1 only after plan N is executed. Later plans depend on interfaces
that plan N settles, and writing them early guarantees rework.
