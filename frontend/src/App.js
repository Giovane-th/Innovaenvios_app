import "@/App.css";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Toaster } from "sonner";
import { SettingsProvider } from "@/context/SettingsContext";
import { Layout } from "@/components/Layout";
import Dashboard from "@/pages/Dashboard";
import Frete from "@/pages/Frete";
import Rastreamento from "@/pages/Rastreamento";
import PrePostagem from "@/pages/PrePostagem";
import ListaPostagens from "@/pages/ListaPostagens";
import Contrato from "@/pages/Contrato";

function App() {
  return (
    <SettingsProvider>
      <BrowserRouter>
        <Toaster position="top-right" richColors closeButton />
        <Routes>
          <Route element={<Layout />}>
            <Route path="/" element={<Dashboard />} />
            <Route path="/frete" element={<Frete />} />
            <Route path="/rastreamento" element={<Rastreamento />} />
            <Route path="/pre-postagem" element={<PrePostagem />} />
            <Route path="/postagens" element={<ListaPostagens />} />
            <Route path="/contrato" element={<Contrato />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </SettingsProvider>
  );
}

export default App;
