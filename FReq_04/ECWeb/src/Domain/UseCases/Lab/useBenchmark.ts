import { useCallback, useRef, useState } from "react";
import type { ApiMode } from "../../Models/ApiMode";
import type { BenchmarkSample } from "../../Repositories/BenchmarkRepository";
import { useDependencies } from "../Dependencies";
import { summarize, type BenchmarkStats } from "./benchmark";

export type BenchmarkPhase = "idle" | "running" | "done" | "cancelled";

export interface BenchmarkState {
  phase: BenchmarkPhase;
  completed: number;
  total: number;
  samples: BenchmarkSample[];
  sync: BenchmarkStats;
  async: BenchmarkStats;
}

const empty = summarize([]);

export const useBenchmark = () => {
  const { benchmark } = useDependencies();
  const cancelled = useRef(false);
  const [state, setState] = useState<BenchmarkState>({ phase: "idle", completed: 0, total: 0, samples: [], sync: empty, async: empty });

  const run = useCallback(
    async (productId: number, iterations: number) => {
      cancelled.current = false;
      const samples: BenchmarkSample[] = [];
      const total = iterations * 2;
      setState({ phase: "running", completed: 0, total, samples: [], sync: empty, async: empty });

      for (let index = 0; index < iterations && !cancelled.current; index += 1) {
        const order: ApiMode[] = index % 2 === 0 ? ["sync", "async"] : ["async", "sync"];
        for (const mode of order) {
          if (cancelled.current) {
            break;
          }
          samples.push(await benchmark.placeOrder(mode, productId));
          setState({
            phase: "running",
            completed: samples.length,
            total,
            samples: [...samples],
            sync: summarize(samples.filter((sample) => sample.mode === "sync")),
            async: summarize(samples.filter((sample) => sample.mode === "async")),
          });
        }
      }

      setState((current) => ({ ...current, phase: cancelled.current ? "cancelled" : "done" }));
    },
    [benchmark],
  );

  const cancel = useCallback(() => {
    cancelled.current = true;
  }, []);

  return { state, run, cancel };
};
