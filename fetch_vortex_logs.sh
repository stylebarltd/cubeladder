#!/bin/bash
# Imports the newest logs of the Vortex game servers, which upload their logs to
# a Cloudflare gateway (HTTPS + bearer token). Same shape as fetch_ftp_logs.sh:
# called by process_logs*.sh; runs on its own too (locally: ddev exec ./fetch_vortex_logs.sh).
#
# Gateway (see the handover from the Vortex admin):
#   GET /servers                 -> {"servers": [id, ...], "cursor": next page or null}
#   GET /files?server=ID         -> {"logs": [{"filename", "path"}, ...]} (oldest first)
#   GET /logfile/ID/FILENAME     -> the complete log; the newest one keeps growing
# Auth: "Authorization: Bearer $VORTEX_TOKEN" (.env.local). The offset in
# log_offsets skips what is imported already, so re-downloading a growing file is fine.
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

GATEWAY="${VORTEX_GATEWAY:-https://vortex-ladder-gateway.vortexassaultcube.workers.dev}"

# ladder server key (games.server_name, Ladder.servers in config/app.php) => gateway server id
declare -A VORTEX_SERVERS=(
    ["vortex-usa-west"]="vortex-usa-ctf-96a2cf41e6"              # Vortex CTF USA West, logs are local#5625
    ["vortex-usa-east"]="vortex-us-east-coast-ctf-7519db72ad"    # Vortex CTF USA East
    ["vortex-brazil"]="vortex-brazil-ctf-533df59e2a"             # Vortex CTF Brazil
    ["vortex-germany"]="vortex-germany-ctf-45593f565f"           # Vortex CTF Germany
    ["vortex-singapore"]="vortex-singapore-ctf-c824d24e74"       # Vortex CTF Singapore
    ["vortex-uk"]="vortex-united-kingdom-ctf-2a2ed4ffcf"         # Vortex CTF UK
)

# VORTEX_TOKEN in .env.local (skipped when unset). The token goes to curl via a
# config file on stdin, so it never shows up in the process list.
if [[ -z "${VORTEX_TOKEN:-}" ]]; then
    exit 0
fi
v_curl() { printf 'header = "Authorization: Bearer %s"\n' "$VORTEX_TOKEN" | curl -K - -s -S --connect-timeout 10 "$@"; }

# the newest log, and the one before it when the server just started a new one
# (its last lines). Nothing older than VORTEX_SINCE (yyyymmdd): the ladder
# starts there for these servers.
SINCE="serverlog_${VORTEX_SINCE:-20261024}"

for key in "${!VORTEX_SERVERS[@]}"; do
    id="${VORTEX_SERVERS[$key]}"
    echo "==============================="
    echo "Processing server: $key (Vortex gateway, $id)"
    echo "==============================="

    if ! listing=$(v_curl --max-time 30 "$GATEWAY/files?server=$id" 2>&1); then
        echo "Vortex listing failed for $key: $(echo "$listing" | tail -n 1)" >> "$LOGFILE"
        continue
    fi
    # "filename<TAB>path" per log, newest two that are not older than SINCE
    files=$(printf '%s' "$listing" | php -r '
        $d = json_decode(stream_get_contents(STDIN), true);
        foreach ($d["logs"] ?? [] as $l) echo $l["filename"], "\t", $l["path"], "\n";' \
        | sort | awk -F'\t' -v since="$SINCE" '$1 >= since' | tail -n 2 || true)

    if [[ -z "$files" ]]; then
        echo "No log file found on the Vortex gateway for $key" >> "$LOGFILE"
        continue
    fi
    while IFS=$'\t' read -r file path; do
        [[ -z "$file" ]] && continue
        echo "✔ $key: $file"
        if err=$(v_curl --max-time 300 -o "$TMP_DIR/$file" "$GATEWAY$path" 2>&1); then
            echo "➡ Parsing $key → $file" >> "$LOGFILE"
            "$BASE_DIR/bin/cake" ProcessLogs "$key" "$file" "$TMP_DIR/$file" >> "$LOGFILE" 2>&1 \
                || echo "ProcessLogs $key failed!" >> "$LOGFILE"
        else
            echo "Vortex download failed for $key: $file ($(echo "$err" | tail -n 1))" >> "$LOGFILE"
        fi
    done <<< "$files"
done
