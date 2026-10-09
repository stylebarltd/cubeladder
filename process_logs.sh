#!/bin/bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"

# server logins / credentials live in .env.local (not in git) - see .env.local.example
if [ -f "$BASE_DIR/.env.local" ]; then . "$BASE_DIR/.env.local"; else echo "missing $BASE_DIR/.env.local" >&2; exit 1; fi

PROCESSOR_LOG_DIR="$BASE_DIR/logs"
TMP_DIR="$BASE_DIR/tmp"
LOGFILE="$PROCESSOR_LOG_DIR/logfile_server_processor.log"

mkdir -p "$TMP_DIR"

declare -A SERVERS
SERVERS["chobbz"]="${CHOBBZ_SSH}|${CHOBBZ_SSH_KEY}|/serverlogs"
SERVERS["acka"]="${ACKA_SSH}|${ACKA_SSH_KEY}|/home/AssaultCube_v1.3/logs"


declare -A LOG_PATTERNS=(
    ["acka-europa"]="Europa"
    ["acka-custom"]="Custom"
    ["acka-nostalgic"]="Nostalgic"
    ["acka-assault"]="Assault"
# Chobbz instances
    ["chobbz-banana"]="local#1111"
    ["chobbz-potato"]="local#2222"
)

for server_key in "${!SERVERS[@]}"; do

    unset NEWEST_FILES
    declare -A NEWEST_FILES=()

    IFS="|" read -r SSH_TARGET SSH_KEY REMOTE_LOG_DIR <<< "${SERVERS[$server_key]}"

    echo "==============================="
    echo "Processing server: $server_key"
    echo "==============================="

SFTP_LIST=$(
sftp -i "$SSH_KEY" \
    -oBatchMode=yes \
    -oStrictHostKeyChecking=no \
    -oUserKnownHostsFile=/dev/null \
    "$SSH_TARGET" <<EOF 2>/dev/null | awk '{print $NF}'
ls $REMOTE_LOG_DIR/serverlog_*.txt
bye
EOF
)

    for pattern_key in "${!LOG_PATTERNS[@]}"; do

        if [[ "$pattern_key" == "$server_key"* ]]; then

            suffix="${LOG_PATTERNS[$pattern_key]}"

            matches=$(echo "$SFTP_LIST" | grep "_${suffix}\.txt$" || true)
            #newest=$(echo "$matches" | sort -r | head -n 1)
            newest=$(echo "$matches" | sort -r | head -n 1 | xargs basename)

            if [[ -n "$newest" ]]; then
                echo "✔ Newest for $pattern_key: $newest"
                NEWEST_FILES["$pattern_key"]="$newest"
            fi
        fi
    done

    if [[ ${#NEWEST_FILES[@]} -eq 0 ]]; then
        echo "No matching log files found."
        continue
    fi

    DOWNLOAD_BATCH="$(mktemp)"

    {
        echo "cd $REMOTE_LOG_DIR"
        echo "lcd $TMP_DIR"

        for key in "${!NEWEST_FILES[@]}"; do
            #echo "get ${NEWEST_FILES[$key]}"
            #echo "get \"${NEWEST_FILES[$key]}\""
            escaped_file="${NEWEST_FILES[$key]//#/\\#}"
            echo "get $escaped_file"
        done

        echo "bye"
    } > "$DOWNLOAD_BATCH"

    sftp \
        -i "$SSH_KEY" \
        -oBatchMode=yes \
        -oStrictHostKeyChecking=no \
        -b "$DOWNLOAD_BATCH" \
        "$SSH_TARGET"
    rm -f "$DOWNLOAD_BATCH"

    for key in "${!NEWEST_FILES[@]}"; do

        file="${NEWEST_FILES[$key]}"
        local_file="$TMP_DIR/$file"

        echo "➡ Parsing $key → $file" >> "$LOGFILE"

        "$BASE_DIR/bin/cake" ProcessLogs \
            "$key" \
            "$file" \
            "$local_file" \
            >> "$LOGFILE" 2>&1

        echo "ProcessLogs exit code: $?"
    done

done

echo "➡ CleanUp"
if ! "$BASE_DIR/bin/cake" CleanUp >> "$LOGFILE" 2>&1; then
    echo "CleanUp failed!" >> "$LOGFILE"
fi

echo "➡ GeoPlayers"
if ! "$BASE_DIR/bin/cake" GeoPlayers >> "$LOGFILE" 2>&1; then
    echo "GeoPlayers failed!" >> "$LOGFILE"
fi

echo "➡ DiscordResults"
# results of the games finished by this import -> Discord results channel
if ! "$BASE_DIR/bin/cake" discord_results >> "$LOGFILE" 2>&1; then
    echo "DiscordResults failed!" >> "$LOGFILE"
fi
