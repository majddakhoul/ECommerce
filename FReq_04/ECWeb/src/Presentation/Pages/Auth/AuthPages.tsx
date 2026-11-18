import { useState, type FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { toApiError } from "../../../Domain/Models/ApiError";
import { useSession } from "../../../Domain/UseCases/Auth/useSession";
import { Button } from "../../Components/Button";
import { TextField } from "../../Components/Field";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";

export function LoginPage() {
  useDocumentTitle("Sign in");
  const { login } = useSession();
  const navigate = useNavigate();
  const location = useLocation();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<ReturnType<typeof toApiError> | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await login({ email, password });
      navigate((location.state as { from?: string } | null)?.from ?? "/", { replace: true });
    } catch (failure) {
      setError(toApiError(failure));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form className="stack" onSubmit={submit} noValidate>
      <h1>Welcome back</h1>
      <p className="muted">Sign in to continue shopping.</p>
      {error ? <div className="alert">{error.message}</div> : null}
      <TextField label="Email" type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} error={error?.firstFieldError("email")} />
      <TextField label="Password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} error={error?.firstFieldError("password")} />
      <Button type="submit" block loading={busy} disabled={!email || !password}>
        Sign in
      </Button>
      <p className="muted" style={{ textAlign: "center" }}>
        New here? <Link to="/register" style={{ color: "var(--accent)", fontWeight: 700 }}>Create an account</Link>
      </p>
    </form>
  );
}

export function RegisterPage() {
  useDocumentTitle("Create account");
  const { register } = useSession();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: "", email: "", password: "", passwordConfirmation: "" });
  const [error, setError] = useState<ReturnType<typeof toApiError> | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (key: keyof typeof form) => (event: { target: { value: string } }) => setForm((current) => ({ ...current, [key]: event.target.value }));

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await register(form);
      navigate("/", { replace: true });
    } catch (failure) {
      setError(toApiError(failure));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form className="stack" onSubmit={submit} noValidate>
      <h1>Create account</h1>
      <p className="muted">It takes less than a minute.</p>
      {error && !error.isValidation ? <div className="alert">{error.message}</div> : null}
      <TextField label="Full name" autoComplete="name" value={form.name} onChange={set("name")} error={error?.firstFieldError("name")} />
      <TextField label="Email" type="email" autoComplete="email" value={form.email} onChange={set("email")} error={error?.firstFieldError("email")} />
      <TextField label="Password" type="password" autoComplete="new-password" value={form.password} onChange={set("password")} error={error?.firstFieldError("password")} hint="At least 8 characters with letters and numbers." />
      <TextField label="Confirm password" type="password" autoComplete="new-password" value={form.passwordConfirmation} onChange={set("passwordConfirmation")} />
      <Button type="submit" block loading={busy} disabled={!form.name || !form.email || !form.password}>
        Create account
      </Button>
      <p className="muted" style={{ textAlign: "center" }}>
        Already registered? <Link to="/login" style={{ color: "var(--accent)", fontWeight: 700 }}>Sign in</Link>
      </p>
    </form>
  );
}
