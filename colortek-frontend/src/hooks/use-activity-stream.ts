"use client";

import { fetchActivity } from "@/services/activityService";
import { getToken } from "@/lib/auth-token";
import { mergeActivityEvents, subscribeActivity, getActivityEvents, resetActivityStore } from "@/lib/activity-store";
import { useCallback, useEffect, useState } from "react";

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1";

export function useActivityStream(enabled = true) {
  const [events, setEvents] = useState(getActivityEvents());
  const [connected, setConnected] = useState(true);

  const refresh = useCallback(async () => {
    const latest = getActivityEvents();
    const since = latest.length ? Math.max(...latest.map((e) => e.id)) : undefined;
    const rows = await fetchActivity(since);
    mergeActivityEvents(rows);
  }, []);

  useEffect(() => {
    if (!enabled) return;
    resetActivityStore();
    void refresh().catch(() => undefined);
    return subscribeActivity(() => setEvents(getActivityEvents()));
  }, [enabled, refresh]);

  useEffect(() => {
    if (!enabled) return;
    const token = getToken();
    if (!token) return;

    let source: EventSource | null = null;
    let pollTimer: ReturnType<typeof setInterval> | null = null;

    const startPoll = () => {
      if (pollTimer) return;
      pollTimer = setInterval(() => {
        void refresh().catch(() => undefined);
      }, 15000);
    };

    const stopPoll = () => {
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = null;
    };

    try {
      source = new EventSource(`${API_BASE}/stream?token=${encodeURIComponent(token)}`);
      source.addEventListener("activity", (msg) => {
        try {
          const data = JSON.parse((msg as MessageEvent).data);
          mergeActivityEvents([data]);
          setConnected(true);
          stopPoll();
        } catch {
          /* ignore */
        }
      });
      source.onerror = () => {
        setConnected(false);
        source?.close();
        startPoll();
      };
    } catch {
      setConnected(false);
      startPoll();
    }

    return () => {
      source?.close();
      stopPoll();
    };
  }, [enabled, refresh]);

  return { events, connected };
}
