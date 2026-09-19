#!/bin/bash
 #---------------------------------------------------------------------------
 # Version | Date       | Author              | Description
 # ----------------------------------------------------------------------------
 # 1.0     | 2026-06-27 | LK27062026          | Check folder permission and set it 
 #                                               if not already configured.
 #         |            |                     | 
 # ----------------------------------------------------------------------------
# ============================================================
# ============================================================
# ---------- CONFIG ----------
# REMOTE_FOLDER="/var/www/html/bi/dist/LKtestingfolder"
REMOTE_FOLDER="/var/lib/mysql-files"
PERMISSION="755"                   # Required permission (755 = full read/write/execute)
LOG_FILE="/home/pipewaydb/folder_permission.txt"
# ----------------------------

# Log function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "========================================"
log "Script started"
log "Target Folder : $REMOTE_FOLDER"

# Check if folder exists
if [ ! -d "$REMOTE_FOLDER" ]; then
    log "ERROR: Folder '$REMOTE_FOLDER' not found!"
    exit 1
fi

# Get current permission in octal format
CURRENT_PERM=$(stat -c "%a" "$REMOTE_FOLDER")
log "Current permission : $CURRENT_PERM"

# Check if required permission is already set
if [ "$CURRENT_PERM" = "$PERMISSION" ]; then
    log "OK: Folder already has permission $CURRENT_PERM. No changes required."
else
    log "Current permission is $CURRENT_PERM, required is $PERMISSION. Updating now..."

    # Set the required permission
    chmod "$PERMISSION" "$REMOTE_FOLDER"

    if [ $? -eq 0 ]; then
        log "SUCCESS: Permission successfully updated to $PERMISSION."
    else
        log "ERROR: Failed to set permission on the folder!"
        exit 1
    fi
fi

log "Script finished"
log "========================================"
exit 0