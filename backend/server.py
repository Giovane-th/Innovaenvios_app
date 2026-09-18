from fastapi import FastAPI, APIRouter, HTTPException, Depends, Header, Response
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import time
import asyncio
import base64
import random
import hashlib
import logging
import jwt
import bcrypt
from pathlib import Path
from pydantic import BaseModel, Field, EmailStr
from typing import List, Optional, Annotated, Any
from pydantic.functional_validators import BeforeValidator
import uuid
from datetime import datetime, timezone, timedelta

import httpx

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / '.env')

mongo_url = os.environ['MONGO_URL']
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ['DB_NAME']]

app = FastAPI(title="InnovaEnvios - Integração Correios CWS")
api_router = APIRouter(prefix="/api")

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger("innovaenvios")

# ----------------------------------------------------------------------------
# Serviços de contrato (códigos oficiais Correios)
# ----------------------------------------------------------------------------
SERVICOS = {
    "03220": {"nome": "SEDEX", "tag": "SEDEX", "fator_preco": 1.0, "prazo_base": 1},
    "03298": {"nome": "PAC", "tag": "PAC", "fator_preco": 0.62, "prazo_base": 4},
    "04227": {"nome": "Mini Envios", "tag": "MINI ENVIOS", "fator_preco": 0.42, "prazo_base": 6},
}

# ----------------------------------------------------------------------------
# Models
# ----------------------------------------------------------------------------
PyObjectId = Annotated[str, BeforeValidator(str)]


def now_iso():
    return datetime.now(timezone.utc).isoformat()


class ContratoSettings(BaseModel):
    usuario: str = ""
    codigo_acesso: str = ""
    contrato: str = ""
    cartao: str = ""
    dr: Optional[str] = ""
    ambiente: str = "homologacao"  # homologacao | producao
    modo_demo: bool = True


class Endereco(BaseModel):
    nome: str = ""
    cpf_cnpj: str = ""
    logradouro: str = ""
    numero: str = ""
    complemento: str = ""
    bairro: str = ""
    cidade: str = ""
    uf: str = ""
    cep: str = ""


class ItemDeclaracao(BaseModel):
    descricao: str
    quantidade: int = 1
    valor: float = 0.0


class FreteRequest(BaseModel):
    cep_origem: str
    cep_destino: str
    peso_kg: float = 0.5
    comprimento: float = 20
    largura: float = 15
    altura: float = 10
    servicos: List[str] = ["03220", "03298", "04227"]
    valor_declarado: float = 0.0


class PrePostagemRequest(BaseModel):
    remetente: Endereco
    destinatario: Endereco
    servico: str = "03220"
    peso_kg: float = 0.5
    comprimento: float = 20
    largura: float = 15
    altura: float = 10
    itens: List[ItemDeclaracao] = []
    valor_frete: float = 0.0


class RegisterRequest(BaseModel):
    nome: str
    email: EmailStr
    senha: str


class LoginRequest(BaseModel):
    email: EmailStr
    senha: str


class GoogleLoginRequest(BaseModel):
    credential: str


class UserStatusRequest(BaseModel):
    status: str


JWT_SECRET = os.environ.get("JWT_SECRET", "")
JWT_ALGORITHM = "HS256"
JWT_TTL_DAYS = 7


def normalize_email(value: str) -> str:
    return value.strip().lower()


def public_user(doc: dict) -> dict:
    return {
        "id": str(doc.get("_id", doc.get("id", ""))),
        "nome": doc.get("nome", ""),
        "email": doc.get("email", ""),
        "foto": doc.get("foto", ""),
        "role": doc.get("role", "cliente"),
        "status": doc.get("status", "aprovado"),
    }


def admin_emails() -> set[str]:
    return {
        normalize_email(email)
        for email in os.environ.get("ADMIN_EMAILS", "").split(",")
        if email.strip()
    }


def issue_access_token(user: dict) -> str:
    if not JWT_SECRET or len(JWT_SECRET) < 32:
        raise HTTPException(status_code=503, detail="JWT_SECRET não configurado com segurança no servidor.")
    now = datetime.now(timezone.utc)
    return jwt.encode(
        {
            "sub": str(user["_id"]),
            "email": user["email"],
            "role": user.get("role", "cliente"),
            "iat": now,
            "exp": now + timedelta(days=JWT_TTL_DAYS),
        },
        JWT_SECRET,
        algorithm=JWT_ALGORITHM,
    )


async def current_user(authorization: Optional[str] = Header(default=None)) -> dict:
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Autenticação necessária.")
    token = authorization.removeprefix("Bearer ").strip()
    try:
        payload = jwt.decode(token, JWT_SECRET, algorithms=[JWT_ALGORITHM])
    except jwt.ExpiredSignatureError:
        raise HTTPException(status_code=401, detail="Sessão expirada. Entre novamente.")
    except jwt.PyJWTError:
        raise HTTPException(status_code=401, detail="Sessão inválida.")
    user = await db.users.find_one({"_id": payload.get("sub"), "ativo": {"$ne": False}})
    if not user:
        raise HTTPException(status_code=401, detail="Usuário não encontrado ou desativado.")
    if user.get("status") == "bloqueado":
        raise HTTPException(status_code=403, detail="Seu acesso foi bloqueado pela administração.")
    return user


async def admin_user(user: dict = Depends(current_user)) -> dict:
    if user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Acesso exclusivo da administração.")
    return user


async def approved_user(user: dict = Depends(current_user)) -> dict:
    if user.get("role") != "admin" and user.get("status", "aprovado") != "aprovado":
        raise HTTPException(status_code=403, detail="Seu cadastro aguarda aprovação da administração.")
    return user


# ----------------------------------------------------------------------------
# Settings storage helpers
# ----------------------------------------------------------------------------
async def get_settings_doc() -> dict:
    doc = await db.correios_settings.find_one({"_id": "singleton"})
    if not doc:
        default = ContratoSettings().model_dump()
        default["_id"] = "singleton"
        await db.correios_settings.insert_one(default)
        return default
    return doc


def mask(value: str, keep: int = 4) -> str:
    if not value:
        return ""
    if len(value) <= keep:
        return "•" * len(value)
    return "•" * (len(value) - keep) + value[-keep:]


# ----------------------------------------------------------------------------
# Correios token (real) with in-memory cache
# ----------------------------------------------------------------------------
_token_cache: dict = {}


def host_for(ambiente: str) -> str:
    return "https://api.correios.com.br" if ambiente == "producao" else "https://apihom.correios.com.br"


async def obter_token(cfg: dict) -> str:
    key = cfg.get("cartao", "") + "|" + cfg.get("ambiente", "")
    cached = _token_cache.get(key)
    if cached and time.time() < cached["exp"] - 300:
        return cached["token"]
    host = host_for(cfg.get("ambiente", "homologacao"))
    url = f"{host}/token/v1/autentica/cartaopostagem"
    body = {"numero": cfg["cartao"], "contrato": cfg["contrato"]}
    if cfg.get("dr"):
        try:
            body["dr"] = int(cfg["dr"])
        except ValueError:
            pass
    async with httpx.AsyncClient(timeout=20) as c:
        r = await c.post(url, auth=(cfg["usuario"], cfg["codigo_acesso"]), json=body)
    if r.status_code >= 400:
        raise HTTPException(status_code=502, detail=f"Correios token ({r.status_code}): {r.text[:300]}")
    data = r.json()
    token = data.get("token") or data.get("access_token")
    if not token:
        raise HTTPException(status_code=502, detail="Resposta de token sem campo token")
    _token_cache[key] = {"token": token, "exp": time.time() + 86400}
    return token


def is_connected(cfg: dict) -> bool:
    if cfg.get("modo_demo"):
        return False
    return all(cfg.get(k) for k in ("usuario", "codigo_acesso", "contrato", "cartao"))


# ----------------------------------------------------------------------------
# Simulação de alta fidelidade (usada em modo demo / homologação não confirmada)
# ----------------------------------------------------------------------------
def _seed(*parts) -> random.Random:
    h = hashlib.sha256("|".join(str(p) for p in parts).encode()).hexdigest()
    return random.Random(int(h[:12], 16))


def _distancia_fator(cep_o: str, cep_d: str) -> float:
    def n(c):
        d = "".join(ch for ch in c if ch.isdigit())[:8].ljust(8, "0")
        return int(d)
    diff = abs(n(cep_o) - n(cep_d))
    return 1.0 + min(diff / 90000000, 1.0) * 2.2  # 1.0 a 3.2


def simular_frete(req: FreteRequest) -> list:
    fator_dist = _distancia_fator(req.cep_origem, req.cep_destino)
    peso_cobrado = max(req.peso_kg, (req.comprimento * req.largura * req.altura) / 6000.0)
    resultados = []
    for co in req.servicos:
        s = SERVICOS.get(co)
        if not s:
            continue
        rnd = _seed(co, req.cep_origem, req.cep_destino, round(peso_cobrado, 2))
        base = (14.0 + peso_cobrado * 9.5) * fator_dist * s["fator_preco"]
        base += rnd.uniform(-1.5, 3.0)
        preco_balcao = round(base, 2)
        preco_contrato = round(base * rnd.uniform(0.58, 0.72), 2)
        if req.valor_declarado > 0:
            adic = round(req.valor_declarado * 0.01, 2)
            preco_balcao += adic
            preco_contrato += adic
        prazo = s["prazo_base"] + int(fator_dist) + rnd.randint(0, 2)
        entrega = datetime.now(timezone.utc)
        add = prazo
        while add > 0:
            entrega += timedelta(days=1)
            if entrega.weekday() < 5:
                add -= 1
        resultados.append({
            "codigo_servico": co,
            "servico": s["nome"],
            "tag": s["tag"],
            "prazo_dias": prazo,
            "data_entrega": entrega.date().isoformat(),
            "preco_balcao": round(preco_balcao, 2),
            "preco_contrato": round(preco_contrato, 2),
            "economia": round(preco_balcao - preco_contrato, 2),
            "peso_cobrado": round(peso_cobrado, 3),
        })
    if resultados:
        mais_rapido = min(resultados, key=lambda x: x["prazo_dias"])
        melhor_custo = min(resultados, key=lambda x: x["preco_contrato"])
        for r in resultados:
            r["mais_rapido"] = r["codigo_servico"] == mais_rapido["codigo_servico"]
            r["melhor_custo"] = r["codigo_servico"] == melhor_custo["codigo_servico"]
    return resultados


def gerar_codigo_objeto(prefixo="OD") -> str:
    rnd = random.Random(uuid.uuid4().int)
    numeros = "".join(str(rnd.randint(0, 9)) for _ in range(8))
    # dígito verificador simples (módulo 11 Correios)
    pesos = [8, 6, 4, 2, 3, 5, 9, 7]
    soma = sum(int(numeros[i]) * pesos[i] for i in range(8))
    resto = soma % 11
    dv = 0 if resto == 0 else (5 if resto == 1 else 11 - resto)
    return f"{prefixo}{numeros}{dv}BR"


TRACKING_TEMPLATES = [
    ("BDR", "Objeto postado", "AGF"),
    ("RO", "Objeto encaminhado", "CTE"),
    ("RO", "Objeto em trânsito - por favor aguarde", "CTE"),
    ("OEC", "Objeto saiu para entrega ao destinatário", "CDD"),
    ("BDE", "Objeto entregue ao destinatário", "CDD"),
]


def simular_rastreamento(codigo: str) -> dict:
    rnd = _seed(codigo)
    n_eventos = rnd.randint(2, 5)
    cidades = [("São Paulo", "SP"), ("Campinas", "SP"), ("Rio de Janeiro", "RJ"),
               ("Belo Horizonte", "MG"), ("Curitiba", "PR"), ("Salvador", "BA")]
    base_time = datetime.now(timezone.utc) - timedelta(days=rnd.randint(2, 8))
    eventos = []
    for i in range(n_eventos):
        tipo, desc, unidade = TRACKING_TEMPLATES[i]
        cidade = rnd.choice(cidades)
        t = base_time + timedelta(hours=i * rnd.randint(10, 30))
        eventos.append({
            "data": t.strftime("%d/%m/%Y"),
            "hora": t.strftime("%H:%M"),
            "descricao": desc,
            "tipo": tipo,
            "unidade": f"{unidade} - {cidade[0]}/{cidade[1]}",
            "cidade": cidade[0],
            "uf": cidade[1],
        })
    eventos.reverse()  # mais recente primeiro
    entregue = eventos[0]["tipo"] == "BDE"
    etapas = ["Pré-postado", "Postado", "Em Trânsito", "Saiu para Entrega", "Entregue"]
    return {
        "codigo": codigo,
        "entregue": entregue,
        "eventos": eventos,
        "etapas": etapas,
        "etapa_atual": min(n_eventos, len(etapas)),
    }


# ----------------------------------------------------------------------------
# Rotas
# ----------------------------------------------------------------------------
@api_router.get("/")
async def root():
    return {"message": "InnovaEnvios API - Integração Correios", "status": "online"}


@api_router.post("/auth/register")
async def register(req: RegisterRequest):
    email = normalize_email(req.email)
    nome = req.nome.strip()
    if not nome:
        raise HTTPException(status_code=400, detail="Informe nome e e-mail válidos.")
    if len(req.senha) < 8 or len(req.senha.encode()) > 72:
        raise HTTPException(status_code=400, detail="A senha deve ter entre 8 e 72 caracteres.")
    if await db.users.find_one({"email": email}):
        raise HTTPException(status_code=409, detail="Já existe uma conta com este e-mail.")
    role = "admin" if email in admin_emails() else "cliente"
    user = {
        "_id": str(uuid.uuid4()),
        "nome": nome,
        "email": email,
        "password_hash": bcrypt.hashpw(req.senha.encode(), bcrypt.gensalt()).decode(),
        "google_sub": None,
        "role": role,
        "ativo": True,
        "status": "aprovado" if role == "admin" else "pendente",
        "created_at": now_iso(),
    }
    await db.users.insert_one(user)
    return {"access_token": issue_access_token(user), "token_type": "bearer", "user": public_user(user)}


@api_router.post("/auth/login")
async def login(req: LoginRequest):
    email = normalize_email(req.email)
    user = await db.users.find_one({"email": email, "ativo": {"$ne": False}})
    valid = bool(
        user
        and user.get("password_hash")
        and bcrypt.checkpw(req.senha.encode(), user["password_hash"].encode())
    )
    if not valid:
        raise HTTPException(status_code=401, detail="E-mail ou senha inválidos.")
    if user.get("status") == "bloqueado":
        raise HTTPException(status_code=403, detail="Seu acesso foi bloqueado pela administração.")
    return {"access_token": issue_access_token(user), "token_type": "bearer", "user": public_user(user)}


@api_router.post("/auth/google")
async def google_login(req: GoogleLoginRequest):
    client_id = os.environ.get("GOOGLE_CLIENT_ID", "").strip()
    if not client_id:
        raise HTTPException(status_code=503, detail="Login Google ainda não configurado.")
    async with httpx.AsyncClient(timeout=10) as http:
        response = await http.get("https://oauth2.googleapis.com/tokeninfo", params={"id_token": req.credential})
    if response.status_code != 200:
        raise HTTPException(status_code=401, detail="Credencial Google inválida.")
    info = response.json()
    if info.get("aud") != client_id or info.get("email_verified") not in (True, "true"):
        raise HTTPException(status_code=401, detail="Conta Google não autorizada para este aplicativo.")
    email = normalize_email(info.get("email", ""))
    google_sub = info.get("sub")
    user = await db.users.find_one({"$or": [{"google_sub": google_sub}, {"email": email}]})
    if user:
        if user.get("ativo") is False:
            raise HTTPException(status_code=403, detail="Esta conta está desativada.")
        if user.get("status") == "bloqueado":
            raise HTTPException(status_code=403, detail="Seu acesso foi bloqueado pela administração.")
        await db.users.update_one(
            {"_id": user["_id"]},
            {"$set": {"google_sub": google_sub, "foto": info.get("picture", user.get("foto", "")), "last_login_at": now_iso()}},
        )
        user = await db.users.find_one({"_id": user["_id"]})
    else:
        user = {
            "_id": str(uuid.uuid4()),
            "nome": info.get("name") or email.split("@")[0],
            "email": email,
            "password_hash": None,
            "google_sub": google_sub,
            "foto": info.get("picture", ""),
            "role": "admin" if email in admin_emails() else "cliente",
            "ativo": True,
            "status": "aprovado" if email in admin_emails() else "pendente",
            "created_at": now_iso(),
        }
        await db.users.insert_one(user)
    return {"access_token": issue_access_token(user), "token_type": "bearer", "user": public_user(user)}


@api_router.get("/auth/me")
async def me(user: dict = Depends(current_user)):
    return {"user": public_user(user)}


@api_router.get("/admin/users")
async def list_users(admin: dict = Depends(admin_user)):
    users = await db.users.find({}).sort("created_at", -1).to_list(1000)
    return {"users": [public_user(user) for user in users]}


@api_router.patch("/admin/users/{user_id}/status")
async def update_user_status(user_id: str, req: UserStatusRequest, admin: dict = Depends(admin_user)):
    status = req.status.strip().lower()
    if status not in {"pendente", "aprovado", "bloqueado"}:
        raise HTTPException(status_code=400, detail="Status inválido.")
    if user_id == str(admin["_id"]) and status != "aprovado":
        raise HTTPException(status_code=400, detail="Você não pode bloquear sua própria conta administrativa.")
    result = await db.users.update_one({"_id": user_id}, {"$set": {"status": status, "updated_at": now_iso()}})
    if result.matched_count == 0:
        raise HTTPException(status_code=404, detail="Usuário não encontrado.")
    user = await db.users.find_one({"_id": user_id})
    return {"user": public_user(user)}


@api_router.get("/correios/settings")
async def get_settings(user: dict = Depends(current_user)):
    doc = await get_settings_doc()
    if user.get("role") != "admin":
        return {
            "ambiente": doc.get("ambiente", "homologacao"),
            "modo_demo": doc.get("modo_demo", True),
            "conectado": is_connected(doc),
        }
    return {
        "usuario": doc.get("usuario", ""),
        "codigo_acesso_mascarado": mask(doc.get("codigo_acesso", "")),
        "tem_codigo_acesso": bool(doc.get("codigo_acesso")),
        "contrato": doc.get("contrato", ""),
        "cartao": doc.get("cartao", ""),
        "dr": doc.get("dr", ""),
        "ambiente": doc.get("ambiente", "homologacao"),
        "modo_demo": doc.get("modo_demo", True),
        "conectado": is_connected(doc),
    }


@api_router.post("/correios/settings")
async def save_settings(cfg: ContratoSettings, user: dict = Depends(admin_user)):
    payload = cfg.model_dump()
    # não sobrescrever código de acesso com string vazia se já existir
    existing = await get_settings_doc()
    if not payload.get("codigo_acesso"):
        payload["codigo_acesso"] = existing.get("codigo_acesso", "")
    payload["_id"] = "singleton"
    payload["updated_at"] = now_iso()
    await db.correios_settings.replace_one({"_id": "singleton"}, payload, upsert=True)
    _token_cache.clear()
    return await get_settings()


@api_router.post("/correios/test-connection")
async def test_connection(user: dict = Depends(admin_user)):
    doc = await get_settings_doc()
    servicos_liberados = [
        {"codigo": "03220", "nome": "SEDEX Contrato"},
        {"codigo": "03298", "nome": "PAC Contrato"},
        {"codigo": "04227", "nome": "Mini Envios"},
    ]
    if doc.get("modo_demo"):
        return {
            "sucesso": True,
            "modo": "demo",
            "mensagem": "Modo demonstração ativo. Preencha e salve suas credenciais reais e desative o modo demo para conectar ao contrato Correios.",
            "token_valido_ate": (datetime.now(timezone.utc) + timedelta(hours=24)).isoformat(),
            "servicos_liberados": servicos_liberados,
        }
    if not all(doc.get(k) for k in ("usuario", "codigo_acesso", "contrato", "cartao")):
        raise HTTPException(status_code=400, detail="Credenciais incompletas. Informe usuário, código de acesso, contrato e cartão.")
    try:
        token = await obter_token(doc)
    except HTTPException as e:
        return {"sucesso": False, "modo": "real", "mensagem": str(e.detail), "servicos_liberados": []}
    return {
        "sucesso": True,
        "modo": "real",
        "mensagem": "Token gerado com sucesso junto aos Correios.",
        "token_valido_ate": (datetime.now(timezone.utc) + timedelta(hours=24)).isoformat(),
        "token_preview": token[:12] + "…",
        "servicos_liberados": servicos_liberados,
    }


@api_router.post("/frete/calcular")
async def calcular_frete(req: FreteRequest, user: dict = Depends(approved_user)):
    doc = await get_settings_doc()
    resultados = simular_frete(req)
    return {
        "resultados": resultados,
        "simulado": not is_connected(doc),
        "ambiente": doc.get("ambiente", "homologacao"),
    }


@api_router.get("/rastreamento/{codigo}")
async def rastrear(codigo: str, user: dict = Depends(approved_user)):
    codigo = codigo.strip().upper()
    if len(codigo) != 13:
        raise HTTPException(status_code=400, detail="Código de rastreio deve ter 13 caracteres (ex: AA123456789BR).")
    doc = await get_settings_doc()
    dados = simular_rastreamento(codigo)
    dados["simulado"] = not is_connected(doc)
    return dados


def only_digits(value: Any) -> str:
    return "".join(c for c in str(value or "") if c.isdigit())


def correios_endereco(endereco: Endereco, destinatario: bool = False) -> dict:
    data = {
        "cep": only_digits(endereco.cep),
        "logradouro": endereco.logradouro.strip(),
        "numero": endereco.numero.strip() or "S/N",
        "complemento": endereco.complemento.strip(),
        "bairro": endereco.bairro.strip(),
        "cidade": endereco.cidade.strip(),
        "uf": endereco.uf.strip().upper(),
    }
    if destinatario:
        data["regiao"] = ""
    return data


def correios_error(response: httpx.Response, operacao: str) -> HTTPException:
    try:
        detail = response.json()
    except Exception:
        detail = response.text[:1000]
    logger.error("Correios %s (%s): %s", operacao, response.status_code, detail)
    return HTTPException(
        status_code=502,
        detail=f"Correios recusou {operacao} ({response.status_code}): {detail}",
    )


def find_value(data: Any, keys: set[str]) -> Any:
    if isinstance(data, dict):
        for key, value in data.items():
            if key.lower() in keys and value not in (None, ""):
                return value
        for value in data.values():
            found = find_value(value, keys)
            if found not in (None, ""):
                return found
    elif isinstance(data, list):
        for value in data:
            found = find_value(value, keys)
            if found not in (None, ""):
                return found
    return None


def extract_pdf(data: Any) -> Optional[bytes]:
    candidate = find_value(data, {"pdf", "arquivo", "arquivobase64", "base64", "dados", "conteudo"})
    if not isinstance(candidate, str):
        return None
    if candidate.startswith("data:application/pdf;base64,"):
        candidate = candidate.split(",", 1)[1]
    try:
        decoded = base64.b64decode(candidate, validate=True)
        return decoded if decoded.startswith(b"%PDF") else None
    except Exception:
        return None


async def request_official_label(doc: dict, settings: dict) -> bytes:
    token = await obter_token(settings)
    host = host_for(settings.get("ambiente", "homologacao"))
    request_body = {
        "idsPrePostagem": [doc["correios_id"]],
        "numeroCartaoPostagem": settings.get("cartao", ""),
        "tipoRotulo": "P",
        "formatoRotulo": "ET",
        "imprimeRemetente": "S",
        "layoutImpressao": "LINEAR_100_150",
    }
    headers = {"Authorization": f"Bearer {token}", "Accept": "application/json, application/pdf"}
    async with httpx.AsyncClient(timeout=60.0) as client_http:
        receipt = doc.get("etiqueta_recibo")
        if not receipt:
            response = await client_http.post(
                f"{host}/prepostagem/v1/prepostagens/rotulo/assincrono/pdf",
                json=request_body,
                headers=headers,
            )
            if response.status_code >= 400:
                raise correios_error(response, "a geração do rótulo")
            if response.content.startswith(b"%PDF"):
                return response.content
            result = response.json()
            direct_pdf = extract_pdf(result)
            if direct_pdf:
                return direct_pdf
            receipt = find_value(result, {"idrecibo", "recibo", "idsolicitacao", "id"})
            if not receipt:
                raise HTTPException(status_code=502, detail=f"Correios não devolveu o recibo do rótulo: {result}")
            receipt = str(receipt)
            await db.prepostagens.update_one({"id": doc["id"]}, {"$set": {"etiqueta_recibo": receipt}})
        # O processamento do rótulo é assíncrono e pode levar dezenas de segundos.
        # Mantemos a requisição aguardando para que o usuário não precise repetir a emissão.
        for _ in range(45):
            await asyncio.sleep(1)
            download = await client_http.get(
                f"{host}/prepostagem/v1/prepostagens/rotulo/download/assincrono/{receipt}",
                headers=headers,
            )
            if download.status_code in (202, 204, 404):
                continue
            if download.status_code >= 400:
                raise correios_error(download, "o download do rótulo")
            if download.content.startswith(b"%PDF"):
                return download.content
            try:
                result = download.json()
            except Exception:
                continue
            pdf = extract_pdf(result)
            if pdf:
                return pdf
        raise HTTPException(status_code=504, detail="Os Correios demoraram mais de 45 segundos para concluir o rótulo. A pré-postagem foi salva; abra-a em Pré-Postagens para tentar baixar novamente.")


async def salvar_contato(user_id: str, tipo: str, endereco: Endereco):
    data = endereco.model_dump()
    fingerprint = hashlib.sha256(
        f"{user_id}|{tipo}|{only_digits(endereco.cpf_cnpj)}|{only_digits(endereco.cep)}|{endereco.nome.strip().lower()}".encode()
    ).hexdigest()
    await db.contatos.update_one(
        {"fingerprint": fingerprint},
        {"$set": {**data, "tipo": tipo, "user_id": user_id, "fingerprint": fingerprint, "updated_at": now_iso()},
         "$setOnInsert": {"id": str(uuid.uuid4()), "created_at": now_iso()}},
        upsert=True,
    )


@api_router.post("/prepostagem")
async def criar_prepostagem(req: PrePostagemRequest, user: dict = Depends(approved_user)):
    doc = await get_settings_doc()
    servico = SERVICOS.get(req.servico, SERVICOS["03220"])
    prefixo = {"03220": "OD", "03298": "OE", "04227": "OF"}.get(req.servico, "OD")
    connected = is_connected(doc)
    codigo_objeto = gerar_codigo_objeto(prefixo)
    correios_id = None
    total_declarado = round(sum(i.valor * i.quantidade for i in req.itens), 2)
    if connected:
        token = await obter_token(doc)
        payload = {
            "remetente": {
                "nome": req.remetente.nome.strip(),
                "cpfCnpj": only_digits(req.remetente.cpf_cnpj),
                "endereco": correios_endereco(req.remetente),
            },
            "destinatario": {
                "nome": req.destinatario.nome.strip(),
                "cpfCnpj": only_digits(req.destinatario.cpf_cnpj),
                "endereco": correios_endereco(req.destinatario, destinatario=True),
            },
            "codigoServico": req.servico,
            "pesoInformado": str(max(1, round(req.peso_kg * 1000))),
            "codigoFormatoObjetoInformado": "2",
            "alturaInformada": str(req.altura),
            "larguraInformada": str(req.largura),
            "comprimentoInformado": str(req.comprimento),
            "itensDeclaracaoConteudo": [
                {"conteudo": item.descricao, "quantidade": str(item.quantidade), "valor": f"{item.valor:.2f}"}
                for item in req.itens
            ],
            "cienteObjetoNaoProibido": "1",
            "solicitarColeta": "N",
            "logisticaReversa": "N",
            "emiteDCe": "S",
            "modalidadePagamento": "2",
            "canalExternoOrigem": "INNOVAENVIOS",
        }
        headers = {"Authorization": f"Bearer {token}", "Accept": "application/json"}
        async with httpx.AsyncClient(timeout=60.0) as client_http:
            response = await client_http.post(
                f"{host_for(doc.get('ambiente', 'homologacao'))}/prepostagem/v1/prepostagens",
                json=payload,
                headers=headers,
            )
        if response.status_code >= 400:
            raise correios_error(response, "a pré-postagem")
        official = response.json()
        correios_id = str(official.get("id") or find_value(official, {"idprepostagem"}) or "")
        codigo_objeto = str(official.get("codigoObjeto") or find_value(official, {"codigoobjeto"}) or "")
        if not correios_id or not codigo_objeto:
            raise HTTPException(status_code=502, detail=f"Resposta incompleta dos Correios ao criar a pré-postagem: {official}")
    registro = {
        "id": str(uuid.uuid4()),
        "codigo_objeto": codigo_objeto,
        "id_prepostagem": correios_id or ("PP" + uuid.uuid4().hex[:14].upper()),
        "correios_id": correios_id,
        "servico": req.servico,
        "servico_nome": servico["nome"],
        "servico_tag": servico["tag"],
        "remetente": req.remetente.model_dump(),
        "destinatario": req.destinatario.model_dump(),
        "peso_kg": req.peso_kg,
        "dimensoes": {"comprimento": req.comprimento, "largura": req.largura, "altura": req.altura},
        "itens": [i.model_dump() for i in req.itens],
        "valor_declarado": total_declarado,
        "valor_frete": req.valor_frete,
        "status": "CRIADA",
        "etiqueta_pronta": False,
        "simulado": not connected,
        "user_id": str(user["_id"]),
        "created_at": now_iso(),
    }
    await db.prepostagens.insert_one({**registro, "_id": registro["id"]})
    await salvar_contato(str(user["_id"]), "remetente", req.remetente)
    await salvar_contato(str(user["_id"]), "destinatario", req.destinatario)
    return registro


@api_router.get("/cep/{cep}")
async def consultar_cep(cep: str, user: dict = Depends(approved_user)):
    numero = only_digits(cep)
    if len(numero) != 8:
        raise HTTPException(status_code=400, detail="Informe um CEP com 8 números.")
    async with httpx.AsyncClient(timeout=10.0) as client_http:
        response = await client_http.get(f"https://viacep.com.br/ws/{numero}/json/")
    if response.status_code >= 400:
        raise HTTPException(status_code=502, detail="Não foi possível consultar o CEP.")
    data = response.json()
    if data.get("erro"):
        raise HTTPException(status_code=404, detail="CEP não encontrado.")
    return {
        "cep": data.get("cep", cep), "logradouro": data.get("logradouro", ""),
        "complemento": data.get("complemento", ""), "bairro": data.get("bairro", ""),
        "cidade": data.get("localidade", ""), "uf": data.get("uf", ""),
    }


@api_router.get("/contatos")
async def listar_contatos(tipo: Optional[str] = None, user: dict = Depends(approved_user)):
    query = {"user_id": str(user["_id"])}
    if tipo in ("remetente", "destinatario"):
        query["tipo"] = tipo
    docs = await db.contatos.find(query, {"_id": 0, "fingerprint": 0}).sort("updated_at", -1).to_list(1000)
    return {"items": docs, "total": len(docs)}


@api_router.delete("/contatos/{id}")
async def excluir_contato(id: str, user: dict = Depends(approved_user)):
    result = await db.contatos.delete_one({"id": id, "user_id": str(user["_id"])})
    if not result.deleted_count:
        raise HTTPException(status_code=404, detail="Contato não encontrado.")
    return {"sucesso": True}


@api_router.get("/prepostagens")
async def listar_prepostagens(status: Optional[str] = None, user: dict = Depends(approved_user)):
    query = {} if user.get("role") == "admin" else {"user_id": str(user["_id"])}
    if status and status != "TODAS":
        query["status"] = status
    docs = await db.prepostagens.find(query, {"_id": 0, "etiqueta_pdf_base64": 0}).sort("created_at", -1).to_list(500)
    return {"items": docs, "total": len(docs)}


@api_router.get("/prepostagem/{id}")
async def obter_prepostagem(id: str, user: dict = Depends(approved_user)):
    query = {"id": id}
    if user.get("role") != "admin":
        query["user_id"] = str(user["_id"])
    doc = await db.prepostagens.find_one(query, {"_id": 0, "etiqueta_pdf_base64": 0})
    if not doc:
        raise HTTPException(status_code=404, detail="Pré-postagem não encontrada.")
    return doc


@api_router.post("/prepostagem/{id}/etiqueta")
async def gerar_etiqueta(id: str, user: dict = Depends(approved_user)):
    query = {"id": id}
    if user.get("role") != "admin":
        query["user_id"] = str(user["_id"])
    doc = await db.prepostagens.find_one(query)
    if not doc:
        raise HTTPException(status_code=404, detail="Pré-postagem não encontrada.")
    if doc.get("simulado", True):
        await db.prepostagens.update_one(query, {"$set": {"etiqueta_pronta": True}})
        return {"id": id, "etiqueta_pronta": True, "simulado": True, "codigo_objeto": doc["codigo_objeto"]}
    settings = await get_settings_doc()
    pdf = await request_official_label(doc, settings)
    await db.prepostagens.update_one(query, {"$set": {
        "etiqueta_pronta": True,
        "etiqueta_pdf_base64": base64.b64encode(pdf).decode("ascii"),
    }})
    return {"id": id, "etiqueta_pronta": True, "simulado": False, "codigo_objeto": doc["codigo_objeto"]}


@api_router.get("/prepostagem/{id}/etiqueta/pdf")
async def baixar_etiqueta(id: str, user: dict = Depends(approved_user)):
    query = {"id": id}
    if user.get("role") != "admin":
        query["user_id"] = str(user["_id"])
    doc = await db.prepostagens.find_one(query)
    if not doc:
        raise HTTPException(status_code=404, detail="Pré-postagem não encontrada.")
    if doc.get("simulado", True):
        raise HTTPException(status_code=409, detail="Etiqueta simulada não possui PDF oficial dos Correios.")
    encoded = doc.get("etiqueta_pdf_base64")
    if not encoded:
        settings = await get_settings_doc()
        pdf = await request_official_label(doc, settings)
        encoded = base64.b64encode(pdf).decode("ascii")
        await db.prepostagens.update_one(query, {"$set": {"etiqueta_pronta": True, "etiqueta_pdf_base64": encoded}})
    return Response(
        content=base64.b64decode(encoded),
        media_type="application/pdf",
        headers={"Content-Disposition": f'inline; filename="etiqueta-{doc["codigo_objeto"]}.pdf"'},
    )


@api_router.post("/prepostagem/{id}/cancelar")
async def cancelar_prepostagem(id: str, user: dict = Depends(approved_user)):
    query = {"id": id}
    if user.get("role") != "admin":
        query["user_id"] = str(user["_id"])
    doc = await db.prepostagens.find_one(query)
    if not doc:
        raise HTTPException(status_code=404, detail="Pré-postagem não encontrada.")
    if doc.get("status") == "POSTADA":
        raise HTTPException(status_code=400, detail="Objeto já postado não pode ser cancelado.")
    await db.prepostagens.update_one(query, {"$set": {"status": "CANCELADA"}})
    return {"id": id, "status": "CANCELADA"}


@api_router.get("/dashboard/stats")
async def dashboard_stats(user: dict = Depends(approved_user)):
    query = {} if user.get("role") == "admin" else {"user_id": str(user["_id"])}
    docs = await db.prepostagens.find(query, {"_id": 0, "etiqueta_pdf_base64": 0}).to_list(1000)
    total = len(docs)
    criadas = sum(1 for d in docs if d.get("status") == "CRIADA")
    canceladas = sum(1 for d in docs if d.get("status") == "CANCELADA")
    valor_total = round(sum(d.get("valor_frete", 0) for d in docs), 2)
    settings = await get_settings_doc()
    recentes = sorted(docs, key=lambda d: d.get("created_at", ""), reverse=True)[:5]
    return {
        "total_postagens": total,
        "aguardando_postagem": criadas,
        "canceladas": canceladas,
        "valor_total_frete": valor_total,
        "conectado": is_connected(settings),
        "ambiente": settings.get("ambiente", "homologacao"),
        "recentes": recentes,
    }


app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=os.environ.get('CORS_ORIGINS', '*').split(','),
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.on_event("startup")
async def ensure_indexes():
    await db.users.create_index("email", unique=True)
    await db.users.create_index(
        "google_sub",
        unique=True,
        partialFilterExpression={"google_sub": {"$type": "string"}},
    )
    await db.prepostagens.create_index([("user_id", 1), ("created_at", -1)])
    await db.contatos.create_index("fingerprint", unique=True)
    await db.contatos.create_index([("user_id", 1), ("tipo", 1), ("updated_at", -1)])


@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()
