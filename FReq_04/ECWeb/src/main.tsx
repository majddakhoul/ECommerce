import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { App } from "./Presentation/App/App";
import "./Presentation/Styles/index.css";

createRoot(document.getElementById("root") as HTMLElement).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
