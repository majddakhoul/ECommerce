import { useState, type FormEvent } from "react";
import { toApiError } from "../../../Domain/Models/ApiError";
import type { User } from "../../../Domain/Models/User";
import { useSession } from "../../../Domain/UseCases/Auth/useSession";
import {
  useAssignRole,
  useCreateUser,
  useDeleteUser,
  useRemoveRole,
  useRoles,
  useUnlockUser,
  useUpdateUser,
  useUsers,
} from "../../../Domain/UseCases/Users/useUsers";
import { ActiveBadge, RoleBadge } from "../../Components/Badges";
import { Button } from "../../Components/Button";
import { EmptyState, ErrorState, LoadingBlock } from "../../Components/Feedback";
import { SelectField, TextField } from "../../Components/Field";
import { ConfirmDialog, Modal } from "../../Components/Modal";
import { Pagination } from "../../Components/Pagination";
import { useDebounce } from "../../Hooks/useDebounce";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { formatDateTime } from "../../format";

function UserForm({ user, onClose }: { user: User | null; onClose: () => void }) {
  const roles = useRoles();
  const create = useCreateUser();
  const update = useUpdateUser();
  const [form, setForm] = useState({
    name: user?.name ?? "",
    email: user?.email ?? "",
    password: "",
    role: user?.roles[0] ?? "user",
    isActive: user?.isActive ?? true,
  });
  const mutation = user ? update : create;
  const error = mutation.error ? toApiError(mutation.error) : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (user) {
      update.mutate(
        { id: user.id, changes: { name: form.name, email: form.email, isActive: form.isActive, ...(form.password ? { password: form.password } : {}) } },
        { onSuccess: onClose },
      );
    } else {
      create.mutate({ name: form.name, email: form.email, password: form.password, role: form.role, isActive: form.isActive }, { onSuccess: onClose });
    }
  };

  return (
    <Modal
      title={user ? `Edit ${user.name}` : "New user"}
      onClose={onClose}
      footer={
        <>
          <Button variant="outline" small onClick={onClose}>
            Cancel
          </Button>
          <Button variant="purple" small type="submit" form="user-form" loading={mutation.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="user-form" className="stack" onSubmit={submit} noValidate>
        {error && !error.isValidation ? <div className="alert">{error.message}</div> : null}
        <TextField label="Name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} error={error?.firstFieldError("name")} />
        <TextField label="Email" type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} error={error?.firstFieldError("email")} />
        <TextField
          label={user ? "New password" : "Password"}
          type="password"
          autoComplete="new-password"
          value={form.password}
          onChange={(event) => setForm({ ...form, password: event.target.value })}
          error={error?.firstFieldError("password")}
          hint={user ? "Leave empty to keep the current password." : undefined}
        />
        {!user ? (
          <SelectField label="Role" value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>
            {(roles.data ?? []).map((role) => (
              <option key={role.id} value={role.name}>
                {role.name}
              </option>
            ))}
          </SelectField>
        ) : null}
        <label className="row">
          <input type="checkbox" checked={form.isActive} onChange={(event) => setForm({ ...form, isActive: event.target.checked })} />
          <span>Active account</span>
        </label>
      </form>
    </Modal>
  );
}

function RolesDialog({ userId, onClose }: { userId: number; onClose: () => void }) {
  const users = useUsers({ perPage: 100 });
  const roles = useRoles();
  const assign = useAssignRole();
  const remove = useRemoveRole();
  const user = users.data?.items.find((entry) => entry.id === userId);

  return (
    <Modal title={`Roles for ${user?.name ?? ""}`} onClose={onClose} footer={<Button variant="outline" small onClick={onClose}>Close</Button>}>
      <div className="stack" style={{ gap: 10 }}>
        {(roles.data ?? []).map((role) => {
          const has = user?.roles.includes(role.name) ?? false;
          return (
            <div key={role.id} className="list-row">
              <span className="grow">
                <RoleBadge role={role.name} />
              </span>
              <Button
                variant={has ? "outline" : "purple"}
                small
                disabled={assign.isPending || remove.isPending}
                onClick={() => (has ? remove.mutate({ userId, roleId: role.id }) : assign.mutate({ userId, roleId: role.id }))}
              >
                {has ? "Remove" : "Assign"}
              </Button>
            </div>
          );
        })}
      </div>
    </Modal>
  );
}

export function UsersAdminPage() {
  useDocumentTitle("Users");
  const { user: me } = useSession();
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<User | null | "new">(null);
  const [rolesFor, setRolesFor] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<User | null>(null);
  const debounced = useDebounce(search);
  const roles = useRoles();
  const users = useUsers({ search: debounced || undefined, role: role || undefined, page, perPage: 10 });
  const unlock = useUnlockUser();
  const remove = useDeleteUser();

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="page-header" style={{ marginBottom: 0 }}>
        <h1>Users</h1>
        <Button variant="purple" small icon="plus" onClick={() => setEditing("new")}>
          New user
        </Button>
      </div>
      <div className="filters">
        <input className="input" type="search" placeholder="Search users" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} aria-label="Search users" />
        <select className="select" value={role} onChange={(event) => { setRole(event.target.value); setPage(1); }} aria-label="Filter by role">
          <option value="">All roles</option>
          {(roles.data ?? []).map((entry) => (
            <option key={entry.id} value={entry.name}>
              {entry.name}
            </option>
          ))}
        </select>
      </div>

      {users.isPending ? <LoadingBlock /> : null}
      {users.isError ? <ErrorState error={users.error} onRetry={() => void users.refetch()} /> : null}
      {users.data && users.data.items.length === 0 ? <EmptyState title="No users found" /> : null}
      {users.data && users.data.items.length > 0 ? (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                <th>User</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last sign-in</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {users.data.items.map((entry) => (
                <tr key={entry.id}>
                  <td>
                    <strong>{entry.name}</strong>
                    <br />
                    <span className="muted">{entry.email}</span>
                  </td>
                  <td>
                    <div className="row wrap">
                      {entry.roles.map((name) => (
                        <RoleBadge key={name} role={name} />
                      ))}
                    </div>
                  </td>
                  <td>
                    <ActiveBadge active={entry.isActive} locked={entry.isLocked} />
                  </td>
                  <td>{formatDateTime(entry.lastLoginAt)}</td>
                  <td className="actions">
                    {entry.isLocked ? (
                      <Button variant="outline" small icon="lock" disabled={unlock.isPending} onClick={() => unlock.mutate(entry.id)}>
                        Unlock
                      </Button>
                    ) : null}
                    <Button variant="outline" small icon="shield" onClick={() => setRolesFor(entry.id)}>
                      Roles
                    </Button>
                    <Button variant="outline" small icon="edit" onClick={() => setEditing(entry)}>
                      Edit
                    </Button>
                    <Button variant="outline" small icon="trash" disabled={entry.id === me?.id} onClick={() => setDeleting(entry)}>
                      Delete
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      <Pagination page={users.data} onChange={setPage} />

      {editing ? <UserForm user={editing === "new" ? null : editing} onClose={() => setEditing(null)} /> : null}
      {rolesFor !== null ? <RolesDialog userId={rolesFor} onClose={() => setRolesFor(null)} /> : null}
      {deleting ? (
        <ConfirmDialog
          title={`Delete ${deleting.name}`}
          message="The account and its sessions are removed."
          confirmLabel="Delete"
          loading={remove.isPending}
          onClose={() => setDeleting(null)}
          onConfirm={() => remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })}
        />
      ) : null}
    </div>
  );
}
