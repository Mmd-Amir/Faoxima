#!/usr/bin/env bash
# ============================================================================
#  Faoxima Bot Installer
# ----------------------------------------------------------------------------
#  Version : v0.0.1
#  GitHub  : https://github.com/Mmd-Amir/Faoxima
#  Telegram: https://t.me/faoxima
# ----------------------------------------------------------------------------
#  This script installs, updates, removes, and manages a Faoxima bot stack
#  (Apache 2 + PHP 8.2 + MySQL + phpMyAdmin) on Ubuntu/Debian, with optional
#  side-by-side support for an existing Marzban panel (Docker MySQL).
# ============================================================================
# shellcheck shell=bash
# shellcheck disable=SC2155,SC2034,SC2317

set -o pipefail

# ─── METADATA ──────────────────────────────────────────────────────────────
readonly FAOXIMA_VERSION="v0.0.1"
readonly FAOXIMA_REPO="Mmd-Amir/Faoxima"
readonly FAOXIMA_GITHUB="https://github.com/${FAOXIMA_REPO}"
readonly FAOXIMA_TELEGRAM="https://t.me/faoxima"

# ─── PATHS ─────────────────────────────────────────────────────────────────
readonly BOT_DIR="/var/www/html/faoxima"
readonly CRED_DIR="/root/conffaoxima"
readonly CRED_FILE="${CRED_DIR}/dbrootfaoxima.txt"
readonly LOG_FILE="/var/log/faoxima_installer.log"
readonly TMP_DOWNLOAD="/tmp/faoxima_download"
readonly TMP_UPDATE="/tmp/faoxima_update"
readonly DEFAULT_DB_NAME="faoxima"
readonly INSTALL_SCRIPT_PATH="/root/install.sh"
readonly INSTALL_SCRIPT_LINK="/usr/local/bin/faoxima"

# ─── ROOT GUARD ────────────────────────────────────────────────────────────
if [ "$(id -u)" -ne 0 ]; then
    printf '\033[1;31m[ERROR]\033[0m This script must be run as \033[1mroot\033[0m.\n' >&2
    exit 1
fi

# ─── ANSI COLOR PALETTE ────────────────────────────────────────────────────
readonly C_RESET=$'\033[0m'
readonly C_BOLD=$'\033[1m'
readonly C_DIM=$'\033[2m'
readonly C_RED=$'\033[1;31m'
readonly C_GREEN=$'\033[1;32m'
readonly C_YELLOW=$'\033[1;33m'
readonly C_BLUE=$'\033[1;34m'
readonly C_MAGENTA=$'\033[1;35m'
readonly C_CYAN=$'\033[1;36m'
readonly C_WHITE=$'\033[1;37m'
readonly C_GRAY=$'\033[38;5;245m'
readonly C_PINK=$'\033[38;5;205m'
readonly C_ORANGE=$'\033[38;5;215m'

# ============================================================================
#  UI LIBRARY — rounded panels, key/value tables, tips
# ----------------------------------------------------------------------------
#  Inspired by Python's `rich` library. Uses Unicode box-drawing characters
#  to render a modern terminal UI even over plain SSH.
# ============================================================================

# Determine usable terminal width, clamped to a sane range.
ui_term_width() {
    local w
    w=$(tput cols 2>/dev/null || echo 80)
    [[ -z "$w" || "$w" -lt 60 ]] && w=80
    [[ "$w" -gt 110 ]] && w=110
    printf '%d' "$w"
}

# Strip ANSI sequences from a string and print its visible length.
ui_strlen() {
    local stripped
    stripped=$(printf '%s' "$1" | sed -E $'s/\033\\[[0-9;]*[A-Za-z]//g')
    printf '%d' "${#stripped}"
}

# Repeat a UTF-8 character N times.
ui_repeat() {
    local ch="$1" n="$2" out="" i=0
    while [ "$i" -lt "$n" ]; do
        out+="$ch"
        i=$((i + 1))
    done
    printf '%s' "$out"
}

# Print spaces N times.
ui_spaces() { printf '%*s' "$1" ''; }

# ─── PANEL PRIMITIVES ──────────────────────────────────────────────────────
# Top border with centered title.
# Args: $1=title $2=title_color $3=border_color $4=width
ui_box_top() {
    local title="$1" title_color="$2" border_color="$3" width="$4"
    local inner=$((width - 2))
    local title_text=" ${title} "
    local title_len=${#title_text}
    local pad_left=$(( (inner - title_len) / 2 ))
    local pad_right=$(( inner - title_len - pad_left ))
    [ "$pad_left" -lt 1 ] && pad_left=1
    [ "$pad_right" -lt 1 ] && pad_right=1

    printf '%s╭%s%s%s%s%s%s%s%s╮%s\n' \
        "$border_color" \
        "$(ui_repeat '─' "$pad_left")" \
        "$C_RESET" "$title_color" "$title_text" "$C_RESET" "$border_color" \
        "$(ui_repeat '─' "$pad_right")" "$C_RESET" \
        "$C_RESET"
}

# Plain top border (no title).
ui_box_top_plain() {
    local border_color="$1" width="$2"
    printf '%s╭%s╮%s\n' "$border_color" "$(ui_repeat '─' $((width - 2)))" "$C_RESET"
}

# Empty padded line.
ui_box_blank() {
    local border_color="$1" width="$2"
    printf '%s│%s│%s\n' "$border_color" "$(ui_spaces $((width - 2)))" "$C_RESET"
}

# Content line with given (already-colored) text.
# Args: $1=border_color $2=width $3=content
ui_box_line() {
    local border_color="$1" width="$2" content="$3"
    local inner=$((width - 4))           # 2 borders + 2 padding spaces
    local visible_len pad
    visible_len=$(ui_strlen "$content")
    pad=$((inner - visible_len))
    [ "$pad" -lt 0 ] && pad=0
    printf '%s│%s %b%s %s│%s\n' \
        "$border_color" "$C_RESET" \
        "$content" "$(ui_spaces $pad)" \
        "$border_color" "$C_RESET"
}

# Divider line (├─┤) inside a panel.
ui_box_divider() {
    local border_color="$1" width="$2"
    printf '%s├%s┤%s\n' "$border_color" "$(ui_repeat '─' $((width - 2)))" "$C_RESET"
}

# Bottom border (╰──╯).
ui_box_bottom() {
    local border_color="$1" width="$2"
    printf '%s╰%s╯%s\n' "$border_color" "$(ui_repeat '─' $((width - 2)))" "$C_RESET"
}

# ─── HIGH-LEVEL PANEL ──────────────────────────────────────────────────────
# Print a rounded panel with a title and one or more lines of content.
# Args:
#   $1 = title
#   $2 = title color
#   $3 = border color
#   $@ (rest) = content lines (may include ANSI colors)
ui_panel() {
    local title="$1" title_color="$2" border_color="$3"
    shift 3
    local width
    width=$(ui_term_width)

    ui_box_top "$title" "$title_color" "$border_color" "$width"
    ui_box_blank "$border_color" "$width"
    local line
    for line in "$@"; do
        ui_box_line "$border_color" "$width" "$line"
    done
    ui_box_blank "$border_color" "$width"
    ui_box_bottom "$border_color" "$width"
}

# Print a panel with a single one-line tip (used for short hints).
ui_tip() {
    local message="$1"
    local width
    width=$(ui_term_width)
    ui_box_top_plain "$C_GREEN" "$width"
    ui_box_line "$C_GREEN" "$width" "${C_GREEN}${C_BOLD}Tip:${C_RESET} ${message}"
    ui_box_bottom "$C_GREEN" "$width"
}

# Print a key/value status table inside a panel.
# Usage:  ui_status_table "TITLE" "border_color" "Key1|Value1" "Key2|Value2" ...
ui_status_table() {
    local title="$1" border_color="$2"
    shift 2
    local width pair key val key_w=0
    width=$(ui_term_width)

    # Find the widest key to align the colons.
    local pair
    for pair in "$@"; do
        key="${pair%%|*}"
        [ "${#key}" -gt "$key_w" ] && key_w=${#key}
    done
    [ "$key_w" -gt 24 ] && key_w=24

    ui_box_top "$title" "$C_CYAN" "$border_color" "$width"
    ui_box_blank "$border_color" "$width"
    for pair in "$@"; do
        key="${pair%%|*}"
        val="${pair#*|}"
        local padded_key
        padded_key=$(printf '%-*s' "$key_w" "$key")
        ui_box_line "$border_color" "$width" "${C_CYAN}${padded_key}${C_RESET}  ${C_WHITE}${val}${C_RESET}"
    done
    ui_box_blank "$border_color" "$width"
    ui_box_bottom "$border_color" "$width"
}

# ─── INLINE STATUS LINES ───────────────────────────────────────────────────
ui_info()    { printf '  %s●%s %s\n' "$C_BLUE"   "$C_RESET" "$*"; }
ui_ok()      { printf '  %s✓%s %s\n' "$C_GREEN"  "$C_RESET" "$*"; }
ui_warn()    { printf '  %s!%s %s\n' "$C_YELLOW" "$C_RESET" "$*"; }
ui_err()     { printf '  %s✗%s %s\n' "$C_RED"    "$C_RESET" "$*"; }
ui_action()  { printf '  %s→%s %s\n' "$C_CYAN"   "$C_RESET" "$*"; }

# Section divider — single thin rule across the screen.
ui_rule() {
    local width
    width=$(ui_term_width)
    printf '%s%s%s\n' "$C_GRAY" "$(ui_repeat '─' "$width")" "$C_RESET"
}

# ============================================================================
#  LOGGING
# ============================================================================
init_logging() {
    local log_dir
    log_dir="$(dirname "$LOG_FILE")"
    [ -d "$log_dir" ] || mkdir -p "$log_dir"
    [ -f "$LOG_FILE" ] || touch "$LOG_FILE"
    chmod 600 "$LOG_FILE" 2>/dev/null || true
}

log_message() {
    local level="$1"; shift
    local message="$*"
    local timestamp color
    timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
    case "$level" in
        INFO)   color="$C_BLUE"   ;;
        WARN)   color="$C_YELLOW" ;;
        ERROR)  color="$C_RED"    ;;
        ACTION) color="$C_CYAN"   ;;
        *)      color="$C_RESET"  ;;
    esac
    printf '%s[%s]%s %s\n' "$color" "$level" "$C_RESET" "$message"
    printf '%s [%s] %s\n' "$timestamp" "$level" "$message" >>"$LOG_FILE" 2>/dev/null || true
}

log_action() { log_message "ACTION" "$@"; }
log_info()   { log_message "INFO"   "$@"; }
log_warn()   { log_message "WARN"   "$@"; }
log_error()  { log_message "ERROR"  "$@"; }

init_logging
log_info "Faoxima installer ${FAOXIMA_VERSION} initialized (PID $$)"

# ============================================================================
#  TOKEN GENERATION (admin panel token)
# ============================================================================
# Generate a 48-character hex token (24 random bytes).
faoxima_generate_token() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 24
    else
        # Fallback — read from /dev/urandom.
        head -c 24 /dev/urandom | xxd -p -c 24
    fi
}

# Display the generated token in a highlighted panel and require the user
# to type 'y' (or 'yes') to confirm they have copied/saved it before continuing.
faoxima_show_and_confirm_token() {
    local token="$1" reply
    printf '\n'
    ui_panel "ADMIN PANEL TOKEN — SAVE THIS NOW" "$C_BOLD$C_RED" "$C_RED" \
        "${C_YELLOW}This token has been auto-generated and saved to:${C_RESET}" \
        "${C_DIM}  ${BOT_DIR}/config.php  →  \$admin_panel_token${C_RESET}" \
        "" \
        "${C_WHITE}${C_BOLD}${token}${C_RESET}" \
        "" \
        "${C_DIM}Copy it now to a password manager or secure note —${C_RESET}" \
        "${C_DIM}you'll need it again if you re-run the bot setup.${C_RESET}"
    ui_tip "Once saved, press ${C_BOLD}y${C_RESET}${C_GREEN} (or yes)${C_RESET}${C_GREEN} to continue.${C_RESET}"

    while true; do
        printf '\n  %s❯%s Have you copied and saved the token securely? [y/N]: ' "$C_YELLOW" "$C_RESET"
        read -r reply
        case "${reply,,}" in
            y|yes)
                ui_ok "Token saved confirmation received. Continuing..."
                printf '\n'
                return 0
                ;;
            n|no|"")
                ui_warn "Please save the token before continuing."
                ;;
            *)
                ui_warn "Please answer 'y' (yes) or 'n' (no)."
                ;;
        esac
    done
}

# Insert/replace the $admin_panel_token line inside config.php.
# If the variable already exists it is replaced; otherwise it's appended
# right after the opening <?php tag.
faoxima_persist_token() {
    local config_path="$1" token="$2"
    [ -f "$config_path" ] || return 1

    if grep -q '^\$admin_panel_token' "$config_path"; then
        sed -i "s|^\$admin_panel_token\s*=.*|\$admin_panel_token = '${token}';|" "$config_path"
    else
        # Insert after the first <?php line.
        sed -i "0,/^<?php/{s|^<?php|<?php\n\$admin_panel_token = '${token}';|}" "$config_path"
    fi
}

# ============================================================================
#  ANIMATIONS — kept light and skippable on slow terminals
# ============================================================================
type_text() {
    local text="$1"
    local delay="${2:-0.02}"
    local i=0
    while [ "$i" -lt "${#text}" ]; do
        printf '%s' "${text:$i:1}"
        sleep "$delay" 2>/dev/null || true
        i=$((i + 1))
    done
    printf '\n'
}

type_text_colored() {
    local color="$1" text="$2" delay="${3:-0.02}"
    printf '%b' "$color"
    type_text "$text" "$delay"
    printf '%b' "$C_RESET"
}

# ============================================================================
#  BRAND LOGO + HEADER
# ============================================================================
show_animated_logo() {
    clear
    printf '\n'
    type_text_colored "$C_RED"     "███████╗  █████╗   ██████╗  ██╗  ██╗ ██╗ ███╗   ███╗  █████╗ " 0.002
    type_text_colored "$C_RED"     "██╔════╝ ██╔══██╗ ██╔═══██╗ ╚██╗██╔╝ ██║ ████╗ ████║ ██╔══██╗" 0.002
    type_text_colored "$C_PINK"    "█████╗   ███████║ ██║   ██║  ╚███╔╝  ██║ ██╔████╔██║ ███████║" 0.002
    type_text_colored "$C_PINK"    "██╔══╝   ██╔══██║ ██║   ██║  ██╔██╗  ██║ ██║╚██╔╝██║ ██╔══██║" 0.002
    type_text_colored "$C_MAGENTA" "██║      ██║  ██║ ╚██████╔╝ ██╔╝ ██╗ ██║ ██║ ╚═╝ ██║ ██║  ██║" 0.002
    type_text_colored "$C_MAGENTA" "╚═╝      ╚═╝  ╚═╝  ╚═════╝  ╚═╝  ╚═╝ ╚═╝ ╚═╝     ╚═╝ ╚═╝  ╚═╝" 0.002
    printf '\n'
    type_text_colored "$C_YELLOW"  "                    Faoxima Bot Installer ${FAOXIMA_VERSION}" 0.01
    type_text_colored "$C_CYAN"    "                    GitHub  : ${FAOXIMA_GITHUB}" 0.01
    type_text_colored "$C_CYAN"    "                    Telegram: ${FAOXIMA_TELEGRAM}" 0.01
    printf '\n'
}

show_logo() { show_animated_logo; }

# ============================================================================
#  STATUS CHECKS (SSL, bot install state)
# ============================================================================
check_ssl_status() {
    local config="${BOT_DIR}/config.php"
    if [ ! -f "$config" ]; then
        ui_warn "Bot config.php not found — SSL status unknown."
        return 0
    fi
    local domain
    domain=$(grep '^\$domainhosts' "$config" 2>/dev/null | cut -d"'" -f2 | cut -d'/' -f1)
    if [ -z "$domain" ]; then
        ui_warn "Domain could not be parsed from config.php."
        return 0
    fi
    local cert="/etc/letsencrypt/live/${domain}/cert.pem"
    if [ ! -f "$cert" ]; then
        ui_warn "SSL certificate not found for domain ${domain}."
        return 0
    fi
    local expiry_date current_date expiry_ts days_remaining
    expiry_date=$(openssl x509 -enddate -noout -in "$cert" | cut -d= -f2)
    current_date=$(date +%s)
    expiry_ts=$(date -d "$expiry_date" +%s 2>/dev/null || echo 0)
    days_remaining=$(( (expiry_ts - current_date) / 86400 ))
    if [ "$days_remaining" -gt 0 ]; then
        ui_ok "SSL Certificate: ${days_remaining} days remaining (Domain: ${domain})"
    else
        ui_err "SSL Certificate: expired (Domain: ${domain})"
    fi
}

check_bot_status() {
    if [ -f "${BOT_DIR}/config.php" ]; then
        ui_ok "Faoxima Bot is installed at ${BOT_DIR}"
        check_ssl_status
    else
        ui_err "Faoxima Bot is not installed"
    fi
}

# ============================================================================
#  APACHE / SSL HELPERS
# ============================================================================
configure_apache_vhost() {
    local domain="$1"
    local docroot="${2:-/var/www/html}"
    local conf="/etc/apache2/sites-available/${domain}.conf"

    if [ -z "$domain" ]; then
        ui_err "Domain name missing while configuring Apache."
        return 1
    fi

    ui_action "Configuring Apache 2 virtual host for ${domain} (DocumentRoot: ${docroot})"
    tee "$conf" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $docroot

    <Directory $docroot>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF

    if ! a2ensite "${domain}.conf" >/dev/null 2>&1; then
        ui_err "Failed to enable Apache 2 site for ${domain}."
        return 1
    fi
    if ! apache2ctl configtest >/dev/null 2>&1; then
        ui_err "Apache 2 configuration test failed after adding ${domain}."
        return 1
    fi
    return 0
}

cleanup_apache_state() {
    if pgrep -x apache2 >/dev/null 2>&1 && ! systemctl is-active --quiet apache2; then
        ui_info "Detected Apache 2 running outside systemd — stopping stale process."
        apachectl stop >/dev/null 2>&1 || pkill -TERM apache2 >/dev/null 2>&1 || true
        rm -f /var/run/apache2/apache2.pid >/dev/null 2>&1 || true
    fi
}

restore_apache_service() {
    cleanup_apache_state
    ui_action "Re-enabling Apache 2 service..."
    systemctl enable apache2 >/dev/null 2>&1 || ui_info "Apache 2 service was already disabled."
    ui_action "Starting Apache 2 service..."
    if ! systemctl start apache2; then
        ui_warn "Apache 2 failed to start cleanly — retrying after cleanup..."
        cleanup_apache_state
        if ! systemctl start apache2; then
            ui_err "Failed to start Apache 2 service!"
        fi
    fi
}

wait_for_certbot() {
    local timeout="${1:-180}"
    local interval=5
    local waited=0
    local lock_paths=(
        "/var/log/letsencrypt/.certbot.lock"
        "/var/lib/letsencrypt/.certbot.lock"
    )

    while true; do
        local lock_found=0
        local lock_file
        for lock_file in "${lock_paths[@]}"; do
            if [ -f "$lock_file" ]; then
                lock_found=1
                break
            fi
        done
        if ! pgrep -x certbot >/dev/null 2>&1 && [ "$lock_found" -eq 0 ]; then
            return 0
        fi
        if [ "$waited" -ge "$timeout" ]; then
            log_error "Certbot lock has been held for more than ${timeout}s. Please ensure no other Certbot process is running."
            return 1
        fi
        log_warn "Certbot is already running; waiting for it to finish..."
        sleep "$interval"
        waited=$((waited + interval))
    done
}

# ============================================================================
#  MARZBAN HELPERS
# ============================================================================
check_marzban_installed() {
    [ -f "/opt/marzban/docker-compose.yml" ]
}

detect_database_type() {
    local compose="/opt/marzban/docker-compose.yml"
    if [ ! -f "$compose" ]; then
        printf 'unknown'
        return 1
    fi
    if grep -q "^[[:space:]]*mysql:" "$compose"; then
        printf 'mysql'
        return 0
    elif grep -q "^[[:space:]]*mariadb:" "$compose"; then
        printf 'mariadb'
        return 1
    fi
    printf 'sqlite'
    return 1
}

find_free_port() {
    local port
    for port in {3300..3330}; do
        if ! ss -tuln | grep -q ":${port} "; then
            printf '%d' "$port"
            return 0
        fi
    done
    ui_err "No free port found between 3300 and 3330."
    exit 1
}

fix_update_issues() {
    ui_warn "Trying to fix update issues by changing apt mirrors..."
    cp /etc/apt/sources.list /etc/apt/sources.list.backup

    local UBUNTU_CODENAME
    if [ -f /etc/os-release ]; then
        # shellcheck source=/dev/null
        . /etc/os-release
        UBUNTU_CODENAME="${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}"
    else
        ui_err "Could not detect Ubuntu version."
        return 1
    fi

    local mirrors=(
        "archive.ubuntu.com"
        "us.archive.ubuntu.com"
        "fr.archive.ubuntu.com"
        "de.archive.ubuntu.com"
        "mirrors.digitalocean.com"
        "mirrors.linode.com"
    )
    local mirror
    for mirror in "${mirrors[@]}"; do
        ui_action "Trying mirror: ${mirror}"
        cat > /etc/apt/sources.list <<EOF
deb http://${mirror}/ubuntu/ ${UBUNTU_CODENAME} main restricted universe multiverse
deb http://${mirror}/ubuntu/ ${UBUNTU_CODENAME}-updates main restricted universe multiverse
deb http://${mirror}/ubuntu/ ${UBUNTU_CODENAME}-security main restricted universe multiverse
EOF
        if apt-get update 2>/dev/null; then
            ui_ok "Successfully updated using mirror: ${mirror}"
            return 0
        fi
    done

    mv /etc/apt/sources.list.backup /etc/apt/sources.list
    ui_err "All mirrors failed. Restored original sources.list"
    return 1
}

# ============================================================================
#  VIEW ERROR LOGS — show PHP/Apache error_log files created under the bot dir
# ============================================================================
view_error_logs() {
    show_logo
    ui_panel "VIEW ERROR LOGS" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Shows error_log / *.log files created under ${BOT_DIR}.${C_RESET}"

    if [ ! -d "$BOT_DIR" ]; then
        ui_err "Bot directory not found: ${BOT_DIR}"
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu; return 1
    fi

    ui_action "Scanning ${BOT_DIR} for log files..."
    local LOGS=()
    mapfile -t LOGS < <(find "$BOT_DIR" -type f \( -name 'error_log' -o -name '*.log' \) 2>/dev/null | sort)

    if [ "${#LOGS[@]}" -eq 0 ]; then
        ui_ok "No error log files found under ${BOT_DIR} — nothing to show."
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu; return 0
    fi

    ui_ok "Found ${#LOGS[@]} log file(s):"
    printf '\n'
    local idx=1 f size
    for f in "${LOGS[@]}"; do
        size=$(du -h "$f" 2>/dev/null | awk '{print $1}')
        printf '  %s%2d)%s %s  %s(%s)%s\n' "$C_YELLOW" "$idx" "$C_RESET" "$f" "$C_DIM" "${size:-?}" "$C_RESET"
        ((idx++))
    done
    printf '  %s%2d)%s Show ALL (last 50 lines of each)\n' "$C_YELLOW" "$idx" "$C_RESET"

    printf '\n  %s❯%s Select a file number (or %d for all, Enter to cancel): ' "$C_YELLOW" "$C_RESET" "$idx"
    local choice; read -r choice
    [ -z "$choice" ] && { show_menu; return 0; }

    local TO_SHOW=()
    if [ "$choice" = "$idx" ]; then
        TO_SHOW=("${LOGS[@]}")
    elif [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -lt "$idx" ]; then
        TO_SHOW=("${LOGS[$((choice-1))]}")
    else
        ui_err "Invalid selection."
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu; return 0
    fi

    local lf
    for lf in "${TO_SHOW[@]}"; do
        ui_rule
        printf '  %s● %s%s  %s(last 50 lines)%s\n' "$C_CYAN" "$lf" "$C_RESET" "$C_DIM" "$C_RESET"
        ui_rule
        tail -n 50 "$lf" 2>/dev/null || ui_err "Could not read ${lf}"
        printf '\n'
    done

    printf '  %s❯%s Clear (empty) the shown log file(s)? (y/N): ' "$C_YELLOW" "$C_RESET"
    local clr; read -r clr
    if [[ "${clr,,}" == "y" ]]; then
        for lf in "${TO_SHOW[@]}"; do
            : > "$lf" 2>/dev/null && ui_ok "Cleared ${lf}" || ui_warn "Could not clear ${lf}"
        done
    fi

    printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
    show_menu
}

# ============================================================================
#  INCREASE UPLOAD LIMIT — raise PHP upload size (for phpMyAdmin DB imports)
# ============================================================================
faoxima_set_ini() {
    local key="$1" val="$2" file="$3"
    if grep -qE "^[[:space:]]*;?[[:space:]]*${key}[[:space:]]*=" "$file"; then
        sed -i -E "s|^[[:space:]]*;?[[:space:]]*${key}[[:space:]]*=.*|${key} = ${val}|" "$file"
    else
        printf '%s = %s\n' "$key" "$val" >> "$file"
    fi
}

increase_upload_limit() {
    show_logo
    ui_panel "INCREASE phpMyAdmin UPLOAD LIMIT" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Raises the PHP upload size so large database backups import via phpMyAdmin.${C_RESET}" \
        "${C_DIM}The default is usually only 2 MB.${C_RESET}"

    local size_mb
    printf '\n  %s❯%s Enter the new max upload size in MB (e.g. 100): ' "$C_YELLOW" "$C_RESET"
    read -r size_mb
    if ! [[ "$size_mb" =~ ^[0-9]+$ ]] || [ "$size_mb" -lt 1 ]; then
        ui_err "Invalid number. Please enter a positive integer (MB)."
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu; return 1
    fi
    local post_mb=$(( size_mb + 16 ))
    local mem_mb=$(( post_mb + 64 ))

    ui_action "Locating PHP configuration files..."
    local INIS=()
    mapfile -t INIS < <(find /etc/php -type f -name php.ini \( -path '*/apache2/*' -o -path '*/fpm/*' -o -path '*/cli/*' \) 2>/dev/null)
    if [ "${#INIS[@]}" -eq 0 ]; then
        local cli_ini
        cli_ini=$(php -i 2>/dev/null | awk -F'=> ' '/Loaded Configuration File/{print $2}' | tr -d ' ')
        [ -n "$cli_ini" ] && [ -f "$cli_ini" ] && INIS+=("$cli_ini")
    fi
    if [ "${#INIS[@]}" -eq 0 ]; then
        ui_err "No php.ini files found under /etc/php."
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu; return 1
    fi

    local ini changed=0
    for ini in "${INIS[@]}"; do
        [ -f "$ini" ] || continue
        cp "$ini" "${ini}.faoxima.bak" 2>/dev/null || true
        faoxima_set_ini "upload_max_filesize" "${size_mb}M" "$ini"
        faoxima_set_ini "post_max_size"       "${post_mb}M" "$ini"
        faoxima_set_ini "memory_limit"        "${mem_mb}M"  "$ini"
        faoxima_set_ini "max_execution_time"  "600"         "$ini"
        faoxima_set_ini "max_input_time"      "600"         "$ini"
        ui_ok "Updated ${ini}"
        ((changed++))
    done

    ui_action "Restarting web server to apply changes..."
    systemctl restart apache2 2>/dev/null || ui_warn "Could not restart apache2 (is it installed?)."
    local fpm
    for fpm in $(systemctl list-units --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}'); do
        systemctl restart "$fpm" 2>/dev/null || true
    done
    ui_ok "Upload limit set to ${size_mb}M (post_max_size ${post_mb}M, memory_limit ${mem_mb}M) in ${changed} file(s)."

    # Read DB credentials from config.php so the user knows how to log into phpMyAdmin
    local CONFIG_PATH="${BOT_DIR}/config.php" DB_USER DB_PASS DB_NAME DOMAIN DOMAIN_HOST
    if [ -f "$CONFIG_PATH" ]; then
        DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        DB_NAME=$(grep '^\$dbname'     "$CONFIG_PATH" | awk -F"'" '{print $2}')
        DOMAIN=$(grep  '^\$domainhosts' "$CONFIG_PATH" | awk -F"'" '{print $2}')
        DOMAIN_HOST="${DOMAIN#http://}"; DOMAIN_HOST="${DOMAIN_HOST#https://}"; DOMAIN_HOST="${DOMAIN_HOST%%/*}"
        printf '\n'
        ui_status_table "phpMyAdmin LOGIN" "$C_GREEN" \
            "phpMyAdmin URL|${C_BLUE}https://${DOMAIN_HOST}/phpmyadmin${C_RESET}" \
            "Database|${C_CYAN}${DB_NAME}${C_RESET}" \
            "Username|${C_CYAN}${DB_USER}${C_RESET}" \
            "Password|${C_CYAN}${DB_PASS}${C_RESET}"
    else
        ui_warn "config.php not found at ${CONFIG_PATH}; skipping DB credential display."
    fi

    ui_tip "You can now import database backups up to ${size_mb} MB in phpMyAdmin."
    printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
    show_menu
}

# ============================================================================
#  MAIN MENU
# ============================================================================
show_menu() {
    show_logo

    # Status panel (SSL + bot install state) above the menu.
    local bot_state ssl_state
    if [ -f "${BOT_DIR}/config.php" ]; then
        bot_state="${C_GREEN}● installed${C_RESET}  ${C_DIM}${BOT_DIR}${C_RESET}"
    else
        bot_state="${C_RED}● not installed${C_RESET}"
    fi
    local domain cert days
    domain=""
    if [ -f "${BOT_DIR}/config.php" ]; then
        domain=$(grep '^\$domainhosts' "${BOT_DIR}/config.php" 2>/dev/null | cut -d"'" -f2 | cut -d'/' -f1)
    fi
    if [ -n "$domain" ] && [ -f "/etc/letsencrypt/live/${domain}/cert.pem" ]; then
        local exp ts now
        exp=$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/${domain}/cert.pem" 2>/dev/null | cut -d= -f2)
        ts=$(date -d "$exp" +%s 2>/dev/null || echo 0)
        now=$(date +%s)
        days=$(( (ts - now) / 86400 ))
        if [ "$days" -gt 0 ]; then
            ssl_state="${C_GREEN}● valid${C_RESET}  ${C_DIM}${days} days remaining (${domain})${C_RESET}"
        else
            ssl_state="${C_RED}● expired${C_RESET}  ${C_DIM}${domain}${C_RESET}"
        fi
    elif [ -n "$domain" ]; then
        ssl_state="${C_YELLOW}● not found${C_RESET}  ${C_DIM}${domain}${C_RESET}"
    else
        ssl_state="${C_DIM}—${C_RESET}"
    fi

    ui_status_table "Faoxima Status" "$C_CYAN" \
        "Version|${C_YELLOW}${FAOXIMA_VERSION}${C_RESET}" \
        "Bot|${bot_state}" \
        "SSL|${ssl_state}"

    printf '\n'

    # Menu panel.
    local width
    width=$(ui_term_width)
    ui_box_top "MAIN MENU" "$C_GREEN$C_BOLD" "$C_GREEN" "$width"
    ui_box_blank "$C_GREEN" "$width"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}1)${C_RESET}  Install Faoxima Bot"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}2)${C_RESET}  Update Faoxima Bot"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}3)${C_RESET}  Remove Faoxima Bot"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}4)${C_RESET}  Export Database"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}5)${C_RESET}  Import Database"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}6)${C_RESET}  Configure Automated Backup"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}7)${C_RESET}  Renew SSL Certificates"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}8)${C_RESET}  Change Domain"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}9)${C_RESET}  Additional Bot Management"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}10)${C_RESET} View Error Logs"
    ui_box_line "$C_GREEN" "$width"  "${C_WHITE}11)${C_RESET} Increase Upload Limit (phpMyAdmin)"
    ui_box_line "$C_GREEN" "$width"  "${C_RED}12)${C_RESET} Remove Domain"
    ui_box_line "$C_GREEN" "$width"  "${C_RED}13)${C_RESET} Delete Cron Jobs"
    ui_box_line "$C_GREEN" "$width"  "${C_RED}14)${C_RESET} Exit"
    ui_box_blank "$C_GREEN" "$width"
    ui_box_bottom "$C_GREEN" "$width"

    printf '\n'
    local option
    printf '  %s❯%s Select an option [1-14]: ' "$C_YELLOW" "$C_RESET"
    read -r option
    case "$option" in
        1)  install_bot ;;
        2)  update_bot ;;
        3)  remove_bot ;;
        4)  export_database ;;
        5)  import_database ;;
        6)  auto_backup ;;
        7)  renew_ssl ;;
        8)  change_domain ;;
        9)  manage_additional_bots ;;
        10) view_error_logs ;;
        11) increase_upload_limit ;;
        12) remove_domain ;;
        13) delete_cron_jobs ;;
        14)
            ui_ok "Exiting... goodbye!"
            exit 0
            ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            show_menu
            ;;
    esac
}

# ============================================================================
#  FILE PERMISSIONS — make the bot files writable by the web server / installer
#  (replaces the standalone "Immigration" option; now reused by install/update)
# ============================================================================
grant_file_permissions() {
    local path="${1:-$BOT_DIR}"
    [ -d "$path" ] || return 0
    ui_action "Setting file permissions for ${path}..."
    [ -f "${path}/config.php" ] && chmod 666 "${path}/config.php" 2>/dev/null
    [ -f "${path}/table.php" ]  && chmod 644 "${path}/table.php"  2>/dev/null
    chmod 755 "$path" 2>/dev/null
    chown -R www-data:www-data "$path" 2>/dev/null
    if [ -d "${path}/app" ]; then
        chmod 755 "${path}/app" 2>/dev/null
        chown -R www-data:www-data "${path}/app" 2>/dev/null
    fi
    ui_ok "File permissions applied"
}

# ============================================================================
#  INSTALL BOT — standalone (no Marzban detected)
# ============================================================================
install_bot() {
    show_logo
    ui_panel "INSTALLATION — STANDALONE" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Installing Apache 2 + PHP 8.2 + MySQL + phpMyAdmin${C_RESET}" \
        "${C_DIM}A fresh stack will be deployed under ${BOT_DIR}${C_RESET}"

    if check_marzban_installed; then
        ui_warn "Marzban detected on this server — switching to Marzban-compatible installer."
        install_bot_with_marzban "$@"
        return 0
    fi

    # ── PPA: ondrej/php ────────────────────────────────────────────────────
    add_php_ppa() {
        add-apt-repository -y ppa:ondrej/php || {
            ui_err "Failed to add PPA ondrej/php."
            return 1
        }
    }
    add_php_ppa_with_locale() {
        LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || {
            ui_err "Failed to add PPA ondrej/php with locale override."
            return 1
        }
    }

    if ! add_php_ppa; then
        ui_warn "Default locale failed, retrying with locale override..."
        if ! add_php_ppa_with_locale; then
            ui_err "Failed to add PPA even with locale override. Exiting..."
            exit 1
        fi
    fi

    # ── apt update + upgrade ───────────────────────────────────────────────
    if ! (apt update && apt upgrade -y); then
        ui_warn "Update/upgrade failed. Attempting alternative mirrors..."
        if fix_update_issues; then
            if apt update && apt upgrade -y; then
                ui_ok "Server updated successfully after fixing mirrors."
            else
                ui_err "Failed to update even after trying alternative mirrors."
                exit 1
            fi
        else
            ui_err "Failed to update/upgrade packages and mirror fix failed."
            exit 1
        fi
    else
        ui_ok "Server packages updated successfully."
    fi

    apt-get install -y software-properties-common || {
        ui_err "Failed to install software-properties-common."
        exit 1
    }

    apt install -y git unzip curl || {
        ui_err "Failed to install required packages."
        exit 1
    }

    DEBIAN_FRONTEND=noninteractive apt install -y php8.2 php8.2-fpm php8.2-mysql || {
        ui_err "Failed to install PHP 8.2 and related packages."
        exit 1
    }

    # ── LAMP stack + Apache 2 modules ──────────────────────────────────────
    local PKG=(
        lamp-server^
        libapache2-mod-php
        mysql-server
        apache2
        php-mbstring
        php-zip
        php-gd
        php-json
        php-curl
    )
    local pkg
    for pkg in "${PKG[@]}"; do
        if dpkg -s "$pkg" &>/dev/null; then
            ui_info "${pkg} is already installed"
        else
            if ! DEBIAN_FRONTEND=noninteractive apt install -y "$pkg"; then
                ui_err "Error installing ${pkg}. Exiting..."
                exit 1
            fi
        fi
    done
    ui_ok "Packages installed, continuing..."

    # ── phpMyAdmin pre-seed + install ──────────────────────────────────────
    echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true'                | debconf-set-selections
    echo 'phpmyadmin phpmyadmin/app-password-confirm password faoximahipass'  | debconf-set-selections
    echo 'phpmyadmin phpmyadmin/mysql/admin-pass password faoximahipass'      | debconf-set-selections
    echo 'phpmyadmin phpmyadmin/mysql/app-pass password faoximahipass'        | debconf-set-selections
    echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2'    | debconf-set-selections

    apt-get install -y phpmyadmin || {
        ui_err "Failed to install phpMyAdmin."
        exit 1
    }
    if [ -f /etc/apache2/conf-available/phpmyadmin.conf ]; then
        rm -f /etc/apache2/conf-available/phpmyadmin.conf
        ui_ok "Removed existing phpMyAdmin configuration."
    fi
    ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf || {
        ui_err "Failed to create symlink for phpMyAdmin configuration."
        exit 1
    }
    a2enconf phpmyadmin.conf || { ui_err "Failed to enable phpMyAdmin configuration."; exit 1; }
    systemctl restart apache2 || { ui_err "Failed to restart Apache 2 service."; exit 1; }

    apt-get install -y php-soap || { ui_err "Failed to install php-soap."; exit 1; }
    apt-get install -y libapache2-mod-php || { ui_err "Failed to install libapache2-mod-php."; exit 1; }

    systemctl enable mysql.service || { ui_err "Failed to enable MySQL service."; exit 1; }
    systemctl start  mysql.service || { ui_err "Failed to start MySQL service.";  exit 1; }
    systemctl enable apache2       || { ui_err "Failed to enable Apache 2 service."; exit 1; }
    systemctl start  apache2       || { ui_err "Failed to start Apache 2 service.";  exit 1; }

    apt-get install -y ufw || { ui_err "Failed to install UFW."; exit 1; }
    ufw allow 'Apache' || { ui_err "Failed to allow Apache 2 in UFW."; exit 1; }
    systemctl restart apache2 || { ui_err "Failed to restart Apache 2 after UFW update."; exit 1; }

    apt-get install -y git wget unzip || { ui_err "Failed to install git/wget/unzip."; exit 1; }
    apt install -y curl                || { ui_err "Failed to install cURL."; exit 1; }
    apt-get install -y php-ssh2        || { ui_err "Failed to install php-ssh2."; exit 1; }
    apt-get install -y libssh2-1-dev libssh2-1 || { ui_err "Failed to install libssh2."; exit 1; }
    apt install -y jq                  || { ui_err "Failed to install jq."; exit 1; }

    systemctl restart apache2.service || { ui_err "Failed to restart Apache 2 service."; exit 1; }

    # ── Bot directory ──────────────────────────────────────────────────────
    if [ -d "$BOT_DIR" ]; then
        ui_warn "Directory ${BOT_DIR} already exists — removing..."
        rm -rf "$BOT_DIR" || { ui_err "Failed to remove existing directory ${BOT_DIR}."; exit 1; }
    fi
    mkdir -p "$BOT_DIR"
    [ -d "$BOT_DIR" ] || { ui_err "Failed to create directory ${BOT_DIR}."; exit 1; }

    # ── Download Faoxima source ────────────────────────────────────────────
    local ZIP_URL
    ZIP_URL=$(curl -s "https://api.github.com/repos/${FAOXIMA_REPO}/releases/latest" | grep "zipball_url" | cut -d '"' -f 4)
    if [[ "$1" == "-v" && "$2" == "beta" ]] || [[ "$1" == "-beta" ]] || [[ "$1" == "-" && "$2" == "beta" ]]; then
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    elif [[ "$1" == "-v" && -n "$2" ]]; then
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/tags/$2.zip"
    fi
    if [ -z "$ZIP_URL" ]; then
        # Fallback to main branch if no release exists yet.
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
        ui_warn "No published release found — falling back to main branch."
    fi

    mkdir -p "$TMP_DOWNLOAD"
    wget -O "${TMP_DOWNLOAD}/bot.zip" "$ZIP_URL" || {
        ui_err "Failed to download Faoxima from ${ZIP_URL}."
        exit 1
    }
    unzip -q "${TMP_DOWNLOAD}/bot.zip" -d "$TMP_DOWNLOAD"
    local extracted_dir
    extracted_dir=$(find "$TMP_DOWNLOAD" -mindepth 1 -maxdepth 1 -type d | head -1)
    mv "${extracted_dir}"/* "$BOT_DIR" || { ui_err "Failed to move extracted files."; exit 1; }
    rm -rf "$TMP_DOWNLOAD"

    chown -R www-data:www-data "$BOT_DIR"
    chmod -R 755 "$BOT_DIR"
    ui_ok "Faoxima source files installed under ${BOT_DIR}"

    # ── Root credentials store ─────────────────────────────────────────────
    wait
    if [ ! -d "$CRED_DIR" ]; then
        mkdir "$CRED_DIR" || { ui_err "Failed to create ${CRED_DIR}."; exit 1; }
        sleep 1
        touch "$CRED_FILE" || { ui_err "Failed to create ${CRED_FILE}."; exit 1; }
        chmod 600 "$CRED_FILE" || true
        sleep 1

        local randomdbpasstxt
        randomdbpasstxt=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
        local ASAS='$'
        {
            echo "${ASAS}user = 'root';"
            echo "${ASAS}pass = '${randomdbpasstxt}';"
            echo "${ASAS}path = '${RANDOM_NUMBER:-faoxima}';"
        } > "$CRED_FILE"

        sleep 1

        local passs userrr
        passs=$(grep '$pass' "$CRED_FILE" | cut -d"'" -f2)
        userrr=$(grep '$user' "$CRED_FILE" | cut -d"'" -f2)

        mysql -u "$userrr" -p"$passs" -e "alter user '${userrr}'@'localhost' identified with mysql_native_password by '${passs}';FLUSH PRIVILEGES;" || {
            ui_warn "Failed to alter MySQL user — attempting recovery..."
            sed -i '$ a skip-grant-tables' /etc/mysql/mysql.conf.d/mysqld.cnf
            systemctl restart mysql
            mysql <<EOF
DROP USER IF EXISTS 'root'@'localhost';
CREATE USER 'root'@'localhost' IDENTIFIED BY '${passs}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
            sed -i '/skip-grant-tables/d' /etc/mysql/mysql.conf.d/mysqld.cnf
            systemctl restart mysql

            echo "SELECT 1" | mysql -u"$userrr" -p"$passs" 2>/dev/null || {
                ui_err "Recovery failed. MySQL login still not working."
                exit 1
            }
        }
        ui_ok "Root credentials saved to ${CRED_FILE}"
    else
        ui_info "Credentials directory already exists at ${CRED_DIR}"
    fi

    # ── SSL / domain ───────────────────────────────────────────────────────
    clear
    show_logo
    ui_panel "SSL CERTIFICATE" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}A free Let's Encrypt certificate will be issued for your domain.${C_RESET}" \
        "${C_DIM}Make sure the domain's A record points to this server before continuing.${C_RESET}"

    local domainname
    printf '\n  %s❯%s Enter the domain: ' "$C_YELLOW" "$C_RESET"
    read -r domainname
    while [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        ui_err "Invalid domain format. Please try again."
        printf '  %s❯%s Enter the domain: ' "$C_YELLOW" "$C_RESET"
        read -r domainname
    done
    local DOMAIN_NAME="$domainname"

    ufw allow 80  || { ui_err "Failed to allow port 80 in UFW.";  exit 1; }
    ufw allow 443 || { ui_err "Failed to allow port 443 in UFW."; exit 1; }

    ui_action "Stopping Apache 2 to free port 80 for certbot..."
    systemctl stop apache2    || { ui_err "Failed to stop Apache 2.";    exit 1; }
    systemctl disable apache2 || { ui_err "Failed to disable Apache 2."; exit 1; }
    apt install -y letsencrypt|| { ui_err "Failed to install letsencrypt.";  exit 1; }
    systemctl enable certbot.timer || { ui_err "Failed to enable certbot timer."; exit 1; }

    if ! wait_for_certbot; then
        ui_err "Certbot is busy. Please try again shortly."
        exit 1
    fi
    certbot certonly --standalone --agree-tos --preferred-challenges http -d "$DOMAIN_NAME" || {
        ui_err "Failed to generate SSL certificate."
        exit 1
    }
    apt install -y python3-certbot-apache || { ui_err "Failed to install python3-certbot-apache."; exit 1; }
    if ! wait_for_certbot; then
        ui_err "Certbot is busy. Please try again shortly."
        exit 1
    fi
    certbot --apache --agree-tos --preferred-challenges http -d "$DOMAIN_NAME" || {
        ui_err "Failed to configure SSL with Certbot."
        exit 1
    }

    ui_action "Re-enabling Apache 2..."
    systemctl enable apache2 || { ui_err "Failed to enable Apache 2."; exit 1; }
    systemctl start apache2  || { ui_err "Failed to start Apache 2.";  exit 1; }

    # ── Bot configuration prompts ──────────────────────────────────────────
    clear
    show_logo
    ui_panel "BOT CONFIGURATION" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Now we'll wire up your Telegram bot credentials.${C_RESET}" \
        "${C_DIM}Get the bot token from @BotFather and your numeric chat ID from @userinfobot.${C_RESET}"

    local YOUR_BOT_TOKEN YOUR_CHAT_ID YOUR_BOTNAME YOUR_DOMAIN
    printf '\n  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
        ui_err "Invalid bot token format. Please try again."
        printf '  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_BOT_TOKEN
    done

    printf '  %s❯%s Chat ID (numeric): ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_CHAT_ID
    while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
        ui_err "Invalid chat ID format. Please try again."
        printf '  %s❯%s Chat ID (numeric): ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_CHAT_ID
    done

    YOUR_DOMAIN="$DOMAIN_NAME"

    while true; do
        printf '  %s❯%s Bot username (without @): ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_BOTNAME
        if [ -n "$YOUR_BOTNAME" ]; then
            break
        fi
        ui_err "Bot username cannot be empty. Please enter a valid username."
    done

    # ── Database ───────────────────────────────────────────────────────────
    local ROOT_PASSWORD ROOT_USER
    ROOT_PASSWORD=$(grep '$pass' "$CRED_FILE" | cut -d"'" -f2)
    ROOT_USER="root"
    echo "SELECT 1" | mysql -u"$ROOT_USER" -p"$ROOT_PASSWORD" 2>/dev/null || {
        ui_err "MySQL connection failed."
        exit 1
    }

    local randomdbpass randomdbdb
    randomdbpass=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    randomdbdb=$(openssl rand -base64 10 | tr -dc 'a-zA-Z' | cut -c1-8)

    local dbname dbuser dbpass
    if mysql -u root -p"$ROOT_PASSWORD" -e "SHOW DATABASES LIKE '${DEFAULT_DB_NAME}'" | grep -q "$DEFAULT_DB_NAME"; then
        clear
        ui_warn "The database '${DEFAULT_DB_NAME}' already exists — please remove it first if you want a fresh install."
        exit 1
    fi
    dbname="$DEFAULT_DB_NAME"

    clear
    show_logo
    ui_panel "DATABASE CREDENTIALS" "$C_BOLD$C_MAGENTA" "$C_MAGENTA" \
        "${C_WHITE}A new MySQL database '${dbname}' will be created.${C_RESET}" \
        "${C_DIM}Press Enter to accept the auto-generated defaults.${C_RESET}"

    printf '\n  %s❯%s Database username [default: %s%s%s]: ' \
        "$C_YELLOW" "$C_RESET" "$C_CYAN" "$randomdbdb" "$C_RESET"
    read -r dbuser
    [ -z "$dbuser" ] && dbuser="$randomdbdb"

    printf '  %s❯%s Database password [default: %s%s%s]: ' \
        "$C_YELLOW" "$C_RESET" "$C_CYAN" "$randomdbpass" "$C_RESET"
    read -r dbpass
    [ -z "$dbpass" ] && dbpass="$randomdbpass"

    mysql -u root -p"$ROOT_PASSWORD" \
        -e "CREATE DATABASE ${dbname};" \
        -e "CREATE USER '${dbuser}'@'%' IDENTIFIED WITH mysql_native_password BY '${dbpass}'; GRANT ALL PRIVILEGES ON *.* TO '${dbuser}'@'%'; FLUSH PRIVILEGES;" \
        -e "CREATE USER '${dbuser}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${dbpass}'; GRANT ALL PRIVILEGES ON *.* TO '${dbuser}'@'localhost'; FLUSH PRIVILEGES;" || {
        ui_err "Failed to create database or user."
        exit 1
    }
    ui_ok "Database '${dbname}' created."

    # ── config.php ─────────────────────────────────────────────────────────
    sleep 1
    local file_path="${BOT_DIR}/config.php"
    if [ -f "$file_path" ]; then
        rm "$file_path" || { ui_err "Failed to delete old config.php."; exit 1; }
    fi
    sleep 1

    local secrettoken admin_panel_token
    secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    admin_panel_token=$(faoxima_generate_token)

    local ASAS='$'
    cat > "$file_path" <<EOF
<?php
${ASAS}APIKEY = '${YOUR_BOT_TOKEN}';
${ASAS}usernamedb = '${dbuser}';
${ASAS}passworddb = '${dbpass}';
${ASAS}dbname = '${dbname}';
${ASAS}domainhosts = '${YOUR_DOMAIN}/faoxima';
${ASAS}adminnumber = '${YOUR_CHAT_ID}';
${ASAS}usernamebot = '${YOUR_BOTNAME}';
${ASAS}secrettoken = '${secrettoken}';
${ASAS}admin_panel_token = '${admin_panel_token}';
${ASAS}connect = mysqli_connect('localhost', \$usernamedb, \$passworddb, \$dbname);
if (${ASAS}connect->connect_error) {
    die(' The connection to the database failed:' . ${ASAS}connect->connect_error);
}
mysqli_set_charset(${ASAS}connect, 'utf8mb4');
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=localhost;dbname=${ASAS}dbname;charset=utf8mb4";
try {
     \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
     throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>
EOF
    sleep 1

    # ── Show admin panel token + require user confirmation ─────────────────
    clear
    show_logo
    faoxima_show_and_confirm_token "$admin_panel_token"

    # ── Telegram webhook + first message ───────────────────────────────────
    curl -F "url=https://${YOUR_DOMAIN}/faoxima/index.php" \
         -F "secret_token=${secrettoken}" \
         "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || {
        ui_err "Failed to set webhook for bot."
        exit 1
    }
    local MESSAGE="✅ Faoxima bot is installed! Send /start to begin."
    curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" \
        -d chat_id="${YOUR_CHAT_ID}" -d text="${MESSAGE}" || {
        ui_err "Failed to send message to Telegram."
        exit 1
    }

    sleep 1
    systemctl start apache2 || { ui_err "Failed to start Apache 2."; exit 1; }

    # ── Trigger table.php with the admin panel token ───────────────────────
    local table_url="https://${YOUR_DOMAIN}/faoxima/table.php?token=$(printf '%s' "$admin_panel_token" | sed 's/&/%26/g')"
    ui_action "Initialising database tables via table.php (with token)..."
    curl -s "$table_url" >/dev/null || {
        ui_warn "Failed to fetch ${table_url} — please open it manually in a browser."
    }

    grant_file_permissions "$BOT_DIR"

    clear
    show_logo
    ui_status_table "INSTALLATION SUCCESSFUL" "$C_GREEN" \
        "Bot URL|${C_GREEN}https://${YOUR_DOMAIN}${C_RESET}" \
        "phpMyAdmin|${C_BLUE}https://${YOUR_DOMAIN}/phpmyadmin${C_RESET}" \
        "Database name|${C_CYAN}${dbname}${C_RESET}" \
        "Database user|${C_CYAN}${dbuser}${C_RESET}" \
        "Database password|${C_CYAN}${dbpass}${C_RESET}" \
        "Admin panel token|${C_YELLOW}${admin_panel_token}${C_RESET}"
    ui_tip "Run 'faoxima' anytime from the shell to reopen this menu."
    printf '\n'

    chmod +x "$INSTALL_SCRIPT_PATH" 2>/dev/null || true
    ln -sf "$INSTALL_SCRIPT_PATH" "$INSTALL_SCRIPT_LINK" >/dev/null 2>&1 || true
}

# ============================================================================
#  INSTALL BOT — alongside Marzban (uses Marzban's MySQL, port 88)
# ============================================================================
install_bot_with_marzban() {
    show_logo
    ui_panel "INSTALLATION — MARZBAN-COMPATIBLE" "$C_BOLD$C_YELLOW" "$C_YELLOW" \
        "${C_WHITE}Marzban panel detected on this server.${C_RESET}" \
        "${C_RED}Backup the Marzban database before continuing.${C_RESET}"

    local confirm
    printf '\n  %s❯%s Are you sure you want to install Faoxima Bot alongside Marzban? (y/n): ' \
        "$C_YELLOW" "$C_RESET"
    read -r confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        ui_err "Installation aborted by user."
        exit 0
    fi

    ui_action "Checking Marzban database type..."
    local DB_TYPE
    DB_TYPE=$(detect_database_type)
    if [ "$DB_TYPE" != "mysql" ]; then
        ui_err "Your database is ${DB_TYPE}. To install Faoxima Bot, you must use MySQL."
        ui_warn "Please configure Marzban to use MySQL and try again."
        exit 1
    fi
    ui_ok "MySQL detected. Proceeding with installation..."

    ui_action "Checking port availability..."
    if ss -tuln | grep -q ":80 "; then
        ui_err "Port 80 is already in use. Please free port 80 and run the script again."
        exit 1
    fi
    if ss -tuln | grep -q ":88 "; then
        ui_err "Port 88 is already in use. Please free port 88 and run the script again."
        exit 1
    fi
    ui_ok "Ports 80 and 88 are free."

    if ! (apt update && apt upgrade -y); then
        ui_warn "Update/upgrade failed. Attempting alternative mirrors..."
        if fix_update_issues; then
            if apt update && apt upgrade -y; then
                ui_ok "System updated successfully after fixing mirrors."
            else
                ui_err "Failed to update even after trying alternative mirrors."
                exit 1
            fi
        else
            ui_err "Failed to update/upgrade system and mirror fix failed."
            exit 1
        fi
    else
        ui_ok "System updated successfully."
    fi

    apt-get install -y software-properties-common || { ui_err "Failed to install software-properties-common."; exit 1; }

    ui_action "Checking and installing MySQL client..."
    if ! command -v mysql &>/dev/null; then
        apt install -y mysql-client || { ui_err "Failed to install MySQL client."; exit 1; }
        ui_ok "MySQL client installed."
    else
        ui_ok "MySQL client is already installed."
    fi

    apt install -y software-properties-common || { ui_err "Failed to install software-properties-common."; exit 1; }
    add-apt-repository -y ppa:ondrej/php || {
        ui_warn "Failed to add PPA ondrej/php — trying with locale override..."
        LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || {
            ui_err "Failed to add PPA even with locale override."
            exit 1
        }
    }
    apt update || { ui_err "Failed to update package list after adding PPA."; exit 1; }

    apt install -y git unzip curl wget jq || { ui_err "Failed to install basic tools."; exit 1; }

    if ! dpkg -s apache2 &>/dev/null; then
        apt install -y apache2 || { ui_err "Failed to install Apache 2."; exit 1; }
    fi

    DEBIAN_FRONTEND=noninteractive apt install -y \
        php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-zip php8.2-gd \
        php8.2-curl php8.2-soap php8.2-ssh2 libssh2-1-dev libssh2-1 php8.2-pdo || {
        ui_err "Failed to install PHP 8.2 and modules."
        exit 1
    }
    apt install -y libapache2-mod-php8.2     || { ui_err "Failed to install libapache2-mod-php8.2."; exit 1; }
    apt install -y python3-certbot-apache    || { ui_err "Failed to install Certbot for Apache 2."; exit 1; }
    systemctl enable certbot.timer           || { ui_err "Failed to enable certbot timer."; exit 1; }

    if ! dpkg -s ufw &>/dev/null; then
        apt install -y ufw || { ui_err "Failed to install UFW."; exit 1; }
    fi

    # ── Marzban MySQL credentials ──────────────────────────────────────────
    local ENV_FILE="/opt/marzban/.env" MYSQL_ROOT_PASSWORD ROOT_USER MYSQL_CONTAINER
    if [ ! -f "$ENV_FILE" ]; then
        ui_err "Marzban .env file not found. Cannot proceed without Marzban configuration."
        exit 1
    fi
    MYSQL_ROOT_PASSWORD=$(grep "MYSQL_ROOT_PASSWORD=" "$ENV_FILE" | cut -d'=' -f2 | tr -d '[:space:]' | sed 's/"//g')
    ROOT_USER="root"
    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        ui_warn "Could not retrieve MySQL root password from Marzban .env file."
        printf '  %s❯%s Please enter the MySQL root password manually: ' "$C_YELLOW" "$C_RESET"
        read -rs MYSQL_ROOT_PASSWORD
        echo
    fi
    MYSQL_CONTAINER=$(docker ps -q --filter "name=mysql" --no-trunc)
    if [ -z "$MYSQL_CONTAINER" ]; then
        ui_err "Could not find a running MySQL container. Ensure Marzban is running with Docker."
        ui_warn "Running containers:"
        docker ps
        exit 1
    fi

    ui_action "Testing MySQL connection..."
    mysql -u "$ROOT_USER" -p"$MYSQL_ROOT_PASSWORD" -h 127.0.0.1 -P 3306 -e "SELECT 1;" 2>/tmp/mysql_error.log
    if [ $? -eq 0 ]; then
        ui_ok "MySQL connection successful (direct host method)."
    else
        ui_warn "Direct connection failed, trying inside container..."
        docker exec "$MYSQL_CONTAINER" bash -c "echo 'SELECT 1;' | mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD'" 2>/tmp/mysql_error.log
        if [ $? -eq 0 ]; then
            ui_ok "MySQL connection successful (container method)."
        else
            ui_err "Failed to connect to MySQL using both methods."
            cat /tmp/mysql_error.log
            local NEW_PASSWORD
            printf '  %s❯%s Enter the correct MySQL root password: ' "$C_YELLOW" "$C_RESET"
            read -rs NEW_PASSWORD
            echo
            MYSQL_ROOT_PASSWORD="$NEW_PASSWORD"
            mysql -u "$ROOT_USER" -p"$MYSQL_ROOT_PASSWORD" -h 127.0.0.1 -P 3306 -e "SELECT 1;" 2>/tmp/mysql_error.log || {
                docker exec "$MYSQL_CONTAINER" bash -c "echo 'SELECT 1;' | mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD'" 2>/tmp/mysql_error.log || {
                    ui_err "Still can't connect with new password."
                    cat /tmp/mysql_error.log
                    exit 1
                }
            }
            ui_ok "MySQL connection successful with new password."
        fi
    fi

    clear
    show_logo
    ui_panel "DATABASE CREDENTIALS" "$C_BOLD$C_MAGENTA" "$C_MAGENTA" \
        "${C_WHITE}Configuring Faoxima Bot database credentials...${C_RESET}"

    local default_dbuser default_dbpass dbuser dbpass dbname
    default_dbuser=$(openssl rand -base64 12 | tr -dc 'a-zA-Z' | head -c8)
    printf '\n  %s❯%s Database username [default: %s%s%s]: ' \
        "$C_YELLOW" "$C_RESET" "$C_CYAN" "$default_dbuser" "$C_RESET"
    read -r dbuser
    [ -z "$dbuser" ] && dbuser="$default_dbuser"

    default_dbpass=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c12)
    printf '  %s❯%s Database password [default: %s%s%s]: ' \
        "$C_YELLOW" "$C_RESET" "$C_CYAN" "$default_dbpass" "$C_RESET"
    read -rs dbpass
    echo
    [ -z "$dbpass" ] && dbpass="$default_dbpass"
    dbname="$DEFAULT_DB_NAME"

    docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"CREATE DATABASE IF NOT EXISTS ${dbname}; CREATE USER IF NOT EXISTS '${dbuser}'@'%' IDENTIFIED BY '${dbpass}'; GRANT ALL PRIVILEGES ON ${dbname}.* TO '${dbuser}'@'%'; FLUSH PRIVILEGES;\"" || {
        ui_err "Failed to create database or user in Marzban MySQL container."
        exit 1
    }
    ui_ok "Database '${dbname}' created."

    # ── Bot directory ──────────────────────────────────────────────────────
    if [ -d "$BOT_DIR" ]; then
        ui_warn "Directory ${BOT_DIR} already exists — removing..."
        rm -rf "$BOT_DIR" || { ui_err "Failed to remove ${BOT_DIR}."; exit 1; }
    fi
    mkdir -p "$BOT_DIR" || { ui_err "Failed to create ${BOT_DIR}."; exit 1; }

    local ZIP_URL
    ZIP_URL=$(curl -s "https://api.github.com/repos/${FAOXIMA_REPO}/releases/latest" | grep "zipball_url" | cut -d '"' -f 4)
    if [[ "$1" == "-v" && "$2" == "beta" ]] || [[ "$1" == "-beta" ]] || [[ "$1" == "-" && "$2" == "beta" ]]; then
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    elif [[ "$1" == "-v" && -n "$2" ]]; then
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/tags/$2.zip"
    fi
    if [ -z "$ZIP_URL" ]; then
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    fi

    mkdir -p "$TMP_DOWNLOAD"
    wget -O "${TMP_DOWNLOAD}/bot.zip" "$ZIP_URL" || { ui_err "Failed to download bot files."; exit 1; }
    unzip -q "${TMP_DOWNLOAD}/bot.zip" -d "$TMP_DOWNLOAD" || { ui_err "Failed to unzip bot files."; exit 1; }
    local extracted_dir
    extracted_dir=$(find "$TMP_DOWNLOAD" -mindepth 1 -maxdepth 1 -type d | head -1)
    mv "${extracted_dir}"/* "$BOT_DIR" || { ui_err "Failed to move bot files."; exit 1; }
    rm -rf "$TMP_DOWNLOAD"

    chown -R www-data:www-data "$BOT_DIR"
    chmod -R 755 "$BOT_DIR"
    ui_ok "Bot files installed in ${BOT_DIR}."
    sleep 2
    clear

    # ── Apache 2 ports + SSL on port 88 ───────────────────────────────────
    show_logo
    ui_action "Configuring Apache 2 ports..."
    : > /etc/apache2/ports.conf
    cat <<EOF | tee /etc/apache2/ports.conf >/dev/null

Listen 80
Listen 88

EOF

    : > /etc/apache2/sites-available/000-default.conf
    cat <<EOF | tee /etc/apache2/sites-available/000-default.conf >/dev/null
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>

EOF

    systemctl enable apache2  || { ui_err "Failed to enable Apache 2.";  exit 1; }
    systemctl restart apache2 || { ui_err "Failed to restart Apache 2."; exit 1; }

    ui_action "Configuring SSL on port 88..."
    ufw allow 80 || { ui_err "Failed to configure firewall for port 80."; exit 1; }
    ufw allow 88 || { ui_err "Failed to configure firewall for port 88."; exit 1; }
    clear
    show_logo
    ui_panel "DOMAIN" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Enter the domain that will host this Faoxima bot.${C_RESET}"

    local domainname DOMAIN_NAME
    printf '\n  %s❯%s Enter the domain (e.g., example.com): ' "$C_YELLOW" "$C_RESET"
    read -r domainname
    while [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        ui_err "Invalid domain format. Must be like 'example.com'. Please try again."
        printf '  %s❯%s Enter the domain (e.g., example.com): ' "$C_YELLOW" "$C_RESET"
        read -r domainname
    done
    DOMAIN_NAME="$domainname"
    ui_ok "Domain set to: ${DOMAIN_NAME}"

    systemctl restart apache2 || { ui_err "Failed to restart Apache 2 before Certbot."; exit 1; }
    if ! wait_for_certbot; then
        ui_err "Certbot is busy. Please try again shortly."
        exit 1
    fi
    certbot --apache --agree-tos --preferred-challenges http -d "$DOMAIN_NAME" --https-port 88 --no-redirect || {
        ui_err "Failed to configure SSL with Certbot on port 88."
        exit 1
    }

    : > /etc/apache2/sites-available/000-default-le-ssl.conf
    cat <<EOF | tee /etc/apache2/sites-available/000-default-le-ssl.conf >/dev/null
<IfModule mod_ssl.c>
<VirtualHost *:88>
    ServerAdmin webmaster@localhost
    ServerName $DOMAIN_NAME
    DocumentRoot /var/www/html
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${DOMAIN_NAME}/privkey.pem
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
</VirtualHost>
</IfModule>
EOF
    a2enmod ssl || { ui_err "Failed to enable SSL module."; exit 1; }
    a2ensite 000-default-le-ssl.conf || { ui_err "Failed to enable SSL site."; exit 1; }
    : > /etc/apache2/ports.conf
    echo "Listen 88" | tee /etc/apache2/ports.conf >/dev/null
    apache2ctl configtest || { ui_err "Apache 2 configuration test failed after Certbot."; exit 1; }
    systemctl restart apache2 || { ui_err "Failed to restart Apache 2 after SSL configuration."; exit 1; }

    ui_action "Disabling port 80 as it's no longer needed..."
    a2dissite 000-default.conf || { ui_err "Failed to disable port 80 VirtualHost."; exit 1; }
    ufw delete allow 80        || { ui_err "Failed to remove port 80 from firewall."; exit 1; }
    apache2ctl configtest      || { ui_err "Apache 2 configuration test failed."; exit 1; }
    systemctl restart apache2  || { ui_err "Failed to restart Apache 2 after disabling port 80."; exit 1; }
    ui_ok "SSL configured successfully on port 88. Port 80 disabled."
    sleep 2

    # ── Bot configuration prompts ──────────────────────────────────────────
    clear
    show_logo
    ui_panel "BOT CONFIGURATION" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Now wire up your Telegram bot credentials.${C_RESET}"

    local YOUR_BOT_TOKEN YOUR_CHAT_ID YOUR_BOTNAME YOUR_DOMAIN
    printf '\n  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
        ui_err "Invalid bot token format. Please try again."
        printf '  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_BOT_TOKEN
    done
    printf '  %s❯%s Chat ID: ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_CHAT_ID
    while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
        ui_err "Invalid chat ID format. Please try again."
        printf '  %s❯%s Chat ID: ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_CHAT_ID
    done

    YOUR_DOMAIN="${DOMAIN_NAME}:88"
    printf '  %s❯%s Bot username: ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_BOTNAME
    while [ -z "$YOUR_BOTNAME" ]; do
        ui_err "Bot username cannot be empty."
        printf '  %s❯%s Bot username: ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_BOTNAME
    done

    local secrettoken admin_panel_token ASAS='$'
    secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    admin_panel_token=$(faoxima_generate_token)

    cat > "${BOT_DIR}/config.php" <<EOF
<?php
${ASAS}APIKEY = '${YOUR_BOT_TOKEN}';
${ASAS}usernamedb = '${dbuser}';
${ASAS}passworddb = '${dbpass}';
${ASAS}dbname = '${dbname}';
${ASAS}domainhosts = '${YOUR_DOMAIN}';
${ASAS}adminnumber = '${YOUR_CHAT_ID}';
${ASAS}usernamebot = '${YOUR_BOTNAME}';
${ASAS}secrettoken = '${secrettoken}';
${ASAS}admin_panel_token = '${admin_panel_token}';

${ASAS}connect = mysqli_connect('127.0.0.1', \$usernamedb, \$passworddb, \$dbname);
if (${ASAS}connect->connect_error) {
    die('Database connection failed: ' . ${ASAS}connect->connect_error);
}
mysqli_set_charset(${ASAS}connect, 'utf8mb4');

${ASAS}options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
${ASAS}dsn = "mysql:host=127.0.0.1;port=3306;dbname=\$dbname;charset=utf8mb4";
try {
    ${ASAS}pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
    die('PDO Connection failed: ' . \$e->getMessage());
}
?>
EOF

    # ── Show & confirm token ───────────────────────────────────────────────
    clear
    show_logo
    faoxima_show_and_confirm_token "$admin_panel_token"

    # ── Webhook + first message (fixed: was using BOT_TOKEN/CHAT_ID before) ─
    curl -F "url=https://${YOUR_DOMAIN}/faoxima/index.php" \
         -F "secret_token=${secrettoken}" \
         "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || {
        ui_err "Failed to set webhook."
        exit 1
    }

    local MESSAGE="✅ Faoxima bot is installed! Send /start to begin."
    curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" \
        -d chat_id="${YOUR_CHAT_ID}" -d text="${MESSAGE}" || {
        ui_err "Failed to send message to Telegram."
        return 1
    }

    local TABLE_SETUP_URL="https://${YOUR_DOMAIN}/faoxima/table.php?token=$(printf '%s' "$admin_panel_token" | sed 's/&/%26/g')"
    ui_action "Setting up database tables (with token)..."
    curl -s "$TABLE_SETUP_URL" >/dev/null || {
        ui_warn "Failed to fetch ${TABLE_SETUP_URL} — please open it manually in a browser."
    }

    clear
    show_logo
    ui_status_table "INSTALLATION SUCCESSFUL" "$C_GREEN" \
        "Bot URL|${C_GREEN}https://${DOMAIN_NAME}:88${C_RESET}" \
        "phpMyAdmin|${C_BLUE}https://${DOMAIN_NAME}/phpmyadmin${C_RESET}" \
        "Database name|${C_CYAN}${dbname}${C_RESET}" \
        "Database user|${C_CYAN}${dbuser}${C_RESET}" \
        "Database password|${C_CYAN}${dbpass}${C_RESET}" \
        "Admin panel token|${C_YELLOW}${admin_panel_token}${C_RESET}"
    ui_tip "Run 'faoxima' anytime from the shell to reopen this menu."
    printf '\n'

    chmod +x "$INSTALL_SCRIPT_PATH" 2>/dev/null || true
    ln -sf "$INSTALL_SCRIPT_PATH" "$INSTALL_SCRIPT_LINK" >/dev/null 2>&1 || true
}

# ============================================================================
#  UPDATE BOT
# ============================================================================
update_bot() {
    show_logo
    ui_panel "UPDATE FAOXIMA BOT" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Pulling the latest Faoxima release while preserving config.php.${C_RESET}"

    if ! (apt update && apt upgrade -y); then
        ui_err "Error updating the server. Exiting..."
        exit 1
    fi
    ui_ok "Server packages updated successfully."

    if [ ! -d "$BOT_DIR" ]; then
        ui_err "Faoxima Bot is not installed. Please install it first."
        exit 1
    fi

    local ZIP_URL
    if [[ "$1" == "-beta" ]] || [[ "$1" == "-v" && "$2" == "beta" ]]; then
        ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    else
        ZIP_URL=$(curl -s "https://api.github.com/repos/${FAOXIMA_REPO}/releases/latest" | grep "zipball_url" | cut -d '"' -f4)
        [ -z "$ZIP_URL" ] && ZIP_URL="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    fi

    mkdir -p "$TMP_UPDATE"
    wget -O "${TMP_UPDATE}/bot.zip" "$ZIP_URL" || { ui_err "Failed to download update package."; exit 1; }
    unzip -q "${TMP_UPDATE}/bot.zip" -d "$TMP_UPDATE"

    local extracted_dir
    extracted_dir=$(find "$TMP_UPDATE" -mindepth 1 -maxdepth 1 -type d | head -1)

    local CONFIG_PATH="${BOT_DIR}/config.php"
    local TEMP_CONFIG="/root/faoxima_config_backup.php"
    if [ -f "$CONFIG_PATH" ]; then
        cp "$CONFIG_PATH" "$TEMP_CONFIG" || { ui_err "Config file backup failed!"; exit 1; }
    fi

    rm -rf "$BOT_DIR" || { ui_err "Failed to remove old bot files!"; exit 1; }
    mkdir -p "$BOT_DIR"
    mv "${extracted_dir}"/* "${BOT_DIR}/" || { ui_err "File transfer failed!"; exit 1; }

    if [ -f "$TEMP_CONFIG" ]; then
        mv "$TEMP_CONFIG" "$CONFIG_PATH" || { ui_err "Config file restore failed!"; exit 1; }
    fi

    local local_install
    local_install=$(find "$BOT_DIR" -maxdepth 2 -name "install.sh" -print -quit)
    if [ -n "$local_install" ]; then
        cp "$local_install" "$INSTALL_SCRIPT_PATH"
        ui_ok "Copied latest install.sh to ${INSTALL_SCRIPT_PATH}."
    else
        local raw_url="https://raw.githubusercontent.com/${FAOXIMA_REPO}/main/install.sh"
        if curl -fsSL "$raw_url" -o "$INSTALL_SCRIPT_PATH"; then
            ui_ok "Fetched install.sh from upstream repository."
        else
            ui_warn "install.sh not found locally and download failed — keeping existing ${INSTALL_SCRIPT_PATH}."
        fi
    fi
    chown -R www-data:www-data "$BOT_DIR"
    chmod -R 755 "$BOT_DIR"

    local URL CLEAN_URL
    URL=$(grep -oP "\$domainhosts\s*=\s*[\'\"]\K[^\'\"]+" "$CONFIG_PATH" 2>/dev/null | head -1)
    if [ -z "$URL" ]; then
        URL=$(grep "domainhosts" "$CONFIG_PATH" | sed -n "s/.*domainhosts.*=.*[\'\"]\([^\'\"]*\)[\'\"].*/\1/p" | head -1)
    fi
    # Re-use the existing admin_panel_token (or mint a new one if missing).
    local admin_panel_token
    admin_panel_token=$(grep '^\$admin_panel_token' "$CONFIG_PATH" 2>/dev/null | cut -d"'" -f2)
    if [ -z "$admin_panel_token" ]; then
        admin_panel_token=$(faoxima_generate_token)
        faoxima_persist_token "$CONFIG_PATH" "$admin_panel_token"
        ui_info "No admin panel token found in config.php — a new one was generated."
        faoxima_show_and_confirm_token "$admin_panel_token"
    fi

    if [ -n "$URL" ]; then
        CLEAN_URL=${URL#http://}
        CLEAN_URL=${CLEAN_URL#https://}
        CLEAN_URL=${CLEAN_URL%/}
        curl -s "https://${CLEAN_URL}/table.php?token=$(printf '%s' "$admin_panel_token" | sed 's/&/%26/g')" >/dev/null || {
            ui_warn "Setup script execution failed for https://${CLEAN_URL}/table.php"
        }
    else
        ui_warn "Unable to detect domainhosts from config.php. Skipping table setup call."
    fi

    ui_action "Verifying database tables..."
    local DB_USERNAME DB_PASSWORD DB_NAME TABLES
    DB_USERNAME=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASSWORD=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname'         "$CONFIG_PATH" | awk -F"'" '{print $2}')
    if [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ] || [ -z "$DB_NAME" ]; then
        ui_err "Failed to read database credentials from config.php. Cannot verify tables."
    else
        TABLES=$(mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SHOW TABLES LIKE 'setting';" 2>&1)
        if echo "$TABLES" | grep -q "setting"; then
            ui_ok "Database table 'setting' exists."
        else
            ui_err "Database table 'setting' NOT FOUND."
            ui_warn "Please check the bot logs for details."
        fi
    fi

    grant_file_permissions "$BOT_DIR"

    rm -rf "$TMP_UPDATE"
    ui_ok "Faoxima Bot updated to latest version successfully."

    if [ -f "$INSTALL_SCRIPT_PATH" ]; then
        chmod +x "$INSTALL_SCRIPT_PATH"
        ln -sf "$INSTALL_SCRIPT_PATH" "$INSTALL_SCRIPT_LINK" >/dev/null 2>&1
        ui_ok "Ensured ${INSTALL_SCRIPT_PATH} is executable and 'faoxima' command is linked."
    else
        ui_err "${INSTALL_SCRIPT_PATH} not found after update attempt."
    fi
}

# ============================================================================
#  REMOVE BOT
# ============================================================================
remove_bot() {
    show_logo
    ui_panel "REMOVE FAOXIMA BOT" "$C_BOLD$C_RED" "$C_RED" \
        "${C_WHITE}This will remove the bot, its database, and the LAMP stack.${C_RESET}" \
        "${C_RED}This action is irreversible.${C_RESET}"

    if [ ! -d "$BOT_DIR" ]; then
        ui_err "Faoxima Bot is not installed (${BOT_DIR} not found)."
        log_warn "Nothing to remove."
        sleep 2
        exit 1
    fi

    local choice
    printf '\n  %s❯%s Are you sure you want to remove Faoxima Bot and its dependencies? (y/n): ' \
        "$C_YELLOW" "$C_RESET"
    read -r choice
    if [[ "$choice" != "y" && "$choice" != "Y" ]]; then
        ui_warn "Aborting..."
        exit 0
    fi

    if check_marzban_installed; then
        ui_warn "Marzban detected — switching to Marzban-compatible removal."
        remove_bot_with_marzban
        return 0
    fi

    log_info "Removing Faoxima Bot..."

    if [ -d "$BOT_DIR" ]; then
        rm -rf "$BOT_DIR" && ui_ok "Bot directory removed: ${BOT_DIR}" || {
            ui_err "Failed to remove bot directory: ${BOT_DIR}. Exiting..."
            exit 1
        }
    fi

    local CONFIG_PATH="/root/config.php"
    if [ -f "$CONFIG_PATH" ]; then
        shred -u -n 5 "$CONFIG_PATH" && ui_ok "Config file securely removed: ${CONFIG_PATH}" || \
            ui_err "Failed to securely remove config file."
    fi

    log_action "Removing MySQL and database..."
    systemctl stop mysql       2>/dev/null || true
    systemctl disable mysql    2>/dev/null || true
    systemctl daemon-reload    2>/dev/null || true
    apt --fix-broken install -y || true

    apt-get purge -y mysql-server mysql-client mysql-common 'mysql-server-core-*' 'mysql-client-core-*' || true
    rm -rf /etc/mysql /var/lib/mysql /var/log/mysql /var/log/mysql.* /usr/lib/mysql /usr/include/mysql /usr/share/mysql 2>/dev/null || true
    rm -f  /lib/systemd/system/mysql.service /etc/init.d/mysql 2>/dev/null || true

    dpkg --remove --force-remove-reinstreq mysql-server mysql-server-8.0 2>/dev/null || true
    find /etc/systemd /lib/systemd /usr/lib/systemd -name "*mysql*" -exec rm -f {} \; 2>/dev/null || true

    apt-get purge -y mysql-server mysql-server-8.0 mysql-client mysql-client-8.0 || true
    apt-get purge -y mysql-client-core-8.0 mysql-server-core-8.0 mysql-common php-mysql php8.2-mysql php-mariadb-mysql-kbs 2>/dev/null || true
    apt-get autoremove --purge -y || true
    apt-get clean       || true
    apt-get update      || true
    ui_ok "MySQL has been completely removed."

    log_action "Removing phpMyAdmin..."
    if dpkg -s phpmyadmin &>/dev/null; then
        apt-get purge -y phpmyadmin && ui_ok "phpMyAdmin removed."
        apt-get autoremove -y && apt-get autoclean -y
    else
        ui_warn "phpMyAdmin is not installed."
    fi

    log_action "Removing Apache 2..."
    systemctl stop apache2    2>/dev/null || ui_warn "Failed to stop Apache 2 — continuing..."
    systemctl disable apache2 2>/dev/null || ui_warn "Failed to disable Apache 2 — continuing..."
    apt-get purge -y apache2 apache2-utils apache2-bin apache2-data 'libapache2-mod-php*' || \
        ui_err "Failed to purge Apache 2 packages."
    apt-get autoremove --purge -y
    apt-get autoclean -y
    rm -rf /etc/apache2 "$BOT_DIR"

    log_action "Removing Apache 2 / PHP configurations..."
    a2disconf phpmyadmin.conf &>/dev/null || true
    rm -f /etc/apache2/conf-available/phpmyadmin.conf

    log_action "Removing additional packages..."
    apt-get remove -y php-soap php-ssh2 libssh2-1-dev libssh2-1 \
        && ui_ok "Removed additional PHP packages." \
        || ui_warn "Some additional PHP packages may not be installed."

    log_action "Resetting firewall rules (except SSL)..."
    ufw delete allow 'Apache' 2>/dev/null || true
    ufw reload 2>/dev/null || true

    ui_ok "Faoxima Bot, MySQL, and dependencies have been completely removed."
}

remove_bot_with_marzban() {
    log_action "Removing Faoxima Bot alongside Marzban..."

    local DB_NAME="$DEFAULT_DB_NAME" DB_USER=""
    if [ ! -d "$BOT_DIR" ]; then
        ui_warn "Bot directory ${BOT_DIR} not found. Assuming it was already removed."
    else
        local CONFIG_PATH="${BOT_DIR}/config.php"
        if [ -f "$CONFIG_PATH" ]; then
            DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
            DB_NAME=$(grep '^\$dbname'     "$CONFIG_PATH" | awk -F"'" '{print $2}')
            if [ -z "$DB_USER" ] || [ -z "$DB_NAME" ]; then
                ui_err "Could not extract database credentials from ${CONFIG_PATH}. Using defaults."
                DB_NAME="$DEFAULT_DB_NAME"; DB_USER=""
            else
                ui_ok "Found database credentials: User=${DB_USER}, DB=${DB_NAME}"
            fi
        else
            ui_warn "config.php not found at ${CONFIG_PATH}. Assuming default database name '${DEFAULT_DB_NAME}'."
            DB_NAME="$DEFAULT_DB_NAME"; DB_USER=""
        fi

        rm -rf "$BOT_DIR" && ui_ok "Bot directory removed: ${BOT_DIR}" || {
            ui_err "Failed to remove bot directory: ${BOT_DIR}. Exiting..."
            exit 1
        }
    fi

    local ENV_FILE="/opt/marzban/.env" MYSQL_ROOT_PASSWORD ROOT_USER MYSQL_CONTAINER
    if [ -f "$ENV_FILE" ]; then
        MYSQL_ROOT_PASSWORD=$(grep "MYSQL_ROOT_PASSWORD=" "$ENV_FILE" | cut -d'=' -f2 | tr -d '[:space:]' | sed 's/"//g')
        ROOT_USER="root"
    else
        ui_err "Marzban .env file not found. Cannot proceed without MySQL root password."
        exit 1
    fi

    MYSQL_CONTAINER=$(docker ps -q --filter "name=mysql" --no-trunc)
    if [ -z "$MYSQL_CONTAINER" ]; then
        ui_err "Could not find a running MySQL container. Ensure Marzban is running."
        exit 1
    fi

    if [ -n "$DB_NAME" ]; then
        ui_action "Removing database ${DB_NAME}..."
        docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"DROP DATABASE IF EXISTS ${DB_NAME};\"" \
            && ui_ok "Database ${DB_NAME} removed." \
            || ui_err "Failed to remove database ${DB_NAME}."
    fi

    if [ -n "$DB_USER" ]; then
        ui_action "Removing database user ${DB_USER}..."
        docker exec "$MYSQL_CONTAINER" bash -c "mysql -u '$ROOT_USER' -p'$MYSQL_ROOT_PASSWORD' -e \"DROP USER IF EXISTS '${DB_USER}'@'%'; FLUSH PRIVILEGES;\"" \
            && ui_ok "User ${DB_USER} removed." \
            || ui_err "Failed to remove user ${DB_USER}."
    fi

    log_action "Removing Apache 2..."
    systemctl stop    apache2 2>/dev/null || ui_warn "Failed to stop Apache 2 — continuing..."
    systemctl disable apache2 2>/dev/null || ui_warn "Failed to disable Apache 2 — continuing..."
    apt-get purge -y apache2 apache2-utils apache2-bin apache2-data 'libapache2-mod-php*' || \
        ui_err "Failed to purge Apache 2 packages."
    apt-get autoremove --purge -y
    apt-get autoclean -y
    rm -rf /etc/apache2 "$BOT_DIR"

    log_action "Resetting firewall rules (keeping SSL)..."
    ufw delete allow 'Apache' 2>/dev/null || ui_err "Failed to remove Apache 2 rule from UFW."
    ufw reload 2>/dev/null || true

    ui_ok "Faoxima Bot has been removed alongside Marzban. SSL certificates remain intact."
}

# ============================================================================
#  DATABASE EXPORT / IMPORT / BACKUP
# ============================================================================
extract_db_credentials() {
    local CONFIG_PATH="${BOT_DIR}/config.php"
    if [ ! -f "$CONFIG_PATH" ]; then
        ui_err "config.php not found at ${CONFIG_PATH}."
        return 1
    fi
    DB_USER=$(grep '^\$usernamedb'   "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb'   "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname'       "$CONFIG_PATH" | awk -F"'" '{print $2}')
    TELEGRAM_TOKEN=$(grep '^\$APIKEY'      "$CONFIG_PATH" | awk -F"'" '{print $2}')
    TELEGRAM_CHAT_ID=$(grep '^\$adminnumber' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ] \
        || [ -z "$TELEGRAM_TOKEN" ] || [ -z "$TELEGRAM_CHAT_ID" ]; then
        ui_err "Failed to extract required credentials from ${CONFIG_PATH}."
        return 1
    fi
    return 0
}

translate_cron() {
    local cron_line="$1" schedule
    case "$cron_line" in
        "* * * * *"*) schedule="Every Minute" ;;
        "0 * * * *"*) schedule="Every Hour"   ;;
        "0 0 * * *"*) schedule="Every Day"    ;;
        "0 0 * * 0"*) schedule="Every Week"   ;;
        *)            schedule="Custom Schedule (${cron_line})" ;;
    esac
    printf '%s' "$schedule"
}

export_database() {
    show_logo
    ui_panel "EXPORT DATABASE" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}A SQL dump of the bot database will be saved under /root.${C_RESET}"

    if ! extract_db_credentials; then return 1; fi
    if check_marzban_installed; then
        ui_err "Exporting is not supported when Marzban is installed (DB lives in Docker)."
        return 1
    fi

    ui_action "Verifying database existence..."
    if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE ${DB_NAME};" 2>/dev/null; then
        ui_err "Database ${DB_NAME} does not exist or credentials are incorrect."
        return 1
    fi

    local BACKUP_FILE="/root/${DB_NAME}_backup.sql"
    ui_action "Creating backup at ${BACKUP_FILE}..."
    if ! mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"; then
        ui_err "Failed to create database backup."
        return 1
    fi
    ui_ok "Backup successfully created at ${BACKUP_FILE}."
}

import_database() {
    show_logo
    ui_panel "IMPORT DATABASE" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Restore a previously-saved SQL dump into the bot database.${C_RESET}"

    if ! extract_db_credentials; then return 1; fi
    if check_marzban_installed; then
        ui_err "Importing is not supported when Marzban is installed (DB lives in Docker)."
        return 1
    fi

    ui_action "Verifying database existence..."
    if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE ${DB_NAME};" 2>/dev/null; then
        ui_err "Database ${DB_NAME} does not exist or credentials are incorrect."
        return 1
    fi

    local BACKUP_FILE
    while true; do
        printf '\n  %s❯%s Path to backup file [default: /root/%s_backup.sql]: ' \
            "$C_YELLOW" "$C_RESET" "$DB_NAME"
        read -r BACKUP_FILE
        BACKUP_FILE=${BACKUP_FILE:-/root/${DB_NAME}_backup.sql}
        if [[ -f "$BACKUP_FILE" && "$BACKUP_FILE" =~ \.sql$ ]]; then
            break
        fi
        ui_err "Invalid file path or format. Please provide a valid .sql file."
    done

    ui_action "Importing backup from ${BACKUP_FILE}..."
    if ! mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"; then
        ui_err "Failed to import database from backup file."
        return 1
    fi
    ui_ok "Database successfully imported from ${BACKUP_FILE}."
}

auto_backup() {
    show_logo
    ui_panel "CONFIGURE AUTOMATED BACKUP" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Schedule automatic database backups sent to your Telegram chat.${C_RESET}"

    if [ ! -d "$BOT_DIR" ]; then
        ui_err "Faoxima Bot is not installed (${BOT_DIR} not found)."
        sleep 2
        return 1
    fi
    if ! extract_db_credentials; then return 1; fi

    local BACKUP_SCRIPT MYSQL_CONTAINER
    if check_marzban_installed; then
        ui_warn "Marzban detected. Using Marzban-compatible backup."
        BACKUP_SCRIPT="/root/backup_faoxima_marzban.sh"
        MYSQL_CONTAINER=$(docker ps -q --filter "name=mysql" --no-trunc)
        if [ -z "$MYSQL_CONTAINER" ]; then
            ui_err "No running MySQL container found for Marzban."
            return 1
        fi
        cat > "$BACKUP_SCRIPT" <<EOF
#!/usr/bin/env bash
BACKUP_FILE="/root/${DB_NAME}_\$(date +"%Y%m%d_%H%M%S").sql"
if docker exec ${MYSQL_CONTAINER} mysqldump -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" > "\$BACKUP_FILE"; then
    curl -F document=@"\$BACKUP_FILE" "https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendDocument" -F chat_id="${TELEGRAM_CHAT_ID}"
    [ \$? -eq 0 ] && rm "\$BACKUP_FILE"
else
    echo "[ERROR] Failed to create Marzban database backup."
fi
EOF
    else
        ui_info "Using standard backup."
        BACKUP_SCRIPT="/root/faoxima_backup.sh"
        if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE ${DB_NAME};" 2>/dev/null; then
            ui_err "Database ${DB_NAME} does not exist or credentials are incorrect."
            return 1
        fi
        cat > "$BACKUP_SCRIPT" <<EOF
#!/usr/bin/env bash
BACKUP_FILE="/root/${DB_NAME}_\$(date +"%Y%m%d_%H%M%S").sql"
if mysqldump -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" > "\$BACKUP_FILE"; then
    curl -F document=@"\$BACKUP_FILE" "https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendDocument" -F chat_id="${TELEGRAM_CHAT_ID}"
    [ \$? -eq 0 ] && rm "\$BACKUP_FILE"
else
    echo "[ERROR] Failed to create database backup."
fi
EOF
    fi
    chmod +x "$BACKUP_SCRIPT"

    local CURRENT_CRON SCHEDULE
    CURRENT_CRON=$(crontab -l 2>/dev/null | grep "$BACKUP_SCRIPT" | grep -v "^#")
    if [ -n "$CURRENT_CRON" ]; then
        SCHEDULE=$(translate_cron "$CURRENT_CRON")
        ui_info "Current backup schedule: ${SCHEDULE}"
    else
        ui_info "No active backup schedule found."
    fi

    printf '\n'
    printf '  %s1)%s Every Minute\n' "$C_CYAN" "$C_RESET"
    printf '  %s2)%s Every Hour\n'   "$C_CYAN" "$C_RESET"
    printf '  %s3)%s Every Day\n'    "$C_CYAN" "$C_RESET"
    printf '  %s4)%s Every Week\n'   "$C_CYAN" "$C_RESET"
    printf '  %s5)%s Disable Backup\n' "$C_RED" "$C_RESET"
    printf '  %s6)%s Back to Menu\n' "$C_CYAN" "$C_RESET"

    local backup_option
    printf '\n  %s❯%s Select an option [1-6]: ' "$C_YELLOW" "$C_RESET"
    read -r backup_option

    update_cron() {
        local cron_line="$1"
        if [ -n "$CURRENT_CRON" ]; then
            crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab - \
                && ui_ok "Removed previous backup schedule." \
                || ui_err "Failed to remove existing cron."
        fi
        if [ -n "$cron_line" ]; then
            (crontab -l 2>/dev/null; echo "$cron_line") | crontab - \
                && { ui_ok "Backup scheduled: $(translate_cron "$cron_line")"; bash "$BACKUP_SCRIPT" &>/dev/null & } \
                || ui_err "Failed to schedule backup."
        fi
    }

    case "$backup_option" in
        1) update_cron "* * * * * bash $BACKUP_SCRIPT" ;;
        2) update_cron "0 * * * * bash $BACKUP_SCRIPT" ;;
        3) update_cron "0 0 * * * bash $BACKUP_SCRIPT" ;;
        4) update_cron "0 0 * * 0 bash $BACKUP_SCRIPT" ;;
        5)
            if [ -n "$CURRENT_CRON" ]; then
                crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab - \
                    && ui_ok "Automated backup disabled." \
                    || ui_err "Failed to disable backup."
            else
                ui_warn "No backup schedule to disable."
            fi
            ;;
        6) show_menu ;;
        *)
            ui_err "Invalid option. Please try again."
            auto_backup
            ;;
    esac
}

# ============================================================================
#  RENEW SSL
# ============================================================================
renew_ssl() {
    show_logo
    ui_panel "RENEW SSL CERTIFICATES" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Apache 2 will be stopped briefly while certbot performs renewal.${C_RESET}"

    if ! command -v certbot &>/dev/null; then
        ui_err "Certbot is not installed. Please install Certbot to proceed."
        return 1
    fi

    ui_action "Stopping Apache 2..."
    systemctl stop apache2 || { ui_err "Failed to stop Apache 2. Exiting..."; return 1; }

    if ! wait_for_certbot; then
        ui_err "Certbot is busy. Please try again later."
        systemctl start apache2 >/dev/null 2>&1
        return 1
    fi

    if certbot renew; then
        ui_ok "SSL certificates successfully renewed."
    else
        ui_err "SSL renewal failed. Please check Certbot logs for more details."
        systemctl start apache2
        return 1
    fi

    ui_action "Restarting Apache 2..."
    systemctl restart apache2 || ui_warn "Failed to restart Apache 2. Please check manually."
}

# ============================================================================
#  CHANGE DOMAIN
# ============================================================================
change_domain() {
    show_logo
    ui_panel "CHANGE DOMAIN" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Migrate the bot to a new domain (issues a new SSL cert and updates the webhook).${C_RESET}"

    local new_domain current_domainhosts sanitized_value path_segment
    local full_domain_path="" WEBHOOK_URL="" updated_domainhosts webhook_response NEW_SECRET BOT_TOKEN
    while [[ ! "$new_domain" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        printf '  %s❯%s Enter new domain: ' "$C_YELLOW" "$C_RESET"
        read -r new_domain
        [[ ! "$new_domain" =~ ^[a-zA-Z0-9.-]+$ ]] && ui_err "Invalid domain format"
    done

    log_action "Disabling Apache 2 service before domain change..."
    systemctl disable apache2 >/dev/null 2>&1 || true

    if ! configure_apache_vhost "$new_domain"; then
        log_error "Unable to prepare Apache 2 virtual host for ${new_domain}."
        restore_apache_service
        return 1
    fi

    log_action "Stopping Apache 2 to configure SSL..."
    if ! systemctl stop apache2; then
        log_error "Failed to stop Apache 2 while preparing SSL for ${new_domain}."
        restore_apache_service
        return 1
    fi

    log_action "Configuring SSL certificate for ${new_domain}..."
    if ! wait_for_certbot; then
        log_error "Certbot is already running. Please try again after the current process completes."
        restore_apache_service
        return 1
    fi
    if ! certbot --apache --redirect --agree-tos --preferred-challenges http \
            --non-interactive --force-renewal --cert-name "$new_domain" -d "$new_domain"; then
        log_error "SSL configuration failed for ${new_domain}, rolling back certificate changes."
        if wait_for_certbot; then
            certbot delete --cert-name "$new_domain" 2>/dev/null
        fi
        restore_apache_service
        return 1
    fi

    local CONFIG_FILE="${BOT_DIR}/config.php"
    if [ -f "$CONFIG_FILE" ]; then
        cp "$CONFIG_FILE" "${CONFIG_FILE}.$(date +%s).bak"

        current_domainhosts=$(awk -F"'" '/\$domainhosts/{print $2}' "$CONFIG_FILE" | head -1)
        sanitized_value=${current_domainhosts#http://}
        sanitized_value=${sanitized_value#https://}
        sanitized_value=${sanitized_value#/}
        path_segment=""
        if [[ "$sanitized_value" == */* ]]; then
            path_segment=${sanitized_value#*/}
            path_segment=${path_segment%/}
        fi
        if [ -z "$path_segment" ] && [ -d "$BOT_DIR" ]; then
            path_segment="faoxima"
            log_info "No path segment detected — using default '/faoxima'."
        fi
        if [ -n "$path_segment" ]; then
            full_domain_path="${new_domain}/${path_segment}"
        else
            full_domain_path="${new_domain}"
        fi
        sed -i "s|\$domainhosts = '.*';|\$domainhosts = '${full_domain_path}';|" "$CONFIG_FILE"

        NEW_SECRET=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9')
        sed -i "s|\$secrettoken = '.*';|\$secrettoken = '${NEW_SECRET}';|" "$CONFIG_FILE"

        BOT_TOKEN=$(awk -F"'" '/\$APIKEY/{print $2}' "$CONFIG_FILE")
        updated_domainhosts=$(awk -F"'" '/\$domainhosts/{print $2}' "$CONFIG_FILE" | head -1)
        updated_domainhosts=${updated_domainhosts%/}
        if [[ "$updated_domainhosts" =~ ^https?:// ]]; then
            WEBHOOK_URL="${updated_domainhosts}/index.php"
        else
            WEBHOOK_URL="https://${updated_domainhosts}/index.php"
        fi

        webhook_response=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
            -F "url=${WEBHOOK_URL}" -F "secret_token=${NEW_SECRET}")
        if echo "$webhook_response" | grep -q '"ok":true'; then
            log_info "Telegram webhook updated successfully for ${new_domain}."
        else
            log_warn "Webhook update returned a warning: ${webhook_response}"
        fi
    else
        log_error "Config file missing at ${CONFIG_FILE}; aborting domain change."
        restore_apache_service
        return 1
    fi

    local attempt http_status=""
    for attempt in {1..5}; do
        http_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$WEBHOOK_URL")
        [[ "$http_status" =~ ^(200|301|302)$ ]] && break
        log_warn "Endpoint ${WEBHOOK_URL} not ready yet (HTTP ${http_status:-000}). Retrying in 3 seconds..."
        sleep 3
    done

    if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
        log_info "Domain successfully migrated to ${full_domain_path}."
    else
        log_warn "Final verification failed for ${WEBHOOK_URL} (HTTP ${http_status:-000})."
    fi
    restore_apache_service
}

# ============================================================================
#  REMOVE DOMAIN
# ============================================================================
remove_domain() {
    show_logo
    ui_panel "REMOVE DOMAIN" "$C_BOLD$C_RED" "$C_RED" \
        "${C_WHITE}Disable an Apache 2 virtual host and optionally delete its SSL certificate.${C_RESET}"

    local conf_dir="/etc/apache2/sites-available"
    local domain_list=()
    local domain selection
    local -a conf_files=()

    if [ ! -d "$conf_dir" ]; then
        ui_err "Apache 2 configuration directory not found."
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu
        return 1
    fi

    mapfile -t conf_files < <(find "$conf_dir" -maxdepth 1 -type f -name '*.conf' -printf '%f\n' 2>/dev/null | sort)
    local conf
    for conf in "${conf_files[@]}"; do
        [ -z "$conf" ] && continue
        domain="${conf%.conf}"
        case "$domain" in
            000-default|default-ssl|000-default-le-ssl) continue ;;
        esac
        domain_list+=("$domain")
    done

    if [ ${#domain_list[@]} -eq 0 ]; then
        ui_info "No custom domains found to remove."
        printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
        show_menu
        return 0
    fi

    printf '\n'
    ui_info "Configured domains:"
    local idx
    for idx in "${!domain_list[@]}"; do
        printf '  %s%d)%s %s\n' "$C_CYAN" "$((idx + 1))" "$C_RESET" "${domain_list[$idx]}"
    done

    printf '\n  %s❯%s Select the domain you want to remove [1-%d]: ' \
        "$C_YELLOW" "$C_RESET" "${#domain_list[@]}"
    read -r selection
    if ! [[ "$selection" =~ ^[0-9]+$ ]] || [ "$selection" -lt 1 ] || [ "$selection" -gt "${#domain_list[@]}" ]; then
        ui_err "Invalid selection."
        sleep 2
        show_menu
        return 1
    fi
    domain="${domain_list[$((selection - 1))]}"

    local confirm
    printf '  %s❯%s Are you sure you want to remove %s? (y/n): ' "$C_YELLOW" "$C_RESET" "$domain"
    read -r confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        ui_info "Operation cancelled."
        sleep 1
        show_menu
        return 0
    fi

    mapfile -t conf_files < <(find "$conf_dir" -maxdepth 1 -type f -name "${domain}*.conf" -printf '%f\n' 2>/dev/null)
    [ ${#conf_files[@]} -eq 0 ] && conf_files=("${domain}.conf")

    for conf in "${conf_files[@]}"; do
        [ -z "$conf" ] && continue
        a2dissite "$conf" >/dev/null 2>&1
        rm -f "${conf_dir}/${conf}" "/etc/apache2/sites-enabled/${conf}"
    done

    if ! apache2ctl configtest >/dev/null 2>&1; then
        ui_err "Apache 2 configuration test failed after removing ${domain}. Please inspect manually."
        show_menu
        return 1
    fi

    if ! systemctl reload apache2 >/dev/null 2>&1; then
        ui_warn "Reload failed. Attempting restart..."
        systemctl restart apache2 >/dev/null 2>&1 || ui_err "Apache 2 restart failed."
    fi

    if [ -d "/etc/letsencrypt/live/${domain}" ]; then
        local delete_cert
        printf '  %s❯%s Delete existing SSL certificate for %s? (y/n): ' "$C_YELLOW" "$C_RESET" "$domain"
        read -r delete_cert
        if [[ "$delete_cert" =~ ^[Yy]$ ]]; then
            if wait_for_certbot; then
                certbot delete --cert-name "$domain" 2>/dev/null \
                    || ui_warn "Failed to delete certificate for ${domain}."
            else
                ui_warn "Certbot is busy. Skipping certificate deletion for ${domain}."
            fi
        fi
    fi

    ui_ok "Domain ${domain} removed."
    printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
    show_menu
}

# ============================================================================
#  DELETE CRON JOBS (www-data)
# ============================================================================
delete_cron_jobs() {
    local CRON_FILE="/var/spool/cron/crontabs/www-data"
    while true; do
        local delete_all selection tmp
        clear
        show_logo
        ui_panel "DELETE CRON JOBS" "$C_BOLD$C_RED" "$C_RED" \
            "${C_WHITE}Manage scheduled tasks for the www-data user.${C_RESET}"

        if [ ! -f "$CRON_FILE" ]; then
            ui_err "Cron file not found at ${CRON_FILE}."
            printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
            show_menu
            return 1
        fi

        if ! cat "$CRON_FILE" >/dev/null 2>&1; then
            ui_err "Cannot read ${CRON_FILE} (permission denied)."
            printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
            show_menu
            return 1
        fi

        local CRON_LINES=()
        mapfile -t CRON_LINES < <(awk '
            /^[[:space:]]*#/ {next}
            /^[[:space:]]*$/ {next}
            {print}
        ' "$CRON_FILE")

        if [ "${#CRON_LINES[@]}" -eq 0 ]; then
            ui_info "No cron entries found for www-data."
            printf '\n  %s❯%s Press Enter to return to main menu... ' "$C_YELLOW" "$C_RESET"; read -r
            show_menu
            return 0
        fi

        printf '\n  %sExisting cron entries:%s\n' "$C_CYAN" "$C_RESET"
        local idx
        for idx in "${!CRON_LINES[@]}"; do
            printf '  %s%d)%s %s\n' "$C_CYAN" "$((idx + 1))" "$C_RESET" "${CRON_LINES[$idx]}"
        done

        printf '\n  %s❯%s Delete all detected cron jobs? (y/n): ' "$C_YELLOW" "$C_RESET"
        read -r delete_all
        if [[ "$delete_all" =~ ^[Yy]$ ]]; then
            tmp=$(mktemp)
            if ! awk '
                /^[[:space:]]*#/ {print; next}
                /^[[:space:]]*$/ {print; next}
            ' "$CRON_FILE" > "$tmp"; then
                ui_err "Failed to clean cron file."
                rm -f "$tmp"
                printf '\n  %s❯%s Press Enter to return... ' "$C_YELLOW" "$C_RESET"; read -r
                show_menu
                return 1
            fi
            if ! mv "$tmp" "$CRON_FILE"; then
                ui_err "Failed to overwrite cron file."
                rm -f "$tmp"
                printf '\n  %s❯%s Press Enter to return... ' "$C_YELLOW" "$C_RESET"; read -r
                show_menu
                return 1
            fi
            chown www-data:crontab "$CRON_FILE" 2>/dev/null || true
            chmod 600 "$CRON_FILE" 2>/dev/null || true
            ui_ok "All detected cron jobs were deleted."
            sleep 1.5
            show_menu
            return 0
        fi

        printf '  %s0)%s Exit to Main Menu\n' "$C_RED" "$C_RESET"
        printf '\n  %s❯%s Select a cron entry to delete [0-%d]: ' \
            "$C_YELLOW" "$C_RESET" "${#CRON_LINES[@]}"
        read -r selection
        if [[ "$selection" == "0" ]]; then
            ui_info "Returning to main menu..."
            sleep 1
            show_menu
            return 0
        fi
        if ! [[ "$selection" =~ ^[0-9]+$ ]] || [ "$selection" -lt 1 ] || [ "$selection" -gt "${#CRON_LINES[@]}" ]; then
            ui_err "Invalid selection."
            sleep 1.5
            continue
        fi

        tmp=$(mktemp)
        if ! awk -v target="$selection" 'BEGIN{idx=0}
        {
            line=$0
            if (line ~ /^[[:space:]]*$/) {print; next}
            if (line ~ /^[[:space:]]*#/) {print; next}
            idx++
            if (idx==target) next
            print
        }' "$CRON_FILE" > "$tmp"; then
            ui_err "Failed to update cron file."
            rm -f "$tmp"
            printf '\n  %s❯%s Press Enter to return... ' "$C_YELLOW" "$C_RESET"; read -r
            show_menu
            return 1
        fi
        if ! mv "$tmp" "$CRON_FILE"; then
            ui_err "Failed to overwrite cron file."
            rm -f "$tmp"
            printf '\n  %s❯%s Press Enter to return... ' "$C_YELLOW" "$C_RESET"; read -r
            show_menu
            return 1
        fi
        chown www-data:crontab "$CRON_FILE" 2>/dev/null || true
        chmod 600 "$CRON_FILE" 2>/dev/null || true
        ui_ok "Cron entry #${selection} deleted."
        sleep 1.5
    done
}

# ============================================================================
#  ADDITIONAL BOT MANAGEMENT
# ----------------------------------------------------------------------------
#  These commands operate on bots installed under /var/www/html/<botname>/
#  (i.e. *separate* from the main /var/www/html/faoxima bot). They re-use the
#  root credentials saved by install_bot under ${CRED_FILE}.
# ============================================================================
manage_additional_bots() {
    show_logo
    if [ ! -d "$BOT_DIR" ]; then
        ui_err "The main Faoxima Bot is not installed (${BOT_DIR} not found)."
        ui_warn "You are not allowed to use this section without the main bot installed. Exiting..."
        sleep 2
        exit 1
    fi
    if check_marzban_installed; then
        ui_err "Additional bot management is not available when Marzban is installed."
        ui_warn "Exiting script..."
        sleep 2
        exit 1
    fi

    ui_panel "ADDITIONAL BOT MANAGEMENT" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Manage extra Faoxima bots running on additional domains.${C_RESET}"

    local width
    width=$(ui_term_width)
    ui_box_top "Sub-menu" "$C_CYAN$C_BOLD" "$C_CYAN" "$width"
    ui_box_blank "$C_CYAN" "$width"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}1)${C_RESET} Install Additional Bot"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}2)${C_RESET} Update Additional Bot"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}3)${C_RESET} Remove Additional Bot"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}4)${C_RESET} Export Additional Bot Database"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}5)${C_RESET} Import Additional Bot Database"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}6)${C_RESET} Configure Automated Backup for Additional Bot"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}7)${C_RESET} Disable Automated Backup for Additional Bot"
    ui_box_line  "$C_CYAN" "$width" "${C_WHITE}8)${C_RESET} Change Additional Bot Domain"
    ui_box_line  "$C_CYAN" "$width" "${C_RED}9)${C_RESET} Back to Main Menu"
    ui_box_blank "$C_CYAN" "$width"
    ui_box_bottom "$C_CYAN" "$width"

    local sub_option
    printf '\n  %s❯%s Select an option [1-9]: ' "$C_YELLOW" "$C_RESET"
    read -r sub_option
    case "$sub_option" in
        1) install_additional_bot ;;
        2) update_additional_bot ;;
        3) remove_additional_bot ;;
        4) export_additional_bot_database ;;
        5) import_additional_bot_database ;;
        6) configure_backup_additional_bot ;;
        7) disable_backup_additional_bot ;;
        8) change_additional_bot_domain ;;
        9) show_menu ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 1
            manage_additional_bots
            ;;
    esac
}

# Helper: list /var/www/html/* directories that aren't the main bot.
# Echoes one bot directory name per line.
_list_additional_bots() {
    ls -d /var/www/html/*/ 2>/dev/null \
        | grep -v "${BOT_DIR}/" \
        | xargs -r -n 1 basename
}

# Helper: prompt the user to pick one of the listed bot names.
# Sets the global SELECTED_BOT on success or returns 1 on cancel/error.
_prompt_select_bot() {
    local prompt_text="${1:-Select a bot by name}"
    local BOT_DIRS
    BOT_DIRS=$(_list_additional_bots)
    if [ -z "$BOT_DIRS" ]; then
        ui_err "No additional bots found in /var/www/html."
        return 1
    fi
    printf '\n  %sAvailable bots:%s\n' "$C_CYAN" "$C_RESET"
    printf '%s\n' "$BOT_DIRS" | nl -w 2 -s ') '
    printf '\n  %s❯%s %s: ' "$C_YELLOW" "$C_RESET" "$prompt_text"
    read -r SELECTED_BOT
    if [[ ! "$BOT_DIRS" =~ (^|[[:space:]])$SELECTED_BOT($|[[:space:]]) ]]; then
        ui_err "Invalid bot name."
        return 1
    fi
    return 0
}

# ─── INSTALL ADDITIONAL BOT ────────────────────────────────────────────────
install_additional_bot() {
    show_logo
    ui_panel "INSTALL ADDITIONAL BOT" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Deploy another Faoxima bot under a new domain.${C_RESET}"

    local ROOT_USER ROOT_PASS
    if [ ! -f "$CRED_FILE" ]; then
        ui_err "Root credentials file not found at ${CRED_FILE}."
        printf '  %s❯%s Please enter the root MySQL password: ' "$C_YELLOW" "$C_RESET"
        read -rs ROOT_PASS; echo
        ROOT_USER="root"
    else
        ROOT_USER=$(grep '\$user =' "$CRED_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$CRED_FILE" | awk -F"'" '{print $2}')
        if [ -z "$ROOT_USER" ] || [ -z "$ROOT_PASS" ]; then
            ui_err "Could not extract root credentials from ${CRED_FILE}."
            return 1
        fi
    fi

    local DOMAIN_NAME BOT_NAME BOT_TOKEN CHAT_ID
    while true; do
        printf '\n  %s❯%s Enter the domain for the additional bot: ' "$C_YELLOW" "$C_RESET"
        read -r DOMAIN_NAME
        [[ "$DOMAIN_NAME" =~ ^[a-zA-Z0-9.-]+$ ]] && break
        ui_err "Invalid domain format. Please try again."
    done

    ui_action "Stopping Apache 2 to free port 80..."
    systemctl stop apache2 2>/dev/null || true

    ui_action "Obtaining SSL certificate..."
    if ! wait_for_certbot; then
        ui_err "Certbot is busy. Please try again shortly."
        systemctl start apache2 2>/dev/null || true
        return 1
    fi
    certbot certonly --standalone --agree-tos --preferred-challenges http -d "$DOMAIN_NAME" || {
        ui_err "Error obtaining SSL certificate."
        systemctl start apache2 2>/dev/null || true
        return 1
    }

    ui_action "Restarting Apache 2..."
    systemctl start apache2 2>/dev/null || true

    while true; do
        printf '  %s❯%s Enter the bot name: ' "$C_YELLOW" "$C_RESET"
        read -r BOT_NAME
        if [[ "$BOT_NAME" =~ ^[a-zA-Z0-9_-]+$ && ! -d "/var/www/html/${BOT_NAME}" ]]; then
            break
        fi
        ui_err "Invalid or duplicate bot name. Please try again."
    done

    local APACHE_CONFIG="/etc/apache2/sites-available/${DOMAIN_NAME}.conf"
    if [ -f "$APACHE_CONFIG" ]; then
        ui_err "Apache 2 configuration for this domain already exists."
        return 1
    fi

    ui_action "Configuring Apache 2 for ${DOMAIN_NAME}..."
    cat > "$APACHE_CONFIG" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN_NAME}
    Redirect permanent / https://${DOMAIN_NAME}/
</VirtualHost>

<VirtualHost *:443>
    ServerName ${DOMAIN_NAME}
    DocumentRoot /var/www/html/${BOT_NAME}

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${DOMAIN_NAME}/privkey.pem
</VirtualHost>
EOF

    local BOT_PATH="/var/www/html/${BOT_NAME}"
    mkdir -p "$BOT_PATH"
    a2ensite "${DOMAIN_NAME}.conf" >/dev/null
    systemctl reload apache2

    ui_action "Cloning Faoxima source code..."
    rm -rf "$BOT_PATH"
    git clone "${FAOXIMA_GITHUB}.git" "$BOT_PATH" || {
        ui_err "Failed to clone the repository."
        return 1
    }

    while true; do
        printf '  %s❯%s Enter the bot token: ' "$C_YELLOW" "$C_RESET"
        read -r BOT_TOKEN
        [[ "$BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]] && break
        ui_err "Invalid bot token format. Please try again."
    done
    while true; do
        printf '  %s❯%s Enter the chat ID: ' "$C_YELLOW" "$C_RESET"
        read -r CHAT_ID
        [[ "$CHAT_ID" =~ ^-?[0-9]+$ ]] && break
        ui_err "Invalid chat ID format. Please try again."
    done

    local DB_NAME="faoxima_${BOT_NAME}"
    local DB_USERNAME="$DB_NAME"
    local DEFAULT_PASSWORD DB_PASSWORD
    DEFAULT_PASSWORD=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    printf '  %s❯%s Enter the database password [default: %s%s%s]: ' \
        "$C_YELLOW" "$C_RESET" "$C_CYAN" "$DEFAULT_PASSWORD" "$C_RESET"
    read -r DB_PASSWORD
    DB_PASSWORD=${DB_PASSWORD:-$DEFAULT_PASSWORD}

    ui_action "Creating database and user..."
    mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "CREATE DATABASE ${DB_NAME};" || {
        ui_err "Failed to create database."
        return 1
    }
    mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "CREATE USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';" || {
        ui_err "Failed to create database user."
        return 1
    }
    mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USERNAME}'@'localhost';" || {
        ui_err "Failed to grant privileges to user."
        return 1
    }
    mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "FLUSH PRIVILEGES;"

    local CONFIG_FILE="${BOT_PATH}/config.php"
    local secrettoken admin_panel_token
    secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    admin_panel_token=$(faoxima_generate_token)

    ui_action "Writing config.php..."
    cat > "$CONFIG_FILE" <<EOF
<?php
\$APIKEY = '${BOT_TOKEN}';
\$usernamedb = '${DB_USERNAME}';
\$passworddb = '${DB_PASSWORD}';
\$dbname = '${DB_NAME}';
\$domainhosts = '${DOMAIN_NAME}/${BOT_NAME}';
\$adminnumber = '${CHAT_ID}';
\$usernamebot = '${BOT_NAME}';
\$secrettoken = '${secrettoken}';
\$admin_panel_token = '${admin_panel_token}';
\$connect = mysqli_connect('localhost', \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) {
    die('Database connection failed: ' . \$connect->connect_error);
}
mysqli_set_charset(\$connect, 'utf8mb4');
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
\$dsn = "mysql:host=localhost;dbname=\$dbname;charset=utf8mb4";
try {
     \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options);
} catch (\PDOException \$e) {
     throw new \PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>
EOF
    sleep 1
    chown -R www-data:www-data "$BOT_PATH"
    chmod -R 755 "$BOT_PATH"

    # Show admin panel token + user confirmation BEFORE we hit table.php.
    clear
    show_logo
    faoxima_show_and_confirm_token "$admin_panel_token"

    ui_action "Setting webhook for bot..."
    curl -F "url=https://${DOMAIN_NAME}/${BOT_NAME}/index.php" \
         -F "secret_token=${secrettoken}" \
         "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" || {
        ui_err "Failed to set webhook for bot."
        return 1
    }

    local MESSAGE="✅ Faoxima additional bot installed! Send /start to begin."
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/sendMessage" \
        -d chat_id="${CHAT_ID}" -d text="${MESSAGE}" || ui_warn "Failed to send Telegram welcome message."

    local TABLE_SETUP_URL="https://${DOMAIN_NAME}/${BOT_NAME}/table.php?token=$(printf '%s' "$admin_panel_token" | sed 's/&/%26/g')"
    ui_action "Setting up database tables (with token)..."
    curl -s "$TABLE_SETUP_URL" >/dev/null || \
        ui_warn "Failed to fetch ${TABLE_SETUP_URL} — please open it manually in a browser."

    clear
    show_logo
    ui_status_table "ADDITIONAL BOT INSTALLED" "$C_GREEN" \
        "Bot URL|${C_GREEN}https://${DOMAIN_NAME}${C_RESET}" \
        "phpMyAdmin|${C_BLUE}https://${DOMAIN_NAME}/phpmyadmin${C_RESET}" \
        "Database name|${C_CYAN}${DB_NAME}${C_RESET}" \
        "Database user|${C_CYAN}${DB_USERNAME}${C_RESET}" \
        "Database password|${C_CYAN}${DB_PASSWORD}${C_RESET}" \
        "Admin panel token|${C_YELLOW}${admin_panel_token}${C_RESET}"
    printf '\n'
}

# ─── UPDATE ADDITIONAL BOT ─────────────────────────────────────────────────
update_additional_bot() {
    show_logo
    ui_panel "UPDATE ADDITIONAL BOT" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Pulls the latest Faoxima source while preserving config.php.${C_RESET}"

    local SELECTED_BOT
    if ! _prompt_select_bot "Select a bot to update"; then return 1; fi

    local BOT_PATH="/var/www/html/${SELECTED_BOT}"
    local CONFIG_PATH="${BOT_PATH}/config.php"
    local TEMP_CONFIG_PATH="/root/${SELECTED_BOT}_config.php"

    ui_action "Updating ${SELECTED_BOT}..."
    if [ -f "$CONFIG_PATH" ]; then
        mv "$CONFIG_PATH" "$TEMP_CONFIG_PATH" || { ui_err "Failed to backup config.php."; return 1; }
    else
        ui_err "config.php not found in ${BOT_PATH}."
        return 1
    fi

    rm -rf "$BOT_PATH" || { ui_err "Failed to remove old bot directory."; return 1; }
    if ! git clone "${FAOXIMA_GITHUB}.git" "$BOT_PATH"; then
        ui_err "Failed to clone the repository."
        return 1
    fi
    if ! mv "$TEMP_CONFIG_PATH" "$CONFIG_PATH"; then
        ui_err "Failed to restore config.php."
        return 1
    fi

    chown -R www-data:www-data "$BOT_PATH"
    chmod -R 755 "$BOT_PATH"

    local URL admin_panel_token
    URL=$(grep '\$domainhosts' "$CONFIG_PATH" | cut -d"'" -f2)
    if [ -z "$URL" ]; then
        ui_err "Failed to extract domain URL from config.php."
        return 1
    fi
    admin_panel_token=$(grep '^\$admin_panel_token' "$CONFIG_PATH" | cut -d"'" -f2)
    if [ -z "$admin_panel_token" ]; then
        admin_panel_token=$(faoxima_generate_token)
        faoxima_persist_token "$CONFIG_PATH" "$admin_panel_token"
        ui_info "No admin panel token found — a new one was generated."
        faoxima_show_and_confirm_token "$admin_panel_token"
    fi

    if ! curl -s "https://${URL}/table.php?token=$(printf '%s' "$admin_panel_token" | sed 's/&/%26/g')" >/dev/null; then
        ui_warn "Failed to execute table.php — please verify manually."
    fi
    ui_ok "${SELECTED_BOT} has been successfully updated."
}

# ─── REMOVE ADDITIONAL BOT ─────────────────────────────────────────────────
remove_additional_bot() {
    show_logo
    ui_panel "REMOVE ADDITIONAL BOT" "$C_BOLD$C_RED" "$C_RED" \
        "${C_WHITE}Drops the bot's database/user, removes its files and Apache 2 vhost.${C_RESET}"

    local SELECTED_BOT
    if ! _prompt_select_bot "Select a bot to remove"; then return 1; fi

    local BOT_PATH="/var/www/html/${SELECTED_BOT}"
    local CONFIG_PATH="${BOT_PATH}/config.php"

    local CONFIRM_REMOVE BACKUP_CONFIRM
    printf '  %s❯%s Are you sure you want to remove %s? (yes/no): ' "$C_YELLOW" "$C_RESET" "$SELECTED_BOT"
    read -r CONFIRM_REMOVE
    [[ "$CONFIRM_REMOVE" != "yes" ]] && { ui_warn "Aborted."; return 1; }
    printf '  %s❯%s Have you backed up the database? (yes/no): ' "$C_YELLOW" "$C_RESET"
    read -r BACKUP_CONFIRM
    [[ "$BACKUP_CONFIRM" != "yes" ]] && { ui_warn "Aborted. Please backup the database first."; return 1; }

    local ROOT_USER ROOT_PASS
    if [ -f "$CRED_FILE" ]; then
        ROOT_USER=$(grep '\$user =' "$CRED_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$CRED_FILE" | awk -F"'" '{print $2}')
    else
        printf '  %s❯%s Root credentials file not found. Enter MySQL root password: ' "$C_YELLOW" "$C_RESET"
        read -rs ROOT_PASS; echo
        ROOT_USER="root"
    fi

    local DOMAIN_NAME DB_NAME DB_USER
    DOMAIN_NAME=$(grep '\$domainhosts' "$CONFIG_PATH" | cut -d"'" -f2 | cut -d'/' -f1)
    DB_NAME=$(awk -F"'" '/\$dbname = / {print $2}'    "$CONFIG_PATH")
    DB_USER=$(awk -F"'" '/\$usernamedb = / {print $2}' "$CONFIG_PATH")

    ui_action "Removing database ${DB_NAME}..."
    if mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" 2>/tmp/db_remove_error.log; then
        ui_ok "Database ${DB_NAME} removed."
    else
        ui_err "Failed to remove database ${DB_NAME}."
    fi
    ui_action "Removing user ${DB_USER}..."
    if mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';" 2>/tmp/user_remove_error.log; then
        ui_ok "User ${DB_USER} removed."
    else
        ui_err "Failed to remove user ${DB_USER}."
    fi

    ui_action "Removing bot directory ${BOT_PATH}..."
    rm -rf "$BOT_PATH" || { ui_err "Failed to remove bot directory."; return 1; }

    local APACHE_CONF="/etc/apache2/sites-available/${DOMAIN_NAME}.conf"
    if [ -f "$APACHE_CONF" ]; then
        ui_action "Removing Apache 2 configuration for ${DOMAIN_NAME}..."
        a2dissite "${DOMAIN_NAME}.conf" >/dev/null 2>&1
        rm -f "$APACHE_CONF" "/etc/apache2/sites-enabled/${DOMAIN_NAME}.conf"
        systemctl reload apache2
    else
        ui_warn "Apache 2 configuration for ${DOMAIN_NAME} not found."
    fi

    ui_ok "${SELECTED_BOT} has been successfully removed."
}

# ─── EXPORT ADDITIONAL BOT DATABASE ────────────────────────────────────────
export_additional_bot_database() {
    show_logo
    ui_panel "EXPORT ADDITIONAL BOT DATABASE" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Dumps a selected additional bot's MySQL database under /root.${C_RESET}"

    local SELECTED_BOT
    if ! _prompt_select_bot "Enter the bot name to export"; then return 1; fi

    local BOT_PATH="/var/www/html/${SELECTED_BOT}"
    local CONFIG_PATH="${BOT_PATH}/config.php"
    [ -f "$CONFIG_PATH" ] || { ui_err "config.php not found for ${SELECTED_BOT}."; return 1; }

    local ROOT_USER ROOT_PASS
    if [ -f "$CRED_FILE" ]; then
        ROOT_USER=$(grep '\$user =' "$CRED_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$CRED_FILE" | awk -F"'" '{print $2}')
    else
        ui_warn "Root credentials file not found."
        printf '  %s❯%s Enter MySQL root password: ' "$C_YELLOW" "$C_RESET"
        read -rs ROOT_PASS; echo
        [ -z "$ROOT_PASS" ] && { ui_err "Password cannot be empty. Exiting..."; return 1; }
        ROOT_USER="root"
        if ! echo "SELECT 1" | mysql -u "$ROOT_USER" -p"$ROOT_PASS" 2>/dev/null; then
            ui_err "Invalid root credentials. Exiting..."
            return 1
        fi
    fi

    local DB_USER DB_PASS DB_NAME
    DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname'     "$CONFIG_PATH" | awk -F"'" '{print $2}')
    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        ui_err "Failed to extract database credentials from ${CONFIG_PATH}."
        return 1
    fi

    ui_action "Verifying database existence..."
    if ! mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "USE ${DB_NAME};" 2>/dev/null; then
        ui_err "Database ${DB_NAME} does not exist or credentials are incorrect."
        return 1
    fi

    local BACKUP_FILE="/root/${DB_NAME}_backup.sql"
    ui_action "Creating backup at ${BACKUP_FILE}..."
    if ! mysqldump -u "$ROOT_USER" -p"$ROOT_PASS" "$DB_NAME" > "$BACKUP_FILE"; then
        ui_err "Failed to create database backup."
        return 1
    fi
    ui_ok "Backup successfully created at ${BACKUP_FILE}."
}

# ─── IMPORT ADDITIONAL BOT DATABASE ────────────────────────────────────────
import_additional_bot_database() {
    show_logo
    ui_panel "IMPORT ADDITIONAL BOT DATABASE" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Restore a previously-saved SQL dump into a selected additional bot.${C_RESET}"

    local ROOT_USER ROOT_PASS
    if [ -f "$CRED_FILE" ]; then
        ROOT_USER=$(grep '\$user =' "$CRED_FILE" | awk -F"'" '{print $2}')
        ROOT_PASS=$(grep '\$pass =' "$CRED_FILE" | awk -F"'" '{print $2}')
    else
        ui_warn "Root credentials file not found."
        printf '  %s❯%s Enter MySQL root password: ' "$C_YELLOW" "$C_RESET"
        read -rs ROOT_PASS; echo
        [ -z "$ROOT_PASS" ] && { ui_err "Password cannot be empty."; return 1; }
        ROOT_USER="root"
        if ! echo "SELECT 1" | mysql -u "$ROOT_USER" -p"$ROOT_PASS" 2>/dev/null; then
            ui_err "Invalid root credentials."
            return 1
        fi
    fi

    local SQL_FILES SELECTED_FILE FILE_SELECTION
    SQL_FILES=$(find /root -maxdepth 1 -type f -name "*.sql")
    if [ -z "$SQL_FILES" ]; then
        ui_err "No .sql files found in /root. Please provide a valid .sql file."
        return 1
    fi
    printf '\n  %sAvailable .sql files:%s\n' "$C_CYAN" "$C_RESET"
    printf '%s\n' "$SQL_FILES" | nl -w 2 -s ') '

    printf '\n  %s❯%s Enter the number of the file or provide a full path: ' "$C_YELLOW" "$C_RESET"
    read -r FILE_SELECTION
    if [[ "$FILE_SELECTION" =~ ^[0-9]+$ ]]; then
        SELECTED_FILE=$(echo "$SQL_FILES" | sed -n "${FILE_SELECTION}p")
    else
        SELECTED_FILE="$FILE_SELECTION"
    fi
    [ -f "$SELECTED_FILE" ] || { ui_err "Selected file does not exist."; return 1; }

    local SELECTED_BOT
    if ! _prompt_select_bot "Select a bot to import into"; then return 1; fi

    local BOT_PATH="/var/www/html/${SELECTED_BOT}"
    local CONFIG_PATH="${BOT_PATH}/config.php"
    [ -f "$CONFIG_PATH" ] || { ui_err "config.php not found for ${SELECTED_BOT}."; return 1; }

    local DB_USER DB_PASS DB_NAME
    DB_USER=$(grep '^\$usernamedb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_NAME=$(grep '^\$dbname'     "$CONFIG_PATH" | awk -F"'" '{print $2}')
    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        ui_err "Failed to extract database credentials from ${CONFIG_PATH}."
        return 1
    fi

    ui_action "Verifying database existence..."
    if ! mysql -u "$ROOT_USER" -p"$ROOT_PASS" -e "USE ${DB_NAME};" 2>/dev/null; then
        ui_err "Database ${DB_NAME} does not exist or credentials are incorrect."
        return 1
    fi

    ui_action "Importing database from ${SELECTED_FILE} into ${DB_NAME}..."
    if ! mysql -u "$ROOT_USER" -p"$ROOT_PASS" "$DB_NAME" < "$SELECTED_FILE"; then
        ui_err "Failed to import database."
        return 1
    fi
    ui_ok "Database successfully imported from ${SELECTED_FILE} into ${DB_NAME}."
}

# ─── CONFIGURE BACKUP FOR ADDITIONAL BOT ───────────────────────────────────
configure_backup_additional_bot() {
    show_logo
    ui_panel "AUTOMATED BACKUP — ADDITIONAL BOT" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Schedule recurring backups of an additional bot's database.${C_RESET}"

    local SELECTED_BOT
    if ! _prompt_select_bot "Select a bot"; then return 1; fi

    local BOT_PATH="/var/www/html/${SELECTED_BOT}"
    local CONFIG_PATH="${BOT_PATH}/config.php"
    [ -f "$CONFIG_PATH" ] || { ui_err "config.php not found for ${SELECTED_BOT}."; return 1; }

    local DB_NAME DB_USER DB_PASS TELEGRAM_TOKEN TELEGRAM_CHAT_ID
    DB_NAME=$(grep '^\$dbname'         "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_USER=$(grep '^\$usernamedb'     "$CONFIG_PATH" | awk -F"'" '{print $2}')
    DB_PASS=$(grep '^\$passworddb'     "$CONFIG_PATH" | awk -F"'" '{print $2}')
    TELEGRAM_TOKEN=$(grep '^\$APIKEY'      "$CONFIG_PATH" | awk -F"'" '{print $2}')
    TELEGRAM_CHAT_ID=$(grep '^\$adminnumber' "$CONFIG_PATH" | awk -F"'" '{print $2}')

    if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
        ui_err "Failed to extract database credentials from ${CONFIG_PATH}."
        return 1
    fi
    if [ -z "$TELEGRAM_TOKEN" ] || [ -z "$TELEGRAM_CHAT_ID" ]; then
        ui_err "Telegram token or chat ID not found in ${CONFIG_PATH}."
        return 1
    fi

    local frequency cron_time
    while true; do
        printf '\n  %sChoose backup frequency:%s\n' "$C_CYAN" "$C_RESET"
        printf '  %s1)%s Every minute\n' "$C_CYAN" "$C_RESET"
        printf '  %s2)%s Every hour\n'   "$C_CYAN" "$C_RESET"
        printf '  %s3)%s Every day\n'    "$C_CYAN" "$C_RESET"
        printf '  %s4)%s Every week\n'   "$C_CYAN" "$C_RESET"
        printf '\n  %s❯%s Enter your choice (1-4): ' "$C_YELLOW" "$C_RESET"
        read -r frequency
        case "$frequency" in
            1) cron_time="* * * * *" ; break ;;
            2) cron_time="0 * * * *" ; break ;;
            3) cron_time="0 0 * * *" ; break ;;
            4) cron_time="0 0 * * 0" ; break ;;
            *) ui_err "Invalid option. Please try again." ;;
        esac
    done

    local BACKUP_SCRIPT="/root/${SELECTED_BOT}_auto_backup.sh"
    cat > "$BACKUP_SCRIPT" <<EOF
#!/usr/bin/env bash
DB_NAME="${DB_NAME}"
DB_USER="${DB_USER}"
DB_PASS="${DB_PASS}"
TELEGRAM_TOKEN="${TELEGRAM_TOKEN}"
TELEGRAM_CHAT_ID="${TELEGRAM_CHAT_ID}"

BACKUP_FILE="/root/\${DB_NAME}_\$(date +"%Y%m%d_%H%M%S").sql"
if mysqldump -u "\$DB_USER" -p"\$DB_PASS" "\$DB_NAME" > "\$BACKUP_FILE"; then
    curl -F document=@"\$BACKUP_FILE" "https://api.telegram.org/bot\$TELEGRAM_TOKEN/sendDocument" -F chat_id="\$TELEGRAM_CHAT_ID"
    [ \$? -eq 0 ] && rm "\$BACKUP_FILE"
else
    echo "[ERROR] Failed to create database backup."
fi
EOF
    chmod +x "$BACKUP_SCRIPT"
    (crontab -l 2>/dev/null; echo "${cron_time} bash ${BACKUP_SCRIPT}") | crontab -
    ui_ok "Automated backup configured successfully for ${SELECTED_BOT}."
}

# ─── DISABLE BACKUP FOR ADDITIONAL BOT ─────────────────────────────────────
disable_backup_additional_bot() {
    show_logo
    ui_panel "DISABLE AUTOMATED BACKUP" "$C_BOLD$C_RED" "$C_RED" \
        "${C_WHITE}Removes the cron entry and helper script for an additional bot's backup.${C_RESET}"

    local SELECTED_BOT
    if ! _prompt_select_bot "Select a bot"; then return 1; fi

    local BACKUP_SCRIPT="/root/${SELECTED_BOT}_auto_backup.sh"
    local CURRENT_CRON
    CURRENT_CRON=$(crontab -l 2>/dev/null | grep "$BACKUP_SCRIPT")
    if [ -z "$CURRENT_CRON" ]; then
        ui_warn "No automated backup found for ${SELECTED_BOT}."
        return 1
    fi
    crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab -
    [ -f "$BACKUP_SCRIPT" ] && rm "$BACKUP_SCRIPT"
    ui_ok "Automated backup disabled successfully for ${SELECTED_BOT}."
}

# ─── CHANGE ADDITIONAL BOT DOMAIN ──────────────────────────────────────────
change_additional_bot_domain() {
    show_logo
    ui_panel "CHANGE ADDITIONAL BOT DOMAIN" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Re-issues SSL, updates config.php and re-registers the Telegram webhook.${C_RESET}"

    log_action "Initiating additional bot domain change workflow."
    local SELECTED_BOT
    if ! _prompt_select_bot "Select a bot"; then
        log_warn "No bot selected during additional-bot domain change."
        return 1
    fi

    local BOT_PATH="/var/www/html/${SELECTED_BOT}"
    local BOT_PARENT_DIR
    BOT_PARENT_DIR="$(dirname "$BOT_PATH")"
    local CONFIG_PATH="${BOT_PATH}/config.php"
    if [ ! -f "$CONFIG_PATH" ]; then
        ui_err "config.php not found for ${SELECTED_BOT}."
        log_error "config.php missing for $SELECTED_BOT while changing domain."
        return 1
    fi

    local current_domainhosts sanitized current_domain
    current_domainhosts=$(grep '^\$domainhosts' "$CONFIG_PATH" | awk -F"'" '{print $2}')
    sanitized=${current_domainhosts#http://}
    sanitized=${sanitized#https://}
    sanitized=${sanitized#/}
    current_domain=${sanitized%%/*}
    log_info "Processing domain change for bot '${SELECTED_BOT}' (current domain: ${current_domain:-unknown})."

    local NEW_DOMAIN
    while true; do
        printf '  %s❯%s Enter the new domain (e.g. example.com): ' "$C_YELLOW" "$C_RESET"
        read -r NEW_DOMAIN
        if [[ "$NEW_DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]]; then
            log_info "User entered new domain '$NEW_DOMAIN' for bot '$SELECTED_BOT'."
            break
        fi
        ui_err "Invalid domain format. Please try again."
    done

    log_action "Disabling Apache 2 service before domain change..."
    systemctl disable apache2 >/dev/null 2>&1 || true

    if ! configure_apache_vhost "$NEW_DOMAIN" "$BOT_PARENT_DIR"; then
        log_error "Unable to prepare Apache 2 virtual host for ${NEW_DOMAIN} (bot ${SELECTED_BOT})."
        restore_apache_service
        return 1
    fi
    log_action "Stopping Apache 2 to configure SSL..."
    if ! systemctl stop apache2; then
        log_error "Failed to stop Apache 2 while preparing SSL for ${NEW_DOMAIN}."
        restore_apache_service
        return 1
    fi
    log_action "Configuring SSL certificate for ${NEW_DOMAIN}..."
    if ! wait_for_certbot; then
        log_error "Certbot is already running. Please try again after the current process completes."
        restore_apache_service
        return 1
    fi
    if ! certbot --apache --redirect --agree-tos --preferred-challenges http \
            --non-interactive --force-renewal --cert-name "$NEW_DOMAIN" -d "$NEW_DOMAIN"; then
        log_error "SSL configuration failed for ${NEW_DOMAIN}, rolling back certificate changes."
        if wait_for_certbot; then
            certbot delete --cert-name "$NEW_DOMAIN" 2>/dev/null
        fi
        restore_apache_service
        return 1
    fi

    local path_segment full_domain_path NEW_SECRET BOT_TOKEN updated_domainhosts WEBHOOK_URL webhook_response
    if [ -f "$CONFIG_PATH" ]; then
        cp "$CONFIG_PATH" "${CONFIG_PATH}.$(date +%s).bak"
        sanitized=${current_domainhosts#http://}
        sanitized=${sanitized#https://}
        sanitized=${sanitized#/}
        path_segment=""
        if [[ "$sanitized" == */* ]]; then
            path_segment=${sanitized#*/}
            path_segment=${path_segment%/}
        fi
        [ -z "$path_segment" ] && path_segment="$SELECTED_BOT"
        full_domain_path="${NEW_DOMAIN}/${path_segment}"
        sed -i "s|\$domainhosts = '.*';|\$domainhosts = '${full_domain_path}';|" "$CONFIG_PATH"

        NEW_SECRET=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9')
        sed -i "s|\$secrettoken = '.*';|\$secrettoken = '${NEW_SECRET}';|" "$CONFIG_PATH"

        BOT_TOKEN=$(awk -F"'" '/\$APIKEY/{print $2}' "$CONFIG_PATH")
        updated_domainhosts=$(awk -F"'" '/\$domainhosts/{print $2}' "$CONFIG_PATH" | head -1)
        updated_domainhosts=${updated_domainhosts%/}
        if [[ "$updated_domainhosts" =~ ^https?:// ]]; then
            WEBHOOK_URL="${updated_domainhosts}/index.php"
        else
            WEBHOOK_URL="https://${updated_domainhosts}/index.php"
        fi

        webhook_response=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
            -F "url=${WEBHOOK_URL}" -F "secret_token=${NEW_SECRET}")
        if echo "$webhook_response" | grep -q '"ok":true'; then
            log_info "Telegram webhook updated successfully for ${NEW_DOMAIN} (bot ${SELECTED_BOT})."
        else
            log_warn "Webhook update returned a warning for ${NEW_DOMAIN}: ${webhook_response}"
        fi
    else
        log_error "Config file missing at ${CONFIG_PATH}; aborting domain change."
        restore_apache_service
        return 1
    fi

    if [ -n "$current_domain" ] && [ "$current_domain" != "$NEW_DOMAIN" ] \
        && [ -f "/etc/apache2/sites-available/${current_domain}.conf" ]; then
        a2dissite "${current_domain}.conf" >/dev/null 2>&1
        log_info "Disabled old Apache 2 site ${current_domain}.conf."
    fi

    local attempt http_status=""
    for attempt in {1..5}; do
        http_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$WEBHOOK_URL")
        [[ "$http_status" =~ ^(200|301|302)$ ]] && break
        log_warn "Endpoint ${WEBHOOK_URL} not ready yet (HTTP ${http_status:-000}). Retrying in 3 seconds..."
        sleep 3
    done
    if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
        log_info "Additional bot domain successfully migrated to ${full_domain_path}."
    else
        log_warn "Final verification failed for ${WEBHOOK_URL} (HTTP ${http_status:-000})."
    fi

    if [ -n "$current_domain" ] && [ "$current_domain" != "$NEW_DOMAIN" ] \
        && [ -d "/etc/letsencrypt/live/${current_domain}" ]; then
        local delete_old_cert
        printf '  %s❯%s Delete old SSL certificate for %s? (y/n): ' "$C_YELLOW" "$C_RESET" "$current_domain"
        read -r delete_old_cert
        if [[ "$delete_old_cert" =~ ^[Yy]$ ]]; then
            if wait_for_certbot; then
                certbot delete --cert-name "$current_domain" 2>/dev/null \
                    || ui_warn "Failed to delete certificate for ${current_domain}."
                log_info "Requested deletion of old certificate for ${current_domain}."
            else
                log_warn "Certbot is busy; skipping deletion of legacy certificate for ${current_domain}."
            fi
        fi
    fi

    ui_ok "Domain updated successfully for ${SELECTED_BOT}."
    log_info "Domain change completed for '${SELECTED_BOT}'. New domain: ${NEW_DOMAIN}."
    restore_apache_service
    printf '\n  %s❯%s Press Enter to return to the Additional Bot menu... ' "$C_YELLOW" "$C_RESET"; read -r
    manage_additional_bots
}

# ============================================================================
#  ENTRY POINT
# ============================================================================
process_arguments() {
    local version=""
    case "$1" in
        -v*)
            version="${1#-v}"
            if [ -n "$version" ]; then
                install_bot "-v" "$version"
            else
                if [ -n "$2" ]; then
                    install_bot "-v" "$2"
                else
                    ui_err "Please specify a version with -v (e.g. -v 0.0.1)"
                    exit 1
                fi
            fi
            ;;
        -beta|--beta)
            install_bot "-beta"
            ;;
        -update)
            update_bot "$2"
            ;;
        *)
            show_menu
            ;;
    esac
}

process_arguments "${1:-}" "${2:-}"
