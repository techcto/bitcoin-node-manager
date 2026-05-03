#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "$ENV_FILE" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a
fi

TAG_RELEASE="${TAG_RELEASE:-$(date +"%y.%m%d.%S")}"
SOLODEV_RELEASE="${SOLODEV_RELEASE:-$TAG_RELEASE}"
AWS_PROFILE="${AWS_PROFILE:-develop}"

readonly ROOT_DIR ENV_FILE TAG_RELEASE SOLODEV_RELEASE AWS_PROFILE

usage() {
    cat <<'EOF'
Usage: ./cmd.sh <command> [args]

Commands:
  help                   Show this help
  bundle                 Build the submodule bundle artifact
  upstream-status        Show origin/upstream remote and branch status
  upstream-add           Add the Mirobit repo as upstream
  upstream-fetch         Fetch upstream refs
  upstream-log           Show recent upstream commits not yet in this branch
  upstream-diff          Show file-level diff summary between this branch and upstream/master
  upstream-pull          Fast-forward merge upstream/master into current branch
EOF
}

has_upstream() {
    git remote get-url upstream >/dev/null 2>&1
}

bundle() {
    docker-compose -f docker-compose.bundle.yml up --build
}

upstream-status() {
    git remote -v
    echo
    git branch -vv
}

upstream-add() {
    if has_upstream; then
        echo "upstream already configured"
        git remote get-url upstream
        return 0
    fi

    git remote add upstream https://github.com/Mirobit/bitcoin-node-manager.git
    git remote get-url upstream
}

upstream-fetch() {
    has_upstream || upstream-add
    git fetch upstream
}

upstream-log() {
    has_upstream || upstream-add
    git log --oneline HEAD..upstream/master | sed -n '1,20p'
}

upstream-diff() {
    has_upstream || upstream-add
    git diff --stat HEAD..upstream/master
}

upstream-pull() {
    has_upstream || upstream-add
    git fetch upstream
    git merge --ff-only upstream/master
}

command_name="${1:-help}"
shift || true

case "$command_name" in
    help|-h|--help) usage ;;
    bundle) bundle ;;
    upstream-status) upstream-status ;;
    upstream-add) upstream-add ;;
    upstream-fetch) upstream-fetch ;;
    upstream-log) upstream-log ;;
    upstream-diff) upstream-diff ;;
    upstream-pull) upstream-pull ;;
    *)
        echo "Unknown command: $command_name" >&2
        echo
        usage
        exit 1
        ;;
esac
