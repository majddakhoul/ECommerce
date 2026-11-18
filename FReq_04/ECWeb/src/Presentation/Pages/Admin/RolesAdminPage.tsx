import { useState, type FormEvent } from "react";
import { toApiError } from "../../../Domain/Models/ApiError";
import type { Role } from "../../../Domain/Models/User";
import { isProtectedRole } from "../../../Domain/Rules/access";
import { useDeleteRole, useRoles, useSaveRole } from "../../../Domain/UseCases/Users/useUsers";
import { Button } from "../../Components/Button";
import { ErrorState, LoadingBlock } from "../../Components/Feedback";
import { TextField } from "../../Components/Field";
import { ConfirmDialog, Modal } from "../../Components/Modal";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";

function RoleForm({ role, onClose }: { role: Role | null; onClose: () => void }) {
  const save = useSaveRole();
  const [name, setName] = useState(role?.name ?? "");
  const error = save.error ? toApiError(save.error) : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    save.mutate({ id: role?.id, name: name.trim() }, { onSuccess: onClose });
  };

  return (
    <Modal
      title={role ? "Rename role" : "New role"}
      onClose={onClose}
      footer={
        <>
          <Button variant="outline" small onClick={onClose}>
            Cancel
          </Button>
          <Button variant="purple" small type="submit" form="role-form" loading={save.isPending} disabled={!name.trim()}>
            Save
          </Button>
        </>
      }
    >
      <form id="role-form" className="stack" onSubmit={submit} noValidate>
        {error && !error.isValidation ? <div className="alert">{error.message}</div> : null}
        <TextField label="Role name" value={name} onChange={(event) => setName(event.target.value)} error={error?.firstFieldError("name")} />
      </form>
    </Modal>
  );
}

export function RolesAdminPage() {
  useDocumentTitle("Roles");
  const roles = useRoles();
  const remove = useDeleteRole();
  const [editing, setEditing] = useState<Role | null | "new">(null);
  const [deleting, setDeleting] = useState<Role | null>(null);

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="page-header" style={{ marginBottom: 0 }}>
        <h1>Roles</h1>
        <Button variant="purple" small icon="plus" onClick={() => setEditing("new")}>
          New role
        </Button>
      </div>

      {roles.isPending ? <LoadingBlock /> : null}
      {roles.isError ? <ErrorState error={roles.error} onRetry={() => void roles.refetch()} /> : null}
      {roles.data ? (
        <div className="card">
          {roles.data.map((role) => {
            const locked = isProtectedRole(role.name);
            return (
              <div key={role.id} className="list-row">
                <span className="grow">
                  <strong>{role.name}</strong>
                  <br />
                  <span className="muted">{role.usersCount} users</span>
                </span>
                <Button variant="outline" small icon="edit" disabled={locked} onClick={() => setEditing(role)}>
                  Rename
                </Button>
                <Button variant="outline" small icon="trash" disabled={locked || role.usersCount > 0} onClick={() => setDeleting(role)}>
                  Delete
                </Button>
              </div>
            );
          })}
        </div>
      ) : null}

      {editing ? <RoleForm role={editing === "new" ? null : editing} onClose={() => setEditing(null)} /> : null}
      {deleting ? (
        <ConfirmDialog
          title={`Delete ${deleting.name}`}
          message="Only roles without users can be deleted."
          confirmLabel="Delete"
          loading={remove.isPending}
          onClose={() => setDeleting(null)}
          onConfirm={() => remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })}
        />
      ) : null}
    </div>
  );
}
