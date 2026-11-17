import imaplib
import json
import re
import ssl
import sys
from datetime import datetime, timedelta, timezone
from email import policy
from email.parser import BytesParser
from email.utils import parseaddr
from pathlib import Path


def fetch_latest(settings, directory):
    limit = settings["max_bytes"]
    max_files = settings["max_files"]
    message_limit = (limit * max_files * 2) + 1024 * 1024
    with imaplib.IMAP4_SSL(
        settings["host"], settings["port"], ssl_context=ssl.create_default_context(), timeout=8
    ) as client:
        client.login(settings["email"], settings["password"])
        status, count = client.select("INBOX", readonly=True)
        if status != "OK":
            raise ValueError("Inbox unavailable")
        if count[0] == b"0":
            return []

        status, metadata = client.fetch("*", "(UID RFC822.SIZE)")
        record = metadata[0] if status == "OK" and metadata else None
        if not isinstance(record, bytes):
            raise ValueError("Missing message metadata")
        uid = re.search(rb"\bUID (\d+)", record)
        size = re.search(rb"\bRFC822\.SIZE (\d+)", record)
        if uid is None or size is None or int(size[1]) > message_limit:
            raise ValueError("Message exceeds limits")

        status, parts = client.uid("FETCH", uid[1], "(BODY.PEEK[])")
        if status != "OK":
            raise ValueError("Message unavailable")
        raw = next((entry[1] for entry in parts if isinstance(entry, tuple)), None)
        if raw is None or len(raw) > message_limit:
            raise ValueError("Invalid message")

    return extract_attachments(raw, directory, max_files, limit)


def extract_attachments(raw, directory, max_files, limit):
    message = BytesParser(policy=policy.default).parsebytes(raw)
    attachments = []
    for part in message.walk():
        filename = part.get_filename()
        if part.is_multipart() or not filename:
            continue
        filename = re.sub(r"[\x00-\x1f\x7f]", "", filename.replace("\\", "/").rsplit("/", 1)[-1])
        if Path(filename).suffix.lower() not in (".pdf", ".xlsx"):
            continue
        payload = part.get_payload(decode=True)
        if not payload or len(payload) > limit or len(attachments) >= max_files:
            raise ValueError("Attachment exceeds limits")
        (directory / str(len(attachments))).write_bytes(payload)
        attachments.append(filename[:255])
    return attachments


def scan_new(settings, directory):
    limit = settings["max_bytes"]
    max_files = settings["max_files"]
    message_limit = (limit * max_files * 2) + 1024 * 1024
    with imaplib.IMAP4_SSL(
        settings["host"], settings["port"], ssl_context=ssl.create_default_context(), timeout=8
    ) as client:
        client.login(settings["email"], settings["password"])
        if client.select("INBOX", readonly=True)[0] != "OK":
            raise ValueError("Inbox unavailable")
        _, validity = client.response("UIDVALIDITY")
        epoch = int(validity[0])
        cursor = settings["cursor"]
        if cursor and cursor["epoch"] == epoch:
            first = cursor["uid"] + 1
            status, rows = client.uid("SEARCH", None, "UID", f"{first}:*")
        else:
            first = 1
            date = (datetime.now(timezone.utc) - timedelta(days=1)).strftime("%d-%b-%Y")
            status, rows = client.uid("SEARCH", None, "SINCE", date)
        if status != "OK":
            raise ValueError("Inbox search failed")
        messages = []
        downloaded = 0
        for uid in sorted({int(value) for value in rows[0].split() if int(value) >= first})[:25]:
            if downloaded >= 3:
                break
            status, metadata = client.uid("FETCH", str(uid), "(RFC822.SIZE)")
            record = metadata[0] if status == "OK" and metadata else None
            size = re.search(rb"\bRFC822\.SIZE (\d+)", record) if isinstance(record, bytes) else None
            if size is None:
                raise ValueError("Missing message size")
            item = {"uid": uid, "state": "ignored", "files": []}
            messages.append(item)
            if int(size[1]) > message_limit:
                item["state"] = "oversized"
                continue

            status, parts = client.uid("FETCH", str(uid), "(BODY.PEEK[HEADER.FIELDS (SUBJECT FROM)])")
            headers = next((part[1] for part in parts if isinstance(part, tuple)), None)
            if status != "OK" or headers is None:
                raise ValueError("Missing message headers")
            message = BytesParser(policy=policy.default).parsebytes(headers)
            if not re.search(r"\b(?:anatel|ciberlab)\b", str(message.get("Subject", "")), re.I):
                continue
            item["sender"] = parseaddr(str(message.get("From", "")))[1]
            downloaded += 1
            status, parts = client.uid("FETCH", str(uid), "(BODY.PEEK[])")
            raw = next((part[1] for part in parts if isinstance(part, tuple)), None)
            if status != "OK" or raw is None or len(raw) > message_limit:
                raise ValueError("Message unavailable")
            folder = directory / str(uid)
            folder.mkdir(mode=0o700)
            try:
                item["files"] = extract_attachments(raw, folder, max_files, limit)
                item["state"] = "matched" if item["files"] else "missing_attachment"
            except ValueError:
                item["state"] = "oversized"
        return {"epoch": epoch, "messages": messages}


if __name__ == "__main__":
    try:
        settings = json.load(sys.stdin)
        operation = scan_new if "cursor" in settings else fetch_latest
        print(json.dumps(operation(settings, Path(sys.argv[1]))))
    except (OSError, ValueError, imaplib.IMAP4.error):
        sys.exit(1)
