"""Comando de confirmação do RPZ, executado pelo gateway do Telegram."""

import json
import re
from datetime import datetime
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import HTTPRedirectHandler, Request, build_opener
from zoneinfo import ZoneInfo


class SemRedirecionamento(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def confirmar(raw_args, *, source=None, platform=None):
    if platform != "telegram" or source is None:
        return "Confirme pelo tópico autorizado do Telegram."
    request_id = raw_args.strip()
    if not re.fullmatch(r"[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}", request_id):
        return "Use /rpz_confirmar seguido do identificador completo da solicitação."

    user, chat, thread = (getattr(source, attr, None) for attr in ("user_id", "chat_id", "thread_id"))
    if not all((user, chat, thread)):
        return "Esta conversa não tem os identificadores necessários para confirmar."

    try:
        config = json.loads(Path(__file__).with_name("approval.json").read_text())
        if str(chat) != config["chat_id"] or str(thread) != config["thread_id"]:
            return "A confirmação pertence ao tópico de operação do RPZ."
        token = Path(config["token_file"]).read_text().strip()
        if not re.fullmatch(r"rpza_[a-f0-9]{64}", token):
            return "A credencial de confirmação precisa ser configurada."
        base = config["api_url"].rstrip("/")
        if not re.fullmatch(r"https://[A-Za-z0-9.-]+(?::[0-9]+)?/api/v1", base):
            return "A URL HTTPS do RPZ precisa ser configurada."
        payload = json.dumps({
            "telegram_user_id": str(user),
            "telegram_chat_id": str(chat),
            "telegram_thread_id": str(thread),
        }).encode()
        req = Request(f"{base}/operacao/solicitacoes-excecao/{request_id}/confirmacao", data=payload, headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        })
        with build_opener(SemRedirecionamento()).open(req, timeout=10) as response:
            result = json.load(response)["data"]
    except HTTPError as error:
        error.close()
        return {
            403: "Seu operador ou tópico não tem permissão para confirmar esta solicitação.",
            404: "Solicitação não encontrada.",
            409: "A solicitação expirou, já foi processada ou o alvo mudou. Prepare uma nova prévia se necessário.",
            422: "O RPZ recusou a alteração. Consulte o estado atual e prepare outra prévia.",
        }.get(error.code, f"O RPZ recusou a confirmação (HTTP {error.code}).")
    except (OSError, URLError, KeyError, ValueError, TypeError):
        return "Não foi possível verificar a confirmação no RPZ. Repita o mesmo comando para recuperar o resultado."
    action = "removida" if result.get("operacao") == "remover" else "registrada"
    publication = result.get("publicacao_empresa") or {}
    state = {
        "pendente": "pendente de publicação",
        "publicada": "zona atualmente publicada",
        "sem_publicacao": "sem publicação registrada",
    }.get(publication.get("status"), "status de publicação indisponível")
    if publication.get("publicacao_habilitada") is False:
        state = "publicação desabilitada"
    confirmed = result.get("confirmada_em")
    receipt_time = ""
    if confirmed:
        local = datetime.fromisoformat(confirmed).astimezone(ZoneInfo("America/Sao_Paulo"))
        receipt_time = f" às {local:%d/%m/%Y %H:%M:%S} (Brasília)"
    company = result.get("empresa") or "Empresa da solicitação"
    if result.get("tipo") == "cadastro_lista_manual":
        action = {"cadastrar": "cadastrada", "alterar": "alterada", "remover": "removida"}[result["operacao"]]
        return (
            f"✅ **Lista {action}**{receipt_time}\n"
            f"🏢 **{company}** · 📋 **{result['lista']}** (ID {result['lista_id']})"
        )
    if "lista_id" in result and "servidores_vinculados" in result:
        domain_action = "incluído" if result["operacao"] == "incluir" else "removido"
        publication_status = f"RPZ: {state}." if result["servidores_vinculados"] else "Lista sem servidores vinculados."
        return (
            f"✅ **Domínio {domain_action}**{receipt_time}\n"
            f"🏢 **{company}** · 📋 **{result['lista']}**\n"
            f"🌐 **{result['dominio']}**\n\n"
            f"📡 {publication_status}"
        )
    if result.get("operacao") in {"vincular", "desvincular"}:
        link_action = "vinculada" if result["operacao"] == "vincular" else "desvinculada"
        return (
            f"✅ **Lista {link_action}**{receipt_time}\n"
            f"🏢 **{company}** · 🖥️ **{result['servidor']}**\n"
            f"📋 **{result['lista']}**\n\n"
            f"📡 RPZ: {state}."
        )
    return (
        f"✅ **Exceção {action}**\n"
        f"🏢 **{company}** · 🌐 **{result['dominio']}**\n"
        f"🧾 Registro {result['excecao_id']}{receipt_time}\n\n"
        f"📡 RPZ: {state}.\n"
        "Para verificar entrega e serial, peça a sincronização da empresa."
    )


def responder_confirmacao(*, event, **_):
    """Encaminha uma resposta explícita à prévia pelo despacho normal de comandos."""
    source = event.source
    if (getattr(source.platform, "value", None) != "telegram"
            or getattr(source, "is_bot", False)
            or getattr(event, "internal", False)
            or not getattr(event, "allow_gateway_control", False)
            or (event.text or "").strip().casefold() != "confirma"
            or not event.reply_to_message_id):
        return None

    # This Telegram adapter provides the original Message on raw_message.
    incoming = getattr(event, "raw_message", None)
    replied = getattr(incoming, "reply_to_message", None)
    author = getattr(replied, "from_user", None)
    sender = getattr(incoming, "from_user", None)
    if (not getattr(author, "is_bot", False)
            or getattr(replied, "forward_origin", None) is not None
            or str(getattr(sender, "id", None)) != str(source.user_id)):
        return None
    try:
        if author.id != incoming.get_bot().id:
            return None
    except (AttributeError, RuntimeError):
        return None

    preview = getattr(replied, "text", None) or getattr(replied, "caption", None) or ""
    if "prévia" not in preview.casefold():
        return None
    ids = re.findall(r"/rpz_confirmar\s+([a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\b", preview)
    if len(set(ids)) != 1:
        return None

    try:
        config = json.loads(Path(__file__).with_name("approval.json").read_text())
        if str(source.chat_id) != config["chat_id"] or str(source.thread_id) != config["thread_id"]:
            return None
    except (OSError, ValueError, KeyError, TypeError):
        return None

    return {"action": "rewrite", "text": f"/rpz_confirmar {ids[0]}"}


def register(ctx):
    ctx.register_hook("pre_gateway_dispatch", responder_confirmacao)
    ctx.register_command("rpz-confirmar", handler=confirmar, description="Confirma uma alteração RPZ preparada no tópico autorizado.")
