#!/bin/bash
set -euo pipefail

#####################################
# LOCKFILE
#####################################

#LOCKFILE="tmp/logfile_server_processor.lock"
#exec 9>"$LOCKFILE" || exit 1
#flock -n 9 || exit 0

#####################################
# PATHS
#####################################

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"

PROCESSOR_LOG_DIR="$BASE_DIR/logs"
TMP_DIR="$BASE_DIR/tmp"
LOGFILE="$PROCESSOR_LOG_DIR/logfile_server_processor.log"

#
######################################
## FTP SERVER CONFIG (B SERVER)
######################################
#
#FTP_HOST="${B_FTP_HOST}"
#FTP_PORT="2121"
#FTP_USER="${B_FTP_USER}"
#FTP_PASS="${B_FTP_PASS}"
#
#FTP_URL="ftp://${FTP_HOST}:${FTP_PORT}"
#FTP_TMP_DIR="$TMP_DIR"
#SERVER_NAME="b-server"
#
######################################
## FIND NEWEST FTP FILE
######################################
#
#echo "➡ Checking FTP log for b-server"
#
#set +e
#FTP_RAW_LIST=$(
#    curl -s \
#         --ftp-pasv \
#         --disable-epsv \
#         --ftp-method singlecwd \
#         --user "$FTP_USER:$FTP_PASS" \
#         "$FTP_URL/"
#)
#set -e
#
#
#echo "---- FTP RAW LIST ----"
#echo "$FTP_RAW_LIST"
#echo "----------------------"
#
#
#latest_file=$(
#    echo "$FTP_RAW_LIST" \
#    | grep '^serverlog_' \
#    | grep 'local' \
#    | sort -r \
#    | head -n 1
#)
#
#if [[ -z "$latest_file" ]]; then
#    echo "⚠ No FTP log found for b-server"
#    exit 0
#fi
#
#echo "✔ Newest FTP log: $latest_file"
#
#
#encoded_file="${latest_file//#/%23}"
#LOCAL_FILE="$FTP_TMP_DIR/$latest_file"
#
#curl --ftp-pasv \
#     --connect-timeout 10 \
#     --max-time 60 \
#     -s \
#     --user "$FTP_USER:$FTP_PASS" \
#     "$FTP_URL/$encoded_file" \
#     -o "$LOCAL_FILE"
#
#
######################################
## PROCESS FTP LOG
######################################
#
#bin/cake ProcessLogs \
#    "$SERVER_NAME" \
#    "$latest_file" \
#    "$LOCAL_FILE" \
#    >> "$LOGFILE" 2>&1
#



#####################################
# SFTP CONFIG
#####################################

SFTP_USER="${ACKA_SSH_USER}"
SFTP_HOST="${ACKA_SSH_HOST}"
SFTP_KEY="$HOME/.ssh/id_acka"
REMOTE_LOG_DIR="/home/AssaultCube_v1.3/logs"

declare -A LOG_PATTERNS=(
    ["acka-europa"]="Europa"
    ["acka-custom"]="Custom"
    ["acka-nostalgic"]="Nostalgic"
    ["acka-assault"]="Assault"
)

#####################################
# LIST FILES (CLEAN FILENAMES ONLY)
#####################################
echo "LIST BATCH Start"

SFTP_LIST=$(
    ssh -i "$SFTP_KEY" \
        -oBatchMode=yes \
        -oStrictHostKeyChecking=no \
        -oUserKnownHostsFile=/dev/null \
        "$SFTP_USER@$SFTP_HOST" \
        "ls -1 $REMOTE_LOG_DIR/serverlog_*.txt" \
    | xargs -n1 basename
)

echo "LIST BATCH End"


#####################################
# SELECT NEWEST FILE PER SERVER
#####################################
echo "NEWEST_FILES Start"
declare -A NEWEST_FILES

for server in "${!LOG_PATTERNS[@]}"; do
    suffix="${LOG_PATTERNS[$server]}"

    newest=$(
        echo "$SFTP_LIST" \
        | grep "_${suffix}\.txt$" \
        | sort -r \
        | head -n 1
    )

    if [[ -n "$newest" ]]; then
        echo "✔ Newest for $server: $newest"
        NEWEST_FILES["$server"]="$newest"
    fi
done

echo "NEWEST_FILES End"

#####################################
# DOWNLOAD NEWEST FILES (FULL FILE)
#####################################
echo "DOWNLOAD Start"

DOWNLOAD_BATCH="$(mktemp)"

{
    echo "cd $REMOTE_LOG_DIR"
    echo "lcd $TMP_DIR"

    for server in "${!NEWEST_FILES[@]}"; do
        echo "get ${NEWEST_FILES[$server]}"
    done

    echo "bye"
} > "$DOWNLOAD_BATCH"

sftp \
    -i "$SFTP_KEY" \
    -oBatchMode=yes \
    -oStrictHostKeyChecking=no \
    -b "$DOWNLOAD_BATCH" \
    "$SFTP_USER@$SFTP_HOST"

rm -f "$DOWNLOAD_BATCH"

echo "DOWNLOAD End"

#####################################
# PROCESS ACKA FILES (NO STDIN)
#####################################
echo "Process Acka Start"
for server in "${!NEWEST_FILES[@]}"; do
    file="${NEWEST_FILES[$server]}"
    local_file="$TMP_DIR/$file"
    bin_file="$BASE_DIR/bin/cake"

    echo "➡ Parsing $server → $file" >> "$LOGFILE"

    "$bin_file" ProcessLogs \
        "$server" \
        "$file" \
        "$local_file" \
        >> "$LOGFILE" 2>&1
done
echo "Process Acka End"

