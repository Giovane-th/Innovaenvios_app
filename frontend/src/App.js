import "@/App.css";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Toaster } from "sonner";
import { SettingsProvider } from "@/context/SettingsContext";
import { AuthProvider } from "@/context/AuthContext";
import { Layout } from "@/components/Layout";
import ProtectedRoute from "@/components/ProtectedRoute";
import Dashboard from "@/pages/Dashboard";
import Frete from "@/pages/Frete";
import Rastreamento from "@/pages/Rastreamento";
import PrePostagem from "@/pages/PrePostagem";
import ListaPostagens from "@/pages/ListaPostagens";
import Contrato from "@/pages/Contrato";
import Login from "@/pages/Login";
import Cadastro from "@/pages/Cadastro";
import Aguardando from "@/pages/Aguardando";
import Usuarios from "@/pages/Usuarios";
import Contatos from "@/pages/Contatos";

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Toaster position="top-right" richColors closeButton />
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/cadastro" element={<Cadastro />} />
          <Route element={<ProtectedRoute allowPending />}>
            <Route path="/aguardando" element={<Aguardando />} />
          </Route>
          <Route element={<ProtectedRoute />}>
            <Route element={<SettingsProvider><Layout /></SettingsProvider>}>
              <Route path="/" element={<Dashboard />} />
              <Route path="/frete" element={<Frete />} />
              <Route path="/rastreamento" element={<Rastreamento />} />
              <Route path="/pre-postagem" element={<PrePostagem />} />
              <Route path="/postagens" element={<ListaPostagens />} />
              <Route path="/contatos" element={<Contatos />} />
              <Route element={<ProtectedRoute admin />}>
                <Route path="/contrato" element={<Contrato />} />
                <Route path="/usuarios" element={<Usuarios />} />
              </Route>
            </Route>
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
