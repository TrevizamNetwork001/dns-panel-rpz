#!/bin/sh
exec cat -- "${DNS_RECURSIVE_SSH_PASSWORD_FILE:?}"
