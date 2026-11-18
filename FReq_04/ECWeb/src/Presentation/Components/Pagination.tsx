import type { Page } from "../../Domain/Models/Pagination";
import { Button } from "./Button";

interface PaginationProps {
  page: Page<unknown> | undefined;
  onChange: (page: number) => void;
}

export function Pagination({ page, onChange }: PaginationProps) {
  if (!page || page.lastPage <= 1) {
    return null;
  }
  return (
    <nav className="pagination" aria-label="Pagination">
      <Button variant="outline" small disabled={page.page <= 1} onClick={() => onChange(page.page - 1)}>
        Previous
      </Button>
      <span className="muted">
        Page {page.page} of {page.lastPage}
      </span>
      <Button variant="outline" small disabled={page.page >= page.lastPage} onClick={() => onChange(page.page + 1)}>
        Next
      </Button>
    </nav>
  );
}
