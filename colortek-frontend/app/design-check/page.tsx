const statusPills = [
  { label: "Not started", className: "bg-neutral-500 text-white" },
  { label: "Ready", className: "bg-spectrum-indigo text-white" },
  { label: "In progress", className: "bg-status-info text-white" },
  { label: "Paused", className: "bg-spectrum-blue-bright text-white" },
  { label: "Waiting", className: "bg-status-pending text-white" },
  { label: "Blocked", className: "bg-status-danger text-white" },
  { label: "Completed", className: "bg-status-success text-white" },
  {
    label: "Cancelled",
    className: "bg-neutral-400 text-neutral-900 line-through",
  },
  { label: "Overdue", className: "bg-orange text-white" },
];

export default function DesignCheckPage() {
  return (
    <main className="min-h-full bg-neutral-50 p-8 flex flex-col gap-8">
      <h1 className="text-h1 text-neutral-900">Design system check</h1>

      <button className="w-fit bg-orange hover:bg-orange-dark text-white font-sans text-body font-medium rounded-button px-4 py-2 transition-colors">
        Primary action
      </button>

      <div className="flex flex-wrap gap-2">
        {statusPills.map((pill) => (
          <span
            key={pill.label}
            className={`${pill.className} rounded-button px-3 py-1 text-label uppercase tracking-wide`}
          >
            {pill.label}
          </span>
        ))}
      </div>
    </main>
  );
}
