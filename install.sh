#!/usr/bin/env bash

set -o pipefail

if locale -a 2>/dev/null | grep -qi '^C\.utf8$'; then
    export LC_ALL=C.UTF-8
elif locale -a 2>/dev/null | grep -qi '^C\.UTF-8$'; then
    export LC_ALL=C.UTF-8
fi

readonly FAOXIMA_VERSION="1.0.2"
readonly FAOXIMA_REPO="Mmd-Amir/Faoxima"
readonly FAOXIMA_GITHUB="https://github.com/${FAOXIMA_REPO}"
readonly FAOXIMA_TELEGRAM="https://t.me/faoxima"

readonly DEFAULT_PROJECT_DIR="/opt/faoxima"
_script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly STAGING_SOURCE_DIR="$_script_dir"
if [ "$_script_dir" = "$DEFAULT_PROJECT_DIR" ] && { [ -f "${_script_dir}/docker-compose.yml" ] || [ -f "${_script_dir}/config.php" ]; }; then
    readonly PROJECT_DIR="$_script_dir"
else
    readonly PROJECT_DIR="$DEFAULT_PROJECT_DIR"
fi
unset _script_dir
readonly BOT_DIR="$PROJECT_DIR"
readonly COMPOSE_FILE="${PROJECT_DIR}/docker-compose.yml"
readonly ENV_FILE="${PROJECT_DIR}/.env"
readonly ENV_EXAMPLE="${PROJECT_DIR}/.env.example"
readonly LOG_FILE="/var/log/faoxima_installer.log"
readonly TMP_DOWNLOAD="/tmp/faoxima_download"
readonly TMP_UPDATE="/tmp/faoxima_update"
readonly CACHE_DIR="/tmp/faoxima_menu_cache"
readonly DEFAULT_DB_NAME="faoxima"
readonly INSTALL_SCRIPT_PATH="${PROJECT_DIR}/install.sh"
readonly INSTALL_SCRIPT_LINK="/usr/local/bin/faoxima"
readonly NGINX_CONF_DIR="${PROJECT_DIR}/nginx/conf.d"
readonly NGINX_TEMPLATE="${PROJECT_DIR}/docker/nginx/nginx.conf.template"
readonly BOT_NGINX_TEMPLATE="${PROJECT_DIR}/docker/nginx/bot.conf.template"
readonly NGINX_BOTS_CONF_DIR="${PROJECT_DIR}/nginx/conf.d/bots"
readonly BOTS_DIR="$(dirname "$PROJECT_DIR")/bots"
readonly BOT_COMPOSE_PREFIX="${PROJECT_DIR}/docker-compose.bot-"

if [ "$(id -u)" -ne 0 ]; then
    printf '\033[1;31m[ERROR]\033[0m This script must be run as \033[1mroot\033[0m.\n' >&2
    exit 1
fi

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


ui_term_width() {
    if [ -n "$UI_FIXED_WIDTH" ]; then
        printf '%d' "$UI_FIXED_WIDTH"
        return
    fi
    local w
    w=$(tput cols 2>/dev/null || echo 80)
    [[ -z "$w" || "$w" -lt 60 ]] && w=80
    [[ "$w" -gt 110 ]] && w=110
    printf '%d' "$w"
}

ui_strlen() {
    local stripped
    stripped=$(printf '%s' "$1" | sed -E $'s/\033\\[[0-9;]*[A-Za-z]//g')
    printf '%d' "${#stripped}"
}

ui_content_width() {
    local title="$1" min_content="${#title}" line len term_w
    shift
    for line in "$@"; do
        len=$(ui_strlen "$line")
        [ "$len" -gt "$min_content" ] && min_content=$len
    done
    term_w=$(ui_term_width)
    local wanted=$((min_content + 6))
    [ "$wanted" -lt 40 ] && wanted=40
    [ "$wanted" -gt "$term_w" ] && wanted=$term_w
    printf '%d' "$wanted"
}

ui_wrap_line() {
    local line="$1" max_len="$2"
    local prefix="" suffix="" plain="$line"
    if [[ "$line" =~ ^($'\033'\[[0-9\;]*m)(.*)$ ]]; then
        prefix="${BASH_REMATCH[1]}"
        plain="${BASH_REMATCH[2]}"
    fi
    if [[ "$plain" == *$'\033['*m ]]; then
        suffix=$(printf '%s' "$plain" | grep -oE $'\033\\[[0-9;]*m' | tail -1)
        plain=$(printf '%s' "$plain" | sed -E $'s/\033\\[[0-9;]*m//g')
    fi
    if [ "${#plain}" -le "$max_len" ]; then
        printf '%s\n' "$line"
        return
    fi
    local word cur=""
    for word in $plain; do
        if [ -z "$cur" ]; then
            cur="$word"
        elif [ "$(( ${#cur} + 1 + ${#word} ))" -le "$max_len" ]; then
            cur="${cur} ${word}"
        else
            printf '%s%s%s\n' "$prefix" "$cur" "$suffix"
            cur="$word"
        fi
    done
    [ -n "$cur" ] && printf '%s%s%s\n' "$prefix" "$cur" "$suffix"
}

ui_repeat() {
    local ch="$1" n="$2" out="" i=0
    while [ "$i" -lt "$n" ]; do
        out+="$ch"
        i=$((i + 1))
    done
    printf '%s' "$out"
}

ui_spaces() { printf '%*s' "$1" ''; }

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

ui_box_top_plain() {
    local border_color="$1" width="$2"
    printf '%s╭%s╮%s\n' "$border_color" "$(ui_repeat '─' $((width - 2)))" "$C_RESET"
}

ui_box_blank() {
    local border_color="$1" width="$2"
    printf '%s│%s│%s\n' "$border_color" "$(ui_spaces $((width - 2)))" "$C_RESET"
}

ui_box_line() {
    local border_color="$1" width="$2" content="$3"
    local inner=$((width - 4))
    local visible_len pad
    visible_len=$(ui_strlen "$content")
    pad=$((inner - visible_len))
    [ "$pad" -lt 0 ] && pad=0
    printf '%s│%s %b%s %s│%s\n' \
        "$border_color" "$C_RESET" \
        "$content" "$(ui_spaces $pad)" \
        "$border_color" "$C_RESET"
}

ui_box_divider() {
    local border_color="$1" width="$2"
    printf '%s├%s┤%s\n' "$border_color" "$(ui_repeat '─' $((width - 2)))" "$C_RESET"
}

ui_box_bottom() {
    local border_color="$1" width="$2"
    printf '%s╰%s╯%s\n' "$border_color" "$(ui_repeat '─' $((width - 2)))" "$C_RESET"
}

ui_panel() {
    local title="$1" title_color="$2" border_color="$3"
    shift 3
    local term_w max_w=90
    term_w=$(ui_term_width)
    [ "$term_w" -lt "$max_w" ] && max_w=$term_w

    local wrapped_lines=() line wrapped
    for line in "$@"; do
        wrapped=$(ui_wrap_line "$line" $((max_w - 6)))
        while IFS= read -r w; do
            wrapped_lines+=("$w")
        done <<< "$wrapped"
    done

    local width
    width=$(ui_content_width "$title" "${wrapped_lines[@]}")

    ui_box_top "$title" "$title_color" "$border_color" "$width"
    ui_box_blank "$border_color" "$width"
    for line in "${wrapped_lines[@]}"; do
        ui_box_line "$border_color" "$width" "$line"
    done
    ui_box_blank "$border_color" "$width"
    ui_box_bottom "$border_color" "$width"
}

ui_tip() {
    local message="$1"
    local width
    width=$(ui_term_width)
    ui_box_top_plain "$C_GREEN" "$width"
    ui_box_line "$C_GREEN" "$width" "${C_GREEN}${C_BOLD}Tip:${C_RESET} ${message}"
    ui_box_bottom "$C_GREEN" "$width"
}

ui_status_table() {
    local title="$1" border_color="$2"
    shift 2
    local pair key val key_w=0

    for pair in "$@"; do
        key="${pair%%|*}"
        [ "${#key}" -gt "$key_w" ] && key_w=${#key}
    done
    [ "$key_w" -gt 24 ] && key_w=24

    local rendered_lines=() padded_key
    for pair in "$@"; do
        key="${pair%%|*}"
        val="${pair#*|}"
        padded_key=$(printf '%-*s' "$key_w" "$key")
        rendered_lines+=("${C_CYAN}${padded_key}${C_RESET}  ${C_WHITE}${val}${C_RESET}")
    done

    local width
    width=$(ui_content_width "$title" "${rendered_lines[@]}")

    ui_box_top "$title" "$C_CYAN" "$border_color" "$width"
    ui_box_blank "$border_color" "$width"
    local line
    for line in "${rendered_lines[@]}"; do
        ui_box_line "$border_color" "$width" "$line"
    done
    ui_box_blank "$border_color" "$width"
    ui_box_bottom "$border_color" "$width"
}

ui_section() {
    local title="$1"
    shift
    printf '%s│%s %s%s%s\n' "$C_ORANGE" "$C_RESET" "$C_BOLD" "$title" "$C_RESET"
    printf '%s%s%s\n' "$C_BLUE" "$(ui_repeat '─' 60)" "$C_RESET"
    printf '\n'
    local key_w=0 pair key
    for pair in "$@"; do
        key="${pair%%|*}"
        [ "${#key}" -gt "$key_w" ] && key_w=${#key}
    done
    local val padded_key
    for pair in "$@"; do
        key="${pair%%|*}"
        val="${pair#*|}"
        padded_key=$(printf '%-*s' "$key_w" "$key")
        printf '  %s%s%s : %b\n' "$C_CYAN" "$padded_key" "$C_RESET" "$val"
    done
    printf '\n'
}

ui_menu_list() {
    local title="$1"
    shift
    printf '%s│%s %s%s%s\n' "$C_ORANGE" "$C_RESET" "$C_BOLD" "$title" "$C_RESET"
    printf '%s%s%s\n' "$C_BLUE" "$(ui_repeat '─' 60)" "$C_RESET"
    printf '\n'
    local item
    for item in "$@"; do
        printf '  %b\n' "$item"
    done
    printf '\n'
}

ui_pick_from_list() {
    local prompt_label="$1"
    shift
    local items=("$@")
    local total="${#items[@]}"
    local page_size=20
    local page=0
    local last_page=$(( (total - 1) / page_size ))

    while true; do
        local start=$((page * page_size))
        local end=$((start + page_size))
        [ "$end" -gt "$total" ] && end="$total"

        printf '\n'
        local i
        for ((i = start; i < end; i++)); do
            printf '  %s%2d)%s %s\n' "$C_YELLOW" "$((i + 1))" "$C_RESET" "${items[$i]}"
        done

        if [ "$last_page" -gt 0 ]; then
            printf '\n  %s(page %d of %d)%s\n' "$C_DIM" "$((page + 1))" "$((last_page + 1))" "$C_RESET"
        fi

        local nav_hint=""
        [ "$page" -lt "$last_page" ] && nav_hint="${nav_hint}n) next page  "
        [ "$page" -gt 0 ] && nav_hint="${nav_hint}p) previous page  "

        printf '\n  %s❯%s %s (%sEnter to cancel): ' "$C_YELLOW" "$C_RESET" "$prompt_label" "$nav_hint"
        local pick
        read -r pick

        case "$pick" in
            "")
                UI_PICK_RESULT=""
                return 1
                ;;
            n|N)
                if [ "$page" -lt "$last_page" ]; then
                    page=$((page + 1))
                fi
                continue
                ;;
            p|P)
                if [ "$page" -gt 0 ]; then
                    page=$((page - 1))
                fi
                continue
                ;;
        esac

        if [[ ! "$pick" =~ ^[0-9]+$ ]] || [ "$pick" -lt 1 ] || [ "$pick" -gt "$total" ]; then
            ui_err "Invalid selection."
            continue
        fi

        UI_PICK_RESULT=$((pick - 1))
        return 0
    done
}

ui_info()    { printf '  %s●%s %s\n' "$C_BLUE"   "$C_RESET" "$*"; }
ui_ok()      { printf '  %s✓%s %s\n' "$C_GREEN"  "$C_RESET" "$*"; }
ui_warn()    { printf '  %s!%s %s\n' "$C_YELLOW" "$C_RESET" "$*"; }
ui_err()     { printf '  %s✗%s %s\n' "$C_RED"    "$C_RESET" "$*"; }
ui_action()  { printf '  %s→%s %s\n' "$C_CYAN"   "$C_RESET" "$*"; }

ui_rule() {
    local width
    width=$(ui_term_width)
    printf '%s%s%s\n' "$C_GRAY" "$(ui_repeat '─' "$width")" "$C_RESET"
}

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

get_installed_version() {
    local installed_version
    installed_version=$(env_get FAOXIMA_INSTALLED_VERSION 2>/dev/null)
    if [ -z "$installed_version" ] && [ -f "${PROJECT_DIR}/version" ]; then
        installed_version=$(tr -d '[:space:]' < "${PROJECT_DIR}/version")
    fi
    [ -n "$installed_version" ] && printf '%s' "$installed_version" || printf '%s' "$FAOXIMA_VERSION"
}

resolve_source_version() {
    local source="$1" arg1="$2" arg2="$3" code_dir="$4" resolved=""
    if [ "$source" = "github" ]; then
        if [[ "$arg1" == "-beta" ]] || [[ "$arg1" == "-v" && "$arg2" == "beta" ]]; then
            resolved="beta"
        elif [[ "$arg1" == "-v" && -n "$arg2" ]]; then
            resolved="$arg2"
        else
            resolved=$(get_latest_version)
        fi
    elif [ -f "${code_dir}/version" ]; then
        resolved=$(tr -d '[:space:]' < "${code_dir}/version")
    fi
    [[ -z "$resolved" || "$resolved" == "unknown" ]] && resolved="$FAOXIMA_VERSION"
    printf '%s' "$resolved"
}

version_is_newer() {
    local candidate="${1#v}" installed="${2#v}"
    [[ "$candidate" =~ ^[0-9]+([.][0-9]+)*$ ]] || return 1
    [[ "$installed" =~ ^[0-9]+([.][0-9]+)*$ ]] || return 1
    [ "$candidate" != "$installed" ] && [ "$(printf '%s\n%s\n' "$candidate" "$installed" | sort -V | tail -1)" = "$candidate" ]
}

show_animated_logo() {
    local latest_line="$1"
    clear
    printf '\n'
    printf '%s███████╗  █████╗   ██████╗  ██╗  ██╗ ██╗ ███╗   ███╗  █████╗ %s\n' "$C_GREEN" "$C_RESET"
    printf '%s██╔════╝ ██╔══██╗ ██╔═══██╗ ╚██╗██╔╝ ██║ ████╗ ████║ ██╔══██╗%s\n' "$C_GREEN" "$C_RESET"
    printf '%s█████╗   ███████║ ██║   ██║  ╚███╔╝  ██║ ██╔████╔██║ ███████║%s\n' "$C_WHITE" "$C_RESET"
    printf '%s██╔══╝   ██╔══██║ ██║   ██║  ██╔██╗  ██║ ██║╚██╔╝██║ ██╔══██║%s\n' "$C_WHITE" "$C_RESET"
    printf '%s██║      ██║  ██║ ╚██████╔╝ ██╔╝ ██╗ ██║ ██║ ╚═╝ ██║ ██║  ██║%s\n' "$C_RED" "$C_RESET"
    printf '%s╚═╝      ╚═╝  ╚═╝  ╚═════╝  ╚═╝  ╚═╝ ╚═╝ ╚═╝     ╚═╝ ╚═╝  ╚═╝%s\n' "$C_RED" "$C_RESET"
    printf '\n'

    local width=55
    local installed_version
    installed_version=$(get_installed_version)
    ui_box_top "Version" "$C_ORANGE$C_BOLD" "$C_ORANGE" "$width"
    ui_box_blank "$C_ORANGE" "$width"
    ui_box_line "$C_ORANGE" "$width" "${C_CYAN}Installed${C_RESET} : ${C_GREEN}${installed_version}${C_RESET}"
    [ -n "$latest_line" ] && ui_box_line "$C_ORANGE" "$width" "$latest_line"
    ui_box_line "$C_ORANGE" "$width" "${C_CYAN}GitHub${C_RESET}    : ${C_WHITE}${FAOXIMA_GITHUB}${C_RESET}"
    ui_box_line "$C_ORANGE" "$width" "${C_CYAN}Telegram${C_RESET}  : ${C_WHITE}${FAOXIMA_TELEGRAM}${C_RESET}"
    ui_box_bottom "$C_ORANGE" "$width"
    printf '\n'
}

show_logo() { show_animated_logo "$@"; }

dc() {
    local files=(-f "$COMPOSE_FILE") f
    for f in "${BOT_COMPOSE_PREFIX}"*.yml; do
        [ -f "$f" ] && files+=(-f "$f")
    done
    docker compose "${files[@]}" --env-file "$ENV_FILE" "$@"
}

normalize_domain() {
    local domain="${1:-}"
    domain=$(printf '%s' "$domain" | tr '[:upper:]' '[:lower:]')
    domain="${domain%.}"
    printf '%s' "$domain"
}

get_cert_enddate() {
    local domain
    domain=$(normalize_domain "$1")
    local project volume_name mountpoint cert_path
    project=$(env_get COMPOSE_PROJECT_NAME)
    [ -z "$project" ] && project="faoxima"
    volume_name="${project}_certs"

    mountpoint=$(docker volume inspect --format '{{.Mountpoint}}' "$volume_name" 2>/dev/null)
    if [ -n "$mountpoint" ]; then
        cert_path="${mountpoint}/live/${domain}/cert.pem"
        if [ -f "$cert_path" ]; then
            openssl x509 -enddate -noout -in "$cert_path" 2>/dev/null | cut -d= -f2 | tr -d '\r'
        fi
        return
    fi

    dc run --rm --no-deps --entrypoint sh certbot -c \
        "test -f /etc/letsencrypt/live/${domain}/cert.pem && openssl x509 -enddate -noout -in /etc/letsencrypt/live/${domain}/cert.pem" \
        2>/dev/null | cut -d= -f2 | tr -d '\r'
}

ensure_envsubst() {
    command -v envsubst >/dev/null 2>&1 && return 0
    local output
    if ! output=$(apt-get install -y gettext-base 2>&1); then
        ui_err "Failed to install gettext-base (needed for envsubst)."
        printf '%s\n' "$output"
        return 1
    fi
    if ! command -v envsubst >/dev/null 2>&1; then
        ui_err "gettext-base installed but the envsubst binary still isn't on PATH."
        return 1
    fi
}

render_vhost() {
    local domain outfile="$2" pma_allowed_ips ip
    domain=$(normalize_domain "$1")
    ensure_envsubst || { ui_err "envsubst is not available and could not be installed."; return 1; }
    mkdir -p "$(dirname "$outfile")" || { ui_err "Failed to create directory for ${outfile}."; return 1; }
    if [ ! -f "$NGINX_TEMPLATE" ]; then
        ui_err "Nginx template not found at ${NGINX_TEMPLATE}."
        return 1
    fi
    DOMAIN="$domain" \
        envsubst '${DOMAIN}' < "$NGINX_TEMPLATE" > "$outfile" \
        || { ui_err "Failed to render nginx config to ${outfile}."; return 1; }
    pma_allowed_ips=$(get_phpmyadmin_allowed_ips)
    if [ -n "$pma_allowed_ips" ]; then
        while IFS= read -r ip; do
            [ -z "$ip" ] && continue
            if ! valid_ipv4_or_cidr "$ip"; then
                ui_err "Invalid phpMyAdmin allowed IP in ${ENV_FILE}: ${ip}"
                return 1
            fi
            sed -i "/location \/phpmyadmin\//,/^[[:space:]]*}/ { /^[[:space:]]*deny all;/i\        allow ${ip};
            }" "$outfile" || { ui_err "Failed to apply phpMyAdmin IP allowlist to ${outfile}."; return 1; }
        done <<EOF
$(printf '%s' "$pma_allowed_ips" | tr ',' '\n')
EOF
    fi
}

render_bot_location() {
    local docroot="$1" upstream="$2" outfile="$3" urlpath="$4"
    ensure_envsubst || { ui_err "envsubst is not available and could not be installed."; return 1; }
    mkdir -p "$(dirname "$outfile")" || { ui_err "Failed to create directory for ${outfile}."; return 1; }
    if [ ! -f "$BOT_NGINX_TEMPLATE" ]; then
        ui_err "Bot nginx template not found at ${BOT_NGINX_TEMPLATE}."
        return 1
    fi
    DOC_ROOT="$docroot" APP_UPSTREAM="$upstream" URL_PATH="$urlpath" \
        envsubst '${DOC_ROOT} ${APP_UPSTREAM} ${URL_PATH}' < "$BOT_NGINX_TEMPLATE" > "$outfile" \
        || { ui_err "Failed to render bot nginx config to ${outfile}."; return 1; }
}

ensure_dummy_cert() {
    local domain
    domain=$(normalize_domain "$1")
    dc run --rm --no-deps --entrypoint sh certbot -c "
        set -e
        dir=/etc/letsencrypt/live/${domain}
        if [ -f \"\${dir}/fullchain.pem\" ]; then exit 0; fi
        mkdir -p \"\${dir}\"
        openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
            -keyout \"\${dir}/privkey.pem\" \
            -out \"\${dir}/fullchain.pem\" \
            -subj \"/CN=${domain}\"
    "
}

discard_dummy_cert() {
    local domain
    domain=$(normalize_domain "$1")
    dc run --rm --no-deps --entrypoint sh certbot -c "
        set -e
        renewal_conf=/etc/letsencrypt/renewal/${domain}.conf
        if [ -f \"\${renewal_conf}\" ]; then exit 0; fi
        rm -rf \"/etc/letsencrypt/live/${domain}\" \"/etc/letsencrypt/archive/${domain}\"
    " || { ui_err "Failed to remove the temporary self-signed certificate for ${domain}."; return 1; }
}

issue_certificate() {
    local domain
    domain=$(normalize_domain "$1")
    if dc run --rm --entrypoint certbot certbot certonly --webroot -w /var/www/certbot --agree-tos --non-interactive \
            -m "admin@${domain}" -d "$domain"; then
        dc exec nginx nginx -s reload 2>/dev/null || true
        return 0
    fi

    ui_warn "Certificate issuance via webroot failed for ${domain} — port 80 may be blocked by something other than our own nginx. Retrying by stopping nginx and binding port 80 directly..."
    dc stop nginx || { ui_err "Failed to stop nginx for the standalone issuance attempt."; return 1; }

    if dc run --rm -p 80:80 --entrypoint certbot certbot certonly --standalone --agree-tos --non-interactive \
            -m "admin@${domain}" -d "$domain"; then
        dc start nginx && dc exec nginx nginx -s reload 2>/dev/null
        return 0
    fi

    dc start nginx || ui_err "nginx failed to restart after the failed standalone attempt — start it manually with 'docker compose start nginx'."
    return 1
}

env_get() {
    local key="$1"
    [ -f "$ENV_FILE" ] || return 1
    grep -E "^${key}=" "$ENV_FILE" | tail -1 | cut -d'=' -f2-
}

env_set() {
    local key="$1" value="$2"
    [ "$key" = "DOMAIN" ] && value=$(normalize_domain "$value")
    if [ ! -f "$ENV_FILE" ]; then
        touch "$ENV_FILE" || { ui_err "Failed to create ${ENV_FILE}."; exit 1; }
    fi
    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE" || { ui_err "Failed to update ${key} in ${ENV_FILE}."; exit 1; }
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE" || { ui_err "Failed to append ${key} to ${ENV_FILE}."; exit 1; }
    fi
}

cache_get() {
    local key="$1" max_age="$2"
    local file="${CACHE_DIR}/${key}"
    [ -f "$file" ] || return 1
    local age
    age=$(( $(date +%s) - $(stat -c %Y "$file" 2>/dev/null || echo 0) ))
    [ "$age" -gt "$max_age" ] && return 1
    cat "$file"
}

cache_set() {
    local key="$1" value="$2"
    mkdir -p "$CACHE_DIR" 2>/dev/null || return 1
    printf '%s' "$value" > "${CACHE_DIR}/${key}"
}

docker_installed() {
    command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1
}

install_docker() {
    if docker_installed; then
        ui_ok "Docker Engine + Compose v2 already installed."
        return 0
    fi

    ui_action "Docker not found (or Compose v2 plugin missing) — installing via Docker's official apt repo..."

    apt-get update || { ui_err "apt-get update failed."; exit 1; }
    apt-get install -y ca-certificates curl gnupg || { ui_err "Failed to install apt prerequisites."; exit 1; }

    install -m 0755 -d /etc/apt/keyrings || { ui_err "Failed to create /etc/apt/keyrings."; exit 1; }
    if [ ! -f /etc/apt/keyrings/docker.gpg ]; then
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg || {
            ui_err "Failed to fetch Docker's GPG key."
            exit 1
        }
        chmod a+r /etc/apt/keyrings/docker.gpg
    fi

    local arch codename
    arch="$(dpkg --print-architecture)"
    codename="$(. /etc/os-release && echo "${VERSION_CODENAME:-jammy}")"
    echo "deb [arch=${arch} signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${codename} stable" \
        | tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update || { ui_err "apt-get update failed after adding Docker's repo."; exit 1; }
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin || {
        ui_err "Failed to install Docker Engine + Compose v2 plugin."
        exit 1
    }

    systemctl enable --now docker || { ui_err "Failed to enable/start the Docker service."; exit 1; }

    if ! docker_installed; then
        ui_err "Docker installation finished but 'docker compose version' still fails."
        exit 1
    fi
    ui_ok "Docker Engine + Compose v2 installed and running."
}

check_ssl_status() {
    if [ ! -f "$ENV_FILE" ]; then
        ui_warn ".env not found — SSL status unknown."
        return 0
    fi
    local domain
    domain=$(normalize_domain "$(env_get DOMAIN)")
    if [ -z "$domain" ]; then
        ui_warn "DOMAIN could not be read from .env."
        return 0
    fi
    local expiry_date expiry_ts current_date days_remaining
    expiry_date=$(get_cert_enddate "$domain")
    if [ -z "$expiry_date" ]; then
        ui_warn "SSL certificate not found or could not be read for domain ${domain}."
        return 0
    fi
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
    if [ -f "$ENV_FILE" ] && [ -f "$COMPOSE_FILE" ]; then
        ui_ok "Faoxima Bot is installed at ${PROJECT_DIR}"
        check_ssl_status
    else
        ui_err "Faoxima Bot is not installed"
    fi
}

wait_for_healthy() {
    local service="$1" timeout="${2:-180}" interval=3 waited=0 cid status
    while [ "$waited" -lt "$timeout" ]; do
        cid=$(dc ps -q "$service" 2>/dev/null)
        if [ -n "$cid" ]; then
            status=$(docker inspect --format='{{.State.Health.Status}}' "$cid" 2>/dev/null)
            [ "$status" = "healthy" ] && return 0
        fi
        sleep "$interval"
        waited=$((waited + interval))
    done
    return 1
}

db_ready_check() {
    local service="${1:-app}" db_host="$2" db_name="$3" db_user="$4" db_pass="$5"
    [ -z "$db_host" ] && { db_host=$(env_get DB_HOST); db_host="${db_host:-db}"; }
    [ -z "$db_name" ] && db_name=$(env_get MYSQL_DATABASE)
    [ -z "$db_user" ] && db_user=$(env_get MYSQL_USER)
    [ -z "$db_pass" ] && db_pass=$(env_get MYSQL_PASSWORD)
    dc exec -T "$service" php -r '
        $h = $argv[1]; $d = $argv[2]; $u = $argv[3]; $p = $argv[4];
        try {
            $pdo = new PDO("mysql:host={$h};dbname={$d};charset=utf8mb4", $u, $p, [PDO::ATTR_TIMEOUT => 3]);
            $pdo->query("SELECT 1");
            echo "OK\n";
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(1);
        }
    ' "$db_host" "$db_name" "$db_user" "$db_pass"
}

wait_for_db_ready() {
    local timeout="${1:-90}" service="${2:-app}" db_host="$3" db_name="$4" db_user="$5" db_pass="$6"
    local interval=3 waited=0
    while [ "$waited" -lt "$timeout" ]; do
        if db_ready_check "$service" "$db_host" "$db_name" "$db_user" "$db_pass" >/dev/null 2>&1; then
            return 0
        fi
        sleep "$interval"
        waited=$((waited + interval))
    done
    return 1
}

wait_for_config_templated() {
    local timeout="${1:-60}" service="${2:-app}"
    local interval=1 waited=0
    while [ "$waited" -lt "$timeout" ]; do
        if dc exec -T "$service" php -r '
            chdir("/var/www/faoxima");
            require "config.php";
            exit(($GLOBALS["pdo"] ?? null) instanceof PDO ? 0 : 1);
        ' >/dev/null 2>&1; then
            return 0
        fi
        sleep "$interval"
        waited=$((waited + interval))
    done
    return 1
}

repair_db_user() {
    local db_name db_user db_pass root_pass output
    db_name=$(env_get MYSQL_DATABASE)
    db_user=$(env_get MYSQL_USER)
    db_pass=$(env_get MYSQL_PASSWORD)
    root_pass=$(env_get MYSQL_ROOT_PASSWORD)
    [ -z "$root_pass" ] && return 1

    output=$(dc exec -T db mysql -uroot -p"${root_pass}" -e \
        "CREATE DATABASE IF NOT EXISTS \`${db_name}\`; \
         CREATE USER IF NOT EXISTS '${db_user}'@'%' IDENTIFIED WITH mysql_native_password BY '${db_pass}'; \
         ALTER USER '${db_user}'@'%' IDENTIFIED WITH mysql_native_password BY '${db_pass}'; \
         GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'%'; \
         FLUSH PRIVILEGES;" 2>&1)
    local status=$?
    printf '%s\n' "$output"
    if [ "$status" -ne 0 ] && printf '%s' "$output" | grep -q "Access denied for user 'root'"; then
        return 2
    fi
    return "$status"
}

reset_db_volume() {
    ui_warn "The 'db_data' volume appears to be from an earlier install with different credentials (root login itself is being rejected) — resetting it..."
    dc rm -f -s db || { ui_err "Failed to stop/remove the 'db' container."; return 1; }
    local project volume_name
    project=$(env_get COMPOSE_PROJECT_NAME)
    [ -z "$project" ] && project="faoxima"
    volume_name="${project}_db_data"
    docker volume rm "$volume_name" >/dev/null 2>&1 || { ui_err "Failed to remove the 'db_data' volume (${volume_name})."; return 1; }
    dc up -d db || { ui_err "Failed to recreate the 'db' service after resetting its volume."; return 1; }
    wait_for_healthy db 180 || { ui_err "Database did not become healthy after the volume reset."; return 1; }
}

diagnose_ssl_failure() {
    local domain
    domain=$(normalize_domain "$1")
    ui_warn "Diagnosing why the SSL certificate could not be issued for ${domain}..."
    printf '\n'

    ui_rule
    printf '  %s● Writing a test challenge file into the shared webroot%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    dc run --rm --entrypoint sh certbot -c \
        "mkdir -p /var/www/certbot/.well-known/acme-challenge && echo diagnostic-ok > /var/www/certbot/.well-known/acme-challenge/diagnostic-test" 2>&1

    printf '\n'
    ui_rule
    printf '  %s● Testing INTERNALLY (inside the nginx container — bypasses DNS/firewall/internet)%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    dc exec -T nginx sh -c "wget -qO- http://127.0.0.1/.well-known/acme-challenge/diagnostic-test 2>&1 || echo 'INTERNAL REQUEST FAILED — nginx is not serving the webroot correctly.'"

    printf '\n'
    ui_rule
    printf '  %s● Testing EXTERNALLY (from this host, via the public domain over port 80)%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    curl -s -o /dev/null -w 'HTTP status: %{http_code}\n' --max-time 10 "http://${domain}/.well-known/acme-challenge/diagnostic-test" \
        || echo "EXTERNAL REQUEST FAILED (timeout or connection error) — check DNS/firewall/port forwarding."

    printf '\n'
    ui_rule
    printf '  %s● DNS resolution for %s%s\n' "$C_CYAN" "$domain" "$C_RESET"
    ui_rule
    getent hosts "$domain" 2>/dev/null || host "$domain" 2>/dev/null || nslookup "$domain" 2>/dev/null || echo "Could not resolve DNS for ${domain} — check the domain's A record."

    printf '\n'
    ui_rule
    printf '  %s● What is actually bound to host port 80 right now%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    local occupant kind ident name project
    occupant=$(describe_port_occupant 80)
    IFS='|' read -r kind ident name project <<< "$occupant"
    case "$kind" in
        docker)
            printf 'Docker container: %s (project: %s, id: %s)\n' "$name" "$project" "${ident:0:12}"
            ;;
        host)
            printf 'Host process (NOT Docker): %s (pid %s)\n' "$name" "$ident"
            printf '%sThis is very likely why external requests never reach nginx — some other web server on this host is intercepting port 80 before Docker gets a chance.%s\n' "$C_YELLOW" "$C_RESET"
            ;;
        *)
            printf 'Could not identify what is bound to port 80.\n'
            ;;
    esac

    printf '\n'
    ui_rule
    printf '  %s● nginx container (last 200 lines)%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    dc logs --tail=200 nginx 2>/dev/null
    printf '\n'
}

diagnose_db_failure() {
    local service="${1:-app}" db_host="$2" db_name="$3" db_user="$4" db_pass="$5"
    ui_warn "Diagnosing why ${service} couldn't reach the database..."
    printf '\n'
    ui_rule
    printf '  %s● Connection attempt (%s → db)%s\n' "$C_CYAN" "$service" "$C_RESET"
    ui_rule
    db_ready_check "$service" "$db_host" "$db_name" "$db_user" "$db_pass"
    printf '\n'
    ui_rule
    printf '  %s● db container (last 200 lines)%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    dc logs --tail=200 db 2>/dev/null
    printf '\n'
    ui_rule
    printf '  %s● %s container (last 200 lines)%s\n' "$C_CYAN" "$service" "$C_RESET"
    ui_rule
    dc logs --tail=200 "$service" 2>/dev/null
    printf '\n'
}

verify_tables_created() {
    local db_name="$1" db_user="$2" db_pass="$3"
    [ -z "$db_name" ] && db_name=$(env_get MYSQL_DATABASE)
    [ -z "$db_user" ] && db_user=$(env_get MYSQL_USER)
    [ -z "$db_pass" ] && db_pass=$(env_get MYSQL_PASSWORD)

    local required_tables=(
        user setting admin channels marzban_panel product invoice Payment_report
        textbot shopSetting support_message crypto_wallets processed_updates cron_runtime_state
    )
    local required_columns=(
        "user:nav_state"
        "user:card_verify_bypass"
        "setting:redis_enabled"
        "setting:banner_start_status"
        "invoice:invalidated_at"
        "Payment_report:tetrapay_token"
        "marzban_panel:xui_api_mode"
        "marzban_panel:ip_limit_guard"
        "product:ip_limit"
        "support_message:seen_by_admin"
        "crypto_wallets:verification_mode"
    )

    local failed=0 table item column exists charset_issues mysql_error

    for table in "${required_tables[@]}"; do
        mysql_error=$(mktemp)
        exists=$(dc exec -T db mysql -u"$db_user" -p"$db_pass" "$db_name" -Nse             "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME='${table}';"             2>"$mysql_error")
        if [ $? -ne 0 ]; then
            ui_err "Database schema verification query failed."
            cat "$mysql_error"
            rm -f "$mysql_error"
            return 1
        fi
        rm -f "$mysql_error"
        if [ "$exists" != "1" ]; then
            ui_err "Missing database table: ${table}"
            failed=1
        fi
    done

    for item in "${required_columns[@]}"; do
        table="${item%%:*}"
        column="${item#*:}"
        mysql_error=$(mktemp)
        exists=$(dc exec -T db mysql -u"$db_user" -p"$db_pass" "$db_name" -Nse             "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='${table}' AND COLUMN_NAME='${column}';"             2>"$mysql_error")
        if [ $? -ne 0 ]; then
            ui_err "Database column verification query failed for ${table}.${column}."
            cat "$mysql_error"
            rm -f "$mysql_error"
            return 1
        fi
        rm -f "$mysql_error"
        if [ "$exists" != "1" ]; then
            ui_err "Missing database column: ${table}.${column}"
            failed=1
        fi
    done

    mysql_error=$(mktemp)
    charset_issues=$(dc exec -T db mysql -u"$db_user" -p"$db_pass" "$db_name" -Nse         "SELECT CONCAT(TABLE_NAME,'.',COLUMN_NAME,' = ',CHARACTER_SET_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME<>'utf8mb4' ORDER BY TABLE_NAME,ORDINAL_POSITION;"         2>"$mysql_error")
    if [ $? -ne 0 ]; then
        ui_err "Database utf8mb4 verification query failed."
        cat "$mysql_error"
        rm -f "$mysql_error"
        return 1
    fi
    rm -f "$mysql_error"

    if [ -n "$charset_issues" ]; then
        ui_err "Database columns still not using utf8mb4:"
        while IFS= read -r item; do
            [ -n "$item" ] && printf '    %s\n' "$item"
        done <<< "$charset_issues"
        failed=1
    fi

    if [ "$failed" -ne 0 ]; then
        return 1
    fi

    return 0
}

run_table_migrations_until_ready() {
    local service="${1:-app}" db_name="$2" db_user="$3" db_pass="$4" code_dir="${5:-$PROJECT_DIR}" label="${6:-database}"
    local attempt max_attempts=3

    for attempt in $(seq 1 "$max_attempts"); do
        if ! dc exec -T "$service" php table.php >/dev/null 2>&1; then
            ui_err "table.php failed while creating or updating the schema for ${label}."
            diagnose_table_failure "$service" "$code_dir"
            return 1
        fi

        if verify_tables_created "$db_name" "$db_user" "$db_pass"; then
            return 0
        fi

        if [ "$attempt" -lt "$max_attempts" ]; then
            ui_warn "Database schema is not complete after migration pass ${attempt}/${max_attempts}; retrying table.php to finish dependent migrations..."
            sleep 2
        fi
    done

    ui_err "table.php completed ${max_attempts} migration passes but the database schema is still incomplete for ${label}."
    diagnose_table_failure "$service" "$code_dir"
    return 1
}

diagnose_table_failure() {
    local service="${1:-app}" code_dir="${2:-$PROJECT_DIR}"
    ui_warn "Diagnosing why table.php did not create the database schema..."
    printf '\n'
    ui_rule
    printf '  %s● Re-running table.php directly (full output, nothing suppressed)%s\n' "$C_CYAN" "$C_RESET"
    ui_rule
    dc exec -T "$service" php table.php
    printf '\n'
    ui_rule
    printf '  %s● %s/error_log (last 200 lines, if present)%s\n' "$C_CYAN" "$code_dir" "$C_RESET"
    ui_rule
    if [ -f "${code_dir}/error_log" ]; then
        tail -n 200 "${code_dir}/error_log"
    else
        printf 'No error_log file found at %s/error_log.\n' "$code_dir"
    fi
    printf '\n'
    ui_rule
    printf '  %s● %s/logs/runtime.log (last 200 lines, if present)%s\n' "$C_CYAN" "$code_dir" "$C_RESET"
    ui_rule
    if [ -f "${code_dir}/logs/runtime.log" ]; then
        tail -n 200 "${code_dir}/logs/runtime.log"
    else
        printf 'No runtime.log file found at %s/logs/runtime.log.\n' "$code_dir"
    fi
    printf '\n'
    ui_rule
    printf '  %s● %s/logs/php-error.log (last 200 lines, if present)%s\n' "$C_CYAN" "$code_dir" "$C_RESET"
    ui_rule
    if [ -f "${code_dir}/logs/php-error.log" ]; then
        tail -n 200 "${code_dir}/logs/php-error.log"
    else
        printf 'No php-error.log file found at %s/logs/php-error.log.\n' "$code_dir"
    fi
    printf '\n'
    ui_rule
    printf '  %s● %s container (last 200 lines)%s\n' "$C_CYAN" "$service" "$C_RESET"
    ui_rule
    dc logs --tail=200 "$service" 2>/dev/null
    printf '\n'
}

port_is_free() {
    local port="$1"
    ! ss -tuln 2>/dev/null | grep -q ":${port} "
}

ensure_firewall_ports_open() {
    local ports=("$@")
    local port

    if command -v ufw >/dev/null 2>&1; then
        if ufw status 2>/dev/null | grep -qi '^Status: active'; then
            ui_action "ufw is active — opening ${ports[*]}/tcp..."
            for port in "${ports[@]}"; do
                ufw allow "${port}/tcp" >/dev/null 2>&1 \
                    || ui_warn "Failed to add a ufw rule for port ${port} — you may need to open it manually."
            done
            ui_ok "ufw rules ensured for: ${ports[*]}/tcp."
        fi
    fi

    if command -v firewall-cmd >/dev/null 2>&1; then
        if firewall-cmd --state >/dev/null 2>&1; then
            ui_action "firewalld is active — opening ${ports[*]}/tcp..."
            for port in "${ports[@]}"; do
                firewall-cmd --permanent --add-port="${port}/tcp" >/dev/null 2>&1 \
                    || ui_warn "Failed to add a firewalld rule for port ${port} — you may need to open it manually."
            done
            firewall-cmd --reload >/dev/null 2>&1 \
                || ui_warn "Failed to reload firewalld — the new rules may not be active yet."
            ui_ok "firewalld rules ensured for: ${ports[*]}/tcp."
        fi
    fi
}

describe_port_occupant() {
    local port="$1" cid cname cproject line pid pname

    cid=$(docker ps --format '{{.ID}}\t{{.Ports}}' 2>/dev/null | grep -E "0\.0\.0\.0:${port}->|:::${port}->" | cut -f1 | head -1)
    if [ -n "$cid" ]; then
        cname=$(docker inspect --format='{{.Name}}' "$cid" 2>/dev/null | sed 's#^/##')
        cproject=$(docker inspect --format='{{ index .Config.Labels "com.docker.compose.project" }}' "$cid" 2>/dev/null)
        printf 'docker|%s|%s|%s' "$cid" "${cname:-unknown}" "${cproject:-unknown}"
        return 0
    fi

    line=$(ss -tulnp 2>/dev/null | grep -E ":${port} " | head -1)
    pid=$(printf '%s' "$line" | grep -oE 'pid=[0-9]+' | head -1 | cut -d= -f2)
    pname=$(printf '%s' "$line" | grep -oE 'users:\(\("[^"]+"' | head -1 | sed -E 's/users:\(\("//; s/"//')
    if [ -n "$pid" ] || [ -n "$pname" ]; then
        printf 'host|%s|%s|' "${pid:-?}" "${pname:-unknown process}"
        return 0
    fi

    printf 'unknown|||'
    return 1
}

grant_file_permissions() {
    local service="${1:-app}"
    local cid
    cid=$(dc ps -q "$service" 2>/dev/null)
    if [ -z "$cid" ]; then
        ui_err "${service} container is not running — cannot apply in-container permissions."
        return 1
    fi

    local wait_tries=0
    while [ "$wait_tries" -lt 15 ]; do
        local c_status
        c_status=$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null)
        [ "$c_status" = "running" ] && break
        sleep 1
        wait_tries=$((wait_tries + 1))
    done
    if [ "$c_status" != "running" ]; then
        ui_err "${service} container did not reach the running state."
        return 1
    fi

    ui_action "Re-applying file permissions inside the ${service} container..."
    local output
    if output=$(dc exec -T "$service" sh -c '
        set -e
        app_dir=/var/www/faoxima
        chown -R www-data:www-data "$app_dir" 2>/dev/null || true
        find "$app_dir" -path "$app_dir/installer" -prune -o -type d -exec chmod 775 {} \; 2>/dev/null || true
        find "$app_dir" -path "$app_dir/installer" -prune -o -type f -exec chmod 664 {} \; 2>/dev/null || true
        if [ -d "$app_dir/installer" ]; then
            chmod 555 "$app_dir/installer"
            find "$app_dir/installer" -type f -exec chmod 444 {} \;
        fi
        if [ -f "$app_dir/config.php" ]; then
            chown www-data:www-data "$app_dir/config.php"
            chmod 600 "$app_dir/config.php"
        fi
        if [ -f "$app_dir/.env" ]; then
            chmod 600 "$app_dir/.env"
        fi
        if [ -d "$app_dir/storage/private" ]; then
            chmod 700 "$app_dir/storage/private"
            find "$app_dir/storage/private" -type f -exec chmod 600 {} \;
        fi
    ' 2>&1); then
        ui_ok "File permissions re-applied."
    else
        ui_err "Failed to re-apply file permissions inside the ${service} container."
        printf '%s\n' "$output"
        return 1
    fi
}

prompt_version_selection() {
    local tags=() prereleases=() release_json latest_tag current_tag line
    release_json=$(curl -fsSL "https://api.github.com/repos/${FAOXIMA_REPO}/releases?per_page=100" 2>/dev/null)
    latest_tag=$(curl -fsSL "https://api.github.com/repos/${FAOXIMA_REPO}/releases/latest" 2>/dev/null \
        | grep -m1 '"tag_name"' | cut -d'"' -f4)

    while IFS= read -r line; do
        if [[ "$line" == *'"tag_name"'* ]]; then
            current_tag=$(printf '%s' "$line" | cut -d'"' -f4)
        elif [[ -n "$current_tag" && "$line" == *'"prerelease"'* ]]; then
            tags+=("$current_tag")
            if [[ "$line" == *true* ]]; then
                prereleases+=("1")
            else
                prereleases+=("0")
                [ -z "$latest_tag" ] && latest_tag="$current_tag"
            fi
            current_tag=""
        fi
    done <<< "$release_json"

    if [ "${#tags[@]}" -eq 0 ]; then
        ui_err "Could not fetch the release list from GitHub." >&2
        return 1
    fi

    local visible_count="${#tags[@]}"
    [ "$visible_count" -gt 5 ] && visible_count=5
    local custom_option=0 beta_option

    {
        printf '\n'
        local i
        for ((i = 0; i < visible_count; i++)); do
            if [ "${tags[$i]}" = "$latest_tag" ]; then
                printf '  %s%2d)%s %s %s(latest)%s\n' "$C_YELLOW" "$((i + 1))" "$C_RESET" "${tags[$i]}" "$C_GREEN" "$C_RESET"
            elif [ "${prereleases[$i]}" = "1" ]; then
                printf '  %s%2d)%s %s %s(pre-release)%s\n' "$C_YELLOW" "$((i + 1))" "$C_RESET" "${tags[$i]}" "$C_YELLOW" "$C_RESET"
            else
                printf '  %s%2d)%s %s\n' "$C_YELLOW" "$((i + 1))" "$C_RESET" "${tags[$i]}"
            fi
        done
        if [ "${#tags[@]}" -gt "$visible_count" ]; then
            custom_option=$((visible_count + 1))
            printf '  %s%2d)%s Custom Version\n' "$C_YELLOW" "$custom_option" "$C_RESET"
        fi
        beta_option=$((visible_count + 1))
        [ "$custom_option" -gt 0 ] && beta_option=$((custom_option + 1))
        printf '  %s%2d)%s Beta (latest main branch, no official release)\n' "$C_YELLOW" "$beta_option" "$C_RESET"
    } >&2

    local pick max_option="$beta_option"
    printf '\n  %s❯%s Select a version [1-%d]: ' "$C_YELLOW" "$C_RESET" "$max_option" >&2
    read -r pick

    if [[ ! "$pick" =~ ^[0-9]+$ ]] || [ "$pick" -lt 1 ] || [ "$pick" -gt "$max_option" ]; then
        ui_err "Invalid selection." >&2
        return 1
    fi

    if [ "$pick" -eq "$beta_option" ]; then
        printf 'beta'
        return 0
    fi

    if [ "$custom_option" -gt 0 ] && [ "$pick" -eq "$custom_option" ]; then
        local custom_tag found=0 i
        printf '  %s❯%s Enter exact release version/tag: ' "$C_YELLOW" "$C_RESET" >&2
        read -r custom_tag
        [ -n "$custom_tag" ] || { ui_err "Version cannot be empty." >&2; return 1; }
        for ((i = 0; i < ${#tags[@]}; i++)); do
            if [ "${tags[$i]}" = "$custom_tag" ]; then
                found=1
                break
            fi
        done
        if [ "$found" -ne 1 ]; then
            ui_err "Release '${custom_tag}' was not found in the GitHub release list." >&2
            return 1
        fi
        printf '%s' "$custom_tag"
        return 0
    fi

    printf '%s' "${tags[$((pick - 1))]}"
}

resolve_zip_url() {
    local arg1="$1" arg2="$2" zip_url
    if [[ "$arg1" == "-v" && "$arg2" == "beta" ]] || [[ "$arg1" == "-beta" ]] || [[ "$arg1" == "-" && "$arg2" == "beta" ]]; then
        zip_url="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    elif [[ "$arg1" == "-v" && -n "$arg2" ]]; then
        zip_url="${FAOXIMA_GITHUB}/archive/refs/tags/${arg2}.zip"
    else
        zip_url=$(curl -s "https://api.github.com/repos/${FAOXIMA_REPO}/releases/latest" | grep "zipball_url" | cut -d '"' -f 4)
        [ -z "$zip_url" ] && zip_url="${FAOXIMA_GITHUB}/archive/refs/heads/main.zip"
    fi
    printf '%s' "$zip_url"
}

install_bot() {
    show_logo
    ui_panel "INSTALLATION — DOCKER STACK" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Installing nginx + php-fpm + MySQL as a Docker Compose stack.${C_RESET}" \
        "${C_DIM}The stack will be deployed from ${PROJECT_DIR}${C_RESET}"

    install_docker

    local install_source version_arg1="${1:-}" version_arg2="${2:-}"
    install_source=$(env_get INSTALL_SOURCE)
    [ -z "$install_source" ] && install_source="manual"

    if [ "$STAGING_SOURCE_DIR" != "$PROJECT_DIR" ] && [ -f "${STAGING_SOURCE_DIR}/docker-compose.yml" ] && [ ! -f "$COMPOSE_FILE" ]; then
        ui_action "Relocating Faoxima source from ${STAGING_SOURCE_DIR} to ${PROJECT_DIR}..."
        mkdir -p "$PROJECT_DIR" || { ui_err "Failed to create project directory ${PROJECT_DIR}."; exit 1; }
        cp -a "${STAGING_SOURCE_DIR}/." "${PROJECT_DIR}/" || { ui_err "Failed to copy Faoxima source into ${PROJECT_DIR}."; exit 1; }
        cd "$PROJECT_DIR" || { ui_err "Failed to switch into ${PROJECT_DIR}."; exit 1; }
        rm -rf "$STAGING_SOURCE_DIR" || ui_warn "Failed to remove the staging directory ${STAGING_SOURCE_DIR} — you can delete it manually."
        ui_ok "Faoxima source relocated to ${PROJECT_DIR}."
    fi

    if [ ! -f "$COMPOSE_FILE" ]; then
        install_source="github"
        if [ -z "$version_arg1" ]; then
            local picked_version
            picked_version=$(prompt_version_selection)
            if [ -n "$picked_version" ]; then
                if [ "$picked_version" = "beta" ]; then
                    version_arg1="-beta"
                else
                    version_arg1="-v"
                    version_arg2="$picked_version"
                fi
            fi
        fi

        ui_action "Downloading Faoxima source..."
        local zip_url
        zip_url=$(resolve_zip_url "$version_arg1" "$version_arg2")

        mkdir -p "$TMP_DOWNLOAD" || { ui_err "Failed to create temporary directory ${TMP_DOWNLOAD}."; exit 1; }
        wget -O "${TMP_DOWNLOAD}/bot.zip" "$zip_url" || { ui_err "Failed to download Faoxima from ${zip_url}."; exit 1; }
        unzip -q "${TMP_DOWNLOAD}/bot.zip" -d "$TMP_DOWNLOAD" || { ui_err "Failed to extract the downloaded archive."; exit 1; }

        local extracted_dir
        extracted_dir=$(find "$TMP_DOWNLOAD" -mindepth 1 -maxdepth 1 -type d | head -1)
        [ -n "$extracted_dir" ] || { ui_err "Could not locate the extracted Faoxima directory."; exit 1; }

        mkdir -p "$PROJECT_DIR" || { ui_err "Failed to create project directory ${PROJECT_DIR}."; exit 1; }
        cp -a "${extracted_dir}/." "${PROJECT_DIR}/" || { ui_err "Failed to copy Faoxima source into ${PROJECT_DIR}."; exit 1; }
        rm -rf "$TMP_DOWNLOAD"
        ui_ok "Faoxima source downloaded to ${PROJECT_DIR}."
    fi

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "docker-compose.yml still not found at ${COMPOSE_FILE} after download — the release archive may be malformed."
        exit 1
    fi

    if [ -f "$ENV_FILE" ]; then
        ui_warn ".env already exists at ${ENV_FILE} — a stack may already be installed."
        local overwrite
        printf '  %s❯%s Overwrite and reinstall? (y/n): ' "$C_YELLOW" "$C_RESET"
        read -r overwrite
        if [[ "$overwrite" != "y" && "$overwrite" != "Y" ]]; then
            ui_info "Installation aborted by user."
            return 0
        fi
    fi

    printf '\n'
    ui_panel "BOT CONFIGURATION" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Now we'll wire up your domain and Telegram bot credentials.${C_RESET}" \
        "${C_DIM}Get the bot token from @BotFather and your numeric chat ID from @userinfobot.${C_RESET}"

    local domainname
    printf '\n  %s❯%s Enter the domain (e.g. example.com): ' "$C_YELLOW" "$C_RESET"
    read -r domainname
    while [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        ui_err "Invalid domain format. Please try again."
        printf '  %s❯%s Enter the domain: ' "$C_YELLOW" "$C_RESET"
        read -r domainname
    done
    local entered_domain="$domainname"
    domainname=$(normalize_domain "$domainname")
    if [ "$entered_domain" != "$domainname" ]; then
        ui_info "Domain normalized to lowercase: ${domainname}"
    fi

    local YOUR_BOT_TOKEN
    printf '  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]+:[a-zA-Z0-9_-]+$ ]]; do
        ui_err "Invalid bot token format. Please try again."
        printf '  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_BOT_TOKEN
    done

    local YOUR_CHAT_ID
    printf '  %s❯%s Admin Telegram ID (numeric): ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_CHAT_ID
    while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
        ui_err "Invalid chat ID format. Please try again."
        printf '  %s❯%s Admin Telegram ID (numeric): ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_CHAT_ID
    done

    ensure_firewall_ports_open 80 443

    local http_port=80 https_port=443
    local port
    for port in 80 443; do
        port_is_free "$port" && continue

        local occupant kind ident name project
        occupant=$(describe_port_occupant "$port")
        IFS='|' read -r kind ident name project <<< "$occupant"

        ui_warn "Port ${port} is already in use."
        case "$kind" in
            docker)
                ui_warn "It's held by a Docker container: ${name} (image project: ${project}, id: ${ident:0:12})."
                printf '  %s❯%s Stop this container now to free the port? (y/N): ' "$C_YELLOW" "$C_RESET"
                local stopit; read -r stopit
                if [[ "${stopit,,}" == "y" ]]; then
                    docker stop "$ident" >/dev/null 2>&1 && ui_ok "Stopped ${name}." || ui_warn "Could not stop ${name} — you may need to do it manually."
                fi
                ;;
            host)
                ui_warn "It's held by a host process: ${name} (pid ${ident})."
                printf '  %s❯%s Stop it now with '"'"'systemctl stop %s'"'"'? (y/N): ' "$C_YELLOW" "$C_RESET" "$name"
                local stopit; read -r stopit
                if [[ "${stopit,,}" == "y" ]]; then
                    systemctl stop "$name" >/dev/null 2>&1 && ui_ok "Stopped ${name}." || ui_warn "Could not stop ${name} — you may need to do it manually."
                fi
                ;;
            *)
                ui_warn "Could not identify what's using port ${port}."
                ;;
        esac
    done

    if ! port_is_free 80 || ! port_is_free 443; then
        ui_err "Port 80 and/or 443 is still in use — Faoxima requires both to be free (nginx serves the bot on 443, and Let's Encrypt needs port 80 reachable from the internet for certificate issuance)."
        ui_err "Free both ports (stop whatever is using them) and re-run install."
        exit 1
    fi
    ui_ok "Ports 80 and 443 are free."

    [ -f "$ENV_FILE" ] || cp "$ENV_EXAMPLE" "$ENV_FILE"
    local mysql_root_pass mysql_pass
    mysql_root_pass=$(env_get MYSQL_ROOT_PASSWORD)
    [[ -z "$mysql_root_pass" || "$mysql_root_pass" == "change_me_root_password" ]] && mysql_root_pass=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9')
    mysql_pass=$(env_get MYSQL_PASSWORD)
    [[ -z "$mysql_pass" || "$mysql_pass" == "change_me_db_password" ]] && mysql_pass=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9')

    env_set "DOMAIN" "$domainname"
    env_set "URL_PATH" "faoxima"
    env_set "HTTP_PORT" "$http_port"
    env_set "HTTPS_PORT" "$https_port"
    env_set "MYSQL_ROOT_PASSWORD" "$mysql_root_pass"
    env_set "MYSQL_DATABASE" "$DEFAULT_DB_NAME"
    env_set "MYSQL_USER" "$DEFAULT_DB_NAME"
    env_set "MYSQL_PASSWORD" "$mysql_pass"
    env_set "DB_HOST" "db"
    env_set "TELEGRAM_BOT_TOKEN" "$YOUR_BOT_TOKEN"
    env_set "TELEGRAM_ADMIN_ID" "$YOUR_CHAT_ID"
    env_set "COMPOSE_PROJECT_NAME" "faoxima"
    env_set "PUID" "33"
    env_set "PGID" "33"
    env_set "BOTS_DIR" "$BOTS_DIR"
    env_set "INSTALL_SOURCE" "$install_source"
    env_set "FAOXIMA_INSTALLED_VERSION" "$(resolve_source_version "$install_source" "$version_arg1" "$version_arg2" "$PROJECT_DIR")"
    ui_ok "Wrote ${ENV_FILE}"

    mkdir -p "$NGINX_CONF_DIR" "$NGINX_BOTS_CONF_DIR" "$BOTS_DIR" || { ui_err "Failed to create ${NGINX_CONF_DIR}, ${NGINX_BOTS_CONF_DIR}, or ${BOTS_DIR}."; exit 1; }
    render_vhost "$domainname" "${NGINX_CONF_DIR}/00-main.conf" || {
        ui_err "Failed to render the nginx vhost for ${domainname}."
        exit 1
    }
    render_bot_location "/var/www" "app:9000" "${NGINX_BOTS_CONF_DIR}/faoxima.conf" "faoxima" || {
        ui_err "Failed to render the nginx location block for the main bot."
        exit 1
    }

    ui_action "Building images (this can take a few minutes)..."
    dc build app || { ui_err "docker compose build failed."; exit 1; }

    ui_action "Generating a temporary self-signed certificate so nginx can start..."
    ensure_dummy_cert "$domainname" || ui_warn "Could not generate a temporary certificate — nginx may fail to start until SSL is issued."

    ui_action "Starting the database first..."
    dc up -d db || { ui_err "docker compose up (db) failed."; exit 1; }

    ui_action "Waiting for the database to become healthy..."
    if ! wait_for_healthy db 180; then
        ui_err "Database did not become healthy in time. Check 'docker compose logs db'."
        exit 1
    fi
    ui_ok "Database container is healthy."

    ui_action "Starting app, nginx, and supporting services..."
    dc up -d --no-deps app nginx certbot phpmyadmin || { ui_err "docker compose up failed."; exit 1; }

    ui_action "Waiting for the app database user to accept connections..."
    if ! wait_for_db_ready 60; then
        ui_warn "App user connection failed — attempting to re-sync database credentials (e.g. a leftover volume from an earlier attempt)..."
        repair_db_user
        local repair_status=$?
        if [ "$repair_status" -eq 2 ]; then
            reset_db_volume || exit 1
            repair_db_user
        fi
        if ! wait_for_db_ready 30; then
            ui_err "The app database user still could not connect after re-syncing credentials."
            diagnose_db_failure
            exit 1
        fi
    fi
    ui_ok "Database is ready for connections."

    ui_action "Waiting for config.php to finish templating from environment..."
    if ! wait_for_config_templated 60 app; then
        ui_err "config.php was not templated in time — the app container may still be starting up."
        diagnose_table_failure app "$PROJECT_DIR"
        exit 1
    fi

    ui_action "Initialising database tables via table.php..."
    if ! run_table_migrations_until_ready app "" "" "" "$PROJECT_DIR" "Faoxima Bot"; then
        ui_err "Fix the issue above, then re-run install."
        exit 1
    fi
    ui_ok "Database tables initialised."

    ui_action "Registering Telegram webhook..."
    local secret_token
    secret_token=$(printf '%s' "${YOUR_BOT_TOKEN}_faoxima_webhook_secret" | sha256sum | awk '{print $1}')
    curl -s -F "url=https://${domainname}/faoxima/index.php" \
        -F "secret_token=${secret_token}" \
        "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || {
        ui_warn "Failed to set webhook — you can retry later via 'Change Domain'."
    }
    local MESSAGE="✅ Faoxima bot is installed! Send /start to begin."
    curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" \
        -d chat_id="${YOUR_CHAT_ID}" -d text="${MESSAGE}" || ui_warn "Failed to send the confirmation Telegram message."

    discard_dummy_cert "$domainname"

    ui_action "Requesting a Let's Encrypt certificate for ${domainname}..."
    if issue_certificate "$domainname"; then
        ui_ok "SSL certificate issued and nginx reloaded."
        local fresh_cert_enddate
        fresh_cert_enddate=$(get_cert_enddate "$domainname")
        [ -n "$fresh_cert_enddate" ] && cache_set "cert_enddate" "$fresh_cert_enddate"
        cache_set "pma_running" "1"
    else
        ui_err "SSL issuance failed for ${domainname} — installation cannot complete without a valid certificate."
        diagnose_ssl_failure "$domainname"
        ui_err "Fix the issue above (usually DNS not pointing here yet, or port 80 not reachable from the internet), then re-run install."
        exit 1
    fi

    if ! grant_file_permissions; then
        ui_err "Installation completed, but file permissions could not be applied."
        exit 1
    fi

    clear
    show_logo
    ui_status_table "INSTALLATION SUCCESSFUL" "$C_GREEN" \
        "Bot URL|${C_GREEN}https://${domainname}/faoxima${C_RESET}" \
        "phpMyAdmin|${C_GREEN}https://${domainname}/phpmyadmin/${C_RESET} ${C_DIM}(login with the DB credentials below)${C_RESET}" \
        "Database name|${C_CYAN}${DEFAULT_DB_NAME}${C_RESET}" \
        "Database user|${C_CYAN}${DEFAULT_DB_NAME}${C_RESET}" \
        "Database password|${C_CYAN}${mysql_pass}${C_RESET}" \
        "Compose file|${C_DIM}${COMPOSE_FILE}${C_RESET}"
    ui_tip "Run 'faoxima' anytime from the shell to reopen this menu."
    printf '\n'

    chmod +x "$INSTALL_SCRIPT_PATH" 2>/dev/null || true
    ln -sf "$INSTALL_SCRIPT_PATH" "$INSTALL_SCRIPT_LINK" >/dev/null 2>&1 || true
}

install_additional_bot() {
    show_logo
    ui_panel "INSTALL ADDITIONAL BOT" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Adds another bot sharing this server's domain, nginx, and MySQL.${C_RESET}" \
        "${C_DIM}The bot is reachable at the main domain under its own name (like cPanel subfolders), and gets its own database inside the same MySQL server.${C_RESET}"

    if [ ! -f "$ENV_FILE" ] || [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Install the main Faoxima Bot first (option 1) before adding additional bots."
        return 1
    fi
    if ! dc ps -q db >/dev/null 2>&1 || [ -z "$(dc ps -q db 2>/dev/null)" ]; then
        ui_err "The main stack's 'db' container is not running. Start it before adding additional bots."
        return 1
    fi

    local domainname
    domainname=$(normalize_domain "$(env_get DOMAIN)")
    if [ -z "$domainname" ]; then
        ui_err "Could not read DOMAIN from ${ENV_FILE}. Is the main bot installed correctly?"
        return 1
    fi

    local botname
    printf '\n  %s❯%s Bot name — this becomes its URL path (e.g. bot1 -> https://%s/bot1): ' "$C_YELLOW" "$C_RESET" "$domainname"
    read -r botname
    while [[ ! "$botname" =~ ^[a-zA-Z0-9_-]+$ ]] \
        || [[ "$botname" =~ ^(app|cron|db|nginx|certbot|phpmyadmin|faoxima)$ ]] \
        || [ -d "${BOTS_DIR}/${botname}" ]; do
        ui_err "Invalid or already-used bot name. Please try again."
        printf '  %s❯%s Bot name: ' "$C_YELLOW" "$C_RESET"
        read -r botname
    done

    local YOUR_BOT_TOKEN
    printf '  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]+:[a-zA-Z0-9_-]+$ ]]; do
        ui_err "Invalid bot token format. Please try again."
        printf '  %s❯%s Bot Token: ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_BOT_TOKEN
    done

    local YOUR_CHAT_ID
    printf '  %s❯%s Admin Telegram ID (numeric): ' "$C_YELLOW" "$C_RESET"
    read -r YOUR_CHAT_ID
    while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
        ui_err "Invalid chat ID format. Please try again."
        printf '  %s❯%s Admin Telegram ID (numeric): ' "$C_YELLOW" "$C_RESET"
        read -r YOUR_CHAT_ID
    done

    local bot_dir="${BOTS_DIR}/${botname}"
    mkdir -p "$bot_dir" || { ui_err "Failed to create bot directory ${bot_dir}."; return 1; }

    local main_install_source version_arg1="${1:-}" version_arg2="${2:-}"
    main_install_source=$(env_get INSTALL_SOURCE)

    if [ "$main_install_source" = "github" ]; then
        if [ -z "$version_arg1" ]; then
            local picked_version
            picked_version=$(prompt_version_selection)
            if [ -n "$picked_version" ]; then
                if [ "$picked_version" = "beta" ]; then
                    version_arg1="-beta"
                else
                    version_arg1="-v"
                    version_arg2="$picked_version"
                fi
            fi
        fi

        ui_action "Downloading Faoxima source for '${botname}'..."
        local zip_url
        zip_url=$(resolve_zip_url "$version_arg1" "$version_arg2")
        mkdir -p "$TMP_DOWNLOAD" || { ui_err "Failed to create temporary directory ${TMP_DOWNLOAD}."; return 1; }
        wget -O "${TMP_DOWNLOAD}/bot.zip" "$zip_url" || { ui_err "Failed to download Faoxima from ${zip_url}."; return 1; }
        unzip -q "${TMP_DOWNLOAD}/bot.zip" -d "$TMP_DOWNLOAD" || { ui_err "Failed to extract the downloaded archive."; return 1; }
        local extracted_dir
        extracted_dir=$(find "$TMP_DOWNLOAD" -mindepth 1 -maxdepth 1 -type d | head -1)
        [ -n "$extracted_dir" ] || { ui_err "Could not locate the extracted Faoxima directory."; return 1; }
        cp -a "${extracted_dir}/." "${bot_dir}/" || { ui_err "Failed to copy Faoxima source into ${bot_dir}."; return 1; }
        rm -rf "$TMP_DOWNLOAD"
    else
        ui_action "Copying Faoxima source from the existing local checkout at ${PROJECT_DIR}..."
        cp -a "${PROJECT_DIR}/." "${bot_dir}/" || { ui_err "Failed to copy Faoxima source into ${bot_dir}."; return 1; }
        rm -rf "${bot_dir}/.env" "${bot_dir}/bots" "${bot_dir}/nginx/conf.d" "${bot_dir}"/docker-compose.bot-*.yml
    fi

    find "${bot_dir}/re/rx" -mindepth 2 -maxdepth 2 \( -name '.compiled.php' -o -name '.compiled.map' \) -delete 2>/dev/null || true
    if [ -f "${bot_dir}/config.php" ]; then
        sed -i -E \
            -e 's/^(\$dbname[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$usernamedb[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$passworddb[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$dbhost[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$APIKEY[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$adminnumber[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$domainhosts[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            -e 's/^(\$usernamebot[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
            "${bot_dir}/config.php" || { ui_err "Failed to reset config.php for '${botname}'."; return 1; }
    fi

    local db_name="faoxima_${botname}" db_user="faoxima_${botname}" db_pass
    db_pass=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9')
    local mysql_root_pass
    mysql_root_pass=$(env_get MYSQL_ROOT_PASSWORD)

    ui_action "Creating database '${db_name}' inside the shared MySQL server..."
    dc exec -T db mysql -uroot -p"${mysql_root_pass}" -e \
        "CREATE DATABASE IF NOT EXISTS \`${db_name}\`; \
         CREATE USER IF NOT EXISTS '${db_user}'@'%' IDENTIFIED WITH mysql_native_password BY '${db_pass}'; \
         ALTER USER '${db_user}'@'%' IDENTIFIED WITH mysql_native_password BY '${db_pass}'; \
         GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'%'; \
         FLUSH PRIVILEGES;" || { ui_err "Failed to create the database for '${botname}'."; return 1; }

    cat > "${bot_dir}/.env" <<EOF
DB_HOST=db
DB_NAME=${db_name}
DB_USER=${db_user}
DB_PASS=${db_pass}
DOMAIN=${domainname}
URL_PATH=${botname}
TELEGRAM_BOT_TOKEN=${YOUR_BOT_TOKEN}
TELEGRAM_ADMIN_ID=${YOUR_CHAT_ID}
PUID=$(env_get PUID)
PGID=$(env_get PGID)
FAOXIMA_INSTALLED_VERSION=$(resolve_source_version "$main_install_source" "$version_arg1" "$version_arg2" "$bot_dir")
EOF

    cat > "${BOT_COMPOSE_PREFIX}${botname}.yml" <<EOF
services:
  app_${botname}:
    image: faoxima_app:latest
    env_file: ${bot_dir}/.env
    volumes:
      - ${bot_dir}:/var/www/faoxima
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
EOF

    mkdir -p "$NGINX_BOTS_CONF_DIR" || { ui_err "Failed to create ${NGINX_BOTS_CONF_DIR}."; return 1; }
    render_bot_location "/var/www/bots" "app_${botname}:9000" "${NGINX_BOTS_CONF_DIR}/${botname}.conf" "$botname" || {
        ui_err "Failed to render the nginx location block for '${botname}'."
        return 1
    }

    ui_action "Starting the app container for '${botname}'..."
    dc up -d --no-deps "app_${botname}" || { ui_err "Failed to start the app container for '${botname}'."; return 1; }

    ui_action "Waiting for '${botname}' database user to accept connections..."
    if ! wait_for_db_ready 60 "app_${botname}" "db" "$db_name" "$db_user" "$db_pass"; then
        ui_err "The '${botname}' database user could not connect in time."
        diagnose_db_failure "app_${botname}" "db" "$db_name" "$db_user" "$db_pass"
        return 1
    fi
    ui_ok "Database is ready for connections."

    ui_action "Waiting for '${botname}' config.php to finish templating from environment..."
    if ! wait_for_config_templated 60 "app_${botname}"; then
        ui_err "config.php was not templated in time for '${botname}' — the app container may still be starting up."
        diagnose_table_failure "app_${botname}" "$bot_dir"
        return 1
    fi

    ui_action "Reloading nginx so '${botname}' is reachable..."
    local nginx_test_output
    nginx_test_output=$(dc exec -T nginx nginx -t 2>&1)
    if [ $? -ne 0 ]; then
        ui_err "The rendered nginx config for '${botname}' is invalid — nginx will keep serving the OLD config until this is fixed."
        printf '%s\n' "$nginx_test_output"
        return 1
    fi
    dc exec nginx nginx -s reload || { ui_err "Failed to reload nginx with the new bot's location block."; return 1; }

    ui_action "Initialising database tables via table.php..."
    if ! run_table_migrations_until_ready "app_${botname}" "$db_name" "$db_user" "$db_pass" "$bot_dir" "${botname}"; then
        return 1
    fi
    ui_ok "Database tables initialised for '${botname}'."

    ui_action "Registering Telegram webhook for '${botname}'..."
    local secret_token
    secret_token=$(printf '%s' "${YOUR_BOT_TOKEN}_faoxima_webhook_secret" | sha256sum | awk '{print $1}')
    curl -s -F "url=https://${domainname}/${botname}/index.php" \
        -F "secret_token=${secret_token}" \
        "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook" || ui_warn "Failed to set webhook."
    curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" \
        -d chat_id="${YOUR_CHAT_ID}" -d text="✅ Faoxima bot '${botname}' is installed! Send /start to begin." \
        >/dev/null 2>&1 || true

    clear
    show_logo
    ui_status_table "ADDITIONAL BOT INSTALLED" "$C_GREEN" \
        "Bot name|${C_CYAN}${botname}${C_RESET}" \
        "Bot URL|${C_GREEN}https://${domainname}/${botname}${C_RESET}" \
        "phpMyAdmin|${C_GREEN}https://${domainname}/phpmyadmin/${C_RESET} ${C_DIM}(login with the DB credentials below)${C_RESET}" \
        "Database name|${C_CYAN}${db_name}${C_RESET}" \
        "Database user|${C_CYAN}${db_user}${C_RESET}" \
        "Database password|${C_CYAN}${db_pass}${C_RESET}" \
        "Source directory|${C_DIM}${bot_dir}${C_RESET}"
}

list_additional_bots() {
    show_logo
    ui_panel "ADDITIONAL BOTS" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Every bot installed alongside the main bot on this server.${C_RESET}"

    if [ ! -d "$BOTS_DIR" ] || [ -z "$(find "$BOTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)" ]; then
        ui_info "No additional bots installed."
        return 0
    fi

    local names=() d
    for d in "${BOTS_DIR}"/*/; do
        [ -d "$d" ] || continue
        names+=("$(basename "$d")")
    done

    local total="${#names[@]}"
    local page_size=20
    local page=0
    local last_page=$(( (total - 1) / page_size ))
    local name domain domain_line bot_dir

    while true; do
        local start=$((page * page_size))
        local end=$((start + page_size))
        [ "$end" -gt "$total" ] && end="$total"

        local i
        for ((i = start; i < end; i++)); do
            name="${names[$i]}"
            bot_dir="${BOTS_DIR}/${name}"
            domain=""
            [ -f "${bot_dir}/.env" ] && domain=$(grep -E '^DOMAIN=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
            if [ -n "$domain" ]; then
                domain_line="https://${domain}/${name}"
            else
                domain_line="unknown"
            fi
            local bot_version
            bot_version=$(get_additional_bot_version "$name")
            ui_status_table "$name" "$C_CYAN" \
                "Version|${C_WHITE}${bot_version}${C_RESET}" \
                "Domain|${C_GREEN}${domain_line}${C_RESET}" \
                "Directory|${C_DIM}${bot_dir}${C_RESET}"
        done

        if [ "$last_page" -le 0 ]; then
            return 0
        fi

        printf '\n  %s(page %d of %d)%s\n' "$C_DIM" "$((page + 1))" "$((last_page + 1))" "$C_RESET"
        local nav_hint=""
        [ "$page" -lt "$last_page" ] && nav_hint="${nav_hint}n) next page  "
        [ "$page" -gt 0 ] && nav_hint="${nav_hint}p) previous page  "
        printf '\n  %s❯%s %s(Enter to finish): ' "$C_YELLOW" "$C_RESET" "$nav_hint"
        local nav
        read -r nav
        case "$nav" in
            n|N) [ "$page" -lt "$last_page" ] && page=$((page + 1)) ;;
            p|P) [ "$page" -gt 0 ] && page=$((page - 1)) ;;
            *) return 0 ;;
        esac
        show_logo
        ui_panel "ADDITIONAL BOTS" "$C_BOLD$C_CYAN" "$C_CYAN" \
            "${C_WHITE}Every bot installed alongside the main bot on this server.${C_RESET}"
    done
}

remove_additional_bot() {
    show_logo
    ui_panel "REMOVE ADDITIONAL BOT" "$C_BOLD$C_RED" "$C_RED" \
        "${C_WHITE}Stops and removes an additional bot's containers, database, and vhost.${C_RESET}" \
        "${C_DIM}The main bot and other additional bots are left untouched.${C_RESET}"

    if [ ! -d "$BOTS_DIR" ] || [ -z "$(find "$BOTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)" ]; then
        ui_info "No additional bots installed."
        return 0
    fi

    local names=() d
    for d in "${BOTS_DIR}"/*/; do
        [ -d "$d" ] || continue
        names+=("$(basename "$d")")
    done

    if ! ui_pick_from_list "Which number to remove" "${names[@]}"; then
        ui_info "Cancelled."
        return 0
    fi

    local botname="${names[$UI_PICK_RESULT]}"
    local bot_dir="${BOTS_DIR}/${botname}"

    printf '  %s❯%s This will delete the bot'"'"'s containers, database, and source. Continue? (y/n): ' "$C_YELLOW" "$C_RESET"
    local confirm
    read -r confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        ui_info "Cancelled."
        return 0
    fi

    ui_action "Stopping and removing containers for '${botname}'..."
    dc rm -f -s "app_${botname}" || ui_warn "Failed to stop/remove containers for '${botname}' — they may already be gone."
    rm -f "${BOT_COMPOSE_PREFIX}${botname}.yml"

    local db_name="faoxima_${botname}" db_user="faoxima_${botname}" mysql_root_pass db_drop_output
    mysql_root_pass=$(env_get MYSQL_ROOT_PASSWORD)
    ui_action "Dropping database '${db_name}'..."
    db_drop_output=$(dc exec -T db mysql -uroot -p"${mysql_root_pass}" -e \
        "DROP DATABASE IF EXISTS \`${db_name}\`; DROP USER IF EXISTS '${db_user}'@'%';" 2>&1)
    if [ $? -ne 0 ]; then
        ui_err "Failed to drop database '${db_name}' — it may need manual cleanup."
        printf '%s\n' "$db_drop_output"
    fi

    rm -f "${NGINX_BOTS_CONF_DIR}/${botname}.conf"
    local nginx_test_output
    nginx_test_output=$(dc exec -T nginx nginx -t 2>&1)
    if [ $? -ne 0 ]; then
        ui_err "nginx config is invalid after removing '${botname}'s location block — check manually."
        printf '%s\n' "$nginx_test_output"
    else
        dc exec nginx nginx -s reload || ui_warn "Failed to reload nginx after removing '${botname}'."
    fi

    rm -rf "$bot_dir"

    local leftovers=()
    dc ps -a -q "app_${botname}" 2>/dev/null | grep -q . && leftovers+=("container app_${botname}")
    [ -f "${BOT_COMPOSE_PREFIX}${botname}.yml" ] && leftovers+=("compose fragment ${BOT_COMPOSE_PREFIX}${botname}.yml")
    [ -f "${NGINX_BOTS_CONF_DIR}/${botname}.conf" ] && leftovers+=("nginx config ${NGINX_BOTS_CONF_DIR}/${botname}.conf")
    [ -d "$bot_dir" ] && leftovers+=("source directory ${bot_dir}")

    if [ "${#leftovers[@]}" -gt 0 ]; then
        ui_warn "Removal finished but some parts are still present: ${leftovers[*]}"
        return 1
    fi
    ui_ok "Additional bot '${botname}' removed."
}

get_additional_bot_version() {
    local botname="$1" bot_dir="${BOTS_DIR}/${botname}" version="" recovered="0"
    if [ -f "${bot_dir}/.env" ]; then
        version=$(file_env_get "${bot_dir}/.env" "FAOXIMA_INSTALLED_VERSION" 2>/dev/null)
    fi
    if [ -z "$version" ] && [ -f "${bot_dir}/version" ]; then
        version=$(tr -d '[:space:]' < "${bot_dir}/version")
        [ -n "$version" ] && recovered="1"
    fi
    if [ -z "$version" ] && [ -f "${bot_dir}/install.sh" ]; then
        version=$(awk -F'"' '/^[[:space:]]*readonly[[:space:]]+FAOXIMA_VERSION="/{print $2; exit}' "${bot_dir}/install.sh" | tr -d '[:space:]')
        [ -n "$version" ] && recovered="1"
    fi
    if [[ "$version" =~ ^v?[0-9]+([.][0-9]+)*([.-][A-Za-z0-9._-]+)?$ ]]; then
        if [ "$recovered" = "1" ] && [ -f "${bot_dir}/.env" ]; then
            file_env_set "${bot_dir}/.env" "FAOXIMA_INSTALLED_VERSION" "$version" >/dev/null 2>&1 || true
        fi
        printf '%s' "$version"
        return 0
    fi
    printf 'unknown'
}

confirm_additional_bot_downgrade() {
    local botname="$1" mode="$2" version_arg1="$3" version_arg2="$4"
    [ "$mode" = "github" ] || return 0
    [[ "$version_arg1" == "-beta" ]] && return 0

    local current target
    current=$(get_additional_bot_version "$botname")
    target=$(resolve_source_version "$mode" "$version_arg1" "$version_arg2" "${BOTS_DIR}/${botname}")

    if version_is_newer "$current" "$target"; then
        ui_warn "Downgrade detected for '${botname}': ${current} -> ${target}."
        printf '  %s❯%s Continue with this downgrade? (y/N): ' "$C_YELLOW" "$C_RESET"
        local confirm
        read -r confirm
        [[ "${confirm,,}" == "y" ]]
        return $?
    fi
    return 0
}

update_single_additional_bot() {
    local botname="$1" mode="$2" version_arg1="$3" version_arg2="$4" zip_path="$5"
    local bot_dir="${BOTS_DIR}/${botname}"
    if [ ! -d "$bot_dir" ]; then
        ui_err "No additional bot named '${botname}' found."
        return 1
    fi

    local db_name db_user db_pass
    db_name=$(grep -E '^DB_NAME=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
    db_user=$(grep -E '^DB_USER=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
    db_pass=$(grep -E '^DB_PASS=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
    if [ -z "$db_name" ] || [ -z "$db_user" ]; then
        ui_err "Failed to read database credentials from ${bot_dir}/.env for '${botname}'."
        return 1
    fi

    update_bot_source "$bot_dir" "app_${botname}" "$botname" \
        "$mode" "$version_arg1" "$version_arg2" "$zip_path" "$db_name" "$db_user" "$db_pass" "0"
}

menu_update_additional_bots() {
    show_logo
    ui_panel "UPDATE ADDITIONAL BOTS" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Update one additional bot, or all of them at once.${C_RESET}"

    if [ ! -d "$BOTS_DIR" ] || [ -z "$(find "$BOTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)" ]; then
        ui_info "No additional bots installed."
        return 0
    fi

    printf '\n'
    printf '  %s1)%s Update Single Bot\n' "$C_CYAN" "$C_RESET"
    printf '  %s2)%s Update All Bots\n' "$C_CYAN" "$C_RESET"
    local scope
    printf '\n  %s❯%s Select an option [1-2]: ' "$C_YELLOW" "$C_RESET"
    read -r scope

    local names=() display_names=() versions=() d name version
    for d in "${BOTS_DIR}"/*/; do
        [ -d "$d" ] || continue
        name=$(basename "$d")
        version=$(get_additional_bot_version "$name")
        names+=("$name")
        versions+=("$version")
        display_names+=("${name} (current: ${version})")
    done

    local targets=()
    case "$scope" in
        1)
            if ! ui_pick_from_list "Which number to update" "${display_names[@]}"; then
                ui_info "Cancelled."
                return 0
            fi
            targets=("${names[$UI_PICK_RESULT]}")
            ;;
        2)
            targets=("${names[@]}")
            ;;
        *)
            ui_err "Invalid selection."
            return 1
            ;;
    esac

    local mode
    mode=$(prompt_update_source)
    if [ -z "$mode" ]; then
        ui_err "Invalid selection."
        return 1
    fi

    local failures=() skipped=() updated=() zip_path="" version_arg1="" version_arg2=""

    if [ "$mode" = "manual" ]; then
        printf '  %s❯%s Path to the update ZIP file: ' "$C_YELLOW" "$C_RESET"
        read -r zip_path
        if ! validate_update_zip "$zip_path"; then
            return 1
        fi

        for name in "${targets[@]}"; do
            ui_action "Updating '${name}'..."
            if update_single_additional_bot "$name" "$mode" "" "" "$zip_path"; then
                updated+=("$name")
            else
                failures+=("$name")
            fi
        done
    elif [ "$scope" = "1" ]; then
        local UPDATE_VERSION_ARG1 UPDATE_VERSION_ARG2
        if ! select_update_version; then
            ui_err "No update version was selected."
            return 1
        fi
        version_arg1="$UPDATE_VERSION_ARG1"
        version_arg2="$UPDATE_VERSION_ARG2"
        name="${targets[0]}"

        if ! confirm_additional_bot_downgrade "$name" "$mode" "$version_arg1" "$version_arg2"; then
            ui_info "Update cancelled for '${name}'."
            return 0
        fi

        ui_action "Updating '${name}'..."
        if update_single_additional_bot "$name" "$mode" "$version_arg1" "$version_arg2" ""; then
            updated+=("$name")
        else
            failures+=("$name")
        fi
    else
        local target_index=0 target_total="${#targets[@]}" current_version target_version
        for name in "${targets[@]}"; do
            target_index=$((target_index + 1))
            current_version=$(get_additional_bot_version "$name")
            printf '\n'
            ui_panel "VERSION FOR ${name}" "$C_BOLD$C_CYAN" "$C_CYAN" \
                "${C_WHITE}Bot ${target_index} of ${target_total}${C_RESET}" \
                "${C_DIM}Current version: ${current_version}${C_RESET}"

            local UPDATE_VERSION_ARG1 UPDATE_VERSION_ARG2
            if ! select_update_version; then
                ui_warn "No version selected for '${name}' — skipped."
                skipped+=("$name")
                continue
            fi
            version_arg1="$UPDATE_VERSION_ARG1"
            version_arg2="$UPDATE_VERSION_ARG2"
            target_version=$(resolve_source_version "github" "$version_arg1" "$version_arg2" "${BOTS_DIR}/${name}")
            ui_info "Selected for '${name}': ${target_version}"

            if ! confirm_additional_bot_downgrade "$name" "$mode" "$version_arg1" "$version_arg2"; then
                ui_warn "Skipped downgrade for '${name}'."
                skipped+=("$name")
                continue
            fi

            ui_action "Updating '${name}'..."
            if update_single_additional_bot "$name" "$mode" "$version_arg1" "$version_arg2" ""; then
                updated+=("$name")
            else
                failures+=("$name")
            fi
        done
    fi

    [ "${#updated[@]}" -gt 0 ] && ui_ok "Updated successfully: ${updated[*]}"
    [ "${#skipped[@]}" -gt 0 ] && ui_warn "Skipped: ${skipped[*]}"
    if [ "${#failures[@]}" -gt 0 ]; then
        ui_err "Update failed for: ${failures[*]}"
        return 1
    fi
    return 0
}

install_beta_additional_bot() {
    show_logo
    ui_panel "INSTALL BETA — ADDITIONAL BOT" "$C_BOLD$C_YELLOW" "$C_YELLOW" \
        "${C_WHITE}Add a new bot on the latest main-branch build, or switch an existing one.${C_RESET}" \
        "${C_DIM}This is not an official release — use it for testing only.${C_RESET}"

    if [ ! -f "$ENV_FILE" ] || [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Install the main Faoxima Bot first (option 1) before adding additional bots."
        return 1
    fi

    local names=("New Additional Bot") d
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            names+=("$(basename "$d")")
        done
    fi

    if ! ui_pick_from_list "New bot, or which existing bot to switch to Beta" "${names[@]}"; then
        ui_info "Cancelled."
        return 0
    fi

    if [ "$UI_PICK_RESULT" -eq 0 ]; then
        install_additional_bot "-beta"
        return $?
    fi

    local botname="${names[$UI_PICK_RESULT]}"
    printf '  %s❯%s This will overwrite '"'"'%s'"'"'s source with the latest Beta build while preserving existing credentials. Continue? (y/N): ' \
        "$C_YELLOW" "$C_RESET" "$botname"
    local confirm
    read -r confirm
    if [[ "${confirm,,}" != "y" ]]; then
        ui_info "Cancelled."
        return 0
    fi

    if ! update_single_additional_bot "$botname" "github" "-beta" "" ""; then
        return 1
    fi
    ui_ok "'${botname}' switched to the latest Beta build."
}

validate_update_zip() {
    local zip_path="$1"
    if [ ! -f "$zip_path" ]; then
        ui_err "File not found: ${zip_path}"
        return 1
    fi
    if [[ ! "$zip_path" =~ \.zip$ ]]; then
        ui_err "File must have a .zip extension: ${zip_path}"
        return 1
    fi
    if ! unzip -tq "$zip_path" >/dev/null 2>&1; then
        ui_err "The file at ${zip_path} is not a valid/readable ZIP archive."
        return 1
    fi
    return 0
}

prompt_update_source() {
    printf '\n' >&2
    printf '  %s1)%s Update from GitHub\n' "$C_CYAN" "$C_RESET" >&2
    printf '  %s2)%s Manual Update (ZIP)\n' "$C_CYAN" "$C_RESET" >&2
    local mode
    printf '\n  %s❯%s Select update method [1-2]: ' "$C_YELLOW" "$C_RESET" >&2
    read -r mode
    case "$mode" in
        1) printf 'github' ;;
        2) printf 'manual' ;;
        *) printf '' ;;
    esac
}

select_update_version() {
    local picked_version
    picked_version=$(prompt_version_selection) || return 1
    [ -n "$picked_version" ] || return 1
    if [ "$picked_version" = "beta" ]; then
        UPDATE_VERSION_ARG1="-beta"
        UPDATE_VERSION_ARG2=""
    else
        UPDATE_VERSION_ARG1="-v"
        UPDATE_VERSION_ARG2="$picked_version"
    fi
}

prepare_update_source_dir() {
    local mode="$1" version_arg1="$2" version_arg2="$3" zip_path="$4" work_dir="$5"
    local extracted_dir

    {
        if [ "$mode" = "manual" ]; then
            if ! validate_update_zip "$zip_path"; then
                return 1
            fi
            ui_action "Extracting ${zip_path}..."
            unzip -q "$zip_path" -d "$work_dir" || { ui_err "Failed to extract ${zip_path}."; return 1; }
        else
            local zip_url
            zip_url=$(resolve_zip_url "$version_arg1" "$version_arg2")
            ui_action "Downloading update from ${zip_url}..."
            wget -O "${work_dir}/bot.zip" "$zip_url" || { ui_err "Failed to download update package."; return 1; }
            unzip -q "${work_dir}/bot.zip" -d "$work_dir" || { ui_err "Failed to extract the update archive."; return 1; }
        fi

        extracted_dir=$(find "$work_dir" -mindepth 1 -maxdepth 1 -type d | head -1)
        [ -n "$extracted_dir" ] || { ui_err "Could not locate the extracted update directory."; return 1; }
    } >&2
    printf '%s' "$extracted_dir"
}

config_file_get_var() {
    local file="$1" var="$2"
    [ -f "$file" ] || return 1
    sed -n -E "s|^[[:space:]]*\$${var}[[:space:]]*=[[:space:]]*(['\"])(.*)\1[[:space:]]*;.*$|\2|p" "$file" | head -1
}

config_file_set_var() {
    local file="$1" var="$2" value="$3"
    [ -f "$file" ] || return 1
    local escaped
    escaped=${value//\\/\\\\}
    escaped=${escaped//&/\\&}
    escaped=${escaped//|/\\|}
    escaped=${escaped//'/\\'}
    if grep -qE "^[[:space:]]*\$${var}[[:space:]]*=" "$file"; then
        sed -i -E "s|^([[:space:]]*\$${var}[[:space:]]*=[[:space:]]*)['\"][^'\"]*['\"]([[:space:]]*;.*)$|\1'${escaped}'\2|" "$file" || return 1
    fi
}

migrate_runtime_config_values() {
    local old_config="$1" new_config="$2" db_name="$3" db_user="$4" db_pass="$5"
    local env_file="$6" var value db_host bot_token admin_id domain

    db_host=$(file_env_get "$env_file" "DB_HOST" 2>/dev/null)
    [ -z "$db_host" ] && db_host=$(env_get DB_HOST 2>/dev/null)
    [ -z "$db_host" ] && db_host="db"

    bot_token=$(file_env_get "$env_file" "TELEGRAM_BOT_TOKEN" 2>/dev/null)
    admin_id=$(file_env_get "$env_file" "TELEGRAM_ADMIN_ID" 2>/dev/null)
    domain=$(file_env_get "$env_file" "DOMAIN" 2>/dev/null)

    config_file_set_var "$new_config" "dbname" "$db_name" || return 1
    config_file_set_var "$new_config" "usernamedb" "$db_user" || return 1
    config_file_set_var "$new_config" "passworddb" "$db_pass" || return 1
    config_file_set_var "$new_config" "dbhost" "$db_host" || return 1
    [ -n "$bot_token" ] && config_file_set_var "$new_config" "APIKEY" "$bot_token" || true
    [ -n "$admin_id" ] && config_file_set_var "$new_config" "adminnumber" "$admin_id" || true
    [ -n "$domain" ] && config_file_set_var "$new_config" "domainhosts" "$(normalize_domain "$domain")" || true

    for var in usernamebot redis_host redis_port redis_password redis_database; do
        value=$(config_file_get_var "$old_config" "$var" 2>/dev/null)
        [ -n "$value" ] && config_file_set_var "$new_config" "$var" "$value" || true
    done
}

update_bot_source() {
    local code_dir="$1" app_service="$2" label="$3" \
        mode="$4" version_arg1="$5" version_arg2="$6" zip_path="$7" \
        db_name="$8" db_user="$9" db_pass="${10}" should_build="${11:-1}"

    local work_dir
    work_dir=$(mktemp -d "${TMP_UPDATE}.XXXXXX") || { ui_err "Failed to create a temporary work directory."; return 1; }

    local extracted_dir
    extracted_dir=$(prepare_update_source_dir "$mode" "$version_arg1" "$version_arg2" "$zip_path" "$work_dir")
    if [ -z "$extracted_dir" ]; then
        rm -rf "$work_dir"
        return 1
    fi

    local safe_label config_path env_path temp_config temp_env
    safe_label=$(printf '%s' "$label" | tr -c 'a-zA-Z0-9_' '_')
    config_path="${code_dir}/config.php"
    env_path="${code_dir}/.env"
    temp_config=$(mktemp "/root/${safe_label}_config_backup.XXXXXX.php") || { rm -rf "$work_dir"; ui_err "Failed to create a config.php backup file."; return 1; }
    temp_env=$(mktemp "/root/${safe_label}_env_backup.XXXXXX") || { rm -rf "$work_dir" "$temp_config"; ui_err "Failed to create a .env backup file."; return 1; }

    if [ -f "$config_path" ]; then
        cp "$config_path" "$temp_config" || { rm -rf "$work_dir" "$temp_config" "$temp_env"; ui_err "Config backup failed for '${label}'."; return 1; }
    else
        : > "$temp_config"
    fi
    if [ -f "$env_path" ]; then
        cp "$env_path" "$temp_env" || { rm -rf "$work_dir" "$temp_config" "$temp_env"; ui_err ".env backup failed for '${label}'."; return 1; }
    else
        : > "$temp_env"
    fi

    ui_action "Extracting update onto ${code_dir}..."
    if ! cp -a "${extracted_dir}/." "${code_dir}/"; then
        rm -rf "$work_dir" "$temp_config" "$temp_env"
        ui_err "File transfer failed for '${label}'."
        return 1
    fi

    if [ -s "$temp_env" ]; then
        cp "$temp_env" "$env_path" || { rm -rf "$work_dir" "$temp_config" "$temp_env"; ui_err ".env restore failed for '${label}'."; return 1; }
    fi

    if [ ! -f "$config_path" ]; then
        rm -rf "$work_dir" "$temp_config" "$temp_env"
        ui_err "The update package does not contain config.php for '${label}'."
        return 1
    fi

    if ! migrate_runtime_config_values "$temp_config" "$config_path" "$db_name" "$db_user" "$db_pass" "$env_path"; then
        [ -s "$temp_config" ] && cp "$temp_config" "$config_path" >/dev/null 2>&1 || true
        rm -rf "$work_dir" "$temp_config" "$temp_env"
        ui_err "Failed to migrate runtime credentials into the new config.php for '${label}'."
        return 1
    fi

    rm -rf "$work_dir" "$temp_config" "$temp_env"

    if [ "$should_build" = "1" ]; then
        ui_action "Rebuilding the app image for '${label}'..."
        dc build "$app_service" || ui_warn "Rebuilding the app image for '${label}' failed — continuing with the existing image."
    fi
    ui_action "Restarting services for '${label}' with the updated source..."
    dc up -d --no-deps "$app_service" || { ui_err "Failed to bring '${label}' services back up."; return 1; }

    ui_action "Waiting for the '${label}' database user to accept connections..."
    if ! wait_for_db_ready 60 "$app_service" "db" "$db_name" "$db_user" "$db_pass"; then
        ui_err "The '${label}' database user could not connect after the update."
        diagnose_db_failure "$app_service" "db" "$db_name" "$db_user" "$db_pass"
        return 1
    fi

    if ! run_table_migrations_until_ready "$app_service" "$db_name" "$db_user" "$db_pass" "$code_dir" "${label}"; then
        return 1
    fi

    dc restart "$app_service" || { ui_err "Failed to restart the '${app_service}' container after the update."; return 1; }
    sleep 2
    if ! grant_file_permissions "$app_service"; then
        return 1
    fi

    local installed_version
    installed_version=$(resolve_source_version "$mode" "$version_arg1" "$version_arg2" "$code_dir")
    file_env_set "${code_dir}/.env" "FAOXIMA_INSTALLED_VERSION" "$installed_version" || {
        ui_err "Failed to save the installed version for '${label}'."
        return 1
    }

    ui_ok "'${label}' updated successfully."
    return 0
}

update_bot() {
    show_logo
    ui_panel "UPDATE FAOXIMA BOT" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Update from the latest GitHub release, or from a manually-provided ZIP.${C_RESET}" \
        "${C_DIM}Latest config.php code is installed while existing credentials and .env values are preserved.${C_RESET}"

    if [ ! -f "$ENV_FILE" ] || [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed (no .env/docker-compose.yml at ${PROJECT_DIR})."
        exit 1
    fi

    local mode
    mode=$(prompt_update_source)
    if [ -z "$mode" ]; then
        ui_err "Invalid selection."
        exit 1
    fi

    local zip_path="" version_arg1="" version_arg2=""
    if [ "$mode" = "manual" ]; then
        printf '  %s❯%s Path to the update ZIP file: ' "$C_YELLOW" "$C_RESET"
        read -r zip_path
    else
        local UPDATE_VERSION_ARG1 UPDATE_VERSION_ARG2
        if ! select_update_version; then
            ui_err "No update version was selected."
            exit 1
        fi
        version_arg1="$UPDATE_VERSION_ARG1"
        version_arg2="$UPDATE_VERSION_ARG2"
    fi

    local db_name db_user db_pass
    require_env_db_creds || exit 1
    db_name="$DB_NAME"
    db_user="$DB_USER"
    db_pass="$DB_PASS"

    if ! update_bot_source "$PROJECT_DIR" "app" "Faoxima Bot" "$mode" "$version_arg1" "$version_arg2" "$zip_path" "$db_name" "$db_user" "$db_pass"; then
        exit 1
    fi

    if [ -f "$INSTALL_SCRIPT_PATH" ]; then
        if chmod +x "$INSTALL_SCRIPT_PATH" 2>/dev/null && ln -sf "$INSTALL_SCRIPT_PATH" "$INSTALL_SCRIPT_LINK" >/dev/null 2>&1; then
            ui_ok "Ensured ${INSTALL_SCRIPT_PATH} is executable and 'faoxima' command is linked."
        else
            ui_warn "Could not update permissions/symlink for ${INSTALL_SCRIPT_PATH} — the 'faoxima' shell command may be stale."
        fi
    fi
}

install_beta_bot() {
    show_logo
    ui_panel "INSTALL BETA — FAOXIMA BOT" "$C_BOLD$C_YELLOW" "$C_YELLOW" \
        "${C_WHITE}Installs (or switches to) the latest main-branch build.${C_RESET}" \
        "${C_DIM}This is not an official release — use it for testing only.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        install_bot "-beta"
        return $?
    fi

    local install_source
    install_source=$(env_get INSTALL_SOURCE)
    if [ "$install_source" != "github" ]; then
        ui_err "This bot was not installed from GitHub, so beta switching isn't available — reinstall from GitHub first."
        return 1
    fi

    printf '  %s❯%s This will overwrite the current source with the latest Beta build while preserving existing credentials. Continue? (y/N): ' "$C_YELLOW" "$C_RESET"
    local confirm
    read -r confirm
    if [[ "${confirm,,}" != "y" ]]; then
        ui_info "Cancelled."
        return 0
    fi

    local db_name db_user db_pass
    require_env_db_creds || return 1
    db_name="$DB_NAME"
    db_user="$DB_USER"
    db_pass="$DB_PASS"

    if ! update_bot_source "$PROJECT_DIR" "app" "Faoxima Bot" "github" "-beta" "" "" "$db_name" "$db_user" "$db_pass"; then
        return 1
    fi
    ui_ok "Faoxima Bot switched to the latest Beta build."
}

remove_bot() {
    show_logo
    ui_panel "REMOVE FAOXIMA BOT" "$C_BOLD$C_RED" "$C_RED" \
        "${C_WHITE}This tears down the Docker stack (containers + db/cert volumes).${C_RESET}" \
        "${C_DIM}If the source was downloaded from GitHub, ${PROJECT_DIR} is deleted too.${C_RESET}" \
        "${C_DIM}If it was uploaded manually, the source is kept and only config.php is reset.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "No docker-compose.yml found at ${COMPOSE_FILE}. Nothing to remove."
        exit 1
    fi

    if [ -d "$BOTS_DIR" ] && [ -n "$(find "$BOTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)" ]; then
        ui_warn "Additional bots are still installed under ${BOTS_DIR}."
        ui_warn "They share this stack's db/nginx — removing the main bot will also stop them."
        ui_warn "Remove each additional bot first (option 6) if you want to keep them working."
    fi

    local choice
    printf '\n  %s❯%s Are you sure you want to remove the Faoxima Docker stack? (y/n): ' \
        "$C_YELLOW" "$C_RESET"
    read -r choice
    if [[ "$choice" != "y" && "$choice" != "Y" ]]; then
        ui_warn "Aborting..."
        return 0
    fi

    local install_source
    install_source=$(env_get INSTALL_SOURCE)

    log_action "Tearing down the Faoxima Docker stack..."
    dc down -v || { ui_err "docker compose down -v failed."; exit 1; }
    ui_ok "Containers and volumes (db_data, certs, certbot_webroot) removed."

    if [ -f "$ENV_FILE" ]; then
        rm -f "$ENV_FILE"
        ui_ok ".env removed."
    fi

    if [ "$install_source" = "github" ]; then
        rm -rf "${PROJECT_DIR:?}"/* "${PROJECT_DIR:?}"/.[!.]* 2>/dev/null || true
        ui_ok "Faoxima Docker stack removed. Source at ${PROJECT_DIR} (downloaded from GitHub) was deleted as well."
    else
        if [ -f "${PROJECT_DIR}/config.php" ]; then
            sed -i -E \
                -e 's/^(\$dbname[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$usernamedb[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$passworddb[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$dbhost[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$APIKEY[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$adminnumber[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$domainhosts[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                -e 's/^(\$usernamebot[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"''"'\2/' \
                "${PROJECT_DIR}/config.php" || ui_warn "Failed to reset config.php — a future reinstall may reuse stale database credentials."
            ui_ok "config.php reset so the next install re-templates fresh credentials."
        fi
        ui_ok "Faoxima Docker stack removed. Source code left in place at ${PROJECT_DIR}."
    fi
}

require_env_db_creds() {
    if [ ! -f "$ENV_FILE" ]; then
        ui_err ".env not found at ${ENV_FILE}."
        return 1
    fi
    DB_USER=$(env_get MYSQL_USER)
    DB_PASS=$(env_get MYSQL_PASSWORD)
    DB_NAME=$(env_get MYSQL_DATABASE)
    local config_user config_pass
    config_user=$(read_config_credential "app" "usernamedb")
    config_pass=$(read_config_credential "app" "passworddb")
    [ -n "$config_user" ] && DB_USER="$config_user"
    [ -n "$config_pass" ] && DB_PASS="$config_pass"
    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        ui_err "Failed to read database credentials from ${ENV_FILE}."
        return 1
    fi
    file_env_set "$ENV_FILE" "MYSQL_USER" "$DB_USER" || return 1
    file_env_set "$ENV_FILE" "MYSQL_PASSWORD" "$DB_PASS" || return 1
    return 0
}

dump_bot_database() {
    local label="$1" db_name="$2" db_user="$3" db_pass="$4" backup_file="$5"
    ui_action "Creating backup for '${label}' at ${backup_file}..."
    if ! dc exec -T db mysqldump -u"$db_user" -p"$db_pass" --no-tablespaces "$db_name" > "$backup_file"; then
        ui_err "Failed to create database backup for '${label}'."
        return 1
    fi
    ui_ok "Backup for '${label}' successfully created at ${backup_file}."
}

export_database_single() {
    show_logo
    ui_panel "EXPORT DATABASE — SINGLE BOT" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Back up one bot's database on its own.${C_RESET}"

    local names=("main") d
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            names+=("$(basename "$d")")
        done
    fi

    if ! ui_pick_from_list "Which bot to back up" "${names[@]}"; then
        ui_info "Cancelled."
        return 0
    fi

    local botname="${names[$UI_PICK_RESULT]}"
    if [ "$botname" = "main" ]; then
        if ! require_env_db_creds; then return 1; fi
        local backup_file="/root/${DB_NAME}_backup_$(date +%Y-%m-%d).sql"
        dump_bot_database "main" "$DB_NAME" "$DB_USER" "$DB_PASS" "$backup_file"
        return $?
    fi

    local bot_dir="${BOTS_DIR}/${botname}"
    if [ ! -f "${bot_dir}/.env" ]; then
        ui_err "Could not find .env for '${botname}' at ${bot_dir}."
        return 1
    fi
    local db_name db_user db_pass
    db_name=$(grep -E '^DB_NAME=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
    db_user=$(grep -E '^DB_USER=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
    db_pass=$(grep -E '^DB_PASS=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
    if [ -z "$db_name" ] || [ -z "$db_user" ]; then
        ui_err "Failed to read database credentials from ${bot_dir}/.env for '${botname}'."
        return 1
    fi

    local backup_file="/root/${db_name}_backup_$(date +%Y-%m-%d).sql"
    dump_bot_database "$botname" "$db_name" "$db_user" "$db_pass" "$backup_file"
}

export_database_all() {
    show_logo
    ui_panel "EXPORT DATABASE — ALL BOTS" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Backs up the main bot and every additional bot's database in one pass.${C_RESET}"

    local failures=0

    if require_env_db_creds; then
        local backup_file="/root/${DB_NAME}_backup_$(date +%Y-%m-%d).sql"
        dump_bot_database "main" "$DB_NAME" "$DB_USER" "$DB_PASS" "$backup_file" || failures=$((failures + 1))
    else
        ui_err "Failed to read database credentials for the main bot."
        failures=$((failures + 1))
    fi

    if [ -d "$BOTS_DIR" ]; then
        local d botname bot_dir db_name db_user db_pass backup_file
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            botname=$(basename "$d")
            bot_dir="${BOTS_DIR}/${botname}"
            if [ ! -f "${bot_dir}/.env" ]; then
                ui_err "Could not find .env for '${botname}' at ${bot_dir}."
                failures=$((failures + 1))
                continue
            fi
            db_name=$(grep -E '^DB_NAME=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
            db_user=$(grep -E '^DB_USER=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
            db_pass=$(grep -E '^DB_PASS=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
            if [ -z "$db_name" ] || [ -z "$db_user" ]; then
                ui_err "Failed to read database credentials from ${bot_dir}/.env for '${botname}'."
                failures=$((failures + 1))
                continue
            fi
            backup_file="/root/${db_name}_backup_$(date +%Y-%m-%d).sql"
            dump_bot_database "$botname" "$db_name" "$db_user" "$db_pass" "$backup_file" || failures=$((failures + 1))
        done
    fi

    if [ "$failures" -gt 0 ]; then
        ui_warn "Bulk export finished with ${failures} failure(s) — see the messages above."
        return 1
    fi
    ui_ok "Bulk export finished successfully for the main bot and all additional bots."
}

import_database() {
    show_logo
    ui_panel "IMPORT DATABASE" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Restore a previously-saved SQL dump into a bot's database.${C_RESET}" \
        "${C_RED}This replaces the current database contents entirely.${C_RESET}"

    local names=("main") d
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            names+=("$(basename "$d")")
        done
    fi

    if ! ui_pick_from_list "Which bot to import into" "${names[@]}"; then
        ui_info "Cancelled."
        return 0
    fi

    local botname="${names[$UI_PICK_RESULT]}"
    local DB_NAME DB_USER DB_PASS
    if [ "$botname" = "main" ]; then
        if ! require_env_db_creds; then return 1; fi
    else
        local bot_dir="${BOTS_DIR}/${botname}"
        if [ ! -f "${bot_dir}/.env" ]; then
            ui_err "Could not find .env for '${botname}' at ${bot_dir}."
            return 1
        fi
        DB_NAME=$(grep -E '^DB_NAME=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
        DB_USER=$(grep -E '^DB_USER=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
        DB_PASS=$(grep -E '^DB_PASS=' "${bot_dir}/.env" | tail -1 | cut -d'=' -f2-)
        if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
            ui_err "Failed to read database credentials from ${bot_dir}/.env for '${botname}'."
            return 1
        fi
    fi

    local BACKUP_FILE
    while true; do
        printf '\n  %s❯%s Path to backup file [default: /root/%s_backup.sql]: ' \
            "$C_YELLOW" "$C_RESET" "$DB_NAME"
        read -r BACKUP_FILE
        BACKUP_FILE=${BACKUP_FILE:-/root/${DB_NAME}_backup.sql}
        if [[ ! -f "$BACKUP_FILE" || ! "$BACKUP_FILE" =~ \.sql$ ]]; then
            ui_err "Invalid file path or format. Please provide a valid .sql file."
            continue
        fi
        if [ ! -s "$BACKUP_FILE" ]; then
            ui_err "The file ${BACKUP_FILE} is empty."
            continue
        fi
        if ! head -c 65536 "$BACKUP_FILE" | grep -qiE 'CREATE TABLE|INSERT INTO|DROP TABLE'; then
            ui_err "The file ${BACKUP_FILE} does not look like a valid SQL dump (no CREATE/INSERT/DROP statements found)."
            continue
        fi
        break
    done

    printf '  %s❯%s This will DROP the current database '"'"'%s'"'"' and replace it with the contents of %s. Continue? (y/N): ' \
        "$C_YELLOW" "$C_RESET" "$DB_NAME" "$BACKUP_FILE"
    local confirm
    read -r confirm
    if [[ "${confirm,,}" != "y" ]]; then
        ui_info "Import cancelled."
        return 0
    fi

    ui_action "Dropping and recreating database '${DB_NAME}'..."
    if ! dc exec -T db mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\`;" 2>/dev/null; then
        ui_err "Failed to drop/recreate database '${DB_NAME}' — the app user may lack the required privileges."
        return 1
    fi

    ui_action "Importing backup from ${BACKUP_FILE}..."
    if ! dc exec -T db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"; then
        ui_err "Failed to import database from backup file — the database was dropped but the new dump did not load cleanly."
        return 1
    fi

    if ! verify_tables_created "$DB_NAME" "$DB_USER" "$DB_PASS"; then
        ui_warn "Import finished, but the 'setting' table was not found afterwards — verify the dump matches this bot's schema."
    fi

    ui_ok "Database successfully imported from ${BACKUP_FILE}."
}

detect_cpu_cores() {
    nproc 2>/dev/null || getconf _NPROCESSORS_ONLN 2>/dev/null || printf '1'
}

detect_ram_mb() {
    free -m 2>/dev/null | awk '/^Mem:/{print $2}'
}

collect_bot_db_creds() {
    names=("main")
    db_names=()
    db_users=()
    db_passes=()

    if require_env_db_creds; then
        db_names+=("$DB_NAME")
        db_users+=("$DB_USER")
        db_passes+=("$DB_PASS")
    else
        db_names+=("")
        db_users+=("")
        db_passes+=("")
    fi

    if [ -d "$BOTS_DIR" ]; then
        local d bn
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            bn=$(basename "$d")
            names+=("$bn")
            db_names+=("$(grep -E '^DB_NAME=' "${d}.env" 2>/dev/null | tail -1 | cut -d'=' -f2-)")
            db_users+=("$(grep -E '^DB_USER=' "${d}.env" 2>/dev/null | tail -1 | cut -d'=' -f2-)")
            db_passes+=("$(grep -E '^DB_PASS=' "${d}.env" 2>/dev/null | tail -1 | cut -d'=' -f2-)")
        done
    fi
}

count_db_users_total() {
    local total=0 i cnt mysql_root_pass
    mysql_root_pass=$(env_get MYSQL_ROOT_PASSWORD)
    for ((i = 0; i < ${#db_names[@]}; i++)); do
        [ -z "${db_names[$i]}" ] && continue
        cnt=$(dc exec -T db mysql -uroot -p"${mysql_root_pass}" -N -e \
            "SELECT COUNT(*) FROM \`${db_names[$i]}\`.user;" 2>/dev/null | tr -d '\r')
        [[ "$cnt" =~ ^[0-9]+$ ]] && total=$((total + cnt))
    done
    printf '%d' "$total"
}

compute_max_connections() {
    local ram_mb="$1" cores="$2" bot_count="$3" user_count="$4"
    local base=64
    local per_core=$((cores * 10))
    local per_bot=$((bot_count * 15))
    local per_1k_users=$(((user_count / 1000) * 5))
    local demand=$((base + per_core + per_bot + per_1k_users))

    local ram_cap=$((ram_mb / 4))
    [ "$ram_cap" -lt 50 ] && ram_cap=50

    local result="$demand"
    [ "$result" -gt "$ram_cap" ] && result="$ram_cap"
    [ "$result" -lt 100 ] && result=100
    [ "$result" -gt 1000 ] && result=1000

    printf '%d' "$result"
}

write_mysql_conf() {
    local max_conn="$1"
    local dir="${PROJECT_DIR}/docker/mysql/conf.d"
    local file="${dir}/faoxima.cnf"
    mkdir -p "$dir" || return 1
    cat > "$file" <<EOF
[mysqld]
max_connections = ${max_conn}
wait_timeout = 180
interactive_timeout = 180
EOF
}

current_max_connections() {
    local mysql_root_pass
    mysql_root_pass=$(env_get MYSQL_ROOT_PASSWORD)
    dc exec -T db mysql -uroot -p"${mysql_root_pass}" -N -e \
        "SHOW VARIABLES LIKE 'max_connections';" 2>/dev/null | awk '{print $2}' | tr -d '\r'
}

set_max_connections() {
    show_logo
    ui_panel "SET MAX DATABASE CONNECTIONS" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Manually set MySQL's max_connections limit.${C_RESET}" \
        "${C_DIM}Use 'Optimize Database & Server' for an automatic, server-aware value instead.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    ui_action "Reading the current value from MySQL..."
    local current
    current=$(current_max_connections)
    [ -z "$current" ] && current="unknown"
    ui_status_table "Current Setting" "$C_CYAN" \
        "max_connections|${C_WHITE}${current}${C_RESET}"

    local new_value
    printf '\n  %s❯%s Enter the new max_connections value (100-1000): ' "$C_YELLOW" "$C_RESET"
    read -r new_value
    if ! [[ "$new_value" =~ ^[0-9]+$ ]] || [ "$new_value" -lt 100 ] || [ "$new_value" -gt 1000 ]; then
        ui_err "Invalid value. Please enter a number between 100 and 1000."
        return 1
    fi

    ui_action "Writing docker/mysql/conf.d/faoxima.cnf..."
    if ! write_mysql_conf "$new_value"; then
        ui_err "Failed to write the MySQL configuration file."
        return 1
    fi

    ui_action "Applying the new setting (this may briefly restart the database)..."
    if ! dc up -d --force-recreate db; then
        ui_err "Failed to apply the MySQL configuration."
        return 1
    fi

    ui_action "Waiting for MySQL to come back up..."
    local wait_ok=0 i
    for i in {1..30}; do
        dc exec -T db mysqladmin ping -uroot -p"$(env_get MYSQL_ROOT_PASSWORD)" >/dev/null 2>&1 && { wait_ok=1; break; }
        sleep 1
    done
    [ "$wait_ok" -eq 0 ] && ui_warn "MySQL did not report healthy within 30s — checking the applied value anyway."

    local applied
    applied=$(current_max_connections)
    if [ "$applied" = "$new_value" ]; then
        ui_ok "max_connections changed from ${current} to ${applied}."
    else
        ui_err "max_connections is still ${applied:-unknown} (expected ${new_value}). The container may not have restarted — check 'dc logs db'."
    fi
}

redis_service_exists() {
    grep -qE '^\s{2}redis:' "$COMPOSE_FILE" 2>/dev/null
}

write_redis_conf() {
    local maxmemory_mb="$1"
    local dir="${PROJECT_DIR}/docker/redis"
    local file="${dir}/redis.conf"
    mkdir -p "$dir" || return 1
    cat > "$file" <<EOF
maxmemory ${maxmemory_mb}mb
maxmemory-policy allkeys-lru
save ""
appendonly no
EOF
}

set_config_php_var() {
    local var="$1" value="$2"
    local file="${PROJECT_DIR}/config.php"
    [ -f "$file" ] || return 1
    if grep -qE "^\\\$${var}[[:space:]]*=" "$file"; then
        sed -i -E \
            -e "s/^(\\\$${var}[[:space:]]*=[[:space:]]*)['\"][^'\"]*['\"](;.*)\$/\\1'${value}'\\2/" \
            "$file"
    fi
}

install_redis() {
    show_logo
    ui_panel "INSTALL / ENABLE REDIS" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Adds a Redis cache service to the Docker stack and enables the${C_RESET}" \
        "${C_DIM}phpredis extension in the app image. Redis here is cache-only —${C_RESET}" \
        "${C_DIM}no persistence, safe to lose on restart.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    if redis_service_exists; then
        ui_ok "Redis service already present in docker-compose.yml."
    else
        ui_err "docker-compose.yml does not have a 'redis' service defined. Please update docker-compose.yml to include the redis service (see docs), then re-run this option."
        return 1
    fi

    ui_action "Writing default docker/redis/redis.conf..."
    if ! write_redis_conf 64; then
        ui_err "Failed to write the Redis configuration file."
        return 1
    fi

    ui_action "Rebuilding the app image with the redis extension enabled..."
    if ! dc build app; then
        ui_err "Failed to rebuild the app image with ext-redis."
        return 1
    fi

    ui_action "Starting redis and refreshing the app container..."
    if ! dc up -d --force-recreate redis app; then
        ui_err "Failed to start the redis service."
        return 1
    fi

    ui_action "Reloading nginx so it picks up the new app container IP..."
    dc restart nginx || ui_err "nginx failed to restart — the site may 502 until you run 'docker compose restart nginx' manually."

    ui_action "Waiting for Redis to come up..."
    local wait_ok=0 i
    for i in {1..30}; do
        if dc exec -T redis redis-cli ping 2>/dev/null | grep -q PONG; then
            wait_ok=1
            break
        fi
        sleep 1
    done
    if [ "$wait_ok" -eq 0 ]; then
        ui_err "Redis did not respond to PING within 30s — check 'dc logs redis'."
        return 1
    fi
    ui_ok "Redis is up and responding to PING."

    ui_action "Writing Redis connection settings to config.php..."
    set_config_php_var "redis_host" "redis"
    set_config_php_var "redis_port" "6379"
    set_config_php_var "redis_password" ""
    set_config_php_var "redis_database" "0"

    ui_ok "Redis installation complete. Enable it via the bot's ⚙️ Feature Status ← 🧠 Redis Status menu."
}

optimize_database() {
    show_logo
    ui_panel "OPTIMIZE DATABASE & SERVER" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Automatically tunes MySQL and PHP based on detected server load,${C_RESET}" \
        "${C_DIM}CPU/RAM, number of bots, databases, and users. Aims to prevent${C_RESET}" \
        "${C_DIM}'Too many connections' / PDO connection errors.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    ui_action "Detecting server resources..."
    local cores ram_mb
    cores=$(detect_cpu_cores)
    ram_mb=$(detect_ram_mb)
    [ -z "$ram_mb" ] && ram_mb=1024

    local names db_names db_users db_passes
    collect_bot_db_creds
    local bot_count="${#names[@]}"
    local db_count=0 i
    for ((i = 0; i < ${#db_names[@]}; i++)); do
        [ -n "${db_names[$i]}" ] && db_count=$((db_count + 1))
    done

    ui_action "Counting user records across ${db_count} database(s)..."
    local user_count
    user_count=$(count_db_users_total)

    ui_info "Detected: ${cores} CPU core(s), ${ram_mb}MB RAM, ${bot_count} bot(s), ${db_count} database(s), ${user_count} total user record(s)."

    local current_conn
    current_conn=$(current_max_connections)
    [ -z "$current_conn" ] && current_conn="unknown"

    local max_conn
    max_conn=$(compute_max_connections "$ram_mb" "$cores" "$bot_count" "$user_count")
    ui_info "Current MySQL max_connections = ${current_conn}."
    ui_info "Computed MySQL max_connections = ${max_conn} (heuristic based on the above detection — not a guarantee for every traffic pattern)."

    ui_action "Writing MySQL tuning to docker/mysql/conf.d/faoxima.cnf..."
    if ! write_mysql_conf "$max_conn"; then
        ui_err "Failed to write the MySQL configuration file."
        return 1
    fi

    if ! grep -qF "./docker/mysql/conf.d:/etc/mysql/conf.d:ro" "$COMPOSE_FILE"; then
        ui_warn "docker-compose.yml does not yet mount docker/mysql/conf.d — add '- ./docker/mysql/conf.d:/etc/mysql/conf.d:ro' under the db service's volumes, then re-run this option."
        return 1
    fi

    ui_action "Applying MySQL configuration (this may briefly restart the database)..."
    if ! dc up -d --force-recreate db; then
        ui_err "Failed to apply the MySQL configuration."
        return 1
    fi

    ui_action "Waiting for MySQL to come back up..."
    local wait_ok=0 wi
    for wi in {1..30}; do
        dc exec -T db mysqladmin ping -uroot -p"$(env_get MYSQL_ROOT_PASSWORD)" >/dev/null 2>&1 && { wait_ok=1; break; }
        sleep 1
    done
    [ "$wait_ok" -eq 0 ] && ui_warn "MySQL did not report healthy within 30s — checking the applied value anyway."

    local applied_conn
    applied_conn=$(current_max_connections)
    if [ "$applied_conn" = "$max_conn" ]; then
        ui_ok "MySQL max_connections changed from ${current_conn} to ${applied_conn}."
    else
        ui_err "max_connections is still ${applied_conn:-unknown} (expected ${max_conn}). The container may not have restarted — check 'dc logs db'."
    fi

    local mem_mb=$((ram_mb / (cores * 4)))
    [ "$mem_mb" -lt 128 ] && mem_mb=128
    [ "$mem_mb" -gt 512 ] && mem_mb=512

    local ini="${PROJECT_DIR}/docker/php/conf.d/faoxima.ini"
    if [ -f "$ini" ]; then
        cp "$ini" "${ini}.bak" 2>/dev/null || true
        if faoxima_set_ini "memory_limit" "${mem_mb}M" "$ini"; then
            ui_action "Rebuilding the app image with the new PHP memory_limit..."
            if dc build app && dc up -d app; then
                ui_ok "PHP memory_limit set to ${mem_mb}M."
                if [ -d "$BOTS_DIR" ]; then
                    local d bn
                    for d in "${BOTS_DIR}"/*/; do
                        [ -d "$d" ] || continue
                        bn=$(basename "$d")
                        dc up -d --no-deps "app_${bn}" || ui_warn "Failed to refresh 'app_${bn}' with the new image."
                    done
                fi
            else
                ui_err "Failed to rebuild/restart the app image — MySQL tuning was still applied."
            fi
        else
            ui_err "Failed to update memory_limit in ${ini}."
        fi
    fi

    ui_warn "PHP-FPM pool tuning (pm.max_children) was intentionally skipped — no pool configuration file exists in this deployment yet."

    if redis_service_exists; then
        local redis_mem_mb=$((ram_mb / 8))
        [ "$redis_mem_mb" -lt 32 ] && redis_mem_mb=32
        [ "$redis_mem_mb" -gt 256 ] && redis_mem_mb=256

        ui_action "Writing Redis tuning (maxmemory=${redis_mem_mb}mb) to docker/redis/redis.conf..."
        if write_redis_conf "$redis_mem_mb"; then
            ui_action "Applying Redis configuration (this may briefly restart Redis)..."
            if dc up -d --force-recreate redis; then
                local redis_wait_ok=0 ri
                for ri in {1..30}; do
                    if dc exec -T redis redis-cli ping 2>/dev/null | grep -q PONG; then
                        redis_wait_ok=1
                        break
                    fi
                    sleep 1
                done
                if [ "$redis_wait_ok" -eq 1 ]; then
                    ui_ok "Redis maxmemory set to ${redis_mem_mb}mb (allkeys-lru eviction)."
                else
                    ui_warn "Redis did not report healthy within 30s after retuning — check 'dc logs redis'."
                fi
            else
                ui_err "Failed to apply the Redis configuration."
            fi
        else
            ui_err "Failed to write the Redis configuration file."
        fi
    else
        ui_info "Redis is not installed — skipping Redis tuning. Use Database ← Install/Enable Redis to add it."
    fi

    ui_ok "Optimization finished. Verify cron/shell_exec still work with: dc exec app sh -c 'command -v cron && pgrep cron'"
}

renew_ssl() {
    show_logo
    ui_panel "RENEW SSL CERTIFICATES" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Runs the certbot sidecar's renewal, then reloads nginx — no downtime.${C_RESET}" \
        "${C_DIM}All bots (main + additional) share one certificate for this domain, so this covers all of them.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    if dc run --rm --entrypoint certbot certbot renew; then
        ui_ok "SSL certificates successfully renewed."
        dc exec nginx nginx -s reload && ui_ok "nginx reloaded." || ui_warn "Failed to reload nginx — check manually."
        rm -f "${CACHE_DIR}/cert_enddate" 2>/dev/null
        return 0
    fi

    ui_warn "Renewal via the webroot method failed — port 80 may be blocked by something other than our own nginx. Retrying by stopping nginx and binding port 80 directly..."
    dc stop nginx || { ui_err "Failed to stop nginx for the standalone renewal attempt."; return 1; }

    if dc run --rm -p 80:80 --entrypoint certbot certbot renew --standalone; then
        ui_ok "SSL certificates successfully renewed (standalone)."
        dc start nginx && ui_ok "nginx restarted." || ui_err "Renewal succeeded but nginx failed to restart — start it manually with 'docker compose start nginx'."
        rm -f "${CACHE_DIR}/cert_enddate" 2>/dev/null
    else
        ui_err "SSL renewal failed even in standalone mode. Please check 'docker compose logs certbot' for details."
        dc start nginx || ui_err "nginx also failed to restart — start it manually with 'docker compose start nginx'."
        return 1
    fi
}

ssl_auto_renew_check() {
    if [ ! -f "$ENV_FILE" ] || [ ! -f "$COMPOSE_FILE" ]; then
        exit 0
    fi

    local domain
    domain=$(normalize_domain "$(env_get DOMAIN)")
    [ -z "$domain" ] && exit 0

    local cert_enddate
    cert_enddate=$(get_cert_enddate "$domain")
    [ -z "$cert_enddate" ] && exit 0

    local expiry_ts now_ts days_left
    expiry_ts=$(date -d "$cert_enddate" +%s 2>/dev/null || echo 0)
    [ "$expiry_ts" -le 0 ] && exit 0
    now_ts=$(date +%s)
    days_left=$(( (expiry_ts - now_ts) / 86400 ))

    if [ "$days_left" -lt 1 ]; then
        renew_ssl
    fi
}

enable_ssl_auto_renew() {
    show_logo
    ui_panel "ENABLE SSL AUTO-RENEWAL" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Installs a daily cron job that checks every domain's certificate.${C_RESET}" \
        "${C_DIM}If less than 1 day of validity remains, renewal runs automatically.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    local already_enabled
    already_enabled=$(env_get SSL_AUTO_RENEW)
    if [ "$already_enabled" = "1" ]; then
        ui_info "Automatic SSL renewal is already enabled."
        return 0
    fi

    local cron_line="0 3 * * * ${INSTALL_SCRIPT_LINK} --ssl-auto-renew-check >/dev/null 2>&1"
    if ! (crontab -l 2>/dev/null | grep -qF "$cron_line"); then
        (crontab -l 2>/dev/null; printf '%s\n' "$cron_line") | crontab - || {
            ui_err "Failed to install the auto-renewal cron job."
            return 1
        }
    fi

    env_set "SSL_AUTO_RENEW" "1"
    ui_ok "Automatic SSL renewal enabled — certificates will be checked daily and renewed automatically when less than 1 day remains."
}

change_domain() {
    show_logo
    ui_panel "CHANGE DOMAIN" "$C_BOLD$C_BLUE" "$C_BLUE" \
        "${C_WHITE}Migrate the main bot and ALL additional bots to a new domain.${C_RESET}" \
        "${C_DIM}Issues a new shared SSL cert and updates the webhook for every bot.${C_RESET}"

    if [ ! -f "$ENV_FILE" ]; then
        ui_err "Faoxima Bot is not installed (${ENV_FILE} not found)."
        return 1
    fi

    local new_domain
    while [[ ! "$new_domain" =~ ^[a-zA-Z0-9.-]+$ ]]; do
        printf '  %s❯%s Enter new domain: ' "$C_YELLOW" "$C_RESET"
        read -r new_domain
        [[ ! "$new_domain" =~ ^[a-zA-Z0-9.-]+$ ]] && ui_err "Invalid domain format"
    done
    local entered_domain="$new_domain"
    new_domain=$(normalize_domain "$new_domain")
    if [ "$entered_domain" != "$new_domain" ]; then
        ui_info "Domain normalized to lowercase: ${new_domain}"
    fi

    local old_domain
    old_domain=$(normalize_domain "$(env_get DOMAIN)")
    env_set "DOMAIN" "$new_domain"

    if [ -f "${PROJECT_DIR}/config.php" ]; then
        sed -i -E \
            -e 's/^(\$domainhosts[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"'${new_domain}'"'\2/' \
            "${PROJECT_DIR}/config.php" || ui_warn "Failed to update \$domainhosts in ${PROJECT_DIR}/config.php."
    fi

    ui_action "Generating a temporary self-signed certificate for ${new_domain} so nginx can reload..."
    ensure_dummy_cert "$new_domain" || ui_warn "Could not generate a temporary certificate — nginx reload below may fail until SSL is issued."

    ui_action "Rendering the nginx vhost for ${new_domain}..."
    render_vhost "$new_domain" "${NGINX_CONF_DIR}/00-main.conf" || {
        ui_err "Failed to render the nginx vhost for ${new_domain}."
        env_set "DOMAIN" "$old_domain"
        return 1
    }

    ui_action "Reloading nginx with the new domain..."
    local nginx_test_output
    nginx_test_output=$(dc exec -T nginx nginx -t 2>&1)
    if [ $? -ne 0 ]; then
        ui_err "The rendered nginx config for ${new_domain} is invalid — nginx will keep serving the OLD domain until this is fixed."
        printf '%s\n' "$nginx_test_output"
        env_set "DOMAIN" "$old_domain"
        return 1
    fi
    dc exec nginx nginx -s reload || { log_error "Failed to reload nginx for ${new_domain}."; return 1; }

    discard_dummy_cert "$new_domain"

    ui_action "Requesting SSL certificate for ${new_domain}..."
    if ! issue_certificate "$new_domain"; then
        log_error "SSL configuration failed for ${new_domain}."
        diagnose_ssl_failure "$new_domain"
        env_set "DOMAIN" "$old_domain"
        render_vhost "$old_domain" "${NGINX_CONF_DIR}/00-main.conf" 2>/dev/null
        dc exec nginx nginx -s reload 2>/dev/null || true
        return 1
    fi

    local fresh_cert_enddate
    fresh_cert_enddate=$(get_cert_enddate "$new_domain")
    [ -n "$fresh_cert_enddate" ] && cache_set "cert_enddate" "$fresh_cert_enddate"

    local bot_token webhook_url webhook_response
    bot_token=$(env_get TELEGRAM_BOT_TOKEN)
    webhook_url="https://${new_domain}/faoxima/index.php"
    local secret_token
    secret_token=$(printf '%s' "${bot_token}_faoxima_webhook_secret" | sha256sum | awk '{print $1}')
    webhook_response=$(curl -s -X POST "https://api.telegram.org/bot${bot_token}/setWebhook" -F "url=${webhook_url}" -F "secret_token=${secret_token}")
    if echo "$webhook_response" | grep -q '"ok":true'; then
        log_info "Telegram webhook updated successfully for ${new_domain}."
    else
        log_warn "Webhook update returned a warning: ${webhook_response}"
    fi

    local attempt http_status=""
    for attempt in {1..5}; do
        http_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$webhook_url")
        [[ "$http_status" =~ ^(200|301|302)$ ]] && break
        log_warn "Endpoint ${webhook_url} not ready yet (HTTP ${http_status:-000}). Retrying in 3 seconds..."
        sleep 3
    done

    if [[ "$http_status" =~ ^(200|301|302)$ ]]; then
        log_info "Domain successfully migrated to ${new_domain}."
        ui_ok "Domain updated to ${new_domain}."

        if [ -d "$BOTS_DIR" ]; then
            local bot_ok=() bot_failed=()
            local d name bot_token_extra bot_webhook_url bot_webhook_response bot_status bot_secret_token
            for d in "${BOTS_DIR}"/*/; do
                [ -f "${d}.env" ] || continue
                name=$(basename "$d")
                if ! sed -i "s|^DOMAIN=.*|DOMAIN=${new_domain}|" "${d}.env"; then
                    ui_warn "Failed to update DOMAIN in ${d}.env for '${name}'."
                    bot_failed+=("$name")
                    continue
                fi
                if [ -f "${d}config.php" ]; then
                    sed -i -E \
                        -e 's/^(\$domainhosts[[:space:]]*=[[:space:]]*)['"'"'"][^'"'"'"]*['"'"'"](;.*)$/\1'"'${new_domain}'"'\2/' \
                        "${d}config.php" || ui_warn "Failed to update \$domainhosts in ${d}config.php for '${name}'."
                fi
                bot_token_extra=$(grep -E '^TELEGRAM_BOT_TOKEN=' "${d}.env" | tail -1 | cut -d'=' -f2-)
                bot_webhook_url="https://${new_domain}/${name}/index.php"
                if [ -n "$bot_token_extra" ]; then
                    bot_secret_token=$(printf '%s' "${bot_token_extra}_faoxima_webhook_secret" | sha256sum | awk '{print $1}')
                    bot_webhook_response=$(curl -s -F "url=${bot_webhook_url}" -F "secret_token=${bot_secret_token}" "https://api.telegram.org/bot${bot_token_extra}/setWebhook")
                    if ! echo "$bot_webhook_response" | grep -q '"ok":true'; then
                        ui_warn "Failed to update webhook for additional bot '${name}': ${bot_webhook_response}"
                        bot_failed+=("$name")
                        continue
                    fi
                fi
                bot_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$bot_webhook_url")
                if [[ "$bot_status" =~ ^(200|301|302)$ ]]; then
                    bot_ok+=("$name")
                else
                    ui_warn "Additional bot '${name}' did not answer at ${bot_webhook_url} (HTTP ${bot_status:-000})."
                    bot_failed+=("$name")
                fi
            done

            if [ "${#bot_ok[@]}" -gt 0 ]; then
                ui_ok "Additional bots verified working on ${new_domain}: ${bot_ok[*]}"
            fi
            if [ "${#bot_failed[@]}" -gt 0 ]; then
                ui_err "Additional bots that failed verification: ${bot_failed[*]} — check them individually."
            fi
        fi

        if [ -n "$old_domain" ] && [ "$old_domain" != "$new_domain" ]; then
            local delete_old_cert
            printf '  %s❯%s Delete old SSL certificate for %s? (y/n): ' "$C_YELLOW" "$C_RESET" "$old_domain"
            read -r delete_old_cert
            if [[ "$delete_old_cert" =~ ^[Yy]$ ]]; then
                dc run --rm --entrypoint certbot certbot delete --cert-name "$old_domain" 2>/dev/null \
                    || ui_warn "Failed to delete certificate for ${old_domain}."
            fi
        fi
    else
        log_warn "Final verification failed for ${webhook_url} (HTTP ${http_status:-000})."
        ui_err "Domain changed in .env, but the endpoint did not answer yet — check DNS/propagation."
        return 1
    fi
}

container_has_error_logs() {
    local service="$1"
    dc logs --tail=200 "$service" 2>&1 \
        | grep -viE '"ok":(true|false)' \
        | grep -vE '"[A-Z]+ [^"]*" [0-9]{3}$' \
        | grep -qiE 'error|exception|fatal|failed|warn|emerg|crit|panic'
}

view_error_logs() {
    local page=0
    local page_size=20
    local -A hidden_from_view=()

    while true; do
        show_logo
        ui_panel "VIEW ERROR LOGS" "$C_BOLD$C_GREEN" "$C_GREEN" \
            "${C_WHITE}Pick a log to view — nothing is shown automatically.${C_RESET}" \
            "${C_DIM}Scans ${PROJECT_DIR} and ${BOTS_DIR} for app-level log files, plus bot containers that have logged an error.${C_RESET}"

        local bot_names=() d
        if [ -d "$BOTS_DIR" ]; then
            for d in "${BOTS_DIR}"/*/; do
                [ -d "$d" ] || continue
                bot_names+=("$(basename "$d")")
            done
        fi
        local bot_total="${#bot_names[@]}"
        local bot_page_size=18
        local bot_last_page=$(( (bot_total - 1) / bot_page_size ))
        [ "$bot_last_page" -lt 0 ] && bot_last_page=0
        [ "$page" -gt "$bot_last_page" ] && page="$bot_last_page"

        local KINDS=() PATHS=()
        if [ -f "$COMPOSE_FILE" ]; then
            [ -z "${hidden_from_view[container|nginx]:-}" ] && container_has_error_logs nginx && { KINDS+=("container"); PATHS+=("nginx"); }
            [ -z "${hidden_from_view[container|app]:-}" ] && container_has_error_logs app && { KINDS+=("container"); PATHS+=("app"); }
        fi
        if [ "$bot_total" -gt 0 ]; then
            local bstart=$((page * bot_page_size))
            local bend=$((bstart + bot_page_size))
            [ "$bend" -gt "$bot_total" ] && bend="$bot_total"
            local bi name
            for ((bi = bstart; bi < bend; bi++)); do
                name="${bot_names[$bi]}"
                [ -z "${hidden_from_view[container|app_${name}]:-}" ] && container_has_error_logs "app_${name}" && { KINDS+=("container"); PATHS+=("app_${name}"); }
            done
        fi

        local LOGS=()
        mapfile -t LOGS < <(find "$PROJECT_DIR" "$BOTS_DIR" -type f \( -name 'error_log' -o -name '*.log' \) 2>/dev/null | sort)
        local f
        for f in "${LOGS[@]}"; do
            [ -n "${hidden_from_view[file|${f}]:-}" ] && continue
            KINDS+=("file")
            PATHS+=("$f")
        done

        if [ "${#PATHS[@]}" -eq 0 ]; then
            if [ "${#hidden_from_view[@]}" -gt 0 ]; then
                ui_ok "No logs to show (some entries are hidden from this session's view)."
            elif [ "$bot_total" -eq 0 ]; then
                ui_ok "No logs found (no on-disk log files, and no compose stack installed)."
            else
                ui_ok "No logs found (no on-disk log files, and no bot containers currently have errors)."
            fi
            return 0
        fi

        if [ "$bot_last_page" -gt 0 ]; then
            ui_info "Checking additional bots' containers page $((page + 1)) of $((bot_last_page + 1)) (nginx/app + on-disk logs are always shown in full)."
        fi

        ui_ok "Found ${#PATHS[@]} log source(s) on this view:"
        printf '\n'
        local idx size
        for ((idx = 0; idx < ${#PATHS[@]}; idx++)); do
            if [ "${KINDS[$idx]}" = "container" ]; then
                printf '  %s%2d)%s [container] %s\n' "$C_YELLOW" "$((idx + 1))" "$C_RESET" "${PATHS[$idx]}"
            else
                size=$(du -h "${PATHS[$idx]}" 2>/dev/null | awk '{print $1}')
                printf '  %s%2d)%s %s  %s(%s)%s\n' "$C_YELLOW" "$((idx + 1))" "$C_RESET" "${PATHS[$idx]}" "$C_DIM" "${size:-?}" "$C_RESET"
            fi
        done
        local has_deletable_file=0 ci
        for ((ci = 0; ci < ${#KINDS[@]}; ci++)); do
            [ "${KINDS[$ci]}" = "file" ] && { has_deletable_file=1; break; }
        done

        local delete_option=0
        local max_option="${#PATHS[@]}"
        if [ "$has_deletable_file" -eq 1 ]; then
            delete_option=$(( ${#PATHS[@]} + 1 ))
            max_option="$delete_option"
            printf '  %s%2d)%s %sDelete a log%s\n' "$C_RED" "$delete_option" "$C_RESET" "$C_RED" "$C_RESET"
        fi

        if [ "$bot_last_page" -gt 0 ]; then
            printf '\n  %s(bot container page %d of %d)%s\n' "$C_DIM" "$((page + 1))" "$((bot_last_page + 1))" "$C_RESET"
        fi
        local nav_hint=""
        [ "$bot_last_page" -gt 0 ] && [ "$page" -lt "$bot_last_page" ] && nav_hint="${nav_hint}n) next bot page  "
        [ "$bot_last_page" -gt 0 ] && [ "$page" -gt 0 ] && nav_hint="${nav_hint}p) previous bot page  "

        printf '\n  %s❯%s Select an option [1-%d], %sor Enter to return to main menu: ' \
            "$C_YELLOW" "$C_RESET" "$max_option" "$nav_hint"
        local choice; read -r choice
        [ -z "$choice" ] && return 0

        case "$choice" in
            n|N)
                [ "$page" -lt "$bot_last_page" ] && page=$((page + 1))
                continue
                ;;
            p|P)
                [ "$page" -gt 0 ] && page=$((page - 1))
                continue
                ;;
        esac

        if [[ ! "$choice" =~ ^[0-9]+$ ]] || [ "$choice" -lt 1 ] || [ "$choice" -gt "$max_option" ]; then
            ui_err "Invalid selection."
            printf '\n  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"; read -r
            continue
        fi

        if [ "$delete_option" -gt 0 ] && [ "$choice" -eq "$delete_option" ]; then
            delete_error_logs KINDS PATHS
            printf '\n  %s❯%s Press Enter to return to the log list... ' "$C_YELLOW" "$C_RESET"; read -r
            continue
        fi

        local sel=$((choice - 1))
        local sel_kind="${KINDS[$sel]}" sel_path="${PATHS[$sel]}"
        ui_rule
        if [ "$sel_kind" = "container" ]; then
            printf '  %s● %s (last 200 lines)%s\n' "$C_CYAN" "$sel_path" "$C_RESET"
            ui_rule
            dc logs --tail=200 "$sel_path" 2>/dev/null || ui_err "Could not read ${sel_path} logs."
        else
            printf '  %s● %s%s  %s(last 200 lines)%s\n' "$C_CYAN" "$sel_path" "$C_RESET" "$C_DIM" "$C_RESET"
            ui_rule
            tail -n 200 "$sel_path" 2>/dev/null || ui_err "Could not read ${sel_path}."
        fi

        printf '\n  %s❯%s 🗑️  Hide this entry from the current view? (y/N): ' "$C_YELLOW" "$C_RESET"
        local hide_confirm; read -r hide_confirm
        if [[ "${hide_confirm,,}" == "y" ]]; then
            hidden_from_view["${sel_kind}|${sel_path}"]=1
            ui_ok "The log entry was removed from the current view. The original log source remains unchanged."
        fi

        printf '\n  %s❯%s Press Enter to return to the log list... ' "$C_YELLOW" "$C_RESET"; read -r
    done
}

delete_error_logs() {
    local -n kinds_ref="$1" paths_ref="$2"
    local file_idx=() i
    for ((i = 0; i < ${#paths_ref[@]}; i++)); do
        [ "${kinds_ref[$i]}" = "file" ] && file_idx+=("$i")
    done

    if [ "${#file_idx[@]}" -eq 0 ]; then
        ui_warn "No on-disk log files to delete (container logs can't be deleted, only viewed)."
        return 0
    fi

    local total="${#file_idx[@]}"
    local page_size=20
    local page=0
    local last_page=$(( (total - 1) / page_size ))

    while true; do
        local start=$((page * page_size))
        local end=$((start + page_size))
        [ "$end" -gt "$total" ] && end="$total"

        printf '\n'
        local j i
        for ((j = start; j < end; j++)); do
            i="${file_idx[$j]}"
            printf '  %s%2d)%s %s\n' "$C_YELLOW" "$((i + 1))" "$C_RESET" "${paths_ref[$i]}"
        done

        local nav_hint=""
        if [ "$last_page" -gt 0 ]; then
            printf '\n  %s(page %d of %d)%s\n' "$C_DIM" "$((page + 1))" "$((last_page + 1))" "$C_RESET"
            [ "$page" -lt "$last_page" ] && nav_hint="${nav_hint}n) next page  "
            [ "$page" -gt 0 ] && nav_hint="${nav_hint}p) previous page  "
        fi

        printf '\n  %s❯%s Which number to delete (%sEnter to cancel): ' "$C_YELLOW" "$C_RESET" "$nav_hint"
        local pick; read -r pick
        [ -z "$pick" ] && { ui_info "Cancelled."; return 0; }

        case "$pick" in
            n|N)
                [ "$page" -lt "$last_page" ] && page=$((page + 1))
                continue
                ;;
            p|P)
                [ "$page" -gt 0 ] && page=$((page - 1))
                continue
                ;;
        esac

        if [[ ! "$pick" =~ ^[0-9]+$ ]]; then
            ui_err "Invalid selection."
            return 1
        fi
        local sel=$((pick - 1))
        if [ "$sel" -lt 0 ] || [ "$sel" -ge "${#paths_ref[@]}" ] || [ "${kinds_ref[$sel]}" != "file" ]; then
            ui_err "Invalid selection."
            return 1
        fi

        printf '  %s❯%s Delete %s? (y/N): ' "$C_YELLOW" "$C_RESET" "${paths_ref[$sel]}"
        local confirm; read -r confirm
        if [[ "${confirm,,}" == "y" ]]; then
            rm -f "${paths_ref[$sel]}" 2>/dev/null && ui_ok "Deleted ${paths_ref[$sel]}" || ui_warn "Could not delete ${paths_ref[$sel]}."
        fi
        return 0
    done
}

faoxima_set_ini() {
    local key="$1" val="$2" file="$3"
    if grep -qE "^[[:space:]]*;?[[:space:]]*${key}[[:space:]]*=" "$file"; then
        sed -i -E "s|^[[:space:]]*;?[[:space:]]*${key}[[:space:]]*=.*|${key} = ${val}|" "$file" || return 1
    else
        printf '%s = %s\n' "$key" "$val" >> "$file" || return 1
    fi
    grep -qE "^${key}[[:space:]]*=[[:space:]]*${val}$" "$file"
}

read_ini_value() {
    local key="$1" file="$2"
    grep -E "^${key}[[:space:]]*=" "$file" 2>/dev/null | tail -1 | sed -E "s/^${key}[[:space:]]*=[[:space:]]*//"
}

increase_upload_limit() {
    show_logo
    ui_panel "INCREASE UPLOAD LIMIT" "$C_BOLD$C_GREEN" "$C_GREEN" \
        "${C_WHITE}Raises upload size limits for both the bot itself and phpMyAdmin.${C_RESET}" \
        "${C_DIM}Requires rebuilding the app image and recreating phpMyAdmin — brief restart.${C_RESET}"

    local ini="${PROJECT_DIR}/docker/php/conf.d/faoxima.ini"
    local current_bot_limit="unknown"
    [ -f "$ini" ] && current_bot_limit=$(read_ini_value "upload_max_filesize" "$ini")
    local current_pma_limit
    current_pma_limit=$(env_get PMA_UPLOAD_LIMIT)
    [ -z "$current_pma_limit" ] && current_pma_limit="50M"

    ui_status_table "Current Upload Limits" "$C_CYAN" \
        "Bot (Telegram uploads)|${C_WHITE}${current_bot_limit}${C_RESET}" \
        "phpMyAdmin (SQL import)|${C_WHITE}${current_pma_limit}${C_RESET}"

    local size_mb
    printf '\n  %s❯%s Enter the new max upload size in MB for BOTH (e.g. 100): ' "$C_YELLOW" "$C_RESET"
    read -r size_mb
    if ! [[ "$size_mb" =~ ^[0-9]+$ ]] || [ "$size_mb" -lt 1 ]; then
        ui_err "Invalid number. Please enter a positive integer (MB)."
        return 1
    fi
    local post_mb=$(( size_mb + 16 ))
    local mem_mb=$(( post_mb + 64 ))

    if [ ! -f "$ini" ]; then
        ui_err "faoxima.ini not found at ${ini}."
        return 1
    fi
    cp "$ini" "${ini}.bak" 2>/dev/null || true
    if ! faoxima_set_ini "upload_max_filesize" "${size_mb}M" "$ini" \
        || ! faoxima_set_ini "post_max_size"       "${post_mb}M" "$ini" \
        || ! faoxima_set_ini "memory_limit"        "${mem_mb}M"  "$ini" \
        || ! faoxima_set_ini "max_execution_time"  "600"         "$ini" \
        || ! faoxima_set_ini "max_input_time"      "600"         "$ini"; then
        ui_err "Failed to update one or more settings in ${ini}."
        return 1
    fi
    ui_ok "Updated ${ini}"

    env_set "PMA_UPLOAD_LIMIT" "${size_mb}M"

    ui_action "Rebuilding the app image and restarting..."
    if ! dc build app && dc up -d app; then
        ui_err "Failed to rebuild/restart the app image."
        return 1
    fi

    ui_action "Recreating phpMyAdmin with the new upload limit..."
    if ! dc up -d --force-recreate --no-deps phpmyadmin; then
        ui_err "Failed to recreate phpMyAdmin — the bot's upload limit was still updated."
        return 1
    fi

    ui_ok "Upload limit set to ${size_mb}M for both the bot (post_max_size ${post_mb}M, memory_limit ${mem_mb}M) and phpMyAdmin."
    ui_tip "You can now upload/import files up to ${size_mb} MB through the bot and phpMyAdmin."
}

file_env_get() {
    local file="$1" key="$2"
    [ -f "$file" ] || return 1
    grep -E "^${key}=" "$file" | tail -1 | cut -d'=' -f2-
}

file_env_set() {
    local file="$1" key="$2" value="$3"
    [ "$key" = "DOMAIN" ] && value=$(normalize_domain "$value")
    local escaped_value
    escaped_value=${value//\\/\\\\}
    escaped_value=${escaped_value//&/\\&}
    escaped_value=${escaped_value//|/\\|}
    if grep -qE "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${escaped_value}|" "$file" || return 1
    else
        printf '%s=%s\n' "$key" "$value" >> "$file" || return 1
    fi
}

read_config_credential() {
    local container="$1" variable="$2"
    dc exec -T "$container" php /var/www/faoxima/docker/php-update-credential.php read "$variable" 2>/dev/null | tr -d '\r'
}

select_bot_for_credentials() {
    local names=("main") d
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            names+=("$(basename "$d")")
        done
    fi

    if ! ui_pick_from_list "Which bot" "${names[@]}"; then
        ui_info "Cancelled."
        return 1
    fi

    SELECTED_BOT="${names[$UI_PICK_RESULT]}"
    if [ "$SELECTED_BOT" = "main" ]; then
        if ! require_env_db_creds; then return 1; fi
        SELECTED_DB_NAME="$DB_NAME"
        SELECTED_DB_USER="$DB_USER"
        SELECTED_DB_PASS="$DB_PASS"
        SELECTED_ENV_FILE="$ENV_FILE"
        SELECTED_CONFIG_PHP="${PROJECT_DIR}/config.php"
        SELECTED_DOMAIN=$(normalize_domain "$(env_get DOMAIN)")
        SELECTED_CONTAINER="app"
    else
        local bot_dir="${BOTS_DIR}/${SELECTED_BOT}"
        if [ ! -f "${bot_dir}/.env" ]; then
            ui_err "Could not find .env for '${SELECTED_BOT}' at ${bot_dir}."
            return 1
        fi
        SELECTED_DB_NAME=$(file_env_get "${bot_dir}/.env" "DB_NAME")
        SELECTED_DB_USER=$(file_env_get "${bot_dir}/.env" "DB_USER")
        SELECTED_DB_PASS=$(file_env_get "${bot_dir}/.env" "DB_PASS")
        SELECTED_ENV_FILE="${bot_dir}/.env"
        SELECTED_CONFIG_PHP="${bot_dir}/config.php"
        SELECTED_DOMAIN=$(file_env_get "${bot_dir}/.env" "DOMAIN")
        SELECTED_CONTAINER="app_${SELECTED_BOT}"
        if [ -z "$SELECTED_DB_NAME" ] || [ -z "$SELECTED_DB_USER" ]; then
            ui_err "Failed to read database credentials for '${SELECTED_BOT}'."
            return 1
        fi
    fi

    local config_user config_pass
    config_user=$(read_config_credential "$SELECTED_CONTAINER" "usernamedb")
    config_pass=$(read_config_credential "$SELECTED_CONTAINER" "passworddb")
    [ -n "$config_user" ] && SELECTED_DB_USER="$config_user"
    [ -n "$config_pass" ] && SELECTED_DB_PASS="$config_pass"
    if [ "$SELECTED_BOT" = "main" ]; then
        file_env_set "$SELECTED_ENV_FILE" "MYSQL_USER" "$SELECTED_DB_USER" || return 1
        file_env_set "$SELECTED_ENV_FILE" "MYSQL_PASSWORD" "$SELECTED_DB_PASS" || return 1
    else
        file_env_set "$SELECTED_ENV_FILE" "DB_USER" "$SELECTED_DB_USER" || return 1
        file_env_set "$SELECTED_ENV_FILE" "DB_PASS" "$SELECTED_DB_PASS" || return 1
    fi
}

show_db_credentials() {
    ui_status_table "Database Info — ${SELECTED_BOT}" "$C_CYAN" \
        "phpMyAdmin|${C_GREEN}https://${SELECTED_DOMAIN}/phpmyadmin/${C_RESET} ${C_DIM}(shared instance — log in with the credentials below)${C_RESET}" \
        "Database|${C_WHITE}${SELECTED_DB_NAME}${C_RESET}" \
        "Username|${C_WHITE}${SELECTED_DB_USER}${C_RESET}" \
        "Password|${C_WHITE}${SELECTED_DB_PASS}${C_RESET}"
}

valid_ipv4_or_cidr() {
    local input="$1" ip prefix o1 o2 o3 o4 extra o
    ip="${input%%/*}"
    if [[ "$input" == */* ]]; then
        prefix="${input#*/}"
        [[ "$prefix" =~ ^[0-9]+$ ]] || return 1
        [ "$prefix" -ge 0 ] && [ "$prefix" -le 32 ] || return 1
    fi
    IFS=. read -r o1 o2 o3 o4 extra <<< "$ip"
    [ -z "$extra" ] || return 1
    for o in "$o1" "$o2" "$o3" "$o4"; do
        [[ "$o" =~ ^[0-9]+$ ]] || return 1
        [ "$o" -ge 0 ] && [ "$o" -le 255 ] || return 1
    done
    return 0
}

get_phpmyadmin_allowed_ips() {
    local ips legacy
    ips=$(env_get PMA_ALLOWED_IPS 2>/dev/null)
    if [ -z "$ips" ]; then
        legacy=$(env_get PMA_ALLOWED_IP 2>/dev/null)
        [ -n "$legacy" ] && ips="$legacy"
    fi
    printf '%s' "$ips" | tr -d '[:space:]'
}

save_phpmyadmin_allowed_ips() {
    local ips="$1"
    env_set "PMA_ALLOWED_IPS" "$ips"
    if grep -qE '^PMA_ALLOWED_IP=' "$ENV_FILE" 2>/dev/null; then
        env_set "PMA_ALLOWED_IP" ""
    fi
}

apply_phpmyadmin_ip_config() {
    local domain nginx_test_output
    domain=$(normalize_domain "$(env_get DOMAIN)")
    if [ -z "$domain" ]; then
        ui_err "DOMAIN could not be read from ${ENV_FILE}."
        return 1
    fi
    render_vhost "$domain" "${NGINX_CONF_DIR}/00-main.conf" || return 1
    nginx_test_output=$(dc exec -T nginx nginx -t 2>&1)
    if [ $? -ne 0 ]; then
        ui_err "nginx configuration test failed."
        printf '%s
' "$nginx_test_output"
        return 1
    fi
    if ! dc exec nginx nginx -s reload; then
        ui_err "Failed to reload nginx."
        return 1
    fi
    return 0
}

list_phpmyadmin_public_ips() {
    local ips ip count=0
    ips=$(get_phpmyadmin_allowed_ips)
    if [ -z "$ips" ]; then
        ui_info "No public IPs are currently allowed for phpMyAdmin."
        return 0
    fi
    printf '
'
    while IFS= read -r ip; do
        [ -z "$ip" ] && continue
        count=$((count + 1))
        printf '  %s%2d)%s %s
' "$C_YELLOW" "$count" "$C_RESET" "$ip"
    done <<EOF
$(printf '%s' "$ips" | tr ',' '
')
EOF
    printf '
'
}

add_phpmyadmin_public_ip() {
    local ips new_ip item new_list
    ips=$(get_phpmyadmin_allowed_ips)
    printf '
  %s❯%s Enter public IPv4 or CIDR: ' "$C_YELLOW" "$C_RESET"
    read -r new_ip
    new_ip=$(printf '%s' "$new_ip" | tr -d '[:space:]')
    if [ -z "$new_ip" ] || ! valid_ipv4_or_cidr "$new_ip"; then
        ui_err "Invalid IPv4/CIDR value."
        return 1
    fi
    if [ -n "$ips" ]; then
        while IFS= read -r item; do
            [ "$item" = "$new_ip" ] && { ui_info "${new_ip} is already allowed."; return 0; }
        done <<EOF
$(printf '%s' "$ips" | tr ',' '
')
EOF
        new_list="${ips},${new_ip}"
    else
        new_list="$new_ip"
    fi
    save_phpmyadmin_allowed_ips "$new_list"
    if ! apply_phpmyadmin_ip_config; then
        save_phpmyadmin_allowed_ips "$ips"
        apply_phpmyadmin_ip_config >/dev/null 2>&1 || true
        return 1
    fi
    ui_ok "Added ${new_ip} to phpMyAdmin public IP allowlist."
}

remove_phpmyadmin_public_ip() {
    local ips items=() ip pick index new_items=() new_list="" i
    ips=$(get_phpmyadmin_allowed_ips)
    if [ -z "$ips" ]; then
        ui_info "No public IPs are currently allowed for phpMyAdmin."
        return 0
    fi
    while IFS= read -r ip; do
        [ -n "$ip" ] && items+=("$ip")
    done <<EOF
$(printf '%s' "$ips" | tr ',' '
')
EOF
    if ! ui_pick_from_list "Which IP to remove" "${items[@]}"; then
        ui_info "Cancelled."
        return 0
    fi
    index="$UI_PICK_RESULT"
    for ((i = 0; i < ${#items[@]}; i++)); do
        [ "$i" -ne "$index" ] && new_items+=("${items[$i]}")
    done
    if [ "${#new_items[@]}" -gt 0 ]; then
        new_list=$(IFS=,; printf '%s' "${new_items[*]}")
    fi
    save_phpmyadmin_allowed_ips "$new_list"
    if ! apply_phpmyadmin_ip_config; then
        save_phpmyadmin_allowed_ips "$ips"
        apply_phpmyadmin_ip_config >/dev/null 2>&1 || true
        return 1
    fi
    ui_ok "Removed ${items[$index]} from phpMyAdmin public IP allowlist."
}

manage_phpmyadmin_public_ips() {
    local option
    while true; do
        show_logo
        ui_panel "PHPMYADMIN PUBLIC IP ACCESS" "$C_BOLD$C_CYAN" "$C_CYAN" \
            "${C_WHITE}Manage the public IPv4/CIDR addresses allowed to open phpMyAdmin.${C_RESET}"
        list_phpmyadmin_public_ips
        ui_menu_list "Public IP Access" \
            "${C_WHITE}[1]${C_RESET} View Allowed IPs" \
            "${C_WHITE}[2]${C_RESET} Add IP" \
            "${C_WHITE}[3]${C_RESET} Remove IP" \
            "${C_RED}[4]${C_RESET} Back"
        printf '  %s❯%s Select an option [1-4]: ' "$C_YELLOW" "$C_RESET"
        read -r option
        case "$option" in
            1)
                list_phpmyadmin_public_ips
                printf '  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"
                read -r
                ;;
            2)
                add_phpmyadmin_public_ip
                printf '  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"
                read -r
                ;;
            3)
                remove_phpmyadmin_public_ip
                printf '  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"
                read -r
                ;;
            4) return 0 ;;
            *) ui_err "Invalid option."; sleep 1 ;;
        esac
    done
}

rotate_bot_db_credentials() {
    local field="$1" new_value="$2"
    local mysql_root_pass env_key old_value escaped_new escaped_old
    mysql_root_pass=$(env_get MYSQL_ROOT_PASSWORD)

    if [ "$field" = "passworddb" ]; then
        env_key="DB_PASS"
        old_value="$SELECTED_DB_PASS"
    else
        env_key="DB_USER"
        old_value="$SELECTED_DB_USER"
    fi
    if [ "$SELECTED_BOT" = "main" ]; then
        if [ "$field" = "passworddb" ]; then
            env_key="MYSQL_PASSWORD"
        else
            env_key="MYSQL_USER"
        fi
    fi

    escaped_new=${new_value//\\/\\\\}
    escaped_new=${escaped_new//\'/\'\'}
    escaped_old=${old_value//\\/\\\\}
    escaped_old=${escaped_old//\'/\'\'}

    if [ "$field" = "passworddb" ]; then
        ui_action "Updating the MySQL password for '${SELECTED_DB_USER}'..."
        if ! dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
            "ALTER USER '${SELECTED_DB_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${escaped_new}'; FLUSH PRIVILEGES;" 2>&1; then
            ui_err "Failed to update the MySQL password — no changes were made to config.php."
            return 1
        fi
    else
        ui_action "Renaming the MySQL user '${SELECTED_DB_USER}' to '${new_value}'..."
        if ! dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
            "RENAME USER '${SELECTED_DB_USER}'@'%' TO '${new_value}'@'%'; FLUSH PRIVILEGES;" 2>&1; then
            ui_err "Failed to rename the MySQL user — no changes were made to config.php."
            return 1
        fi
    fi

    ui_action "Updating config.php for '${SELECTED_BOT}'..."
    if ! dc exec -T "$SELECTED_CONTAINER" php /var/www/faoxima/docker/php-update-credential.php "$field" "$new_value" 2>&1; then
        ui_err "Failed to update config.php — reverting the MySQL change."
        if [ "$field" = "passworddb" ]; then
            dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
                "ALTER USER '${SELECTED_DB_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${escaped_old}'; FLUSH PRIVILEGES;" >/dev/null 2>&1
        else
            dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
                "RENAME USER '${new_value}'@'%' TO '${SELECTED_DB_USER}'@'%'; FLUSH PRIVILEGES;" >/dev/null 2>&1
        fi
        return 1
    fi

    if ! file_env_set "$SELECTED_ENV_FILE" "$env_key" "$new_value"; then
        ui_err "Failed to update ${SELECTED_ENV_FILE} — reverting the credential change."
        dc exec -T "$SELECTED_CONTAINER" php /var/www/faoxima/docker/php-update-credential.php "$field" "$old_value" >/dev/null 2>&1 || true
        if [ "$field" = "passworddb" ]; then
            dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
                "ALTER USER '${SELECTED_DB_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${escaped_old}'; FLUSH PRIVILEGES;" >/dev/null 2>&1
        else
            dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
                "RENAME USER '${new_value}'@'%' TO '${SELECTED_DB_USER}'@'%'; FLUSH PRIVILEGES;" >/dev/null 2>&1
        fi
        return 1
    fi

    local verify_user="$SELECTED_DB_USER" verify_pass="$SELECTED_DB_PASS"
    [ "$field" = "usernamedb" ] && verify_user="$new_value"
    [ "$field" = "passworddb" ] && verify_pass="$new_value"
    if ! db_ready_check "$SELECTED_CONTAINER" "db" "$SELECTED_DB_NAME" "$verify_user" "$verify_pass" >/dev/null 2>&1; then
        ui_err "The new credentials could not connect — reverting all changes."
        file_env_set "$SELECTED_ENV_FILE" "$env_key" "$old_value" >/dev/null 2>&1 || true
        dc exec -T "$SELECTED_CONTAINER" php /var/www/faoxima/docker/php-update-credential.php "$field" "$old_value" >/dev/null 2>&1 || true
        if [ "$field" = "passworddb" ]; then
            dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
                "ALTER USER '${SELECTED_DB_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${escaped_old}'; FLUSH PRIVILEGES;" >/dev/null 2>&1
        else
            dc exec -T -e MYSQL_PWD="${mysql_root_pass}" db mysql -uroot -e \
                "RENAME USER '${new_value}'@'%' TO '${SELECTED_DB_USER}'@'%'; FLUSH PRIVILEGES;" >/dev/null 2>&1
        fi
        return 1
    fi

    SELECTED_DB_USER="$verify_user"
    SELECTED_DB_PASS="$verify_pass"
    ui_ok "Credentials updated successfully for '${SELECTED_BOT}'."
    show_db_credentials
}

change_db_username() {
    printf '\n  %s❯%s New database username (letters, numbers, underscore only): ' "$C_YELLOW" "$C_RESET"
    local new_user
    read -r new_user
    if [[ ! "$new_user" =~ ^[a-zA-Z0-9_]+$ ]] || [ "${#new_user}" -gt 32 ]; then
        ui_err "Invalid username format."
        return 1
    fi
    if [ "$new_user" = "$SELECTED_DB_USER" ]; then
        ui_info "The database username is already '${new_user}'."
        return 0
    fi
    printf '  %s❯%s Change username from '"'"'%s'"'"' to '"'"'%s'"'"'? (y/N): ' "$C_YELLOW" "$C_RESET" "$SELECTED_DB_USER" "$new_user"
    local confirm
    read -r confirm
    if [[ "${confirm,,}" != "y" ]]; then
        ui_info "Cancelled."
        return 0
    fi
    rotate_bot_db_credentials "usernamedb" "$new_user"
}

change_db_password() {
    printf '\n  %s❯%s Leave blank to auto-generate a strong password, or enter one: ' "$C_YELLOW" "$C_RESET"
    local new_pass
    read -r new_pass
    if [ -z "$new_pass" ]; then
        new_pass=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9')
    fi
    if [[ ! "$new_pass" =~ ^[a-zA-Z0-9_@%+=:,./?-]+$ ]]; then
        ui_err "Password contains unsupported characters."
        return 1
    fi
    printf '  %s❯%s Change the database password for '"'"'%s'"'"'? (y/N): ' "$C_YELLOW" "$C_RESET" "$SELECTED_DB_USER"
    local confirm
    read -r confirm
    if [[ "${confirm,,}" != "y" ]]; then
        ui_info "Cancelled."
        return 0
    fi
    rotate_bot_db_credentials "passworddb" "$new_pass"
}

menu_db_credentials() {
    show_logo
    ui_panel "DATABASE CREDENTIALS & INFO" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}View phpMyAdmin access and rotate database credentials for any bot.${C_RESET}" \
        "${C_DIM}Changes are applied to MySQL and config.php together, in real time.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    local SELECTED_BOT SELECTED_DB_NAME SELECTED_DB_USER SELECTED_DB_PASS
    local SELECTED_ENV_FILE SELECTED_CONFIG_PHP SELECTED_DOMAIN SELECTED_CONTAINER
    select_bot_for_credentials || return 0
    show_db_credentials

    ui_menu_list "Credentials" \
        "${C_WHITE}[1]${C_RESET} Change Username" \
        "${C_WHITE}[2]${C_RESET} Change Password" \
        "${C_WHITE}[3]${C_RESET} Manage phpMyAdmin Public IPs" \
        "${C_RED}[4]${C_RESET} Back"

    local option
    printf '  %s❯%s Select an option [1-4]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) change_db_username ;;
        2) change_db_password ;;
        3) manage_phpmyadmin_public_ips ;;
        4) return 0 ;;
        *) ui_err "Invalid option." ;;
    esac
}

service_action() {
    local action="$1"
    local names=("nginx" "db" "app") d
    redis_service_exists && names+=("redis")
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            names+=("app_$(basename "$d")")
        done
    fi

    if ! ui_pick_from_list "Which service to ${action}" "${names[@]}"; then
        ui_info "Cancelled."
        return 0
    fi

    local svc="${names[$UI_PICK_RESULT]}"
    if [ "$action" != "start" ]; then
        printf '  %s❯%s %s %s? (y/N): ' "$C_YELLOW" "$C_RESET" "$action" "$svc"
        local confirm
        read -r confirm
        if [[ "${confirm,,}" != "y" ]]; then
            ui_info "Cancelled."
            return 0
        fi
    fi

    ui_action "Running: docker compose ${action} ${svc}..."
    if dc "$action" "$svc"; then
        ui_ok "${svc} ${action} succeeded."
    else
        ui_err "Failed to ${action} ${svc}."
        return 1
    fi
}

service_logs() {
    local names=("nginx" "db" "app") d
    redis_service_exists && names+=("redis")
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            names+=("app_$(basename "$d")")
        done
    fi

    if ! ui_pick_from_list "Which service's logs" "${names[@]}"; then
        ui_info "Cancelled."
        return 0
    fi

    dc logs --tail=200 "${names[$UI_PICK_RESULT]}"
}

service_status_all() {
    local rows=("nginx|$(service_state nginx)" "db|$(service_state db)" "app (main, incl. PHP-FPM)|$(service_state app)")
    if redis_service_exists; then
        rows+=("redis|$(service_state redis)")
    fi

    local bot_names=() d
    if [ -d "$BOTS_DIR" ]; then
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            bot_names+=("$(basename "$d")")
        done
    fi

    local total="${#bot_names[@]}"
    local page_size=20
    local page="${1:-0}"
    local last_page=$(( (total - 1) / page_size ))
    [ "$last_page" -lt 0 ] && last_page=0

    if [ "$total" -gt 0 ]; then
        local start=$((page * page_size))
        local end=$((start + page_size))
        [ "$end" -gt "$total" ] && end="$total"
        local i name
        for ((i = start; i < end; i++)); do
            name="${bot_names[$i]}"
            rows+=("app_${name}|$(service_state "app_${name}")")
        done
    fi

    ui_status_table "Service Status" "$C_CYAN" "${rows[@]}"

    if [ "$last_page" -gt 0 ]; then
        printf '  %s(additional bots — page %d of %d)%s\n' "$C_DIM" "$((page + 1))" "$((last_page + 1))" "$C_RESET"
        local nav_hint=""
        [ "$page" -lt "$last_page" ] && nav_hint="${nav_hint}n) next page  "
        [ "$page" -gt 0 ] && nav_hint="${nav_hint}p) previous page  "
        printf '\n  %s❯%s %s(Enter to continue): ' "$C_YELLOW" "$C_RESET" "$nav_hint"
        local nav
        read -r nav
        case "$nav" in
            n|N)
                [ "$page" -lt "$last_page" ] && page=$((page + 1))
                show_logo
                service_status_all "$page"
                return
                ;;
            p|P)
                [ "$page" -gt 0 ] && page=$((page - 1))
                show_logo
                service_status_all "$page"
                return
                ;;
        esac
    fi
}

menu_service_management() {
    show_logo
    ui_panel "SERVICE STATUS & MANAGEMENT" "$C_BOLD$C_CYAN" "$C_CYAN" \
        "${C_WHITE}Start, stop, restart, or view logs for any container.${C_RESET}" \
        "${C_DIM}PHP-FPM runs inside the app container — there is no separate PHP-FPM service.${C_RESET}"

    if [ ! -f "$COMPOSE_FILE" ]; then
        ui_err "Faoxima Bot is not installed."
        return 1
    fi

    service_status_all

    ui_menu_list "Service Management" \
        "${C_WHITE}[1]${C_RESET} Start a Service" \
        "${C_WHITE}[2]${C_RESET} Stop a Service" \
        "${C_WHITE}[3]${C_RESET} Restart a Service" \
        "${C_WHITE}[4]${C_RESET} View Logs" \
        "${C_RED}[5]${C_RESET} Back"

    local option
    printf '  %s❯%s Select an option [1-5]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) service_action start ;;
        2) service_action stop ;;
        3) service_action restart ;;
        4) service_logs ;;
        5) return 0 ;;
        *) ui_err "Invalid option." ;;
    esac
}

get_latest_version() {
    local tag
    tag=$(curl -s --max-time 4 "https://api.github.com/repos/${FAOXIMA_REPO}/releases/latest" 2>/dev/null \
        | grep -o '"tag_name"[^,]*' | head -1 | cut -d'"' -f4)
    [ -n "$tag" ] && printf '%s' "$tag" || printf 'unknown'
}

webhook_field() {
    local json="$1" field="$2"
    printf '%s' "$json" | grep -o "\"${field}\":[^,}]*" | head -1 | sed -E 's/^"[^"]+": ?"?([^"}]*)"?$/\1/'
}

service_state() {
    local service="$1" cid info running restart_count started_at started_ts now_ts uptime
    cid=$(dc ps -q "$service" 2>/dev/null)
    if [ -z "$cid" ]; then
        printf '%s● inactive%s' "$C_RED" "$C_RESET"
        return
    fi

    info=$(docker inspect -f '{{.State.Running}}|{{.RestartCount}}|{{.State.StartedAt}}' "$cid" 2>/dev/null)
    running="${info%%|*}"
    info="${info#*|}"
    restart_count="${info%%|*}"
    started_at="${info#*|}"

    if [ "$running" != "true" ]; then
        printf '%s● inactive%s' "$C_RED" "$C_RESET"
        return
    fi

    if [[ "$restart_count" =~ ^[0-9]+$ ]] && [ "$restart_count" -ge 3 ]; then
        started_ts=$(date -d "$started_at" +%s 2>/dev/null || echo 0)
        now_ts=$(date +%s)
        uptime=$((now_ts - started_ts))
        if [ "$uptime" -lt 60 ]; then
            printf '%s● crash-looping (%s restarts)%s' "$C_RED" "$restart_count" "$C_RESET"
            return
        fi
    fi

    printf '%s● active%s' "$C_GREEN" "$C_RESET"
}

show_menu() {
    local UI_FIXED_WIDTH
    UI_FIXED_WIDTH=$(ui_term_width)

    local installed=0
    [ -f "$ENV_FILE" ] && [ -f "$COMPOSE_FILE" ] && installed=1

    local latest_version
    latest_version=$(cache_get "latest_version" 1800)
    if [ -z "$latest_version" ]; then
        latest_version=$(get_latest_version)
        cache_set "latest_version" "$latest_version"
    fi
    local installed_version latest_line
    installed_version=$(get_installed_version)
    if [ "$latest_version" = "unknown" ]; then
        latest_line="${C_CYAN}Latest${C_RESET}    : ${C_DIM}unknown${C_RESET}"
    elif [ "${latest_version#v}" = "${installed_version#v}" ]; then
        latest_line="${C_CYAN}Latest${C_RESET}    : ${C_GREEN}${latest_version} (up to date)${C_RESET}"
    elif version_is_newer "$latest_version" "$installed_version"; then
        latest_line="${C_CYAN}Latest${C_RESET}    : ${C_YELLOW}${latest_version} (update available!)${C_RESET}"
    else
        latest_line="${C_CYAN}Latest${C_RESET}    : ${C_GREEN}${latest_version} (latest official)${C_RESET}"
    fi

    local bot_token="" wh_url="" wh_pending=""
    if [ "$installed" -eq 1 ]; then
        bot_token=$(env_get TELEGRAM_BOT_TOKEN)
        if [ -n "$bot_token" ]; then
            local wh_json
            wh_json=$(cache_get "webhook_info" 30)
            if [ -z "$wh_json" ]; then
                wh_json=$(curl -s --max-time 4 "https://api.telegram.org/bot${bot_token}/getWebhookInfo" 2>/dev/null)
                cache_set "webhook_info" "$wh_json"
            fi
            wh_url=$(webhook_field "$wh_json" "url")
            wh_pending=$(webhook_field "$wh_json" "pending_update_count")
        fi
    fi

    show_animated_logo "$latest_line"

    local bot_state domain pma_state
        if [ "$installed" -eq 1 ]; then
            bot_state="${C_GREEN}● installed${C_RESET}"
            domain=$(normalize_domain "$(env_get DOMAIN)")
            pma_state="${C_DIM}not enabled${C_RESET}"
            local pma_running
            pma_running=$(cache_get "pma_running" 30)
            if [ -z "$pma_running" ]; then
                if dc ps -q phpmyadmin >/dev/null 2>&1 && [ -n "$(dc ps -q phpmyadmin 2>/dev/null)" ]; then
                    pma_running="1"
                else
                    pma_running="0"
                fi
                cache_set "pma_running" "$pma_running"
            fi
            [ "$pma_running" = "1" ] && pma_state="${C_GREEN}https://${domain}/phpmyadmin/${C_RESET}"

            local ssl_line="${C_DIM}— not issued${C_RESET}"
            local cert_enddate
            cert_enddate=$(cache_get "cert_enddate" 600)
            if [ -z "$cert_enddate" ]; then
                cert_enddate=$(get_cert_enddate "$domain")
                cache_set "cert_enddate" "$cert_enddate"
            fi
            if [ -n "$cert_enddate" ]; then
                local expiry_ts now_ts days_left
                expiry_ts=$(date -d "$cert_enddate" +%s 2>/dev/null || echo 0)
                now_ts=$(date +%s)
                if [ "$expiry_ts" -gt 0 ]; then
                    days_left=$(( (expiry_ts - now_ts) / 86400 ))
                    if [ "$days_left" -gt 0 ]; then
                        ssl_line="${C_GREEN}● valid (${days_left} day$([ "$days_left" -ne 1 ] && printf 's') left)${C_RESET}"
                    else
                        ssl_line="${C_RED}● expired${C_RESET}"
                    fi
                fi
            fi
            ui_section "Bot Status" \
                "State|${bot_state}" \
                "SSL|${ssl_line}" \
                "Domain|${C_WHITE}https://${domain}/faoxima${C_RESET}" \
                "phpMyAdmin|${pma_state}"

            if [ -n "$bot_token" ]; then
                ui_section "Webhook" \
                    "URL|${C_GREEN}● set${C_RESET}  ${C_DIM}${wh_url:-unknown}${C_RESET}" \
                    "Pending|${C_WHITE}${wh_pending:-0} update(s)${C_RESET}"
            fi
        else
            ui_section "Bot Status" \
                "State|${C_RED}● not installed${C_RESET}"
        fi

        local os_name docker_ver compose_ver
        os_name=$(cache_get "os_name" 3600)
        if [ -z "$os_name" ]; then
            os_name=$( . /etc/os-release 2>/dev/null && echo "$PRETTY_NAME" )
            cache_set "os_name" "$os_name"
        fi
        docker_ver=$(cache_get "docker_ver" 3600)
        if [ -z "$docker_ver" ]; then
            docker_ver=$(docker --version 2>/dev/null | sed -E 's/^Docker version ([^,]+),.*/\1/')
            cache_set "docker_ver" "$docker_ver"
        fi
        compose_ver=$(cache_get "compose_ver" 3600)
        if [ -z "$compose_ver" ]; then
            compose_ver=$(docker compose version --short 2>/dev/null)
            cache_set "compose_ver" "$compose_ver"
        fi
        if [ "$installed" -eq 1 ]; then
            ui_section "System" \
                "OS|${C_WHITE}${os_name:-unknown}${C_RESET}" \
                "Docker|${C_WHITE}${docker_ver:-unknown}${C_RESET}" \
                "Compose|${C_WHITE}${compose_ver:-unknown}${C_RESET}" \
                "nginx|$(service_state nginx)" \
                "app|$(service_state app)" \
                "db|$(service_state db)"
        else
            ui_section "System" \
                "OS|${C_WHITE}${os_name:-unknown}${C_RESET}" \
                "Docker|${C_WHITE}${docker_ver:-unknown}${C_RESET}" \
                "Compose|${C_WHITE}${compose_ver:-unknown}${C_RESET}"
        fi

        local ram disk cpu_load uptime_str
        ram=$(free -m 2>/dev/null | awk '/^Mem:/{printf "%sMB / %sMB (%.0f%%)", $3, $2, ($3*100)/$2}')
        disk=$(df -h / 2>/dev/null | awk 'NR==2{printf "%s / %s (%s)", $3, $2, $5}')
        cpu_load=$(uptime 2>/dev/null | awk -F'load average:' '{print $2}' | sed 's/^ *//')
        uptime_str=$(uptime -p 2>/dev/null)
        ui_section "Resources" \
            "RAM|${C_WHITE}${ram:-unknown}${C_RESET}" \
            "Disk|${C_WHITE}${disk:-unknown}${C_RESET}" \
            "CPU load|${C_WHITE}${cpu_load:-unknown}${C_RESET}" \
            "Uptime|${C_WHITE}${uptime_str:-unknown}${C_RESET}"

        ui_menu_list "Menu" \
            "${C_WHITE}[1]${C_RESET} Bot" \
            "${C_WHITE}[2]${C_RESET} Additional Bots" \
            "${C_WHITE}[3]${C_RESET} Database" \
            "${C_WHITE}[4]${C_RESET} Domain & SSL" \
            "${C_WHITE}[5]${C_RESET} Maintenance" \
            "${C_RED}[6]${C_RESET} Exit"

    local option
    printf '  %s❯%s Select a category [1-6]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) menu_bot ;;
        2) menu_additional_bots ;;
        3) menu_database ;;
        4) menu_domain_ssl ;;
        5) menu_maintenance ;;
        6)
            ui_ok "Exiting... goodbye!"
            exit 0
            ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            ;;
    esac
    show_menu
}

menu_bot() {
    show_logo
    ui_menu_list "Bot" \
        "${C_WHITE}[1]${C_RESET} Install Faoxima Bot" \
        "${C_YELLOW}[2]${C_RESET} Install Beta" \
        "${C_WHITE}[3]${C_RESET} Update Faoxima Bot" \
        "${C_WHITE}[4]${C_RESET} Remove Faoxima Bot" \
        "${C_RED}[5]${C_RESET} Back to main menu"

    local option
    printf '  %s❯%s Select an option [1-5]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) install_bot ;;
        2) install_beta_bot ;;
        3) update_bot ;;
        4) remove_bot ;;
        5) return 0 ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            menu_bot
            return 0
            ;;
    esac
    printf '\n  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"; read -r
}

menu_additional_bots() {
    show_logo

    if [ -d "$BOTS_DIR" ] && [ -n "$(find "$BOTS_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)" ]; then
        local all_names=() d
        for d in "${BOTS_DIR}"/*/; do
            [ -d "$d" ] || continue
            all_names+=("$(basename "$d")")
        done

        local preview_limit=20
        local shown="${#all_names[@]}"
        [ "$shown" -gt "$preview_limit" ] && shown="$preview_limit"

        local rows=() name domain domain_line i
        for ((i = 0; i < shown; i++)); do
            name="${all_names[$i]}"
            domain=""
            [ -f "${BOTS_DIR}/${name}/.env" ] && domain=$(grep -E '^DOMAIN=' "${BOTS_DIR}/${name}/.env" | tail -1 | cut -d'=' -f2-)
            if [ -n "$domain" ]; then
                domain_line="https://${domain}/${name}"
            else
                domain_line="unknown"
            fi
            local bot_version
            bot_version=$(get_additional_bot_version "$name")
            rows+=("${name} (${bot_version})|${C_GREEN}${domain_line}${C_RESET}")
        done
        ui_status_table "Installed Additional Bots (${#all_names[@]} total)" "$C_CYAN" "${rows[@]}"
        if [ "${#all_names[@]}" -gt "$preview_limit" ]; then
            ui_info "Showing the first ${preview_limit} of ${#all_names[@]} — use 'List Additional Bots' below to page through all of them."
        fi
    fi

    ui_menu_list "Additional Bots" \
        "${C_WHITE}[1]${C_RESET} Install Additional Bot" \
        "${C_YELLOW}[2]${C_RESET} Install Beta" \
        "${C_WHITE}[3]${C_RESET} List Additional Bots" \
        "${C_WHITE}[4]${C_RESET} Update Additional Bots" \
        "${C_RED}[5]${C_RESET} Remove Additional Bot" \
        "${C_RED}[6]${C_RESET} Back to main menu"

    local option
    printf '  %s❯%s Select an option [1-6]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) install_additional_bot ;;
        2) install_beta_additional_bot ;;
        3) list_additional_bots ;;
        4) menu_update_additional_bots ;;
        5) remove_additional_bot ;;
        6) return 0 ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            menu_additional_bots
            return 0
            ;;
    esac
    printf '\n  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"; read -r
}

menu_database() {
    show_logo
    ui_menu_list "Database" \
        "${C_WHITE}[1]${C_RESET} Export Database — All Bots (Main + Additional)" \
        "${C_WHITE}[2]${C_RESET} Export Database — Single Bot" \
        "${C_WHITE}[3]${C_RESET} Import Database" \
        "${C_WHITE}[4]${C_RESET} Optimize Database & Server" \
        "${C_WHITE}[5]${C_RESET} Install / Enable Redis" \
        "${C_RED}[6]${C_RESET} Back to main menu"

    local option
    printf '  %s❯%s Select an option [1-6]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) export_database_all ;;
        2) export_database_single ;;
        3) import_database ;;
        4) optimize_database ;;
        5) install_redis ;;
        6) return 0 ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            menu_database
            return 0
            ;;
    esac
    printf '\n  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"; read -r
}

menu_domain_ssl() {
    show_logo
    ui_menu_list "Domain & SSL" \
        "${C_WHITE}[1]${C_RESET} Renew SSL Certificates" \
        "${C_WHITE}[2]${C_RESET} Change Domain" \
        "${C_WHITE}[3]${C_RESET} Enable SSL Auto-Renewal" \
        "${C_RED}[4]${C_RESET} Back to main menu"

    local option
    printf '  %s❯%s Select an option [1-4]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) renew_ssl ;;
        2) change_domain ;;
        3) enable_ssl_auto_renew ;;
        4) return 0 ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            menu_domain_ssl
            return 0
            ;;
    esac
    printf '\n  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"; read -r
}

menu_maintenance() {
    show_logo
    ui_menu_list "Maintenance" \
        "${C_WHITE}[1]${C_RESET} View Error Logs" \
        "${C_WHITE}[2]${C_RESET} Increase Upload Limit" \
        "${C_WHITE}[3]${C_RESET} Database Credentials & Info" \
        "${C_WHITE}[4]${C_RESET} Service Status & Management" \
        "${C_WHITE}[5]${C_RESET} Set Max Database Connections" \
        "${C_RED}[6]${C_RESET} Back to main menu"

    local option
    printf '  %s❯%s Select an option [1-6]: ' "$C_YELLOW" "$C_RESET"
    read -r option

    case "$option" in
        1) view_error_logs ;;
        2) increase_upload_limit ;;
        3) menu_db_credentials ;;
        4) menu_service_management ;;
        5) set_max_connections ;;
        6) return 0 ;;
        *)
            ui_err "Invalid option. Please try again."
            sleep 2
            menu_maintenance
            return 0
            ;;
    esac
    printf '\n  %s❯%s Press Enter to continue... ' "$C_YELLOW" "$C_RESET"; read -r
}

process_arguments() {
    if [ "$1" = "--ssl-auto-renew-check" ]; then
        ssl_auto_renew_check
        exit 0
    fi

    install_docker

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
                    ui_err "Please specify a version with -v (e.g. -v 1.0.0)"
                    exit 1
                fi
            fi
            ;;
        -beta|--beta)
            install_bot "-beta"
            ;;
        *)
            show_menu
            ;;
    esac
}

process_arguments "${1:-}" "${2:-}"
