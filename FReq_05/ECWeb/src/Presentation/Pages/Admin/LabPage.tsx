import { useState } from "react";
import { improvement, type BenchmarkStats } from "../../../Domain/UseCases/Lab/benchmark";
import { useBenchmark } from "../../../Domain/UseCases/Lab/useBenchmark";
import { useProducts } from "../../../Domain/UseCases/Products/useProducts";
import { Button } from "../../Components/Button";
import { Meter } from "../../Components/Charts";
import { SelectField, TextField } from "../../Components/Field";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";

function StatsCard({ title, stats, color }: { title: string; stats: BenchmarkStats; color: string }) {
  return (
    <section className="card stack">
      <div className="card-title" style={{ marginBottom: 0 }}>{title}</div>
      <div className="stat-line"><span className="muted">Requests</span><strong>{stats.count}</strong></div>
      <div className="stat-line"><span className="muted">Failed</span><strong>{stats.errors}</strong></div>
      <div className="stat-line"><span className="muted">Average</span><strong>{stats.averageMs.toFixed(0)} ms</strong></div>
      <div className="stat-line"><span className="muted">Median</span><strong>{stats.medianMs.toFixed(0)} ms</strong></div>
      <div className="stat-line"><span className="muted">95th percentile</span><strong>{stats.p95Ms.toFixed(0)} ms</strong></div>
      <div className="stat-line"><span className="muted">Slowest</span><strong>{stats.maxMs.toFixed(0)} ms</strong></div>
      <Meter value={stats.averageMs} max={Math.max(stats.averageMs, 1)} color={color} />
    </section>
  );
}

export function LabPage() {
  useDocumentTitle("Lab");
  const products = useProducts({ inStock: true, perPage: 100 });
  const { state, run, cancel } = useBenchmark();
  const [productId, setProductId] = useState("");
  const [iterations, setIterations] = useState("10");

  const selected = Number(productId || products.data?.items[0]?.id || 0);
  const running = state.phase === "running";
  const gain = state.sync.count > 0 && state.async.count > 0 ? improvement(state.sync, state.async) : null;

  return (
    <div className="stack" style={{ gap: 18 }}>
      <h1>Lab</h1>
      <p className="muted" style={{ maxWidth: 680 }}>
        Places the same order on the synchronous and the asynchronous API, alternating between them, and compares response times.
        Every request creates a real order for one unit of the selected product.
      </p>

      <div className="card stack">
        <div className="form-grid two">
          <SelectField label="Product" value={productId} onChange={(event) => setProductId(event.target.value)} disabled={running}>
            {(products.data?.items ?? []).map((product) => (
              <option key={product.id} value={product.id}>
                {product.name} ({product.stock} in stock)
              </option>
            ))}
          </SelectField>
          <TextField label="Orders per mode" type="number" min="1" max="50" value={iterations} onChange={(event) => setIterations(event.target.value)} disabled={running} />
        </div>
        <div className="row">
          {running ? (
            <Button variant="outline" small onClick={cancel}>
              Stop
            </Button>
          ) : (
            <Button variant="purple" small icon="lab" disabled={!selected} onClick={() => void run(selected, Math.min(50, Math.max(1, Number(iterations) || 1)))}>
              Run benchmark
            </Button>
          )}
          {state.total > 0 ? (
            <span className="muted">
              {state.completed} of {state.total} requests
            </span>
          ) : null}
        </div>
        {state.total > 0 ? <Meter value={state.completed} max={state.total} /> : null}
      </div>

      <div className="lab-grid">
        <StatsCard title="Synchronous" stats={state.sync} color="var(--warning)" />
        <StatsCard title="Asynchronous" stats={state.async} color="var(--success)" />
      </div>

      {gain !== null ? (
        <div className="card soft">
          <strong>
            {gain >= 0 ? `Asynchronous responds ${gain.toFixed(0)}% faster on average.` : `Asynchronous is ${Math.abs(gain).toFixed(0)}% slower on average.`}
          </strong>
        </div>
      ) : null}
    </div>
  );
}
