#!/bin/bash
# Imports the newest log of each FTP game server (Barry's B server), like
# process_logs.sh does for the SFTP ones. Called by process_logs*.sh; runs
# on its own too (locally: ddev exec ./fetch_ftp_logs.sh).
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -f "$BASE_DIR/.env.local" ]; then . "$BASE_DIR/.env.local"; fi
TMP_DIR="$BASE_DIR/tmp"
LOGFILE="$BASE_DIR/logs/logfile_server_processor.log"
mkdir -p "$TMP_DIR" "$BASE_DIR/logs"

# same lock as process_logs.sh, so a run by hand never overlaps the cron run
if [[ -z "${PROCESS_LOGS_LOCKED:-}" ]]; then
    export PROCESS_LOGS_LOCKED=1
    exec flock -w 900 "$TMP_DIR/process_logs.lock" "$0" "$@"
fi

# B_FTP_HOST / B_FTP_PORT / B_FTP_USER / B_FTP_PASS in .env.local (skipped when unset).
# The login goes to curl on stdin, so it never shows up in the process list.
if [[ -n "${B_FTP_HOST:-}" ]]; then
    echo "==============================="
    echo "Processing server: barry-b (FTP)"
    echo "==============================="
    B_FTP_DIR="${B_FTP_DIR:-/}"
    B_FTP_URL="ftp://${B_FTP_HOST}:${B_FTP_PORT:-21}${B_FTP_DIR%/}/"
    b_login=$(printf '%s:%s' "$B_FTP_USER" "$B_FTP_PASS" | sed 's/[\\"]/\\&/g')
    b_curl() { printf 'user = "%s"\n' "$b_login" | curl -K - -s --ftp-pasv --disable-epsv --connect-timeout 10 "$@"; }

    # the newest log, and the one before it when the server just started a new one
    # (its last lines; the offset in log_offsets skips what is imported already).
    # Nothing older than B_FTP_SINCE: the ladder starts there for this server.
    # curl's own error (-S) goes to the log, so an unreachable FTP doesn't
    # look like an empty one (the error names host and port, never the login)
    if ! listing=$(b_curl -S --max-time 30 --list-only "$B_FTP_URL" 2>&1); then
        echo "FTP listing failed for barry-b: $(echo "$listing" | tail -n 1)" >> "$LOGFILE"
        exit 0
    fi
    files=$(echo "$listing" \
        | tr -d '\r' | grep '^serverlog_.*\.txt$' | grep -- "${B_FTP_MATCH:-local}" | sort | tail -n 2 \
        | awk -v since="serverlog_${B_FTP_SINCE:-20261008}" '$0 >= since' || true)

    if [[ -z "$files" ]]; then
        echo "No log file found on the barry-b FTP" >> "$LOGFILE"
    fi
    for file in $files; do
        echo "✔ barry-b: $file"
        if err=$(b_curl -S --max-time 120 -o "$TMP_DIR/$file" "$B_FTP_URL${file//#/%23}" 2>&1); then
            echo "➡ Parsing barry-b → $file" >> "$LOGFILE"
            "$BASE_DIR/bin/cake" ProcessLogs barry-b "$file" "$TMP_DIR/$file" >> "$LOGFILE" 2>&1 \
                || echo "ProcessLogs barry-b failed!" >> "$LOGFILE"
        else
            echo "FTP download failed for barry-b: $file ($(echo "$err" | tail -n 1))" >> "$LOGFILE"
        fi
    done
fi
