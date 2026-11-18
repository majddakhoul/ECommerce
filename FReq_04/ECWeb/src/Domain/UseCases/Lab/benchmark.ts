import type { BenchmarkSample } from "../../Repositories/BenchmarkRepository";

export interface BenchmarkStats {
  count: number;
  errors: number;
  averageMs: number;
  medianMs: number;
  p95Ms: number;
  minMs: number;
  maxMs: number;
}

const percentile = (sorted: number[], fraction: number): number => {
  if (sorted.length === 0) {
    return 0;
  }
  const index = Math.min(sorted.length - 1, Math.max(0, Math.ceil(fraction * sorted.length) - 1));
  return sorted[index];
};

export const summarize = (samples: BenchmarkSample[]): BenchmarkStats => {
  const successful = samples.filter((sample) => sample.ok).map((sample) => sample.milliseconds).sort((a, b) => a - b);
  const total = successful.reduce((sum, value) => sum + value, 0);

  return {
    count: samples.length,
    errors: samples.length - successful.length,
    averageMs: successful.length === 0 ? 0 : total / successful.length,
    medianMs: percentile(successful, 0.5),
    p95Ms: percentile(successful, 0.95),
    minMs: successful[0] ?? 0,
    maxMs: successful[successful.length - 1] ?? 0,
  };
};

export const improvement = (sync: BenchmarkStats, async: BenchmarkStats): number =>
  sync.averageMs === 0 ? 0 : ((sync.averageMs - async.averageMs) / sync.averageMs) * 100;
