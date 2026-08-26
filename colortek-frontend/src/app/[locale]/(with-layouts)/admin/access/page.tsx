"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import { Alert } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { TableBody, TableCell, TableHead, TableHeader, TableRoot, TableRow } from "@/components/tailgrids/core/table";
import { TabRoot, TabContent, TabList, TabTrigger } from "@/components/tailgrids/core/tabs";
import { usePermissions } from "@/hooks/use-permissions";
import { queryKeys } from "@/lib/queryKeys";
import { getCoverageGaps, getEmployees, getRoles, getUsers } from "@/services/admin/adminService";
import { useQuery } from "@tanstack/react-query";

export default function AdminAccessPage() {
  const { can } = usePermissions();
  const showRoles = can("role.manage");
  const showUsers = can("user.manage");
  const showEmployees = can("employee.manage");

  const coverageQuery = useQuery({
    queryKey: queryKeys.admin.coverage(),
    queryFn: getCoverageGaps,
    enabled: can("role.manage"),
  });

  const rolesQuery = useQuery({ queryKey: queryKeys.admin.roles(), queryFn: getRoles, enabled: showRoles });
  const usersQuery = useQuery({ queryKey: queryKeys.admin.users(), queryFn: getUsers, enabled: showUsers });
  const employeesQuery = useQuery({ queryKey: queryKeys.admin.employees(), queryFn: getEmployees, enabled: showEmployees });

  const defaultTab = showRoles ? "roles" : showUsers ? "users" : "employees";

  return (
    <div className="space-y-6 pb-8">
      <AdminPageHeader
        title="Access management"
        description="Roles, users, and employees. Super admins manage roles; operational admins can edit user details only."
        trail={[
          { href: "/", label: "Home" },
          { href: "/admin/access", label: "Access" },
        ]}
      />

      <div className="px-2 lg:px-6 space-y-4">
        {(coverageQuery.data?.length ?? 0) > 0 ? (
          <Alert status="warning">
            Coverage gaps: {coverageQuery.data?.map((g) => g.description).join(" · ")}
          </Alert>
        ) : null}

        <TabRoot defaultValue={defaultTab}>
          <TabList>
            {showRoles ? <TabTrigger value="roles">Roles</TabTrigger> : null}
            {showUsers ? <TabTrigger value="users">Users</TabTrigger> : null}
            {showEmployees ? <TabTrigger value="employees">Employees</TabTrigger> : null}
          </TabList>

          {showRoles ? (
            <TabContent value="roles">
              <TableRoot>
                <TableHeader>
                  <TableRow>
                    <TableHead>Role</TableHead>
                    <TableHead>Permissions</TableHead>
                    <TableHead>Users</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(rolesQuery.data?.data ?? []).map((role) => (
                    <TableRow key={role.id}>
                      <TableCell>{role.name}{role.is_protected ? " (protected)" : ""}</TableCell>
                      <TableCell>{role.permissions_count}</TableCell>
                      <TableCell>{role.users_count}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </TableRoot>
            </TabContent>
          ) : null}

          {showUsers ? (
            <TabContent value="users">
              <TableRoot>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Roles</TableHead>
                    <TableHead>Active</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(usersQuery.data?.data ?? []).map((user) => (
                    <TableRow key={user.id}>
                      <TableCell>{user.name}</TableCell>
                      <TableCell>{user.email}</TableCell>
                      <TableCell>{user.roles?.join(", ") ?? "—"}</TableCell>
                      <TableCell>{user.active ? "Yes" : "No"}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </TableRoot>
            </TabContent>
          ) : null}

          {showEmployees ? (
            <TabContent value="employees">
              <TableRoot>
                <TableHeader>
                  <TableRow>
                    <TableHead>Code</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Active</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(employeesQuery.data?.data ?? []).map((emp) => (
                    <TableRow key={emp.id}>
                      <TableCell>{emp.code ?? "—"}</TableCell>
                      <TableCell>{emp.name}</TableCell>
                      <TableCell>{emp.active ? "Yes" : "No"}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </TableRoot>
            </TabContent>
          ) : null}
        </TabRoot>
      </div>
    </div>
  );
}
