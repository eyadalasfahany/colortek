# AI-Powered Live Project Workflow & Cost Intelligence System
## Product / UX / Workflow Design Specification for Claude Opus

**Document purpose:**  
This document captures the full product concept, business workflows, internal handoffs, roles, task automation rules, live operational tracking, costing logic, material quantity calculations, AI capabilities, Odoo integration principles, and UI/UX expectations discussed so far.

It is intended to be detailed enough for Claude Opus (or another product/design agent) to begin designing the system architecture, information architecture, user journeys, screens, dashboards, and interactive prototype.

> **Important:** This system is **not a replacement for Odoo**. Odoo remains the core business system and system of record. The proposed system is an intelligent, real-time operational workflow layer connected to Odoo.

---

# 1. Executive Summary

The company currently uses **Odoo** for business operations such as clients, quotations, payments, accounting, projects and related records.

The proposed system should sit on top of Odoo and provide:

- Live project workflow visibility.
- Automatic task generation and handover between people/departments.
- Real-time responsibility tracking.
- Employee task timers and working-hour tracking.
- Sample workflow and sample revision traceability.
- Workshop workflow tracking.
- Tinting/formula tracking.
- Site inspection and site readiness workflows.
- Production/execution tracking.
- Project cost tracking from the first sample until project completion.
- Material quantity calculations based on each material's technical Data Sheet.
- Planned vs actual material consumption analysis.
- AI-generated operational summaries, alerts, forecasts and explanations.
- Company-wide connectivity so that each team knows exactly what they need to do next.

The key operating principle is:

> **Complete Task → Automatically Create Next Task → Assign Responsible Person → Transfer Relevant Information & Documents → Update Live Workflow → Trigger Next Task**

The system should behave like a **live company operating system**, not just a static project-management tool.

---

# 2. Core Product Vision

The company should be able to open one system and immediately understand:

- What projects are currently active?
- What stage is each project in?
- What is currently happening inside each project?
- Who is responsible for each task?
- Who is physically/operationally working on each project today?
- How many workers are working on each project?
- How many hours has each person spent?
- What is blocked?
- Why is it blocked?
- What is overdue?
- What is the next action?
- Which person or department receives the next task?
- Which documents are required before the next stage?
- How many samples have been made?
- How many sample revisions were required?
- Which formulas were used?
- Which sample was finally approved?
- What was the cost of samples before execution started?
- How much material was technically required?
- How much was actually consumed?
- What is the current actual project cost?
- What is the expected final project cost?
- Is the project exceeding normal material consumption?
- Is labor cost increasing unexpectedly?
- What operational risk should management know about now?

---

# 3. Non-Negotiable System Principles

## 3.1 Odoo is the Core System

Odoo remains the **System of Record**.

The new platform should integrate with Odoo rather than duplicate Odoo unnecessarily.

Odoo is expected to remain responsible for core records such as:

- Customers / contacts.
- Sales.
- Quotations.
- Payments.
- Accounting-related records.
- Projects.
- Inventory/material records where applicable.
- Other existing company records.

The new system should act as:

- Workflow engine.
- Task orchestration layer.
- Live operational dashboard.
- Time tracking layer.
- Cost intelligence layer.
- AI assistant layer.

---

## 3.2 Every Workflow Step Must Have an Owner

No workflow stage should exist without:

- Responsible role.
- Responsible person.
- Status.
- Start time.
- Completion time.
- Required input.
- Required output.
- Required attachments where applicable.
- Dependencies.
- Next task.
- Audit history.

---

## 3.3 Handover Must Be Automatic

Information should not rely on people manually forwarding details between departments.

For example, Sales should not need to separately tell Reception:

> “The client paid; here are the details.”

Instead:

1. Sales completes the required payment-confirmation task.
2. Sales locks the quotation in Odoo.
3. Sales uploads payment proof.
4. The workflow engine detects completion.
5. The system automatically creates the Reception task.
6. The Reception task already contains:
   - Project.
   - Client.
   - Quotation.
   - Amount paid.
   - Payment details.
   - Payment proof.
   - Salesperson.
   - Date/time.
   - Previous workflow history.
7. Reception only performs the next required action.

The same principle applies throughout the company.

---

## 3.4 Workflow Must Be Live

The system should update continuously as users perform work.

Examples:

- Employee starts a task.
- Task becomes `In Progress`.
- Timer starts.
- Employee pauses task.
- Employee marks task `Blocked`.
- Employee enters blocker reason.
- Manager sees the blocker live.
- Employee completes task.
- Timer stops.
- Next task is automatically generated.
- Project timeline updates.
- Project cost updates if the task has a cost impact.

---

# 4. Key Roles and Departments

The following roles/departments are currently known.

## 4.1 Sales

Primary responsibilities:

- Client communication.
- Creating/handling project sales process.
- Preparing quotation through Odoo.
- Receiving payment confirmation from client.
- Locking quotation in Odoo after payment.
- Uploading payment/transfer proof.
- Creating Sample Request.
- Creating Modification Request when the client asks to modify a sample.
- Acting as the client-facing source for client requests.

Sales should **not** manually distribute project information to every department.

The system should distribute tasks automatically after Sales completes the required workflow action.

---

## 4.2 Reception

Reception has an important operational coordination role.

Responsibilities currently identified:

### Payment process
- Receive payment details automatically through a system task.
- Review/collect required payment information.
- Prepare the daily Journal.
- Complete the Journal task.
- Trigger automatic Accounting handover.

### Sample process
- Receive Sample Request from Sales through workflow.
- Handle the manager-approval step / ensure the request is approved.
- Forward/hand over the approved request to Workshop through the system workflow.
- Receive completed sample information back from Workshop.
- Register the Formula in the system based on the Workshop request/sample documentation.

### Modification process
- Receive Modification Request from Sales.
- Pass it to Workshop through the workflow.
- Register updated formula information once returned.

The system should eliminate unnecessary manual copying/re-sending of information.

---

## 4.3 Manager / Approver

A manager approval currently exists in the Sample Request workflow.

Current known flow:

`Sales → Reception → Manager Approval → Workshop`

The manager's exact authorization rules are still to be finalized.

The system should support:

- Approval task.
- Approve.
- Reject.
- Add note/comment.
- Timestamp.
- User identity.
- Optional rejection reason.
- Workflow branching after approval/rejection.

---

## 4.4 Workshop

The Workshop is physically located in the **Garden building within the company premises**.

The Workshop operates together with:

- Workshop area.
- Warehouse / Store.
- Tinting Room.

The Workshop performs more than only samples.

Known responsibilities include:

- Sample preparation.
- Production.
- Finishing.
- Preparation for execution/delivery.
- Coordination with Tinting.
- Handling material/product preparation.
- Providing sample/formula-related information back to Reception.

### Finish quality

The finish must be a **good finish**.

Finish quality is considered the responsibility of the Workshop collectively, rather than one isolated person.

---

## 4.5 Tinting

Tinting is responsible for the actual Formula-related technical work.

Known rule:

- Formula originates from Tinting / Workshop operations.
- Reception is responsible for registering the formula in the system after receiving the sample and request documentation from Workshop.

The system must distinguish between:

- **Formula creator / technical source:** Tinting / Workshop.
- **Formula recorder / system registrar:** Reception.

This distinction must be preserved for traceability.

---

## 4.6 Warehouse / Store

The Warehouse is associated with Workshop operations.

The system should eventually capture:

- Materials issued to project.
- Materials issued to sample.
- Quantity issued.
- Date/time.
- Responsible person.
- Project.
- Task.
- Return quantities if applicable.
- Actual material use where measurable.

Odoo Inventory should be used where suitable so material movement does not become a duplicated disconnected system.

---

## 4.7 Projects Team / Site Team

Known responsibilities:

- Site Visit.
- Site Inspection.
- Site readiness validation.
- Site report upload.
- Monitoring execution progress.
- Coordinating project/site work.
- Tracking site conditions.
- Tracking workers and working hours during execution.

---

## 4.8 Accounting

Known responsibilities:

- Receive Journal from Reception.
- Process Journal.
- Update Accounting task status.
- Continue accounting workflow inside Odoo as required.

Accounting should receive all relevant payment information automatically with the task.

---

## 4.9 Workers / Operational Employees

Workers may work in:

- Workshop.
- Tinting.
- Site.
- Production/execution.
- Other project-related operational areas.

Each worker/employee should be associated with:

- Project.
- Task.
- Start time.
- End time.
- Hours worked.
- Current status.
- Department/location.
- Daily project allocation.

---

# 5. Global Task Model

Every actionable workflow step should become a **Task**.

## 5.1 Minimum Task Fields

Each task should contain:

- Task ID.
- Project ID.
- Project name.
- Client.
- Workflow stage.
- Task type.
- Task title.
- Detailed instructions.
- Assigned department.
- Assigned role.
- Assigned user.
- Priority.
- Status.
- Created time.
- Assigned time.
- Planned start.
- Actual start.
- Due date/time.
- Actual completion time.
- Total active working time.
- Pause duration.
- Blocked duration.
- Blocker reason.
- Dependency tasks.
- Previous task.
- Next expected task.
- Required attachments.
- Attached documents.
- Required input fields.
- Output fields.
- Comments/updates.
- Audit history.
- Cost impact flag.
- Estimated cost if applicable.
- Actual cost if applicable.
- Odoo record/reference.

---

# 6. Task Status Model

Recommended status model:

- `Not Started`
- `Ready`
- `In Progress`
- `Paused`
- `Waiting`
- `Blocked`
- `Completed`
- `Cancelled`
- `Overdue`

## 6.1 Difference Between Waiting and Blocked

### Waiting
The task cannot start because it is legitimately waiting for another expected input.

Example:

- Waiting for client approval.
- Waiting for project stage to unlock.

### Blocked
There is an issue preventing progress.

Example:

- Site not ready.
- Missing client reference.
- Missing required material.
- Technical problem.
- Required document unavailable.

Blocked tasks should require:

- Blocker category.
- Description.
- Responsible party.
- Expected resolution date if known.
- Optional photo/document.
- Escalation.

---

# 7. Automatic Task Generation Logic

This is one of the most important requirements.

## 7.1 General Rule

When task A is completed:

1. Validate that mandatory fields are filled.
2. Validate that required attachments are present.
3. Validate business rules.
4. Mark task A completed.
5. Record completion timestamp.
6. Save output data.
7. Determine next workflow rule.
8. Generate next task(s).
9. Assign to responsible person(s).
10. Transfer all relevant information.
11. Notify assignees.
12. Update project current stage.
13. Add event to Live Activity Stream.
14. Recalculate progress.
15. Recalculate project cost if applicable.
16. Run AI/risk checks if applicable.

---

# 8. Sales → Payment → Reception → Accounting Workflow

This workflow has been specifically clarified and must be represented accurately.

## 8.1 Step 1 — Client Payment

The client pays according to the project's payment terms.

Payment percentages are generally fixed.

The contract is generally fixed.

Rare exceptions may exist.

The system should support standard defaults with exceptional overrides if authorized.

---

## 8.2 Step 2 — Sales Payment Confirmation Task

**Owner:** Salesperson

Sales performs:

- Confirm payment received.
- Lock quotation in Odoo.
- Upload payment/transfer proof.

Required system validation before completion:

- Quotation exists.
- Quotation is linked to correct project/client.
- Payment amount entered.
- Payment method entered if required.
- Payment proof uploaded.
- Quotation Lock completed / confirmed.

When Sales completes this task:

> **The system automatically sends all payment information to Reception by generating the next Reception task.**

No manual forwarding should be required.

---

## 8.3 Step 3 — Reception Payment Task

**Owner:** Reception

Reception receives an automatic task containing:

- Project.
- Client.
- Salesperson.
- Quotation details.
- Payment amount.
- Payment date.
- Payment type/method where available.
- Uploaded proof.
- Notes.
- Relevant Odoo references.

Reception reviews the information.

Reception should not need to re-enter data already available from Odoo/Sales unless there is a specific business reason.

On completion:

- Payment is marked ready for Journal process.
- System updates payment workflow status.

---

## 8.4 Step 4 — Daily Journal Task

**Owner:** Reception

Current business process:

- Reception prepares a Journal at the end of each day.

Desired automation:

- The system should automatically collect all payment items that reached the required Reception-completed status during the day.
- At the appropriate point, the system automatically creates/updates the Daily Journal task.
- Reception sees the list of included payments.
- Reception prepares/completes the Journal.
- Relevant Odoo reference is linked.

When completed:

> The system automatically creates the Accounting task.

---

## 8.5 Step 5 — Accounting Task

**Owner:** Accounting

The Accounting task automatically contains:

- Journal.
- Related project/payment records.
- Payment proofs.
- Sales information.
- Reception review details.
- Required Odoo references.

Accounting processes the Journal.

On completion:

- Accounting status updates.
- Workflow event is logged.
- Any next required accounting/project step is generated automatically.

---

# 9. Sample Request Workflow

## 9.1 Sample Request Initiation

**Owner:** Sales

Sales creates the Sample Request based on client requirements.

Known sample fields:

- Client.
- Project.
- Color.
- Texture.
- Reference.
- Size.
- Finish requirement.
- Sample notes.
- Requested date.
- Requested by.

### Field ownership

- **Color:** Client requirement.
- **Texture:** Client requirement.
- **Reference:** Client requirement.
- **Size:** Fixed / standard.
- **Formula:** Generated through Tinting.
- **Finish:** Workshop responsibility; should meet good-finish quality.

---

## 9.2 Sample Request Handover

Current known process:

`Sales → Reception → Manager Approval → Workshop`

Desired system process:

### Sales
Creates and submits Sample Request.

### System
Automatically creates Reception task.

### Reception
Reviews the request and ensures it enters manager approval.

### System
Creates manager approval task.

### Manager
Approves or rejects.

### If Approved
System automatically creates Workshop Sample task.

### If Rejected
System returns workflow to the appropriate responsible person with rejection reason.

---

# 10. Workshop Sample Workflow

When Workshop receives an approved Sample task, the task should include:

- Project.
- Client.
- Salesperson.
- Color.
- Texture.
- Reference.
- Size.
- Sample request number.
- Revision number.
- Required finish.
- Deadline.
- Attachments/photos/references.

Workshop starts the task.

System starts time tracking.

Workshop may need to coordinate with Tinting for Formula preparation.

---

# 11. Tinting / Formula Internal Workflow

## 11.1 Formula Creation

**Technical owner:** Tinting / Workshop

Tinting determines the Formula required for the requested sample.

The formula should be linked to:

- Project.
- Sample.
- Sample revision.
- Color.
- Texture.
- Reference.
- Formula version.
- Date.
- Technical responsible person.

---

## 11.2 Formula Registration

**System-registration owner:** Reception

After Workshop/Tinting completes the sample and returns the documentation:

- Reception receives a Formula Registration task.
- Reception records the Formula into the system.
- Formula remains traceable to sample and revision.

The system should preserve:

- Original formula.
- Revised formulas.
- Formula version history.
- Created by / technical source.
- Registered by.
- Registration date.
- Formula status.

---

# 12. Sample Completion & Approval Workflow

The primary approver of the sample is:

- The client, or
- The person who requested the sample.

There is no known multi-party approval requirement at this stage.

Recommended statuses:

- `Preparing`
- `Ready for Approval`
- `Approved`
- `Rejected`
- `Modification Requested`

If approved:

- Final approved sample version is recorded.
- Approved Formula is marked.
- Approval date recorded.
- Approver identity/name recorded.
- System triggers next relevant project task.

If rejected:

- Rejection comments are captured.
- Modification workflow begins.

---

# 13. Sample Modification / Revision Workflow

When the client asks for a change:

## Step 1
Sales creates a **Modification Request**.

## Step 2
System automatically creates Reception task.

## Step 3
Reception receives Modification Request.

## Step 4
System/Reception sends it into Workshop workflow.

## Step 5
Workshop performs the requested modification.

## Step 6
Tinting adjusts Formula if required.

## Step 7
Reception registers the new Formula/version.

## Step 8
New sample revision goes for approval.

The system must never overwrite previous sample history.

Example:

- Sample S-001 Revision 1
- Formula F-001-V1
- Rejected
- Client comment
- Sample S-001 Revision 2
- Formula F-001-V2
- Approved

This revision history is critical for:

- Accountability.
- Formula traceability.
- Sample cost calculation.
- Understanding repeated work.
- AI analysis.

---

# 14. Sample Cost Tracking

The system should calculate total sample cost before project execution.

For each sample/revision, potentially track:

- Materials used.
- Quantity used.
- Tinting materials.
- Workshop labor hours.
- Tinting labor hours.
- Finishing labor.
- Consumables.
- Transportation if sample movement causes transport expense.
- Other approved direct costs.

Project should display:

- Number of samples produced.
- Number of rejected samples.
- Number of revisions.
- Cost of each sample.
- Total sample-development cost.
- Cost before final approval.

This enables management to answer:

> “How much did we spend on samples before we even started execution?”

---

# 15. Site Visit Workflow

The company already uses a **Site Visit Report**.

The report consists of:

## Page 1
Survey / scanner / measurement report.

## Page 2
Site report containing the full site conditions.

Known required checks include:

- Surface readiness.
- Access.
- Lighting.
- Protection.
- Other trades completion.
- Dimensions.
- Moisture.
- Scaffolding.
- Electricity.
- Water.
- **Humidity.**
- Any other checklist fields already present in the existing Site Visit Report.

The digital system should reflect the existing report, not replace required information with a simplified generic form.

---

# 16. Site Readiness Workflow

Possible site states:

- `Inspection Pending`
- `Inspection In Progress`
- `Site Ready`
- `Site Not Ready`
- `Corrective Action Pending`
- `Reinspection Required`

## If Site Ready

- Site Visit task completes.
- Report is attached.
- Workflow can move to the next permitted stage.

## If Site Not Ready

The system should:

1. Record failed checklist items.
2. Require notes.
3. Allow photos/documents.
4. Mark project/site stage blocked.
5. Create corrective action task.
6. Assign responsible person.
7. Notify project responsible users.
8. Prevent dependent execution tasks from starting where appropriate.
9. Require reinspection if configured.

---

# 17. Workshop / Production Workflow

The Workshop performs production as well as samples.

The detailed production sequence still needs business validation, but the system should support internal production tasks such as:

- Material preparation.
- Tinting.
- Product production.
- Finishing.
- Quality check.
- Packing/preparation.
- Warehouse movement.
- Ready-for-site / ready-for-delivery state.

Each internal step should be configurable as a workflow task.

This workflow should be **configuration-driven**, because actual workshop sequences may differ by material/product/project.

---

# 18. Site Execution Workflow

Once the project reaches execution:

The system should track work **daily**.

Each day should be able to show:

- Project.
- Site.
- Date.
- Number of workers.
- Worker names.
- Team leader/supervisor.
- Individual tasks.
- Start time.
- End time.
- Working hours.
- Paused time.
- Blocked time.
- Output completed.
- Materials consumed.
- Transportation expenses.
- Other direct expenses.
- Site notes.
- Photos.
- Issues/blockers.

---

# 19. Employee Time Tracking

The system should track operational time at task level.

Recommended controls:

- `START`
- `PAUSE`
- `RESUME`
- `BLOCKED`
- `COMPLETE`

When `START` is pressed:

- Actual start timestamp is saved.
- Timer starts.
- Employee becomes “Working Now”.
- Project live dashboard updates.

When `PAUSE` is pressed:

- Pause timestamp saved.
- Active time stops accumulating.

When `BLOCKED` is selected:

- Active timer may pause depending on rule.
- User selects blocker category.
- User enters reason.
- Responsible person is notified.

When `COMPLETE` is selected:

- Active timer stops.
- Total working hours are calculated.
- Completion validation runs.
- Next task is generated.

---

# 20. Attendance vs Project Time

The system should distinguish between:

### Attendance Time
Employee is present at work.

### Project Task Time
Employee is actually working on a specific project/task.

These are not the same.

The main project-cost logic should use project/task time, not merely attendance.

Odoo Attendance/Timesheets may be used if suitable.

---

# 21. Live Management Dashboard

The management dashboard should behave like an operational control room.

Recommended top-level metrics:

- Active Projects.
- Projects in Workshop.
- Projects on Site.
- Samples In Progress.
- Samples Awaiting Approval.
- Sites Not Ready.
- Blocked Projects.
- Overdue Tasks.
- People Working Now.
- Workers on Site Today.
- Workshop Workers Today.
- Total Working Hours Today.
- Cost Alerts.
- Material Consumption Alerts.

---

# 22. Live Activity Stream

A company-wide chronological feed should show operational events.

Example events:

- 09:22 — Sales confirmed payment and locked quotation.
- 09:22 — Payment proof uploaded.
- 09:23 — Reception payment-review task created automatically.
- 09:40 — Reception completed payment review.
- 09:41 — Payment added to Daily Journal queue.
- 10:05 — Sample Request submitted.
- 10:06 — Manager approval task created.
- 10:20 — Sample approved.
- 10:21 — Workshop Sample task created.
- 10:28 — Workshop employee started sample.
- 11:10 — Formula created by Tinting.
- 12:00 — Site report uploaded.
- 12:01 — Site marked Not Ready.
- 12:02 — Corrective-action task generated.

Each event should include:

- Time.
- User/system actor.
- Project.
- Event type.
- Task.
- Before/after status where relevant.
- Link to record.

---

# 23. Project Detail Screen

One project screen should consolidate the entire operational picture.

Recommended sections:

## 23.1 Header
- Project name.
- Client.
- Odoo reference.
- Salesperson.
- Project responsible person.
- Current stage.
- Overall progress.
- Project status.
- Start date.
- Target date.
- Current risk indicator.

## 23.2 Live Workflow
Visual stage timeline.

Example:

`Sales → Payment → Sample → Approval → Site Inspection → Production → Execution → Delivery → Completed`

The exact sequence may vary by project.

## 23.3 Current Tasks
- Task.
- Owner.
- Status.
- Due date.
- Timer.
- Blocker.
- Next dependency.

## 23.4 People Working Now
- Employee.
- Department.
- Task.
- Start time.
- Elapsed time.

## 23.5 Samples
- Sample count.
- Revision count.
- Formula.
- Approval.
- Sample cost.

## 23.6 Site
- Site Visit status.
- Site readiness.
- Humidity.
- Checklist.
- Reports.
- Corrective actions.

## 23.7 Materials
- Technical quantity required.
- Warehouse issued.
- Actual consumed.
- Variance.
- Current material cost.

## 23.8 Costs
- Sample cost.
- Labor cost.
- Workshop cost.
- Material cost.
- Transportation cost.
- Site expenses.
- Other.
- Actual total.
- Forecast final.
- Planned cost.

## 23.9 Activity
Full project event feed.

## 23.10 AI Insights
- Current risks.
- Delay explanation.
- Cost anomalies.
- Material anomalies.
- Recommended action.

---

# 24. Employee “My Tasks” Screen

The employee experience must be extremely simple.

Employees should primarily see:

- Tasks assigned today.
- Overdue tasks.
- Ready tasks.
- Blocked tasks.
- Upcoming tasks if appropriate.

Task card should show:

- Project.
- Task.
- Location/department.
- Priority.
- Due date.
- Required information.
- Relevant attachments.
- Timer.
- Status buttons.

Main actions:

- Start.
- Pause.
- Resume.
- Block.
- Complete.
- Add update.
- Add photo/document.

Workers should not be forced to navigate complex project screens to update simple task progress.

---

# 25. Material Master & Technical Data Sheet Model

This is a critical requirement for cost and quantity accuracy.

Each material/product should have a structured technical record.

Recommended fields:

- Material ID.
- Odoo Product ID.
- Material name.
- Brand/manufacturer.
- Material category.
- Technical Data Sheet attachment.
- Technical Data Sheet version.
- Unit of measure.
- Packaging size.
- Coverage Rate.
- Consumption Rate.
- Required Quantity per m².
- Number of coats/layers.
- Mixing Ratio.
- Density where applicable.
- Recommended wet/dry thickness where applicable.
- Application method.
- Surface/substrate applicability.
- Wastage allowance.
- Unit cost.
- Currency.
- Effective cost date.
- Notes.
- Technical approval status.
- Data source.
- Last updated.
- Updated by.

The system should use approved technical values rather than arbitrary manual assumptions.

---

# 26. Quantity Calculation Engine

The quantity engine should use:

- Project measured area.
- Product/material technical rate.
- Layers/coats.
- Thickness if relevant.
- Mixing ratios.
- Wastage.
- Any applicable project-specific adjustment.

## Simple Example

Area:

`100 m²`

Consumption:

`1.5 kg/m²`

Base quantity:

`100 × 1.5 = 150 kg`

Wastage:

`10%`

Required quantity:

`150 × 1.10 = 165 kg`

If unit cost is available:

`Estimated Material Cost = 165 kg × Unit Cost`

The calculation should show its formula transparently.

---

# 27. Material Planned vs Actual Comparison

The system should compare:

### Planned
Technical Data Sheet expected quantity.

### Issued
Quantity issued from Warehouse.

### Actual
Actual quantity consumed where it can be measured.

### Variance
Difference between expected and actual.

Example:

| Metric | Quantity |
|---|---:|
| Technical expected | 150 kg |
| Expected incl. approved wastage | 165 kg |
| Warehouse issued | 175 kg |
| Actual consumed | 172 kg |
| Variance vs expected incl. wastage | +7 kg |

---

# 28. AI Material Consumption Analysis

If material consumption exceeds expected values, AI should investigate possible causes.

Potential causes to evaluate:

- Excessive wastage.
- Extra coats.
- Surface condition.
- Poor substrate readiness.
- Different application method.
- Incorrect thickness.
- Material variation.
- Measurement error.
- Quantity-entry error.
- Execution issue.
- Rework.
- Sample/revision waste.
- Material loss/return mismatch.

AI should not silently change technical rates.

It should:

- Detect.
- Explain.
- Recommend investigation.
- Request human confirmation where needed.

---

# 29. Project Cost Model

The exact company-approved cost model is **not yet finalized**.

The system should be designed to support it once management decides the accounting rules.

Potential cost categories include:

## Samples
- Sample materials.
- Tinting.
- Labor.
- Finishing.
- Consumables.
- Sample transportation.
- Sample revisions.

## Workshop
- Workshop labor hours.
- Tinting labor.
- Production labor.
- Material usage.
- Consumables.
- Other approved workshop direct costs.

## Site Execution
- Number of workers.
- Individual worker hours.
- Supervisor hours.
- Materials.
- Transportation.
- Site expenses.
- Rework.
- Other approved project expenses.

## Materials
- Technical planned cost.
- Actual issued cost.
- Actual consumed cost where available.

## Transportation
- Vehicle/trip.
- Driver.
- Fuel where tracked.
- External transport cost.
- Project/site destination.

## Other
- Approved direct project expenses.

---

# 30. Planned vs Actual vs Forecast Cost

Each project should support three major cost figures.

## Planned Cost

Expected cost before execution based on:

- Quantities.
- Technical material consumption.
- Planned labor.
- Planned transport.
- Approved project assumptions.

## Actual Cost

Costs actually accumulated to date.

## Forecast Final Cost

AI/analytics estimate of likely final cost based on:

- Current actual spend.
- Remaining scope.
- Material burn rate.
- Labor burn rate.
- Remaining tasks.
- Historical patterns where available.

Example:

- Planned Cost: EGP 200,000
- Actual Cost to Date: EGP 150,000
- Forecast Final Cost: EGP 225,000

The system should clearly flag forecast overruns.

---

# 31. Cost Timeline

Project cost should be visible chronologically.

Example:

- Sample #001 — EGP X
- Sample #002 Revision — EGP X
- Workshop Day 1 — EGP X
- Tinting — EGP X
- Site Day 1 — EGP X
- Site Day 2 — EGP X
- Transportation — EGP X
- Material Issue — EGP X
- Additional Rework — EGP X

Every cost event should ideally link to:

- Project.
- Task.
- Person.
- Date.
- Cost category.
- Source record.
- Supporting document.
- Odoo reference where applicable.

---

# 32. Labor Cost Calculation

The exact labor-cost method still needs management approval.

Potential approaches include:

- Standard hourly cost per role.
- Salary-derived hourly cost.
- Employee-specific hourly cost.
- Hybrid rates.

The system should be designed so rate methodology is configurable.

Important:

> The system already tracks hours; once management approves the rate model, labor cost can be calculated accurately from task time.

---

# 33. Transportation Cost Tracking

Transportation should be attributable to a project.

Potential fields:

- Project.
- Date.
- From.
- To.
- Purpose.
- Vehicle.
- Driver.
- Trip count.
- Fuel cost.
- External transport cost.
- Other expense.
- Receipt/photo.
- Responsible user.

The exact operational process needs confirmation.

---

# 34. Delivery

A final Delivery stage is part of the high-level project lifecycle.

However, the exact business definition of **Delivery** was not fully clarified in the discussion.

Therefore Claude should **not invent a detailed Delivery workflow yet**.

Design it as a configurable stage and mark the following as TBD:

- What exactly is being delivered?
- From Warehouse/Workshop to site or to client?
- Who schedules it?
- Who approves readiness?
- What documents are required?
- Is there a Delivery Note?
- Is client/contractor signature required?
- Does payment block delivery?
- Is transport part of Delivery?
- Does final handover happen after application/execution?

---

# 35. Workflow Variability

Not every project may follow exactly the same path.

The workflow engine should support configurable branches.

Examples:

### Project requiring a sample
`Sales → Payment → Sample → Approval → Site → Production → Execution → Delivery`

### Project without sample
`Sales → Payment → Site → Production → Execution → Delivery`

### Project requiring site inspection earlier
`Sales → Payment → Site → Sample → Approval → Production → Execution`

These examples are conceptual.

Final workflow templates should be approved by management.

---

# 36. Dependency Rules

Tasks should support dependencies.

Example:

Production cannot start unless:

- Required approval is completed.
- Site readiness is valid where required.
- Required materials are available.
- Required preceding tasks are complete.

A task may have:

- One predecessor.
- Multiple predecessors.
- Conditional predecessor.
- Blocking rule.
- Optional dependency.

---

# 37. Permissions & Access Model

The system should be role-based.

Example roles:

- Management.
- Sales.
- Reception.
- Accounting.
- Workshop Manager.
- Workshop Employee.
- Tinting.
- Warehouse.
- Project Manager.
- Site Engineer/Supervisor.
- Worker.
- Admin.

Permissions should control:

- View project.
- View financial data.
- View cost.
- Edit project.
- Create task.
- Reassign task.
- Approve.
- Edit Formula.
- Register Formula.
- Change technical Data Sheet.
- Override technical rate.
- Add expense.
- Approve expense.
- View AI insights.
- Execute AI actions.
- Modify workflow template.

Sensitive financial information should not automatically be visible to all workers.

---

# 38. Audit Trail

Every important change should be auditable.

Record:

- Who.
- What.
- When.
- Old value.
- New value.
- Project.
- Task.
- Source.
- Reason if required.

Important audit areas:

- Formula changes.
- Sample revisions.
- Approval.
- Payment proof.
- Cost changes.
- Technical Data Sheet values.
- Manual cost overrides.
- Task reassignment.
- Workflow override.
- Site readiness decision.

---

# 39. Notifications

Potential notification events:

- New task assigned.
- Task due soon.
- Task overdue.
- Task blocked.
- Blocker assigned.
- Approval required.
- Approval rejected.
- Sample ready.
- Sample rejected.
- Site not ready.
- Corrective action assigned.
- Material variance above threshold.
- Cost forecast exceeds plan.
- Journal ready.
- Accounting task assigned.
- Project stage changed.

Notification channels are TBD.

Could include:

- In-app.
- Email.
- Mobile push.
- WhatsApp, if later approved and technically integrated.

---

# 40. AI Assistant

The AI assistant should work over structured company/project data.

It is **not** merely a chatbot.

Its purpose is to understand workflow state and help users make decisions.

Example questions:

### Operational
- “What’s happening today?”
- “Which projects are blocked?”
- “What is happening in the Workshop now?”
- “Who is working on Project X?”
- “How many workers are on Project X today?”
- “Which tasks are overdue?”

### Project
- “Why is Project X delayed?”
- “What is the next step for Project X?”
- “What is blocking production?”
- “How many samples were made for Project X?”
- “Which formula is approved?”

### Cost
- “How much has Project X cost so far?”
- “What did we spend before execution?”
- “Which projects are above expected material consumption?”
- “Which project has the highest sample-development cost?”
- “What is the forecast final cost?”

### People
- “Who has overdue tasks?”
- “How many hours did the Workshop spend on Project X?”
- “Which project used the most labor this week?”

---

# 41. AI Actions / Agent Capabilities

Where permissions allow, AI may perform actions such as:

- Create task.
- Assign task.
- Send reminder.
- Summarize project.
- Generate management report.
- Explain blocker.
- Suggest next action.
- Flag abnormal cost.
- Flag abnormal material consumption.

High-impact actions should require permission and possibly confirmation.

AI should **not**:

- Edit approved formulas autonomously.
- Change Data Sheet consumption rates autonomously.
- Modify payment/accounting records without appropriate controls.
- Close important approval tasks without an authorized user.

---

# 42. AI Risk & Anomaly Examples

Possible AI-generated alerts:

> **Delay Risk**  
> Project X has been waiting for Sample Approval for 2 days.

> **Site Risk**  
> Production is planned to begin tomorrow, but Site Readiness is still Not Ready.

> **Material Variance**  
> Project X actual consumption is 8% above the technical expected consumption including approved wastage.

> **Sample Cost Risk**  
> Project X has required 4 sample revisions and sample-development cost is significantly above normal.

> **Labor Risk**  
> Project X used 18 extra labor hours compared with current plan.

> **Forecast Overrun**  
> Current material and labor burn rate indicates the project may finish above planned cost.

---

# 43. Design Requirements for the Live Stream

The live stream should not look like a generic social feed.

It should feel operational.

Each event should visually show:

- Time.
- Actor.
- Department.
- Project.
- Action.
- Result/status.
- Severity if relevant.
- Link.

Suggested event types:

- Informational.
- Success.
- Warning.
- Blocker.
- Cost Alert.
- Approval Required.
- Automation/System Event.

---

# 44. Suggested Main Navigation

Recommended initial navigation:

1. **Home / Live Control Room**
2. **Projects**
3. **My Tasks**
4. **Workshop**
5. **Site**
6. **Samples**
7. **Materials**
8. **Costs**
9. **People**
10. **AI Assistant**
11. **Reports**
12. **Admin / Workflow Configuration**

Navigation should adapt by role.

Workers should see a simplified interface.

---

# 45. Suggested Home / Control Room Layout

Top KPI row:

- Active Projects.
- People Working Now.
- Workshop Active Tasks.
- Site Active Tasks.
- Blocked Tasks.
- Overdue Tasks.
- Cost Alerts.

Main area:

### Left
Company Live Activity Stream.

### Center
Active project cards / project-stage map.

### Right
Alerts and priorities:
- Blockers.
- Approval requests.
- Cost anomalies.
- Material anomalies.

---

# 46. Suggested Project Card

Each project card can show:

- Project name.
- Client.
- Current stage.
- Progress %.
- Current owner/team.
- People working now.
- Today's hours.
- Blocker count.
- Next task.
- Actual cost.
- Forecast risk indicator.

---

# 47. Workshop Dashboard

Recommended workshop dashboard content:

- Samples today.
- Samples awaiting work.
- Samples awaiting formula.
- Active production tasks.
- Tinting tasks.
- Warehouse requests.
- Workers active now.
- Hours today.
- Blocked tasks.
- Ready for next stage.

Ability to filter:

- Project.
- Employee.
- Task type.
- Status.
- Date.

---

# 48. Site Dashboard

Recommended site dashboard content:

- Active sites.
- Sites awaiting inspection.
- Sites not ready.
- Reinspection required.
- Workers on each site.
- Current task.
- Hours today.
- Material consumption.
- Blockers.
- Photos/reports.
- Daily activity.

---

# 49. Samples Dashboard

Recommended:

- New requests.
- Awaiting manager approval.
- In Workshop.
- Awaiting formula registration.
- Ready for client approval.
- Approved.
- Rejected.
- Modification requested.

Each sample card:

- Project.
- Client.
- Color.
- Texture.
- Reference.
- Revision.
- Formula status.
- Current owner.
- Age/time in stage.
- Cost to date.

---

# 50. Material Dashboard

Recommended views:

- Material master.
- Data Sheet status.
- Technical rates.
- Unit costs.
- Project planned quantity.
- Issued quantity.
- Actual quantity.
- Variance.
- High-variance projects.

A material Data Sheet should be version controlled.

---

# 51. Cost Dashboard

Management should be able to view:

- Project Revenue / contract value where permission allows.
- Planned Project Cost.
- Actual Cost.
- Forecast Cost.
- Variance.
- Material Cost.
- Labor Cost.
- Sample Cost.
- Workshop Cost.
- Site Cost.
- Transportation.
- Other.
- Margin/profit if management chooses to enable this calculation.

Exact profit/margin rules require management approval.

---

# 52. Reports

Potential reports:

- Active Project Status Report.
- Daily Operations Report.
- Workshop Daily Report.
- Site Daily Report.
- Worker Hours Report.
- Project Labor Hours.
- Sample History Report.
- Sample Cost Report.
- Formula History.
- Site Readiness Report.
- Material Planned vs Actual.
- Material Variance Report.
- Project Actual Cost.
- Project Cost Forecast.
- Overdue Tasks.
- Blocker Aging.
- Workflow SLA.
- Department Performance.

---

# 53. Odoo Integration Expectations

The technical design should assume a bidirectional integration where authorized.

Potential integration areas:

- Contacts.
- Sales.
- Quotations.
- Quotation Lock state.
- Payment references.
- Accounting.
- Journals.
- Projects.
- Inventory.
- Products/materials.
- Employees.
- Attendance.
- Timesheets.

The actual Odoo version, hosting type and enabled modules are still TBD and must be confirmed before implementation architecture is finalized.

---

# 54. Integration Architecture Principle

Recommended conceptual architecture:

```text
┌──────────────────────────────┐
│      AI Assistant / Agents   │
│ Summaries • Risks • Actions  │
└──────────────┬───────────────┘
               │
┌──────────────▼───────────────┐
│     Live Workflow Engine     │
│ Tasks • Rules • Dependencies │
│ Timers • Events • Alerts     │
└──────────────┬───────────────┘
               │
┌──────────────▼───────────────┐
│             Odoo             │
│ Sales • Accounting • Stock   │
│ Projects • People • Records  │
└──────────────────────────────┘
```

---

# 55. Suggested Core Data Entities

Claude should consider an information architecture around these entities:

- Client.
- Project.
- Odoo Reference.
- Quotation.
- Payment.
- Journal.
- Workflow Template.
- Workflow Instance.
- Workflow Stage.
- Task.
- Task Dependency.
- Task Update.
- Task Timer.
- Employee.
- Role.
- Department.
- Sample.
- Sample Revision.
- Modification Request.
- Approval.
- Formula.
- Formula Version.
- Workshop Work Order.
- Site.
- Site Visit.
- Site Inspection Report.
- Site Checklist.
- Corrective Action.
- Material.
- Material Data Sheet.
- Material Technical Rate.
- Material Issue.
- Material Consumption.
- Transportation Record.
- Expense.
- Cost Entry.
- Cost Plan.
- Cost Forecast.
- Notification.
- Activity Event.
- AI Insight.
- Attachment.
- Audit Log.

---

# 56. Workflow Configuration Model

The system should not hardcode every sequence.

Admin/authorized management should eventually be able to configure:

- Workflow template.
- Stage.
- Task type.
- Responsible role.
- Required fields.
- Required attachments.
- Approval requirement.
- Due-time/SLA.
- Dependency.
- Trigger condition.
- Next task.
- Parallel task.
- Conditional branch.
- Escalation rule.
- Cost impact.
- Odoo action.
- Notification rule.

---

# 57. Parallel Tasks

Some work may happen in parallel.

The engine should support:

- Task A and Task B both start after Task X.
- Next stage waits for both.
- Next stage waits for either.
- Task B optional depending on condition.

Example:

After project handover:

- Site inspection.
- Workshop preparation.

These may potentially run in parallel if business rules allow.

Final rules need management confirmation.

---

# 58. SLAs & Overdue Logic

Every task type should optionally have a target completion time.

Example:

- Payment review: X hours.
- Manager approval: X hours.
- Sample preparation: X days.
- Formula registration: X hours.
- Site inspection: X days.

When SLA is exceeded:

- Task becomes Overdue.
- Owner notified.
- Supervisor notified after configured threshold.
- Management can view aging.

---

# 59. Search

Global search should find:

- Project.
- Client.
- Sample.
- Formula.
- Quotation.
- Task.
- Employee.
- Material.
- Site report.

Example:

Search `Project X`

Immediately show:

- Current status.
- current stage.
- active tasks.
- sample status.
- formula.
- site status.
- workers.
- cost.
- alerts.

---

# 60. Mobile Experience

Operational staff should be able to use the system easily on mobile.

Priority mobile actions:

- View My Tasks.
- Start/Pause/Complete.
- Mark Blocked.
- Add update.
- Upload photo.
- Upload document.
- View required instructions.
- Confirm receipt/handover.
- Fill Site Visit checklist.
- View project contact/location if permitted.

The mobile UI should minimize typing.

---

# 61. Data Quality Rules

The system should reduce duplicate/manual data.

Examples:

- Client comes from Odoo.
- Project comes from Odoo or synchronized project source.
- Quotation is linked, not retyped.
- Payment proof is linked to existing payment/project.
- Formula revision must reference Sample Revision.
- Material must reference approved material master.
- Unit cost should come from approved source where available.

---

# 62. Error / Exception Handling

The system must support exceptional cases.

Examples:

- Payment proof missing.
- Quotation cannot be locked.
- Odoo synchronization failed.
- Task assigned user is absent.
- Workshop cannot proceed.
- Sample reference missing.
- Formula incomplete.
- Site inspection failed.
- Material unavailable.
- Actual material exceeds issued material.
- Employee forgets to stop timer.
- Duplicate task trigger.

System should:

- Detect.
- Warn.
- Prevent invalid transition if critical.
- Allow authorized override.
- Record override reason.
- Maintain audit log.

---

# 63. What Must NOT Be Assumed Yet

The following items require management/business confirmation before detailed implementation:

- Exact Odoo version.
- Odoo hosting model.
- Exact modules currently enabled.
- Whether all workers have Odoo/user accounts.
- Exact labor hourly-cost methodology.
- Exact overhead allocation methodology.
- Exact Delivery workflow.
- Exact transportation workflow.
- Exact profit-margin calculation rules.
- Exact contract-payment percentages.
- Exact Project Team role mapping by person.
- Exact SLAs per task.
- Exact notification channels.
- Exact approval hierarchy beyond known sample manager/client approvals.
- Exact production steps for each product/material category.
- Exact material wastage rules per product.
- Exact method for recording actual consumed quantity.
- Exact project completion/closure criteria.

Claude should represent these as **TBD / configurable**, not invent them as finalized rules.

---

# 64. Initial Demo Scenario

The first interactive prototype should demonstrate one realistic project end-to-end.

## Demo Flow

1. Sales project exists in Odoo.
2. Sales receives payment.
3. Sales locks quotation.
4. Sales uploads payment proof.
5. Sales completes task.
6. Reception task appears automatically.
7. Reception completes payment review.
8. Daily Journal task receives the item.
9. Reception completes Journal.
10. Accounting task appears automatically.
11. Sample Request is created.
12. Reception receives it.
13. Manager approval is requested.
14. Manager approves.
15. Workshop receives Sample task.
16. Workshop employee presses Start.
17. Worker timer appears live.
18. Tinting creates Formula.
19. Sample is completed.
20. Reception receives Formula Registration task.
21. Reception registers Formula.
22. Sample goes to client/requester approval.
23. Client requests modification.
24. Sales creates Modification Request.
25. New revision begins automatically.
26. Revised sample is approved.
27. Site Visit task begins.
28. Site report is completed.
29. Site is marked Ready or Not Ready.
30. If Not Ready, corrective action is generated.
31. Once Ready, Production/Execution tasks begin.
32. Workers start project tasks.
33. Worker count and hours update live.
34. Materials are issued.
35. Technical quantity is compared with actual.
36. Transportation/expenses are added.
37. Live project cost updates.
38. AI identifies any blocker or cost anomaly.
39. Project proceeds toward Delivery.
40. Project is completed after final confirmed workflow.

---

# 65. Demo Screens to Design

Claude should design at minimum:

1. Login / role-based entry.
2. Company Live Control Room.
3. Projects list.
4. Project detail / live workflow.
5. My Tasks.
6. Task detail.
7. Payment handover workflow.
8. Reception Journal view.
9. Sample Request screen.
10. Manager approval.
11. Workshop dashboard.
12. Sample detail / revision history.
13. Formula registration.
14. Site Visit checklist/report.
15. Site readiness screen.
16. Daily site execution.
17. Workers/time tracking.
18. Material technical Data Sheet.
19. Material quantity calculator.
20. Material planned vs actual view.
21. Project Cost dashboard.
22. Cost timeline.
23. AI Assistant.
24. Alerts center.
25. Workflow configuration/admin screen.

---

# 66. UX Design Goal

The system should feel:

- Operational.
- Fast.
- Clear.
- Live.
- Connected.
- Accountability-driven.
- Easy for workers.
- Insight-rich for management.

Avoid designing it as a heavy ERP clone.

Odoo already performs the ERP function.

The new layer should make Odoo data **actionable, live and intelligent**.

---

# 67. Primary User Experience by Persona

## Management

Needs:

- Control room.
- Project status.
- Bottlenecks.
- People working.
- Cost.
- Forecasts.
- AI answers.
- Alerts.

## Sales

Needs:

- Project/client context.
- Payment confirmation.
- Sample request.
- Modification request.
- Client approval status.
- Their open tasks.

## Reception

Needs:

- Incoming automatic tasks.
- Payment review queue.
- Daily Journal.
- Sample request coordination.
- Formula registration.
- Clear handovers.

## Workshop

Needs:

- Work queue.
- Samples.
- Production.
- Start/stop task.
- Material info.
- Tinting dependencies.
- Deadlines.

## Tinting

Needs:

- Formula tasks.
- Sample references.
- Revision/version control.
- Technical record.

## Projects / Site Team

Needs:

- Site Visit.
- Checklist.
- Site readiness.
- Corrective actions.
- Daily execution.
- Workers.
- Hours.
- Materials.
- blockers.

## Accounting

Needs:

- Journal task queue.
- Related payment/project information.
- Attachments.
- Odoo references.

---

# 68. Success Criteria

The product should be considered successful if:

- A manager can know the exact current state of any project without asking multiple people.
- Every active task has a clear owner.
- The next responsible person receives their task automatically.
- Relevant information travels with the task automatically.
- No workflow step depends primarily on WhatsApp/manual verbal handover.
- Worker time can be attributed to projects/tasks.
- Sample history is fully traceable.
- Formula history is fully traceable.
- Site readiness blocks execution when required.
- Material quantities can be calculated from approved technical data.
- Planned material consumption can be compared with actual.
- Project cost can accumulate from sample stage to completion.
- AI can explain delays and highlight abnormal cost/consumption.
- Odoo remains the core business record system.

---

# 69. Product Positioning for Management

Do **not** position the proposal as:

> “We want to replace Odoo with a new system.”

Position it as:

> **“We want to add an Intelligent Live Workflow and Cost Intelligence Layer on top of our existing Odoo system.”**

Conceptually:

> **Odoo + Live Workflow + Automation + Cost Intelligence + AI = One Connected Company**

---

# 70. Instructions for Claude Opus

When designing the system:

1. Treat this document as the current source of truth for the concept.
2. Do not invent business rules marked TBD.
3. Use configurable workflow patterns where uncertainty exists.
4. Keep Odoo as the underlying system of record.
5. Prioritize live task orchestration over duplicating ERP screens.
6. Design for both management desktop use and simple mobile worker use.
7. Make the automatic task-handover behavior visible in the prototype.
8. Make project cost evolution visible in the workflow.
9. Make sample revisions and formulas traceable.
10. Make Data Sheet-based material calculation a first-class feature.
11. Make blocked states and reasons highly visible.
12. Make the project detail screen the central operational view.
13. Keep every action auditable.
14. Use realistic sample data in the prototype.
15. Clearly distinguish:
    - confirmed business rule,
    - proposed feature,
    - configurable logic,
    - TBD requirement.

---

# 71. Recommended Next Design Deliverables

Claude should produce, in order:

1. **Information Architecture**
2. **Role / Permission Matrix**
3. **Entity Relationship Model**
4. **Detailed Workflow Diagrams**
5. **Task State Machine**
6. **Project State Machine**
7. **Automatic Trigger Matrix**
8. **Odoo Integration Map**
9. **Screen Map**
10. **Low-Fidelity Wireframes**
11. **High-Fidelity Design System**
12. **Clickable Desktop Prototype**
13. **Mobile Worker Prototype**
14. **AI Assistant Interaction Design**
15. **Cost Intelligence Design**
16. **Exception / Error States**
17. **TBD Questions for Management**

---

# 72. Final Design Principle

The most important design idea is:

> **The user should never have to ask “Who needs to do the next step?”**

The system should always know:

- What happened.
- What must happen next.
- Who owns it.
- What information they need.
- When it is due.
- What blocks it.
- What it costs.
- What risk it creates.

That is the core purpose of the AI-Powered Live Project Workflow System.
