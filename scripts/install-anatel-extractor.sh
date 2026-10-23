#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENV_DIR="${PROJECT_DIR}/.venv-anatel"
python3 -m venv "${VENV_DIR}"
"${VENV_DIR}/bin/python" -m pip install --disable-pip-version-check --upgrade pip
"${VENV_DIR}/bin/python" -m pip install --disable-pip-version-check -r "${PROJECT_DIR}/docker/requirements-anatel.txt"
"${VENV_DIR}/bin/python" -c 'import pdfplumber, openpyxl; print("Extrator ANATEL pronto")'
