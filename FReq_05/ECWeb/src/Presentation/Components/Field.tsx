import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from "react";

interface FieldShellProps {
  label: string;
  error?: string;
  hint?: string;
  children: (id: string, invalid: boolean) => ReactNode;
}

function FieldShell({ label, error, hint, children }: FieldShellProps) {
  const id = useId();
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      {children(id, Boolean(error))}
      {error ? <span className="field-error">{error}</span> : hint ? <span className="field-hint">{hint}</span> : null}
    </div>
  );
}

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string;
  hint?: string;
}

export function TextField({ label, error, hint, className, ...rest }: TextFieldProps) {
  return (
    <FieldShell label={label} error={error} hint={hint}>
      {(id, invalid) => <input id={id} className={["input", invalid && "invalid", className].filter(Boolean).join(" ")} {...rest} />}
    </FieldShell>
  );
}

interface TextAreaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string;
  error?: string;
  hint?: string;
}

export function TextAreaField({ label, error, hint, className, ...rest }: TextAreaFieldProps) {
  return (
    <FieldShell label={label} error={error} hint={hint}>
      {(id, invalid) => <textarea id={id} className={["textarea", invalid && "invalid", className].filter(Boolean).join(" ")} {...rest} />}
    </FieldShell>
  );
}

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string;
  error?: string;
  hint?: string;
}

export function SelectField({ label, error, hint, className, children, ...rest }: SelectFieldProps) {
  return (
    <FieldShell label={label} error={error} hint={hint}>
      {(id, invalid) => (
        <select id={id} className={["select", invalid && "invalid", className].filter(Boolean).join(" ")} {...rest}>
          {children}
        </select>
      )}
    </FieldShell>
  );
}
