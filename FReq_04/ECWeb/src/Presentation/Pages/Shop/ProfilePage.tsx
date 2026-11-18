import { useState, type FormEvent } from "react";
import { API_MODES, describeMode } from "../../../Domain/Models/ApiMode";
import { toApiError } from "../../../Domain/Models/ApiError";
import { useChangePassword, useUpdateProfile } from "../../../Domain/UseCases/Auth/useProfile";
import { useSession } from "../../../Domain/UseCases/Auth/useSession";
import { useDependencies } from "../../../Domain/UseCases/Dependencies";
import { switchMode } from "../../../Services/Config";
import { RoleBadge } from "../../Components/Badges";
import { Button } from "../../Components/Button";
import { TextField } from "../../Components/Field";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { capitalize, formatDateTime } from "../../format";

export function ProfilePage() {
  useDocumentTitle("Profile");
  const { user, logout } = useSession();
  const { mode } = useDependencies();
  const updateProfile = useUpdateProfile();
  const changePassword = useChangePassword();

  const [name, setName] = useState(user?.name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [passwords, setPasswords] = useState({ currentPassword: "", password: "", passwordConfirmation: "" });

  if (!user) {
    return null;
  }

  const profileError = updateProfile.error ? toApiError(updateProfile.error) : null;
  const passwordError = changePassword.error ? toApiError(changePassword.error) : null;

  const saveProfile = (event: FormEvent) => {
    event.preventDefault();
    updateProfile.mutate({ name, email });
  };

  const savePassword = (event: FormEvent) => {
    event.preventDefault();
    changePassword.mutate(passwords, {
      onSuccess: () => setPasswords({ currentPassword: "", password: "", passwordConfirmation: "" }),
    });
  };

  return (
    <div className="stack" style={{ gap: 20, maxWidth: 720 }}>
      <div className="row between wrap">
        <div className="stack" style={{ gap: 6 }}>
          <h1>{user.name}</h1>
          <div className="row wrap">
            {user.roles.map((role) => (
              <RoleBadge key={role} role={role} />
            ))}
            <span className="muted">Last sign-in {formatDateTime(user.lastLoginAt)}</span>
          </div>
        </div>
        <Button variant="outline" small onClick={() => void logout()}>
          Sign out
        </Button>
      </div>

      <form className="card stack" onSubmit={saveProfile}>
        <div className="card-title">Personal details</div>
        {profileError && !profileError.isValidation ? <div className="alert">{profileError.message}</div> : null}
        <div className="form-grid two">
          <TextField label="Name" value={name} onChange={(event) => setName(event.target.value)} error={profileError?.firstFieldError("name")} />
          <TextField label="Email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} error={profileError?.firstFieldError("email")} />
        </div>
        <div>
          <Button type="submit" variant="purple" small loading={updateProfile.isPending}>
            Save changes
          </Button>
        </div>
      </form>

      <form className="card stack" onSubmit={savePassword}>
        <div className="card-title">Change password</div>
        {passwordError && !passwordError.isValidation ? <div className="alert">{passwordError.message}</div> : null}
        <TextField label="Current password" type="password" autoComplete="current-password" value={passwords.currentPassword} onChange={(event) => setPasswords({ ...passwords, currentPassword: event.target.value })} error={passwordError?.firstFieldError("current_password")} />
        <div className="form-grid two">
          <TextField label="New password" type="password" autoComplete="new-password" value={passwords.password} onChange={(event) => setPasswords({ ...passwords, password: event.target.value })} error={passwordError?.firstFieldError("password")} />
          <TextField label="Confirm new password" type="password" autoComplete="new-password" value={passwords.passwordConfirmation} onChange={(event) => setPasswords({ ...passwords, passwordConfirmation: event.target.value })} />
        </div>
        <div>
          <Button type="submit" variant="purple" small loading={changePassword.isPending} disabled={!passwords.currentPassword || !passwords.password}>
            Update password
          </Button>
        </div>
      </form>

      <section className="card soft stack">
        <div className="card-title" style={{ marginBottom: 0 }}>Processing mode</div>
        <p className="muted">{describeMode(mode)}. Switching reloads the app and sends every request to the other API path.</p>
        <div className="mode-switch" role="group" aria-label="Processing mode">
          {API_MODES.map((entry) => (
            <button key={entry} className={entry === mode ? "active" : ""} onClick={() => entry !== mode && switchMode(entry)}>
              {capitalize(entry)}
            </button>
          ))}
        </div>
      </section>
    </div>
  );
}
