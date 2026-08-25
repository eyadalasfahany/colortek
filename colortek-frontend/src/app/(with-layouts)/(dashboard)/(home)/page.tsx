"use client";

import { useAuth } from "@/context/auth-context";
import { usePermissions } from "@/hooks/use-permissions";
import ControlRoomView from "@/components/dashboards/control-room-view";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function HomePage() {
  const { can } = usePermissions();
  const { isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !can("project.view_all")) {
      router.replace("/my-tasks");
    }
  }, [can, isLoading, router]);

  if (isLoading) {
    return null;
  }

  if (!can("project.view_all")) {
    return null;
  }

  return <ControlRoomView />;
}
