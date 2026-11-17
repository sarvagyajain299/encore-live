#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# Encore Live task runner for WSL, Git Bash, Linux and macOS.
# Windows PowerShell twin: scripts/encore.ps1 (same commands).
#
#   scripts/encore.sh up | down | install | wp <args> | composer <args>
#                     phpcs | phpcbf | test | seed | reset | shell | destroy
# -----------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")/.."

# Git Bash rewrites "/scripts/x" into "C:/Program Files/Git/scripts/x".
# Turn that off so container paths reach Docker untouched.
export MSYS_NO_PATHCONV=1

# Allocate a TTY only when we have one (keeps CI and pipes working).
run_flags=(--rm)
[ -t 0 ] && [ -t 1 ] || run_flags+=(-T)

dc() { docker compose "$@"; }

ensure_env() {
	if [ ! -f .env ]; then
		cp .env.example .env
		echo "Created .env from .env.example. Change the passwords in it before sharing anything."
	fi
}

# vendor/ is a Docker volume, so check for it inside the container, not on the host.
ensure_vendor() {
	dc run "${run_flags[@]}" tools sh -c 'test -f vendor/autoload.php || composer install --no-interaction'
}

cmd="${1:-help}"
shift || true

case "$cmd" in
	up)
		ensure_env
		dc up -d --wait
		echo "Site: http://localhost:8080  phpMyAdmin: http://localhost:8081  Mailpit: http://localhost:8025"
		;;
	down)     dc down ;;
	install)
		# The plugin's PSR-4 autoloader is generated, not committed.
		dc run "${run_flags[@]}" tools composer install --no-interaction --no-dev -d wp-content/plugins/encore-core
		dc run "${run_flags[@]}" wpcli sh /scripts/install.sh
		;;
	wp)       dc run "${run_flags[@]}" wpcli wp "$@" ;;
	composer) dc run "${run_flags[@]}" tools composer "$@" ;;
	phpcs)    ensure_vendor; dc run "${run_flags[@]}" tools vendor/bin/phpcs "$@" ;;
	phpcbf)   ensure_vendor; dc run "${run_flags[@]}" tools vendor/bin/phpcbf "$@" ;;
	test)     ensure_vendor; dc run "${run_flags[@]}" tools vendor/bin/phpunit "$@" ;;
	seed)     dc run "${run_flags[@]}" wpcli wp encore seed "$@" ;;   # command arrives in Phase 1
	reset)    dc run "${run_flags[@]}" wpcli wp encore reset "$@" ;;  # command arrives in Phase 1
	shell)    dc exec wordpress bash ;;
	# TODO(sarvagya): add a `logs` command that follows one service's logs.
	#   Usage: scripts/encore.sh logs wordpress   (no service = all services)
	#   Hint: `docker compose logs -f --tail=100` takes optional service names; pass "$@".
	#   Then add the same command to scripts/encore.ps1 and the Makefile.
	destroy)
		read -r -p "This deletes the database and WordPress volumes. Type 'yes' to continue: " answer
		[ "$answer" = "yes" ] && dc down -v || echo "Cancelled."
		;;
	help|*)
		sed -n '2,8p' "$0"
		;;
esac
