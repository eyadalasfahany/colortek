#!/usr/bin/env bash
set -euo pipefail
cd /workspace
STASH='stash@{6}'

git checkout -f cursor/phase4-site-visit-4e6f
git reset --hard fab9f82

rm -f \
  colortek-frontend/src/components/site/site-visit-form.tsx \
  colortek-frontend/src/components/tasks/site-task-panels.tsx \
  colortek-frontend/src/lib/offlinePhotoQueue.ts \
  colortek-frontend/src/lib/siteVisitDraftStore.ts \
  colortek-frontend/src/services/siteVisitService.ts \
  colortek-frontend/src/types/siteVisit.ts

mkdir -p "colortek-frontend/src/app/(with-layouts)/site-visits/[id]/edit"
mkdir -p colortek-frontend/src/components/site

FILES=(
  colortek-api/app/Http/Controllers/Api/V1/TaskController.php
  colortek-api/app/Http/Resources/SiteChecklistItemResource.php
  colortek-api/app/Http/Resources/SiteMeasurementResource.php
  colortek-api/app/Http/Resources/SiteVisitAnswerResource.php
  colortek-api/app/Http/Resources/SiteVisitResource.php
  colortek-api/app/Http/Resources/TaskResource.php
  "colortek-frontend/src/app/(with-layouts)/site-visits/[id]/edit/page.tsx"
  colortek-frontend/src/components/site/site-visit-form.tsx
  colortek-frontend/src/components/tasks/site-task-panels.tsx
  colortek-frontend/src/components/tasks/task-detail-view.tsx
  colortek-frontend/src/lib/offlinePhotoQueue.ts
  colortek-frontend/src/lib/siteVisitDraftStore.ts
  colortek-frontend/src/services/siteVisitService.ts
  colortek-frontend/src/services/taskService.ts
  colortek-frontend/src/types/api.ts
  colortek-frontend/src/types/siteVisit.ts
  colortek-frontend/src/utils/task-codes.ts
)

for f in "${FILES[@]}"; do
  git show "$STASH:$f" > "$f"
done

cat > colortek-frontend/src/lib/queryKeys.ts <<'EOF'
export const queryKeys = {
  auth: { me: () => ["auth", "me"] as const },
  tasks: {
    all: () => ["tasks"] as const,
    list: (scope: "queue" | "my" | "all") => ["tasks", "list", scope] as const,
    detail: (id: number) => ["tasks", "detail", id] as const,
  },
  samples: {
    all: () => ["samples"] as const,
    list: (params?: Record<string, unknown>) => ["samples", "list", params] as const,
    detail: (reference: string) => ["samples", "detail", reference] as const,
    chain: (reference: string) => ["samples", "chain", reference] as const,
  },
  formulas: { list: (sampleReference: string) => ["formulas", "list", sampleReference] as const },
  employees: { list: () => ["employees", "list"] as const },
  siteVisits: {
    all: () => ["siteVisits"] as const,
    detail: (id: number) => ["siteVisits", "detail", id] as const,
    checklist: () => ["siteVisits", "checklist"] as const,
  },
  dashboard: {
    controlRoom: () => ["dashboard", "control-room"] as const,
    workshop: () => ["dashboard", "workshop"] as const,
    site: () => ["dashboard", "site"] as const,
    samples: () => ["dashboard", "samples"] as const,
  },
  projects: {
    list: (params?: Record<string, unknown>) => ["projects", "list", params] as const,
    detail: (reference: string) => ["projects", "detail", reference] as const,
    workflow: (id: number) => ["projects", "workflow", id] as const,
    activity: (id: number) => ["projects", "activity", id] as const,
  },
  notifications: {
    list: () => ["notifications", "list"] as const,
    unread: () => ["notifications", "unread"] as const,
  },
  activity: { feed: () => ["activity", "feed"] as const },
} as const;
EOF

rm -f colortek-api/database/migrations/2026_08_26_010000_create_site_checklist_items_table.php
python3 <<'PY'
from pathlib import Path
p = Path('colortek-api/database/migrations/2026_08_26_260001_create_site_tables.php')
text = p.read_text()
if "Schema::create('site_checklist_items'" not in text:
    block = """        Schema::create('site_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label_en', 250);
            $table->string('label_ar', 250);
            $table->string('answer_type', 20);
            $table->string('unit', 20)->nullable();
            $table->boolean('is_readiness_critical')->default(false);
            $table->boolean('allows_note')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

"""
    text = text.replace("        Schema::create('site_visits',", block + "        Schema::create('site_visits',", 1)
    p.write_text(text)
PY

cd colortek-api
php artisan test --filter=SiteVisitFlowTest
vendor/bin/pint --dirty
vendor/bin/pint --test

cd ../colortek-frontend
rm -rf .next
npm run build

cd ..
git add -A
git commit -m "feat(site): Plan 4 — site visit form, task panels, API resources

- Expand SiteVisitResource, TaskResource (site_block, subject context)
- Site visit form with offline draft and 3-step stepper
- Task detail panels for conduct/readiness/block/corrective/re-inspection
- Consolidate site_checklist_items migration; 14/14 SiteVisitFlowTest"

git push -u origin cursor/phase4-site-visit-4e6f --force-with-lease

echo "COMMIT=$(git rev-parse HEAD)"
echo "BRANCH=$(git branch --show-current)"
