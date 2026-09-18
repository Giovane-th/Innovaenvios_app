import { useState } from "react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import {
  LayoutDashboard, Calculator, Search, FilePlus2, PackageCheck, KeyRound,
  Truck, Moon, Sun, Menu, X, Zap,
  LogOut, UserCircle, Users,
} from "lucide-react";
import { NAV } from "@/constants/testIds";
import { useSettings } from "@/context/SettingsContext";
import { useAuth } from "@/context/AuthContext";

const links = [
  { to: "/", label: "Visão Geral", icon: LayoutDashboard, tid: NAV.dashboard, end: true },
  { to: "/frete", label: "Cálculo de Frete", icon: Calculator, tid: NAV.calculator },
  { to: "/rastreamento", label: "Rastreamento", icon: Search, tid: NAV.tracking },
  { to: "/pre-postagem", label: "Nova Pré-Postagem", icon: FilePlus2, tid: NAV.prepostNew },
  { to: "/postagens", label: "Pré-Postagens", icon: PackageCheck, tid: NAV.prepostList },
  { to: "/contrato", label: "Integração Contrato CWS", icon: KeyRound, tid: NAV.contract },
  { to: "/usuarios", label: "Usuários", icon: Users },
];

const ConnectionPill = ({ settings }) => {
  const conectado = settings?.conectado;
  return (
    <div
      data-testid="connection-status-pill"
      className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold ${
        conectado
          ? "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
          : "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800"
      }`}
    >
      <span className={`h-2 w-2 rounded-full ${conectado ? "bg-emerald-500" : "bg-amber-500"} animate-pulse`} />
      {conectado ? "Contrato Conectado" : "Modo Demonstração"}
    </div>
  );
};

export const Layout = () => {
  const { settings } = useSettings();
  const { user, logout } = useAuth();
  const [dark, setDark] = useState(false);
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  const toggleDark = () => {
    const next = !dark;
    setDark(next);
    document.documentElement.classList.toggle("dark", next);
  };

  const ambiente = settings?.ambiente === "producao" ? "Produção" : "Homologação";

  return (
    <div className="App min-h-screen bg-background">
      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 w-72 transform bg-slate-950 text-slate-200 transition-transform duration-300 md:translate-x-0 ${
          open ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="flex items-center justify-between border-b border-slate-800 px-6 py-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600">
              <Truck className="h-6 w-6 text-white" />
            </div>
            <div>
              <p className="text-base font-extrabold tracking-tight text-white">InnovaEnvios</p>
              <p className="text-[11px] font-medium text-blue-400">Parceiro Correios CWS</p>
            </div>
          </div>
          <button className="md:hidden" onClick={() => setOpen(false)}><X className="h-5 w-5" /></button>
        </div>
        <nav className="flex flex-col gap-1 p-4">
          {links.filter((l) => !["/contrato", "/usuarios"].includes(l.to) || user?.role === "admin").map((l) => (
            <NavLink
              key={l.to}
              to={l.to}
              end={l.end}
              data-testid={l.tid}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                  isActive
                    ? "bg-blue-600 text-white shadow-lg shadow-blue-600/20"
                    : "text-slate-400 hover:bg-slate-800 hover:text-white"
                }`
              }
            >
              <l.icon className="h-[18px] w-[18px]" />
              {l.label}
            </NavLink>
          ))}
        </nav>
        <div className="absolute bottom-4 left-4 right-4 rounded-xl border border-slate-800 bg-slate-900 p-4">
          <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Ambiente API</p>
          <p className="mt-1 text-sm font-bold text-white">{ambiente}</p>
          <p className="mt-2 font-mono text-[10px] text-slate-500">
            {settings?.ambiente === "producao" ? "api.correios.com.br" : "apihom.correios.com.br"}
          </p>
        </div>
      </aside>

      {/* Main */}
      <div className="md:pl-72">
        <header className="sticky top-0 z-40 flex items-center justify-between border-b border-border bg-white/90 px-4 py-3.5 backdrop-blur-md dark:bg-slate-900/90 sm:px-6">
          <div className="flex items-center gap-3">
            <button className="md:hidden" onClick={() => setOpen(true)}><Menu className="h-6 w-6" /></button>
            <ConnectionPill settings={settings} />
          </div>
          <div className="flex items-center gap-2">
            <button
              data-testid="quick-emit-button"
              onClick={() => navigate("/pre-postagem")}
              className="hidden items-center gap-2 rounded-full bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 transition-colors hover:bg-amber-400 sm:inline-flex"
            >
              <Zap className="h-4 w-4" /> Emitir Etiqueta
            </button>
            <button
              onClick={toggleDark}
              data-testid="theme-toggle"
              className="rounded-full border border-border p-2 text-muted-foreground transition-colors hover:text-foreground"
            >
              {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>
            <div className="hidden items-center gap-2 pl-2 sm:flex">
              {user?.foto ? <img src={user.foto} alt="" className="h-8 w-8 rounded-full" referrerPolicy="no-referrer" /> : <UserCircle className="h-8 w-8 text-slate-400" />}
              <div className="max-w-32 leading-tight"><p className="truncate text-xs font-bold">{user?.nome}</p><p className="truncate text-[10px] text-muted-foreground">{user?.role === "admin" ? "Administrador" : "Cliente"}</p></div>
            </div>
            <button
              onClick={() => { logout(); navigate("/login"); }}
              title="Sair"
              className="rounded-full border border-border p-2 text-muted-foreground transition-colors hover:text-rose-600"
            ><LogOut className="h-4 w-4" /></button>
          </div>
        </header>
        <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
          <Outlet />
        </main>
      </div>

      {open && <div className="fixed inset-0 z-40 bg-black/50 md:hidden" onClick={() => setOpen(false)} />}
    </div>
  );
};
