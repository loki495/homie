#!/bin/sh
# Forced command handler for the homie demo output-card SSH sandbox (see
# docker/ssh-sandbox/README.md). Set as the sole authorized_keys `command=`
# for the sandbox's one key, so this runs instead of whatever the SSH client
# actually asked for - $SSH_ORIGINAL_COMMAND holds that original request, and
# only an exact, fixed allowlist of harmless read-only commands is permitted
# regardless of it. Everything else is rejected outright, no exceptions.
set -eu

# A client connecting without specifying a remote command (an interactive
# session attempt) leaves this genuinely unset, not empty - handle it as a
# clean rejection rather than letting `set -u` crash with a raw shell error
# that could leak this script's own path to the client.
SSH_ORIGINAL_COMMAND="${SSH_ORIGINAL_COMMAND:-}"

case "$SSH_ORIGINAL_COMMAND" in
    "uptime")
        exec uptime
        ;;
    "df -h")
        exec df -h
        ;;
    "date")
        exec date
        ;;
    "whoami")
        exec whoami
        ;;
    "echo "*)
        # exec'd directly (never through a shell), so there is no injection
        # risk from the argument itself - this check is defense in depth only,
        # to keep output predictable rather than because it's load-bearing.
        arg="${SSH_ORIGINAL_COMMAND#echo }"
        case "$arg" in
            *[\;\&\|\`\$\(\)\<\>\\]*)
                echo "Command not allowed in this demo sandbox." >&2
                exit 1
                ;;
            *)
                exec echo "$arg"
                ;;
        esac
        ;;
    *)
        echo "Command not allowed in this demo sandbox. Try: uptime, df -h, date, whoami, echo <text>" >&2
        exit 1
        ;;
esac
